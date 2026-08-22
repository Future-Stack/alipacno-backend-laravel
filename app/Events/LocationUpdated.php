<?php

namespace App\Events;

use App\Models\DriverLocation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DriverLocation $location
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'delivery.' . $this->location->delivery_id
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->location->driver_id,
            'delivery_id' => $this->location->delivery_id,

            'latitude' => $this->location->latitude,
            'longitude' => $this->location->longitude,

            'accuracy' => $this->location->accuracy,
            'speed' => $this->location->speed,
            'heading' => $this->location->heading,

            'tracked_at' => $this->location
                ->tracked_at
                ?->toISOString(),
        ];
    }
}