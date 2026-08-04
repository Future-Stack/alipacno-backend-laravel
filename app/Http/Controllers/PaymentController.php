<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['order.user', 'order.branch']);

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhere('stripe_payment_intent', 'like', "%{$search}%");
            });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created payment record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|string|max:50',
            'stripe_payment_intent' => 'nullable|string|max:255',
            'transaction_id' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'status' => 'nullable|in:pending,successful,failed,refunded',
        ]);

        if (empty($validated['transaction_id'])) {
            $validated['transaction_id'] = 'TXN-' . strtoupper(Str::random(10));
        }

        if (!isset($validated['currency'])) {
            $validated['currency'] = 'GBP';
        }

        $status = $validated['status'] ?? 'successful';
        $validated['status'] = $status;

        if ($status === 'successful') {
            $validated['paid_at'] = now();
            Order::where('id', $validated['order_id'])->update([
                'payment_status' => 'paid',
                'payment_method' => $validated['payment_method'],
            ]);
        }

        $payment = Payment::create($validated);

        return response()->json($payment->load(['order']), 201);
    }

    /**
     * Display the specified payment record.
     */
    public function show(Payment $payment)
    {
        return response()->json($payment->load(['order.user', 'order.branch', 'order.items']));
    }

    /**
     * Update the specified payment record.
     */
    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'payment_method' => 'sometimes|string|max:50',
            'transaction_id' => 'nullable|string|max:255',
            'status' => 'required|in:pending,successful,failed,refunded',
        ]);

        $status = $validated['status'];

        if ($status === 'successful') {
            $validated['paid_at'] = now();
            $payment->order()->update(['payment_status' => 'paid']);
        } elseif ($status === 'refunded') {
            $payment->order()->update(['payment_status' => 'refunded']);
        }

        $payment->update($validated);

        return response()->json($payment->load(['order']));
    }

    /**
     * Remove the specified payment record.
     */
    public function destroy(Payment $payment)
    {
        $payment->delete();

        return response()->json(null, 204);
    }

    /**
     * Process refund for a payment.
     */
    public function processRefund(Request $request, Payment $payment)
    {
        if ($payment->status === 'refunded') {
            return response()->json(['message' => 'Payment has already been refunded.'], 422);
        }

        $payment->update([
            'status' => 'refunded',
        ]);

        $payment->order()->update([
            'payment_status' => 'refunded',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment refunded successfully.',
            'payment' => $payment->fresh(['order']),
        ]);
    }
}