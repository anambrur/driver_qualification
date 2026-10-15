<?php

namespace App\Services\Users;

use App\Features\Feature;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\Entitlements\Limits;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Users of the actor's own company. Ids outside it are 404; the 06 §8 rules (no self-service,
 * only the Owner touches the Owner, subset assignment) come from RoleAssignmentService.
 * Users are deactivated, never deleted (10 §5).
 */
class UserService
{
    public function __construct(private RoleAssignmentService $assignments) {}

    /**
     * @param  array{search?: string|null, role?: int|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(User $actor, array $filters): LengthAwarePaginator
    {
        return User::query()
            ->where('company_id', $actor->company_id)
            ->where('user_type', 'member')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['role'] ?? null, fn ($query, int $roleId) => $query->whereHas('roles', fn ($q) => $q->whereKey($roleId)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->with('roles:id,name,system_key')
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function find(User $actor, int $id): User
    {
        return User::query()
            ->where('company_id', $actor->company_id)
            ->where('user_type', 'member')
            ->findOrFail($id);
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function update(User $actor, User $target, string $name, array $roleIds): void
    {
        $this->assignments->authorizeTarget($actor, $target, 'users.edit');

        DB::transaction(function () use ($actor, $target, $name, $roleIds) {
            if ($target->name !== $name) {
                $target->forceFill(['name' => $name])->save();
                AuditLog::record($actor, $actor->company_id, 'user.updated', $target, ['name' => $name]);
            }

            $this->assignments->syncRoles($actor, $target, $roleIds);
        });
    }

    /**
     * An inactive user is logged out on their next request (EnsureUserIsActive).
     */
    public function setStatus(User $actor, User $target, string $status): void
    {
        $this->assignments->authorizeTarget($actor, $target, 'users.deactivate');

        if ($target->status === $status) {
            return;
        }

        DB::transaction(function () use ($actor, $target, $status) {
            // Re-activating an office user raises the max_users count (05 §5).
            if ($status === 'active' && Limits::isOfficeUser($target)) {
                Limits::assertCanCreate($target->company_id, Feature::MaxUsers);
            }

            $target->forceFill(['status' => $status])->save();

            AuditLog::record($actor, $actor->company_id, $status === 'active' ? 'user.reactivated' : 'user.deactivated', $target);
        });
    }
}
