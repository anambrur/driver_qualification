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
        Schema::create('plan_features', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 64);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();

            $table->primary(['plan_id', 'feature_key']);
            $table->foreign('feature_key')->references('key')->on('features')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
