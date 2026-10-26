<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\OwnedByCompany;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * A folder bound to a driver, vehicle, trailer or trip also needs the right to see that entity, so
 * folders.view alone never opens a DQ file.
 */
class FolderPolicy
{
    use OwnedByCompany;

    /** Binding column => the entity's view permission. */
    public const ENTITY_ABILITIES = ['driver_id' => 'drivers.view', 'vehicle_id' => 'vehicles.view', 'trailer_id' => 'trailers.view', 'trip_id' => 'trips.view'];

    public function view(User $user, Model $model): Response
    {
        return $this->reachable($user, $model);
    }

    public function update(User $user, Model $model): Response
    {
        return $this->reachable($user, $model);
    }

    public function delete(User $user, Model $model): Response
    {
        return $this->reachable($user, $model);
    }

    /**
     * Owned by the user's company, and its binding (or the document's subject) visible to them.
     */
    protected function reachable(User $user, Model $model): Response
    {
        $owned = $this->owned($user, $model);

        if ($owned->denied()) {
            return $owned;
        }

        foreach (self::ENTITY_ABILITIES as $column => $ability) {
            if ($model->getAttribute($column) !== null && ! $user->can($ability)) {
                return Response::deny();
            }
        }

        return Response::allow();
    }
}
