<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only (08 §10): rows are written through write() and never updated or deleted by the app.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** Keys whose values never reach the log, at any depth. */
    private const REDACT = '/password|token|secret|ssn|recovery_codes/i';

    protected $fillable = [
        'company_id',
        'actor_type',
        'actor_id',
        'support_session_id',
        'action',
        'subject_type',
        'subject_id',
        'changes',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(User|PlatformUser $actor, ?int $companyId, string $action, ?Model $subject = null, array $changes = []): self
    {
        return self::write($actor instanceof PlatformUser ? 'platform_user' : 'user', $actor->getKey(), $companyId, $action, $subject, $changes);
    }

    /**
     * A change made by a Stripe webhook (07 §6.2): no user, no request.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function recordStripe(int $companyId, string $action, Model $subject, array $changes = []): self
    {
        return self::write('stripe', null, $companyId, $action, $subject, $changes, withRequest: false);
    }

    /**
     * A change made by the app itself: a console command or a scheduled job.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function recordSystem(?int $companyId, string $action, ?Model $subject = null, array $changes = []): self
    {
        return self::write('system', null, $companyId, $action, $subject, $changes);
    }

    /**
     * The single insert path. Secrets are redacted from $changes here, whatever the caller sent.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function write(
        string $actorType,
        int|string|null $actorId,
        ?int $companyId,
        string $action,
        ?Model $subject = null,
        array $changes = [],
        bool $withRequest = true,
    ): self {
        $request = $withRequest && ! app()->runningInConsole() ? request() : null;

        return self::query()->create([
            'company_id' => $companyId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'support_session_id' => app(TenantContext::class)->supportSessionId(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : self::redact($changes),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::REDACT, $key)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value);
            }
        }

        return $values;
    }
}
