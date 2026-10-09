<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * DRV-03: driver photos, licences, medical cards and forfeiture documents moved from the
 * public disk (readable at /storage/... without login) to the private `local` disk. Paths in
 * the database stay the same; only the disk changes. Missing files are skipped, and files
 * already moved are left alone, so the migration can be re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move('public', 'local');
    }

    public function down(): void
    {
        $this->move('local', 'public');
    }

    private function move(string $from, string $to): void
    {
        $paths = DB::table('drivers')->whereNotNull('photo')->pluck('photo');

        foreach (['license_front', 'license_back', 'medical_card', 'forfeiture_document'] as $column) {
            $paths = $paths->merge(DB::table('driver_documents')->whereNotNull($column)->pluck($column));
        }

        foreach ($paths->filter()->unique() as $path) {
            $source = Storage::disk($from);
            $target = Storage::disk($to);

            if (! $source->exists($path) || $target->exists($path)) {
                continue;
            }

            if ($target->writeStream($path, $source->readStream($path))) {
                $source->delete($path);
            }
        }
    }
};
