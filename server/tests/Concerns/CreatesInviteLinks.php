<?php

namespace Tests\Concerns;

use App\Models\InviteLink;
use Illuminate\Support\Carbon;

trait CreatesInviteLinks
{
    protected function createInviteLinkToken(array $attributes = []): string
    {
        $token = InviteLink::generateRawToken();

        InviteLink::create(array_merge([
            'name' => 'Test Invite',
            'token_hash' => InviteLink::hashToken($token),
            'expires_at' => Carbon::now()->addDays(30),
        ], $attributes));

        return $token;
    }
}
