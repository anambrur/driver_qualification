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
        // Owned by the company (07 §2). Price and amount are snapshots of what was charged.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('plan_price_id')->constrained()->restrictOnDelete();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_price_id')->nullable();
            $table->enum('interval', ['month', 'year']);
            $table->decimal('amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->enum('status', ['trialing', 'active', 'past_due', 'unpaid', 'canceled', 'incomplete', 'incomplete_expired'])->default('incomplete');
            $table->enum('source', ['stripe', 'trial', 'admin'])->default('stripe');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('ended_at')->nullable(); // set when finished; ended rows are history
            $table->timestamp('last_stripe_event_at')->nullable(); // ordering guard
            $table->boolean('needs_review')->default(false);
            $table->unsignedTinyInteger('open_flag')->nullable()->storedAs('CASE WHEN ended_at IS NULL THEN 1 END');
            $table->timestamps();

            // At most one open subscription per company (07 §4.1).
            $table->unique(['company_id', 'open_flag']);
            $table->index(['company_id', 'status']);
            $table->index(['status', 'ends_at']);
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
