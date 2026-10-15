<?php

namespace App\Features;

use App\Authorization\PermissionCatalogue;

/**
 * The feature registry (05 §2). FeatureRegistrySync upserts these cases into `features`; a
 * feature cannot be created from the UI because code has to implement it.
 */
enum Feature: string
{
    case Drivers = 'drivers';
    case DriverApplications = 'driver_applications';
    case DriverCompliance = 'driver_compliance';
    case Fleet = 'fleet';
    case FleetCompliance = 'fleet_compliance';
    case Maintenance = 'maintenance';
    case ComplianceReminders = 'compliance_reminders';
    case CustomRoles = 'custom_roles';
    case DocumentFolders = 'document_folders';
    case ZipExport = 'zip_export';
    case Trips = 'trips';
    case PreTripInspections = 'pre_trip_inspections';
    case TripEvents = 'trip_events';
    case MobileApp = 'mobile_app';
    case GpsTracking = 'gps_tracking';
    case Messaging = 'messaging';
    case TimeOff = 'time_off';
    case DriverPay = 'driver_pay';
    case Timesheets = 'timesheets';
    case ProfitLoss = 'profit_loss';
    case YearEndReports = 'year_end_reports';
    case Quickbooks = 'quickbooks';
    case MaxUsers = 'max_users';
    case MaxDrivers = 'max_drivers';
    case MaxVehicles = 'max_vehicles';
    case MaxTrailers = 'max_trailers';
    case StorageGb = 'storage_gb';

    /**
     * @return array{0: string, 1: string, 2: string|null} name, group, unit
     */
    private function meta(): array
    {
        return match ($this) {
            self::Drivers => ['Drivers', 'Core', null],
            self::DriverApplications => ['Driver applications', 'Core', null],
            self::DriverCompliance => ['Driver compliance', 'Compliance', null],
            self::Fleet => ['Fleet', 'Core', null],
            self::FleetCompliance => ['Fleet compliance', 'Compliance', null],
            self::Maintenance => ['Maintenance', 'Fleet', null],
            self::ComplianceReminders => ['Compliance reminders', 'Compliance', null],
            self::CustomRoles => ['Custom roles', 'Account', null],
            self::DocumentFolders => ['Document folders', 'Documents', null],
            self::ZipExport => ['ZIP export', 'Documents', null],
            self::Trips => ['Trips', 'Operations', null],
            self::PreTripInspections => ['Pre-trip inspections', 'Operations', null],
            self::TripEvents => ['Trip events', 'Operations', null],
            self::MobileApp => ['Mobile app', 'Operations', null],
            self::GpsTracking => ['GPS tracking', 'Operations', null],
            self::Messaging => ['Messaging', 'Communication', null],
            self::TimeOff => ['Time off', 'Communication', null],
            self::DriverPay => ['Driver pay', 'Money', null],
            self::Timesheets => ['Timesheets', 'Money', null],
            self::ProfitLoss => ['Profit and loss', 'Money', null],
            self::YearEndReports => ['Year-end reports', 'Money', null],
            self::Quickbooks => ['QuickBooks', 'Integrations', null],
            self::MaxUsers => ['Office users', 'Limits', 'users'],
            self::MaxDrivers => ['Drivers', 'Limits', 'drivers'],
            self::MaxVehicles => ['Vehicles', 'Limits', 'vehicles'],
            self::MaxTrailers => ['Trailers', 'Limits', 'trailers'],
            self::StorageGb => ['Storage', 'Limits', 'GB'],
        };
    }

    public function label(): string
    {
        return $this->meta()[0];
    }

    public function group(): string
    {
        return $this->meta()[1];
    }

    public function unit(): ?string
    {
        return $this->meta()[2];
    }

    public function isLimit(): bool
    {
        return $this->meta()[1] === 'Limits';
    }

    public function kind(): string
    {
        return $this->isLimit() ? 'limit' : 'module';
    }

    /**
     * The tenant permissions that belong to this feature, from the permission catalogue.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return array_keys(array_filter(PermissionCatalogue::TENANT, fn (array $row) => $row[0] === $this->value));
    }
}
