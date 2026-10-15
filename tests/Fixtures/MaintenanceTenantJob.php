<?php

namespace Tests\Fixtures;

/**
 * A tenant job that declares the maintenance feature (05 §4); $readOnly marks an export-type job.
 */
class MaintenanceTenantJob extends RecordingTenantJob
{
    public string $feature = 'maintenance';

    public function __construct(int $companyId, public bool $readOnly = false)
    {
        parent::__construct($companyId);
    }
}
