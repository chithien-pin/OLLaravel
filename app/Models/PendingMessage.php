<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_session_id',
        'sender_user_id',
        'streamer_user_id',
        'message_content',
        'message_type',
        'status',
        'approved_at',
        'rejected_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationship with sender user
    public function sender()
    {
        return $this->belongsTo(Users::class, 'sender_user_id', 'id');
    }

    // Relationship with streamer user
    public function streamer()
    {
        return $this->belongsTo(Users::class, 'streamer_user_id', 'id');
    }

    // Scopes for filtering
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByLiveSession($query, $liveSessionId)
    {
        return $query->where('live_session_id', $liveSessionId);
    }

    public function scopeByStreamer($query, $streamerId)
    {
        return $query->where('streamer_user_id', $streamerId);
    }

    public function scopeBySender($query, $senderId)
    {
        return $query->where('sender_user_id', $senderId);
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function approve()
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now()
        ]);
    }

    public function reject()
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now()
        ]);
    }
}