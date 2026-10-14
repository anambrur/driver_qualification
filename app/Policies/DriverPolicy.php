<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByCompany;

class DriverPolicy
{
    use OwnedByCompany;
}
