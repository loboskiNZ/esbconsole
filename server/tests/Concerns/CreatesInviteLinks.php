<?php

namespace Tests\Concerns;

use App\Models\InviteLink;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

trait CreatesInviteLinks
{
    protected function ensureInviteLinksTable(): void
    {
        if (Schema::hasTable('invite_links')) {
            if (! Schema::hasColumn('invite_links', 'token_ciphertext')) {
                Schema::table('invite_links', function (Blueprint $table): void {
                    $table->text('token_ciphertext')->nullable()->after('token_hash');
                });
            }

            return;
        }

        Schema::create('invite_links', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->text('token_ciphertext')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->timestamps();
        });
    }

    protected function createInviteLinkToken(array $attributes = []): string
    {
        $this->ensureInviteLinksTable();

        $token = InviteLink::generateRawToken();

        InviteLink::createWithToken(
            name: $attributes['name'] ?? 'Test Invite',
            rawToken: $token,
            expiresAt: $attributes['expires_at'] ?? Carbon::now()->addDays(30),
            attributes: collect($attributes)->except(['name', 'expires_at'])->all(),
        );

        return $token;
    }
}
