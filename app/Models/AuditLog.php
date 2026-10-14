<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $changes  never secrets
     */
    public static function record(User|PlatformUser $actor, ?int $companyId, string $action, Model $subject, array $changes = []): self
    {
        $request = app()->runningInConsole() ? null : request();

        return self::query()->create([
            'company_id' => $companyId,
            'actor_type' => $actor instanceof PlatformUser ? 'platform_user' : 'user',
            'actor_id' => $actor->getKey(),
            'support_session_id' => app(TenantContext::class)->supportSessionId(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'changes' => $changes ?: null,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
