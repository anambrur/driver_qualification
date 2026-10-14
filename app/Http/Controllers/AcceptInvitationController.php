<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\AcceptInvitationRequest;
use App\Services\Users\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Guest side of an invitation: the invitee sets a name and password and is logged in.
 */
class AcceptInvitationController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function show(string $token): View
    {
        $invitation = $this->invitations->findByToken($token);

        abort_if($invitation === null, 404);

        return view('auth.accept-invitation', [
            'token' => $token,
            'email' => $invitation->email,
            'companyName' => $invitation->company->company_name,
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $data = $request->validated();

        $user = $this->invitations->accept($token, $data['name'], $data['password']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
