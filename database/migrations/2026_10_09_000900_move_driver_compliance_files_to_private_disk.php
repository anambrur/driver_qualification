<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * DCMP-01: driver compliance documents moved from the public disk (readable at /storage/...
 * without login) to the private `local` disk. Paths in the database stay the same; only the
 * disk changes. "Upload to all" rows share one path, so each file is moved once. Missing files
 * are skipped, and files already moved are left alone, so the migration can be re-run.
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
        $paths = DB::table('driver_compliance_documents')->whereNotNull('file_path')->distinct()->pluck('file_path');

        foreach ($paths->filter() as $path) {
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
