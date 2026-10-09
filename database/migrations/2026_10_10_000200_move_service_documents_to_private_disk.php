<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * MNT-05: service documents (service_documents.file_path) moved from the public disk (readable at
 * /storage/... without login) to the private `local` disk. Paths in the database stay the same;
 * only the disk changes. Missing files are skipped, and files already moved are left alone, so the
 * migration can be re-run.
 */
return new class extends Migration
{
    private const TABLES = ['service_documents'];

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
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        foreach (self::TABLES as $table) {
            $paths = DB::table($table)->whereNotNull('file_path')->distinct()->pluck('file_path');

            foreach ($paths->filter() as $path) {
                if (! $source->exists($path) || $target->exists($path)) {
                    continue;
                }

                if ($target->writeStream($path, $source->readStream($path))) {
                    $source->delete($path);
                }
            }
        }
    }
};
