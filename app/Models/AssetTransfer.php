<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A platform Asset Transfer (PO-08, 04 §7). Platform-owned: it names two companies, so it has
 * no tenant scope and is only read from /platform.
 */
class AssetTransfer extends Model
{
    protected $fillable = [
        'asset_type',
        'source_company_id',
        'target_company_id',
        'source_vehicle_id',
        'target_vehicle_id',
        'source_trailer_id',
        'target_trailer_id',
        'reason',
        'status',
        'copied_counts',
        'bytes_copied',
        'performed_by_platform_user_id',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'copied_counts' => 'array',
            'bytes_copied' => 'integer',
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function sourceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'source_company_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function targetCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'target_company_id');
    }

    /**
     * @return BelongsTo<PlatformUser, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'performed_by_platform_user_id');
    }

    /**
     * Whether this vehicle or trailer left its company through a transfer. Its history stays
     * read-only there, so it can't be restored.
     */
    public static function retired(string $assetType, int $assetId): bool
    {
        return self::query()
            ->where('status', 'completed')
            ->where("source_{$assetType}_id", $assetId)
            ->exists();
    }
}
