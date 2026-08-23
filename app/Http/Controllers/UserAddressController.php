<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    /**
     * Display a listing of user addresses.
     */
    public function index(Request $request)
    {
        $userId = $request->input('user_id', $request->user()?->id);

        $query = UserAddress::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $query->orderBy('is_default', 'desc')->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store or update user address (supports updateOrCreate).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:user_addresses,id',
            'user_id' => 'nullable|exists:users,id',
            'label' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'post_code' => 'nullable|string|max:30', // alias for postcode
            'city' => 'nullable|string|max:100',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_default' => 'nullable|boolean',
        ]);

        $userId = $validated['user_id'] ?? $request->user()?->id;
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated or user_id required.'], 401);
        }
        $validated['user_id'] = $userId;

        // Map post_code alias if provided
        if (isset($validated['post_code']) && !isset($validated['postcode'])) {
            $validated['postcode'] = $validated['post_code'];
        }
        unset($validated['post_code']);

        // Default values
        $validated['country'] = $validated['country'] ?? 'UK';
        $validated['label'] = $validated['label'] ?? 'Home';
        $validated['is_default'] = $validated['is_default'] ?? true;

        // Auto compose full address string if not provided
        if (empty($validated['address'])) {
            $addressParts = array_filter([
                $validated['address_line_1'] ?? null,
                $validated['address_line_2'] ?? null,
                $validated['city'] ?? null,
                $validated['postcode'] ?? null,
                $validated['country'] ?? null,
            ]);
            $validated['address'] = implode(', ', $addressParts);
        }

        // Auto compose address_line_1 if address was given instead
        if (empty($validated['address_line_1']) && !empty($validated['address'])) {
            $validated['address_line_1'] = $validated['address'];
        }

        // If setting as default address, reset other default addresses for this user
        if (!empty($validated['is_default'])) {
            UserAddress::where('user_id', $userId)->update(['is_default' => false]);
        }

        // If specific address ID is passed, update it, otherwise updateOrCreate default address or create new
        if (!empty($validated['id'])) {
            $addressId = $validated['id'];
            unset($validated['id']);
            $address = UserAddress::updateOrCreate(['id' => $addressId, 'user_id' => $userId], $validated);
        } else {
            // Update existing default address or create new
            $address = UserAddress::updateOrCreate(
                ['user_id' => $userId, 'is_default' => true],
                $validated
            );
        }

        return response()->json($address, 200);
    }

    /**
     * Display the specified user address.
     */
    public function show(UserAddress $userAddress)
    {
        return response()->json($userAddress->load('user'));
    }

    /**
     * Update the specified user address.
     */
    public function update(Request $request, UserAddress $userAddress)
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:30',
            'post_code' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:100',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_default' => 'sometimes|boolean',
        ]);

        if (isset($validated['post_code']) && !isset($validated['postcode'])) {
            $validated['postcode'] = $validated['post_code'];
        }
        unset($validated['post_code']);

        if (!empty($validated['is_default'])) {
            UserAddress::where('user_id', $userAddress->user_id)->update(['is_default' => false]);
        }

        if (empty($validated['address']) && (!empty($validated['address_line_1']) || !empty($validated['city']))) {
            $addressParts = array_filter([
                $validated['address_line_1'] ?? $userAddress->address_line_1,
                $validated['address_line_2'] ?? $userAddress->address_line_2,
                $validated['city'] ?? $userAddress->city,
                $validated['postcode'] ?? $userAddress->postcode,
                $validated['country'] ?? $userAddress->country,
            ]);
            $validated['address'] = implode(', ', $addressParts);
        }

        $userAddress->update($validated);

        return response()->json($userAddress);
    }

    /**
     * Remove the specified user address.
     */
    public function destroy(UserAddress $userAddress)
    {
        $userAddress->delete();

        return response()->json(null, 204);
    }
}