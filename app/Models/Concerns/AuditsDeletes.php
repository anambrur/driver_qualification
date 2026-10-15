<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\PlatformUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Deletes and restores are always audited (08 §10). The using model defines AUDIT_NAME, the
 * action prefix. Query-builder mass deletes fire no model events and are not covered.
 */
trait AuditsDeletes
{
    public static function bootAuditsDeletes(): void
    {
        static::deleted(fn (Model $model) => self::auditDelete($model, method_exists($model, 'isForceDeleting') && $model->isForceDeleting() ? 'force_deleted' : 'deleted'));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => self::auditDelete($model, 'restored'));
        }
    }

    private static function auditDelete(Model $model, string $event): void
    {
        $action = static::AUDIT_NAME.'.'.$event;
        $companyId = $model->getAttribute('company_id');
        // The request's own guard: platform routes switch it to platform (EnsurePlatformSession)
        $actor = Auth::user();

        if ($actor instanceof User || $actor instanceof PlatformUser) {
            AuditLog::record($actor, $companyId, $action, $model);
        } else {
            AuditLog::recordSystem($companyId, $action, $model);
        }
    }
}
