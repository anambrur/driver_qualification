<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\TripEvent;
use App\Models\User;
use App\Policies\Concerns\OwnedByCompany;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff with trip-events.view see every trip event of their company. A driver login
 * (trip-events.create.own, Phase 3 app) sees only the events of its own driver record.
 * Recording and voiding need trip-events.manage, decided by the route's permission.
 */
class TripEventPolicy
{
    use OwnedByCompany {
        view as private viewOwned;
    }

    public function view(User $user, Model $model): Response
    {
        $owned = $this->viewOwned($user, $model);

        if ($owned->denied() || $user->can('trip-events.view')) {
            return $owned;
        }

        $ownDriver = $model instanceof TripEvent && $user->can('trip-events.create.own')
            && Driver::query()->whereKey($model->driver_id)->where('user_id', $user->id)->exists();

        return $ownDriver ? Response::allow() : Response::deny();
    }
}
