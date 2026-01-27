<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EarningsAnalyticsController extends Controller
{
    /**
     * Calculate start date based on period
     */
    private function getStartDate($period)
    {
        $months = match($period) {
            'last_month' => 1,
            'last_3_months' => 3,
            'last_6_months' => 6,
            'last_year' => 12,
            default => null, // 'all' - no filter
        };

        return $months ? Carbon::now()->subMonths($months)->startOfMonth() : null;
    }

    /**
     * Get comprehensive earnings analytics for a streamer
     * POST /api/getEarningsAnalytics
     */
    public function getEarningsAnalytics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'period' => 'sometimes|string|in:all,last_month,last_3_months,last_6_months,last_year',
            'time_frame' => 'sometimes|string|in:daily,weekly,monthly'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;
        $period = $request->get('period', 'all');
        $startDate = $this->getStartDate($period);

        try {
            // Get overview stats filtered by period
            $overview = $this->getOverviewStats($userId, $startDate);

            // Get time-based earnings breakdown
            $timeBasedEarnings = $this->getTimeBasedEarnings($userId, $startDate);

            // Get top performing streams filtered by period
            $topStreams = $this->fetchTopPerformingStreams($userId, 5, $startDate);

            // Get earnings trends filtered by period
            $earningsTrends = $this->getEarningsTrends($userId, $startDate);

            return response()->json([
                'status' => true,
                'data' => [
                    'overview' => $overview,
                    'time_based_earnings' => $timeBasedEarnings,
                    'top_performing_streams' => $topStreams,
                    'earnings_trends' => $earningsTrends
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch earnings analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get demographics of gifters (gender, location analysis)
     * POST /api/getGifterDemographics
     */
    public function getGifterDemographics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'period' => 'sometimes|string|in:all,last_month,last_3_months,last_6_months,last_year'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;
        $period = $request->get('period', 'all');
        $startDate = $this->getStartDate($period);

        try {
            // Get gender demographics of gifters
            $genderStats = $this->getGifterGenderStats($userId, $startDate);

            // Get geographic demographics
            $locationStats = $this->getGifterLocationStats($userId, $startDate);

            // Get age demographics
            $ageStats = $this->getGifterAgeStats($userId, $startDate);

            // Get follower vs non-follower statistics
            $followerStats = $this->getFollowerGiftStats($userId, $startDate);

            return response()->json([
                'status' => true,
                'data' => [
                    'gender_breakdown' => $genderStats,
                    'location_breakdown' => $locationStats,
                    'age_breakdown' => $ageStats,
                    'follower_breakdown' => $followerStats,
                    'total_unique_gifters' => $this->getTotalUniqueGifters($userId, $startDate)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch gifter demographics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top performing streams
     * POST /api/getTopPerformingStreams
     */
    public function getTopPerformingStreams(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'limit' => 'sometimes|integer|min:1|max:20',
            'period' => 'sometimes|string|in:all,last_month,last_3_months,last_6_months,last_year'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;
        $limit = $request->get('limit', 10);
        $period = $request->get('period', 'all');
        $startDate = $this->getStartDate($period);

        try {
            $topStreams = $this->fetchTopPerformingStreams($userId, $limit, $startDate);

            return response()->json([
                'status' => true,
                'data' => $topStreams
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch top performing streams',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get follower vs non-follower gift analysis
     * POST /api/getFollowerGiftAnalysis
     */
    public function getFollowerGiftAnalysis(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'period' => 'sometimes|string|in:all,last_month,last_3_months,last_6_months,last_year'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;
        $period = $request->get('period', 'all');
        $startDate = $this->getStartDate($period);

        try {
            $followerStats = $this->getFollowerGiftStats($userId, $startDate);

            return response()->json([
                'status' => true,
                'data' => $followerStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch follower gift analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods

    private function getOverviewStats($userId, $startDate = null)
    {
        // Get current wallet (always current, not filtered)
        $user = DB::table('users')
            ->select('wallet', 'followers')
            ->where('id', $userId)
            ->first();

        // Calculate total_collected from gift_transactions within period
        $earningsQuery = DB::table('gift_transactions')
            ->where('receiver_user_id', $userId);

        if ($startDate) {
            $earningsQuery->where('gifted_at', '>=', $startDate);
        }

        $totalCollected = $earningsQuery->sum('coin_value') ?? 0;

        // Calculate total_streams from live_history within period
        $streamsQuery = DB::table('live_history')
            ->where('user_id', $userId);

        if ($startDate) {
            $streamsQuery->where('created_at', '>=', $startDate);
        }

        $totalStreams = $streamsQuery->count();

        // Calculate average per stream
        $avgPerStream = $totalStreams > 0 ? round($totalCollected / $totalStreams, 2) : 0;

        return [
            'total_collected' => (int) $totalCollected,
            'total_streams' => $totalStreams,
            'current_wallet' => $user->wallet ?? 0,
            'followers_count' => $user->followers ?? 0,
            'average_per_stream' => $avgPerStream
        ];
    }

    private function getTimeBasedEarnings($userId, $startDate = null)
    {
        // Get stream count from live_history
        $streamQuery = DB::table('live_history')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
                DB::raw("COUNT(*) as stream_count")
            )
            ->where('user_id', $userId);

        if ($startDate) {
            $streamQuery->where('created_at', '>=', $startDate);
        }

        $streamCounts = $streamQuery
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->pluck('stream_count', 'period')
            ->toArray();

        // Get earnings from gift_transactions
        $earningsQuery = DB::table('gift_transactions')
            ->select(
                DB::raw("DATE_FORMAT(gifted_at, '%Y-%m') as period"),
                DB::raw("SUM(coin_value) as total_earnings")
            )
            ->where('receiver_user_id', $userId);

        if ($startDate) {
            $earningsQuery->where('gifted_at', '>=', $startDate);
        }

        $earnings = $earningsQuery
            ->groupBy(DB::raw("DATE_FORMAT(gifted_at, '%Y-%m')"))
            ->pluck('total_earnings', 'period')
            ->toArray();

        // Merge results
        $allPeriods = array_unique(array_merge(array_keys($streamCounts), array_keys($earnings)));
        sort($allPeriods);

        $result = [];
        foreach ($allPeriods as $p) {
            $result[] = (object)[
                'period' => $p,
                'total_earnings' => $earnings[$p] ?? 0,
                'stream_count' => $streamCounts[$p] ?? 0
            ];
        }

        return $result;
    }

    private function fetchTopPerformingStreams($userId, $limit, $startDate = null)
    {
        $dateFilter = $startDate ? "AND lh.created_at >= ?" : "";
        $params = $startDate ? [$userId, $startDate, $limit] : [$userId, $limit];

        // Get streams with calculated earnings from gift_transactions
        $streams = DB::select("
            SELECT
                lh.id,
                lh.started_at,
                lh.streamed_for,
                lh.created_at,
                COALESCE(SUM(gt.coin_value), 0) as amount_collected
            FROM live_history lh
            LEFT JOIN gift_transactions gt ON gt.receiver_user_id = lh.user_id
                AND gt.gifted_at >= lh.created_at
                AND gt.gifted_at <= DATE_ADD(lh.created_at, INTERVAL 24 HOUR)
            WHERE lh.user_id = ? {$dateFilter}
            GROUP BY lh.id, lh.started_at, lh.streamed_for, lh.created_at
            ORDER BY amount_collected DESC
            LIMIT ?
        ", $params);

        return $streams;
    }

    private function getEarningsTrends($userId, $startDate = null)
    {
        $query = DB::table('gift_transactions')
            ->select(
                DB::raw("DATE_FORMAT(gifted_at, '%Y-%m') as month"),
                DB::raw("SUM(coin_value) as earnings"),
                DB::raw("AVG(coin_value) as avg_per_stream")
            )
            ->where('receiver_user_id', $userId);

        if ($startDate) {
            $query->where('gifted_at', '>=', $startDate);
        }

        return $query
            ->groupBy(DB::raw("DATE_FORMAT(gifted_at, '%Y-%m')"))
            ->orderBy('month', 'asc')
            ->get()
            ->toArray();
    }

    private function getGifterGenderStats($userId, $startDate = null)
    {
        $dateFilter = $startDate ? "AND gt.gifted_at >= ?" : "";
        $params = $startDate ? [$userId, $startDate] : [$userId];

        $stats = DB::select("
            SELECT
                u.gender,
                COUNT(*) as gift_count,
                SUM(gt.coin_value) as total_value,
                COUNT(DISTINCT gt.sender_user_id) as unique_gifters
            FROM gift_transactions gt
            JOIN users u ON gt.sender_user_id = u.id
            WHERE gt.receiver_user_id = ? {$dateFilter}
            GROUP BY u.gender
        ", $params);

        $total = array_sum(array_column($stats, 'gift_count'));

        return array_map(function($stat) use ($total) {
            return [
                'gender' => $stat->gender == 1 ? 'Male' : ($stat->gender == 2 ? 'Female' : 'Other'),
                'gift_count' => $stat->gift_count,
                'total_value' => $stat->total_value,
                'percentage' => $total > 0 ? round(($stat->gift_count / $total) * 100, 1) : 0,
                'unique_gifters' => $stat->unique_gifters
            ];
        }, $stats);
    }

    private function getGifterLocationStats($userId, $startDate = null)
    {
        $dateFilter = $startDate ? "AND gt.gifted_at >= ?" : "";
        $params = $startDate ? [$userId, $startDate] : [$userId];

        $stats = DB::select("
            SELECT
                COALESCE(NULLIF(u.country, ''), 'Other') as country,
                COUNT(*) as gift_count,
                SUM(gt.coin_value) as total_value
            FROM gift_transactions gt
            JOIN users u ON gt.sender_user_id = u.id
            WHERE gt.receiver_user_id = ? {$dateFilter}
            GROUP BY COALESCE(NULLIF(u.country, ''), 'Other')
            ORDER BY gift_count DESC
            LIMIT 10
        ", $params);

        $total = array_sum(array_column($stats, 'gift_count'));

        return array_map(function($stat) use ($total) {
            return [
                'country' => $stat->country,
                'gift_count' => $stat->gift_count,
                'total_value' => $stat->total_value,
                'percentage' => $total > 0 ? round(($stat->gift_count / $total) * 100, 1) : 0
            ];
        }, $stats);
    }

    private function getGifterAgeStats($userId, $startDate = null)
    {
        $dateFilter = $startDate ? "AND gt.gifted_at >= ?" : "";
        $params = $startDate ? [$userId, $startDate] : [$userId];

        $stats = DB::select("
            SELECT
                CASE
                    WHEN u.age BETWEEN 18 AND 25 THEN '18-25'
                    WHEN u.age BETWEEN 26 AND 35 THEN '26-35'
                    WHEN u.age BETWEEN 36 AND 45 THEN '36-45'
                    WHEN u.age > 45 THEN '45+'
                    ELSE 'Unknown'
                END as age_group,
                COUNT(*) as gift_count,
                SUM(gt.coin_value) as total_value
            FROM gift_transactions gt
            JOIN users u ON gt.sender_user_id = u.id
            WHERE gt.receiver_user_id = ? {$dateFilter}
            GROUP BY age_group
            ORDER BY gift_count DESC
        ", $params);

        $total = array_sum(array_column($stats, 'gift_count'));

        return array_map(function($stat) use ($total) {
            return [
                'age_group' => $stat->age_group,
                'gift_count' => $stat->gift_count,
                'total_value' => $stat->total_value,
                'percentage' => $total > 0 ? round(($stat->gift_count / $total) * 100, 1) : 0
            ];
        }, $stats);
    }

    private function getFollowerGiftStats($userId, $startDate = null)
    {
        $dateFilter = $startDate ? "AND gt.gifted_at >= ?" : "";
        $params = $startDate ? [$userId, $userId, $startDate] : [$userId, $userId];

        $followerStats = DB::select("
            SELECT
                CASE WHEN fl.id IS NOT NULL THEN 'Followers' ELSE 'Non-Followers' END as user_type,
                COUNT(*) as gift_count,
                SUM(gt.coin_value) as total_value
            FROM gift_transactions gt
            LEFT JOIN following_lists fl ON fl.my_user_id = gt.sender_user_id AND fl.user_id = ?
            WHERE gt.receiver_user_id = ? {$dateFilter}
            GROUP BY user_type
        ", $params);

        $total = array_sum(array_column($followerStats, 'gift_count'));

        return array_map(function($stat) use ($total) {
            return [
                'user_type' => $stat->user_type,
                'gift_count' => $stat->gift_count,
                'total_value' => $stat->total_value,
                'percentage' => $total > 0 ? round(($stat->gift_count / $total) * 100, 1) : 0
            ];
        }, $followerStats);
    }

    private function getTotalUniqueGifters($userId, $startDate = null)
    {
        $dateFilter = $startDate ? "AND gifted_at >= ?" : "";
        $params = $startDate ? [$userId, $startDate] : [$userId];

        $result = DB::select("
            SELECT COUNT(DISTINCT sender_user_id) as unique_count
            FROM gift_transactions
            WHERE receiver_user_id = ? {$dateFilter}
        ", $params);

        return $result[0]->unique_count ?? 0;
    }
}
