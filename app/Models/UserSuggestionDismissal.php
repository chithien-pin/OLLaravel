<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserSuggestionDismissal extends Model
{
    use HasFactory;
    
    protected $table = "user_suggestion_dismissals";

    protected $fillable = [
        'user_id',
        'dismissed_user_id',
        'dismissal_type',
        'reason',
        'dismissed_at',
        'expires_at'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'dismissed_user_id' => 'integer',
        'dismissed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Dismissal type constants
    const TYPE_NOT_INTERESTED = 'not_interested';
    const TYPE_HIDE_TEMPORARILY = 'hide_temporarily';
    const TYPE_HIDE_PERMANENTLY = 'hide_permanently';
    const TYPE_REPORT_INAPPROPRIATE = 'report_inappropriate';

    // Relationship with Users - user who dismissed
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    // Relationship with Users - user who was dismissed
    public function dismissedUser()
    {
        return $this->belongsTo(Users::class, 'dismissed_user_id', 'id');
    }

    // Scope for active dismissals (not expired)
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->where('dismissal_type', '!=', self::TYPE_HIDE_TEMPORARILY)
              ->orWhere('expires_at', '>', now())
              ->orWhereNull('expires_at');
        });
    }

    // Scope for expired temporary dismissals
    public function scopeExpired($query)
    {
        return $query->where('dismissal_type', self::TYPE_HIDE_TEMPORARILY)
                    ->where('expires_at', '<=', now())
                    ->whereNotNull('expires_at');
    }

    // Scope for permanent dismissals
    public function scopePermanent($query)
    {
        return $query->where('dismissal_type', self::TYPE_HIDE_PERMANENTLY);
    }

    // Scope for temporary dismissals
    public function scopeTemporary($query)
    {
        return $query->where('dismissal_type', self::TYPE_HIDE_TEMPORARILY);
    }

    // Scope for reports
    public function scopeReported($query)
    {
        return $query->where('dismissal_type', self::TYPE_REPORT_INAPPROPRIATE);
    }

    // Check if dismissal is still active
    public function isActive()
    {
        if ($this->dismissal_type === self::TYPE_HIDE_TEMPORARILY) {
            return $this->expires_at && $this->expires_at->isFuture();
        }
        
        return true; // Permanent dismissals and reports are always active
    }

    // Check if temporary dismissal has expired
    public function isExpired()
    {
        if ($this->dismissal_type === self::TYPE_HIDE_TEMPORARILY) {
            return $this->expires_at && $this->expires_at->isPast();
        }
        
        return false;
    }

    // Get remaining days for temporary dismissal
    public function getRemainingDays()
    {
        if ($this->dismissal_type === self::TYPE_HIDE_TEMPORARILY && $this->expires_at) {
            return max(0, now()->diffInDays($this->expires_at, false));
        }
        
        return null;
    }

    // Static method to create a dismissal with automatic expiry for temporary hides
    public static function createDismissal($userId, $dismissedUserId, $type, $reason = null, $hideDurationDays = null)
    {
        $data = [
            'user_id' => $userId,
            'dismissed_user_id' => $dismissedUserId,
            'dismissal_type' => $type,
            'reason' => $reason,
            'dismissed_at' => now(),
        ];

        if ($type === self::TYPE_HIDE_TEMPORARILY && $hideDurationDays) {
            $data['expires_at'] = now()->addDays($hideDurationDays);
        }

        return self::create($data);
    }

    // Static method to clean up expired temporary dismissals
    public static function cleanupExpired()
    {
        return self::expired()->delete();
    }

    // Get dismissal type label for display
    public function getTypeLabel()
    {
        switch ($this->dismissal_type) {
            case self::TYPE_NOT_INTERESTED:
                return 'Not Interested';
            case self::TYPE_HIDE_TEMPORARILY:
                return 'Hidden Temporarily';
            case self::TYPE_HIDE_PERMANENTLY:
                return 'Hidden Permanently';
            case self::TYPE_REPORT_INAPPROPRIATE:
                return 'Reported';
            default:
                return 'Unknown';
        }
    }
}