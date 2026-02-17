<?php
// database/migrations/2024_01_01_000002_create_service_log_category_table.php

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
        Schema::create('service_log_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_log_id')->constrained()->onDelete('cascade');
            $table->foreignId('maintenance_category_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate entries
            $table->unique(['service_log_id', 'maintenance_category_id'], 'service_log_category_unique');

            // Indexes
            $table->index('service_log_id');
            $table->index('maintenance_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_log_category');
    }
};
