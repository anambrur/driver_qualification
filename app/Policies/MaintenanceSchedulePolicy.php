<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByCompany;

class MaintenanceSchedulePolicy
{
    use OwnedByCompany;
}
