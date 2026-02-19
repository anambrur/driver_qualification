<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', [
                'trial',
                'active',
                'grace',       // expired but within grace period
                'expired',
                'cancelled',
                'suspended',
            ])->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();      // null = lifetime
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable(); // extra days after expiry
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_renewed_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('payment_method')->nullable();  // stripe, manual, paypal
            $table->string('external_subscription_id')->nullable(); // Stripe sub ID
            $table->json('metadata')->nullable();           // custom data
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['ends_at', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
