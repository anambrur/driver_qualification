<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Another company's record is a 404, never a 403 that confirms it exists (03 §5). The route's
 * permission: middleware decides what the user may do; the tenant scope already hides foreign
 * rows, so this guards paths that bypass it.
 */
trait OwnedByCompany
{
    protected function owned(User $user, Model $model): Response
    {
        return (int) $model->getAttribute('company_id') === (int) $user->company_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, Model $model): Response
    {
        return $this->owned($user, $model);
    }

    public function update(User $user, Model $model): Response
    {
        return $this->owned($user, $model);
    }

    public function delete(User $user, Model $model): Response
    {
        return $this->owned($user, $model);
    }

    public function restore(User $user, Model $model): Response
    {
        return $this->owned($user, $model);
    }
}
