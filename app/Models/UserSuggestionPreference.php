<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSuggestionPreference extends Model
{
    use HasFactory;
    
    protected $table = "user_suggestion_preferences";

    protected $fillable = [
        'user_id',
        'location_radius',
        'location_weight',
        'interests_weight',
        'mutual_friends_weight',
        'age_range_min',
        'age_range_max',
        'gender_preference',
        'activity_level_weight',
        'new_users_weight',
        'social_engagement_weight',
        'dating_compatibility_weight',
        'include_verified_only',
        'include_streamers_only',
        'exclude_blocked_friends',
        'min_common_interests',
        'max_suggestions_per_day'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'location_radius' => 'integer',
        'location_weight' => 'integer',
        'interests_weight' => 'integer',
        'mutual_friends_weight' => 'integer',
        'age_range_min' => 'integer',
        'age_range_max' => 'integer',
        'gender_preference' => 'integer',
        'activity_level_weight' => 'integer',
        'new_users_weight' => 'integer',
        'social_engagement_weight' => 'integer',
        'dating_compatibility_weight' => 'integer',
        'include_verified_only' => 'boolean',
        'include_streamers_only' => 'boolean',
        'exclude_blocked_friends' => 'boolean',
        'min_common_interests' => 'integer',
        'max_suggestions_per_day' => 'integer',
    ];

    // Relationship with Users
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    // Static method to get default preferences
    public static function getDefaults()
    {
        return [
            'location_radius' => 50,
            'location_weight' => 15,
            'interests_weight' => 25,
            'mutual_friends_weight' => 40,
            'age_range_min' => 18,
            'age_range_max' => 50,
            'gender_preference' => 0, // 0=all, 1=male, 2=female, 3=other
            'activity_level_weight' => 3,
            'new_users_weight' => 2,
            'social_engagement_weight' => 10,
            'dating_compatibility_weight' => 5,
            'include_verified_only' => false,
            'include_streamers_only' => false,
            'exclude_blocked_friends' => true,
            'min_common_interests' => 1,
            'max_suggestions_per_day' => 20,
        ];
    }

    // Method to validate weights sum to 100%
    public function validateWeights()
    {
        $totalWeight = $this->location_weight + 
                      $this->interests_weight + 
                      $this->mutual_friends_weight + 
                      $this->activity_level_weight + 
                      $this->new_users_weight + 
                      $this->social_engagement_weight + 
                      $this->dating_compatibility_weight;
        
        return $totalWeight === 100;
    }

    // Method to auto-adjust weights to sum to 100%
    public function normalizeWeights()
    {
        $totalWeight = $this->location_weight + 
                      $this->interests_weight + 
                      $this->mutual_friends_weight + 
                      $this->activity_level_weight + 
                      $this->new_users_weight + 
                      $this->social_engagement_weight + 
                      $this->dating_compatibility_weight;

        if ($totalWeight !== 100 && $totalWeight > 0) {
            $factor = 100 / $totalWeight;
            $this->location_weight = round($this->location_weight * $factor);
            $this->interests_weight = round($this->interests_weight * $factor);
            $this->mutual_friends_weight = round($this->mutual_friends_weight * $factor);
            $this->activity_level_weight = round($this->activity_level_weight * $factor);
            $this->new_users_weight = round($this->new_users_weight * $factor);
            $this->social_engagement_weight = round($this->social_engagement_weight * $factor);
            $this->dating_compatibility_weight = round($this->dating_compatibility_weight * $factor);
        }
    }

    // Scope for active preferences
    public function scopeActive($query)
    {
        return $query->whereNotNull('user_id');
    }
}