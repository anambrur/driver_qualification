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
        // "Schedule a demo" requests from the homepage
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email');
            $table->string('company', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('handled_at')->nullable()->index();
            $table->foreignId('handled_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
