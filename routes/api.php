<?php

use App\Http\Controllers\DiamondPackController;
use App\Http\Controllers\EarningsAnalyticsController;
use App\Http\Controllers\GiftInventoryController;
use App\Http\Controllers\InterestController;
use App\Http\Controllers\LiveApplicationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CloudflareController;
use App\Http\Controllers\RedeemRequestsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


/*|--------------------------------------------------------------------------|
  | Users Route                                                              |
  |--------------------------------------------------------------------------|*/

Route::post('register', [UsersController::class, 'addUserDetails'])->middleware('checkHeader');
Route::post('updateProfile', [UsersController::class, 'updateProfile'])->middleware('checkHeader');
Route::post('fetchUsersByCordinates', [UsersController::class, 'fetchUsersByCordinates'])->middleware('checkHeader');
Route::post('updateUserBlockList', [UsersController::class, 'updateUserBlockList'])->middleware('checkHeader');
Route::post('deleteMyAccount', [UsersController::class, 'deleteMyAccount'])->middleware('checkHeader');
Route::post('minusCoinsFromWallet', [UsersController::class, 'minusCoinsFromWallet'])->middleware('checkHeader');
Route::post('toggleLiveStreamStatus', [UsersController::class, 'toggleLiveStreamStatus'])->middleware('checkHeader');

Route::post('getProfile', [UsersController::class, 'getProfile'])->middleware('checkHeader');
Route::post('getUserDetails', [UsersController::class, 'getUserDetails'])->middleware('checkHeader');
Route::post('getRandomProfile', [UsersController::class, 'getRandomProfile'])->middleware(['checkHeader', 'throttle:90,1']);
Route::post('getExplorePageProfileList', [UsersController::class, 'getExplorePageProfileList'])->middleware(['checkHeader', 'throttle:60,1']);

Route::post('updateSavedProfile', [UsersController::class, 'updateSavedProfile'])->middleware('checkHeader');
Route::post('updateLikedProfile', [UsersController::class, 'updateLikedProfile'])->middleware(['checkHeader', 'throttle:180,1']);

Route::post('fetchSavedProfiles', [UsersController::class, 'fetchSavedProfiles'])->middleware('checkHeader');
Route::post('fetchLikedProfiles', [UsersController::class, 'fetchLikedProfiles'])->middleware('checkHeader');

Route::post('getPackage', [PackageController::class, 'getPackage'])->middleware('checkHeader');
Route::post('getInterests', [InterestController::class, 'getInterests'])->middleware('checkHeader');
Route::post('addUserReport', [ReportController::class, 'addUserReport'])->middleware('checkHeader');
Route::post('getSettingData', [SettingController::class, 'getSettingData'])->middleware('checkHeader');

Route::post('searchUsers', [UsersController::class, 'searchUsers'])->middleware('checkHeader');
Route::post('searchUsersForInterest', [UsersController::class, 'searchUsersForInterest'])->middleware('checkHeader');

Route::post('getUserNotifications', [NotificationController::class, 'getUserNotifications'])->middleware('checkHeader');
Route::post('getAdminNotifications', [NotificationController::class, 'getAdminNotifications'])->middleware('checkHeader');

Route::post('getDiamondPacks', [DiamondPackController::class, 'getDiamondPacks'])->middleware('checkHeader');

Route::post('onOffNotification', [UsersController::class, 'onOffNotification'])->middleware('checkHeader');
Route::post('updateLiveStatus', [UsersController::class, 'updateLiveStatus'])->middleware('checkHeader');
Route::post('onOffShowMeOnMap', [UsersController::class, 'onOffShowMeOnMap'])->middleware('checkHeader');
Route::post('onOffAnonymous', [UsersController::class, 'onOffAnonymous'])->middleware('checkHeader');
Route::post('onOffVideoCalls', [UsersController::class, 'onOffVideoCalls'])->middleware('checkHeader');

Route::post('fetchBlockedProfiles', [UsersController::class, 'fetchBlockedProfiles'])->middleware('checkHeader');
Route::post('getSwipeStatus', [UsersController::class, 'getSwipeStatus'])->middleware(['checkHeader', 'throttle:180,1']);
Route::post('incrementSwipeCount', [UsersController::class, 'incrementSwipeCount'])->middleware(['checkHeader', 'throttle:90,1']);

Route::post('applyForLive', [LiveApplicationController::class, 'applyForLive'])->middleware('checkHeader');
Route::post('applyForVerification', [UsersController::class, 'applyForVerification'])->middleware('checkHeader');

Route::post('addCoinsToWallet', [UsersController::class, 'addCoinsToWallet'])->middleware('checkHeader');
// minusCoinsFromWallet route removed - free chat only
Route::post('increaseStreamCountOfUser', [UsersController::class, 'increaseStreamCountOfUser'])->middleware('checkHeader');

Route::post('addLiveStreamHistory', [LiveApplicationController::class, 'addLiveStreamHistory'])->middleware('checkHeader');
Route::post('logOutUser', [UsersController::class, 'logOutUser'])->middleware('checkHeader');
Route::post('fetchAllLiveStreamHistory', [LiveApplicationController::class, 'fetchAllLiveStreamHistory'])->middleware('checkHeader');

Route::post('placeRedeemRequest', [RedeemRequestsController::class, 'placeRedeemRequest'])->middleware('checkHeader');
Route::post('fetchMyRedeemRequests', [RedeemRequestsController::class, 'fetchMyRedeemRequests'])->middleware('checkHeader');
Route::post('pushNotificationToSingleUser', [NotificationController::class, 'pushNotificationToSingleUser'])->middleware('checkHeader');
Route::post('sendLivestreamNotificationToFollowers', [NotificationController::class, 'sendLivestreamNotificationToFollowers'])->middleware('checkHeader');



Route::post('followUser', [UsersController::class, 'followUser'])->middleware('checkHeader');
Route::post('followMultipleUsers', [UsersController::class, 'followMultipleUsers'])->middleware('checkHeader');
Route::post('unfollowMultipleUsers', [UsersController::class, 'unfollowMultipleUsers'])->middleware('checkHeader');
Route::post('fetchFollowingList', [UsersController::class, 'fetchFollowingList'])->middleware('checkHeader');
Route::post('fetchFollowersList', [UsersController::class, 'fetchFollowersList'])->middleware('checkHeader');
Route::post('unfollowUser', [UsersController::class, 'unfollowUser'])->middleware('checkHeader');

Route::post('fetchHomePageData', [UsersController::class, 'fetchHomePageData'])->middleware('checkHeader');
Route::post('fetchFollowingPageData', [UsersController::class, 'fetchFollowingPageData'])->middleware('checkHeader');

Route::post('createStory', [PostController::class, 'createStory'])->middleware('checkHeader');
Route::post('viewStory', [PostController::class, 'viewStory'])->middleware('checkHeader');
Route::post('fetchStories', [PostController::class, 'fetchStories'])->middleware('checkHeader');
Route::post('deleteStory', [PostController::class, 'deleteStory'])->middleware('checkHeader');

Route::post('reportPost', [PostController::class, 'reportPost'])->middleware('checkHeader');

Route::post('addPost', [PostController::class, 'addPost'])->middleware('checkHeader');
// Route::post('fetchPosts', [PostController::class, 'fetchPosts'])->middleware('checkHeader');
Route::post('addComment', [PostController::class, 'addComment'])->middleware('checkHeader');
Route::post('fetchComments', [PostController::class, 'fetchComments'])->middleware('checkHeader');
Route::post('deleteComment', [PostController::class, 'deleteComment'])->middleware('checkHeader');
Route::post('likePost', [PostController::class, 'likePost'])->middleware('checkHeader');
Route::post('dislikePost', [PostController::class, 'dislikePost'])->middleware('checkHeader');
Route::post('deleteMyPost', [PostController::class, 'deleteMyPost'])->middleware('checkHeader');
Route::post('fetchPostByUser', [PostController::class, 'fetchPostByUser'])->middleware('checkHeader');
Route::post('getUserFeed', [PostController::class, 'getUserFeed'])->middleware('checkHeader');
Route::post('fetchPostsByHashtag', [PostController::class, 'fetchPostsByHashtag'])->middleware('checkHeader');
Route::post('fetchPostByPostId', [PostController::class, 'fetchPostByPostId'])->middleware('checkHeader');
Route::post('increasePostViewCount', [PostController::class, 'increasePostViewCount'])->middleware('checkHeader');

// Cloudflare Stream Routes for Direct Creator Upload (Videos)
Route::post('cloudflare/getUploadUrl', [CloudflareController::class, 'getUploadUrl'])->middleware('checkHeader');
Route::post('cloudflare/checkVideoStatus', [CloudflareController::class, 'checkVideoStatus'])->middleware('checkHeader');
Route::post('cloudflare/deleteVideo', [CloudflareController::class, 'deleteVideo'])->middleware('checkHeader');
Route::post('cloudflare/webhook', [CloudflareController::class, 'webhook']); // No auth for webhook

// Cloudflare Images Routes for Direct Creator Upload (Images)
Route::post('cloudflare/getImageUploadUrl', [CloudflareController::class, 'getImageUploadUrl'])->middleware('checkHeader');
Route::post('cloudflare/deleteImage', [CloudflareController::class, 'deleteImage'])->middleware('checkHeader');

Route::get('test', [UsersController::class, 'test'])->middleware('checkHeader');

Route::get('deleteStoryFromWeb', [PostController::class, 'deleteStoryFromWeb'])->name('deleteStoryFromWeb');
Route::post('storeFileGivePath', [SettingController::class, 'storeFileGivePath'])->middleware('checkHeader');
Route::post('generateAgoraToken', [SettingController::class, 'generateAgoraToken'])->middleware('checkHeader');

/*|--------------------------------------------------------------------------|
  | Pending Messages Routes                                                  |
  |--------------------------------------------------------------------------|*/

// Pending message routes removed - free chat only

// Live stream chat notification routes removed - free chat only

/*|--------------------------------------------------------------------------|
  | Subscription Routes                                                      |
  |--------------------------------------------------------------------------|*/

Route::post('subscription/plans', [SubscriptionController::class, 'getPlans'])->middleware('checkHeader');
Route::post('subscription/create-payment-intent', [SubscriptionController::class, 'createPaymentIntent'])->middleware('checkHeader');
Route::post('subscription/create', [SubscriptionController::class, 'createSubscription'])->middleware('checkHeader');
Route::post('subscription/status', [SubscriptionController::class, 'getSubscriptionStatus'])->middleware('checkHeader');
Route::post('subscription/cancel', [SubscriptionController::class, 'cancelSubscription'])->middleware('checkHeader');
Route::post('subscription/resume', [SubscriptionController::class, 'resumeSubscription'])->middleware('checkHeader');
Route::post('subscription/update-payment-method', [SubscriptionController::class, 'updatePaymentMethod'])->middleware('checkHeader');
Route::post('subscription/confirm-payment', [SubscriptionController::class, 'confirmPaymentSuccess'])->middleware('checkHeader');
Route::post('subscription/confirm-iap', [SubscriptionController::class, 'confirmIAPPayment'])->middleware('checkHeader');
Route::post('subscription/simple-confirm', [SubscriptionController::class, 'simpleConfirm'])->middleware('checkHeader');

// Webhook notification endpoint (called by Go webhook service)
Route::post('webhook/subscription-confirmed', [SubscriptionController::class, 'handleWebhookConfirmation'])->middleware('checkHeader');

/*|--------------------------------------------------------------------------|
  | Gift Inventory Routes                                                    |
  |--------------------------------------------------------------------------|*/

Route::post('sendGiftToUser', [GiftInventoryController::class, 'sendGiftToUser'])->middleware('checkHeader');
Route::post('getUserGiftInventory', [GiftInventoryController::class, 'getUserGiftInventory'])->middleware('checkHeader');
Route::post('convertGiftsToCoins', [GiftInventoryController::class, 'convertGiftsToCoins'])->middleware('checkHeader');
Route::post('getGiftConversionData', [GiftInventoryController::class, 'getGiftConversionData'])->middleware('checkHeader');
Route::post('getInventoryStats', [GiftInventoryController::class, 'getInventoryStats'])->middleware('checkHeader');

/*|--------------------------------------------------------------------------|
  | Earnings Analytics Routes                                                |
  |--------------------------------------------------------------------------|*/

Route::post('getEarningsAnalytics', [EarningsAnalyticsController::class, 'getEarningsAnalytics'])->middleware('checkHeader');
Route::post('getGifterDemographics', [EarningsAnalyticsController::class, 'getGifterDemographics'])->middleware('checkHeader');
Route::post('getTopPerformingStreams', [EarningsAnalyticsController::class, 'getTopPerformingStreams'])->middleware('checkHeader');
Route::post('getFollowerGiftAnalysis', [EarningsAnalyticsController::class, 'getFollowerGiftAnalysis'])->middleware('checkHeader');

/*|--------------------------------------------------------------------------|
  | Suggestion Routes (Suggested People to Follow)                          |
  |--------------------------------------------------------------------------|*/

Route::post('getSuggestedUsers', [SuggestionController::class, 'getSuggestedUsers'])->middleware('checkHeader');
Route::post('getSuggestionPreferences', [SuggestionController::class, 'getSuggestionPreferences'])->middleware('checkHeader');
Route::post('updateSuggestionPreferences', [SuggestionController::class, 'updateSuggestionPreferences'])->middleware('checkHeader');
Route::post('dismissSuggestion', [SuggestionController::class, 'dismissSuggestion'])->middleware('checkHeader');
Route::post('undoDismissal', [SuggestionController::class, 'undoDismissal'])->middleware('checkHeader');
Route::post('getDismissedUsers', [SuggestionController::class, 'getDismissedUsers'])->middleware('checkHeader');
Route::post('rateSuggestion', [SuggestionController::class, 'rateSuggestion'])->middleware('checkHeader');

/*|--------------------------------------------------------------------------|
  | iOS Subscription Routes (Apple IAP)                                     |
  |--------------------------------------------------------------------------|*/

Route::get('subscription/ios-plans', [SubscriptionController::class, 'getIOSSubscriptionPlans'])->middleware('checkHeader');
Route::post('subscription/ios-confirm', [SubscriptionController::class, 'confirmIOSSubscription'])->middleware('checkHeader');