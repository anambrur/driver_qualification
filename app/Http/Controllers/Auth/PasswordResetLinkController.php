<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // The same answer, sent before the mail, whether or not the email has an account: no
        // enumeration from the message or from the time the mail takes.
        $credentials = $request->only('email');
        dispatch(fn () => Password::sendResetLink($credentials))->afterResponse();

        return back()->with('status', __('If that email has an account, a reset link is on its way.'));
    }
}
