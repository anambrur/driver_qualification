<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // paid|failed|refunded|pending
            $table->string('billing_reason')->nullable();
            $table->string('hosted_invoice_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
