<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class FoundationSliceTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_shows(): void
    {
        $this->get(route('shows.index'))->assertRedirect(route('login'));
    }

    public function test_non_director_cannot_access_shows(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('shows.index'))
            ->assertForbidden();
    }

    public function test_director_can_login_and_reach_shows(): void
    {
        $user = $this->createDirectorUser([
            'email' => 'director@example.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('shows.index'));

        $this->assertAuthenticated();
    }

    public function test_band_context_resolves_on_show_list(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create(['name' => 'Test Band Context']);

        $response = $this->actingAs($user)->get(route('shows.index'));

        $response->assertOk();
        $response->assertSee('Test Band Context');
    }

    public function test_show_list_loads_band_shows(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id, 'name' => 'Slice Test Show']);

        $response = $this->actingAs($user)->get(route('shows.index'));

        $response->assertOk();
        $response->assertSee('Slice Test Show');
    }

    public function test_active_show_selection_and_playlist_order(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id, 'name' => 'Playlist Show']);

        $songB = Song::factory()->create(['band_id' => $band->id, 'name' => 'Second Song']);
        $songA = Song::factory()->create(['band_id' => $band->id, 'name' => 'First Song']);

        ShowPlaylistItem::create(['show_id' => $show->id, 'song_id' => $songA->id, 'position' => 1]);
        ShowPlaylistItem::create(['show_id' => $show->id, 'song_id' => $songB->id, 'position' => 2]);

        $this->actingAs($user)
            ->post(route('shows.activate', $show))
            ->assertRedirect(route('playlist.show', $show));

        $response = $this->actingAs($user)->get(route('playlist.show', $show));

        $response->assertOk();
        $response->assertSeeInOrder(['First Song', 'Second Song']);
        $this->assertEquals($show->id, session('active_show_id'));
    }
}
