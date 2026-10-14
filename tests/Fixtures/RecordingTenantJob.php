<?php

namespace Tests\Fixtures;

use App\Jobs\Middleware\TenantAware;
use App\Tenancy\TenantAwareJob;
use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Records the tenant context and Spatie team it ran with.
 */
class RecordingTenantJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @var list<array{company: ?int, team: int|string|null}> */
    public static array $seen = [];

    public function __construct(public int $companyId, public bool $fail = false) {}

    public function companyId(): int
    {
        return $this->companyId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new TenantAware];
    }

    public function handle(): void
    {
        self::$seen[] = [
            'company' => app(TenantContext::class)->companyId(),
            'team' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
        ];

        if ($this->fail) {
            throw new RuntimeException('job failed');
        }
    }
}
