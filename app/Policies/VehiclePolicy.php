<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByCompany;

class VehiclePolicy
{
    use OwnedByCompany;
}
