<?php

namespace App\Http\Controllers;

use App\Models\PendingMessage;
use App\Models\AppData;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PendingMessageController extends Controller
{
    /**
     * Send a new pending message
     */
    public function sendPendingMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'live_session_id' => 'required|string',
            'streamer_user_id' => 'required|integer|exists:users,id',
            'message_content' => 'required|string|max:1000',
            'message_type' => 'in:text,gift'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $senderId = $request->header('userid');
        if (!$senderId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID not found in header'
            ], 401);
        }

        // Check if pending messages are allowed
        $appData = AppData::first();
        if (!$appData || !$appData->allow_free_pending_messages) {
            return response()->json([
                'status' => false,
                'message' => 'Pending messages are not allowed'
            ], 403);
        }

        // Check if user has reached max pending messages for this live session
        $existingPendingCount = PendingMessage::where('sender_user_id', $senderId)
            ->where('live_session_id', $request->live_session_id)
            ->where('status', 'pending')
            ->count();

        $maxPending = $appData->max_pending_messages_per_user ?? 5;
        if ($existingPendingCount >= $maxPending) {
            return response()->json([
                'status' => false,
                'message' => "You can only have {$maxPending} pending messages at a time"
            ], 429);
        }

        // Create pending message
        $pendingMessage = PendingMessage::create([
            'live_session_id' => $request->live_session_id,
            'sender_user_id' => $senderId,
            'streamer_user_id' => $request->streamer_user_id,
            'message_content' => $request->message_content,
            'message_type' => $request->message_type ?? 'text',
            'status' => 'pending'
        ]);

        // Load sender information
        $pendingMessage->load('sender');

        // Send push notification to streamer about new pending message
        $senderName = $pendingMessage->sender->fullname ?? 'Someone';
        $title = "💌 New Pending Message";
        $body = "$senderName sent a message waiting for approval";
        $data = [
            'messageType' => 'pending_message',
            'senderName' => $senderName,
            'messageContent' => $pendingMessage->message_content,
            'liveSessionId' => $request->live_session_id
        ];

        $success = NotificationController::sendLiveStreamChatNotification($request->streamer_user_id, $title, $body, $data);

        \Illuminate\Support\Facades\Log::info("🔥 [PENDING_MESSAGE_NOTIFICATION] Sent: " . json_encode([
            'status' => $success,
            'streamer_id' => $request->streamer_user_id,
            'sender' => $senderName,
            'message' => $pendingMessage->message_content
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Message sent for approval',
            'data' => [
                'id' => $pendingMessage->id,
                'message_content' => $pendingMessage->message_content,
                'status' => $pendingMessage->status,
                'sender' => [
                    'id' => $pendingMessage->sender->id,
                    'fullname' => $pendingMessage->sender->fullname,
                    'images' => $pendingMessage->sender->images
                ],
                'created_at' => $pendingMessage->created_at->toISOString()
            ]
        ]);
    }

    /**
     * Get pending messages for a streamer's live session
     */
    public function getPendingMessages(Request $request, $liveSessionId)
    {
        $streamerId = $request->header('userid');
        if (!$streamerId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID not found in header'
            ], 401);
        }

        $pendingMessages = PendingMessage::with('sender')
            ->where('live_session_id', $liveSessionId)
            ->where('streamer_user_id', $streamerId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedMessages = $pendingMessages->map(function ($message) {
            return [
                'id' => $message->id,
                'message_content' => $message->message_content,
                'message_type' => $message->message_type,
                'status' => $message->status,
                'sender' => [
                    'id' => $message->sender->id,
                    'fullname' => $message->sender->fullname,
                    'images' => $message->sender->images,
                    'live' => $message->sender->live
                ],
                'created_at' => $message->created_at->toISOString()
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $formattedMessages,
            'count' => $pendingMessages->count()
        ]);
    }

    /**
     * Get user's own pending messages for a live session
     */
    public function getMyPendingMessages(Request $request, $liveSessionId)
    {
        $userId = $request->header('userid');
        if (!$userId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID not found in header'
            ], 401);
        }

        $pendingMessages = PendingMessage::where('live_session_id', $liveSessionId)
            ->where('sender_user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedMessages = $pendingMessages->map(function ($message) {
            return [
                'id' => $message->id,
                'message_content' => $message->message_content,
                'message_type' => $message->message_type,
                'status' => $message->status,
                'created_at' => $message->created_at->toISOString(),
                'approved_at' => $message->approved_at?->toISOString(),
                'rejected_at' => $message->rejected_at?->toISOString()
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $formattedMessages
        ]);
    }

    /**
     * Approve a pending message
     */
    public function approveMessage(Request $request, $messageId)
    {
        $streamerId = $request->header('userid');
        if (!$streamerId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID not found in header'
            ], 401);
        }

        $pendingMessage = PendingMessage::with('sender')
            ->where('id', $messageId)
            ->where('streamer_user_id', $streamerId)
            ->where('status', 'pending')
            ->first();

        if (!$pendingMessage) {
            return response()->json([
                'status' => false,
                'message' => 'Pending message not found or already processed'
            ], 404);
        }

        $pendingMessage->approve();

        return response()->json([
            'status' => true,
            'message' => 'Message approved successfully',
            'data' => [
                'id' => $pendingMessage->id,
                'message_content' => $pendingMessage->message_content,
                'status' => $pendingMessage->status,
                'sender' => [
                    'id' => $pendingMessage->sender->id,
                    'fullname' => $pendingMessage->sender->fullname,
                    'images' => $pendingMessage->sender->images,
                    'live' => $pendingMessage->sender->live
                ],
                'approved_at' => $pendingMessage->approved_at->toISOString()
            ]
        ]);
    }

    /**
     * Reject a pending message
     */
    public function rejectMessage(Request $request, $messageId)
    {
        $streamerId = $request->header('userid');
        if (!$streamerId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID not found in header'
            ], 401);
        }

        $pendingMessage = PendingMessage::where('id', $messageId)
            ->where('streamer_user_id', $streamerId)
            ->where('status', 'pending')
            ->first();

        if (!$pendingMessage) {
            return response()->json([
                'status' => false,
                'message' => 'Pending message not found or already processed'
            ], 404);
        }

        $pendingMessage->reject();

        return response()->json([
            'status' => true,
            'message' => 'Message rejected successfully',
            'data' => [
                'id' => $pendingMessage->id,
                'status' => $pendingMessage->status,
                'rejected_at' => $pendingMessage->rejected_at->toISOString()
            ]
        ]);
    }

    /**
     * Get pending messages count for a streamer
     */
    public function getPendingMessagesCount(Request $request, $liveSessionId)
    {
        $streamerId = $request->header('userid');
        if (!$streamerId) {
            return response()->json([
                'status' => false,
                'message' => 'User ID not found in header'
            ], 401);
        }

        $count = PendingMessage::where('live_session_id', $liveSessionId)
            ->where('streamer_user_id', $streamerId)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'status' => true,
            'count' => $count
        ]);
    }
}