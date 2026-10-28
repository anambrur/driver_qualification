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
        // 08 §8: the trip event and fuel purchase subjects of documents exist since P1-4 inside the
        // one-subject CHECK. MySQL only: an alter on SQLite rebuilds the table and drops it.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign(['company_id', 'trip_event_id'])->references(['company_id', 'id'])->on('trip_events')->restrictOnDelete();
            $table->foreign(['company_id', 'fuel_purchase_id'])->references(['company_id', 'id'])->on('fuel_purchases')->restrictOnDelete();
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
            $table->dropForeign(['company_id', 'trip_event_id']);
            $table->dropForeign(['company_id', 'fuel_purchase_id']);
        });
    }
};
