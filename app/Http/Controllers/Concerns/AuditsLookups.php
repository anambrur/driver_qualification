<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Lookups are platform data (LKP-01), managed only from /platform: every create, edit, status
 * change and delete is audited (08 §10).
 */
trait AuditsLookups
{
    protected function auditLookup(string $event, Model $lookup): void
    {
        /** @var PlatformUser $actor */
        $actor = Auth::guard('platform')->user();
        $changes = $event === 'updated' ? $lookup->getChanges() : $lookup->only($lookup->getFillable());

        AuditLog::record($actor, null, 'lookup.'.$event, $lookup, Arr::except($changes, ['updated_at', 'created_at']));
    }
}
