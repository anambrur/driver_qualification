<?php

namespace App\Authorization;

/**
 * Every permission the app knows (06 §5 tenant, 04 §3 platform). PermissionSeeder syncs these
 * rows; nothing else creates permissions.
 */
final class PermissionCatalogue
{
    /**
     * Guard `web`. Name => [feature_key (null = core), is_write, self_scoped].
     *
     * @var array<string, array{0: string|null, 1: bool, 2: bool}>
     */
    public const TENANT = [
        'company.view' => [null, false, false],
        'company.edit' => [null, true, false],
        'users.view' => [null, false, false],
        'users.create' => [null, true, false],
        'users.edit' => [null, true, false],
        'users.deactivate' => [null, true, false],
        'billing.view' => [null, false, false],
        'billing.manage' => [null, true, false],
        'document-types.toggle' => [null, true, false],

        'roles.view' => ['custom_roles', false, false],
        'roles.create' => ['custom_roles', true, false],
        'roles.edit' => ['custom_roles', true, false],
        'roles.delete' => ['custom_roles', true, false],
        'permissions.grant' => ['custom_roles', true, false],

        'drivers.view' => ['drivers', false, false],
        'drivers.create' => ['drivers', true, false],
        'drivers.edit' => ['drivers', true, false],
        'drivers.delete' => ['drivers', true, false],
        'drivers.hire' => ['drivers', true, false],

        'drivers.dashboard' => ['driver_compliance', false, false],
        'driver-documents.upload' => ['driver_compliance', true, false],
        'driver-documents.delete' => ['driver_compliance', true, false],

        'vehicles.view' => ['fleet', false, false],
        'vehicles.create' => ['fleet', true, false],
        'vehicles.edit' => ['fleet', true, false],
        'vehicles.delete' => ['fleet', true, false],
        'trailers.view' => ['fleet', false, false],
        'trailers.create' => ['fleet', true, false],
        'trailers.edit' => ['fleet', true, false],
        'trailers.delete' => ['fleet', true, false],
        'asset-groups.view' => ['fleet', false, false],
        'asset-groups.create' => ['fleet', true, false],
        'asset-groups.edit' => ['fleet', true, false],
        'asset-groups.delete' => ['fleet', true, false],

        'fleets.dashboard' => ['fleet_compliance', false, false],
        'fleets.edit' => ['fleet_compliance', true, false],

        'maintenance.view' => ['maintenance', false, false],
        'maintenance.create' => ['maintenance', true, false],
        'maintenance.edit' => ['maintenance', true, false],
        'maintenance.delete' => ['maintenance', true, false],
        'scheduled.view' => ['maintenance', false, false],
        'scheduled.create' => ['maintenance', true, false],
        'scheduled.edit' => ['maintenance', true, false],
        'scheduled.delete' => ['maintenance', true, false],

        'reminders.send' => ['compliance_reminders', true, false],

        'folders.view' => ['document_folders', false, false],
        'folders.manage' => ['document_folders', true, false],
        'documents.upload' => ['document_folders', true, false],
        'documents.delete' => ['document_folders', true, false],

        'exports.create' => ['zip_export', false, false],
        'exports.download' => ['zip_export', false, false],

        'trips.view' => ['trips', false, false],
        'trips.create' => ['trips', true, false],
        'trips.edit' => ['trips', true, false],
        'trips.delete' => ['trips', true, false],
        'trips.assign' => ['trips', true, false],
        'trips.confirm-number' => ['trips', true, false],
        'shippers.view' => ['trips', false, false],
        'shippers.manage' => ['trips', true, false],
        'trips.view.own' => ['trips', false, true],
        'trips.drive.own' => ['trips', true, true],

        'inspections.view' => ['pre_trip_inspections', false, false],
        'inspections.perform.own' => ['pre_trip_inspections', true, true],

        'trip-events.view' => ['trip_events', false, false],
        'trip-events.manage' => ['trip_events', true, false],
        'trip-events.create.own' => ['trip_events', true, true],
        'fuel.create.own' => ['trip_events', true, true],

        'tracking.view' => ['gps_tracking', false, false],
        'tracking.report.own' => ['gps_tracking', true, true],

        'mobile.access' => ['mobile_app', false, false],

        'messages.view' => ['messaging', false, false],
        'messages.send' => ['messaging', true, false],
        'messages.broadcast' => ['messaging', true, false],

        'time-off.view' => ['time_off', false, false],
        'time-off.approve' => ['time_off', true, false],
        'time-off.request.own' => ['time_off', true, true],

        'pay.view' => ['driver_pay', false, false],
        'pay.manage' => ['driver_pay', true, false],

        'timesheets.view' => ['timesheets', false, false],
        'timesheets.approve' => ['timesheets', true, false],
        'timesheets.view.own' => ['timesheets', false, true],
        'time-entries.create.own' => ['timesheets', true, true],

        // One feature column per permission; year_end_reports reuses these (05 §2).
        'reports.view' => ['profit_loss', false, false],
        'reports.export' => ['profit_loss', false, false],

        'integrations.manage' => ['quickbooks', true, false],
        'payroll.export' => ['quickbooks', true, false],
    ];

    /**
     * Guard `platform`. Name => is_write.
     *
     * @var array<string, bool>
     */
    public const PLATFORM = [
        'platform.staff.manage' => true,
        'platform.companies.view' => false,
        'platform.companies.manage' => true,
        'platform.companies.lifecycle' => true,
        'platform.companies.purge' => true,
        'platform.assets.transfer' => true,
        'platform.plans.manage' => true,
        'platform.features.manage' => true,
        'platform.overrides.manage' => true,
        'platform.subscriptions.view' => false,
        'platform.subscriptions.manage' => true,
        'platform.payments.view' => false,
        'platform.settings.manage' => true,
        'platform.audit.view' => false,
        'platform.support.request' => true,
        'platform.support.approve' => true,
    ];

    /**
     * @return list<string>
     */
    public static function tenantNames(): array
    {
        return array_keys(self::TENANT);
    }

    /**
     * @return list<string>
     */
    public static function platformNames(): array
    {
        return array_keys(self::PLATFORM);
    }
}
