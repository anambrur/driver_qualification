<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyPdf extends Model
{
    /** The company's policy files (stored_files ids). */
    public const FILES = ['alcohol_drug_test_policy_file_id', 'general_work_policy_file_id'];

    /** The policies a company can upload, each a {policy}_policy_file_id column. */
    public const POLICIES = ['alcohol_drug_test', 'general_work'];

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
     * The company's own policy files. The platform defaults moved to site_settings (F12); they
     * have no upload screen yet, so there is no fallback to them (PENDING).
     */
    public static function resolveFor(?int $companyId): self
    {
        return ($companyId ? self::where('company_id', $companyId)->first() : null) ?? new self;
    }

    public function fileFor(string $policy): ?StoredFile
    {
        abort_unless(in_array($policy, self::POLICIES, true), 404);

        $fileId = $this->getAttribute("{$policy}_policy_file_id");

        return $fileId === null ? null : StoredFile::query()->find($fileId);
    }
}
