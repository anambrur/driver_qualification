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
        Schema::create('driver_compliance_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('driver_id');
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('file_id');
            $table->date('file_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['driver_id', 'document_type_id', 'expiry_date'], 'dcd_driver_type_expiry_index');
            $table->index(['company_id', 'expiry_date']);
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->cascadeOnDelete();
            $table->foreign(['company_id', 'file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_compliance_documents');
    }
};
