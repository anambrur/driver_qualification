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
