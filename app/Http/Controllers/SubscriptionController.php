<?php

namespace App\Http\Controllers;

use App\Models\Users;
use App\Models\Subscription;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController extends Controller
{
    /**
     * Get available subscription plans for user
     */
    public function getPlans(Request $request)
    {
        Log::info('SUBSCRIPTION: getPlans called', [
            'request_data' => $request->all(),
            'headers' => $request->headers->all()
        ]);
        
        try {
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
                'message' => 'Subscription plans retrieved successfully',
                'data' => $plans
            ]);
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
}