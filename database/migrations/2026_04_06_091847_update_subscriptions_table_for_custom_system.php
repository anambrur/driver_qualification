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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('user_id');
            $table->string('status')->index()->nullable()->after('plan_id');
            $table->timestamp('starts_at')->nullable()->after('status');
            $table->timestamp('last_renewed_at')->nullable()->after('starts_at');
            $table->timestamp('grace_ends_at')->nullable()->after('ends_at');
            $table->timestamp('cancelled_at')->nullable()->after('grace_ends_at');
            $table->boolean('auto_renew')->default(false)->after('cancelled_at');
            $table->string('payment_method')->nullable()->after('auto_renew');
            $table->string('external_subscription_id')->nullable()->after('payment_method');
            $table->json('metadata')->nullable()->after('external_subscription_id');
            $table->softDeletes()->after('updated_at');

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'plan_id',
                'status',
                'starts_at',
                'last_renewed_at',
                'grace_ends_at',
                'cancelled_at',
                'auto_renew',
                'payment_method',
                'external_subscription_id',
                'metadata',
                'deleted_at',
            ]);
        });
    }
};
