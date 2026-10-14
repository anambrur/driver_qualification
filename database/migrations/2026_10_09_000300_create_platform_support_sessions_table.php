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
        // Approved by the company Owner and a second Platform Owner (Q1, 04 §4.2).
        // FKs on the CHECK columns are RESTRICT: MySQL forbids referential actions on them.
        Schema::create('platform_support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('platform_user_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->enum('scope', ['read', 'read_write']);
            $table->enum('status', ['requested', 'approved', 'rejected', 'expired', 'active', 'ended'])->default('requested');
            $table->foreignId('tenant_approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('tenant_approved_at')->nullable();
            $table->foreignId('platform_approved_by_platform_user_id')->nullable()->constrained('platform_users', 'id', 'pss_platform_approver_foreign')->restrictOnDelete();
            $table->timestamp('platform_approved_at')->nullable();
            $table->enum('rejected_by_type', ['user', 'platform_user'])->nullable();
            $table->unsignedBigInteger('rejected_by_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['platform_user_id', 'status']);
        });

        CheckConstraints::add('platform_support_sessions', [
            'platform_support_sessions_approvers_check' => "status NOT IN ('approved', 'active', 'ended') OR (tenant_approved_by_user_id IS NOT NULL AND platform_approved_by_platform_user_id IS NOT NULL)",
            'platform_support_sessions_four_eyes_check' => 'platform_approved_by_platform_user_id IS NULL OR platform_approved_by_platform_user_id <> platform_user_id',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_support_sessions');
    }
};
