<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * The folder's rule, applied to the document's own subject and to its folder's binding: a
 * vehicle-folder document about one of its service logs still needs vehicles.view.
 */
class DocumentPolicy extends FolderPolicy
{
    /**
     * Files of a trip event or fuel purchase are evidence (08 §9): the event is voided instead.
     */
    public function delete(User $user, Model $model): Response
    {
        if ($model instanceof Document && ($model->trip_event_id !== null || $model->fuel_purchase_id !== null)) {
            return Response::deny('Files of a trip event are kept as evidence. Void the event instead.');
        }

        return parent::delete($user, $model);
    }

    protected function reachable(User $user, Model $model): Response
    {
        $response = parent::reachable($user, $model);

        if ($response->denied() || ! $model instanceof Document || $model->folder_id === null) {
            return $response;
        }

        $folder = $model->folder()->withTrashed()->first();

        return $folder === null ? $response : parent::reachable($user, $folder);
    }
}
