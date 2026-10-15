<?php

namespace App\Http\Controllers;

use App\Models\PlatformSupportSession;
use App\Models\User;
use App\Services\Platform\SupportSessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The company Owner's side of support sessions (Q1, 04 §4): approve, refuse or end platform
 * access to the company. Only the Owner may act; another company's session is a 404.
 */
class SupportAccessController extends Controller
{
    public function __construct(private SupportSessionService $sessions) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        abort_unless((int) $user->company?->owner_user_id === (int) $user->id, 403, 'Only the company owner manages support access.');

        return view('support-access.index', [
            'sessions' => PlatformSupportSession::query()
                ->where('company_id', $user->company_id)
                ->with('requester:id,name')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function approve(Request $request, PlatformSupportSession $supportSession): RedirectResponse
    {
        return $this->attempt(fn () => $this->sessions->approveAsTenant($this->user($request), $supportSession), 'Support access approved.');
    }

    public function reject(Request $request, PlatformSupportSession $supportSession): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? 'Refused by the company owner.';

        return $this->attempt(fn () => $this->sessions->reject($this->user($request), $supportSession, $reason), 'Support access refused.');
    }

    public function revoke(Request $request, PlatformSupportSession $supportSession): RedirectResponse
    {
        return $this->attempt(fn () => $this->sessions->revoke($this->user($request), $supportSession), 'Support access ended.');
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->route('support-access.index')->with('success', $success);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
