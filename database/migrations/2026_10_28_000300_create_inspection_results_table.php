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
        // 08 §9: one answer per template item. item_label keeps the wording the driver saw.
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_item_id')->constrained('inspection_template_items')->restrictOnDelete();
            $table->string('item_label');
            $table->enum('result', ['pass', 'fail', 'na']);
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'template_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};
