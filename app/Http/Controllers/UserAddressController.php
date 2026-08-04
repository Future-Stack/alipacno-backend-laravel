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
     * Store a newly created user address.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'label' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'address' => 'required|string',
            'postcode' => 'required|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_default' => 'nullable|boolean',
        ]);

        if (empty($validated['user_id']) && $request->user()) {
            $validated['user_id'] = $request->user()->id;
        }

        if (!empty($validated['is_default']) && !empty($validated['user_id'])) {
            UserAddress::where('user_id', $validated['user_id'])->update(['is_default' => false]);
        }

        $address = UserAddress::create($validated);

        return response()->json($address, 201);
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
            'address' => 'sometimes|string',
            'postcode' => 'sometimes|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_default' => 'sometimes|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            UserAddress::where('user_id', $userAddress->user_id)->update(['is_default' => false]);
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