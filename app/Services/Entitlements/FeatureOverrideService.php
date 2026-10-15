<?php

namespace App\Services\Entitlements;

use App\Features\Feature;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlatformUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Platform exceptions for one company (05 §6). Append-only: a change revokes the open row and
 * inserts a new one in one transaction, and bumps the company's entitlements_version.
 */
class FeatureOverrideService
{
    public function grant(
        PlatformUser $actor,
        Company $company,
        Feature $feature,
        ?bool $enabled,
        ?int $limit,
        string $reason,
        bool $unlimited = false,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): int {
        if ($unlimited && $limit !== null) {
            throw new InvalidArgumentException('An unlimited override has no limit value.');
        }

        return DB::transaction(function () use ($actor, $company, $feature, $enabled, $limit, $reason, $unlimited, $startsAt, $endsAt) {
            Company::query()->whereKey($company->id)->lockForUpdate()->first();
            $this->revokeOpen($actor, $company, $feature, 'replaced');

            $now = now();
            $id = DB::table('company_feature_overrides')->insertGetId([
                'company_id' => $company->id,
                'feature_key' => $feature->value,
                'enabled' => $enabled,
                'limit_value' => $limit,
                'is_unlimited' => $unlimited,
                'reason' => $reason,
                'starts_at' => $startsAt ?? $now,
                'ends_at' => $endsAt,
                'granted_by_platform_user_id' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $company->increment('entitlements_version');
            AuditLog::record($actor, $company->id, 'feature_override.granted', $company, [
                'override_id' => $id, 'feature' => $feature->value, 'enabled' => $enabled, 'limit_value' => $limit,
                'is_unlimited' => $unlimited, 'ends_at' => $endsAt?->toIso8601String(), 'reason' => $reason,
            ]);

            return $id;
        });
    }

    public function revoke(PlatformUser $actor, Company $company, Feature $feature, string $reason): void
    {
        DB::transaction(function () use ($actor, $company, $feature, $reason) {
            Company::query()->whereKey($company->id)->lockForUpdate()->first();

            if ($this->revokeOpen($actor, $company, $feature, $reason)) {
                $company->increment('entitlements_version');
                AuditLog::record($actor, $company->id, 'feature_override.revoked', $company, ['feature' => $feature->value, 'reason' => $reason]);
            }
        });
    }

    private function revokeOpen(PlatformUser $actor, Company $company, Feature $feature, string $reason): bool
    {
        return DB::table('company_feature_overrides')
            ->where('company_id', $company->id)
            ->where('feature_key', $feature->value)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoked_by_platform_user_id' => $actor->id,
                'revoke_reason' => $reason,
                'updated_at' => now(),
            ]) > 0;
    }
}
