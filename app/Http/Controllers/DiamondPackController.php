<?php

namespace App\Http\Controllers;

use App\Models\DiamondPacks;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiamondPackController extends Controller
{
    //

    /**
     * Create payment intent for Stripe diamond purchase (Android)
     */
    public function createDiamondPaymentIntent(Request $request)
    {
        Log::info('DIAMOND: createDiamondPaymentIntent called', [
            'request_data' => $request->all()
        ]);

        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'amount' => 'required|integer', // Diamond amount (10, 20, 50, etc.)
            ]);

            // Find diamond pack by amount
            $pack = DiamondPacks::findByAmount($request->amount);
            if (!$pack) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid diamond pack amount'
                ], 400);
            }

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Create PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $pack->price * 100, // Convert to cents
                'currency' => 'usd',
                'metadata' => [
                    'user_id' => $request->user_id,
                    'diamond_amount' => $pack->amount,
                    'type' => 'diamond_purchase'
                ],
            ]);

            Log::info('DIAMOND: PaymentIntent created', [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $pack->price
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'diamond_amount' => $pack->amount,
                    'price' => $pack->price,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('DIAMOND: Create payment intent error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm diamond payment and add coins to wallet
     */
    public function confirmDiamondPayment(Request $request)
    {
        Log::info('DIAMOND: confirmDiamondPayment called', [
            'request_data' => $request->all()
        ]);

        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'payment_intent_id' => 'required|string',
                'diamond_amount' => 'required|integer',
            ]);

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Verify payment intent status
            $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent_id);

            if ($paymentIntent->status !== 'succeeded') {
                Log::warning('DIAMOND: Payment not succeeded', [
                    'payment_intent_id' => $request->payment_intent_id,
                    'status' => $paymentIntent->status
                ]);
                return response()->json([
                    'status' => 400,
                    'message' => 'Payment not completed'
                ], 400);
            }

            // Verify metadata matches
            if ($paymentIntent->metadata->user_id != $request->user_id ||
                $paymentIntent->metadata->diamond_amount != $request->diamond_amount) {
                Log::warning('DIAMOND: Metadata mismatch', [
                    'expected_user' => $request->user_id,
                    'actual_user' => $paymentIntent->metadata->user_id
                ]);
                return response()->json([
                    'status' => 400,
                    'message' => 'Payment verification failed'
                ], 400);
            }

            // Add coins to user wallet
            $user = Users::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found'
                ], 404);
            }

            $user->wallet = ($user->wallet ?? 0) + $request->diamond_amount;
            $user->total_collected = ($user->total_collected ?? 0) + $request->diamond_amount;
            $user->save();

            Log::info('DIAMOND: Coins added to wallet', [
                'user_id' => $user->id,
                'diamond_amount' => $request->diamond_amount,
                'new_wallet' => $user->wallet
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Diamond purchase successful',
                'data' => [
                    'wallet' => $user->wallet,
                    'total_collected' => $user->total_collected,
                    'diamonds_added' => $request->diamond_amount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('DIAMOND: Confirm payment error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to confirm payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    function diamondpacks()
    {
        return view('diamondpacks');
    }

    function getDiamondPacks(Request $request){
        $data = DiamondPacks::all();

        return json_encode([
            'status' => true,
            'message' => 'diamond packs get successfully',
            'data' => $data
        ]);
    }

    public function addDiamondPack(Request $request)
    {
        // Validate image if uploaded
        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,jpg,png|max:2048', // Max 2MB
            ]);
        }

        $pack = new DiamondPacks();
        $pack->amount = $request->amount;
        $pack->android_product_id = $request->android_product_id;
        $pack->ios_product_id = $request->ios_product_id;

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/diamond_packs'), $imageName);
            $pack->image = 'images/diamond_packs/' . $imageName;
        }

        $pack->save();

        return response()->json([
            'status' => true,
            'message' => 'Diamond Pack Added Successfully',
        ]);
    }

    function getDiamondPackById($id)
    {
        $data = DiamondPacks::where('id', $id)->first();
        echo response()->json($data);
    }

    function updateDiamondPack(Request $request)
    {
        // Validate image if uploaded
        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,jpg,png|max:2048', // Max 2MB
            ]);
        }

        $pack = DiamondPacks::where('id', $request->id)->first();
        $pack->amount = $request->amount;
        $pack->android_product_id = $request->android_product_id;
        $pack->ios_product_id = $request->ios_product_id;

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($pack->image && file_exists(public_path($pack->image))) {
                unlink(public_path($pack->image));
            }

            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/diamond_packs'), $imageName);
            $pack->image = 'images/diamond_packs/' . $imageName;
        }

        $pack->save();

        return response()->json([
            'status' => true,
            'message' => 'Diamond Pack Updated Successfully',
        ]);
    }

    function deleteDiamondPack(Request $request)
    {
        $diamondPack = DiamondPacks::where('id', $request->diamond_pack_id)->first();

        // Delete image file if exists
        if ($diamondPack->image && file_exists(public_path($diamondPack->image))) {
            unlink(public_path($diamondPack->image));
        }

        $diamondPack->delete();

        return response()->json([
            'status' => true,
            'message' => 'Diamond Pack Deleted',
        ]);
    }

    function fetchDiamondPackages(Request $request)
    {
        $totalData =  DiamondPacks::count();
        $rows = DiamondPacks::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'amount'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $totalData = DiamondPacks::count();
        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DiamondPacks::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DiamondPacks::Where('amount', 'LIKE', "%{$search}%")
                                    ->orWhere('android_product_id', 'LIKE', "%{$search}%")
                                    ->orWhere('ios_product_id', 'LIKE', "%{$search}%")
                                    ->offset($start)
                                    ->limit($limit)
                                    ->orderBy($order, $dir)
                                    ->get();
            $totalFiltered = DiamondPacks::where('amount', 'LIKE', "%{$search}%")
                                    ->orWhere('android_product_id', 'LIKE', "%{$search}%")
                                    ->orWhere('ios_product_id', 'LIKE', "%{$search}%")
                                    ->count();
        }
        $data = array();
        foreach ($result as $item) {

            // Image preview
            $imageHtml = '';
            if ($item->image) {
                $imageUrl = asset($item->image);
                $imageHtml = '<img src="'.$imageUrl.'" alt="Diamond Pack" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">';
            } else {
                $imageHtml = '<span class="text-muted">No image</span>';
            }

            $block = '<span class="float-end">
                            <a href="" rel="' . $item->id . '"
                                class="btn btn-success edit mr-2"
                                data-amount="'. $item->amount .'"
                                data-android_product_id="'. $item->android_product_id .'"
                                data-ios_product_id="'. $item->ios_product_id .'"
                                data-image="'. ($item->image ?? '') .'">
                                Edit
                            </a>
                            <a rel="'.$item->id.'" class="btn btn-danger delete text-white">
                                Delete
                            </a>
                        </span>';

            $data[] = array(
                $item->amount,
                $item->android_product_id,
                $item->ios_product_id,
                $imageHtml,
                $block
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
}
