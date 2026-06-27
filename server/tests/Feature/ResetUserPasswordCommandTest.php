<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class ResetUserPasswordCommandTest extends TestCase
{
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal.band_id' => 1]);
        $this->ensurePortalBand();
    }

    public function test_operator_can_reset_user_password_with_generated_password(): void
    {
        $user = User::factory()->create([
            'username' => 'loboski',
            'password' => 'OldPassword1!',
        ]);

        $oldHash = $user->password;

        $this->artisan('esb:reset-user-password', [
            'username' => 'loboski',
            '--generate' => true,
        ])->assertSuccessful();

        $user->refresh();

        $this->assertNotSame($oldHash, $user->password);
        $this->assertFalse(Hash::check('OldPassword1!', $user->password));
    }

    public function test_reset_rejects_weak_password(): void
    {
        User::factory()->create(['username' => 'loboski']);

        $this->artisan('esb:reset-user-password', [
            'username' => 'loboski',
            '--password' => 'weak',
        ])->assertFailed();
    }
}
