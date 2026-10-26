<?php

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\ServiceDocument;
use App\Models\TrailerDocument;
use App\Models\VehicleDocument;
use App\Services\Platform\SupportSessionService;
use App\Tenancy\TenantContext;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DocumentFactory;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverComplianceDocumentFactory;
use Database\Factories\DriverFactory;
use Database\Factories\ExportFactory;
use Database\Factories\FolderFactory;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\ShipperFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\UserFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| Isolation matrix (11 §5, 13 acceptance): for every tenant route with a parameter, the owner of
| company B gets a 404 on company A's id. The routes come from the router, so a new tenant route
| fails here until it gets a fixture in ISO_FIXTURES or a reason in ISO_EXCLUDED.
| Tenant route = authenticated, has a parameter, not super-admin or platform-permission only.
*/

const ISO_EXCLUDED = [
    // Shared reference data (03 §2): global, every tenant may read it
    'GET admin/settings/document-types/{id}' => 'global lookup',
    'POST admin/settings/document-types/{id}/company-toggle' => 'global lookup; the opt-out row is the tenant\'s own',
    // Plan slugs: public catalogue
    // Signed link of the logged-in user's own email
    'GET verify-email/{id}/{hash}' => 'signed own-user link',
    // A policy key, not an id: the action only serves the user's own company's file
    'GET admin/settings/policy-pdf/{policy}' => 'policy key; own company only (PolicyPdfTest)',
    // A provider key, not an id: only the logged-in user's own sign-in accounts (SocialLoginTest)
    'POST admin/profile/connected-accounts/{provider}' => 'provider key; own user only',
    'DELETE admin/profile/connected-accounts/{provider}' => 'provider key; own user only',
    // Platform guard: a tenant user is unauthenticated there; requester-only (SupportSessionTest)
    'POST platform/support-sessions/{supportSession}/end' => 'platform route; requester only',
];

/**
 * Route key => builds company A's record and returns the route parameters.
 *
 * @return array<string, Closure(Company): array<int|string, mixed>>
 */
function isoFixtures(): array
{
    $driver = fn (Company $a) => [DriverFactory::new()->forCompany($a)->create()->id];
    $driverField = fn (Company $a) => [DriverFactory::new()->forCompany($a)->create()->id, 'license_front'];
    $driverDocument = fn (Company $a) => [DriverComplianceDocumentFactory::new()
        ->forDriver(DriverFactory::new()->forCompany($a)->create(), DocumentTypeFactory::new()->module('driver')->create())
        ->create()->id];
    $vehicle = fn (Company $a) => [VehicleFactory::new()->forCompany($a)->create()->id];
    $trailer = fn (Company $a) => [TrailerFactory::new()->forCompany($a)->create()->id];
    // With a photo, so the 404 comes from tenancy and not from a missing file
    $withPhoto = fn (string $factory) => fn (Company $a) => [$factory::new()->forCompany($a)
        ->create(['photo_file_id' => StoredFileFactory::new()->forCompany($a)->create()->id])->id];
    $fleetDocument = fn (string $type) => function (Company $a) use ($type) {
        $asset = $type === 'vehicle' ? VehicleFactory::new()->forCompany($a)->create() : TrailerFactory::new()->forCompany($a)->create();
        $model = $type === 'vehicle' ? VehicleDocument::class : TrailerDocument::class;
        $document = app(TenantContext::class)->run($a->id, fn () => $model::create([
            $type.'_id' => $asset->id,
            'document_type_id' => DocumentTypeFactory::new()->module($type)->create()->id,
            'file_id' => StoredFileFactory::new()->forCompany($a)->create()->id,
            'expiry_date' => now()->addYear()->toDateString(),
        ]));

        return [$document->id, $type];
    };
    $supportSession = fn (Company $a) => [app(SupportSessionService::class)
        ->request(Actors::platformOwner(), $a, 'read', 'Isolation matrix fixture')->id];
    $serviceLog = fn (Company $a) => [ServiceLogFactory::new()->forVehicle(VehicleFactory::new()->forCompany($a)->create())->create()->id];
    $serviceDocument = function (Company $a) {
        $log = ServiceLogFactory::new()->forVehicle(VehicleFactory::new()->forCompany($a)->create())->create();

        return [app(TenantContext::class)->run($a->id, fn () => ServiceDocument::create([
            'service_log_id' => $log->id,
            'file_id' => StoredFileFactory::new()->forCompany($a)->create()->id,
        ]))->id];
    };
    $schedule = fn (Company $a) => [MaintenanceScheduleFactory::new()->forVehicle(VehicleFactory::new()->forCompany($a)->create())->create()->id];
    $group = fn (Company $a) => [AssetGroupFactory::new()->forCompany($a)->create()->id];
    $deletedGroup = function (Company $a) {
        $group = AssetGroupFactory::new()->forCompany($a)->create();
        $group->delete();

        return [$group->id];
    };
    $deletedVehicle = function (Company $a) {
        $vehicle = VehicleFactory::new()->forCompany($a)->create();
        $vehicle->delete();

        return [$vehicle->id];
    };
    $deletedTrailer = function (Company $a) {
        $trailer = TrailerFactory::new()->forCompany($a)->create();
        $trailer->delete();

        return [$trailer->id];
    };
    // A custom folder in a DQ file, and a document in it
    $folder = fn (Company $a) => [FolderFactory::new()->childOf(Docs::dqFile($a)[1])->create()->id];
    $document = fn (Company $a) => [DocumentFactory::new()->inFolder(Docs::dqFile($a)[1])->withFile(Files::stored($a->id))->create()->id];
    $shipper = fn (Company $a) => [ShipperFactory::new()->forCompany($a)->create()->id];
    $user = fn (Company $a) => [UserFactory::new()->create(['company_id' => $a->id])->id];
    $role = fn (Company $a) => [Role::query()->where('team_id', $a->id)->where('name', 'Dispatcher')->value('id')];
    $invitation = fn (Company $a) => [app(TenantContext::class)->run($a->id, fn () => CompanyInvitation::create([
        'email' => fake()->unique()->safeEmail(),
        'role_id' => Role::query()->where('team_id', $a->id)->where('name', 'Dispatcher')->value('id'),
        'token_hash' => hash('sha256', Str::random(40)),
        'invited_by_user_id' => $a->owner_user_id,
        'expires_at' => now()->addDay(),
    ]))->id];

    return [
        // Drivers
        'GET admin/driver/{id}' => $driver,
        'GET admin/driver/{id}/edit' => $driver,
        'PUT admin/driver/{id}' => $driver,
        'DELETE admin/driver/{id}' => $driver,
        'GET admin/driver/{id}/details' => $driver,
        'GET admin/driver/{id}/file/{field}' => $driverField,
        'POST admin/driver/{driver}/hire-status' => $driver,
        'GET admin/driver/license/{driver_id}' => $driver,
        'GET admin/driver/medical-card/{driver_id}' => $driver,
        'GET admin/driver/forfeiture-document/{driver_id}' => $driver,
        'GET admin/driver/violation/{driver_id}' => $driver,
        'GET admin/driver/drug-test/{driver_id}' => $driver,
        'GET admin/driver/fmcsa_consent/{driver_id}' => $driver,
        'GET admin/driver/psp/{driver_id}' => $driver,
        'GET admin/driver/alcohol-drug-test-policy/{driver_id}' => $driver,
        'GET admin/driver/general-work-policy/{driver_id}' => $driver,
        'GET admin/compliance/drivers/{id}/details' => $driver,
        'GET admin/compliance/driver-documents/{documentId}/view' => $driverDocument,
        'GET admin/compliance/driver-documents/{documentId}/download' => $driverDocument,
        // Fleet
        'GET admin/vehicle/{id}/edit' => $vehicle,
        'PUT admin/vehicle/{id}' => $vehicle,
        'DELETE admin/vehicle/{id}' => $vehicle,
        'POST admin/vehicle/{id}/restore' => $deletedVehicle,
        'GET admin/vehicle/{id}/details' => $vehicle,
        'GET admin/vehicle/{id}' => $vehicle,
        'GET admin/vehicle/{id}/photo' => $withPhoto(VehicleFactory::class),
        'GET admin/trailer/{id}/edit' => $trailer,
        'PUT admin/trailer/{id}' => $trailer,
        'DELETE admin/trailer/{id}' => $trailer,
        'POST admin/trailer/{id}/restore' => $deletedTrailer,
        'GET admin/trailer/{id}/details' => $trailer,
        'GET admin/trailer/{id}' => $trailer,
        'GET admin/trailer/{id}/photo' => $withPhoto(TrailerFactory::class),
        'GET admin/compliance/vehicles/{id}/details' => $vehicle,
        'GET admin/compliance/trailers/{id}/details' => $trailer,
        'GET admin/compliance/documents/{documentId}/{assetType}/view' => $fleetDocument('vehicle'),
        'GET admin/compliance/documents/{documentId}/{assetType}/download' => $fleetDocument('trailer'),
        // Maintenance
        'GET admin/service-log/{id}' => $serviceLog,
        'GET admin/service-log/{id}/edit' => $serviceLog,
        'PUT admin/service-log/{id}' => $serviceLog,
        'DELETE admin/service-log/{id}' => $serviceLog,
        'GET admin/service-log/vehicle/{id}/details' => $vehicle,
        'GET admin/service-log/document/{id}/download' => $serviceDocument,
        'DELETE admin/service-log/document/{id}' => $serviceDocument,
        'GET admin/maintenance-schedule/{id}' => $schedule,
        'GET admin/maintenance-schedule/{id}/edit' => $schedule,
        'PUT admin/maintenance-schedule/{id}' => $schedule,
        'DELETE admin/maintenance-schedule/{id}' => $schedule,
        'POST admin/maintenance-schedule/{id}/mark-completed' => $schedule,
        'GET admin/maintenance-schedule/vehicle/{id}/details' => $vehicle,
        // Asset groups
        'GET admin/asset-group/{id}/edit' => $group,
        'PUT admin/asset-group/{id}' => $group,
        'DELETE admin/asset-group/{id}' => $group,
        'POST admin/asset-group/{id}/restore' => $deletedGroup,
        // Folders and documents (P1-4)
        'PUT admin/documents/folders/{folder}' => $folder,
        'DELETE admin/documents/folders/{folder}' => $folder,
        'GET admin/documents/folders/{folder}/documents' => $folder,
        'POST admin/documents/folders/{folder}/documents' => $folder,
        'PATCH admin/documents/{document}/folder' => $document,
        'GET admin/documents/{document}/download' => $document,
        'GET admin/documents/{document}/view' => $document,
        'GET admin/documents/{document}/original' => $document,
        'DELETE admin/documents/{document}' => $document,
        // Exports (P1-6)
        'GET admin/exports/{export}/download' => fn (Company $a) => [ExportFactory::new()->ready(Files::stored($a->id, 'PK', 'zip'))->create()->id],
        // Shippers (P2-1)
        'GET admin/shippers/{shipper}/edit' => $shipper,
        'PUT admin/shippers/{shipper}' => $shipper,
        'POST admin/shippers/{shipper}/activate' => $shipper,
        'POST admin/shippers/{shipper}/deactivate' => $shipper,
        'DELETE admin/shippers/{shipper}' => $shipper,
        // Company settings
        'GET admin/settings/company/{id}/edit' => fn (Company $a) => [$a->id],
        'PATCH admin/settings/company/{id}' => fn (Company $a) => [$a->id],
        // Users, roles, invitations (WP4)
        'GET users/{user}/edit' => $user,
        'PUT users/{user}' => $user,
        'POST users/{user}/deactivate' => $user,
        'POST users/{user}/reactivate' => $user,
        'POST users/{user}/permissions' => $user,
        'DELETE users/{user}/permissions/{permission}' => fn (Company $a) => [...$user($a), 'drivers.view'],
        'GET roles/{role}' => $role,
        'GET roles/{role}/edit' => $role,
        'PUT roles/{role}' => $role,
        'POST roles/{role}/reset' => $role,
        'DELETE roles/{role}' => $role,
        'POST users/invitations/{invitation}/resend' => $invitation,
        'DELETE users/invitations/{invitation}' => $invitation,
        // Support access (WP8b): a session on company A
        'POST support-access/{supportSession}/approve' => $supportSession,
        'POST support-access/{supportSession}/reject' => $supportSession,
        'POST support-access/{supportSession}/revoke' => $supportSession,
    ];
}

/**
 * @return array<string, RoutingRoute> keyed "METHODS uri" (HEAD dropped)
 */
function isoTenantRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(function (RoutingRoute $route) {
            $middleware = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m));

            return str_contains($route->uri(), '{')
                && $middleware->contains(fn (string $m) => $m === 'auth' || str_starts_with($m, 'auth:'))
                && ! $middleware->contains('role:super-admin')
                && ! $middleware->contains(fn (string $m) => str_starts_with($m, 'permission:platform.'));
        })
        ->keyBy(fn (RoutingRoute $route) => implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri())
        ->all();
}

it('has a fixture or an exclusion reason for every tenant route with a parameter', function () {
    $routes = array_keys(isoTenantRoutes());
    $classified = [...array_keys(isoFixtures()), ...array_keys(ISO_EXCLUDED)];

    expect(array_values(array_diff($routes, $classified)))->toBe([])
        ->and(array_values(array_diff($classified, $routes)))->toBe([]);
});

it('returns 404 to company B on every company A id', function () {
    $companyA = Actors::company();
    $ownerB = Actors::companyOwner();
    $routes = isoTenantRoutes();
    $leaks = [];

    foreach (isoFixtures() as $key => $fixture) {
        [$method] = explode(' ', $key);
        $url = route($routes[$key]->getName(), $fixture($companyA));

        $status = $this->actingAs($ownerB)->json($method, $url)->getStatusCode();

        if ($status !== 404) {
            $leaks[] = "$key -> $status";
        }
    }

    expect($leaks)->toBe([]);
});
