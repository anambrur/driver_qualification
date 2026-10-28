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
        // 08 §8: the inspection subject of documents exists since P1-4 inside the one-subject
        // CHECK. MySQL only: an alter on SQLite rebuilds the table and drops it.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign(['company_id', 'inspection_id'])->references(['company_id', 'id'])->on('inspections')->restrictOnDelete();
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
            $table->dropForeign(['company_id', 'inspection_id']);
        });
    }
};
