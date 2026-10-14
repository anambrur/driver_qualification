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
        // File metadata lives in stored_files (F13d).
        Schema::create('service_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('service_log_id');
            $table->unsignedBigInteger('file_id');
            $table->timestamps();

            $table->foreign(['company_id', 'service_log_id'])->references(['company_id', 'id'])->on('service_logs')->cascadeOnDelete();
            $table->foreign(['company_id', 'file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_documents');
    }
};
