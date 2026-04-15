<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

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
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:' . User::class,
                'unique:companies,email'
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        try {
            DB::beginTransaction();
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $slug = Str::slug($request->name) . '-' . Str::random(4);

            Company::create([
                'user_id' => $user->id,
                'company_name' => $request->name,
                'email' => $request->email,
                'slug' => $slug,
                'status' => 'active',
            ]);

            $user->assignRole('company');
            DB::commit();
            event(new Registered($user));
            Auth::login($user);
            return redirect(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Registration Failed: ' . $e->getMessage());

            toastr()->error('An error occurred during registration. Please try again.');
            return back()->withInput();
        }
    }
}
