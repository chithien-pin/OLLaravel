<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPacks;
use Illuminate\Http\Request;

class SubscriptionPackController extends Controller
{
    /**
     * Display subscription packs admin page
     */
    function subscriptionpacks()
    {
        return view('subscriptionpacks');
    }

    /**
     * Get all subscription packs for admin
     */
    function getSubscriptionPacks(Request $request)
    {
        $data = SubscriptionPacks::orderBy('amount')->get();

        return json_encode([
            'status' => true,
            'message' => 'Subscription packs retrieved successfully',
            'data' => $data
        ]);
    }

    /**
     * Add new subscription pack
     */
    public function addSubscriptionPack(Request $request)
    {
        $request->validate([
            'plan_type' => 'required|in:starter,monthly,yearly,millionaire,billionaire|unique:subscription_packs,plan_type',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'ios_product_id' => 'required|string',
            'interval_type' => 'required|in:month,year',
            'type' => 'required|in:role,package',
        ]);

        $pack = new SubscriptionPacks();
        $pack->plan_type = $request->plan_type;
        $pack->amount = $request->amount;
        $pack->currency = $request->currency;
        $pack->ios_product_id = $request->ios_product_id;
        $pack->android_product_id = $request->android_product_id; // Optional
        $pack->interval_type = $request->interval_type;
        $pack->type = $request->type;
        $pack->first_time_only = $request->plan_type === 'starter' ? true : false;
        $pack->save();

        return response()->json([
            'status' => true,
            'message' => 'Subscription Pack Added Successfully',
            'data' => $pack
        ]);
    }

    /**
     * Get subscription pack by ID
     */
    function getSubscriptionPackById($id)
    {
        $data = SubscriptionPacks::where('id', $id)->first();
        return response()->json($data);
    }

    /**
     * Update subscription pack
     */
    function updateSubscriptionPack(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:subscription_packs,id',
            'plan_type' => 'required|in:starter,monthly,yearly,millionaire,billionaire',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'ios_product_id' => 'required|string',
            'interval_type' => 'required|in:month,year',
            'type' => 'required|in:role,package',
        ]);

        $pack = SubscriptionPacks::where('id', $request->id)->first();
        $pack->plan_type = $request->plan_type;
        $pack->amount = $request->amount;
        $pack->currency = $request->currency;
        $pack->ios_product_id = $request->ios_product_id;
        $pack->android_product_id = $request->android_product_id; // Optional
        $pack->interval_type = $request->interval_type;
        $pack->type = $request->type;
        $pack->first_time_only = $request->plan_type === 'starter' ? true : false;
        $pack->save();

        return response()->json([
            'status' => true,
            'message' => 'Subscription Pack Updated Successfully',
            'data' => $pack
        ]);
    }

    /**
     * Delete subscription pack
     */
    function deleteSubscriptionPack(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:subscription_packs,id'
        ]);

        $pack = SubscriptionPacks::where('id', $request->id)->first();
        $pack->delete();

        return response()->json([
            'status' => true,
            'message' => 'Subscription Pack Deleted Successfully'
        ]);
    }

    /**
     * Fetch subscription packages for API (with pagination)
     */
    public function fetchSubscriptionPackages(Request $request)
    {
        $start = $request->input('start', 0);
        $limit = $request->input('limit', 10);

        $packs = SubscriptionPacks::orderBy('amount')
                                  ->skip($start)
                                  ->take($limit)
                                  ->get();

        $total = SubscriptionPacks::count();

        return response()->json([
            'status' => true,
            'message' => 'Subscription packages fetched successfully',
            'data' => $packs,
            'total' => $total
        ]);
    }
}