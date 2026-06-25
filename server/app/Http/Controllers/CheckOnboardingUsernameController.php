<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PortalUsername;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckOnboardingUsernameController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $username = PortalUsername::normalize((string) $request->input('username', ''));

        if ($username === '' || strlen($username) < 3 || strlen($username) > 32 || ! preg_match('/^[a-z0-9]+$/', $username)) {
            return response()->json([
                'available' => false,
                'username' => $username,
                'message' => 'Username must be 3–32 characters and contain only letters and numbers.',
            ]);
        }

        $available = ! User::query()->where('username', $username)->exists();

        return response()->json([
            'available' => $available,
            'username' => $username,
            'message' => $available ? null : 'That username is already taken.',
        ]);
    }
}
