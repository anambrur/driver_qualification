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
        Schema::create('trailer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trailer_id');
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('file_id');
            $table->date('file_date')->nullable();
            $table->date('expiry_date');
            $table->string('description')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trailer_id', 'document_type_id', 'expiry_date']);
            $table->index(['company_id', 'expiry_date']);
            $table->foreign(['company_id', 'trailer_id'])->references(['company_id', 'id'])->on('trailers')->cascadeOnDelete();
            $table->foreign(['company_id', 'file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trailer_documents');
    }
};
