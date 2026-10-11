<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyPdf extends Model
{
    /** The policy files; each falls back to the platform set on its own (PO-14). */
    public const FILES = ['alcohol_drug_test_policy_pdf', 'general_work_policy_pdf'];

    protected $fillable = [
        'company_id',
        'alcohol_drug_test_policy_pdf',
        'general_work_policy_pdf',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The policies a company's drivers and applicants see: the company's own file for each
     * policy, or the platform one (company_id null) when the company hasn't uploaded it.
     */
    public static function resolveFor(?int $companyId): self
    {
        $platform = self::whereNull('company_id')->first();
        $own = $companyId ? self::where('company_id', $companyId)->first() : null;

        $resolved = new self();
        foreach (self::FILES as $file) {
            $resolved->{$file} = $own?->{$file} ?: $platform?->{$file};
        }

        return $resolved;
    }
}
