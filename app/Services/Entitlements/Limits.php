<?php

namespace App\Services\Entitlements;

use App\Exceptions\LimitReached;
use App\Features\Feature;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Resource limits (05 §5). Every path that raises a count calls assertCanCreate inside its
 * transaction. It locks the company row first, so two parallel creates at limit - 1 serialize
 * and only one passes.
 */
class Limits
{
    public static function assertCanCreate(Company|int $company, Feature $limit, int $increment = 1): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Limits::assertCanCreate must run inside the transaction that raises the count.');
        }

        $locked = Company::query()->lockForUpdate()->findOrFail($company instanceof Company ? $company->id : $company);
        $max = Entitlements::for($locked)->limit($limit);

        if ($max !== null && self::count($locked->id, $limit) + $increment > $max) {
            throw new LimitReached($limit, $max);
        }
    }

    /**
     * Whether an active user counts toward max_users: office users only (see count()).
     */
    public static function isOfficeUser(User $user): bool
    {
        return $user->user_type === 'member' && ! DB::table('drivers')->where('user_id', $user->id)->exists();
    }

    /**
     * What counts toward the limit (decided Q4).
     */
    public static function count(int $companyId, Feature $limit): int
    {
        $query = match ($limit) {
            // Applicants, drafts, rejected, inactive and terminated drivers don't count.
            Feature::MaxDrivers => DB::table('drivers')->whereNull('deleted_at')->where('employment_status', 'active'),
            // Office users only: driver logins and support users don't count.
            Feature::MaxUsers => DB::table('users')->where('status', 'active')->where('user_type', 'member')
                ->whereNotExists(fn (Builder $q) => $q->from('drivers')->whereColumn('drivers.user_id', 'users.id')),
            Feature::MaxVehicles => DB::table('vehicles')->whereNull('deleted_at'),
            Feature::MaxTrailers => DB::table('trailers')->whereNull('deleted_at'),
            default => throw new InvalidArgumentException("{$limit->value} is not a counted limit."),
        };

        return $query->where('company_id', $companyId)->count();
    }
}
