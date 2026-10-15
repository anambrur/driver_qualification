<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StaffRequest;
use App\Models\PlatformUser;
use App\Services\Platform\PlatformStaffService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private PlatformStaffService $staff) {}

    public function index(): View
    {
        return view('platform.staff.index', [
            'staff' => PlatformUser::query()->with('roles:id,name')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('platform.staff.form', ['member' => new PlatformUser, 'roles' => $this->staff->roles(), 'held' => []]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        try {
            $this->staff->invite($this->actor($request), $request->validated('name'), $request->validated('email'), $request->roleIds());
        } catch (AuthorizationException $e) {
            return back()->withInput()->withErrors(['roles' => $e->getMessage() ?: 'You cannot assign these roles.']);
        }

        return redirect()->route('platform.staff.index')->with('success', 'Staff member invited. They were emailed a link to set their password.');
    }

    public function edit(PlatformUser $staff): View
    {
        return view('platform.staff.form', ['member' => $staff, 'roles' => $this->staff->roles(), 'held' => $this->staff->heldRoleIds($staff)]);
    }

    public function update(StaffRequest $request, PlatformUser $staff): RedirectResponse
    {
        try {
            $this->staff->update($this->actor($request), $staff, $request->validated('name'), $request->validated('email'), $request->roleIds());
        } catch (AuthorizationException $e) {
            return back()->withInput()->withErrors(['roles' => $e->getMessage() ?: 'This change is not allowed.']);
        }

        return redirect()->route('platform.staff.index')->with('success', 'Staff member updated.');
    }

    public function status(Request $request, PlatformUser $staff): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);

        try {
            $this->staff->setStatus($this->actor($request), $staff, $validated['status']);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['status' => $e->getMessage() ?: 'This change is not allowed.']);
        }

        return back()->with('success', $validated['status'] === 'active' ? 'Staff member enabled.' : 'Staff member disabled.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
