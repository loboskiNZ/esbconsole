<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOnboardingRequest;
use App\Models\InviteLink;
use App\Services\OnboardingRegistrationService;
use Illuminate\Http\JsonResponse;

class OnboardingRegistrationController extends Controller
{
    public function store(
        StoreOnboardingRequest $request,
        string $token,
        OnboardingRegistrationService $registrationService,
    ): JsonResponse {
        if ($request->filled('honeypot')) {
            abort(404);
        }

        $inviteLink = InviteLink::query()
            ->where('token_hash', InviteLink::hashToken($token))
            ->first();

        if ($inviteLink === null || ! $inviteLink->canAcceptAnotherRegistration()) {
            return response()->json([
                'message' => 'This invitation is no longer valid.',
            ], 410);
        }

        $registrationService->register($inviteLink, $request->validatedPayload());

        return response()->json([
            'message' => 'Your Studio account has been created.',
            'redirect' => url('/?onboarding=complete'),
        ]);
    }
}
