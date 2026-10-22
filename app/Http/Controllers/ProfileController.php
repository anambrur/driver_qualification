<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\Auth\SocialLoginService;
use App\Services\Auth\SocialProviders;
use App\Services\Platform\EmailIdentityService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, SocialProviders $providers, SocialLoginService $social): View
    {
        $user = $request->user();
        $socialProviders = $providers->enabled();
        $socialAccounts = $user->socialAccounts()->get(['id', 'provider', 'provider_email'])->keyBy('provider');

        return view('profile.edit', [
            'user' => $user,
            'canCloseAccount' => $this->canCloseAccount($user),
            'socialProviders' => $socialProviders,
            'socialAccounts' => $socialAccounts,
            // Only the Connected accounts card uses it, and it is hidden without providers or links
            'neverSetPassword' => ($socialProviders !== [] || $socialAccounts->isNotEmpty()) && $social->neverSetPassword($user),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, EmailIdentityService $emails): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            // Never also a platform login (D3a). The company's contact email no longer follows
            // the login email (D3b).
            $from = $user->getOriginal('email');
            $emails->claim($user->email, function () use ($user, $from) {
                $user->save();
                AuditLog::record($user, $user->company_id, 'user.email_changed', $user, ['from' => $from, 'to' => $user->email]);
            }, $user);
            // The admin area needs a verified email, so the link goes out at once
            $user->sendEmailVerificationNotification();
        } else {
            $user->save();
        }

        Toast::success('Profile updated successfully!');

        return Redirect::route('profile.edit');
    }

    /**
     * Close the user's account (PO-17): only a company owner may, and the account and its
     * company are deactivated, not deleted, so no driver, vehicle or billing record is lost
     * (the same rule as deactivating a company, PO-13). Login refuses inactive users.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canCloseAccount($user), 403);

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        // A deactivated company blocks all its users (PO-13); the owner's login is closed too
        DB::transaction(function () use ($user) {
            $user->update(['status' => 'inactive']);
            $company = Company::query()->whereKey($user->company_id)->lockForUpdate()->firstOrFail();
            $from = $company->status;
            $company->forceFill(['status' => 'deactivated', 'status_changed_at' => now(), 'status_reason' => 'Closed by the owner'])->save();
            AuditLog::record($user, $company->id, 'user.deactivated', $user);
            AuditLog::record($user, $company->id, 'company.status_changed', $company, ['from' => $from, 'to' => 'deactivated', 'reason' => 'Closed by the owner']);
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Only the company's owner sees and may use "Close account"; other users don't.
     */
    private function canCloseAccount(User $user): bool
    {
        return Company::query()->whereKey($user->company_id)->where('owner_user_id', $user->id)->exists();
    }
}
