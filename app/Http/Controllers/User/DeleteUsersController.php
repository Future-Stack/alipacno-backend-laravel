<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class DeleteUsersController extends Controller
{


    public function changePassword(Request $request)
        {
            try {
                $request->validate([
                    'current_password' => 'required',
                    'new_password'     => ['required', 'confirmed', Password::min(8)],
                ]);

                $user = $request->user();

                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect.',
                    ], 400);
                }

                $user->update([
                    'password' => Hash::make($request->new_password),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Password changed successfully.',
                ], 200);

            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first(),
                ], 422);
            } catch (\Exception $e) {
                Log::error('Change Password Error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to change password. Please try again.',
                ], 500);
            }
        }

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