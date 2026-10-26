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
        // 08 §8: the trip binding of folders and the trip subject of documents exist since P1-4
        // inside their CHECKs. MySQL only: an alter on SQLite rebuilds the table and drops them.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('folders', function (Blueprint $table) {
            $table->foreign(['company_id', 'trip_id'])->references(['company_id', 'id'])->on('trips')->restrictOnDelete();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign(['company_id', 'trip_id'])->references(['company_id', 'id'])->on('trips')->restrictOnDelete();
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

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['company_id', 'trip_id']);
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropForeign(['company_id', 'trip_id']);
        });
    }
};
