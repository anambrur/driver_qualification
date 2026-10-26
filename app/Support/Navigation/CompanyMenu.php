<?php

namespace App\Support\Navigation;

use App\Models\PlatformSupportSession;
use App\Models\User;
use App\Services\Entitlements\Entitlements;
use Illuminate\Http\Request;

/**
 * Company-area sidebar. Gates mirror the routes they link to (@can / @feature).
 */
final class CompanyMenu
{
    /**
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    public static function for(?User $user, Request $request): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $company = $user->company;
        $entitlements = $company ? Entitlements::for($company) : null;
        $feature = fn (string $key): bool => $entitlements?->allows($key) ?? false;
        $isOwner = $company && (int) $company->owner_user_id === (int) $user->id;

        return Menu::resolve([
            'General' => [
                ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard', 'active' => ['dashboard', 'admin.dashboard']],
            ],
            'Operations' => [
                [
                    'label' => 'Drivers', 'icon' => 'id-card',
                    'visible' => $feature('drivers') && $user->can('drivers.view'),
                    'children' => [
                        ['label' => 'Compliance', 'route' => 'admin.compliance.drivers', 'active' => ['admin.compliance.drivers*', 'admin.compliance.driver.*'], 'visible' => $user->can('drivers.dashboard')],
                        ['label' => 'Add driver', 'route' => 'admin.driver.create', 'visible' => $user->can('drivers.create')],
                        ['label' => 'All drivers', 'route' => 'admin.driver.index', 'active' => ['admin.driver.index', 'admin.driver.show', 'admin.driver.edit'], 'visible' => $user->can('drivers.view')],
                    ],
                ],
                [
                    'label' => 'Fleet', 'icon' => 'truck',
                    'visible' => $feature('fleet') && $user->can('fleets.dashboard'),
                    'children' => [
                        ['label' => 'Compliance', 'route' => 'admin.compliance.fleet', 'active' => ['admin.compliance.fleet', 'admin.compliance.vehicle*', 'admin.compliance.trailer*'], 'visible' => $user->can('fleets.dashboard')],
                        ['label' => 'Vehicles', 'route' => 'admin.vehicle.index', 'active' => ['admin.vehicle.*', 'admin.vehicles.*'], 'visible' => $user->can('vehicles.view')],
                        ['label' => 'Trailers', 'route' => 'admin.trailer.index', 'active' => ['admin.trailer.*', 'admin.trailers.*'], 'visible' => $user->can('trailers.view')],
                    ],
                ],
                [
                    'label' => 'Maintenance', 'icon' => 'wrench',
                    'visible' => $feature('maintenance') && $user->can('maintenance.view'),
                    'children' => [
                        ['label' => 'Service log', 'route' => 'admin.service-log.index', 'active' => ['admin.service-log.*'], 'visible' => $user->can('maintenance.view')],
                        ['label' => 'Scheduled', 'route' => 'admin.maintenance-schedule.index', 'active' => ['admin.maintenance-schedule.*'], 'visible' => $user->can('scheduled.view')],
                    ],
                ],
                ['label' => 'Shippers', 'icon' => 'warehouse', 'route' => 'shippers.index', 'active' => ['shippers.*'], 'visible' => $feature('trips') && $user->can('shippers.view')],
                ['label' => 'Documents', 'icon' => 'folder', 'route' => 'documents.index', 'active' => ['documents.*'], 'visible' => $feature('document_folders') && $user->can('folders.view')],
                ['label' => 'Exports', 'icon' => 'archive', 'route' => 'exports.index', 'active' => ['exports.*'], 'visible' => $feature('zip_export') && ($user->can('exports.create') || $user->can('exports.download'))],
            ],
            'Settings' => [
                ['label' => 'Asset groups', 'icon' => 'layers', 'route' => 'admin.asset-group.index', 'active' => ['admin.asset-group.*'], 'visible' => $user->can('asset-groups.view')],
                ['label' => 'Document types', 'icon' => 'file-text', 'route' => 'admin.settings.document-types.index', 'active' => ['admin.settings.document-types.*'], 'visible' => $user->can('document-types.toggle')],
            ],
            'Team' => [
                ['label' => 'Users', 'icon' => 'users', 'route' => 'users.index', 'active' => ['users.*'], 'visible' => $user->can('users.view')],
                ['label' => 'Roles', 'icon' => 'shield-check', 'route' => 'admin.roles.index', 'active' => ['admin.roles.*'], 'visible' => $user->can('roles.view')],
            ],
            'Account' => [
                ['label' => 'Company', 'icon' => 'building-2', 'route' => 'admin.settings.company.edit', 'params' => [$company?->id], 'active' => ['admin.settings.company.*'], 'visible' => $company !== null && $user->can('company.edit')],
                ['label' => 'Policy PDFs', 'icon' => 'file-badge', 'route' => 'admin.settings.policy.pdf', 'active' => ['admin.settings.policy.*'], 'visible' => $company !== null && $user->can('company.edit')],
                [
                    'label' => 'Support access', 'icon' => 'life-buoy', 'route' => 'support-access.index', 'active' => ['support-access.*'],
                    'visible' => $isOwner,
                    'badge' => $isOwner && PlatformSupportSession::query()->open()
                        ->where('company_id', $company->id)->whereNull('tenant_approved_at')->exists() ? 'New' : null,
                ],
                [
                    'label' => 'Subscription', 'icon' => 'credit-card',
                    'children' => [
                        ['label' => 'Plans & pricing', 'route' => 'pricing.plans'],
                        ['label' => 'Billing & invoices', 'route' => 'billing.index', 'active' => ['billing.*']],
                    ],
                ],
            ],
        ], $request);
    }
}
