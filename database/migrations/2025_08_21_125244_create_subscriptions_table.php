<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id'); // Link to users table
            $table->string('stripe_subscription_id')->nullable(); // Stripe subscription ID
            $table->string('stripe_customer_id')->nullable(); // Stripe customer ID
            $table->enum('plan_type', ['monthly', 'yearly']); // Subscription plan type
            $table->string('stripe_price_id'); // Stripe price ID
            $table->enum('status', ['active', 'canceled', 'expired', 'incomplete'])->default('incomplete');
            $table->decimal('amount', 8, 2); // Subscription amount
            $table->string('currency', 3)->default('USD'); // Currency
            $table->timestamp('starts_at')->nullable(); // Subscription start date
            $table->timestamp('ends_at')->nullable(); // Subscription end date
            $table->timestamp('canceled_at')->nullable(); // Cancellation date
            $table->json('metadata')->nullable(); // Additional metadata from Stripe
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('stripe_subscription_id');
            $table->index('stripe_customer_id');
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
}
