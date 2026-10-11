<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Company;
use App\Models\User;
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
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'canCloseAccount' => $this->canCloseAccount($request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        DB::transaction(function () use ($user, $emailChanged) {
            $user->save();

            // The company's contact email is the owner's login email (PO-22)
            if ($emailChanged) {
                Company::where('user_id', $user->id)->update(['email' => $user->email]);
            }
        });

        toastr()->success('Profile updated successfully!');
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

        DB::transaction(function () use ($user) {
            $user->update(['status' => 'inactive']);
            Company::where('user_id', $user->id)->update(['status' => 'inactive']);
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Only company owners see and may use "Close account"; sub-users and super-admins don't.
     */
    private function canCloseAccount(User $user): bool
    {
        return $user->hasRole('company')
            && ! $user->hasRole('super-admin')
            && Company::where('user_id', $user->id)->exists();
    }
}
