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
        // 08 §12: every export type now; Phase 1 builds zip_driver and zip_company (req 2.5)
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['zip_driver', 'zip_trip', 'zip_company', 'year_end_report', 'profit_loss_report', 'payroll_summary', 'accident_register']);
            $table->json('parameters');
            $table->enum('status', ['queued', 'running', 'ready', 'failed', 'expired'])->default('queued');
            $table->unsignedBigInteger('file_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'status', 'created_at']);
            $table->foreign(['company_id', 'file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
