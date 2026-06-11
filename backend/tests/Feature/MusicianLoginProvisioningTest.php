<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Musician;
use App\Models\User;
use App\Services\MusicianLoginPasswordGenerator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class MusicianLoginProvisioningTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_musician_can_be_created_without_password_or_email(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $response = $this->actingAs($user)->post(route('musicians.store'), [
            'first_name' => 'Pat',
            'last_name' => 'Smith',
        ]);

        $response->assertRedirect(route('people.index'));
        $response->assertSessionHas('status');

        $musician = Musician::query()->where('first_name', 'Pat')->firstOrFail();
        $this->assertNull($musician->email);
        $this->assertNull($musician->user_id);
        $this->assertDatabaseMissing('users', ['name' => 'Pat Smith']);
    }

    public function test_musician_login_account_receives_generated_password_and_hashed_storage(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $generator = $this->createMock(MusicianLoginPasswordGenerator::class);
        $generator->method('generate')->willReturn('Ab1!xyZ9');
        $generator->method('satisfiesRequirements')->willReturn(true);
        $this->app->instance(MusicianLoginPasswordGenerator::class, $generator);

        $response = $this->actingAs($user)->post(route('musicians.store'), [
            'first_name' => 'Login',
            'last_name' => 'Musician',
            'email' => 'login.musician@example.test',
            'create_login_account' => '1',
        ]);

        $response->assertRedirect(route('people.index'));
        $response->assertSessionHas('generated_musician_password', 'Login created for login.musician@example.test. One-time password: Ab1!xyZ9');

        $musician = Musician::query()->where('email', 'login.musician@example.test')->firstOrFail();
        $this->assertNotNull($musician->user_id);

        $loginUser = User::query()->findOrFail($musician->user_id);
        $this->assertSame('login.musician@example.test', $loginUser->email);
        $this->assertNotSame('Ab1!xyZ9', $loginUser->password);
        $this->assertTrue(Hash::check('Ab1!xyZ9', $loginUser->password));
        $this->assertTrue($loginUser->hasRole('musician'));
    }

    public function test_generated_password_meets_character_class_requirements(): void
    {
        $generator = new MusicianLoginPasswordGenerator;

        $password = $generator->generate();

        $this->assertSame(8, strlen($password));
        $this->assertTrue($generator->satisfiesRequirements($password));
    }

    public function test_existing_musician_creation_flow_still_works_with_optional_email_only(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $response = $this->actingAs($user)->post(route('musicians.store'), [
            'first_name' => 'Ed',
            'last_name' => 'Operator',
            'email' => 'ed.operator@example.test',
        ]);

        $response->assertRedirect(route('people.index'));

        $this->assertDatabaseHas('musicians', [
            'first_name' => 'Ed',
            'last_name' => 'Operator',
            'email' => 'ed.operator@example.test',
            'user_id' => null,
        ]);
    }

    public function test_create_login_requires_email(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $response = $this->actingAs($user)->post(route('musicians.store'), [
            'first_name' => 'No',
            'last_name' => 'Email',
            'create_login_account' => '1',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
