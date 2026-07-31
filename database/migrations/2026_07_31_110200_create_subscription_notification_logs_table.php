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
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
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
