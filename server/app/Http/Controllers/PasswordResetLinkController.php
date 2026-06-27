<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink(
            $request->only('email'),
            function ($user, string $token): void {
                if (! $user->is_active) {
                    return;
                }

                $user->sendPasswordResetNotification($token);
            },
        );

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Please wait before requesting another reset link.',
                ]);
        }

        return back()->with(
            'status',
            'If an account exists for that email, we have emailed a password reset link.',
        );
    }
}
