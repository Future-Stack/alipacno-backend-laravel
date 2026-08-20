<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

//    public function handleWebhook(Request $request)
//    {
//        $payload = $request->getContent();
//        $sigHeader = $request->header('Stripe-Signature');
//
//        try {
//            $event = \Stripe\Webhook::constructEvent(
//                $payload,
//                $sigHeader,
//                config('services.stripe.secret')
//            );
//
//            Log::info('event: ' . $event->type);
//
//
//            if ($event->type === 'payment_intent.succeeded') {
//                $intent = $event->data->object;
//                $paymentId = $intent->metadata->payment_id ?? null;
//
//                Log::info("Payment ID: " . $paymentId);
//
//                if ($paymentId) {
//                    $payment = Payment::find($paymentId);
//
//                    if ($payment && $payment->status !== 'paid') {
//                        $payment->update([
//                            'status' => 'paid',
//                        ]);
//
//                        $booking = Booking::find($payment->booking_id);
//
//                        if ($booking && $booking->time_slot_id) {
//                            TimeSlot::where('id', $booking->time_slot_id)->update([
//                                'is_booked' => true,
//                            ]);
//                        }
//
//                        Vendor::where('id', $booking->vendor_id)
//                            ->increment('account_balance', $vendor_earning->net_amount);
//                    }
//                }
//            }
//
//            return response('OK', 200);
//
//        } catch (\Exception $e) {
//            return response('Webhook Error: ' . $e->getMessage(), 400);
//        }
//    }
}
