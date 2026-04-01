<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class CheckGuestApiKey
{
    /**
     * Handle an incoming request.
     *
     * Validates guest API requests using API key from environment variable (GUEST_KEY).
     * The key can be sent via header (X-Guest-Api-Key) or query parameter (guest_api_key).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check IP ban first
        try {
            if (Redis::sismember('banned_ips', $request->ip())) {
                return response()->json(['status' => false, 'message' => 'Access Denied'], 403);
            }
        } catch (\Exception $e) {}

        // Get API key from environment variable
        $validApiKey = env('GUEST_KEY');

        // Check if GUEST_KEY is configured
        if (!$validApiKey) {
            return response()->json([
                'status' => false,
                'message' => 'Guest API key not configured'
            ], 500);
        }

        // Check API key from header (preferred) or query parameter (fallback)
        $providedKey = $request->header('X-Guest-Api-Key') ?? $request->input('guest_api_key');

        // Validate the provided key
        if (!$providedKey || !hash_equals($validApiKey, $providedKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing API key'
            ], 401);
        }

        return $next($request);
    }
}
