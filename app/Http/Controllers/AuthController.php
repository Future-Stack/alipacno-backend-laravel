<?php

namespace App\Http\Controllers;

use App\Mail\SendOtpMail;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user & send 5-digit OTP verification email.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'avatar' => 'nullable',
            'user_image' => 'nullable',
            'user_type' => 'nullable|string|in:customer,admin,staff,super_admin,hq_admin,branch_admin',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['user_type'] = $validated['user_type'] ?? 'customer';
        $validated['status'] = 'active';
        $validated['email_verified_at'] = null; // Unverified until OTP confirmation

        // Handle avatar image file upload if present
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpeg,png,jpg,webp,gif|max:2048']);
            $validated['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        // Handle user_image file upload if present
        if ($request->hasFile('user_image')) {
            $request->validate(['user_image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:2048']);
            $validated['user_image'] = $request->file('user_image')->store('users/images', 'public');
        }

        $user = User::create($validated);

        // Generate 5-digit OTP code
        $otpCode = sprintf('%05d', mt_rand(0, 99999));

        // Invalidate old registration OTPs for this email
        Otp::where('email', $user->email)->where('type', 'registration')->delete();

        // Create new OTP record
        Otp::create([
            'email' => $user->email,
            'otp' => $otpCode,
            'type' => 'registration',
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP Email
        try {
            Mail::to($user->email)->send(new SendOtpMail($otpCode, 'registration', $user->name));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Registration OTP Email Error: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Registration successful! A 5-digit verification code has been sent to your email.',
            'user' => $user,
            'otp_sent' => true,
        ], 201);
    }

    /**
     * Verify registration 5-digit OTP code & complete account activation.
     */
    public function verifyRegistrationOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:5',
        ]);

        $otpRecord = Otp::where('email', $request->email)
            ->where('type', 'registration')
            ->latest()
            ->first();

        if (!$otpRecord || !$otpRecord->isValid($request->otp)) {
            return response()->json([
                'message' => 'Invalid or expired OTP verification code.'
            ], 422);
        }

        // Mark OTP as used
        $otpRecord->update(['used_at' => now()]);

        // Mark user email as verified
        $user = User::where('email', $request->email)->first();
        $user->email_verified_at = now();
        $user->save();

        $user->load('role.permissions');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully!',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Send forgot password 5-digit OTP to user email.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate 5-digit OTP code
        $otpCode = sprintf('%05d', mt_rand(0, 99999));

        // Invalidate old forgot_password OTPs
        Otp::where('email', $user->email)->where('type', 'forgot_password')->delete();

        // Save new OTP record
        Otp::create([
            'email' => $user->email,
            'otp' => $otpCode,
            'type' => 'forgot_password',
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP Email
        try {
            Mail::to($user->email)->send(new SendOtpMail($otpCode, 'forgot_password', $user->name));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Forgot Password OTP Email Error: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'A 5-digit password reset verification code has been sent to your email.',
            'email' => $user->email,
        ]);
    }

    /**
     * Reset password using 5-digit OTP code.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:5',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $otpRecord = Otp::where('email', $request->email)
            ->where('type', 'forgot_password')
            ->latest()
            ->first();

        if (!$otpRecord || !$otpRecord->isValid($request->otp)) {
            return response()->json([
                'message' => 'Invalid or expired OTP code.'
            ], 422);
        }

        // Mark OTP as used
        $otpRecord->update(['used_at' => now()]);

        // Reset user password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Revoke old tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password has been reset successfully! You can now log in with your new password.'
        ]);
    }

    /**
     * Resend fresh 5-digit OTP to user email.
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'type' => 'required|string|in:registration,forgot_password',
        ]);

        $user = User::where('email', $request->email)->first();
        $type = $request->input('type');

        // Generate fresh 5-digit OTP
        $otpCode = sprintf('%05d', mt_rand(0, 99999));

        // Delete previous unexpired OTPs
        Otp::where('email', $user->email)->where('type', $type)->delete();

        // Create new OTP
        Otp::create([
            'email' => $user->email,
            'otp' => $otpCode,
            'type' => $type,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send Email
        try {
            Mail::to($user->email)->send(new SendOtpMail($otpCode, $type, $user->name));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Resend OTP Email Error: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'A fresh 5-digit verification code has been resent to your email.'
        ]);
    }

    /**
     * Send 5-digit OTP code to user's phone for verification / login.
     */
    public function sendPhoneOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:7|max:20',
        ]);

        $phone = preg_replace('/[^0-9+]/', '', $validated['phone']);

        // Generate 5-digit OTP
        $otpCode = sprintf('%05d', mt_rand(0, 99999));

        // Delete old phone login OTPs for this phone number
        Otp::where('phone', $phone)->where('type', 'phone_login')->delete();

        // Create new OTP record
        Otp::create([
            'phone' => $phone,
            'otp' => $otpCode,
            'type' => 'phone_login',
            'expires_at' => now()->addMinutes(10),
        ]);

        // Mask phone for response e.g. +1 (xxx) xxx-1234
        $maskedPhone = $this->maskPhoneNumber($phone);

        return response()->json([
            'message' => "A 5-digit code has been sent to {$maskedPhone}",
            'phone' => $phone,
            'masked_phone' => $maskedPhone,
            'otp_code' => config('app.debug') ? $otpCode : null,
            'otp_sent' => true,
        ]);
    }

    /**
     * Verify 5-digit OTP code sent to phone & log user in.
     */
    public function verifyPhoneOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:5',
        ]);

        $phone = preg_replace('/[^0-9+]/', '', $validated['phone']);

        $otpRecord = Otp::where('phone', $phone)
            ->where('type', 'phone_login')
            ->latest()
            ->first();

        if (!$otpRecord || !$otpRecord->isValid($validated['otp'])) {
            return response()->json([
                'message' => 'Invalid or expired OTP verification code.'
            ], 422);
        }

        // Mark OTP as used
        $otpRecord->update(['used_at' => now()]);

        // Find or auto-create customer user by phone
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $user = User::create([
                'name' => 'Customer ' . substr($phone, -4),
                'phone' => $phone,
                'email' => $phone . '@pacinos.local',
                'password' => Hash::make(mt_rand(100000, 999999)),
                'user_type' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is currently ' . $user->status . '. Please contact support.'
            ], 403);
        }

        // Revoke previous tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Phone verified & logged in successfully!',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Resend fresh 5-digit OTP code to user's phone.
     */
    public function resendPhoneOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = preg_replace('/[^0-9+]/', '', $validated['phone']);

        // Generate fresh 5-digit OTP
        $otpCode = sprintf('%05d', mt_rand(0, 99999));

        // Delete old OTPs
        Otp::where('phone', $phone)->where('type', 'phone_login')->delete();

        // Create new OTP
        Otp::create([
            'phone' => $phone,
            'otp' => $otpCode,
            'type' => 'phone_login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $maskedPhone = $this->maskPhoneNumber($phone);

        return response()->json([
            'message' => "A fresh 5-digit verification code has been sent to {$maskedPhone}",
            'phone' => $phone,
            'masked_phone' => $maskedPhone,
            'otp_code' => config('app.debug') ? $otpCode : null,
        ]);
    }

    /**
     * Helper to mask phone number into format +1 (xxx) xxx-XXXX or similar.
     */
    private function maskPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);
        if ($len >= 10) {
            $last4 = substr($digits, -4);
            $country = $len > 10 ? '+' . substr($digits, 0, $len - 10) . ' ' : '+1 ';
            return "{$country}(xxx) xxx-{$last4}";
        }
        return $phone;
    }

    /**
     * Authenticate user & issue Sanctum Bearer token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string', // Accepts email or phone
            'password' => 'required|string',
        ]);

        $loginInput = $request->input('login');

        // Check if input is email or phone number
        $fieldType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($fieldType, $loginInput)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials do not match our records.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is currently ' . $user->status . '. Please contact support.'
            ], 403);
        }

        // Check if email has been verified via OTP
        if (is_null($user->email_verified_at) && $user->user_type !== 'super_admin') {
            return response()->json([
                'message' => 'Your email address is not verified. Please complete OTP verification to log in.',
                'email_unverified' => true,
                'email' => $user->email,
            ], 403);
        }

        //$user->load('role.permissions');

        // Revoke previous tokens optionally
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Get current authenticated user profile with role & permissions.
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['addresses']);

        return response()->json([
            'user' => $user,
            'permissions' => $user->role ? $user->role->permissions->pluck('name') : [],
            'is_super_admin' => $user->isSuperAdmin(),
            'is_branch_admin' => $user->isBranchAdmin(),
            'is_customer' => $user->isCustomer(),
        ]);
    }

    /**
     * Logout user (Revoke current access token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
}
