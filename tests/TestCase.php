<?php

namespace Tests;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** The permission catalogue exists before CompanyProvisioner grants system roles. */
    protected bool $seed = true;

    protected string $seeder = PermissionSeeder::class;
}
