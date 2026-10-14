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
        Schema::create('features', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->string('name');
            $table->string('group');
            $table->enum('kind', ['module', 'limit']);
            $table->string('unit')->nullable();
            $table->enum('availability', ['general', 'override_only', 'disabled'])->default('general');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
