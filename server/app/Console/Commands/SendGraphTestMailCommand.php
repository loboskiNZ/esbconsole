<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendGraphTestMailCommand extends Command
{
    protected $signature = 'esb:send-test-mail {email : Recipient email address}';

    protected $description = 'Send a test message through the configured Microsoft Graph mail transport';

    public function handle(): int
    {
        $recipient = trim($this->argument('email'));

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid recipient email address.');

            return self::FAILURE;
        }

        if (config('mail.default') !== 'graph') {
            $this->warn('MAIL_MAILER is not set to graph (current: '.config('mail.default').').');
        }

        Mail::raw(
            'This is a Microsoft Graph test message from ESB Cloud Studio.',
            function ($message) use ($recipient): void {
                $message->to($recipient)
                    ->subject('ESB Cloud Studio Graph mail test');
            },
        );

        $this->line('Test message dispatched to '.$recipient.'.');

        return self::SUCCESS;
    }
}
