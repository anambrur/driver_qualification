<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\InviteUserRequest;
use App\Models\User;
use App\Services\Authorization\RoleService;
use App\Services\Users\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyInvitationController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function create(Request $request, RoleService $roles): View
    {
        return view('admin.users.invite', ['roles' => $roles->assignable($this->actor($request))]);
    }

    /**
     * Always "invitation sent": whether the email has an account elsewhere is never shown (D3).
     */
    public function store(InviteUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->invitations->invite($this->actor($request), $data['email'], (int) $data['role_id']);

        toastr()->success('Invitation sent.');

        return redirect()->route('users.index');
    }

    public function resend(Request $request, int $invitation): RedirectResponse
    {
        $this->invitations->resend($this->actor($request), $invitation);

        toastr()->success('Invitation sent.');

        return redirect()->route('users.index');
    }

    public function destroy(Request $request, int $invitation): RedirectResponse
    {
        $this->invitations->revoke($this->actor($request), $invitation);

        toastr()->success('Invitation revoked.');

        return redirect()->route('users.index');
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
