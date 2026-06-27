<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class PortalPasswordResetTest extends TestCase
{
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePortalBand();
    }

    public function test_login_page_shows_forgotten_password_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('password.request'), false)
            ->assertSee('Forgot your password?', false);
    }

    public function test_reset_request_page_loads(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?', false)
            ->assertSee('Email reset link', false);
    }

    public function test_submitting_valid_email_sends_reset_notification(): void
    {
        Notification::fake();

        $user = $this->createPortalUser([
            'email' => 'reset@example.com',
        ]);

        $this->get(route('password.request'));

        $this->post(route('password.email'), [
            '_token' => session()->token(),
            'email' => 'reset@example.com',
        ])->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_submitting_unknown_email_shows_safe_generic_response(): void
    {
        Notification::fake();

        $this->get(route('password.request'));

        $this->post(route('password.email'), [
            '_token' => session()->token(),
            'email' => 'missing@example.com',
        ])->assertRedirect()
            ->assertSessionHas('status', 'If an account exists for that email, we have emailed a password reset link.')
            ->assertSessionDoesntHaveErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_link_opens_reset_form(): void
    {
        $token = 'test-reset-token';

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset@example.com',
        ]))
            ->assertOk()
            ->assertSee('Choose a new password', false)
            ->assertSee('reset@example.com', false)
            ->assertSee($token, false);
    }

    public function test_password_validation_uses_existing_rules(): void
    {
        $user = $this->createPortalUser([
            'email' => 'reset@example.com',
        ]);

        $token = Password::createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset@example.com',
        ]));

        $this->post(route('password.update'), [
            '_token' => session()->token(),
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_confirmation_is_required(): void
    {
        $user = $this->createPortalUser([
            'email' => 'reset@example.com',
        ]);

        $token = Password::createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset@example.com',
        ]));

        $this->post(route('password.update'), [
            '_token' => session()->token(),
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password2!',
        ])->assertSessionHasErrors('password');
    }

    public function test_successful_reset_updates_password_hash(): void
    {
        $user = $this->createPortalUser([
            'email' => 'reset@example.com',
            'password' => 'OldPassword1!',
        ]);

        $oldHash = $user->password;
        $token = Password::createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset@example.com',
        ]));

        $this->post(route('password.update'), [
            '_token' => session()->token(),
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertRedirect(route('home', ['password_reset' => 'complete'], absolute: false));

        $user->refresh();

        $this->assertNotSame($oldHash, $user->password);
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
    }

    public function test_token_cannot_be_reused(): void
    {
        $user = $this->createPortalUser([
            'email' => 'reset@example.com',
            'password' => 'OldPassword1!',
        ]);

        $token = Password::createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset@example.com',
        ]));

        $payload = [
            '_token' => session()->token(),
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ];

        $this->post(route('password.update'), $payload)->assertRedirect();

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset@example.com',
        ]));

        $payload['_token'] = session()->token();

        $this->post(route('password.update'), $payload)
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_log_in_with_new_password(): void
    {
        $user = $this->createPortalUser([
            'email' => 'reset@example.com',
            'username' => 'resetplayer',
            'password' => 'OldPassword1!',
        ]);

        $token = Password::createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset@example.com',
        ]));

        $this->post(route('password.update'), [
            '_token' => session()->token(),
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $this->get('/');

        $this->post('/login', [
            '_token' => session()->token(),
            'username' => $user->username,
            'password' => 'NewPassword1!',
        ])->assertRedirect(route('studio'));

        $this->assertAuthenticated();
    }

    public function test_old_password_no_longer_works_after_reset(): void
    {
        $user = $this->createPortalUser([
            'email' => 'reset@example.com',
            'username' => 'resetplayer2',
            'password' => 'OldPassword1!',
        ]);

        $token = Password::createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset@example.com',
        ]));

        $this->post(route('password.update'), [
            '_token' => session()->token(),
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $this->get('/');

        $this->post('/login', [
            '_token' => session()->token(),
            'username' => $user->username,
            'password' => 'OldPassword1!',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_home_shows_password_reset_success_message(): void
    {
        $this->get('/?password_reset=complete')
            ->assertOk()
            ->assertSee('Your password has been reset. Log in with your new credentials.', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPortalUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'portal@example.com',
            'username' => 'portaluser'.random_int(1000, 9999),
            'is_active' => true,
        ], $overrides));
    }
}
