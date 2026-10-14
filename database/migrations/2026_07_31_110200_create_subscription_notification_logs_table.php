<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // expiring_soon|expired
            $table->string('channel'); // mail|database|sms
            $table->unsignedTinyInteger('days_before');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(
                ['subscription_id', 'type', 'channel', 'days_before'],
                'sub_notif_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_notification_logs');
    }
};
