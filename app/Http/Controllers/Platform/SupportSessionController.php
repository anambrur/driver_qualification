<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SupportSessionRequest;
use App\Models\Company;
use App\Models\PlatformSupportSession;
use App\Models\PlatformUser;
use App\Services\Platform\SupportSessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Support sessions from the platform side (04 §4): ask, approve as the second Platform Owner,
 * start and end. Starting logs the requester into the tenant area as the company's support user.
 */
class SupportSessionController extends Controller
{
    public function __construct(private SupportSessionService $sessions) {}

    public function index(Request $request): View
    {
        $staff = $this->actor($request);
        $with = ['company:id,company_name', 'requester:id,name'];

        return view('platform.support-sessions.index', [
            'mine' => PlatformSupportSession::query()->with($with)->where('platform_user_id', $staff->id)->orderByDesc('id')->limit(50)->get(),
            'awaiting' => $staff->can('platform.support.approve')
                ? PlatformSupportSession::query()->with($with)->open()->whereNull('platform_approved_by_platform_user_id')
                    ->where('platform_user_id', '!=', $staff->id)->orderBy('requested_at')->get()
                : collect(),
        ]);
    }

    public function create(): View
    {
        return view('platform.support-sessions.create', [
            'companies' => Company::query()->where('status', 'active')->orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function store(SupportSessionRequest $request): RedirectResponse
    {
        $this->sessions->request(
            $this->actor($request),
            Company::query()->findOrFail($request->validated('company_id')),
            $request->validated('scope'),
            $request->validated('reason'),
        );

        return redirect()->route('platform.support-sessions.index')
            ->with('success', 'Requested. The company owner and a second Platform Owner must both approve it.');
    }

    public function approve(Request $request, PlatformSupportSession $supportSession): RedirectResponse
    {
        return $this->attempt(fn () => $this->sessions->approveAsPlatform($this->actor($request), $supportSession), 'Approved.');
    }

    public function reject(Request $request, PlatformSupportSession $supportSession): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];

        return $this->attempt(fn () => $this->sessions->reject($this->actor($request), $supportSession, $reason), 'Rejected.');
    }

    public function start(Request $request, PlatformSupportSession $supportSession): RedirectResponse
    {
        try {
            $support = $this->sessions->start($this->actor($request), $supportSession);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['session' => $e->getMessage() ?: 'This action is not allowed.']);
        }

        Auth::guard('web')->login($support);
        $request->session()->regenerate();
        $request->session()->put(SupportSessionService::SESSION_KEY, $supportSession->id);

        return redirect()->route('admin.dashboard');
    }

    public function end(Request $request, PlatformSupportSession $supportSession): RedirectResponse
    {
        $staff = $this->actor($request);

        if ((int) $staff->id !== (int) $supportSession->platform_user_id) {
            abort(403);
        }

        $this->sessions->end($supportSession, 'ended by staff', $staff);

        if ((int) $request->session()->pull(SupportSessionService::SESSION_KEY) === $supportSession->id) {
            Auth::guard('web')->logout();
            $request->session()->regenerateToken();
        }

        return redirect()->route('platform.support-sessions.index')->with('success', 'Support session ended.');
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (AuthorizationException $e) {
            return back()->withErrors(['session' => $e->getMessage() ?: 'This action is not allowed.']);
        }

        return back()->with('success', $success);
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
