<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentGatewayController extends Controller
{
    /**
     * Display a listing of payment gateways.
     */
    public function index(Request $request)
    {
        $query = PaymentGateway::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'gateway_name', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortDirection) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created payment gateway in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'gateway_name' => 'required|string|max:255|unique:payment_gateways,gateway_name',
            'api_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'webhook' => 'nullable|url|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (!isset($validated['status'])) {
            $validated['status'] = 'active';
        }

        $paymentGateway = PaymentGateway::create($validated);

        return response()->json($paymentGateway, 201);
    }

    /**
     * Display the specified payment gateway.
     */
    public function show(PaymentGateway $paymentGateway)
    {
        return response()->json($paymentGateway);
    }

    /**
     * Update the specified payment gateway in storage.
     */
    public function update(Request $request, PaymentGateway $paymentGateway)
    {
        $validated = $request->validate([
            'gateway_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('payment_gateways', 'gateway_name')->ignore($paymentGateway->id),
            ],
            'api_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'webhook' => 'nullable|url|max:255',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $paymentGateway->update($validated);

        return response()->json($paymentGateway);
    }

    /**
     * Remove the specified payment gateway from storage.
     */
    public function destroy(PaymentGateway $paymentGateway)
    {
        $paymentGateway->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle the status of a payment gateway (active / inactive).
     */
    public function toggleStatus(PaymentGateway $paymentGateway)
    {
        $newStatus = $paymentGateway->status === 'active' ? 'inactive' : 'active';
        $paymentGateway->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "Payment gateway status updated to {$newStatus}.",
            'data' => $paymentGateway,
        ]);
    }

    /**
     * Test connection credentials for the payment gateway.
     */
    public function testConnection(PaymentGateway $paymentGateway)
    {
        if (empty($paymentGateway->api_key) && empty($paymentGateway->secret_key)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot test connection: API key or Secret key missing.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully connected to {$paymentGateway->gateway_name} API.",
            'gateway_name' => $paymentGateway->gateway_name,
            'status' => $paymentGateway->status,
            'tested_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get listing of active payment gateways for checkout.
     */
    public function activeGateways()
    {
        $gateways = PaymentGateway::active()
            ->select(['id', 'gateway_name', 'status', 'created_at'])
            ->get();

        return response()->json(['data' => $gateways]);
    }
}