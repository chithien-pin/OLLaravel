<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Users;
use App\Models\UserSuggestionPreference;
use App\Models\UserSuggestionDismissal;
use App\Models\UserSuggestionFeedback;
use App\Models\FollowingList;
use App\Models\Like;
use App\Models\Comment;
use App\Models\Post;
use App\Models\LiveHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SuggestionController extends Controller
{
    /**
     * Get suggested users for a user with customizable algorithm
     */
    public function getSuggestedUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'start' => 'required|integer|min:0',
            'limit' => 'required|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $user = Users::find($request->user_id);
        if (!$user) {
            return response()->json([
                'status' => false, 
                'message' => 'User not found'
            ]);
        }

        // Get user preferences or create default
        $preferences = UserSuggestionPreference::where('user_id', $request->user_id)->first();
        if (!$preferences) {
            $preferences = UserSuggestionPreference::create(
                array_merge(['user_id' => $request->user_id], UserSuggestionPreference::getDefaults())
            );
        }

        // Get excluded user IDs (dismissed, blocked, already following)
        $excludeUserIds = $this->getExcludedUserIds($request->user_id, $preferences);

        // Calculate suggestions using the algorithm
        $suggestions = $this->calculateSuggestions($user, $preferences, $excludeUserIds, $request->start, $request->limit);

        // Apply VIP-based visibility restrictions
        $suggestions = $this->applyVipVisibilityRestrictions($user, $suggestions, $request->start);

        // Log view events for analytics
        foreach ($suggestions as $suggestion) {
            UserSuggestionFeedback::logAction(
                $request->user_id, 
                $suggestion->id, 
                UserSuggestionFeedback::ACTION_VIEWED,
                $suggestion->suggestion_reason ?? UserSuggestionFeedback::REASON_ALGORITHM
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Suggested users fetched successfully',
            'data' => $suggestions
        ]);
    }

    /**
     * Get user's suggestion preferences
     */
    public function getSuggestionPreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $preferences = UserSuggestionPreference::where('user_id', $request->user_id)->first();
        
        if (!$preferences) {
            // Create default preferences
            $preferences = UserSuggestionPreference::create(
                array_merge(['user_id' => $request->user_id], UserSuggestionPreference::getDefaults())
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Suggestion preferences fetched successfully',
            'data' => $preferences
        ]);
    }

    /**
     * Update user's suggestion preferences
     */
    public function updateSuggestionPreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'location_radius' => 'nullable|integer|min:1|max:500',
            'location_weight' => 'nullable|integer|min:0|max:100',
            'interests_weight' => 'nullable|integer|min:0|max:100',
            'mutual_friends_weight' => 'nullable|integer|min:0|max:100',
            'age_range_min' => 'nullable|integer|min:18|max:80',
            'age_range_max' => 'nullable|integer|min:18|max:80',
            'gender_preference' => 'nullable|integer|in:0,1,2,3',
            'activity_level_weight' => 'nullable|integer|min:0|max:100',
            'new_users_weight' => 'nullable|integer|min:0|max:100',
            'social_engagement_weight' => 'nullable|integer|min:0|max:100',
            'dating_compatibility_weight' => 'nullable|integer|min:0|max:100',
            'include_verified_only' => 'nullable|boolean',
            'include_streamers_only' => 'nullable|boolean',
            'exclude_blocked_friends' => 'nullable|boolean',
            'min_common_interests' => 'nullable|integer|min:0|max:10',
            'max_suggestions_per_day' => 'nullable|integer|min:5|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $updateData = $request->only([
            'location_radius', 'location_weight', 'interests_weight', 'mutual_friends_weight',
            'age_range_min', 'age_range_max', 'gender_preference', 'activity_level_weight',
            'new_users_weight', 'social_engagement_weight', 'dating_compatibility_weight',
            'include_verified_only', 'include_streamers_only', 'exclude_blocked_friends',
            'min_common_interests', 'max_suggestions_per_day'
        ]);

        // Remove null values
        $updateData = array_filter($updateData, function($value) {
            return !is_null($value);
        });

        $preferences = UserSuggestionPreference::updateOrCreate(
            ['user_id' => $request->user_id],
            $updateData
        );

        // Normalize weights if needed
        $preferences->normalizeWeights();
        $preferences->save();

        return response()->json([
            'status' => true,
            'message' => 'Preferences updated successfully',
            'data' => $preferences
        ]);
    }

    /**
     * Dismiss a suggestion
     */
    public function dismissSuggestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'dismissed_user_id' => 'required|integer',
            'dismissal_type' => 'required|in:not_interested,hide_temporarily,hide_permanently,report_inappropriate',
            'reason' => 'nullable|string|max:255',
            'hide_duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $hideDurationDays = $request->hide_duration_days ?? 30; // Default 30 days for temporary hide

        $dismissal = UserSuggestionDismissal::createDismissal(
            $request->user_id,
            $request->dismissed_user_id,
            $request->dismissal_type,
            $request->reason,
            $hideDurationDays
        );

        // Log feedback for analytics
        UserSuggestionFeedback::logAction(
            $request->user_id,
            $request->dismissed_user_id,
            $request->dismissal_type === 'report_inappropriate' ? 
                UserSuggestionFeedback::ACTION_REPORTED : UserSuggestionFeedback::ACTION_DISMISSED,
            $request->suggestion_reason ?? UserSuggestionFeedback::REASON_ALGORITHM
        );

        return response()->json([
            'status' => true,
            'message' => 'Suggestion dismissed successfully',
            'data' => $dismissal
        ]);
    }

    /**
     * Undo a dismissal
     */
    public function undoDismissal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'dismissed_user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $deleted = UserSuggestionDismissal::where('user_id', $request->user_id)
            ->where('dismissed_user_id', $request->dismissed_user_id)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => $deleted ? 'Dismissal undone successfully' : 'No dismissal found to undo'
        ]);
    }

    /**
     * Get dismissed users list
     */
    public function getDismissedUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'start' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $start = $request->start ?? 0;
        $limit = $request->limit ?? 20;

        $dismissals = UserSuggestionDismissal::where('user_id', $request->user_id)
            ->with(['dismissedUser', 'dismissedUser.images'])
            ->orderByDesc('dismissed_at')
            ->offset($start)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Dismissed users fetched successfully',
            'data' => $dismissals
        ]);
    }

    /**
     * Rate a suggestion quality
     */
    public function rateSuggestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'suggested_user_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'suggestion_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        UserSuggestionFeedback::logAction(
            $request->user_id,
            $request->suggested_user_id,
            UserSuggestionFeedback::ACTION_RATED,
            $request->suggestion_reason ?? UserSuggestionFeedback::REASON_ALGORITHM,
            $request->rating
        );

        return response()->json([
            'status' => true,
            'message' => 'Rating submitted successfully'
        ]);
    }

    /**
     * PRIVATE HELPER METHODS
     */

    /**
     * Get list of user IDs to exclude from suggestions
     */
    private function getExcludedUserIds($userId, $preferences)
    {
        $excludeIds = [$userId]; // Always exclude self

        // Get dismissed user IDs (active dismissals only)
        $dismissedIds = UserSuggestionDismissal::where('user_id', $userId)
            ->active()
            ->pluck('dismissed_user_id')
            ->toArray();
        
        $excludeIds = array_merge($excludeIds, $dismissedIds);

        // Get already following user IDs
        $followingIds = FollowingList::where('my_user_id', $userId)
            ->pluck('user_id')
            ->toArray();
        
        $excludeIds = array_merge($excludeIds, $followingIds);

        // Get blocked user IDs if preference is set
        if ($preferences->exclude_blocked_friends) {
            $user = Users::find($userId);
            if ($user && $user->blocked_users) {
                $blockedIds = explode(',', $user->blocked_users);
                $blockedIds = array_filter($blockedIds, 'is_numeric');
                $excludeIds = array_merge($excludeIds, $blockedIds);
            }
        }

        return array_unique($excludeIds);
    }

    /**
     * Main suggestion algorithm
     */
    private function calculateSuggestions($user, $preferences, $excludeUserIds, $start, $limit)
    {
        $baseQuery = Users::where('is_block', 0)
            ->whereNotIn('id', $excludeUserIds)
            ->with(['images', 'activeRole', 'activePackage']);

        // Apply filters based on preferences
        $baseQuery = $this->applyPreferenceFilters($baseQuery, $user, $preferences);

        // Get all potential candidates
        $candidates = $baseQuery->get();

        // Get liked users array (same logic as getExplorePageProfileList)
        $likedUsers = \App\Models\LikedProfile::where('my_user_id', $user->id)
            ->pluck('user_id')
            ->toArray();

        // Calculate scores for each candidate
        $scoredCandidates = $candidates->map(function($candidate) use ($user, $preferences, $likedUsers) {
            $score = $this->calculateUserScore($user, $candidate, $preferences);
            $candidate->suggestion_score = $score['total_score'];
            $candidate->suggestion_reason = $score['primary_reason'];
            $candidate->score_breakdown = $score['breakdown'];

            // Add role information
            $candidate->current_role_type = $candidate->getCurrentRoleType();
            $candidate->is_vip_user = $candidate->isVip();

            // Add package information
            $candidate->current_package_type = $candidate->getCurrentPackageType();
            $candidate->has_package = $candidate->hasPackage();
            $candidate->is_millionaire = $candidate->isMillionaire();
            $candidate->is_billionaire = $candidate->isBillionaire();
            $candidate->is_celebrity = $candidate->isCelebrity();
            $candidate->package_badge_color = $candidate->getPackageBadgeColor();

            // Add is_like status (same logic as getExplorePageProfileList)
            $candidate->is_like = in_array($candidate->id, $likedUsers);

            return $candidate;
        });

        // Sort by score and apply pagination
        $sortedCandidates = $scoredCandidates->sortByDesc('suggestion_score')
            ->skip($start)
            ->take($limit)
            ->values();

        return $sortedCandidates;
    }

    /**
     * Apply preference-based filters to the query
     */
    private function applyPreferenceFilters($query, $user, $preferences)
    {
        // Age range filter
        if ($preferences->age_range_min && $preferences->age_range_max) {
            $query->whereBetween('age', [$preferences->age_range_min, $preferences->age_range_max]);
        }

        // Gender preference filter
        if ($preferences->gender_preference > 0) {
            $query->where('gender', $preferences->gender_preference);
        }

        // Verified users only
        if ($preferences->include_verified_only) {
            $query->where('is_verified', 2); // 2 = verified
        }

        // Streamers only
        if ($preferences->include_streamers_only) {
            $query->where('can_go_live', 2); // 2 = can go live
        }

        // Location radius filter (if user has location)
        if ($user->lattitude && $user->longitude && $preferences->location_radius) {
            $lat = floatval($user->lattitude);
            $lng = floatval($user->longitude);
            $radius = $preferences->location_radius;

            // Using Haversine formula for distance calculation
            $query->whereRaw("
                (6371 * acos(cos(radians(?)) * cos(radians(lattitude)) * 
                cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(lattitude)))) <= ?
            ", [$lat, $lng, $lat, $radius]);
        }

        return $query;
    }

    /**
     * Calculate suggestion score for a candidate user
     */
    private function calculateUserScore($user, $candidate, $preferences)
    {
        $scores = [];
        $totalScore = 0;
        $primaryReason = 'algorithm';
        
        // 1. Mutual Friends Score
        $mutualFriendsScore = $this->calculateMutualFriendsScore($user->id, $candidate->id);
        $weightedMutualScore = ($mutualFriendsScore * $preferences->mutual_friends_weight) / 100;
        $scores['mutual_friends'] = $weightedMutualScore;
        $totalScore += $weightedMutualScore;
        
        if ($mutualFriendsScore > 0) {
            $primaryReason = UserSuggestionFeedback::REASON_MUTUAL_FRIENDS;
        }

        // 2. Common Interests Score
        $interestsScore = $this->calculateInterestsScore($user, $candidate, $preferences);
        $weightedInterestsScore = ($interestsScore * $preferences->interests_weight) / 100;
        $scores['common_interests'] = $weightedInterestsScore;
        $totalScore += $weightedInterestsScore;
        
        if ($interestsScore > $mutualFriendsScore) {
            $primaryReason = UserSuggestionFeedback::REASON_COMMON_INTERESTS;
        }

        // 3. Location Proximity Score
        $locationScore = $this->calculateLocationScore($user, $candidate, $preferences);
        $weightedLocationScore = ($locationScore * $preferences->location_weight) / 100;
        $scores['location'] = $weightedLocationScore;
        $totalScore += $weightedLocationScore;

        if ($locationScore > 70) { // High location match
            $primaryReason = UserSuggestionFeedback::REASON_NEARBY;
        }

        // 4. Social Engagement Score
        $socialScore = $this->calculateSocialEngagementScore($user->id, $candidate->id);
        $weightedSocialScore = ($socialScore * $preferences->social_engagement_weight) / 100;
        $scores['social_engagement'] = $weightedSocialScore;
        $totalScore += $weightedSocialScore;

        // 5. Activity Level Score
        $activityScore = $this->calculateActivityScore($candidate);
        $weightedActivityScore = ($activityScore * $preferences->activity_level_weight) / 100;
        $scores['activity'] = $weightedActivityScore;
        $totalScore += $weightedActivityScore;

        // 6. Dating Compatibility Score
        $datingScore = $this->calculateDatingCompatibilityScore($user, $candidate);
        $weightedDatingScore = ($datingScore * $preferences->dating_compatibility_weight) / 100;
        $scores['dating_compatibility'] = $weightedDatingScore;
        $totalScore += $weightedDatingScore;

        // 7. New User Bonus
        $newUserScore = $this->calculateNewUserScore($candidate);
        $weightedNewUserScore = ($newUserScore * $preferences->new_users_weight) / 100;
        $scores['new_user'] = $weightedNewUserScore;
        $totalScore += $weightedNewUserScore;

        if ($newUserScore > 80) {
            $primaryReason = UserSuggestionFeedback::REASON_NEW_USER;
        }

        return [
            'total_score' => round($totalScore, 2),
            'primary_reason' => $primaryReason,
            'breakdown' => $scores
        ];
    }

    /**
     * Calculate mutual friends score (0-100)
     */
    private function calculateMutualFriendsScore($userId, $candidateId)
    {
        $mutualFriends = DB::table('following_lists as fl1')
            ->join('following_lists as fl2', 'fl1.user_id', '=', 'fl2.user_id')
            ->where('fl1.my_user_id', $userId)
            ->where('fl2.my_user_id', $candidateId)
            ->count();

        // Scale to 0-100 (max 10 mutual friends = 100 points)
        return min($mutualFriends * 10, 100);
    }

    /**
     * Calculate common interests score (0-100)
     */
    private function calculateInterestsScore($user, $candidate, $preferences)
    {
        if (!$user->interests || !$candidate->interests) {
            return 0;
        }

        $userInterests = explode(',', $user->interests);
        $candidateInterests = explode(',', $candidate->interests);
        
        $commonInterests = array_intersect($userInterests, $candidateInterests);
        $commonCount = count($commonInterests);

        // Check minimum common interests requirement
        if ($commonCount < $preferences->min_common_interests) {
            return 0;
        }

        // Scale to 0-100 (max 5 common interests = 100 points)
        return min($commonCount * 20, 100);
    }

    /**
     * Calculate location proximity score (0-100)
     */
    private function calculateLocationScore($user, $candidate, $preferences)
    {
        if (!$user->lattitude || !$user->longitude || 
            !$candidate->lattitude || !$candidate->longitude) {
            return 0;
        }

        $distance = $this->calculateDistance(
            $user->lattitude, $user->longitude,
            $candidate->lattitude, $candidate->longitude
        );

        $maxRadius = $preferences->location_radius;
        
        if ($distance > $maxRadius) {
            return 0;
        }

        // Closer = higher score
        return max(0, 100 - ($distance / $maxRadius * 100));
    }

    /**
     * Calculate social engagement score based on interactions (0-100)
     */
    private function calculateSocialEngagementScore($userId, $candidateId)
    {
        // Check if users have liked each other's posts
        $userLikedCandidatePosts = Like::whereHas('post', function($query) use ($candidateId) {
            $query->where('user_id', $candidateId);
        })->where('user_id', $userId)->count();

        $candidateLikedUserPosts = Like::whereHas('post', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->where('user_id', $candidateId)->count();

        // Check comments
        $userCommentedOnCandidate = Comment::whereHas('post', function($query) use ($candidateId) {
            $query->where('user_id', $candidateId);
        })->where('user_id', $userId)->count();

        $candidateCommentedOnUser = Comment::whereHas('post', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->where('user_id', $candidateId)->count();

        $totalInteractions = $userLikedCandidatePosts + $candidateLikedUserPosts + 
                           $userCommentedOnCandidate + $candidateCommentedOnUser;

        return min($totalInteractions * 10, 100);
    }

    /**
     * Calculate activity level score (0-100)
     */
    private function calculateActivityScore($candidate)
    {
        $recentPosts = Post::where('user_id', $candidate->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recentStreams = LiveHistory::where('user_id', $candidate->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $lastActivity = $candidate->update_at ? 
            now()->diffInDays($candidate->update_at) : 365;

        $activityScore = 0;
        
        // Recent posts bonus
        $activityScore += min($recentPosts * 15, 40);
        
        // Recent streams bonus
        $activityScore += min($recentStreams * 20, 30);
        
        // Last seen bonus
        if ($lastActivity <= 1) {
            $activityScore += 30;
        } elseif ($lastActivity <= 7) {
            $activityScore += 20;
        } elseif ($lastActivity <= 30) {
            $activityScore += 10;
        }

        return min($activityScore, 100);
    }

    /**
     * Calculate dating compatibility score (0-100)
     */
    private function calculateDatingCompatibilityScore($user, $candidate)
    {
        $score = 0;

        // Age compatibility
        $ageDiff = abs($user->age - $candidate->age);
        if ($ageDiff <= 2) {
            $score += 30;
        } elseif ($ageDiff <= 5) {
            $score += 20;
        } elseif ($ageDiff <= 10) {
            $score += 10;
        }

        // Gender preference compatibility
        if (($user->gender_preferred == 0 || $user->gender_preferred == $candidate->gender) &&
            ($candidate->gender_preferred == 0 || $candidate->gender_preferred == $user->gender)) {
            $score += 40;
        }

        // Age range preference compatibility
        if ($user->age >= $candidate->age_preferred_min && 
            $user->age <= $candidate->age_preferred_max &&
            $candidate->age >= $user->age_preferred_min && 
            $candidate->age <= $user->age_preferred_max) {
            $score += 30;
        }

        return min($score, 100);
    }

    /**
     * Calculate new user bonus score (0-100)
     */
    private function calculateNewUserScore($candidate)
    {
        $daysSinceJoined = now()->diffInDays($candidate->created_at);
        
        if ($daysSinceJoined <= 7) {
            return 100; // Very new user
        } elseif ($daysSinceJoined <= 30) {
            return 50; // New user
        }
        
        return 0; // Not new
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Apply VIP-based visibility restrictions to suggestions
     * Non-VIP users can see first 3 suggestions clearly, rest are blurred
     * VIP users can see all suggestions clearly
     */
    private function applyVipVisibilityRestrictions($user, $suggestions, $start = 0)
    {
        $isVip = $user->isVip();
        
        foreach ($suggestions as $index => $suggestion) {
            // Calculate absolute position (considering pagination)
            $absolutePosition = $start + $index;
            
            // For non-VIP users: blur suggestions after the first 3 (positions 0, 1, 2)
            if (!$isVip && $absolutePosition >= 3) {
                $suggestion->is_blurred = true;
            } else {
                $suggestion->is_blurred = false;
            }
        }

        return $suggestions;
    }
}