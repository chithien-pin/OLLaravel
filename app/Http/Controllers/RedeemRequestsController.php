<?php

namespace App\Http\Controllers;

use App\Models\AppData;
use App\Models\RedeemRequest;
use App\Models\Users;
use App\Models\UserNotification;
use App\Models\Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Google\Client;

class RedeemRequestsController extends Controller
{
    //

    function redeemrequests()
    {
        return view('redeemrequests');
    }

    function getRedeemById($id)
    {
        $data = RedeemRequest::with('user')->where('id', $id)->first();
        if (count($data->user->images) > 0) {
            $data->user->image = $data->user->images[0]->image;
        } else {
            $data->user->image = null;
        }
        
        // Add structured bank transfer information
        $data->has_structured_data = !empty($data->account_holder_name) && 
                                    !empty($data->bank_name) && 
                                    !empty($data->account_number);
        
        // Add coin rate for auto-calculating amount paid
        $app_data = AppData::first();
        $data->coin_rate = $app_data ? $app_data->coin_rate : 0.006; // Default fallback
        $data->expected_amount = $data->coin_amount * $data->coin_rate;
        
        echo json_encode($data);
    }

    function completeRedeem(Request $request)
    {
        $redeem = RedeemRequest::where('id', $request->id)->first();
        
        if (!$redeem) {
            return response()->json(['status' => false, 'message' => 'Redeem request not found']);
        }
        
        $redeem->status = 1;
        $redeem->amount_paid = $request->amount_paid;
        $result = $redeem->save();

        if ($result) {
            // Send push notification to user
            $this->sendRedeemCompletionNotification($redeem);
            
            // Create in-app notification
            $this->createInAppNotification($redeem);
            
            return response()->json(['status' => true, 'message' => 'Redeem Request completed successfully']);
        } else {
            return response()->json(['status' => false, 'message' => 'something went wrong']);
        }
    }
    
    /**
     * Send push notification when redeem request is completed
     */
    private function sendRedeemCompletionNotification($redeem)
    {
        try {
            $user = Users::find($redeem->user_id);
            if (!$user || !$user->device_token) {
                \Illuminate\Support\Facades\Log::info("No FCM token found for user: {$redeem->user_id}");
                return;
            }

            $app_data = AppData::first();
            $currency = $app_data ? $app_data->currency : '$';
            
            $title = "Redeem Request Completed! 🎉";
            $message = "Great news! Your redeem request ({$redeem->request_id}) has been completed. {$currency}{$redeem->amount_paid} has been transferred to your bank account.";
            
            // Prepare notification payload
            $notificationData = [
                'message' => [
                    'token' => $user->device_token,
                    'notification' => [
                        'title' => $title,
                        'body' => $message
                    ],
                    'data' => [
                        'type' => 'redeem_completed',
                        'redeem_id' => (string)$redeem->id,
                        'request_id' => $redeem->request_id,
                        'amount_paid' => (string)$redeem->amount_paid
                    ]
                ]
            ];
            
            // Send the notification using the existing service
            $this->sendPushNotificationDirectly($user->device_token, $title, $message, [
                'type' => 'redeem_completed',
                'redeem_id' => (string)$redeem->id,
                'request_id' => $redeem->request_id,
                'amount_paid' => (string)$redeem->amount_paid
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send redeem completion notification: " . $e->getMessage());
        }
    }
    
    /**
     * Send push notification directly using Firebase FCM
     */
    private function sendPushNotificationDirectly($deviceToken, $title, $message, $data = [])
    {
        try {
            $client = new Client();
            $client->setAuthConfig(base_path('firebase-credentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->fetchAccessTokenWithAssertion();
            $accessToken = $client->getAccessToken();
            $accessToken = $accessToken['access_token'];

            $contents = \Illuminate\Support\Facades\File::get(base_path('firebase-credentials.json'));
            $json = json_decode($contents, true);

            $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
            
            $fields = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $message
                    ],
                    'data' => $data
                ]
            ];

            $headers = [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            
            $result = curl_exec($ch);
            \Illuminate\Support\Facades\Log::info("FCM Response: " . $result);
            
            if ($result === FALSE) {
                \Illuminate\Support\Facades\Log::error('FCM Send Error: ' . curl_error($ch));
            }
            curl_close($ch);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Direct FCM notification error: " . $e->getMessage());
        }
    }
    
    /**
     * Create in-app notification for redeem completion
     */
    private function createInAppNotification($redeem)
    {
        try {
            $app_data = AppData::first();
            $currency = $app_data ? $app_data->currency : '$';
            
            $userNotification = new UserNotification();
            $userNotification->user_id = $redeem->user_id; // Receiver (the user who requested redeem)
            $userNotification->my_user_id = $redeem->user_id; // System notification from user to user
            $userNotification->item_id = $redeem->id; // Redeem request ID
            $userNotification->type = Constants::notificationTypeRedeemCompleted;
            $userNotification->title = 'Redeem Request Completed! 🎉';
            $userNotification->message = "Great news! Your redeem request ({$redeem->request_id}) has been completed. {$currency}{$redeem->amount_paid} has been transferred to your bank account.";
            $userNotification->save();
            
            \Illuminate\Support\Facades\Log::info("In-app notification created for user: {$redeem->user_id}");
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to create in-app notification: " . $e->getMessage());
        }
    }

    /**
     * Send push notification when redeem request is deleted/cancelled
     */
    private function sendRedeemDeletionNotification($redeem, $user = null)
    {
        try {
            if (!$user) {
                $user = Users::find($redeem->user_id);
            }
            
            if (!$user || !$user->device_token) {
                \Illuminate\Support\Facades\Log::info("No device token found for user: {$redeem->user_id}");
                return;
            }
            
            $app_data = AppData::first();
            $currency = $app_data ? $app_data->currency : '$';
            $refundAmount = $redeem->coin_amount;
            
            $deviceToken = $user->device_token;
            $title = 'Redeem Request Cancelled';
            $message = "Your redeem request has been cancelled. {$currency}{$refundAmount} coins have been refunded to your wallet.";
            
            $data = [
                'type' => 'redeem_cancelled',
                'redeem_id' => (string)$redeem->id,
                'request_id' => $redeem->request_id,
                'refund_amount' => (string)$refundAmount,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ];

            // Use same Firebase method as completion notification
            $client = new Client();
            $client->setAuthConfig(base_path('firebase-credentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->fetchAccessTokenWithAssertion();
            $accessToken = $client->getAccessToken();
            $accessToken = $accessToken['access_token'];

            $contents = \Illuminate\Support\Facades\File::get(base_path('firebase-credentials.json'));
            $json = json_decode($contents, true);

            $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
            
            $fields = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $message
                    ],
                    'data' => $data
                ]
            ];

            $headers = [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            
            $result = curl_exec($ch);
            \Illuminate\Support\Facades\Log::info("FCM Deletion Response: " . $result);
            
            if ($result === FALSE) {
                \Illuminate\Support\Facades\Log::error('FCM Send Error: ' . curl_error($ch));
            }
            curl_close($ch);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Direct FCM deletion notification error: " . $e->getMessage());
        }
    }
    
    /**
     * Create in-app notification for redeem deletion/cancellation
     */
    private function createRedeemDeletionInAppNotification($redeem, $user = null)
    {
        try {
            if (!$user) {
                $user = Users::find($redeem->user_id);
            }
            
            $userNotification = new UserNotification();
            $userNotification->user_id = $redeem->user_id; // Receiver (the user who requested redeem)
            $userNotification->my_user_id = $redeem->user_id; // System notification from user to user
            $userNotification->item_id = $redeem->id; // Redeem request ID
            $userNotification->type = Constants::notificationTypeRedeemCancelled;
            $userNotification->save();
            
            \Illuminate\Support\Facades\Log::info("In-app deletion notification created for user: {$redeem->user_id}");
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to create in-app deletion notification: " . $e->getMessage());
        }
    }


    function fetchCompletedRedeems(Request $request)
    {

        $totalData =  RedeemRequest::where('status', '=', 1)->count();
        $rows = RedeemRequest::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $totalData = RedeemRequest::where('status', '=', 1)->count();

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = RedeemRequest::where('status', '=', 1)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  RedeemRequest::where('status', 1)
                ->Where('request_id', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = RedeemRequest::where('status', 1)
                ->Where('request_id', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();

        $app_data = AppData::first();

        foreach ($result as $item) {

            if (count($item->user->images) > 0) {
                $image = '<img src="public/storage/' . $item->user->images[0]->image . '" width="50" height="50">';
            } else {
                $image = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            }

            $block = '<div class="action-buttons"><a href=""  rel="' . $item->id . '"   class="btn btn-primary btn-compact view-request">View</a><a href=""  rel="' . $item->id . '"   class="btn btn-info btn-compact info-payment">Info</a><a href = ""  rel = "' . $item->id . '" class = "btn btn-danger btn-compact delete text-white" >Delete</a></div>';

            // Simple payment gateway display - details available via Info button
            $payment_display = 'Bank Transfer';

            $data[] = array(
                $image,
                $item->user->fullname,
                $item->request_id,
                $item->coin_amount,
                $app_data->currency . ' ' . $item->amount_paid,
                $payment_display,
                $block,

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

    function deleteRedeemRequest(Request $request)
    {
        $redeem = RedeemRequest::where('id', $request->redeem_id)->first();
        if ($redeem) {
            // Get user to refund coins
            $user = Users::find($redeem->user_id);
            if ($user) {
                // Refund coins to user wallet
                $refundAmount = $redeem->coin_amount;
                $user->wallet = $user->wallet + $refundAmount;
                $user->save();
                
                \Illuminate\Support\Facades\Log::info("Refunded {$refundAmount} coins to user {$user->id} due to redeem deletion");
            }

            // Send notifications before deleting
            $this->sendRedeemDeletionNotification($redeem, $user);
            $this->createRedeemDeletionInAppNotification($redeem, $user);

            $redeem->delete();
            return response()->json([
                'status' => true,
                'message' => 'Redeem Request Deleted Successfully and Coins Refunded',
                'data' => $redeem,
                'refunded_amount' => $refundAmount ?? 0,
            ]);
        } 
        return response()->json([
            'status' => false,
            'message' => 'Redeem Request Not Found',
        ]);    
    }

    function fetchPendingRedeems(Request $request)
    {

        $totalData =  RedeemRequest::where('status', '=', 0)->count();
        $rows = RedeemRequest::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $totalData = RedeemRequest::where('status', '=', 0)->count();

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = RedeemRequest::where('status', '=', 0)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  RedeemRequest::where('status', 0)
                ->Where('request_id', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = RedeemRequest::where('status', 0)
                ->Where('request_id', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();

        $app_data = AppData::first();

        foreach ($result as $item) {

            if (count($item->user->images) > 0) {
                $image = '<img src="public/storage/' . $item->user->images[0]->image . '" width="50" height="50">';
            } else {
                $image = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            }

            $block = '<div class="action-buttons"><a href=""  rel="' . $item->id . '"   class="btn btn-success btn-compact complete-redeem">Complete</a><a href=""  rel="' . $item->id . '"   class="btn btn-info btn-compact info-payment">Info</a><a href = ""  rel = "' . $item->id . '" class = "btn btn-danger btn-compact delete text-white" >Delete</a></div>';

            $payable_Amount = $app_data->coin_rate * $item->coin_amount;

            // Simple payment gateway display - details available via Info button
            $payment_display = 'Bank Transfer';

            $data[] = array(
                $image,
                $item->user->fullname,
                $item->request_id,
                $item->coin_amount,
                $app_data->currency . ' ' . $payable_Amount,
                $payment_display,
                $block,
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

    function placeRedeemRequest(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'payment_gateway' => 'required',
            'coin_amount' => 'required|integer|min:1',
            'account_holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255', 
            'account_number' => 'required|string|max:50',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('id', $request->user_id)->first();
        if ($user == null) {
            return response()->json([
                'status' => false,
                'message' => 'user not found!',
            ]);
        }

        $appdata = AppData::first();
        $requestedAmount = (int) $request->coin_amount;

        // Validate minimum threshold
        if ($requestedAmount < $appdata->min_threshold) {
            return response()->json([
                'status' => false,
                'message' => 'Requested amount is below minimum threshold of ' . $appdata->min_threshold,
            ]);
        }

        // Validate user has enough coins
        if ($user->wallet < $requestedAmount) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient coins in wallet!',
            ]);
        }

        $redeemRequest = new RedeemRequest();
        $redeemRequest->user_id = $user->id;
        $redeemRequest->request_id = $this->generateCode();
        $redeemRequest->coin_amount = $requestedAmount; // Use selected amount
        $redeemRequest->payment_gateway = $request->payment_gateway;
        
        // Store structured bank transfer data
        $redeemRequest->account_holder_name = $request->account_holder_name;
        $redeemRequest->bank_name = $request->bank_name;
        $redeemRequest->account_number = $request->account_number;
        
        // Keep legacy account_details for backward compatibility
        $redeemRequest->account_details = $request->account_details ?? 
            "Account Holder: {$request->account_holder_name}\nBank Name: {$request->bank_name}\nAccount Number: {$request->account_number}";

        // Deduct only the requested amount from user wallet
        $user->wallet = $user->wallet - $requestedAmount;
        $user->save();

        $result = $redeemRequest->save();
        if ($result) {
             return response()->json([
                'status' => true,
                'message' => 'Redeem Request placed successfully!',
            ]);
        } else {
             return response()->json([
                'status' => false,
                'message' => 'something went wrong!',
            ]);
        }
    }

    function fetchMyRedeemRequests(Request $request)
    {
        $rules = [
            'user_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('id', $request->user_id)->first();
        if ($user == null) {
             return response()->json([
                'status' => false,
                'message' => 'user not found!',
            ]);
        }
        $redeems = RedeemRequest::where('user_id', $request->user_id)->get();

         return response()->json([
            'status' => true,
            'message' => 'Data fetch successfully !',
            'data' => $redeems,
        ]);
    }


    function generateCode()
    {


        function generateRandomString($length)
        {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }


        $token =  rand(100000, 999999);

        $first = generateRandomString(3);
        $first .= $token;
        $first .= generateRandomString(3);



        $count = RedeemRequest::where('request_id', $first)->count();

        while ($count >= 1) {

            $token =  rand(100000, 999999);

            $first = generateRandomString(3);
            $first .= $token;
            $first .= generateRandomString(3);
            $count = RedeemRequest::where('request_id', $first)->count();
        }

        return $first;
    }
}
