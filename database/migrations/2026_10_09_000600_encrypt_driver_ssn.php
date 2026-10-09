<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Driver::$casts now encrypts `ssn` (DRV-02). Encrypt the SSNs stored before that in plain
 * text. Values that already decrypt are left alone, so running it twice is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite(fn (string $ssn) => $this->isEncrypted($ssn) ? null : Crypt::encryptString($ssn));
    }

    public function down(): void
    {
        $this->rewrite(fn (string $ssn) => $this->isEncrypted($ssn) ? Crypt::decryptString($ssn) : null);
    }

    /**
     * @param  callable(string): ?string  $transform  returns the new value, or null to skip the row
     */
    private function rewrite(callable $transform): void
    {
        DB::table('drivers')
            ->whereNotNull('ssn')
            ->where('ssn', '!=', '')
            ->orderBy('id')
            ->select(['id', 'ssn'])
            ->chunkById(500, function ($rows) use ($transform) {
                foreach ($rows as $row) {
                    $value = $transform($row->ssn);
                    if ($value !== null) {
                        DB::table('drivers')->where('id', $row->id)->update(['ssn' => $value]);
                    }
                }
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
