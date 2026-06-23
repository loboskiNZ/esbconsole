<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'username' => $request->normalizedUsername(),
            'password' => $request->input('password'),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, false)) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'login' => 'These credentials do not match our records.',
                ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('studio'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }
}
