<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) request()->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $this->safeResetErrorMessage($status)]);
        }

        return redirect()
            ->route('home', ['password_reset' => 'complete'])
            ->with('password_reset', true);
    }

    private function safeResetErrorMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => 'This password reset link is invalid or has expired.',
            Password::INVALID_USER => 'This password reset link is invalid or has expired.',
            default => 'Unable to reset password. Please request a new reset link.',
        };
    }
}
