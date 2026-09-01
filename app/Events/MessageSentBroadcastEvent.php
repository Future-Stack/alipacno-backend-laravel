<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSentBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Message $message,
        public array $receiverIds = []
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];

        foreach ($this->receiverIds as $receiverId) {
            $channels[] = new PrivateChannel('user.' . $receiverId . '.chat');
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $msg = $this->message->fresh(['sender', 'conversation']);

        return [
            'id' => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'sender_id' => $msg->sender_id,
            'sender' => [
                'id' => $msg->sender?->id,
                'name' => $msg->sender?->name,
                'user_type' => $msg->sender?->user_type,
                'avatar' => $msg->sender?->avatar_url ?? $msg->sender?->user_image_url,
            ],
            'message' => $msg->message,
            'attachment' => $msg->attachment_url,
            'attachment_type' => $msg->attachment_type,
            'is_read' => (bool)$msg->is_read,
            'created_at' => $msg->created_at?->toIso8601String(),
            'formatted_time' => $msg->created_at?->format('h:i A'),
        ];
    }
}
