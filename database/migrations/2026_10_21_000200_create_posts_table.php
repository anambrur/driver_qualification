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
        // Public blog posts; a null published_at is a draft
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('excerpt', 300)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('cover_image')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
