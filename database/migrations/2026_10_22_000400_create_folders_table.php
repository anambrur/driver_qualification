<?php

use App\Support\CheckConstraints;
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
        // 08 §8. Every reference is a same-company composite FK with RESTRICT: the bindings are in
        // a CHECK and parent_id is the base of an indexed generated column. trip_id gets its FK in
        // Phase 2 from a MySQL-only migration (altering this table on SQLite drops the CHECK).
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 120);
            $table->string('system_key', 32)->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('trailer_id')->nullable();
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Sibling names are unique. Root folders share key 0 (MySQL allows repeated NULLs);
            // a deleted folder leaves the key, so its name can be used again.
            $table->unsignedBigInteger('parent_key')->nullable()
                ->storedAs('CASE WHEN deleted_at IS NOT NULL THEN NULL WHEN parent_id IS NULL THEN 0 ELSE parent_id END');

            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'parent_key', 'name']);
            $table->index(['company_id', 'system_key']);
            $table->foreign(['company_id', 'parent_id'])->references(['company_id', 'id'])->on('folders')->restrictOnDelete();
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['company_id', 'vehicle_id'])->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
            $table->foreign(['company_id', 'trailer_id'])->references(['company_id', 'id'])->on('trailers')->restrictOnDelete();
        });

        CheckConstraints::add('folders', [
            'folders_one_binding_check' => '(driver_id IS NOT NULL) + (vehicle_id IS NOT NULL) + (trailer_id IS NOT NULL) + (trip_id IS NOT NULL) <= 1',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
