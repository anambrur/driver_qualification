<?php

namespace App\Services\Operations;

use App\Models\Shipper;

/**
 * Saved shippers (req 4.4). The company comes from the tenant context (BelongsToCompany);
 * deletes are soft and audited (AuditsDeletes), so trips keep their shipper.
 */
class ShipperService
{
    /**
     * @param  array<string, mixed>  $data  validated ShipperRequest data
     */
    public function create(array $data): Shipper
    {
        return Shipper::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data  validated ShipperRequest data
     */
    public function update(Shipper $shipper, array $data): Shipper
    {
        $shipper->update($data);

        return $shipper;
    }

    public function setActive(Shipper $shipper, bool $active): void
    {
        $shipper->update(['is_active' => $active]);
    }

    public function delete(Shipper $shipper): void
    {
        $shipper->delete();
    }
}
