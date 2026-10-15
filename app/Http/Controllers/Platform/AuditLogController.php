<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only audit viewer (04 §5): view and search, never edit or delete.
 */
class AuditLogController extends Controller
{
    public const ACTOR_TYPES = ['user', 'platform_user', 'system', 'stripe'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'actor_type' => ['nullable', 'in:'.implode(',', self::ACTOR_TYPES)],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['actor_type'] ?? null, fn ($q, $type) => $q->where('actor_type', $type))
            // Prefix match, so `company.` finds every company event and the index can be used
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', addcslashes($action, '%_\\').'%'))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', now()->parse($to)->addDay()->startOfDay()))
            ->orderByDesc('id')
            ->cursorPaginate(50)
            ->withQueryString();

        return view('platform.audit.index', ['logs' => $logs, 'filters' => $filters, 'actorTypes' => self::ACTOR_TYPES]);
    }
}
