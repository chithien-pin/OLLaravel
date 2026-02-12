<?php

namespace App\Http\Controllers;

use App\Models\AppData;
use App\Models\RedeemRequest;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class UserPortalController extends Controller
{
    /**
     * Show login page
     */
    public function showLogin()
    {
        return view('portal.login');
    }

    /**
     * Send magic link to user email
     */
    public function sendMagicLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        // Note: users table uses 'identity' column for email
        $user = Users::where('identity', $request->email)->first();

        if (!$user) {
            return back()->with('error', 'No account found with this email address.');
        }

        // Generate magic token
        $token = Str::random(64);

        // Store token in cache for 30 minutes
        Cache::put('portal_magic_' . $token, $user->id, now()->addMinutes(30));

        // Build magic link
        $magicLink = url('/portal/verify/' . $token);

        // Send email
        try {
            Mail::send('portal.emails.magic-link', ['link' => $magicLink, 'user' => $user], function ($message) use ($user) {
                $message->to($user->identity)
                    ->subject('Your GYPSYLIVE Login Link');
            });

            return back()->with('success', 'A login link has been sent to your email. Please check your inbox.');
        } catch (\Exception $e) {
            Log::error('Failed to send magic link email: ' . $e->getMessage());
            return back()->with('error', 'Failed to send email. Please try again later.');
        }
    }

    /**
     * Verify magic link and login user
     */
    public function verifyMagicLink($token)
    {
        $userId = Cache::get('portal_magic_' . $token);

        if (!$userId) {
            return redirect()->route('portal.login')->with('error', 'Invalid or expired login link.');
        }

        $user = Users::find($userId);

        if (!$user) {
            return redirect()->route('portal.login')->with('error', 'User not found.');
        }

        // Clear the token
        Cache::forget('portal_magic_' . $token);

        // Store user in session
        session(['portal_user_id' => $user->id]);

        return redirect()->route('portal.dashboard');
    }

    /**
     * Show dashboard
     */
    public function dashboard()
    {
        $user = $this->getAuthUser();
        if (!$user) {
            return redirect()->route('portal.login');
        }

        $appData = AppData::first();

        // Get recent redeem requests
        $recentRedeems = RedeemRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('portal.dashboard', [
            'user' => $user,
            'appData' => $appData,
            'recentRedeems' => $recentRedeems
        ]);
    }

    /**
     * Show redeem form
     */
    public function showRedeemForm()
    {
        $user = $this->getAuthUser();
        if (!$user) {
            return redirect()->route('portal.login');
        }

        $appData = AppData::first();

        return view('portal.redeem', [
            'user' => $user,
            'appData' => $appData
        ]);
    }

    /**
     * Submit redeem request
     */
    public function submitRedeem(Request $request)
    {
        $user = $this->getAuthUser();
        if (!$user) {
            return redirect()->route('portal.login');
        }

        $request->validate([
            'coin_amount' => 'required|integer|min:1',
            'account_holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
        ]);

        $appData = AppData::first();
        $requestedAmount = (int) $request->coin_amount;

        // Validate minimum threshold
        if ($requestedAmount < $appData->min_threshold) {
            return back()->with('error', 'Requested amount is below minimum threshold of ' . $appData->min_threshold . ' coins.');
        }

        // Validate user has enough coins
        if ($user->wallet < $requestedAmount) {
            return back()->with('error', 'Insufficient coins in wallet!');
        }

        // Create redeem request
        $redeemRequest = new RedeemRequest();
        $redeemRequest->user_id = $user->id;
        $redeemRequest->request_id = $this->generateRequestId();
        $redeemRequest->coin_amount = $requestedAmount;
        $redeemRequest->payment_gateway = 'Bank Transfer';
        $redeemRequest->account_holder_name = $request->account_holder_name;
        $redeemRequest->bank_name = $request->bank_name;
        $redeemRequest->account_number = $request->account_number;
        $redeemRequest->account_details = "Account Holder: {$request->account_holder_name}\nBank Name: {$request->bank_name}\nAccount Number: {$request->account_number}";

        // Deduct from user wallet
        $user->wallet = $user->wallet - $requestedAmount;
        $user->save();

        $result = $redeemRequest->save();

        if ($result) {
            return redirect()->route('portal.history')->with('success', 'Redeem request submitted successfully! Request ID: ' . $redeemRequest->request_id);
        } else {
            // Refund if save failed
            $user->wallet = $user->wallet + $requestedAmount;
            $user->save();
            return back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    /**
     * Show redeem history
     */
    public function redeemHistory()
    {
        $user = $this->getAuthUser();
        if (!$user) {
            return redirect()->route('portal.login');
        }

        $appData = AppData::first();

        $redeems = RedeemRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('portal.history', [
            'user' => $user,
            'appData' => $appData,
            'redeems' => $redeems
        ]);
    }

    /**
     * Logout
     */
    public function logout()
    {
        session()->forget('portal_user_id');
        return redirect()->route('portal.login')->with('success', 'You have been logged out.');
    }

    /**
     * Get authenticated user from session
     */
    private function getAuthUser()
    {
        $userId = session('portal_user_id');
        if (!$userId) {
            return null;
        }
        return Users::find($userId);
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId()
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        do {
            $first = '';
            for ($i = 0; $i < 3; $i++) {
                $first .= $characters[rand(0, strlen($characters) - 1)];
            }
            $first .= rand(100000, 999999);
            for ($i = 0; $i < 3; $i++) {
                $first .= $characters[rand(0, strlen($characters) - 1)];
            }

            $count = RedeemRequest::where('request_id', $first)->count();
        } while ($count >= 1);

        return $first;
    }
}
