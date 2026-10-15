<?php

namespace App\Services\Platform;

use App\Models\PlatformUser;
use App\Models\User;
use App\Support\NamedLock;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An email address is either a tenant login or a platform login, never both (D3a, 04 §2).
 * Each table has its own unique index; the named lock closes the race between the two tables,
 * so it must cover the check and the insert: callers do the write inside claim().
 */
class EmailIdentityService
{
    public const TAKEN = 'This email address is already in use.';

    private const LOCK_SECONDS = 5;

    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @param  Model|null  $ignore  the user or platform user whose own email this is
     *
     * @throws ValidationException
     */
    public function assertAvailable(string $email, ?Model $ignore = null): void
    {
        $email = $this->normalize($email);

        $taken = fn (string $model) => $model::query()
            // Both columns are stored lowercase (mutators), so this is an index lookup
            ->where('email', $email)
            ->when($ignore instanceof $model, fn ($q) => $q->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($taken(User::class) || $taken(PlatformUser::class)) {
            throw ValidationException::withMessages(['email' => self::TAKEN]);
        }
    }

    /**
     * Runs the write that stores $email under the email lock, after the check, in its own
     * transaction, so the row is committed before the lock is released.
     *
     * @template TReturn
     *
     * @param  Closure(string): TReturn  $write  receives the normalised email
     * @return TReturn
     *
     * @throws ValidationException
     */
    public function claim(string $email, Closure $write, ?Model $ignore = null): mixed
    {
        $email = $this->normalize($email);

        try {
            return NamedLock::run("email:{$email}", self::LOCK_SECONDS, function () use ($email, $write, $ignore) {
                $this->assertAvailable($email, $ignore);

                return DB::transaction(fn () => $write($email));
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['email' => 'This email address is being registered. Please try again.']);
        } catch (UniqueConstraintViolationException $e) {
            // The per-table email index caught a same-table race; any other unique index is not
            // ours. SQLite names it users.email, MySQL users_email_unique (platform_users alike).
            throw preg_match('/users\.email\b|users_email_unique/', $e->getMessage())
                ? ValidationException::withMessages(['email' => self::TAKEN])
                : $e;
        }
    }
}
