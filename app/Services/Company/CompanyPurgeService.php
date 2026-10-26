<?php

namespace App\Services\Company;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Hard delete of a deactivated company once every retention period has passed (08 §15, 10 §11).
 * Children go in FK order in one transaction; stored objects are deleted after it commits.
 * Audit logs have no FK and are kept: they outlive what they describe (08 §10).
 */
class CompanyPurgeService
{
    /**
     * Every table with a company_id column. A new tenant table must be added to purge() and here
     * (CompanyPurgeTest compares this list with the schema).
     */
    public const COMPANY_TABLES = [
        'asset_groups', 'audit_logs', 'company_document_type', 'company_feature_overrides', 'company_invitations',
        'company_retained_features', 'company_settings', 'documents', 'driver_compliance_documents', 'drivers',
        'exports', 'folders', 'maintenance_schedules', 'payments', 'platform_support_sessions', 'policy_pdfs',
        'service_documents', 'service_logs', 'shippers', 'stored_files', 'subscription_notification_logs', 'subscriptions',
        'trailer_documents', 'trailers', 'trip_number_sequences', 'users', 'vehicle_documents', 'vehicles',
    ];

    /**
     * Why the company can't be purged yet; empty when it can.
     *
     * @return list<string>
     */
    public function blockers(Company $company, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        if ($company->status !== 'deactivated' || $company->status_changed_at === null) {
            return ['The company is not deactivated.'];
        }

        $blockers = [];

        $openSubscription = DB::table('subscriptions')->where('company_id', $company->id)->whereNull('ended_at')
            ->whereNotIn('status', ['canceled', 'incomplete_expired'])->exists();
        if ($openSubscription) {
            $blockers[] = 'The company still has an open subscription. Cancel it in Stripe first.';
        }

        $until = $this->retainedUntil($company);
        if ($until !== null && $now->lt($until)) {
            $blockers[] = 'Records must be kept until '.$until->toDateString().' (08 §15).';
        }

        return $blockers;
    }

    /**
     * The end of the longest retention period that applies to what the company holds.
     */
    public function retainedUntil(Company $company): ?CarbonImmutable
    {
        $closed = CarbonImmutable::parse($company->status_changed_at);
        $owns = fn (string $table) => DB::table($table)->where('company_id', $company->id)->exists();
        $latest = function (string $table, string $column) use ($company): ?CarbonImmutable {
            $value = DB::table($table)->where('company_id', $company->id)->max($column);

            return $value === null ? null : CarbonImmutable::parse($value);
        };

        $dates = [];

        if ($owns('drivers')) {
            $dates[] = $closed->addYears((int) config('retention.dq_file_years'));
        }

        if ($owns('vehicles') || $owns('trailers')) {
            $dates[] = $closed->addMonths((int) config('retention.maintenance_months_after_exit'));
        }

        if ($lastService = $latest('service_logs', 'service_date')) {
            $dates[] = $lastService->addYears((int) config('retention.maintenance_years'));
        }

        if ($lastPayment = $latest('payments', 'created_at')) {
            $dates[] = $lastPayment->addYears((int) config('retention.financial_years'));
        }

        return $dates === [] ? null : max($dates);
    }

    /**
     * Row counts per table that purge() would delete (users and drivers include soft-deleted rows).
     *
     * @return array<string, int>
     */
    public function counts(Company $company): array
    {
        $counts = [];
        foreach (self::COMPANY_TABLES as $table) {
            if ($table !== 'audit_logs') {
                $counts[$table] = DB::table($table)->where('company_id', $company->id)->count();
            }
        }

        return array_filter($counts);
    }

    /**
     * @return array<string, int> deleted rows per table
     */
    public function purge(Company $company, ?PlatformUser $actor = null, ?string $reason = null): array
    {
        $files = [];
        $logo = $company->logo_path;

        $deleted = DB::transaction(function () use ($company, $actor, $reason, &$files) {
            $locked = Company::query()->lockForUpdate()->findOrFail($company->id);
            if ($blockers = $this->blockers($locked)) {
                throw new RuntimeException('The company cannot be purged: '.implode(' ', $blockers));
            }

            $id = $locked->id;
            $of = fn (string $table) => DB::table($table)->where('company_id', $id);
            $userIds = $of('users')->pluck('id')->all();
            $driverIds = $of('drivers')->pluck('id')->all();
            $files = $of('stored_files')->get(['disk', 'path'])->groupBy('disk')->map->pluck('path')->all();
            $deleted = [];

            DB::table('companies')->where('id', $id)->update(['owner_user_id' => null]);

            // Platform records about the company
            $deleted['platform_support_sessions'] = $of('platform_support_sessions')->delete();
            $deleted['asset_transfers'] = DB::table('asset_transfers')
                ->where(fn ($q) => $q->where('source_company_id', $id)->orWhere('target_company_id', $id))->delete();
            $deleted['company_feature_overrides'] = $of('company_feature_overrides')->delete();

            // Documents, folders (children before parents) and files' other users
            $deleted['documents'] = $of('documents')->delete();
            $deleted['folders'] = 0;
            while ($leaves = $of('folders')->whereNotIn('id', $of('folders')->whereNotNull('parent_id')->select('parent_id'))->pluck('id')->all()) {
                $deleted['folders'] += DB::table('folders')->whereIn('id', $leaves)->delete();
            }
            $deleted['exports'] = $of('exports')->delete();
            $deleted['policy_pdfs'] = $of('policy_pdfs')->delete();

            // Operations
            $deleted['shippers'] = $of('shippers')->delete();

            // Fleet and maintenance (service documents and categories cascade from the logs)
            $deleted['asset_groups'] = $of('asset_groups')->delete();
            $deleted['service_logs'] = $of('service_logs')->delete();
            $deleted['maintenance_schedules'] = $of('maintenance_schedules')->delete();
            $deleted['vehicles'] = $of('vehicles')->delete();
            $deleted['trailers'] = $of('trailers')->delete();

            // Drivers: licences are RESTRICT (generated column); the other DQ tables cascade
            $deleted['company_invitations'] = $of('company_invitations')->delete();
            $deleted['licenses'] = DB::table('licenses')->whereIn('driver_id', $driverIds)->delete();
            $deleted['drivers'] = $of('drivers')->delete();

            // Billing
            $deleted['payments'] = $of('payments')->delete();
            $deleted['subscription_notification_logs'] = $of('subscription_notification_logs')->delete();
            $deleted['subscriptions'] = $of('subscriptions')->delete();

            // A converted PDF points at its image (RESTRICT), so it goes first
            $deleted['stored_files'] = $of('stored_files')->whereNotNull('converted_from_file_id')->delete()
                + $of('stored_files')->delete();

            // Roles live in the company's team without an FK (06 §5); assignments cascade
            $deleted['roles'] = DB::table('roles')->where('team_id', $id)->delete();
            DB::table('model_has_permissions')->where('team_id', $id)->delete();

            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $userIds)->delete();
            DB::table('password_reset_tokens')->whereIn('email', $of('users')->select('email'))->delete();
            $deleted['users'] = $of('users')->delete();

            // Settings, sequences and opt-outs cascade with the company
            DB::table('companies')->where('id', $id)->delete();

            $deleted = array_filter($deleted);
            $actor === null
                ? AuditLog::recordSystem($id, 'company.purged', null, $deleted)
                : AuditLog::record($actor, $id, 'company.purged', null, $deleted + ['reason' => $reason]);

            return $deleted;
        });

        $this->deleteObjects($company->id, $files, $logo);

        return $deleted;
    }

    /**
     * After commit: a failed delete leaves an orphan object, which is reported, not thrown.
     *
     * @param  array<string, Collection<int, string>>  $files
     */
    private function deleteObjects(int $companyId, array $files, ?string $logo): void
    {
        try {
            foreach ($files as $disk => $paths) {
                foreach ($paths->chunk(500) as $chunk) {
                    Storage::disk($disk)->delete($chunk->all());
                }
                Storage::disk($disk)->deleteDirectory("companies/{$companyId}");
            }

            if ($logo) {
                Storage::disk('public')->delete($logo);
            }
        } catch (Throwable $e) {
            Log::error("Company {$companyId} purged, but its stored objects were not all deleted: {$e->getMessage()}");
        }
    }
}
