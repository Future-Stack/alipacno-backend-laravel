<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
}
