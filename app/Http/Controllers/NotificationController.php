<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use App\Models\GlobalFunction;
use App\Models\UserNotification;
use App\Models\FollowingList;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Google\Client;
use Illuminate\Support\Facades\File;

class NotificationController extends Controller
{
    function pushNotificationToSingleUser(Request $request)
    {
        $client = new Client();
        $client->setAuthConfig(base_path('firebase-credentials.json'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $accessToken = $client->getAccessToken();
        $accessToken = $accessToken['access_token'];

        // Log::info($accessToken);
        $contents = File::get(base_path('firebase-credentials.json'));
        $json = json_decode(json: $contents, associative: true);

        $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
        // $notificationArray = array('title' => $title, 'body' => $message);

        // $device_token = $user->device_token;

        $fields = $request->json()->all();

        // $fields = array(
        //     'message'=> [
        //         'token'=> $device_token,
        //         'notification' => $notificationArray,
        //     ]
        // );

        $headers = array(
            'Content-Type:application/json',
            'Authorization:Bearer ' . $accessToken
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        // print_r(json_encode($fields));
        $result = curl_exec($ch);
        Log::debug($result);

        if ($result === FALSE) {
            die('FCM Send Error: ' . curl_error($ch));
        }
        curl_close($ch);

        // return $response;
        return response()->json(['result'=> $result, 'fields'=> $fields]);
    }

    /**
     * Send push notification to specific user for live stream chat
     */
    public static function sendLiveStreamChatNotification($userId, $title, $message, $data = [])
    {
        try {
            // Get user's FCM token
            $user = \App\Models\Users::find($userId);
            if (!$user || !$user->device_token) {
                Log::info("No FCM token found for user: $userId");
                return false;
            }

            $client = new Client();
            $client->setAuthConfig(base_path('firebase-credentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->fetchAccessTokenWithAssertion();
            $accessToken = $client->getAccessToken();
            $accessToken = $accessToken['access_token'];

            $contents = File::get(base_path('firebase-credentials.json'));
            $json = json_decode($contents, true);

            $url = 'https://fcm.googleapis.com/v1/projects/' . $json['project_id'] . '/messages:send';
            
            $notificationPayload = [
                'title' => $title,
                'body' => $message
            ];

            $fields = [
                'message' => [
                    'token' => $user->device_token,
                    'notification' => $notificationPayload,
                    'data' => array_merge([
                        'type' => 'live_stream_chat',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ], array_map('strval', $data)), // Convert all values to strings for FCM
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default'
                        ]
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1
                            ]
                        ]
                    ]
                ]
            ];

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($result === FALSE) {
                Log::error('FCM Send Error: ' . curl_error($ch));
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            
            $response = json_decode($result, true);
            if ($httpCode == 200) {
                Log::info("Live stream chat notification sent successfully to user: $userId");
                return true;
            } else {
                Log::error("FCM Error: " . $result);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error("Live stream chat notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send livestream notification to followers only
     */
    public function sendLivestreamNotificationToFollowers(Request $request)
    {
        $rules = [
            'streamer_user_id' => 'required',
            'title' => 'required',
            'body' => 'required',
            'live_stream_data' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $streamerUserId = $request->streamer_user_id;
        $title = $request->title;
        $body = $request->body;
        $liveStreamData = $request->live_stream_data;

        // Get all followers of this streamer
        $followers = FollowingList::where('user_id', $streamerUserId)
                                 ->with('followerUser') // Get the user who is following
                                 ->get();

        if ($followers->isEmpty()) {
            return response()->json([
                'status' => true, 
                'message' => 'No followers found for this streamer',
                'sent_count' => 0
            ]);
        }

        $successCount = 0;
        $totalFollowers = $followers->count();

        foreach ($followers as $follower) {
            $followerUser = $follower->followerUser; // The person who follows the streamer
            
            if (!$followerUser || !$followerUser->device_token || $followerUser->is_notification != 1) {
                continue; // Skip if no token or notifications disabled
            }

            // Send individual notification
            if ($this->sendSingleLivestreamNotification($followerUser->device_token, $title, $body, $liveStreamData)) {
                $successCount++;
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Livestream notification sent successfully",
            'total_followers' => $totalFollowers,
            'sent_count' => $successCount
        ]);
    }

    /**
     * Send single livestream notification to specific device token
     */
    private function sendSingleLivestreamNotification($deviceToken, $title, $body, $liveStreamData)
    {
        try {
            $client = new Client();
            $client->setAuthConfig(base_path('googleCredentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->fetchAccessTokenWithAssertion();
            $accessToken = $client->getAccessToken();
            $accessToken = $accessToken['access_token'];

            $contents = File::get(base_path('googleCredentials.json'));
            $json = json_decode($contents, true);

            $url = 'https://fcm.googleapis.com/v1/projects/' . $json['project_id'] . '/messages:send';
            
            $fields = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body
                    ],
                    'data' => [
                        'event_type' => 'live_stream_start',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'params' => json_encode(is_array($liveStreamData) ? $liveStreamData : ['data' => $liveStreamData]),
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default'
                        ]
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1
                            ]
                        ]
                    ]
                ]
            ];

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($result === FALSE || $httpCode != 200) {
                Log::error('Livestream notification failed: ' . curl_error($ch));
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            return true;
            
        } catch (\Exception $e) {
            Log::error("Livestream notification error: " . $e->getMessage());
            return false;
        }
    }

    function notifications(Request $req)
    {
        return view('notifications');
    }

    function updateNotification(Request $request)
    {
        $notification = AdminNotification::where('id', $request->id)->first();
        $notification->title = $request->title;
        $notification->message = $request->message;
        $notification->save();

        return response()->json([
            'status' => true,
            'message' => 'Notification Updated Successfully',
        ]);
    }

    function getNotificationById($id)
    {
        $data = AdminNotification::where('id', $id)->first();
        echo json_encode($data);
    }

    function deleteNotification(Request $request)
    {
        $notification = AdminNotification::where('id', $request->notification_id)->first();
        $notification->delete();

        return response()->json([
            'status' => true,
            'message' => 'Notification Deleted Successfully',
        ]);
    }

    function addNotification(Request $request)
    {

        $notification = new AdminNotification;
        $notification->title = $request->title;
        $notification->message = $request->message;
        $notification->save();

        $title = $request->title;
        $message  = $request->message;
      
        GlobalFunction::sendPushNotificationToAllUsers($title, $message);

        return response()->json([
            'status' => true,
            'message' => 'Notification Send Successfully',
        ]);
     
    }

    public function repeatNotification(Request $request)
    {
        $title = $request->title;
        $message  = $request->message;

        GlobalFunction::sendPushNotificationToAllUsers($title, $message);

        return response()->json([
                'status' => true,
                'message' => 'Notification Repeat Successfully',
            ]);
    }

    function fetchAllNotification(Request $request)
    {

        $totalData =  AdminNotification::count();
        $rows = AdminNotification::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'title'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $totalData = AdminNotification::count();
        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = AdminNotification::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  AdminNotification::Where('title', 'LIKE', "%{$search}%")
                ->orWhere('message', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = AdminNotification::where('title', 'LIKE', "%{$search}%")
                ->orWhere('message', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {


            $repeat = '<a href="#" data-title="' . $item->title . '" data-message="' . $item->message . '" class="me-3 btn btn-info px-4 text-white shadow-none repeat saveButton2" rel=' . $item->id . '>' . __('Repeat') . '</a>';
            $edit = '<a rel="' . $item->id . '" class="btn btn-success edit mr-2 text-white" data-title="' . $item->title . '" data-message="' . $item->message . '" > ' . __('Edit') . ' </a>';
            $delete = '<a rel="' . $item->id . '" class="btn btn-danger delete text-white"> ' . __('Delete') . ' </a>';
            $action = '<span class="float-end">' . $repeat . $edit . $delete . ' </span>';

 

            $data[] = array(
                $item->title,
                $item->message,
                $action
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

    function getAdminNotifications(Request $req)
    {
        $rules = [
            'start' => 'required',
            'count' => 'required',
        ];

        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $result =  AdminNotification::offset($req->start)
                                    ->limit($req->count)
                                    ->orderBy('id', 'DESC')
                                    ->get();


        if ($result->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No data found',
                'data' => $result
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'data get successfully',
            'data' => $result
        ]);
    }

    function getUserNotifications(Request $req)
    {
        $rules = [
            'user_id' => 'required',
            'start' => 'required',
            'count' => 'required',
        ];

        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $result =  UserNotification::Where('user_id', $req->user_id)
                                    ->with('user')
                                    ->with('user.images')
                                    ->with('receiverUser')
                                    ->offset($req->start)
                                    ->limit($req->count)
                                    ->orderBy('id', 'DESC')
                                    ->get();


        if ($result->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No data found',
                'data' => $result
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'data get successfully',
            'data' => $result
        ]);
    }

    /**
     * Get all notifications (both user and admin notifications merged)
     * Returns unified notification list sorted by created_at
     */
    function getAllNotifications(Request $req)
    {
        Log::info('=== getAllNotifications API Called ===');
        Log::info('Request params: ' . json_encode($req->all()));

        $rules = [
            'user_id' => 'required',
            'start' => 'required',
            'count' => 'required',
        ];

        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            Log::info('Validation failed: ' . $msg);
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $start = (int) $req->start;
        $count = (int) $req->count;
        Log::info("User ID: {$req->user_id}, Start: $start, Count: $count");

        // Fetch extra items to ensure we have enough after merging
        // We fetch 2x the requested count from each source to handle edge cases
        $fetchLimit = $count * 2;

        // Get user notifications (personal)
        $userNotifications = UserNotification::where('user_id', $req->user_id)
            ->with('user')
            ->with('user.images')
            ->with('receiverUser')
            ->orderBy('created_at', 'DESC')
            ->limit($fetchLimit)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'source' => 'personal',
                    'type' => $notification->type,
                    'my_user_id' => $notification->my_user_id,
                    'user_id' => $notification->user_id,
                    'item_id' => $notification->item_id,
                    'user' => $notification->user,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'created_at' => $notification->created_at,
                    'updated_at' => $notification->updated_at,
                ];
            });

        // Get admin notifications (platform)
        $adminNotifications = AdminNotification::orderBy('created_at', 'DESC')
            ->limit($fetchLimit)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'source' => 'platform',
                    'type' => null,
                    'my_user_id' => null,
                    'user_id' => null,
                    'item_id' => null,
                    'user' => null,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'created_at' => $notification->created_at,
                    'updated_at' => $notification->updated_at,
                ];
            });

        // Merge both collections
        $allNotifications = $userNotifications->concat($adminNotifications);

        // Sort by created_at descending
        $allNotifications = $allNotifications->sortByDesc('created_at')->values();

        // Apply pagination
        $paginatedNotifications = $allNotifications->slice($start, $count)->values();

        Log::info("User notifications found: " . $userNotifications->count());
        Log::info("Admin notifications found: " . $adminNotifications->count());
        Log::info("Total merged: " . $allNotifications->count());
        Log::info("After pagination: " . $paginatedNotifications->count());

        return response()->json([
            'status' => true,
            'message' => 'data get successfully',
            'data' => $paginatedNotifications
        ]);
    }
}
