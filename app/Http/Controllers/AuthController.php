<?php

namespace App\Http\Controllers;

use App\Mail\SendOtpMail;
use App\Models\Driver;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user & send -digit OTP verification email.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:50|unique:users',
            'gender' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'avatar' => 'nullable',
            'user_image' => 'nullable',
            'user_type' => 'nullable|string|in:customer,driver,admin,staff,super_admin,hq_admin,branch_admin',
            'role_id' => 'nullable|exists:roles,id',
            'branch_id' => 'nullable|exists:branches,id',
            'vehicle_type' => 'nullable|string|max:100',
            'license_number' => 'nullable|string|max:100',
        ]);

        $userPayload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'password' => Hash::make($validated['password']),
            'user_type' => $validated['user_type'] ?? 'customer',
            'role_id' => $validated['role_id'] ?? null,
            'status' => 'active',
            'email_verified_at' => null,
        ];

        // Handle avatar image file upload if present
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpeg,png,jpg,webp,gif|max:10240']);
            $userPayload['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        // Handle user_image file upload if present
        if ($request->hasFile('user_image')) {
            $request->validate(['user_image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:10240']);
            $userPayload['user_image'] = $request->file('user_image')->store('users/images', 'public');
        }

        $user = User::create($userPayload);

        // Check if user is registering as a driver (via user_type == 'driver' or role_id == 7 / 'Delivery Driver' role)
        $isDriverRole = false;
        if ($user->role_id) {
            $role = \App\Models\Role::find($user->role_id);
            if ($role && (strtolower($role->name) === 'delivery driver' || $role->id == 7)) {
                $isDriverRole = true;
            }
        }

        if ($user->user_type === 'driver' || $user->role_id == 7 || $isDriverRole) {
            $user->user_type = 'driver';
            $user->save();

            $licenseImagePath = null;
            if ($request->hasFile('license_image')) {
                $request->validate(['license_image' => 'file|mimes:jpeg,png,jpg,webp,pdf|max:10240']);
                $licenseImagePath = $request->file('license_image')->store('drivers/licenses', 'public');
            }

            Driver::create([
                'user_id' => $user->id,
                'branch_id' => $request->input('branch_id', 1),
                'name' => $user->name,
                'phone' => $user->phone ?? 'N/A',
                'vehicle_type' => $request->input('vehicle_type', 'Motorcycle'),
                'license_number' => $request->input('license_number'),
                'license_image' => $licenseImagePath,
                'kyc_status' => 'pending',
                'is_online' => false,
                'status' => 'available',
            ]);
        }

        $user->load(['driver', 'role']);

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
            'kyc_status' => $user->driver?->kyc_status ?? ($user->user_type === 'driver' ? 'pending' : null),
            'is_online' => (bool) ($user->driver?->is_online ?? false),
            'status' => $user->driver?->status ?? 'available',
            'otp_sent' => true,
        ], 201);
    }

    /**
     * Verify 5-digit OTP code.
     * Route: verify-otp (unchanged) — used by BOTH:
     *  - Registration email verification screen
     *  - Forgot-password "Verification" screen (Step 2 in the 3-screen flow)
     *
     * No 'type' field needed from the frontend — the latest OTP record for
     * this email tells us which flow it belongs to.
     *
     * - registration -> marks email verified, revokes old tokens, logs the
     *   user in (same as before).
     * - forgot_password -> marks the OTP as verified (NOT used yet) and
     *   returns success, so the app can move to the "Reset Password"
     *   screen. resetPassword() below checks this verified flag.
     */
    public function verifyRegistrationOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:5',
        ]);

        $otpRecord = Otp::where('email', $request->email)
            ->latest()
            ->first();

        if (!$otpRecord || !$otpRecord->isValid($request->otp)) {
            return response()->json([
                'message' => 'Invalid or expired OTP verification code.'
            ], 422);
        }

        if ($otpRecord->type === 'forgot_password') {
            // Just confirm the code is correct and mark it verified (not
            // used yet) — resetPassword() checks this flag by email.
            $otpRecord->update(['verified_at' => now()]);

            return response()->json([
                'message' => 'OTP verified. You can now reset your password.',
                'email' => $request->email,
                'verified' => true,
            ]);
        }

        // Registration flow (default / fallback)
        $otpRecord->update(['used_at' => now()]);

        $user = User::where('email', $request->email)->first();
        $user->email_verified_at = now();
        $user->save();

        $user->load(['driver.branch', 'role.permissions']);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully!',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'kyc_status' => $user->driver?->kyc_status ?? ($user->user_type === 'driver' ? 'pending' : null),
            'is_online' => (bool) ($user->driver?->is_online ?? false),
            'status' => $user->driver?->status ?? 'available',
        ]);
    }

    /**
     * Step 1 (Forgot Password screen): send 5-digit OTP to user email.
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
     * Step 3 (Reset Password screen): set the new password.
     * This screen only has password + confirm password fields — plus
     * email, which the app carries forward from Step 1/2 (no OTP field
     * shown here). We check that this email's forgot_password OTP was
     * already verified in Step 2 (verify-otp), then mark it fully used
     * once the password is actually changed.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $otpRecord = Otp::where('email', $request->email)
            ->where('type', 'forgot_password')
            ->latest()
            ->first();

        if (!$otpRecord || is_null($otpRecord->verified_at) || !is_null($otpRecord->used_at)) {
            return response()->json([
                'message' => 'Please verify your OTP code first.'
            ], 422);
        }

        if (now()->greaterThan($otpRecord->expires_at)) {
            return response()->json([
                'message' => 'Your verification code has expired. Please request a new one.'
            ], 422);
        }

        // Mark OTP as fully used
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
     * Route: resend-otp (unchanged) — used from both the registration
     * "verify-otp" screen and the forgot-password "Verification" screen.
     *
     * 'type' is now OPTIONAL. If the frontend doesn't send it (as in the
     * forgot-password screens, which have no type selector), the latest
     * OTP record for this email is looked up and its type is reused.
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'type' => 'nullable|string|in:registration,forgot_password',
        ]);

        $user = User::where('email', $request->email)->first();
        $type = $request->input('type');

        // Auto-detect type from the latest OTP record for this email
        // when the frontend doesn't send it explicitly.
        if (!$type) {
            $latestOtp = Otp::where('email', $user->email)->latest()->first();

            if (!$latestOtp) {
                return response()->json([
                    'message' => 'No previous verification request found for this email.'
                ], 422);
            }

            $type = $latestOtp->type;
        }

        // Generate fresh 5-digit OTP
        $otpCode = sprintf('%05d', mt_rand(0, 99999));

        // Delete previous unexpired OTPs of this type
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

        $user->load(['driver.branch', 'role.permissions']);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Phone verified & logged in successfully!',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'kyc_status' => $user->driver?->kyc_status ?? ($user->user_type === 'driver' ? 'pending' : null),
            'is_online' => (bool) ($user->driver?->is_online ?? false),
            'status' => $user->driver?->status ?? 'available',
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

        $user->load(['driver.branch', 'role.permissions']);

        // Revoke previous tokens optionally
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'kyc_status' => $user->driver?->kyc_status ?? ($user->user_type === 'driver' ? 'pending' : null),
            'is_online' => (bool) ($user->driver?->is_online ?? false),
            'status' => $user->driver?->status ?? 'available',
        ]);
    }

    /**
     * Get current authenticated user profile with role & permissions.
     */
    public function me(Request $request)
    {
        $user = Auth::user()->load(['addresses', 'defaultAddress', 'driver.branch', 'role.permissions']);

        return response()->json([
            'user' => $user,
            'address' => $user->defaultAddress ?? $user->addresses->first(),
            'kyc_status' => $user->driver?->kyc_status ?? ($user->user_type === 'driver' ? 'pending' : null),
            'is_online' => (bool) ($user->driver?->is_online ?? false),
            'status' => $user->driver?->status ?? 'available',
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