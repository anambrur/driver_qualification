<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applicants can withdraw their public application: status `withdrawn` + when it happened.
 */
return new class extends Migration
{
    private const STATUSES = ['draft', 'active', 'inactive', 'pending', 'submitted', 'under_review', 'approved', 'rejected'];

    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->enum('status', [...self::STATUSES, 'withdrawn'])->default('draft')->change();
            $table->timestamp('withdrawn_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        // The narrower enum can't hold `withdrawn`; those applications become `inactive`.
        DB::table('drivers')->where('status', 'withdrawn')->update(['status' => 'inactive']);

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('withdrawn_at');
            $table->enum('status', self::STATUSES)->default('draft')->change();
        });
    }
};
