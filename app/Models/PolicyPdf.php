<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyPdf extends Model
{
    /** The company's policy files (stored_files ids). */
    public const FILES = ['alcohol_drug_test_policy_file_id', 'general_work_policy_file_id'];

    protected $fillable = [
        'company_id',
        'alcohol_drug_test_policy_file_id',
        'general_work_policy_file_id',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The company's own policy files. The platform defaults moved to site_settings (F12); the
     * per-file fallback to them returns with the file-serving rewrite (WP9).
     */
    public static function resolveFor(?int $companyId): self
    {
        return ($companyId ? self::where('company_id', $companyId)->first() : null) ?? new self;
    }
}
