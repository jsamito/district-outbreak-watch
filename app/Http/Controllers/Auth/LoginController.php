<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([...$credentials, 'active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $request->user()->forceFill(['last_login_at' => now()])->save();
            Audit::log(Audit::AUTH, 'login');

            return redirect()->intended(route('dashboard'));
        }

        Audit::log(Audit::AUTH, 'login_failed', null, ['email' => $credentials['email']]);

        return back()
            ->withErrors(['email' => 'The email or password is not correct, or the account is inactive.'])
            ->onlyInput('email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Audit::log(Audit::AUTH, 'logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
