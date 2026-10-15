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
        // Modules a company lost through a plan change: read-only and exportable (Q3, 05 §7.2).
        Schema::create('company_retained_features', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 64);
            $table->timestamp('retained_at')->useCurrent();

            $table->primary(['company_id', 'feature_key']);
            $table->foreign('feature_key')->references('key')->on('features')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_retained_features');
    }
};
