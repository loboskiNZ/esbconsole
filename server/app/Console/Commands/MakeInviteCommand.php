<?php

namespace App\Console\Commands;

use App\Models\InviteLink;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MakeInviteCommand extends Command
{
    protected $signature = 'esb:make-invite {name} {--days=30 : Number of days until the invite expires}';

    protected $description = 'Create a shared invite link for Chapter 1 onboarding';

    public function handle(): int
    {
        $rawToken = InviteLink::generateRawToken();
        $expiresAt = Carbon::now()->addDays((int) $this->option('days'));

        InviteLink::createWithToken(
            name: $this->argument('name'),
            rawToken: $rawToken,
            expiresAt: $expiresAt,
        );

        $url = rtrim((string) config('app.url'), '/').'/invite/'.$rawToken;

        $this->line('Invite link created:');
        $this->line('');
        $this->line($url);
        $this->line('');
        $this->line('Expires:');
        $this->line($expiresAt->format('Y-m-d H:i'));

        return self::SUCCESS;
    }
}
