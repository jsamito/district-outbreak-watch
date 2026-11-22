<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.password', ['title' => 'Change password', 'forced' => (bool) $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->letters()->mixedCase()->numbers()],
        ], ['password.different' => 'Choose a password you have not used before.']);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false, 'password_changed_at' => now()])->save();
        $request->session()->regenerate();
        Audit::log(Audit::AUTH, 'password_changed');

        return redirect()->route('dashboard')->with('status', 'Your password has been changed.');
    }
}
