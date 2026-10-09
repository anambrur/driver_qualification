<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company opt-out of global document types. A row means the company has switched
 * that type off: it no longer counts towards the company's compliance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_document_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_document_type');
    }
};
