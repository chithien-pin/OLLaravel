<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSuggestionFeedback extends Model
{
    use HasFactory;
    
    protected $table = "user_suggestion_feedback";

    protected $fillable = [
        'user_id',
        'suggested_user_id',
        'action',
        'suggestion_reason',
        'feedback_score'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'suggested_user_id' => 'integer',
        'feedback_score' => 'integer',
    ];

    // Action type constants
    const ACTION_VIEWED = 'viewed';
    const ACTION_PROFILE_VISITED = 'profile_visited';
    const ACTION_FOLLOWED = 'followed';
    const ACTION_DISMISSED = 'dismissed';
    const ACTION_REPORTED = 'reported';
    const ACTION_RATED = 'rated';

    // Suggestion reason constants
    const REASON_MUTUAL_FRIENDS = 'mutual_friends';
    const REASON_COMMON_INTERESTS = 'common_interests';
    const REASON_NEARBY = 'nearby';
    const REASON_NEW_USER = 'new_user';
    const REASON_ACTIVITY = 'activity';
    const REASON_SOCIAL_ENGAGEMENT = 'social_engagement';
    const REASON_DATING_COMPATIBILITY = 'dating_compatibility';
    const REASON_ALGORITHM = 'algorithm';

    // Relationship with Users - user who took action
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    // Relationship with Users - suggested user
    public function suggestedUser()
    {
        return $this->belongsTo(Users::class, 'suggested_user_id', 'id');
    }

    // Scope for specific actions
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    // Scope for positive actions (profile visits, follows)
    public function scopePositive($query)
    {
        return $query->whereIn('action', [
            self::ACTION_PROFILE_VISITED,
            self::ACTION_FOLLOWED,
        ]);
    }

    // Scope for negative actions (dismissed, reported)
    public function scopeNegative($query)
    {
        return $query->whereIn('action', [
            self::ACTION_DISMISSED,
            self::ACTION_REPORTED,
        ]);
    }

    // Scope for rated feedback
    public function scopeRated($query)
    {
        return $query->where('action', self::ACTION_RATED)
                    ->whereNotNull('feedback_score');
    }

    // Scope for specific suggestion reason
    public function scopeReason($query, $reason)
    {
        return $query->where('suggestion_reason', $reason);
    }

    // Scope for recent feedback (last 30 days)
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Static method to log feedback
    public static function logAction($userId, $suggestedUserId, $action, $suggestionReason = null, $feedbackScore = null)
    {
        return self::create([
            'user_id' => $userId,
            'suggested_user_id' => $suggestedUserId,
            'action' => $action,
            'suggestion_reason' => $suggestionReason,
            'feedback_score' => $feedbackScore,
        ]);
    }

    // Static method to get success rate for a specific suggestion reason
    public static function getSuccessRateByReason($reason, $days = 30)
    {
        $totalSuggestions = self::reason($reason)->recent($days)->count();
        
        if ($totalSuggestions === 0) {
            return 0;
        }
        
        $successfulActions = self::reason($reason)
                                ->recent($days)
                                ->positive()
                                ->count();
        
        return round(($successfulActions / $totalSuggestions) * 100, 2);
    }

    // Static method to get average rating for suggestions
    public static function getAverageRating($days = 30)
    {
        return self::rated()
                  ->recent($days)
                  ->avg('feedback_score') ?: 0;
    }

    // Static method to get most successful suggestion reasons
    public static function getTopReasons($limit = 5, $days = 30)
    {
        return self::recent($days)
                  ->positive()
                  ->whereNotNull('suggestion_reason')
                  ->selectRaw('suggestion_reason, COUNT(*) as success_count')
                  ->groupBy('suggestion_reason')
                  ->orderByDesc('success_count')
                  ->limit($limit)
                  ->get();
    }

    // Get action label for display
    public function getActionLabel()
    {
        switch ($this->action) {
            case self::ACTION_VIEWED:
                return 'Viewed';
            case self::ACTION_PROFILE_VISITED:
                return 'Profile Visited';
            case self::ACTION_FOLLOWED:
                return 'Followed';
            case self::ACTION_DISMISSED:
                return 'Dismissed';
            case self::ACTION_REPORTED:
                return 'Reported';
            case self::ACTION_RATED:
                return 'Rated';
            default:
                return 'Unknown';
        }
    }

    // Get suggestion reason label for display
    public function getReasonLabel()
    {
        switch ($this->suggestion_reason) {
            case self::REASON_MUTUAL_FRIENDS:
                return 'Mutual Friends';
            case self::REASON_COMMON_INTERESTS:
                return 'Common Interests';
            case self::REASON_NEARBY:
                return 'Nearby';
            case self::REASON_NEW_USER:
                return 'New User';
            case self::REASON_ACTIVITY:
                return 'Activity Level';
            case self::REASON_SOCIAL_ENGAGEMENT:
                return 'Social Engagement';
            case self::REASON_DATING_COMPATIBILITY:
                return 'Dating Compatibility';
            case self::REASON_ALGORITHM:
                return 'Algorithm';
            default:
                return 'Other';
        }
    }
}