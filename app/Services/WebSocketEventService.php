<?php

namespace App\Services;

use App\Models\Images;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class WebSocketEventService
{
    // Redis channel name (Go server subscribes to this)
    const CHANNEL = 'orange:ws:events';

    /**
     * Publish event to WebSocket server via Redis.
     * Uses raw Redis command to avoid Laravel's key prefix.
     *
     * @param string $eventType
     * @param array  $targetUserIds
     * @param array  $data
     */
    public static function publish(string $eventType, array $targetUserIds, array $data = [])
    {
        try {
            $event = [
                'event_type' => $eventType,
                'target_users' => $targetUserIds,
                'data' => $data,
                'timestamp' => time(),
            ];

            // Use executeRaw to bypass Laravel's Redis key prefix (gypsylive_database_)
            $redis = Redis::connection()->client();
            $redis->executeRaw(['PUBLISH', self::CHANNEL, json_encode($event)]);

            Log::info('[WS_EVENT] Published', ['event_type' => $eventType, 'targets' => $targetUserIds]);
        } catch (\Exception $e) {
            // Never let Redis failure break the API response
            Log::error('[WS_EVENT] Failed to publish', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Publish a "user_followed" event to the target user.
     */
    public static function publishUserFollowed(int $fromUserId, int $toUserId, $fromUser)
    {
        self::publish('user_followed', [$toUserId], [
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'user_name' => $fromUser->fullname ?? '',
            'user_image' => self::getProfileImageUrl($fromUser),
        ]);
    }

    /**
     * Publish a "user_unfollowed" event to the target user.
     */
    public static function publishUserUnfollowed(int $fromUserId, int $toUserId, $fromUser)
    {
        self::publish('user_unfollowed', [$toUserId], [
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'user_name' => $fromUser->fullname ?? '',
            'user_image' => self::getProfileImageUrl($fromUser),
        ]);
    }

    /**
     * Publish a "friend_made" event to BOTH users.
     */
    public static function publishFriendMade(int $userA, int $userB, $userAData, $userBData)
    {
        self::publish('friend_made', [$userA, $userB], [
            'user_a' => [
                'user_id' => $userA,
                'user_name' => $userAData->fullname ?? '',
                'user_image' => self::getProfileImageUrl($userAData),
            ],
            'user_b' => [
                'user_id' => $userB,
                'user_name' => $userBData->fullname ?? '',
                'user_image' => self::getProfileImageUrl($userBData),
            ],
        ]);
    }

    /**
     * Publish a "friendship_broken" event to the target user.
     */
    public static function publishFriendshipBroken(int $fromUserId, int $toUserId, $fromUser)
    {
        self::publish('friendship_broken', [$toUserId], [
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'user_name' => $fromUser->fullname ?? '',
            'user_image' => self::getProfileImageUrl($fromUser),
        ]);
    }

    /**
     * Get the first profile image URL for a user.
     */
    private static function getProfileImageUrl($user): string
    {
        $baseImageUrl = env('image', env('APP_URL') . '/storage/');

        if ($user && $user->images && count($user->images) > 0) {
            return $baseImageUrl . $user->images[0]->image;
        }

        // Try loading images if not already loaded
        if ($user) {
            $firstImage = Images::where('user_id', $user->id)->first();
            if ($firstImage) {
                return $baseImageUrl . $firstImage->image;
            }
        }

        return '';
    }
}
