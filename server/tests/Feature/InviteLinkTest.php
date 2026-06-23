<?php

namespace Tests\Feature;

use App\Models\InviteLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesInviteLinks;
use Tests\TestCase;

class InviteLinkTest extends TestCase
{
    use CreatesInviteLinks;
    use RefreshDatabase;

    public function test_valid_invite_token_opens_chapter_one(): void
    {
        $token = $this->createInviteLinkToken(['name' => 'Chapter 1 Test']);

        $response = $this->get('/invite/'.$token);

        $response->assertOk();
        $response->assertSee('Someone believes you belong here', false);
        $response->assertSee('Begin Your Journey', false);
    }

    public function test_invalid_invite_token_is_rejected(): void
    {
        $response = $this->get('/invite/'.bin2hex(random_bytes(32)));

        $response->assertNotFound();
        $response->assertSee('This invitation is no longer valid', false);
    }

    public function test_expired_invite_token_is_rejected(): void
    {
        $token = $this->createInviteLinkToken([
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->get('/invite/'.$token);

        $response->assertGone();
        $response->assertSee('This invitation is no longer valid', false);
    }

    public function test_revoked_invite_token_is_rejected(): void
    {
        $token = $this->createInviteLinkToken([
            'revoked_at' => Carbon::now(),
        ]);

        $response = $this->get('/invite/'.$token);

        $response->assertGone();
        $response->assertSee('This invitation is no longer valid', false);
    }

    public function test_raw_token_is_not_stored(): void
    {
        $token = bin2hex(random_bytes(32));

        InviteLink::create([
            'name' => 'Hash only',
            'token_hash' => InviteLink::hashToken($token),
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $row = DB::table('invite_links')->first();

        $this->assertNotNull($row);
        $this->assertSame(InviteLink::hashToken($token), $row->token_hash);
        $this->assertNotSame($token, $row->token_hash);

        $serialized = json_encode($row, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($token, $serialized);
    }

    public function test_make_invite_command_creates_invite_link_with_hashed_token(): void
    {
        $exitCode = Artisan::call('esb:make-invite', [
            'name' => 'Chapter 1 Test',
            '--days' => 30,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, InviteLink::count());

        $invite = InviteLink::first();
        $this->assertNotNull($invite);
        $this->assertSame('Chapter 1 Test', $invite->name);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $invite->token_hash);

        $output = Artisan::output();
        $this->assertStringContainsString('Invite link created:', $output);
        $this->assertStringContainsString('/invite/', $output);
        $this->assertStringContainsString('Expires:', $output);
        $this->assertStringNotContainsString($invite->token_hash, $output);
    }

    public function test_make_invite_command_supports_thirty_day_expiry(): void
    {
        Carbon::setTestNow('2026-06-23 14:30:00');

        try {
            Artisan::call('esb:make-invite', [
                'name' => 'Chapter 1 Test',
                '--days' => 30,
            ]);

            $invite = InviteLink::first();
            $this->assertNotNull($invite);
            $this->assertTrue($invite->expires_at->equalTo(Carbon::parse('2026-07-23 14:30:00')));

            $this->assertStringContainsString('2026-07-23 14:30', Artisan::output());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_visiting_invite_url_does_not_create_users_or_people(): void
    {
        $token = $this->createInviteLinkToken();

        $tables = ['users'];

        if (Schema::hasTable('people')) {
            $tables[] = 'people';
        }

        foreach ($tables as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Expected zero rows in {$table} before request.");
        }

        $this->get('/invite/'.$token)->assertOk();

        foreach ($tables as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Expected zero rows in {$table} after invite request.");
        }

        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_no_seed_data_added_for_invite_links(): void
    {
        $this->assertFileDoesNotExist(database_path('seeders/InviteLinkSeeder.php'));

        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertNotFalse($seeder);
        $this->assertStringNotContainsString('InviteLinkSeeder', $seeder);
        $this->assertStringNotContainsString('invite_links', $seeder);
    }
}
