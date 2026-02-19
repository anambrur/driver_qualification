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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // Basic, Pro, Enterprise
            $table->string('slug')->unique();              // basic, pro, enterprise
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);              // 0.00 for free
            $table->string('currency', 3)->default('USD');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime', 'trial']);
            $table->integer('duration_days');              // 30, 365, 0 (lifetime), 14 (trial)
            $table->integer('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('max_users')->nullable();      // null = unlimited
            $table->json('features')->nullable();          // ["feature1", "feature2"]
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
