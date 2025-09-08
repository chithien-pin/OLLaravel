<?php

namespace App\Http\Controllers;

use App\Models\Users;
use App\Models\Subscription;
use App\Models\SubscriptionPacks;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController extends Controller
{
    /**
     * Get available subscription plans for user (supports both Stripe and IAP)
     */
    public function getPlans(Request $request)
    {
        Log::info('SUBSCRIPTION: getPlans called', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all()
        ]);
        
        try {
            $platform = $request->input('platform', 'android'); // Default to android (Stripe)
            
            if ($platform === 'ios') {
                // iOS: Return IAP product IDs from subscription_packs table
                return $this->getIOSPlans($request);
            } else {
                // Android: Return existing Stripe plans (unchanged)
                return $this->getStripePlans($request);
            }
        } catch (\Exception $e) {
            Log::error('Get plans error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to retrieve subscription plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get iOS subscription plans (IAP)
     */
    private function getIOSPlans(Request $request)
    {
        $subscriptionPacks = SubscriptionPacks::getAllPacks();
        $plans = [];
        
        foreach ($subscriptionPacks as $pack) {
            $plans[$pack->plan_type] = $pack->toApiArray();
        }
        
        // Check starter plan eligibility if user_id provided
        if ($request->has('current_user_id')) {
            $isEligibleForStarter = Subscription::isEligibleForStarterPlan($request->current_user_id);
            
            if ($isEligibleForStarter) {
                // First-time user: Show only Starter + Yearly (remove Monthly)
                unset($plans['monthly']);
            } else {
                // Existing user: Show only Monthly + Yearly (remove Starter)
                unset($plans['starter']);
            }
            
            // Add eligibility info to response
            $plans['starter_eligible'] = $isEligibleForStarter;
        }
        
        return response()->json([
            'status' => 200,
            'message' => 'iOS subscription plans retrieved successfully',
            'data' => $plans
        ]);
    }

    /**
     * Get Android subscription plans (Stripe) - unchanged
     */
    private function getStripePlans(Request $request)
    {
        $plans = Subscription::getSubscriptionPlans();
        
        // If user_id is provided, check starter plan eligibility
        if ($request->has('current_user_id')) {
            $isEligibleForStarter = Subscription::isEligibleForStarterPlan($request->current_user_id);
            
            if ($isEligibleForStarter) {
                // First-time user: Show only Starter + Yearly (remove Monthly)
                unset($plans['monthly']);
            } else {
                // Existing user: Show only Monthly + Yearly (remove Starter)
                unset($plans['starter']);
            }
            
            // Add eligibility info to response
            $plans['starter_eligible'] = $isEligibleForStarter;
        }
        
        return response()->json([
            'status' => 200,
            'message' => 'Android subscription plans retrieved successfully',
            'data' => $plans
        ]);
    }

    /**
     * Create subscription intent
     */
    public function createSubscription(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'plan_type' => 'required|in:starter,monthly,yearly,millionaire,billionaire',
                'payment_method_id' => 'required|string'
            ]);

            $user = Users::find($request->user_id);
            $plans = Subscription::getSubscriptionPlans();
            $planData = $plans[$request->plan_type];

            // Create or retrieve Stripe customer
            if (!$user->stripe_id) {
                $user->createAsStripeCustomer();
            }

            // Add payment method to customer
            $user->addPaymentMethod($request->payment_method_id);
            $user->updateDefaultPaymentMethod($request->payment_method_id);

            // Create subscription
            $subscription = $user->newSubscription('default', $planData['price_id'])
                ->create($request->payment_method_id, [
                    'expand' => ['latest_invoice.payment_intent'],
                ]);

            // Create our local subscription record
            $localSubscription = Subscription::create([
                'user_id' => $user->id,
                'stripe_subscription_id' => $subscription->stripe_id,
                'stripe_customer_id' => $user->stripe_id,
                'plan_type' => $request->plan_type,
                'stripe_price_id' => $planData['price_id'],
                'status' => 'active',
                'amount' => $planData['amount'],
                'currency' => $planData['currency'],
                'starts_at' => now(),
                'ends_at' => ($request->plan_type === 'starter' || $request->plan_type === 'monthly') ? now()->addMonth() : now()->addYear(),
            ]);

            // Assign VIP role to user
            $user->assignRole('vip', $request->plan_type === 'monthly' ? '1_month' : '1_year');

            return response()->json([
                'status' => 200,
                'message' => 'Subscription created successfully',
                'data' => [
                    'subscription_id' => $localSubscription->id,
                    'status' => 'active',
                    'plan_type' => $request->plan_type,
                    'starts_at' => $localSubscription->starts_at,
                    'ends_at' => $localSubscription->ends_at,
                ]
            ]);

        } catch (IncompletePayment $exception) {
            return response()->json([
                'status' => 400,
                'message' => 'Payment requires additional verification',
                'data' => [
                    'payment_intent' => [
                        'id' => $exception->payment->id,
                        'client_secret' => $exception->payment->client_secret,
                    ],
                ]
            ], 400);

        } catch (\Exception $e) {
            Log::error('Create subscription error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's subscription status
     */
    public function getSubscriptionStatus(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id'
            ]);

            $user = Users::find($request->user_id);
            $activeSubscription = $user->activeSubscription;

            if (!$activeSubscription) {
                return response()->json([
                    'status' => 200,
                    'message' => 'No active subscription found',
                    'data' => [
                        'has_subscription' => false,
                        'subscription' => null,
                        'role' => $user->getCurrentRoleType()
                    ]
                ]);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Subscription status retrieved successfully',
                'data' => [
                    'has_subscription' => true,
                    'subscription' => [
                        'id' => $activeSubscription->id,
                        'plan_type' => $activeSubscription->plan_type,
                        'status' => $activeSubscription->status,
                        'amount' => $activeSubscription->amount,
                        'currency' => $activeSubscription->currency,
                        'starts_at' => $activeSubscription->starts_at,
                        'ends_at' => $activeSubscription->ends_at,
                        'days_remaining' => $activeSubscription->getDaysRemaining(),
                        'display_name' => $activeSubscription->getPlanDisplayName()
                    ],
                    'role' => $user->getCurrentRoleType(),
                    'is_vip' => $user->isVip()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get subscription status error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to retrieve subscription status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id'
            ]);

            $user = Users::find($request->user_id);
            $activeSubscription = $user->activeSubscription;

            if (!$activeSubscription) {
                return response()->json([
                    'status' => 404,
                    'message' => 'No active subscription found'
                ], 404);
            }

            // Cancel Stripe subscription
            if ($user->subscribed('default')) {
                $user->subscription('default')->cancel();
            }

            // Update local subscription status
            $activeSubscription->update([
                'status' => 'canceled',
                'canceled_at' => now()
            ]);

            // Keep VIP role until expiry but mark as canceled
            // The role will automatically expire when ends_at is reached

            return response()->json([
                'status' => 200,
                'message' => 'Subscription canceled successfully',
                'data' => [
                    'subscription_id' => $activeSubscription->id,
                    'status' => 'canceled',
                    'canceled_at' => $activeSubscription->canceled_at,
                    'ends_at' => $activeSubscription->ends_at,
                    'message' => 'Your VIP access will continue until ' . $activeSubscription->ends_at->format('Y-m-d')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Cancel subscription error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume subscription
     */
    public function resumeSubscription(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id'
            ]);

            $user = Users::find($request->user_id);

            if (!$user->subscription('default') || !$user->subscription('default')->canceled()) {
                return response()->json([
                    'status' => 400,
                    'message' => 'No canceled subscription found to resume'
                ], 400);
            }

            // Resume Stripe subscription
            $user->subscription('default')->resume();

            // Update local subscription status
            $activeSubscription = $user->activeSubscription;
            if ($activeSubscription) {
                $activeSubscription->update([
                    'status' => 'active',
                    'canceled_at' => null
                ]);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Subscription resumed successfully',
                'data' => [
                    'subscription_id' => $activeSubscription ? $activeSubscription->id : null,
                    'status' => 'active'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Resume subscription error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to resume subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment method for subscription
     */
    public function updatePaymentMethod(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'payment_method_id' => 'required|string'
            ]);

            $user = Users::find($request->user_id);

            if (!$user->subscribed('default')) {
                return response()->json([
                    'status' => 404,
                    'message' => 'No active subscription found'
                ], 404);
            }

            // Add and update payment method
            $user->addPaymentMethod($request->payment_method_id);
            $user->updateDefaultPaymentMethod($request->payment_method_id);

            return response()->json([
                'status' => 200,
                'message' => 'Payment method updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Update payment method error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create payment intent for Stripe payment sheet
     */
    public function createPaymentIntent(Request $request)
    {
        Log::info('SUBSCRIPTION: createPaymentIntent called', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all()
        ]);
        
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'plan_type' => 'required|in:starter,monthly,yearly,millionaire,billionaire',
            ]);

            // Validate starter plan eligibility
            if ($request->plan_type === 'starter') {
                $isEligible = Subscription::isEligibleForStarterPlan($request->user_id);
                if (!$isEligible) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'User is not eligible for starter plan. Starter plan is only available for first-time subscribers.'
                    ], 400);
                }
            }

            $plans = Subscription::getSubscriptionPlans();
            $planData = $plans[$request->plan_type];

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            // Create PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $planData['amount'] * 100, // Convert to cents
                'currency' => strtolower($planData['currency']),
                'metadata' => [
                    'user_id' => $request->user_id,
                    'plan_type' => $request->plan_type,
                    'type' => 'subscription'
                ],
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Create payment intent error: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Handle successful payment confirmation from Flutter with Stripe verification
     */
    public function confirmPaymentSuccess(Request $request)
    {
        Log::info('SUBSCRIPTION: confirmPaymentSuccess called', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all()
        ]);
        
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'plan_type' => 'required|in:starter,monthly,yearly,millionaire,billionaire',
                'payment_intent_id' => 'required|string'
            ]);

            // Check for duplicate payment confirmation to prevent double processing
            $existingSubscription = Subscription::where('stripe_subscription_id', $request->payment_intent_id)
                ->where('user_id', $request->user_id)
                ->first();

            if ($existingSubscription) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Payment already confirmed',
                    'data' => [
                        'subscription_id' => $existingSubscription->id,
                        'status' => $existingSubscription->status,
                        'plan_type' => $existingSubscription->plan_type,
                        'expires_at' => $existingSubscription->ends_at,
                        'days_remaining' => $existingSubscription->getDaysRemaining()
                    ]
                ]);
            }

            // Validate starter plan eligibility again for security
            if ($request->plan_type === 'starter') {
                $isEligible = Subscription::isEligibleForStarterPlan($request->user_id);
                if (!$isEligible) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'User is not eligible for starter plan. Starter plan is only available for first-time subscribers.'
                    ], 400);
                }
            }

            Log::info('SUBSCRIPTION: About to verify PaymentIntent with Stripe', [
                'payment_intent_id' => $request->payment_intent_id,
                'user_id' => $request->user_id,
                'plan_type' => $request->plan_type
            ]);

            // STEP 1: Verify PaymentIntent with Stripe API
            $paymentIntentData = $this->verifyPaymentIntentWithStripe($request->payment_intent_id, $request->user_id, $request->plan_type);
            
            Log::info('SUBSCRIPTION: PaymentIntent verification result', [
                'success' => $paymentIntentData['success'],
                'message' => $paymentIntentData['message'] ?? null
            ]);
            
            if (!$paymentIntentData['success']) {
                Log::error('SUBSCRIPTION: PaymentIntent verification failed', [
                    'payment_intent_id' => $request->payment_intent_id,
                    'message' => $paymentIntentData['message']
                ]);
                return response()->json([
                    'status' => 400,
                    'message' => $paymentIntentData['message']
                ], 400);
            }

            $user = Users::find($request->user_id);
            $plans = Subscription::getSubscriptionPlans();
            $planData = $plans[$request->plan_type];

            // STEP 2: Create local subscription record with pending_webhook status
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'stripe_subscription_id' => $request->payment_intent_id,
                'stripe_customer_id' => $paymentIntentData['customer_id'],
                'payment_intent_id' => $request->payment_intent_id,
                'plan_type' => $request->plan_type,
                'stripe_price_id' => $planData['price_id'],
                'status' => 'pending_webhook',
                'amount' => $planData['amount'],
                'currency' => $planData['currency'],
                'starts_at' => now(),
                'ends_at' => ($request->plan_type === 'starter' || $request->plan_type === 'monthly') ? now()->addMonth() : now()->addYear(),
                'payment_intent_verified' => true,
                'metadata' => [
                    'payment_intent_id' => $request->payment_intent_id,
                    'confirmed_by_app' => true,
                    'stripe_payment_intent_data' => $paymentIntentData['payment_intent_data']
                ]
            ]);

            // STEP 3: Assign role/package immediately for better UX
            if ($planData['type'] === 'role') {
                // VIP role assignment for starter, monthly, yearly
                $duration = ($request->plan_type === 'starter' || $request->plan_type === 'monthly') ? '1_month' : '1_year';
                $userRole = $user->assignRole('vip', $duration);

                // Link subscription to user role
                if ($userRole) {
                    $userRole->update(['subscription_id' => $subscription->id]);
                }

                $responseMessage = 'Payment verified and VIP access granted (pending final webhook confirmation)';
                $responseRole = 'vip';
            } else {
                // Package assignment for millionaire, billionaire
                $packageType = $request->plan_type;
                $user->assignPackage($packageType);

                $responseMessage = "Payment verified and {$planData['name']} package activated (pending final webhook confirmation)";
                $responseRole = $packageType;
            }

            // Log successful verification
            Log::info("Payment verification successful", [
                'user_id' => $user->id,
                'payment_intent_id' => $request->payment_intent_id,
                'plan_type' => $request->plan_type,
                'subscription_id' => $subscription->id
            ]);

            return response()->json([
                'status' => 200,
                'message' => $responseMessage,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => 'pending_webhook',
                    'plan_type' => $request->plan_type,
                    'expires_at' => $subscription->ends_at,
                    'package_type' => $planData['type'],
                    'role' => $responseRole,
                    'days_remaining' => $subscription->getDaysRemaining(),
                    'payment_verified' => true,
                    'webhook_pending' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Confirm payment success error: ' . $e->getMessage(), [
                'user_id' => $request->user_id ?? null,
                'payment_intent_id' => $request->payment_intent_id ?? null,
                'plan_type' => $request->plan_type ?? null
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'Failed to confirm payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Payment processing failed'
            ], 500);
        }
    }

    /**
     * Verify PaymentIntent with Stripe API
     */
    private function verifyPaymentIntentWithStripe($paymentIntentId, $userId, $planType)
    {
        Log::info('SUBSCRIPTION: Starting Stripe verification', [
            'payment_intent_id' => $paymentIntentId,
            'user_id' => $userId,
            'plan_type' => $planType
        ]);
        
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            
            Log::info('SUBSCRIPTION: About to retrieve PaymentIntent from Stripe');
            
            // Retrieve PaymentIntent from Stripe
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            
            Log::info('SUBSCRIPTION: PaymentIntent retrieved successfully', [
                'id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency
            ]);

            // Check if payment succeeded
            if ($paymentIntent->status !== 'succeeded') {
                return [
                    'success' => false,
                    'message' => 'Payment has not been completed successfully. Status: ' . $paymentIntent->status
                ];
            }

            // Verify metadata if it exists
            if (isset($paymentIntent->metadata->user_id) && $paymentIntent->metadata->user_id != $userId) {
                return [
                    'success' => false,
                    'message' => 'Payment verification failed: User ID mismatch'
                ];
            }

            if (isset($paymentIntent->metadata->plan_type) && $paymentIntent->metadata->plan_type !== $planType) {
                return [
                    'success' => false,
                    'message' => 'Payment verification failed: Plan type mismatch'
                ];
            }

            // Verify amount matches plan
            $plans = Subscription::getSubscriptionPlans();
            $planData = $plans[$planType];
            $expectedAmountCents = $planData['amount'] * 100;

            Log::info('SUBSCRIPTION: Amount verification', [
                'plan_type' => $planType,
                'plan_amount_usd' => $planData['amount'],
                'expected_amount_cents' => $expectedAmountCents,
                'actual_amount_cents' => $paymentIntent->amount,
                'amounts_match' => ($paymentIntent->amount === $expectedAmountCents)
            ]);

            if ((int)$paymentIntent->amount !== (int)$expectedAmountCents) {
                Log::error('SUBSCRIPTION: Amount mismatch details', [
                    'expected_cents' => $expectedAmountCents,
                    'actual_cents' => $paymentIntent->amount,
                    'expected_type' => gettype($expectedAmountCents),
                    'actual_type' => gettype($paymentIntent->amount)
                ]);
                return [
                    'success' => false,
                    'message' => 'Payment verification failed: Amount mismatch'
                ];
            }

            // Verify currency
            if (strtoupper($paymentIntent->currency) !== strtoupper($planData['currency'])) {
                return [
                    'success' => false,
                    'message' => 'Payment verification failed: Currency mismatch'
                ];
            }

            return [
                'success' => true,
                'customer_id' => $paymentIntent->customer ?? null,
                'payment_intent_data' => [
                    'id' => $paymentIntent->id,
                    'amount' => $paymentIntent->amount,
                    'currency' => $paymentIntent->currency,
                    'status' => $paymentIntent->status,
                    'created' => $paymentIntent->created
                ]
            ];

        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return [
                'success' => false,
                'message' => 'Invalid PaymentIntent: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Stripe PaymentIntent verification error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment verification failed: Unable to verify with Stripe'
            ];
        }
    }

    /**
     * Handle webhook confirmation from Go service
     */
    public function handleWebhookConfirmation(Request $request)
    {
        Log::info('SUBSCRIPTION: Webhook confirmation received', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'subscription_id' => 'required|integer',
                'payment_intent_id' => 'required|string',
                'confirmed_by' => 'required|string',
                'confirmed_at' => 'required|string'
            ]);

            $user = Users::find($request->user_id);
            $subscription = Subscription::find($request->subscription_id);

            if (!$subscription) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Subscription not found'
                ], 404);
            }

            Log::info('SUBSCRIPTION: Processing webhook confirmation', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'payment_intent_id' => $request->payment_intent_id,
                'plan_type' => $subscription->plan_type
            ]);

            // Here you can add additional business logic that should happen
            // when a subscription is confirmed via webhook, such as:
            // - Send confirmation email
            // - Update user analytics
            // - Trigger notifications
            // - Update external systems

            // For now, just log the successful webhook confirmation
            Log::info('SUBSCRIPTION: Webhook confirmation processed successfully', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'payment_intent_id' => $request->payment_intent_id
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Webhook confirmation processed successfully',
                'data' => [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'confirmed_at' => $request->confirmed_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('SUBSCRIPTION: Webhook confirmation error: ' . $e->getMessage(), [
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Failed to process webhook confirmation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Confirm iOS IAP subscription purchase
     */
    public function confirmIAPPayment(Request $request)
    {
        Log::info('SUBSCRIPTION: confirmIAPPayment called', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all()
        ]);
        
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'ios_product_id' => 'required|string',
                'receipt_data' => 'required|string', // Base64 encoded receipt
                'transaction_id' => 'required|string'
            ]);

            // Find subscription pack by iOS product ID
            $subscriptionPack = SubscriptionPacks::findByIOSProductId($request->ios_product_id);
            if (!$subscriptionPack) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid iOS product ID'
                ], 400);
            }

            // SECURITY: Check iOS transaction deduplication table first
            $existingTransaction = DB::table('ios_transactions')
                ->where('transaction_id', $request->transaction_id)
                ->first();

            if ($existingTransaction) {
                if ($existingTransaction->validation_status === 'success') {
                    $existingSubscription = Subscription::find($existingTransaction->subscription_id);
                    return response()->json([
                        'status' => 200,
                        'message' => 'Transaction already processed',
                        'data' => [
                            'subscription_id' => $existingSubscription->id,
                            'status' => $existingSubscription->status,
                            'plan_type' => $existingSubscription->plan_type,
                            'expires_at' => $existingSubscription->ends_at,
                            'days_remaining' => $existingSubscription->getDaysRemaining()
                        ]
                    ]);
                } else {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Transaction validation failed or pending'
                    ], 400);
                }
            }

            // SECURITY: Validate Apple receipt BEFORE creating subscription
            $receiptValidation = $this->validateAppleReceipt($request->receipt_data, $request->transaction_id, $request->ios_product_id);
            
            if (!$receiptValidation['success']) {
                // Log failed transaction for audit
                DB::table('ios_transactions')->insert([
                    'transaction_id' => $request->transaction_id,
                    'user_id' => $request->user_id,
                    'product_id' => $request->ios_product_id,
                    'purchase_date' => now(),
                    'receipt_data' => $request->receipt_data,
                    'validation_status' => 'failed',
                    'processed_at' => now()
                ]);
                
                return response()->json([
                    'status' => 400,
                    'message' => $receiptValidation['message']
                ], 400);
            }

            // Validate starter plan eligibility if needed
            if ($subscriptionPack->plan_type === 'starter') {
                $isEligible = Subscription::isEligibleForStarterPlan($request->user_id);
                if (!$isEligible) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'User is not eligible for starter plan. Starter plan is only available for first-time subscribers.'
                    ], 400);
                }
            }

            $user = Users::find($request->user_id);
            
            // ATOMIC: Create subscription and log transaction in single transaction
            $subscription = DB::transaction(function () use ($request, $user, $subscriptionPack, $receiptValidation) {
                // Create local subscription record
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'stripe_subscription_id' => null, // Not using Stripe for iOS
                    'stripe_customer_id' => null,
                    'payment_intent_id' => $request->transaction_id, // Use transaction_id as identifier
                    'plan_type' => $subscriptionPack->plan_type,
                    'stripe_price_id' => $subscriptionPack->ios_product_id, // Store iOS product ID here
                    'status' => 'active', // IAP purchases are immediately active
                    'amount' => $subscriptionPack->amount,
                    'currency' => $subscriptionPack->currency,
                    'starts_at' => now(),
                    'ends_at' => $subscriptionPack->interval_type === 'month' ? now()->addMonth() : now()->addYear(),
                    'payment_intent_verified' => true,
                    'metadata' => [
                        'ios_product_id' => $request->ios_product_id,
                        'transaction_id' => $request->transaction_id,
                        'platform' => 'ios',
                        'receipt_validated' => true,
                        'original_transaction_id' => $receiptValidation['original_transaction_id'] ?? null,
                        'purchase_date' => $receiptValidation['purchase_date'] ?? now()
                    ]
                ]);
                
                // Log successful transaction
                DB::table('ios_transactions')->insert([
                    'transaction_id' => $request->transaction_id,
                    'original_transaction_id' => $receiptValidation['original_transaction_id'] ?? null,
                    'user_id' => $request->user_id,
                    'subscription_id' => $subscription->id,
                    'product_id' => $request->ios_product_id,
                    'purchase_date' => $receiptValidation['purchase_date'] ?? now(),
                    'receipt_data' => $request->receipt_data,
                    'validation_status' => 'success',
                    'processed_at' => now()
                ]);
                
                return $subscription;
            });

            // Assign role/package immediately
            if ($subscriptionPack->type === 'role') {
                // VIP role assignment for starter, monthly, yearly
                $duration = $subscriptionPack->interval_type === 'month' ? '1_month' : '1_year';
                $userRole = $user->assignRole('vip', $duration);

                // Link subscription to user role
                if ($userRole) {
                    $userRole->update(['subscription_id' => $subscription->id]);
                }

                $responseMessage = 'iOS subscription successful and VIP access granted';
                $responseRole = 'vip';
            } else {
                // Package assignment for millionaire, billionaire
                $packageType = $subscriptionPack->plan_type;
                $user->assignPackage($packageType);

                $responseMessage = "iOS subscription successful and {$subscriptionPack->getDisplayName()} package activated";
                $responseRole = $packageType;
            }

            // Log successful purchase
            Log::info("iOS IAP subscription successful", [
                'user_id' => $user->id,
                'transaction_id' => $request->transaction_id,
                'plan_type' => $subscriptionPack->plan_type,
                'subscription_id' => $subscription->id,
                'ios_product_id' => $request->ios_product_id
            ]);

            return response()->json([
                'status' => 200,
                'message' => $responseMessage,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => 'active',
                    'plan_type' => $subscriptionPack->plan_type,
                    'expires_at' => $subscription->ends_at,
                    'package_type' => $subscriptionPack->type,
                    'role' => $responseRole,
                    'days_remaining' => $subscription->getDaysRemaining(),
                    'platform' => 'ios',
                    'transaction_verified' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('iOS IAP confirmation error: ' . $e->getMessage(), [
                'user_id' => $request->user_id ?? null,
                'transaction_id' => $request->transaction_id ?? null,
                'ios_product_id' => $request->ios_product_id ?? null
            ]);
            return response()->json([
                'status' => 500,
                'message' => 'Failed to confirm iOS subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Subscription processing failed'
            ], 500);
        }
    }

    /**
     * Validate Apple Receipt with Apple's verifyReceipt API
     */
    private function validateAppleReceipt($receiptData, $transactionId, $productId)
    {
        $sharedSecret = config('services.apple.shared_secret', env('APPLE_SHARED_SECRET'));
        
        if (!$sharedSecret) {
            return [
                'success' => false,
                'message' => 'Apple shared secret not configured'
            ];
        }

        $requestData = [
            'receipt-data' => $receiptData,
            'password' => $sharedSecret,
            'exclude-old-transactions' => false
        ];

        // Try production first
        $response = $this->sendAppleReceiptRequest('https://buy.itunes.apple.com/verifyReceipt', $requestData);
        
        // If status 21007, retry with sandbox
        if (isset($response['status']) && $response['status'] === 21007) {
            Log::info('Retrying with Apple Sandbox environment');
            $response = $this->sendAppleReceiptRequest('https://sandbox.itunes.apple.com/verifyReceipt', $requestData);
        }

        if (!isset($response['status'])) {
            return [
                'success' => false,
                'message' => 'Invalid response from Apple verification servers'
            ];
        }

        // Check response status
        if ($response['status'] !== 0) {
            $errorMessages = [
                21000 => 'The App Store could not read the JSON object you provided.',
                21002 => 'The receipt-data property was malformed or missing.',
                21003 => 'The receipt could not be authenticated.',
                21004 => 'The shared secret you provided does not match the shared secret on file for your account.',
                21005 => 'The receipt server is not currently available.',
                21006 => 'This receipt is valid but the subscription has expired.',
                21007 => 'This receipt is from the sandbox but it was sent to the production environment for verification.',
                21008 => 'This receipt is from the production environment but it was sent to the sandbox environment for verification.',
                21010 => 'This receipt could not be authorized. Treat this the same as if a purchase was never made.'
            ];

            $errorMessage = $errorMessages[$response['status']] ?? 'Unknown Apple verification error: ' . $response['status'];
            
            Log::error('Apple receipt validation failed', [
                'status' => $response['status'],
                'message' => $errorMessage,
                'transaction_id' => $transactionId
            ]);

            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }

        // Verify transaction details
        $receipt = $response['receipt'] ?? [];
        $inAppPurchases = $receipt['in_app'] ?? [];
        
        if (empty($inAppPurchases)) {
            return [
                'success' => false,
                'message' => 'No in-app purchases found in receipt'
            ];
        }

        // Find matching transaction
        $matchingTransaction = null;
        foreach ($inAppPurchases as $purchase) {
            if ($purchase['transaction_id'] === $transactionId && $purchase['product_id'] === $productId) {
                $matchingTransaction = $purchase;
                break;
            }
        }

        if (!$matchingTransaction) {
            Log::warning('Transaction not found in Apple receipt', [
                'expected_transaction_id' => $transactionId,
                'expected_product_id' => $productId,
                'found_transactions' => array_column($inAppPurchases, 'transaction_id')
            ]);
            
            return [
                'success' => false,
                'message' => 'Transaction ID not found in Apple receipt'
            ];
        }

        // Additional validation: Check purchase date is recent (within 24 hours)
        $purchaseTime = (int) ($matchingTransaction['purchase_date_ms'] ?? 0) / 1000;
        $maxAge = 24 * 60 * 60; // 24 hours
        
        if ($purchaseTime > 0 && (time() - $purchaseTime) > $maxAge) {
            return [
                'success' => false,
                'message' => 'Receipt is too old (older than 24 hours)'
            ];
        }

        Log::info('Apple receipt validation successful', [
            'transaction_id' => $transactionId,
            'product_id' => $productId,
            'original_transaction_id' => $matchingTransaction['original_transaction_id'] ?? null
        ]);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'original_transaction_id' => $matchingTransaction['original_transaction_id'] ?? null,
            'product_id' => $matchingTransaction['product_id'],
            'purchase_date' => isset($matchingTransaction['purchase_date_ms']) 
                ? now()->createFromTimestamp((int) $matchingTransaction['purchase_date_ms'] / 1000)
                : now(),
            'quantity' => $matchingTransaction['quantity'] ?? 1
        ];
    }

    /**
     * Send request to Apple's verifyReceipt endpoint
     */
    private function sendAppleReceiptRequest($url, $data)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('Apple receipt verification cURL error: ' . $error);
            return ['status' => -1, 'error' => $error];
        }

        if ($httpCode !== 200) {
            Log::error('Apple receipt verification HTTP error', ['http_code' => $httpCode]);
            return ['status' => -2, 'http_code' => $httpCode];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Apple receipt verification JSON decode error: ' . json_last_error_msg());
            return ['status' => -3, 'json_error' => json_last_error_msg()];
        }

        return $decoded;
    }

    /**
     * Simple iOS subscription confirmation (like Diamond Shop)
     * Bypasses Apple receipt validation for immediate activation
     */
    public function simpleConfirm(Request $request)
    {
        Log::info('SUBSCRIPTION: simpleConfirm called', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        try {
            // Validate input - only need user_id and plan_type
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'plan_type' => 'required|string',
            ]);

            $userId = $request->user_id;
            $planType = $request->plan_type;

            // Get user
            $user = Users::find($userId);
            if (!$user) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found'
                ], 404);
            }

            // Get subscription pack by plan_type (not type)
            $subscriptionPack = SubscriptionPacks::where('plan_type', $planType)->first();
            if (!$subscriptionPack) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Subscription pack not found for plan_type: ' . $planType
                ], 404);
            }

            // Calculate expiration date based on interval_type
            $duration = ($subscriptionPack->interval_type === 'year') ? 365 : 30;
            $expiresAt = now()->addDays($duration);

            DB::beginTransaction();

            try {
                // Create subscription record (like Diamond Shop - direct creation)
                $subscription = Subscription::create([
                    'user_id' => $userId,
                    'stripe_subscription_id' => null, // iOS doesn't use Stripe
                    'payment_intent_id' => 'ios_simple_' . time(), // Simple identifier
                    'plan_type' => $planType,
                    'stripe_price_id' => 'ios_' . $planType,
                    'status' => 'active',
                    'amount' => $subscriptionPack->amount, // Required field from subscription_packs
                    'currency' => $subscriptionPack->currency ?? 'USD', // Required field
                    'starts_at' => now(),
                    'ends_at' => $expiresAt,
                    'metadata' => json_encode([
                        'platform' => 'ios',
                        'plan_type' => $planType,
                        'simple_confirm' => true,
                        'subscription_pack_id' => $subscriptionPack->id,
                        'ios_product_id' => $subscriptionPack->ios_product_id,
                        'created_at' => now()->toISOString()
                    ])
                ]);

                // Assign role/package immediately (like Diamond Shop)
                if ($subscriptionPack->type === 'role') {
                    // VIP role assignment
                    $existingRole = UserRole::where('user_id', $userId)->where('role_type', 'vip')->first();
                    
                    if ($existingRole) {
                        // Extend existing VIP
                        $existingRole->expires_at = $expiresAt;
                        $existingRole->is_active = true;
                        $existingRole->subscription_id = $subscription->id;
                        $existingRole->save();
                    } else {
                        // Create new VIP role
                        UserRole::create([
                            'user_id' => $userId,
                            'role_type' => 'vip',
                            'granted_at' => now(),
                            'expires_at' => $expiresAt,
                            'subscription_id' => $subscription->id,
                            'is_active' => true
                        ]);
                    }
                    
                    Log::info('VIP role assigned/extended', ['user_id' => $userId, 'expires_at' => $expiresAt]);
                } else {
                    // Package assignment (millionaire/billionaire) - use user_packages table
                    $existingPackage = DB::table('user_packages')
                        ->where('user_id', $userId)
                        ->where('package_type', $planType)
                        ->first();
                    
                    if ($existingPackage) {
                        // Extend existing package
                        DB::table('user_packages')
                            ->where('id', $existingPackage->id)
                            ->update([
                                'expires_at' => $expiresAt,
                                'is_active' => true,
                                'updated_at' => now()
                            ]);
                    } else {
                        // Create new package
                        DB::table('user_packages')->insert([
                            'user_id' => $userId,
                            'package_type' => $planType, // 'millionaire' or 'billionaire'
                            'granted_at' => now(),
                            'expires_at' => $expiresAt,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    
                    Log::info('Package assigned/extended', ['user_id' => $userId, 'package_type' => $planType, 'expires_at' => $expiresAt]);
                }

                DB::commit();

                Log::info('iOS subscription activated successfully (simple confirm)', [
                    'user_id' => $userId,
                    'subscription_id' => $subscription->id,
                    'plan_type' => $planType,
                    'expires_at' => $expiresAt
                ]);

                return response()->json([
                    'status' => 200,
                    'message' => 'iOS subscription successful and access granted',
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'status' => 'active',
                        'plan_type' => $planType,
                        'expires_at' => $expiresAt->toISOString(),
                        'package_type' => $subscriptionPack->type,
                        'role' => $subscriptionPack->type === 'role' ? 'vip' : null,
                        'days_remaining' => $duration,
                        'platform' => 'ios',
                        'simple_confirm' => true
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Simple iOS subscription confirmation failed', [
                'user_id' => $request->user_id ?? 'unknown',
                'plan_type' => $request->plan_type ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Failed to process subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}