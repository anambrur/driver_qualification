<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // On SQLite this FK is declared in create_companies_table (see the note there).
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign(['id', 'owner_user_id'])->references(['company_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['id', 'owner_user_id']);
        });
    }
};
