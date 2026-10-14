<?php

use App\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\RecordingTenantJob;
use Tests\Support\Actors;

beforeEach(function () {
    RecordingTenantJob::$seen = [];
    $this->companyA = Actors::company();
    $this->companyB = Actors::company();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('runs consecutive jobs for different companies without leaking context', function () {
    RecordingTenantJob::dispatchSync($this->companyA->id);

    expect(app(TenantContext::class)->companyId())->toBeNull()
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();

    RecordingTenantJob::dispatchSync($this->companyB->id);

    expect(RecordingTenantJob::$seen)->toBe([
        ['company' => $this->companyA->id, 'team' => $this->companyA->id],
        ['company' => $this->companyB->id, 'team' => $this->companyB->id],
    ])
        ->and(app(TenantContext::class)->companyId())->toBeNull()
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

it('resets the context when the job throws', function () {
    expect(fn () => RecordingTenantJob::dispatchSync($this->companyA->id, true))->toThrow(RuntimeException::class);

    expect(RecordingTenantJob::$seen)->toHaveCount(1)
        ->and(app(TenantContext::class)->companyId())->toBeNull()
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

it('drops the job of a suspended company without running it', function () {
    Actors::suspended($this->companyA);

    RecordingTenantJob::dispatchSync($this->companyA->id);

    expect(RecordingTenantJob::$seen)->toBe([]);
});

it('keeps the context of the code that dispatched it', function () {
    app(TenantContext::class)->run($this->companyA->id, function () {
        RecordingTenantJob::dispatchSync($this->companyB->id);

        expect(app(TenantContext::class)->companyId())->toBe($this->companyA->id)
            ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($this->companyA->id);
    });

    expect(RecordingTenantJob::$seen[0]['company'])->toBe($this->companyB->id);
});
