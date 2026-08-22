<?php

namespace App\Http\Controllers;

use App\Events\LocationUpdated;
use App\Models\Delivery;
use App\Models\DriverLocation;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'delivery_id' => [
                'required',
                'integer',
                'exists:deliveries,id',
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'accuracy' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'speed' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'heading' => [
                'nullable',
                'numeric',
                'between:0,360',
            ],

            'tracked_at' => [
                'nullable',
                'date',
            ],
        ]);

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Find Driver
        |--------------------------------------------------------------------------
        */

        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Delivery
        |--------------------------------------------------------------------------
        */

        $delivery = Delivery::where('id', $validated['delivery_id'])
            ->where('driver_id', $driver->id)
            ->first();

        if (!$delivery) {
            return response()->json([
                'success' => false,
                'message' => 'This delivery is not assigned to you.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Location
        |--------------------------------------------------------------------------
        */

        $location = DriverLocation::updateOrCreate(
            [
                'driver_id' => $driver->id,
                'delivery_id' => $delivery->id,
            ],
            [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],

                'accuracy' => $validated['accuracy'] ?? null,
                'speed' => $validated['speed'] ?? null,
                'heading' => $validated['heading'] ?? null,

                'tracked_at' => $validated['tracked_at'] ?? now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Broadcast
        |--------------------------------------------------------------------------
        */

        broadcast(
            new LocationUpdated($location)
        )->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Driver location updated successfully.',
            'data' => [
                'id' => $location->id,
                'driver_id' => $location->driver_id,
                'delivery_id' => $location->delivery_id,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'tracked_at' => $location->tracked_at,
            ],
        ]);
    }
}