<?php

namespace App\Http\Controllers;

use App\Events\MessageSentBroadcastEvent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Services\ChatPermissionService;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function __construct(
        protected ChatPermissionService $permissionService
    ) {
    }

    /**
     * Get allowable contacts for the authenticated user based on role matrix with filters.
     */
    public function contacts(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $search = $request->input('search');
        $role = $request->input('role') ?? $request->input('user_type');
        $roleId = $request->input('role_id');
        $branchId = $request->input('branch_id');
        $perPage = (int) $request->input('per_page', 20);

        $contacts = $this->permissionService->getAllowedContacts(
            $authUser,
            $search,
            $role,
            $roleId ? (int)$roleId : null,
            $branchId ? (int)$branchId : null,
            $perPage
        );

        return response()->json($contacts);
    }

    /**
     * List all conversations for the authenticated user.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = Conversation::forUser($authUser->id)
            ->with([
                'participants' => function ($q) {
                    $q->select('users.id', 'users.name', 'users.email', 'users.phone', 'users.user_type', 'users.avatar', 'users.user_image');
                },
                'lastMessage.sender' => function ($q) {
                    $q->select('id', 'name', 'user_type', 'avatar', 'user_image');
                },
                'order' => function ($q) {
                    $q->select('id', 'order_number', 'order_status', 'total', 'branch_id');
                },
                'branch' => function ($q) {
                    $q->select('id', 'name', 'address');
                },
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $conversations = $query->paginate($request->input('per_page', 20));

        // Format conversation items with other participant & unread badge
        $formatted = $conversations->getCollection()->map(function ($conv) use ($authUser) {
            $otherParticipant = $conv->participants->firstWhere('id', '!=', $authUser->id) ?? $conv->participants->first();
            $unreadCount = $conv->unreadCountForUser($authUser->id);

            return [
                'id' => $conv->id,
                'type' => $conv->type,
                'branch_id' => $conv->branch_id,
                'branch' => $conv->branch,
                'order_id' => $conv->order_id,
                'order' => $conv->order,
                'last_message_at' => $conv->last_message_at?->toIso8601String(),
                'unread_count' => $unreadCount,
                'other_participant' => $otherParticipant ? [
                    'id' => $otherParticipant->id,
                    'name' => $otherParticipant->name,
                    'email' => $otherParticipant->email,
                    'phone' => $otherParticipant->phone,
                    'user_type' => $otherParticipant->user_type,
                    'avatar' => $otherParticipant->avatar_url ?? $otherParticipant->user_image_url,
                ] : null,
                'last_message' => $conv->lastMessage ? [
                    'id' => $conv->lastMessage->id,
                    'sender_id' => $conv->lastMessage->sender_id,
                    'sender_name' => $conv->lastMessage->sender?->name,
                    'message' => $conv->lastMessage->message,
                    'has_attachment' => !empty($conv->lastMessage->attachment),
                    'attachment' => $conv->lastMessage->attachment,
                    'attachment_url' => $conv->lastMessage->attachment_url,
                    'attachment_type' => $conv->lastMessage->attachment_type,
                    'is_read' => (bool)$conv->lastMessage->is_read,
                    'created_at' => $conv->lastMessage->created_at?->toIso8601String(),
                ] : null,
                'participants' => $conv->participants->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'user_type' => $p->user_type,
                    'avatar' => $p->avatar_url ?? $p->user_image_url,
                ]),
            ];
        });

        return response()->json([
            'current_page' => $conversations->currentPage(),
            'last_page' => $conversations->lastPage(),
            'per_page' => $conversations->perPage(),
            'total' => $conversations->total(),
            'data' => $formatted,
        ]);
    }

    /**
     * Start or retrieve a conversation with a target user (and optional order context).
     */
    public function store(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
            'type' => 'nullable|in:direct,order_delivery,branch_support',
        ]);

        $receiver = User::findOrFail($validated['receiver_id']);
        $order = !empty($validated['order_id']) ? Order::find($validated['order_id']) : null;

        // Check role messaging permissions
        $check = $this->permissionService->canMessage($authUser, $receiver, $order);
        if (!$check['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $check['reason'] ?? 'You are not authorized to message this user.',
            ], 403);
        }

        $branchId = $order?->branch_id ?? $this->permissionService->getUserBranchId($authUser) ?? $this->permissionService->getUserBranchId($receiver);
        $convType = $validated['type'] ?? ($order ? 'order_delivery' : 'direct');

        return DB::transaction(function () use ($authUser, $receiver, $order, $branchId, $convType) {
            // Find existing conversation between these 2 participants (matching order_id if present)
            $existingConv = Conversation::whereHas('participants', function ($q) use ($authUser) {
                $q->where('users.id', $authUser->id);
            })->whereHas('participants', function ($q) use ($receiver) {
                $q->where('users.id', $receiver->id);
            })->when($order, function ($q) use ($order) {
                $q->where('order_id', $order->id);
            })->first();

            if ($existingConv) {
                return response()->json([
                    'success' => true,
                    'message' => 'Existing conversation retrieved.',
                    'conversation' => $existingConv->load(['participants', 'lastMessage', 'order', 'branch']),
                ]);
            }

            // Create new conversation
            $conversation = Conversation::create([
                'type' => $convType,
                'branch_id' => $branchId,
                'order_id' => $order?->id,
                'created_by' => $authUser->id,
                'last_message_at' => now(),
            ]);

            // Attach participants
            $conversation->participants()->attach([
                $authUser->id => ['last_read_at' => now()],
                $receiver->id => ['last_read_at' => null],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'New conversation started successfully.',
                'conversation' => $conversation->load(['participants', 'order', 'branch']),
            ], 201);
        });
    }

    /**
     * Get conversation details and message history.
     */
    public function show(Request $request, Conversation $conversation)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $isParticipant = $conversation->participants()->where('users.id', $authUser->id)->exists();
        $isSuperAdmin = $this->permissionService->isSuperAdmin($authUser);

        if (!$isParticipant && !$isSuperAdmin) {
            return response()->json(['message' => 'Unauthorized: You are not a participant in this conversation.'], 403);
        }

        // Auto mark unread messages as read for this user
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $authUser->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $authUser->id)
            ->update(['last_read_at' => now()]);

        $messages = $conversation->messages()
            ->with(['sender:id,name,email,user_type,avatar,user_image'])
            ->orderBy('created_at', 'asc')
            ->paginate($request->input('per_page', 50));

        $otherParticipant = $conversation->participants->firstWhere('id', '!=', $authUser->id);

        return response()->json([
            'conversation' => $conversation->load(['participants', 'order', 'branch']),
            'other_participant' => $otherParticipant ? [
                'id' => $otherParticipant->id,
                'name' => $otherParticipant->name,
                'email' => $otherParticipant->email,
                'phone' => $otherParticipant->phone,
                'user_type' => $otherParticipant->user_type,
                'avatar' => $otherParticipant->avatar_url ?? $otherParticipant->user_image_url,
            ] : null,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $isParticipant = $conversation->participants()->where('users.id', $authUser->id)->exists();
        $isSuperAdmin = $this->permissionService->isSuperAdmin($authUser);

        if (!$isParticipant && !$isSuperAdmin) {
            return response()->json(['message' => 'Unauthorized to send message in this conversation.'], 403);
        }

        $validated = $request->validate([
            'message' => 'required_without:attachment|nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx|max:10240', // 10MB
        ]);

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $extension = strtolower($file->getClientOriginalExtension());
            $attachmentType = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : 'file';
            $attachmentPath = $file->store('chat_attachments', 'public');
        }

        return DB::transaction(function () use ($request, $conversation, $authUser, $validated, $attachmentPath, $attachmentType) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $authUser->id,
                'message' => $validated['message'] ?? null,
                'attachment' => $attachmentPath,
                'attachment_type' => $attachmentType,
                'is_read' => false,
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);

            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $authUser->id)
                ->update(['last_read_at' => now()]);

            // Other participants to receive broadcast & notifications
            $receivers = $conversation->participants()->where('users.id', '!=', $authUser->id)->get();
            $receiverIds = $receivers->pluck('id')->toArray();

            // 1. Broadcast real-time WebSocket event
            try {
                broadcast(new MessageSentBroadcastEvent($message, $receiverIds));
            } catch (\Exception $e) {
                Log::error('Real-time Chat Broadcast Error: ' . $e->getMessage());
            }

            // 2. Send FCM Push Notification & In-App Notification to receiver(s)
            $previewText = $message->message ?: ($attachmentType === 'image' ? 'Sent an image' : 'Sent an attachment');

            foreach ($receivers as $receiver) {
                try {
                    Notification::create([
                        'user_id' => $receiver->id,
                        'branch_id' => $conversation->branch_id,
                        'title' => "New message from {$authUser->name}",
                        'message' => $previewText,
                        'type' => 'chat',
                        'is_read' => false,
                    ]);

                    if ($receiver->fcm_token) {
                        FirebaseNotificationService::sendPushNotification(
                            $receiver->fcm_token,
                            "New message from {$authUser->name}",
                            $previewText,
                            [
                                'type' => 'chat_message',
                                'conversation_id' => (string) $conversation->id,
                                'sender_id' => (string) $authUser->id,
                                'sender_name' => $authUser->name,
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Chat FCM/Notification Error: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully.',
                'data' => $message->fresh(['sender:id,name,email,user_type,avatar,user_image']),
            ], 201);
        });
    }

    /**
     * Mark all unread messages in conversation as read.
     */
    public function markAsRead(Request $request, Conversation $conversation)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $authUser->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $authUser->id)
            ->update(['last_read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as read.',
        ]);
    }
}
