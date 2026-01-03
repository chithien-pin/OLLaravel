<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Google\Client;
use Illuminate\Support\Facades\File;
use App\Services\TranslationService;

class Myfunction extends Model
{
    use HasFactory;

    public static function sendPushToUser($title, $message, $token, $eventData = null)
    {

        $client = new Client();
        $client->setAuthConfig(base_path('googleCredentials.json'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $accessToken = $client->getAccessToken();
        $accessToken = $accessToken['access_token'];

        $contents = File::get(base_path('googleCredentials.json'));
        $json = json_decode(json: $contents, associative: true);

        // Log::info($accessToken);

        $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
        $notificationArray = array('title' => $title, 'body' => $message);

        $device_token = $token;

        // Build message payload
        $messagePayload = [
            'token'=> $device_token,
            'notification' => $notificationArray,
        ];

        // Add event data if provided
        if ($eventData !== null && is_array($eventData)) {
            // Convert all values to strings for FCM compatibility
            $stringEventData = array();
            foreach ($eventData as $key => $value) {
                if (is_array($value)) {
                    $stringEventData[$key] = json_encode($value);
                } else {
                    $stringEventData[$key] = (string) $value;
                }
            }
            $messagePayload['data'] = $stringEventData;
        }

        $fields = array('message' => $messagePayload);

        $headers = array(
            'Content-Type:application/json',
            'Authorization:Bearer ' . $accessToken
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        // Log payload for debugging
        Log::info('FCM Payload: ' . json_encode($fields));

        $result = curl_exec($ch);
        Log::info('FCM Response: ' . $result);

        if ($result === FALSE) {
            Log::error('FCM Send Error: ' . curl_error($ch));
            die('FCM Send Error: ' . curl_error($ch));
        }
        curl_close($ch);

        return $result;
    }

    /**
     * Send follow notification with event data
     */
    public static function sendFollowNotification($fromUser, $toUser)
    {
        if ($toUser->is_notification != 1) {
            return;
        }

        $title = TranslationService::forUser($toUser, 'notification.title.app');
        $message = TranslationService::forUser($toUser, 'notification.follow', [
            'name' => $fromUser->fullname
        ]);

        $eventData = [
            'event_type' => Constants::eventTypeUserFollow,
            'user_id' => (string) $fromUser->id,
            'user_name' => $fromUser->fullname,
            'user_image' => self::getProfileImageUrl($fromUser),
            'timestamp' => (string) time(),
            'action' => Constants::actionViewFollowing,
            'screen' => Constants::screenFollow,
            'params' => json_encode(['initialTabIndex' => 1]) // Following tab
        ];

        return self::sendPushToUser($title, $message, $toUser->device_token, $eventData);
    }

    /**
     * Send message notification with event data
     */
    public static function sendMessageNotification($fromUser, $toUser, $messageText, $conversationId)
    {
        if ($toUser->is_notification != 1) {
            return;
        }

        $title = $fromUser->fullname;
        $message = $messageText;

        $eventData = [
            'event_type' => Constants::eventTypeNewMessage,
            'user_id' => (string) $fromUser->id,
            'user_name' => $fromUser->fullname,
            'user_image' => self::getProfileImageUrl($fromUser),
            'conversation_id' => (string) $conversationId,
            'message_preview' => substr($messageText, 0, 100),
            'timestamp' => (string) time(),
            'action' => Constants::actionOpenChat,
            'screen' => Constants::screenChat,
            'params' => json_encode(['conversationId' => $conversationId])
        ];

        return self::sendPushToUser($title, $message, $toUser->device_token, $eventData);
    }

    /**
     * Send live stream notification with event data
     */
    public static function sendLiveStreamNotification($streamer, $followers)
    {
        $title = env('APP_NAME');
        $message = $streamer->fullname . ' is now live!';

        $eventData = [
            'event_type' => Constants::eventTypeLiveStreamStart,
            'user_id' => (string) $streamer->id,
            'user_name' => $streamer->fullname,
            'user_image' => self::getProfileImageUrl($streamer),
            'stream_title' => $streamer->live_title ?? '',
            'timestamp' => (string) time(),
            'action' => Constants::actionJoinLiveStream,
            'screen' => Constants::screenPersonStreaming,
            'params' => json_encode([
                'channelId' => $streamer->identity,
                'userId' => $streamer->id
            ])
        ];

        // Send to all followers
        foreach ($followers as $follower) {
            if ($follower->is_notification == 1 && !empty($follower->device_token)) {
                self::sendPushToUser($title, $message, $follower->device_token, $eventData);
            }
        }
    }

    /**
     * Send post like notification with event data
     */
    public static function sendPostLikeNotification($fromUser, $toUser, $postId)
    {
        if ($toUser->is_notification != 1) {
            return;
        }

        $title = TranslationService::forUser($toUser, 'notification.title.app');
        $message = TranslationService::forUser($toUser, 'notification.post_like', [
            'name' => $fromUser->fullname
        ]);

        $eventData = [
            'event_type' => Constants::eventTypePostLike,
            'user_id' => (string) $fromUser->id,
            'user_name' => $fromUser->fullname,
            'user_image' => self::getProfileImageUrl($fromUser),
            'post_id' => (string) $postId,
            'timestamp' => (string) time(),
            'action' => Constants::actionViewPost,
            'screen' => Constants::screenPostDetail,
            'params' => json_encode(['postId' => $postId])
        ];

        return self::sendPushToUser($title, $message, $toUser->device_token, $eventData);
    }

    /**
     * Send post comment notification with event data
     */
    public static function sendPostCommentNotification($fromUser, $toUser, $postId, $commentText)
    {
        if ($toUser->is_notification != 1) {
            return;
        }

        $title = TranslationService::forUser($toUser, 'notification.title.app');
        $message = TranslationService::forUser($toUser, 'notification.comment', [
            'name' => $fromUser->fullname,
            'comment' => substr($commentText, 0, 50)
        ]);

        $eventData = [
            'event_type' => Constants::eventTypePostComment,
            'user_id' => (string) $fromUser->id,
            'user_name' => $fromUser->fullname,
            'user_image' => self::getProfileImageUrl($fromUser),
            'post_id' => (string) $postId,
            'comment_preview' => substr($commentText, 0, 100),
            'timestamp' => (string) time(),
            'action' => Constants::actionViewPost,
            'screen' => Constants::screenPostDetail,
            'params' => json_encode(['postId' => $postId])
        ];

        return self::sendPushToUser($title, $message, $toUser->device_token, $eventData);
    }

    /**
     * Send gift received notification with event data
     */
    public static function sendGiftNotification($fromUser, $toUser, $giftName, $giftValue)
    {
        if ($toUser->is_notification != 1) {
            return;
        }

        $title = env('APP_NAME');
        $message = $fromUser->fullname . ' sent you a ' . $giftName . '!';

        $eventData = [
            'event_type' => Constants::eventTypeGiftReceived,
            'user_id' => (string) $fromUser->id,
            'user_name' => $fromUser->fullname,
            'user_image' => self::getProfileImageUrl($fromUser),
            'gift_name' => $giftName,
            'gift_value' => (string) $giftValue,
            'timestamp' => (string) time(),
            'action' => Constants::actionNavigateToProfile,
            'screen' => Constants::screenUserDetail,
            'params' => json_encode(['userId' => $fromUser->id])
        ];

        return self::sendPushToUser($title, $message, $toUser->device_token, $eventData);
    }

    /**
     * Send admin notification with event data
     */
    public static function sendAdminNotification($user, $title, $message, $actionType = 'none')
    {
        if ($user->is_notification != 1) {
            return;
        }

        $eventData = [
            'event_type' => Constants::eventTypeAdminAnnouncement,
            'admin_title' => $title,
            'admin_message' => $message,
            'action_type' => $actionType,
            'timestamp' => (string) time(),
            'action' => Constants::actionViewNotification,
            'screen' => Constants::screenNotification,
            'params' => json_encode([])
        ];

        return self::sendPushToUser($title, $message, $user->device_token, $eventData);
    }

    /**
     * Send profile like (handshake) notification with event data
     */
    public static function sendProfileLikeNotification($fromUser, $toUser)
    {
        if ($toUser->is_notification != 1) {
            return;
        }

        $title = TranslationService::forUser($toUser, 'notification.title.app');
        $message = TranslationService::forUser($toUser, 'notification.profile_like', [
            'name' => $fromUser->fullname
        ]);

        // Generate conversation ID (smaller ID first)
        $conversationId = min($fromUser->id, $toUser->id) . '_' . max($fromUser->id, $toUser->id);

        $eventData = [
            'event_type' => Constants::eventTypeProfileLike,
            'user_id' => (string) $fromUser->id,
            'user_name' => $fromUser->fullname,
            'user_image' => self::getProfileImageUrl($fromUser),
            'conversation_id' => $conversationId,
            'timestamp' => (string) time(),
            'action' => Constants::actionOpenChat,
            'screen' => Constants::screenChat,
            'params' => json_encode(['conversationId' => $conversationId, 'userId' => $fromUser->id])
        ];

        return self::sendPushToUser($title, $message, $toUser->device_token, $eventData);
    }

    /**
     * Send handshake accepted notification
     * Called when User B accepts the handshake request from User A
     */
    public static function sendHandshakeAcceptedNotification($fromUser, $toUser)
    {
        if ($toUser->is_notification != 1) {
            return;
        }

        $title = TranslationService::forUser($toUser, 'notification.title.app');
        $message = TranslationService::forUser($toUser, 'notification.handshake_accepted', [
            'name' => $fromUser->fullname
        ]);

        // Generate conversation ID (smaller ID first)
        $conversationId = min($fromUser->id, $toUser->id) . '_' . max($fromUser->id, $toUser->id);

        $eventData = [
            'event_type' => Constants::eventTypeHandshakeAccepted,
            'user_id' => (string) $fromUser->id,
            'user_name' => $fromUser->fullname,
            'user_image' => self::getProfileImageUrl($fromUser),
            'conversation_id' => $conversationId,
            'timestamp' => (string) time(),
            'action' => Constants::actionOpenChat,
            'screen' => Constants::screenChat,
            'params' => json_encode(['conversationId' => $conversationId, 'userId' => $fromUser->id])
        ];

        return self::sendPushToUser($title, $message, $toUser->device_token, $eventData);
    }

    /**
     * Get profile image URL for user
     */
    private static function getProfileImageUrl($user)
    {
        if ($user && $user->images && count($user->images) > 0) {
            return env('APP_URL') . '/storage/app/public/' . $user->images[0]->image;
        }
        return '';
    }

    public static function point2point_distance($lat1, $lon1, $lat2, $lon2, $unit = 'K', $radius)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        if ($unit == "K") {
            return (($miles * 1.609344) <= $radius);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }

    public static function customReplace($string)
    {
        return str_replace(array('<', '>', '{', '}', '[', ']', '`'), '', $string);
    }

    public static function generateFakeUserIdentity()
    {
        $token =  rand(100000, 999999);
        $first = MyFunction::generateRandomString(3);
        $first .= $token;
        $first .= MyFunction::generateRandomString(3);

        $count = Users::where('identity', $first)->count();
        while ($count >= 1) {
            $token =  rand(100000, 999999);
            $first = MyFunction::generateRandomString(3);
            $first .= $token;
            $first .= MyFunction::generateRandomString(3);
            $count = Users::where('identity', $first)->count();
        }
        return $first;
    }

    public static function generateRandomString($length)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
