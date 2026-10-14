<?php

namespace App\Authorization;

/**
 * System roles every company gets at provisioning (06 §6). Permission grants follow in WP3.
 */
final class RoleTemplates
{
    /** Role name => system_key (null = system role without a key). */
    public const SYSTEM_ROLES = [
        'Owner' => 'owner',
        'Administrator' => null,
        'Dispatcher' => null,
        'Office / Compliance' => null,
        'Driver' => 'driver',
        'Support (read)' => 'support_read',
        'Support (read/write)' => 'support_read_write',
    ];

    public const OWNER = 'Owner';
}
