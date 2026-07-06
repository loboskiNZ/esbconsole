<?php

namespace Tests\Feature;

use App\Models\Library\Song;
use App\Models\Show;
use App\Models\User;
use App\Services\StudioShowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioBreadcrumbsTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal.band_id' => 1,
            'portal.library_connection' => 'sqlite',
        ]);
        $this->ensurePortalBand();
    }

    public function test_studio_home_renders_brand_link_and_current_breadcrumb(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get(route('studio'))
            ->assertOk()
            ->assertSee('esb-studio__brand-link', false)
            ->assertSee('href="'.route('studio').'"', false)
            ->assertSee('esb-studio__breadcrumbs', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Studio</span>', false);
    }

    public function test_songs_index_renders_parent_and_current_breadcrumbs(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get(route('songs.index'))
            ->assertOk()
            ->assertSee('esb-studio__breadcrumbs-link">Studio<', false)
            ->assertSee('href="'.route('studio').'"', false)
            ->assertSee('Songs</span>', false);
    }

    public function test_song_edit_renders_song_title_in_breadcrumb_trail(): void
    {
        $director = $this->createDirectorUser();
        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => 'Midnight Train',
            'song_code' => '042',
            'status' => Song::STATUS_READY,
        ]);

        $this->actingAs($director)->get(route('songs.edit', $song))
            ->assertOk()
            ->assertSee('href="'.route('songs.index').'"', false)
            ->assertSee('Midnight Train</span>', false);
    }

    public function test_show_detail_renders_show_name_in_breadcrumb_trail(): void
    {
        $director = $this->createDirectorUser();
        $show = app(StudioShowService::class)->createShow([
            'name' => 'Summer Festival Set',
            'lifecycle_state' => Show::STATE_DRAFT,
        ]);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('href="'.route('studio.shows.index').'"', false)
            ->assertSee('Summer Festival Set</span>', false);
    }

    public function test_calendar_page_uses_schedule_breadcrumb_label(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get(route('studio.calendar.index'))
            ->assertOk()
            ->assertSee('Schedule</span>', false);
    }

    public function test_profile_edit_renders_brand_link_and_profile_breadcrumb(): void
    {
        $user = User::factory()->create();
        $this->assignMusicianRole($user);

        $this->actingAs($user)->get(route('studio.profile.edit'))
            ->assertOk()
            ->assertSee('esb-studio__brand-link', false)
            ->assertSee('Profile</span>', false);
    }
}
