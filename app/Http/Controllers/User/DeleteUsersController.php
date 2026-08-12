<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DeleteUsersController extends Controller
{
    public function destroy(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string',
            ]);

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect password.',
                ], 403);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('User deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account.',
            ], 500);
        }
    }
}