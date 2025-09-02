<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EarningsAnalyticsController extends Controller
{
    /**
     * Get comprehensive earnings analytics for a streamer
     * POST /api/getEarningsAnalytics
     */
    public function getEarningsAnalytics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'period' => 'sometimes|string|in:weekly,monthly,yearly',
            'months' => 'sometimes|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;
        $period = $request->get('period', 'monthly');
        $months = $request->get('months', 6);

        try {
            // Get overall user stats
            $userStats = DB::table('users')
                ->select('total_collected', 'total_streams', 'wallet', 'followers')
                ->where('id', $userId)
                ->first();

            // Get time-based earnings breakdown
            $timeBasedEarnings = $this->getTimeBasedEarnings($userId, $period, $months);
            
            // Get top performing streams
            $topStreams = $this->fetchTopPerformingStreams($userId, 5);
            
            // Get earnings trends
            $earningsTrends = $this->getEarningsTrends($userId, $months);

            return response()->json([
                'status' => true,
                'data' => [
                    'overview' => [
                        'total_collected' => $userStats->total_collected ?? 0,
                        'total_streams' => $userStats->total_streams ?? 0,
                        'current_wallet' => $userStats->wallet ?? 0,
                        'followers_count' => $userStats->followers ?? 0,
                        'average_per_stream' => $userStats->total_streams > 0 ? 
                            round($userStats->total_collected / $userStats->total_streams, 2) : 0
                    ],
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
            'user_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;

        try {
            // Get gender demographics of gifters
            $genderStats = $this->getGifterGenderStats($userId);
            
            // Get geographic demographics
            $locationStats = $this->getGifterLocationStats($userId);
            
            // Get age demographics
            $ageStats = $this->getGifterAgeStats($userId);
            
            // Get follower vs non-follower statistics
            $followerStats = $this->getFollowerGiftStats($userId);

            return response()->json([
                'status' => true,
                'data' => [
                    'gender_breakdown' => $genderStats,
                    'location_breakdown' => $locationStats,
                    'age_breakdown' => $ageStats,
                    'follower_breakdown' => $followerStats,
                    'total_unique_gifters' => $this->getTotalUniqueGifters($userId)
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
            'limit' => 'sometimes|integer|min:1|max:20'
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

        try {
            $topStreams = $this->fetchTopPerformingStreams($userId, $limit);

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
            'user_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;

        try {
            $followerStats = $this->getFollowerGiftStats($userId);

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

    private function getTimeBasedEarnings($userId, $period, $months)
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();
        
        $query = DB::table('live_history')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
                DB::raw("SUM(amount_collected) as total_earnings"),
                DB::raw("COUNT(*) as stream_count")
            )
            ->where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->groupBy('period')
            ->orderBy('period', 'asc');

        return $query->get()->toArray();
    }

    private function fetchTopPerformingStreams($userId, $limit)
    {
        return DB::table('live_history')
            ->select('id', 'started_at', 'streamed_for', 'amount_collected', 'created_at')
            ->where('user_id', $userId)
            ->orderBy('amount_collected', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function getEarningsTrends($userId, $months)
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();
        
        return DB::table('live_history')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw("SUM(amount_collected) as earnings"),
                DB::raw("AVG(amount_collected) as avg_per_stream")
            )
            ->where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->toArray();
    }

    private function getGifterGenderStats($userId)
    {
        // Complex query to get gender stats from gift senders
        $stats = DB::select("
            SELECT 
                u.gender,
                COUNT(*) as gift_count,
                SUM(g.coin_price * ugi.quantity) as total_value,
                COUNT(DISTINCT JSON_EXTRACT(ugi.received_from_user_id, '$[0]')) as unique_gifters
            FROM user_gift_inventory ugi
            JOIN gifts g ON ugi.gift_id = g.id
            JOIN users u ON JSON_CONTAINS(ugi.received_from_user_id, CAST(u.id AS JSON))
            WHERE ugi.user_id = ?
            GROUP BY u.gender
        ", [$userId]);

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

    private function getGifterLocationStats($userId)
    {
        // Geographic analysis using latitude/longitude ranges
        $stats = DB::select("
            SELECT 
                CASE 
                    WHEN u.lattitude BETWEEN 46 AND 51 AND u.longitude BETWEEN -5 AND 9 THEN 'France'
                    WHEN u.lattitude BETWEEN 47 AND 55 AND u.longitude BETWEEN 5 AND 15 THEN 'Germany'
                    WHEN u.lattitude BETWEEN 8 AND 12 AND u.longitude BETWEEN 102 AND 110 THEN 'Vietnam'
                    WHEN u.lattitude BETWEEN 36 AND 42 AND u.longitude BETWEEN -125 AND -117 THEN 'California'
                    ELSE 'Other'
                END as country,
                COUNT(*) as gift_count,
                SUM(g.coin_price * ugi.quantity) as total_value
            FROM user_gift_inventory ugi
            JOIN gifts g ON ugi.gift_id = g.id
            JOIN users u ON JSON_CONTAINS(ugi.received_from_user_id, CAST(u.id AS JSON))
            WHERE ugi.user_id = ? AND u.lattitude IS NOT NULL AND u.longitude IS NOT NULL
            GROUP BY country
            ORDER BY gift_count DESC
        ", [$userId]);

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

    private function getGifterAgeStats($userId)
    {
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
                SUM(g.coin_price * ugi.quantity) as total_value
            FROM user_gift_inventory ugi
            JOIN gifts g ON ugi.gift_id = g.id
            JOIN users u ON JSON_CONTAINS(ugi.received_from_user_id, CAST(u.id AS JSON))
            WHERE ugi.user_id = ? AND u.age IS NOT NULL
            GROUP BY age_group
            ORDER BY gift_count DESC
        ", [$userId]);

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

    private function getFollowerGiftStats($userId)
    {
        // Check if gifters are followers
        $followerStats = DB::select("
            SELECT 
                CASE WHEN fl.id IS NOT NULL THEN 'Followers' ELSE 'Non-Followers' END as user_type,
                COUNT(*) as gift_count,
                SUM(g.coin_price * ugi.quantity) as total_value
            FROM user_gift_inventory ugi
            JOIN gifts g ON ugi.gift_id = g.id
            JOIN users sender ON JSON_CONTAINS(ugi.received_from_user_id, CAST(sender.id AS JSON))
            LEFT JOIN following_lists fl ON fl.my_user_id = sender.id AND fl.user_id = ?
            WHERE ugi.user_id = ?
            GROUP BY user_type
        ", [$userId, $userId]);

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

    private function getTotalUniqueGifters($userId)
    {
        $result = DB::select("
            SELECT COUNT(DISTINCT sender.id) as unique_count
            FROM user_gift_inventory ugi
            JOIN users sender ON JSON_CONTAINS(ugi.received_from_user_id, CAST(sender.id AS JSON))
            WHERE ugi.user_id = ?
        ", [$userId]);

        return $result[0]->unique_count ?? 0;
    }
}