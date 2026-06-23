<?php

namespace App\Http\Controllers;

use App\Models\InviteLink;
use App\Support\OnboardingBackgroundImages;
use Illuminate\Http\Response;

class InviteOnboardingController extends Controller
{
    public function show(string $token)
    {
        $invite = InviteLink::query()
            ->where('token_hash', InviteLink::hashToken($token))
            ->first();

        if ($invite === null) {
            return response()->view('onboarding.invite-invalid', status: Response::HTTP_NOT_FOUND);
        }

        if (! $invite->isValid()) {
            return response()->view('onboarding.invite-invalid', status: Response::HTTP_GONE);
        }

        return view('onboarding.invite', [
            'token' => $token,
            'backgroundImages' => OnboardingBackgroundImages::resolve(),
        ]);
    }
}
