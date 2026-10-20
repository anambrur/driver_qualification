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
        // Set when a Platform Owner edits a built-in platform role; PlatformRoleSeeder then keeps the edit
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->timestamp('customized_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropColumn('customized_at');
        });
    }
};
