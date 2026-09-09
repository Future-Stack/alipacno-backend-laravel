<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeController extends Controller
{
    public function OrderSuccess(Request $request)
    {
        return response()->json([
            'success' => true,
            'session_id' => $request->query('session_id'),
            'message' => 'Booking payment successful. Final confirmation will be handled by webhook.',
        ]);
    }

    public function OrderCancel()
    {
        return response()->json([
            'success' => false,
            'message' => 'Payment cancelled by user.',
        ]);
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $this->handleCheckoutSessionCompleted($session);
                break;

            case 'checkout.session.async_payment_succeeded':
                $session = $event->data->object;
                $this->handleCheckoutSessionCompleted($session);
                break;

            case 'checkout.session.async_payment_failed':
                $session = $event->data->object;
                $this->handleAsyncPaymentFailed($session);
                break;

            default:
                Log::info('Received unhandled event type: ' . $event->type);
        }

        return response()->json(['status' => 'success'], 200);
    }

    protected function handleCheckoutSessionCompleted($session)
    {
        // Verify that the payment was actually settled
        if ($session->payment_status !== 'paid') {
            Log::info("Checkout Session {$session->id} completed, but payment status is {$session->payment_status}.");
            return;
        }

        $paymentId = $session->metadata->payment_id ?? null;
        $orderId = $session->metadata->order_id ?? null;

        if (!$paymentId) {
            Log::error("Missing payment_id in metadata for session {$session->id}");
            return;
        }

        if (!$orderId) {
            Log::error("Missing order_id in metadata for session {$session->id}");
            return;
        }

        $payment = Payment::find($paymentId);
        $order = Order::find($orderId);

        if (!$payment) {
            Log::error("Payment not found for ID {$paymentId}");
            return;
        }

        if (!$order) {
            Log::error("Order not found for ID {$paymentId}");
            return;
        }

        // Idempotency check: prevent processing the same payment twice
        if ($payment->status === 'successful') {
            return;
        }

        if ($order->payment_status === 'paid') {
            return;
        }

        $payment->update([
            'status' => 'successful',
            'paid_at' => now()
        ]);

        $order->update([
            'payment_status' => 'paid'
        ]);

        Log::info("Payment {$paymentId} successfully marked as paid.");
    }

    protected function handleAsyncPaymentFailed($session)
    {
        $paymentId = $session->metadata->payment_id ?? null;
        if ($paymentId) {
            Payment::where('id', $paymentId)->update(['status' => 'failed']);
        }
    }
}
