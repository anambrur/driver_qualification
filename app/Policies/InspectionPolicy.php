<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Inspection;
use App\Models\User;
use App\Policies\Concerns\OwnedByCompany;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff with inspections.view see every inspection of their company. A driver login
 * (inspections.perform.own, Phase 3 app) sees only the inspections of its own driver record.
 * Recording needs inspections.manage, decided by the route's permission.
 */
class InspectionPolicy
{
    use OwnedByCompany {
        view as private viewOwned;
    }

    public function view(User $user, Model $model): Response
    {
        $owned = $this->viewOwned($user, $model);

        if ($owned->denied() || $user->can('inspections.view')) {
            return $owned;
        }

        $ownDriver = $model instanceof Inspection && $user->can('inspections.perform.own')
            && Driver::query()->whereKey($model->driver_id)->where('user_id', $user->id)->exists();

        return $ownDriver ? Response::allow() : Response::deny();
    }
}
