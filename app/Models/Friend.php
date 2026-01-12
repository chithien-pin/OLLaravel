<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Friend extends Model
{
    use HasFactory;

    public $table = "friends";

    protected $fillable = [
        'user_id',
        'friend_id'
    ];

    /**
     * Get the first user in the friendship (smaller ID)
     */
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    /**
     * Get the second user in the friendship (larger ID)
     */
    public function friend()
    {
        return $this->belongsTo(Users::class, 'friend_id', 'id');
    }

    /**
     * Create a friendship between two users
     * Automatically orders IDs so user_id < friend_id
     */
    public static function createFriendship(int $userId1, int $userId2): ?Friend
    {
        $smallerId = min($userId1, $userId2);
        $largerId = max($userId1, $userId2);

        // Check if friendship already exists
        $existing = self::where('user_id', $smallerId)
            ->where('friend_id', $largerId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return self::create([
            'user_id' => $smallerId,
            'friend_id' => $largerId
        ]);
    }

    /**
     * Check if two users are friends
     */
    public static function areFriends(int $userId1, int $userId2): bool
    {
        $smallerId = min($userId1, $userId2);
        $largerId = max($userId1, $userId2);

        return self::where('user_id', $smallerId)
            ->where('friend_id', $largerId)
            ->exists();
    }

    /**
     * Remove friendship between two users
     */
    public static function removeFriendship(int $userId1, int $userId2): bool
    {
        $smallerId = min($userId1, $userId2);
        $largerId = max($userId1, $userId2);

        return self::where('user_id', $smallerId)
            ->where('friend_id', $largerId)
            ->delete() > 0;
    }

    /**
     * Get all friends for a user
     */
    public static function getFriendsForUser(int $userId)
    {
        return Users::whereIn('id', function ($query) use ($userId) {
            $query->select('friend_id')
                ->from('friends')
                ->where('user_id', $userId)
                ->union(
                    \DB::table('friends')
                        ->select('user_id')
                        ->where('friend_id', $userId)
                );
        })->with('images')->get();
    }

    /**
     * Get friend IDs for a user
     */
    public static function getFriendIds(int $userId): array
    {
        $asUser = self::where('user_id', $userId)->pluck('friend_id')->toArray();
        $asFriend = self::where('friend_id', $userId)->pluck('user_id')->toArray();

        return array_merge($asUser, $asFriend);
    }

    /**
     * Get friends count for a user
     */
    public static function getFriendsCount(int $userId): int
    {
        return self::where('user_id', $userId)
            ->orWhere('friend_id', $userId)
            ->count();
    }
}
