<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CheckHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check IP ban first (Redis O(1) lookup)
        $ip = $request->ip();
        try {
            if (Redis::sismember('banned_ips', $ip)) {
                return new JsonResponse([
                    'status' => false,
                    'message' => 'Access Denied',
                ], 403);
            }
        } catch (\Exception $e) {
            // Redis down — fallback silently, don't block legitimate users
        }

        if (isset($_SERVER['HTTP_APIKEY'])) {

            $apikey = $_SERVER['HTTP_APIKEY'];

            if ($apikey == 123) {
                // Check if user account is banned
                $userId = $request->input('user_id') ?? $request->input('my_user_id');
                if ($userId) {
                    $banned = DB::table('users')
                        ->where('id', $userId)
                        ->whereNotNull('banned_at')
                        ->exists();
                    if ($banned) {
                        return new JsonResponse([
                            'status' => false,
                            'message' => 'Account Suspended',
                        ], 403);
                    }
                }

                return $next($request);
            } else {

                $data['status']    = false;
                $data['message']  = "Enter Right Api key";
                return new JsonResponse($data, 401);
            }
        } else {
            $data['status']    = false;
            $data['message']  = "Unauthorized Access";
            return new JsonResponse($data, 401);
        }
    }
}
