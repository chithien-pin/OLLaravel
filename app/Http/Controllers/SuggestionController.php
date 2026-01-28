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
use App\Models\Friend;
use App\Models\LikedProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SuggestionController extends Controller
{
    /**
     * Get suggested users for a user with automatic fallback logic
     * Always returns at least 20 suggestions (or max available)
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

        $start = $request->start;
        $limit = $request->limit;
        $minResults = 10; // Minimum results to return

        // Get excluded user IDs (dismissed, blocked, already following)
        $excludeUserIds = $this->getExcludedUserIds($request->user_id, null);

        // Use automatic fallback strategy to get suggestions
        $suggestions = $this->getSuggestionsWithFallback($user, $excludeUserIds, $start, $limit, $minResults);

        // Apply VIP-based visibility restrictions
        $suggestions = $this->applyVipVisibilityRestrictions($user, $suggestions, $start);

        // Log view events for analytics (only for first page to avoid spam)
        if ($start == 0) {
            foreach ($suggestions->take(10) as $suggestion) {
                UserSuggestionFeedback::logAction(
                    $request->user_id,
                    $suggestion->id,
                    UserSuggestionFeedback::ACTION_VIEWED,
                    $suggestion->suggestion_reason ?? UserSuggestionFeedback::REASON_ALGORITHM
                );
            }
        }

        // Get total count for pagination
        $totalCount = $this->getTotalSuggestionsCount($user, $excludeUserIds);

        return response()->json([
            'status' => true,
            'message' => 'Suggested users fetched successfully',
            'data' => $suggestions,
            'pagination' => [
                'start' => $start,
                'limit' => $limit,
                'count' => $suggestions->count(),
                'total' => $totalCount,
                'has_more' => ($start + $suggestions->count()) < $totalCount
            ]
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
    private function getExcludedUserIds($userId, $preferences = null)
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

        // Get blocked user IDs (always exclude)
        $user = Users::find($userId);
        if ($user && $user->blocked_users) {
            $blockedIds = explode(',', $user->blocked_users);
            $blockedIds = array_filter($blockedIds, 'is_numeric');
            $excludeIds = array_merge($excludeIds, $blockedIds);
        }

        return array_unique($excludeIds);
    }

    /**
     * Get suggestions with automatic fallback strategy
     * Progressively relaxes filters to ensure minimum results
     */
    private function getSuggestionsWithFallback($user, $excludeUserIds, $start, $limit, $minResults = 20)
    {
        // Get liked users for is_like status
        $likedUsers = \App\Models\LikedProfile::where('my_user_id', $user->id)
            ->pluck('user_id')
            ->toArray();

        // Strategy 1: Try with location filter (nearby users first)
        $suggestions = $this->querySuggestionsWithFilters($user, $excludeUserIds, $likedUsers, [
            'use_location' => true,
            'location_radius' => 100, // 100km first
        ]);

        // Strategy 2: Expand location radius if not enough
        if ($suggestions->count() < $minResults) {
            $suggestions = $this->querySuggestionsWithFilters($user, $excludeUserIds, $likedUsers, [
                'use_location' => true,
                'location_radius' => 500, // 500km
            ]);
        }

        // Strategy 3: Remove location filter entirely (global)
        if ($suggestions->count() < $minResults) {
            $suggestions = $this->querySuggestionsWithFilters($user, $excludeUserIds, $likedUsers, [
                'use_location' => false,
            ]);
        }

        // Apply pagination
        $paginatedSuggestions = $suggestions->skip($start)->take($limit)->values();

        return $paginatedSuggestions;
    }

    /**
     * Query suggestions with specific filters (OPTIMIZED)
     */
    private function querySuggestionsWithFilters($user, $excludeUserIds, $likedUsers, $filters = [])
    {
        $query = Users::where('is_block', 0)
            ->whereNotIn('id', $excludeUserIds)
            ->with(['images', 'activeRole', 'activePackage']);

        // Apply location filter if enabled and user has location
        if (($filters['use_location'] ?? false) && $user->lattitude && $user->longitude) {
            $lat = floatval($user->lattitude);
            $lng = floatval($user->longitude);
            $radius = $filters['location_radius'] ?? 100;

            $query->whereNotNull('lattitude')
                ->whereNotNull('longitude')
                ->whereRaw("
                    (6371 * acos(cos(radians(?)) * cos(radians(lattitude)) *
                    cos(radians(longitude) - radians(?)) + sin(radians(?)) *
                    sin(radians(lattitude)))) <= ?
                ", [$lat, $lng, $lat, $radius]);
        }

        // Get candidates
        $candidates = $query->get();
        $candidateIds = $candidates->pluck('id')->toArray();

        if (empty($candidateIds)) {
            return collect([]);
        }

        // ============ BATCH PRE-FETCH ALL DATA (Optimized) ============

        // 1. Get followers of current user (for "Follow back")
        $followersOfCurrentUser = FollowingList::where('user_id', $user->id)
            ->pluck('my_user_id')
            ->toArray();

        // 2. Get users that current user is following (my friends)
        $myFollowingIds = FollowingList::where('my_user_id', $user->id)
            ->pluck('user_id')
            ->toArray();

        // 3. BATCH: Get mutual friends for ALL candidates (single query)
        $mutualFriendsData = $this->batchGetMutualFriends($user->id, $candidateIds);

        // 4. BATCH: Get "followed by my friends" for ALL candidates (single query)
        $followedByFriendsData = $this->batchGetFollowedByFriends($candidateIds, $myFollowingIds);

        // 5. BATCH: Get recent posts count for ALL candidates (single query)
        $recentPostsCounts = Post::whereIn('user_id', $candidateIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->pluck('count', 'user_id')
            ->toArray();

        // 6. Pre-fetch context users info (all unique user IDs from mutual friends and followed by)
        $allContextUserIds = collect($mutualFriendsData)->flatten()
            ->merge(collect($followedByFriendsData)->flatten())
            ->unique()
            ->values()
            ->toArray();
        $contextUsersInfo = $this->batchGetUsersInfo($allContextUserIds);

        // 7. BATCH: Check friendship status for ALL candidates (for is_friend field)
        $friendsData = $this->batchCheckFriends($user->id, $candidateIds);

        // 8. BATCH: Check if candidates have liked current user (for is_liked_me field)
        $likedMeData = $this->batchCheckLikedMe($user->id, $candidateIds);

        // ============ CALCULATE SCORES (No more N+1 queries) ============

        $scoredCandidates = $candidates->map(function($candidate) use (
            $user, $likedUsers, $followersOfCurrentUser, $myFollowingIds, $filters,
            $mutualFriendsData, $followedByFriendsData, $recentPostsCounts, $contextUsersInfo,
            $friendsData, $likedMeData
        ) {
            $candidateId = $candidate->id;

            // Get pre-fetched data for this candidate
            $mutualFriendIds = $mutualFriendsData[$candidateId] ?? [];
            $followedByIds = $followedByFriendsData[$candidateId] ?? [];
            $recentPostsCount = $recentPostsCounts[$candidateId] ?? 0;

            // Calculate score using pre-fetched data (no queries)
            $score = $this->calculateScoreOptimized($user, $candidate, $filters, count($mutualFriendIds), $recentPostsCount);
            $candidate->suggestion_score = $score['total_score'];
            $candidate->suggestion_reason = $score['primary_reason'];

            // Add role/package information (already loaded via with())
            $candidate->current_role_type = $candidate->getCurrentRoleType();
            $candidate->is_vip_user = $candidate->isVip();
            $candidate->current_package_type = $candidate->getCurrentPackageType();
            $candidate->has_package = $candidate->hasPackage();
            $candidate->is_millionaire = $candidate->isMillionaire();
            $candidate->is_billionaire = $candidate->isBillionaire();
            $candidate->is_celebrity = $candidate->isCelebrity();
            $candidate->package_badge_color = $candidate->getPackageBadgeColor();

            // Add is_like and is_following_me (array lookups, no queries)
            $candidate->is_like = in_array($candidateId, $likedUsers);
            $candidate->is_following_me = in_array($candidateId, $followersOfCurrentUser);

            // Add is_friend and is_liked_me for UserDetailScreen optimization (skip API call)
            $candidate->is_friend = in_array($candidateId, $friendsData);
            $candidate->is_liked_me = in_array($candidateId, $likedMeData);

            // Build context using pre-fetched data (no queries)
            $contextData = $this->buildContextFromPrefetched(
                $mutualFriendIds, $followedByIds, $contextUsersInfo, $candidate->suggestion_reason
            );
            $candidate->suggestion_context_type = $contextData['type'];
            $candidate->suggestion_context_users = $contextData['users'];

            return $candidate;
        });

        // Sort by score (descending)
        return $scoredCandidates->sortByDesc('suggestion_score')->values();
    }

    /**
     * BATCH: Get mutual friends for all candidates in ONE query
     */
    private function batchGetMutualFriends($userId, $candidateIds)
    {
        if (empty($candidateIds)) return [];

        $results = DB::table('following_lists as fl1')
            ->join('following_lists as fl2', 'fl1.user_id', '=', 'fl2.user_id')
            ->where('fl1.my_user_id', $userId)
            ->whereIn('fl2.my_user_id', $candidateIds)
            ->select('fl2.my_user_id as candidate_id', 'fl1.user_id as mutual_friend_id')
            ->get();

        // Group by candidate_id, limit 3 per candidate
        $grouped = [];
        foreach ($results as $row) {
            $candidateId = $row->candidate_id;
            if (!isset($grouped[$candidateId])) {
                $grouped[$candidateId] = [];
            }
            if (count($grouped[$candidateId]) < 3) {
                $grouped[$candidateId][] = $row->mutual_friend_id;
            }
        }
        return $grouped;
    }

    /**
     * BATCH: Get "followed by my friends" for all candidates in ONE query
     */
    private function batchGetFollowedByFriends($candidateIds, $myFollowingIds)
    {
        if (empty($candidateIds) || empty($myFollowingIds)) return [];

        $results = FollowingList::whereIn('user_id', $candidateIds)
            ->whereIn('my_user_id', $myFollowingIds)
            ->select('user_id as candidate_id', 'my_user_id as friend_id')
            ->get();

        // Group by candidate_id, limit 3 per candidate
        $grouped = [];
        foreach ($results as $row) {
            $candidateId = $row->candidate_id;
            if (!isset($grouped[$candidateId])) {
                $grouped[$candidateId] = [];
            }
            if (count($grouped[$candidateId]) < 3) {
                $grouped[$candidateId][] = $row->friend_id;
            }
        }
        return $grouped;
    }

    /**
     * BATCH: Get user info for all context users in ONE query
     */
    private function batchGetUsersInfo($userIds)
    {
        if (empty($userIds)) return [];

        $users = Users::whereIn('id', $userIds)
            ->with('images')
            ->get();

        $result = [];
        foreach ($users as $user) {
            $image = '';
            if ($user->images && $user->images->count() > 0) {
                $image = $user->images->first()->image ?? '';
            }
            $result[$user->id] = [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'image' => $image
            ];
        }
        return $result;
    }

    /**
     * BATCH: Check friendship status for all candidates in ONE query
     * Returns array of candidate IDs who are friends with the user
     */
    private function batchCheckFriends($userId, $candidateIds)
    {
        if (empty($candidateIds)) return [];

        // Friends table stores: user_id (smaller) < friend_id (larger)
        $friends = Friend::where(function($query) use ($userId, $candidateIds) {
            $query->where('user_id', $userId)
                ->whereIn('friend_id', $candidateIds);
        })->orWhere(function($query) use ($userId, $candidateIds) {
            $query->where('friend_id', $userId)
                ->whereIn('user_id', $candidateIds);
        })->get();

        $friendIds = [];
        foreach ($friends as $friend) {
            // Get the candidate ID (not current user)
            $friendIds[] = $friend->user_id == $userId ? $friend->friend_id : $friend->user_id;
        }
        return $friendIds;
    }

    /**
     * BATCH: Check if candidates have liked current user in ONE query
     * Returns array of candidate IDs who have sent handshake/like to current user
     */
    private function batchCheckLikedMe($userId, $candidateIds)
    {
        if (empty($candidateIds)) return [];

        // like_profiles: my_user_id (liker) -> user_id (liked person)
        // We want to find candidates who liked current user
        return LikedProfile::where('user_id', $userId)
            ->whereIn('my_user_id', $candidateIds)
            ->pluck('my_user_id')
            ->toArray();
    }

    /**
     * Calculate score using pre-fetched data (no queries)
     */
    private function calculateScoreOptimized($user, $candidate, $filters, $mutualFriendsCount, $recentPostsCount)
    {
        $score = 0;
        $primaryReason = 'algorithm';

        // 1. Mutual friends bonus (pre-fetched)
        if ($mutualFriendsCount > 0) {
            $score += min($mutualFriendsCount * 15, 40);
            $primaryReason = UserSuggestionFeedback::REASON_MUTUAL_FRIENDS;
        }

        // 2. Location proximity bonus
        if (($filters['use_location'] ?? false) && $user->lattitude && $candidate->lattitude) {
            $distance = $this->calculateDistance(
                $user->lattitude, $user->longitude,
                $candidate->lattitude, $candidate->longitude
            );
            if ($distance <= 50) {
                $score += 30;
                $primaryReason = UserSuggestionFeedback::REASON_NEARBY;
            } elseif ($distance <= 100) {
                $score += 20;
            } elseif ($distance <= 500) {
                $score += 10;
            }
        }

        // 3. Activity bonus (pre-fetched)
        $score += min($recentPostsCount * 5, 15);

        // 4. VIP/Premium user bonus
        if ($candidate->isVip()) {
            $score += 10;
        }

        // 5. Verified user bonus
        if ($candidate->is_verified == 2) {
            $score += 10;
        }

        // 6. New user bonus
        $daysSinceJoined = now()->diffInDays($candidate->created_at);
        if ($daysSinceJoined <= 7) {
            $score += 15;
            $primaryReason = UserSuggestionFeedback::REASON_NEW_USER;
        } elseif ($daysSinceJoined <= 30) {
            $score += 5;
        }

        // 7. Common interests bonus
        if ($user->interests && $candidate->interests) {
            $userInterests = explode(',', $user->interests);
            $candidateInterests = explode(',', $candidate->interests);
            $commonCount = count(array_intersect($userInterests, $candidateInterests));
            if ($commonCount > 0) {
                $score += min($commonCount * 10, 20);
                if ($commonCount >= 3) {
                    $primaryReason = UserSuggestionFeedback::REASON_COMMON_INTERESTS;
                }
            }
        }

        // 8. Profile completeness bonus
        if ($candidate->bio && strlen($candidate->bio) > 20) {
            $score += 5;
        }
        if ($candidate->images && $candidate->images->count() >= 3) {
            $score += 5;
        }

        return [
            'total_score' => $score,
            'primary_reason' => $primaryReason
        ];
    }

    /**
     * Build context from pre-fetched data (no queries)
     */
    private function buildContextFromPrefetched($mutualFriendIds, $followedByIds, $contextUsersInfo, $suggestionReason)
    {
        // Priority 1: Mutual friends
        if (!empty($mutualFriendIds)) {
            $users = [];
            foreach (array_slice($mutualFriendIds, 0, 3) as $userId) {
                if (isset($contextUsersInfo[$userId])) {
                    $users[] = $contextUsersInfo[$userId];
                }
            }
            if (!empty($users)) {
                return ['type' => 'mutual_friends', 'users' => $users];
            }
        }

        // Priority 2: Followed by friends
        if (!empty($followedByIds)) {
            $users = [];
            foreach (array_slice($followedByIds, 0, 3) as $userId) {
                if (isset($contextUsersInfo[$userId])) {
                    $users[] = $contextUsersInfo[$userId];
                }
            }
            if (!empty($users)) {
                return ['type' => 'followed_by', 'users' => $users];
            }
        }

        // Priority 3: Use suggestion reason
        $contextType = 'default';
        if ($suggestionReason === 'nearby') {
            $contextType = 'nearby';
        } elseif ($suggestionReason === 'new_user') {
            $contextType = 'new_user';
        } elseif ($suggestionReason === 'common_interests') {
            $contextType = 'common_interests';
        }

        return ['type' => $contextType, 'users' => []];
    }

    /**
     * Calculate simple score for suggestion ranking
     */
    private function calculateSimpleScore($user, $candidate, $filters = [])
    {
        $score = 0;
        $primaryReason = 'algorithm';

        // 1. Mutual friends bonus (high priority)
        $mutualFriends = DB::table('following_lists as fl1')
            ->join('following_lists as fl2', 'fl1.user_id', '=', 'fl2.user_id')
            ->where('fl1.my_user_id', $user->id)
            ->where('fl2.my_user_id', $candidate->id)
            ->count();

        if ($mutualFriends > 0) {
            $score += min($mutualFriends * 15, 40);
            $primaryReason = UserSuggestionFeedback::REASON_MUTUAL_FRIENDS;
        }

        // 2. Location proximity bonus
        if (($filters['use_location'] ?? false) && $user->lattitude && $candidate->lattitude) {
            $distance = $this->calculateDistance(
                $user->lattitude, $user->longitude,
                $candidate->lattitude, $candidate->longitude
            );
            if ($distance <= 50) {
                $score += 30;
                $primaryReason = UserSuggestionFeedback::REASON_NEARBY;
            } elseif ($distance <= 100) {
                $score += 20;
            } elseif ($distance <= 500) {
                $score += 10;
            }
        }

        // 3. Activity bonus (recent activity)
        $recentPosts = Post::where('user_id', $candidate->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $score += min($recentPosts * 5, 15);

        // 4. VIP/Premium user bonus
        if ($candidate->isVip()) {
            $score += 10;
        }

        // 5. Verified user bonus
        if ($candidate->is_verified == 2) {
            $score += 10;
        }

        // 6. New user bonus
        $daysSinceJoined = now()->diffInDays($candidate->created_at);
        if ($daysSinceJoined <= 7) {
            $score += 15;
            $primaryReason = UserSuggestionFeedback::REASON_NEW_USER;
        } elseif ($daysSinceJoined <= 30) {
            $score += 5;
        }

        // 7. Common interests bonus
        if ($user->interests && $candidate->interests) {
            $userInterests = explode(',', $user->interests);
            $candidateInterests = explode(',', $candidate->interests);
            $commonCount = count(array_intersect($userInterests, $candidateInterests));
            if ($commonCount > 0) {
                $score += min($commonCount * 10, 20);
                if ($commonCount >= 3) {
                    $primaryReason = UserSuggestionFeedback::REASON_COMMON_INTERESTS;
                }
            }
        }

        // 8. Profile completeness bonus
        if ($candidate->bio && strlen($candidate->bio) > 20) {
            $score += 5;
        }
        if ($candidate->images && $candidate->images->count() >= 3) {
            $score += 5;
        }

        return [
            'total_score' => $score,
            'primary_reason' => $primaryReason
        ];
    }

    /**
     * Get total count of available suggestions for pagination
     */
    private function getTotalSuggestionsCount($user, $excludeUserIds)
    {
        return Users::where('is_block', 0)
            ->whereNotIn('id', $excludeUserIds)
            ->count();
    }

    /**
     * Get suggestion context (mutual friends, followed by, etc.)
     * Returns context type and relevant users for display
     */
    private function getSuggestionContext($userId, $candidateId, $myFollowingIds, $suggestionReason)
    {
        $contextType = 'default';
        $contextUsers = [];

        // Priority 1: Mutual friends (people both user and candidate follow)
        $mutualFriendIds = DB::table('following_lists as fl1')
            ->join('following_lists as fl2', 'fl1.user_id', '=', 'fl2.user_id')
            ->where('fl1.my_user_id', $userId)
            ->where('fl2.my_user_id', $candidateId)
            ->limit(3)
            ->pluck('fl1.user_id')
            ->toArray();

        if (!empty($mutualFriendIds)) {
            $contextType = 'mutual_friends';
            $contextUsers = $this->getUsersContextInfo($mutualFriendIds);
            return ['type' => $contextType, 'users' => $contextUsers];
        }

        // Priority 2: Followed by my friends (which of my friends follow this candidate)
        $friendsWhoFollowCandidate = FollowingList::where('user_id', $candidateId)
            ->whereIn('my_user_id', $myFollowingIds)
            ->limit(3)
            ->pluck('my_user_id')
            ->toArray();

        if (!empty($friendsWhoFollowCandidate)) {
            $contextType = 'followed_by';
            $contextUsers = $this->getUsersContextInfo($friendsWhoFollowCandidate);
            return ['type' => $contextType, 'users' => $contextUsers];
        }

        // Priority 3: Use suggestion reason as context type
        if ($suggestionReason === 'nearby') {
            $contextType = 'nearby';
        } elseif ($suggestionReason === 'new_user') {
            $contextType = 'new_user';
        } elseif ($suggestionReason === 'common_interests') {
            $contextType = 'common_interests';
        }

        return ['type' => $contextType, 'users' => $contextUsers];
    }

    /**
     * Get basic user info for context display (id, fullname, image)
     */
    private function getUsersContextInfo($userIds)
    {
        if (empty($userIds)) return [];

        $users = Users::whereIn('id', $userIds)
            ->with('images')
            ->get();

        return $users->map(function($user) {
            $image = '';
            if ($user->images && $user->images->count() > 0) {
                $image = $user->images->first()->image ?? '';
            }
            return [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'image' => $image
            ];
        })->values()->toArray();
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