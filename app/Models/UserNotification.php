<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\TranslationService;

class UserNotification extends Model
{
    use HasFactory;
    public $table = "user_notification";

    protected $fillable = [
        'user_id', 'my_user_id', 'item_id', 'type'
    ];

    // Append title và message vào JSON response
    protected $appends = ['title', 'message', 'post_thumbnail', 'post_id_resolved'];

    // Sender user (người gửi notification) - withTrashed để hiển thị cả user đã bị xoá
    public function user()
    {
        return $this->hasOne(Users::class, "id", 'my_user_id')->withTrashed();
    }

    // Receiver user (người nhận notification - để lấy language)
    public function receiverUser()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id')->withTrashed();
    }

    // Generate title dựa trên type
    public function getTitleAttribute()
    {
        $receiver = $this->receiverUser;

        $titleKeys = [
            1 => 'notification.title.app',
            2 => 'notification.title.comment',
            3 => 'notification.title.post_like',
            4 => 'notification.title.profile_like',
            8 => 'notification.title.livestream',
            11 => 'notification.title.handshake_accepted',
            12 => 'notification.title.new_friend',
        ];

        $key = $titleKeys[$this->type] ?? 'notification.title.app';
        return TranslationService::forUser($receiver, $key);
    }

    // Generate message dựa trên type + sender name
    public function getMessageAttribute()
    {
        $receiver = $this->receiverUser;
        $sender = $this->user;
        $senderName = $sender ? $sender->fullname : 'Someone';

        $messageKeys = [
            1 => 'notification.follow',
            2 => 'notification.comment',
            3 => 'notification.post_like',
            4 => 'notification.profile_like',
            8 => 'notification.livestream',
            11 => 'notification.handshake_accepted',
            12 => 'notification.new_friend',
        ];

        $key = $messageKeys[$this->type] ?? '';
        if (empty($key)) return '';

        // Type 2 (Comment): return comment text directly (truncated)
        if ($this->type == 2 && $this->item_id) {
            $comment = Comment::withTrashed()->find($this->item_id);
            if ($comment && $comment->description) {
                $text = $comment->description;
                return mb_strlen($text) > 50 ? mb_substr($text, 0, 50) . '...' : $text;
            }
            return '';
        }

        return TranslationService::forUser($receiver, $key, [
            'name' => $senderName
        ]);
    }

    // Generate post thumbnail URL for post-related notifications (like, comment)
    public function getPostThumbnailAttribute()
    {
        $postId = null;

        if ($this->type == 3 && $this->item_id) {
            // Type 3 (Like): item_id = post_id
            $postId = $this->item_id;
        } elseif ($this->type == 2 && $this->item_id) {
            // Type 2 (Comment): item_id = comment_id → get post_id
            $comment = Comment::withTrashed()->find($this->item_id);
            $postId = $comment ? $comment->post_id : null;
        }

        if (!$postId) return null;

        $media = PostMedia::where('post_id', $postId)
            ->orderBy('sort_order')
            ->first();

        if (!$media) return null;

        // Video: use r2_thumbnail_url
        if ($media->media_type == 1) {
            return $media->r2_thumbnail_url;
        }

        // Image: use gallery_path with base URL
        if ($media->gallery_path) {
            return url('storage/' . $media->gallery_path);
        }

        return null;
    }

    // Resolve post_id for post-related notifications
    public function getPostIdResolvedAttribute()
    {
        if ($this->type == 3 && $this->item_id) {
            return $this->item_id; // item_id IS the post_id
        }
        if ($this->type == 2 && $this->item_id) {
            $comment = Comment::withTrashed()->find($this->item_id);
            return $comment ? $comment->post_id : null;
        }
        return null;
    }
}
