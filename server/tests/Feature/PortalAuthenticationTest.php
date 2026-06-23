<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
use App\Models\InviteLink;
use App\Models\InviteLinkAcceptance;
use App\Models\Person;
use App\Models\User;
use App\Support\OnboardingHumanCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesInviteLinks;
use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    use CreatesInviteLinks;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validOnboardingPayload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'shadowplayer1',
            'password' => 'Password1!',
            'password_confirm' => 'Password1!',
            'human_answer' => 8,
            'honeypot' => '',
            'first_name' => 'Ed',
            'middle_name' => 'J',
            'surname' => 'Musician',
            'stage_name' => 'Shadow Player',
            'primary_instrument' => 'scaffold-vocals',
            'additional_instruments' => ['scaffold-keys'],
            'email' => 'shadow@example.com',
            'country' => 'New Zealand',
            'country_iso3' => 'NZL',
            'city' => 'Dunedin',
            'telephone' => '+64 21 000 0000',
        ], $overrides);
    }

    private function postOnboarding(string $token, array $overrides = [], int $humanAnswer = 8): \Illuminate\Testing\TestResponse
    {
        $this->withSession([
            OnboardingHumanCheck::SESSION_KEY => [
                'answer' => $humanAnswer,
                'token_hash' => InviteLink::hashToken($token),
            ],
        ]);

        return $this->postJson('/invite/'.$token.'/complete', $this->validOnboardingPayload(array_merge([
            'human_answer' => $humanAnswer,
        ], $overrides)));
    }

    public function test_onboarding_human_check_rejects_wrong_answer(): void
    {
        $token = $this->createInviteLinkToken();

        $this->withSession([
            OnboardingHumanCheck::SESSION_KEY => [
                'answer' => 8,
                'token_hash' => InviteLink::hashToken($token),
            ],
        ]);

        $response = $this->postJson('/invite/'.$token.'/complete', $this->validOnboardingPayload([
            'human_answer' => 99,
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('human_answer');
        $this->assertSame(0, User::count());
    }

    public function test_onboarding_human_check_accepts_correct_answer(): void
    {
        $token = $this->createInviteLinkToken();

        $this->postOnboarding($token, [], 11)->assertOk();

        $this->assertSame(1, User::count());
    }

    public function test_onboarding_requires_valid_invite(): void
    {
        $token = bin2hex(random_bytes(16));
        $response = $this->postOnboarding($token);

        $response->assertStatus(410);
        $this->assertSame(0, Person::count());
        $this->assertSame(0, User::count());
    }

    public function test_onboarding_creates_person_user_and_links_them(): void
    {
        $token = $this->createInviteLinkToken();

        $response = $this->postOnboarding($token);

        $response->assertOk();
        $this->assertSame(1, Person::count());
        $this->assertSame(1, User::count());

        $person = Person::first();
        $user = User::first();

        $this->assertNotNull($person);
        $this->assertNotNull($user);
        $this->assertSame($person->id, $user->person_id);
        $this->assertSame('Ed', $person->legal_first_name);
        $this->assertSame('Musician', $person->legal_last_name);
        $this->assertSame('shadow@example.com', $person->email);
        $this->assertNotContains('password', Schema::getColumnListing('people'));
    }

    public function test_username_is_lowercase_normalized_on_registration(): void
    {
        $token = $this->createInviteLinkToken();

        $this->postOnboarding($token, [
            'username' => 'StageName99',
        ])->assertOk();

        $this->assertSame('stagename99', User::first()->username);
    }

    public function test_username_login_is_case_insensitive(): void
    {
        $token = $this->createInviteLinkToken();

        $this->postOnboarding($token, [
            'username' => 'caseplayer',
        ])->assertOk();

        $response = $this->post('/login', [
            'username' => 'CasePlayer',
            'password' => 'Password1!',
        ]);

        $response->assertRedirect(route('studio'));
        $this->assertAuthenticated();
    }

    public function test_duplicate_usernames_differing_only_by_case_are_rejected(): void
    {
        $token = $this->createInviteLinkToken();

        $this->postOnboarding($token, [
            'username' => 'uniqueplayer',
        ])->assertOk();

        $secondToken = $this->createInviteLinkToken();

        $response = $this->postOnboarding($secondToken, [
            'username' => 'UniquePlayer',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, User::count());
    }

    public function test_password_policy_is_enforced(): void
    {
        $token = $this->createInviteLinkToken();

        $response = $this->postOnboarding($token, [
            'password' => 'weakpass',
            'password_confirm' => 'weakpass',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, User::count());
    }

    public function test_password_is_hashed_and_raw_password_is_never_stored(): void
    {
        $token = $this->createInviteLinkToken();
        $plain = 'Password1!';

        $this->postOnboarding($token, [
            'password' => $plain,
            'password_confirm' => $plain,
        ])->assertOk();

        $user = User::first();
        $this->assertNotNull($user);
        $this->assertNotSame($plain, $user->password);
        $this->assertTrue(Hash::check($plain, $user->password));

        $serialized = json_encode(DB::table('users')->first(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($plain, $serialized);
    }

    public function test_person_does_not_store_login_credentials(): void
    {
        $token = $this->createInviteLinkToken();

        $this->postOnboarding($token)->assertOk();

        $columns = Schema::getColumnListing('people');

        $this->assertNotContains('username', $columns);
        $this->assertNotContains('password', $columns);
    }

    public function test_primary_and_additional_instruments_are_persisted(): void
    {
        $token = $this->createInviteLinkToken();

        $this->postOnboarding($token, [
            'primary_instrument' => 'scaffold-drums',
            'additional_instruments' => ['scaffold-percussion', 'scaffold-vocals'],
        ])->assertOk();

        $person = Person::with('instruments')->first();
        $this->assertNotNull($person);
        $this->assertCount(3, $person->instruments);

        $primary = $person->instruments->firstWhere('slug', 'scaffold-drums');
        $this->assertNotNull($primary);
        $this->assertTrue((bool) $primary->pivot->is_primary);

        $additional = $person->instruments->firstWhere('slug', 'scaffold-percussion');
        $this->assertNotNull($additional);
        $this->assertFalse((bool) $additional->pivot->is_primary);
    }

    public function test_shared_invite_can_create_multiple_acceptances(): void
    {
        $token = $this->createInviteLinkToken(['name' => 'Shared Invite']);

        $this->postOnboarding($token, [
            'username' => 'playerone',
            'email' => 'one@example.com',
        ])->assertOk();

        $this->postOnboarding($token, [
            'username' => 'playertwo',
            'email' => 'two@example.com',
            'stage_name' => 'Player Two',
        ])->assertOk();

        $this->assertSame(2, Person::count());
        $this->assertSame(2, User::count());
        $this->assertSame(2, InviteLinkAcceptance::count());
        $this->assertSame(2, InviteLink::first()->used_count);
    }

    public function test_expired_invite_cannot_complete_onboarding(): void
    {
        $token = $this->createInviteLinkToken([
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $this->postOnboarding($token)
            ->assertStatus(410);

        $this->assertSame(0, Person::count());
    }

    public function test_revoked_invite_cannot_complete_onboarding(): void
    {
        $token = $this->createInviteLinkToken([
            'revoked_at' => Carbon::now(),
        ]);

        $this->postOnboarding($token)
            ->assertStatus(410);

        $this->assertSame(0, Person::count());
    }

    public function test_studio_redirects_unauthenticated_users_to_login(): void
    {
        $response = $this->get('/studio');

        $response->assertRedirect('/');
    }

    public function test_authenticated_users_can_access_studio(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/studio');

        $response->assertOk();
        $response->assertSee('The Studio', false);
        $response->assertSee($user->username, false);
    }

    public function test_login_with_username_and_password_works(): void
    {
        $user = User::factory()->create([
            'username' => 'logintest',
            'password' => 'Password1!',
        ]);

        $response = $this->post('/login', [
            'username' => 'logintest',
            'password' => 'Password1!',
        ]);

        $response->assertRedirect(route('studio'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_login_does_not_reveal_username_existence(): void
    {
        $response = $this->from('/')->post('/login', [
            'username' => 'missinguser',
            'password' => 'Password1!',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('login');
        $response->assertSessionHas('errors');

        $message = session('errors')->first('login');
        $this->assertSame('These credentials do not match our records.', $message);
    }

    public function test_logout_works(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/studio')->assertOk();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_register_and_signup_routes_remain_unavailable(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
        $this->get('/signup')->assertNotFound();
    }

    public function test_no_seed_data_added_for_portal_authentication(): void
    {
        $this->assertFileDoesNotExist(database_path('seeders/PortalAuthenticationSeeder.php'));

        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertNotFalse($seeder);
        $this->assertStringNotContainsString('PortalAuthenticationSeeder', $seeder);
    }

    public function test_onboarding_completion_redirects_to_login_not_studio(): void
    {
        $token = $this->createInviteLinkToken();

        $response = $this->postOnboarding($token);

        $response->assertOk();
        $response->assertJsonPath('redirect', url('/?onboarding=complete'));
        $this->assertGuest();
    }

    public function test_failed_login_restores_password_step_with_username(): void
    {
        User::factory()->create([
            'username' => 'knownuser',
            'password' => 'Password1!',
        ]);

        $response = $this->from('/')->post('/login', [
            'username' => 'knownuser',
            'password' => 'WrongPass1!',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('login');
        $response->assertSessionHas('_old_input');

        $this->get('/')
            ->assertOk()
            ->assertSee('knownuser', false)
            ->assertSee('Enter your password', false)
            ->assertSee('type="hidden" name="username"', false);
    }

    public function test_instrument_reference_catalog_exists_without_seeders(): void
    {
        $this->assertGreaterThan(0, InstrumentReference::count());
        $this->assertDatabaseHas('instrument_reference', [
            'slug' => 'scaffold-vocals',
            'name' => 'Vocals',
        ]);
    }
}
