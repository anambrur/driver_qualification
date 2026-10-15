<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Company\CompanyProvisioner;
use App\Services\Platform\EmailIdentityService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, CompanyProvisioner $provisioner, EmailIdentityService $emails): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
                'unique:companies,email',
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        try {
            // Never also a platform login (D3a)
            $company = $emails->claim($request->email, fn (string $email) => $provisioner->provision(
                ['company_name' => $request->company_name, 'email' => $email],
                ['name' => $request->name, 'email' => $email, 'password' => Hash::make($request->password)],
            ));
            $user = $company->owner;
            event(new Registered($user));
            Auth::login($user);

            return redirect(route('dashboard', absolute: false));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Registration Failed: '.$e->getMessage());

            toastr()->error('An error occurred during registration. Please try again.');

            return back()->withInput($request->except('password', 'password_confirmation'));
        }
    }
}
