<?php

use App\Support\CheckConstraints;
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
        // Append-only exception history (05 §6). A change revokes the open row and inserts a new one.
        Schema::create('company_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('feature_key', 64);
            $table->boolean('enabled')->nullable(); // null = inherit from the plan
            $table->unsignedInteger('limit_value')->nullable(); // null = inherit
            $table->boolean('is_unlimited')->default(false);
            $table->text('reason');
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('granted_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->text('revoke_reason')->nullable();
            $table->unsignedTinyInteger('open_flag')->nullable()->storedAs('CASE WHEN revoked_at IS NULL THEN 1 END');
            $table->timestamps();

            $table->unique(['company_id', 'feature_key', 'open_flag'], 'cfo_company_feature_open_unique');
            $table->foreign('feature_key')->references('key')->on('features')->restrictOnDelete();
        });

        CheckConstraints::add('company_feature_overrides', [
            'company_feature_overrides_unlimited_check' => 'NOT (is_unlimited AND limit_value IS NOT NULL)',
            'company_feature_overrides_period_check' => 'ends_at IS NULL OR ends_at > starts_at',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_feature_overrides');
    }
};
