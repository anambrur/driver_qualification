<?php

namespace App\Tenancy;

use Closure;
use Spatie\Permission\PermissionRegistrar;

/**
 * The tenant of the current request or job (03 §4.1). Bound as a scoped instance, so it is
 * reset per request and per job under Octane and queue workers.
 */
class TenantContext
{
    public const MODES = ['tenant', 'public_applicant', 'support_session', 'platform'];

    private ?int $companyId = null;

    private ?string $mode = null;

    private ?int $supportSessionId = null;

    public function set(int $companyId, string $mode = 'tenant', ?int $supportSessionId = null): void
    {
        if (! in_array($mode, self::MODES, true) || $mode === 'platform') {
            throw new \InvalidArgumentException("Invalid tenant mode [{$mode}].");
        }

        $this->companyId = $companyId;
        $this->mode = $mode;
        $this->supportSessionId = $supportSessionId;
    }

    /**
     * Platform mode: no tenant. Tenant models refuse to query (fail closed).
     */
    public function setPlatform(): void
    {
        $this->companyId = null;
        $this->mode = 'platform';
        $this->supportSessionId = null;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function requireCompanyId(): int
    {
        return $this->companyId ?? throw new MissingTenantContextException('No tenant context is set.');
    }

    public function mode(): ?string
    {
        return $this->mode;
    }

    public function supportSessionId(): ?int
    {
        return $this->supportSessionId;
    }

    /**
     * Runs the callback as the given company (context and Spatie team), then restores the
     * previous state even when the callback throws.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(int $companyId, Closure $callback): mixed
    {
        $previous = [$this->companyId, $this->mode, $this->supportSessionId];
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();

        $this->set($companyId);
        $registrar->setPermissionsTeamId($companyId);

        try {
            return $callback();
        } finally {
            [$this->companyId, $this->mode, $this->supportSessionId] = $previous;
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }
}
