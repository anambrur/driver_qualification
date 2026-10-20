<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role inside one team: a company id, or 0 for platform roles (06 §4.1).
 *
 * @property int $team_id
 * @property string $name
 * @property string $guard_name
 * @property string|null $system_key
 * @property bool $is_system
 * @property string|null $description
 * @property Carbon|null $customized_at
 */
class Role extends SpatieRole
{
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'customized_at' => 'datetime',
        ];
    }

    /**
     * Locked roles (Owner, Driver, Support, platform system roles) can't be edited, deleted or
     * assigned through the UI (06 §8 rule 5).
     */
    public function isLocked(): bool
    {
        return $this->system_key !== null;
    }
}
