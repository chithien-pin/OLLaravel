<?php

namespace App\Http\Controllers;

use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LiveStreamChatController extends Controller
{
    /**
     * Send notification for direct chat message (10 coins)
     */
    public function sendDirectChatMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'streamerUserId' => 'required|integer|exists:users,id',
            'senderName' => 'required|string',
            'message' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $streamerId = $request->streamerUserId;
        $senderName = $request->senderName;
        $message = $request->message;

        // Send push notification
        $title = "💬 New Chat Message";
        $body = "$senderName: $message";
        $data = [
            'messageType' => 'direct_chat',
            'senderName' => $senderName,
            'message' => $message
        ];

        $success = NotificationController::sendLiveStreamChatNotification($streamerId, $title, $body, $data);

        Log::info("🔥 [DIRECT_CHAT_NOTIFICATION] Sent: " . json_encode([
            'status' => $success,
            'streamer_id' => $streamerId,
            'sender' => $senderName,
            'message' => $message
        ]));

        return response()->json([
            'status' => $success,
            'message' => $success ? 'Notification sent successfully' : 'Failed to send notification'
        ]);
    }

    /**
     * Send notification for viewer joined live stream
     */
    public function sendViewerJoinedNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'streamerUserId' => 'required|integer|exists:users,id',
            'viewerName' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $streamerId = $request->streamerUserId;
        $viewerName = $request->viewerName;

        // Send push notification
        $title = "👋 New Viewer";
        $body = "$viewerName joined your live stream";
        $data = [
            'messageType' => 'viewer_joined',
            'viewerName' => $viewerName
        ];

        $success = NotificationController::sendLiveStreamChatNotification($streamerId, $title, $body, $data);

        Log::info("🔥 [VIEWER_JOINED_NOTIFICATION] Sent: " . json_encode([
            'status' => $success,
            'streamer_id' => $streamerId,
            'viewer' => $viewerName
        ]));

        return response()->json([
            'status' => $success,
            'message' => $success ? 'Notification sent successfully' : 'Failed to send notification'
        ]);
    }

    /**
     * Send notification for message approval
     */
    public function sendApprovalNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'viewerUserId' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $viewerId = $request->viewerUserId;

        // Send push notification
        $title = "✅ Message Approved";
        $body = "Your message has been approved and is now visible in the chat";
        $data = [
            'messageType' => 'message_approved'
        ];

        $success = NotificationController::sendLiveStreamChatNotification($viewerId, $title, $body, $data);

        Log::info("🔥 [APPROVAL_NOTIFICATION] Sent: " . json_encode([
            'status' => $success,
            'viewer_id' => $viewerId
        ]));

        return response()->json([
            'status' => $success,
            'message' => $success ? 'Approval notification sent successfully' : 'Failed to send notification'
        ]);
    }

    /**
     * Send notification for message rejection
     */
    public function sendRejectionNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'viewerUserId' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $viewerId = $request->viewerUserId;

        // Send push notification
        $title = "❌ Message Rejected";
        $body = "Your message has been rejected by the streamer";
        $data = [
            'messageType' => 'message_rejected'
        ];

        $success = NotificationController::sendLiveStreamChatNotification($viewerId, $title, $body, $data);

        Log::info("🔥 [REJECTION_NOTIFICATION] Sent: " . json_encode([
            'status' => $success,
            'viewer_id' => $viewerId
        ]));

        return response()->json([
            'status' => $success,
            'message' => $success ? 'Rejection notification sent successfully' : 'Failed to send notification'
        ]);
    }
}