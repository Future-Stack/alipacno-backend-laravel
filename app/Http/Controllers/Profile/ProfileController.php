<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            // User personal fields
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50|unique:users,phone,' . $user->id,
            'phone_number' => 'nullable|string|max:50', // alias for phone
            'gender' => 'nullable|string|max:20', // Male, Female, Other
            'password' => 'sometimes|string|min:8',
            'avatar' => 'nullable',
            'user_image' => 'nullable',
            'user_type' => 'sometimes|string',
            'role_id' => 'nullable|exists:roles,id',
            'status' => 'sometimes|in:active,inactive,blocked',

            // Customer address fields
            'country' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'post_code' => 'nullable|string|max:30', // alias for postcode
            'city' => 'nullable|string|max:100',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'label' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
        ]);

        if (isset($validated['phone_number']) && !isset($validated['phone'])) {
            $validated['phone'] = $validated['phone_number'];
        }
        unset($validated['phone_number']);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        // Handle avatar image update & old file deletion
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpeg,png,jpg,webp,gif|max:5120']);
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $avatarPath = $request->file('avatar')->store('users/avatars', 'public');
            $validated['avatar'] = $avatarPath;
            if (!$request->hasFile('user_image')) {
                $validated['user_image'] = $avatarPath;
            }
        }

        // Handle user_image update & old file deletion
        if ($request->hasFile('user_image')) {
            $request->validate(['user_image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:5120']);
            if ($user->user_image && Storage::disk('public')->exists($user->user_image)) {
                Storage::disk('public')->delete($user->user_image);
            }
            $userImagePath = $request->file('user_image')->store('users/images', 'public');
            $validated['user_image'] = $userImagePath;
            if (!$request->hasFile('avatar')) {
                $validated['avatar'] = $userImagePath;
            }
        }

        // Filter user fields for user table
        $userFields = ['name', 'email', 'phone', 'gender', 'password', 'avatar', 'user_image', 'user_type', 'role_id', 'status'];
        $userData = array_intersect_key($validated, array_flip($userFields));
        if (!empty($userData)) {
            $user->update($userData);
        }

        // Check if any address field is present in the request
        $hasAddressData = $request->hasAny(['country', 'postcode', 'post_code', 'city', 'address_line_1', 'address_line_2', 'address', 'label', 'contact_name']);
        if ($hasAddressData) {
            $postcode = $validated['postcode'] ?? $validated['post_code'] ?? null;
            $country = $validated['country'] ?? 'UK';
            $city = $validated['city'] ?? null;
            $line1 = $validated['address_line_1'] ?? $validated['address'] ?? null;
            $line2 = $validated['address_line_2'] ?? null;

            $fullAddress = $validated['address'] ?? null;
            if (empty($fullAddress)) {
                $parts = array_filter([$line1, $line2, $city, $postcode, $country]);
                $fullAddress = implode(', ', $parts);
            }

            $addressData = [
                'user_id' => $user->id,
                'label' => $validated['label'] ?? 'Home',
                'contact_name' => $validated['contact_name'] ?? $user->name,
                'phone' => $user->phone,
                'country' => $country,
                'postcode' => $postcode,
                'city' => $city,
                'address_line_1' => $line1,
                'address_line_2' => $line2,
                'address' => $fullAddress,
                'is_default' => true,
            ];

            // updateOrCreate the user's primary/default address
            UserAddress::updateOrCreate(
                ['user_id' => $user->id, 'is_default' => true],
                array_filter($addressData, fn($val) => !is_null($val))
            );
        }

        $freshUser = $user->fresh(['addresses', 'defaultAddress', 'role.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $freshUser,
            'address' => $freshUser->defaultAddress ?? $freshUser->addresses->first(),
        ]);
    }
}
