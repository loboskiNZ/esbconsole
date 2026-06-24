<?php

namespace App\Http\Controllers;

use App\Models\InviteLink;
use App\Support\OnboardingBackgroundImages;
use Illuminate\Http\Response;

class InviteOnboardingController extends Controller
{
    public function show(string $token)
    {
        $invite = InviteLink::findValidByToken($token);

        if ($invite === null) {
            $exists = InviteLink::query()
                ->where('token_hash', InviteLink::hashToken($token))
                ->exists();

            return response()->view(
                'onboarding.invite-invalid',
                status: $exists ? Response::HTTP_GONE : Response::HTTP_NOT_FOUND,
            );
        }

        return view('onboarding.invite', [
            'token' => $token,
            'backgroundImages' => OnboardingBackgroundImages::resolve(),
        ]);
    }
}
