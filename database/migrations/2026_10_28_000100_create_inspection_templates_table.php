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
        // 08 §9 (req 5.1): platform-managed checklists (LKP-01). A draft has no published_at; a
        // version that an inspection used is never edited, changes go into a new version. One
        // active published pre_trip version at a time (kept by InspectionTemplateService).
        Schema::create('inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('inspection_type', ['pre_trip'])->default('pre_trip');
            $table->unsignedSmallInteger('version');
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
        });

        Schema::create('inspection_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('inspection_templates')->cascadeOnDelete();
            $table->string('section', 100);
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['template_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_template_items');
        Schema::dropIfExists('inspection_templates');
    }
};
