<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class ThrottleApiRequests extends ThrottleRequests
{
    /**
     * Create a 'too many attempts' response for API endpoints.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  callable|null  $responseCallback
     * @return \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    protected function buildException($request, $key, $maxAttempts, $responseCallback = null)
    {
        $retryAfter = $this->getTimeUntilNextRetry($key);

        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );

        // Create custom exception with JSON response
        return new ThrottleRequestsException(
            'Too many requests. Please slow down.',
            response()->json([
                'status' => false,
                'message' => 'Too many requests. Please slow down.',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $retryAfter,
            ], 429, $headers)
        );
    }
}