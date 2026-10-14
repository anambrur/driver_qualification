<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedByCompany;

class ServiceLogPolicy
{
    use OwnedByCompany;
}
