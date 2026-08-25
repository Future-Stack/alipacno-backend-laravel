<?php

use App\Models\Delivery;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('delivery.{deliveryId}', function ($user, $deliveryId) {

    $delivery = Delivery::with('order')->find($deliveryId);

    if (!$delivery) {
        return false;
    }

    // Admin can view any delivery
    if ($user->hasRole(['super_admin', 'hq_admin', 'branch_admin'])) {
        return true;
    }


    // Customer can view only their own order delivery
    if (
        $delivery->order &&
        $delivery->order->user_id === $user->id
    ) {
        return true;
    }

    // Driver can view their assigned delivery
    if (
        $user->driver &&
        $delivery->driver_id === $user->driver->id
    ) {
        return true;
    }

    return false;
});

Broadcast::channel('branch.{branchId}.drivers', function ($user, $branchId) {
    if ($user->hasRole(['super_admin', 'hq_admin', 'branch_admin'])) {
        return true;
    }

    if ($user->driver && (int) $user->driver->branch_id === (int) $branchId) {
        return true;
    }

    return false;
});