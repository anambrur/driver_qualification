<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CMP-04: companies.slug drives the public /{slug}/apply URL but had no unique index, so two
 * companies could share a slug and applications went to whichever row came first. The column
 * becomes a bounded string (TEXT can't carry a plain unique index on MySQL) with a unique index.
 *
 * Existing data is never rewritten: if duplicate slugs already exist the migration stops and
 * lists them so they can be fixed by hand first.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('companies')
            ->select('slug')
            ->whereNotNull('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Duplicate company slugs must be resolved before adding the unique index: '.$duplicates->implode(', '));
        }

        $tooLong = DB::table('companies')->whereRaw('LENGTH(slug) > 300')->count();

        if ($tooLong > 0) {
            throw new RuntimeException("{$tooLong} company slug(s) are longer than 300 characters; shorten them before running this migration.");
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug', 300)->nullable()->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->text('slug')->nullable()->change();
        });
    }
};
