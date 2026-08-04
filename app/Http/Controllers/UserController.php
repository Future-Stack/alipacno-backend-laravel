<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with(['role', 'addresses']);

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->latest();

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|unique:users',
            'avatar' => 'nullable', // Accepts image file or string path
            'user_image' => 'nullable', // Accepts image file or string path
            'user_type' => 'nullable|string',
            'role_id' => 'nullable|exists:roles,id',
            'status' => 'nullable|in:active,inactive,blocked',
        ]);

        $validated['password'] = bcrypt($validated['password']);

        // Handle avatar file upload
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpeg,png,jpg,webp,gif|max:10240']);
            $validated['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        // Handle user_image file upload
        if ($request->hasFile('user_image')) {
            $request->validate(['user_image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:10240']);
            $validated['user_image'] = $request->file('user_image')->store('users/images', 'public');
        }

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return response()->json($user->load(['role', 'addresses']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|unique:users,phone,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'avatar' => 'nullable',
            'user_image' => 'nullable',
            'user_type' => 'sometimes|string',
            'role_id' => 'nullable|exists:roles,id',
            'status' => 'sometimes|in:active,inactive,blocked',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        // Handle avatar image update & old file deletion
        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|mimes:jpeg,png,jpg,webp,gif|max:2048']);
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        // Handle user_image update & old file deletion
        if ($request->hasFile('user_image')) {
            $request->validate(['user_image' => 'image|mimes:jpeg,png,jpg,webp,gif|max:2048']);
            if ($user->user_image && Storage::disk('public')->exists($user->user_image)) {
                Storage::disk('public')->delete($user->user_image);
            }
            $validated['user_image'] = $request->file('user_image')->store('users/images', 'public');
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Delete stored image files from disk
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        if ($user->user_image && Storage::disk('public')->exists($user->user_image)) {
            Storage::disk('public')->delete($user->user_image);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}

