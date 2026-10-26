<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Policies\Concerns\OwnedByCompany;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff with trips.view see every trip of their company. A driver login (trips.view.own, Phase 3
 * app) sees only the trips assigned to its own driver record. Assigning needs trips.assign; the
 * other writes are decided by the route's permission (trips.edit for the lifecycle).
 */
class TripPolicy
{
    use OwnedByCompany {
        view as private viewOwned;
    }

    public function view(User $user, Model $model): Response
    {
        $owned = $this->viewOwned($user, $model);

        if ($owned->denied() || $user->can('trips.view')) {
            return $owned;
        }

        $ownDriver = $model instanceof Trip && $model->driver_id !== null && $user->can('trips.view.own')
            && Driver::query()->whereKey($model->driver_id)->where('user_id', $user->id)->exists();

        return $ownDriver ? Response::allow() : Response::deny();
    }

    public function assign(User $user, Model $model): Response
    {
        $owned = $this->owned($user, $model);

        return $owned->denied() || $user->can('trips.assign') ? $owned : Response::deny();
    }
}
