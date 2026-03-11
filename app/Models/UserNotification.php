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
    protected $appends = ['title', 'message'];

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

        return TranslationService::forUser($receiver, $key, [
            'name' => $senderName
        ]);
    }
}
