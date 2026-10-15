<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A support session (04 §4): the only way platform staff reach one company's data. Approved by
 * that company's Owner and by a second Platform Owner. Platform-owned, so no tenant scope; the
 * company sees its own sessions through SupportSessionService.
 *
 * @property int $id
 * @property int $company_id
 * @property int $platform_user_id
 * @property string $scope
 * @property string $status
 * @property int|null $tenant_approved_by_user_id
 * @property int|null $platform_approved_by_platform_user_id
 * @property Carbon|null $tenant_approved_at
 * @property Carbon|null $platform_approved_at
 * @property Carbon $requested_at
 * @property Carbon|null $started_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $ended_at
 * @property string|null $end_reason
 */
class PlatformSupportSession extends Model
{
    /** Requested and approved-but-unstarted sessions expire after this many hours [A]. */
    public const REQUEST_WINDOW_HOURS = 24;

    public const SCOPES = ['read', 'read_write'];

    /** Set by SupportSessionService only, never mass-assigned from a request. */
    protected $fillable = [
        'company_id',
        'platform_user_id',
        'reason',
        'scope',
        'status',
        'tenant_approved_by_user_id',
        'tenant_approved_at',
        'platform_approved_by_platform_user_id',
        'platform_approved_at',
        'rejected_by_type',
        'rejected_by_id',
        'rejection_reason',
        'requested_at',
        'started_at',
        'expires_at',
        'ended_at',
        'end_reason',
        'revoked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tenant_approved_at' => 'datetime',
            'platform_approved_at' => 'datetime',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<PlatformUser, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'platform_user_id');
    }

    /**
     * @return BelongsTo<PlatformUser, $this>
     */
    public function platformApprover(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'platform_approved_by_platform_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tenantApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_approved_by_user_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Requested or approved, not yet started, and still inside the request window.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['requested', 'approved'])
            ->where('requested_at', '>', now()->subHours(self::REQUEST_WINDOW_HOURS));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'approved'], true)
            && $this->requested_at->gt(now()->subHours(self::REQUEST_WINDOW_HOURS));
    }

    public function supportRoleKey(): string
    {
        return $this->scope === 'read_write' ? 'support_read_write' : 'support_read';
    }
}
