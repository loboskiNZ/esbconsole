<?php

namespace Tests\Feature;

use App\Models\Show;
use App\Models\User;
use App\Services\StudioShowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioShowsTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal.band_id' => 1]);
        $this->ensurePortalBand();
    }

    public function test_shows_card_lists_existing_shows(): void
    {
        $director = $this->createDirectorUser();
        $this->seedShow([
            'name' => 'Summer Tour',
            'scheduled_at' => '2026-08-15 20:00:00',
            'venue_location' => 'Dunedin Town Hall',
            'lifecycle_state' => Show::STATE_PLANNED,
        ]);

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Summer Tour', false)
            ->assertSee('Dunedin Town Hall', false)
            ->assertSee('Planned', false)
            ->assertDontSee('Upcoming shows', false);
    }

    public function test_shows_card_shows_empty_state_when_no_shows_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('No shows yet.', false)
            ->assertDontSee('Upcoming shows', false);
    }

    public function test_director_sees_add_show_in_director_tools(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Add Show', false)
            ->assertSee(route('studio.shows.create'), false);
    }

    public function test_musician_does_not_see_add_show_in_director_tools(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($musician)->get('/studio')
            ->assertOk()
            ->assertDontSee('Add Show', false)
            ->assertDontSee(route('studio.shows.create'), false);
    }

    public function test_musician_can_view_show_list_and_overview(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $show = $this->seedShow(['name' => 'Musician Visible Show']);

        $this->actingAs($musician)->get('/studio/shows')
            ->assertOk()
            ->assertSee('Musician Visible Show', false);

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Musician Visible Show', false);
    }

    public function test_musician_cannot_access_create_show_form(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $this->actingAs($musician)->get('/studio/shows/create')->assertForbidden();
    }

    public function test_director_can_create_show(): void
    {
        $director = $this->createDirectorUser();

        $this->beginShowsSession($director)
            ->post('/studio/shows', [
                '_token' => session()->token(),
                'name' => 'Winter Gala',
                'scheduled_at' => '2026-09-01T19:30',
                'venue_location' => 'Regent Theatre',
                'notes' => 'Full horn section.',
                'lifecycle_state' => Show::STATE_PLANNED,
            ])
            ->assertRedirect();

        $show = Show::query()->where('name', 'Winter Gala')->first();

        $this->assertNotNull($show);
        $this->assertSame('Regent Theatre', $show->venue_location);
        $this->assertSame('Full horn section.', $show->description);
        $this->assertSame(Show::STATE_PLANNED, $show->lifecycle_state);
        $this->assertNotNull($show->ableton_show_file_id);
    }

    public function test_show_persists_after_refresh(): void
    {
        $director = $this->createDirectorUser();

        $this->beginShowsSession($director)
            ->post('/studio/shows', [
                '_token' => session()->token(),
                'name' => 'Persisted Show',
                'lifecycle_state' => Show::STATE_DRAFT,
            ])
            ->assertRedirect();

        $show = Show::query()->where('name', 'Persisted Show')->firstOrFail();

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Persisted Show', false);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Persisted Show', false)
            ->assertSee('Draft', false);
    }

    public function test_clicking_show_opens_overview_page_with_details(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow([
            'name' => 'Overview Show',
            'scheduled_at' => '2026-10-05 18:00:00',
            'venue_location' => 'Queens Gardens',
            'notes' => 'Outdoor set.',
            'lifecycle_state' => Show::STATE_PLANNED,
        ]);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Overview Show', false)
            ->assertSee('Queens Gardens', false)
            ->assertSee('Outdoor set.', false)
            ->assertSee('Planned', false)
            ->assertSee('Playlist management will appear here', false)
            ->assertSee('Performance scheduling will appear here', false)
            ->assertSee('Technical requirements will appear here', false);
    }

    public function test_show_management_never_deletes_show_rows(): void
    {
        $director = $this->createDirectorUser();
        $existing = $this->seedShow(['name' => 'Existing Show']);
        $countBefore = DB::table('shows')->count();

        $this->beginShowsSession($director)
            ->post('/studio/shows', [
                '_token' => session()->token(),
                'name' => 'Another Show',
            ])
            ->assertRedirect();

        $this->assertSame($countBefore + 1, DB::table('shows')->count());
        $this->assertDatabaseHas('shows', ['id' => $existing->id, 'name' => 'Existing Show']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedShow(array $attributes = []): Show
    {
        return app(StudioShowService::class)->createShow(array_merge([
            'name' => 'Seed Show',
            'lifecycle_state' => Show::STATE_DRAFT,
        ], $attributes));
    }

    private function beginShowsSession(User $director): self
    {
        $this->actingAs($director)->get('/studio/shows/create')->assertOk();

        return $this;
    }
}
