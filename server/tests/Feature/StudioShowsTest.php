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

    public function test_shows_card_lists_existing_shows_by_name_and_state_only(): void
    {
        $director = $this->createDirectorUser();
        $this->seedShow([
            'name' => 'LO-FI Show',
            'lifecycle_state' => Show::STATE_PLANNED,
        ]);

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('LO-FI Show', false)
            ->assertSee('Planned', false)
            ->assertDontSee('Upcoming shows', false)
            ->assertDontSee('Dunedin Town Hall', false);
    }

    public function test_shows_card_does_not_display_dormant_schedule_or_venue_columns(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Legacy Scheduled Show']);

        DB::table('shows')->where('id', $show->id)->update([
            'scheduled_at' => '2026-08-15 20:00:00',
            'venue_location' => 'Dunedin Town Hall',
        ]);

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Legacy Scheduled Show', false)
            ->assertDontSee('15 Aug 2026', false)
            ->assertDontSee('Dunedin Town Hall', false);
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

    public function test_director_can_create_show_with_metadata_only(): void
    {
        $director = $this->createDirectorUser();

        $this->beginShowsSession($director)
            ->post('/studio/shows', [
                '_token' => session()->token(),
                'name' => 'Christmas Show',
                'description' => 'Seasonal production variant.',
                'lifecycle_state' => Show::STATE_PLANNED,
            ])
            ->assertRedirect();

        $show = Show::query()->where('name', 'Christmas Show')->first();

        $this->assertNotNull($show);
        $this->assertSame('Seasonal production variant.', $show->description);
        $this->assertSame(Show::STATE_PLANNED, $show->lifecycle_state);
        $this->assertTrue($show->is_active);
        $this->assertNull($show->scheduled_at);
        $this->assertNull($show->venue_location);
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

    public function test_clicking_show_opens_overview_with_production_placeholders(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow([
            'name' => 'Festival Set',
            'description' => 'Outdoor festival production.',
            'lifecycle_state' => Show::STATE_PLANNED,
        ]);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Festival Set', false)
            ->assertSee('Outdoor festival production.', false)
            ->assertSee('Planned', false)
            ->assertSee('Playlist', false)
            ->assertSee('Performances', false)
            ->assertSee('Ableton', false)
            ->assertSee('X32', false)
            ->assertSee('Technical', false)
            ->assertSee('Files', false)
            ->assertDontSee('Venue / location', false)
            ->assertDontSee('Date/time', false);
    }

    public function test_dormant_schedule_and_venue_columns_are_preserved_on_existing_rows(): void
    {
        $director = $this->createDirectorUser();
        $existing = $this->seedShow(['name' => 'Existing Show']);

        DB::table('shows')->where('id', $existing->id)->update([
            'scheduled_at' => '2026-08-15 20:00:00',
            'venue_location' => 'Preserved Venue',
        ]);

        $this->beginShowsSession($director)
            ->post('/studio/shows', [
                '_token' => session()->token(),
                'name' => 'Another Show',
            ])
            ->assertRedirect();

        $preserved = DB::table('shows')->where('id', $existing->id)->first();

        $this->assertSame('Preserved Venue', $preserved->venue_location);
        $this->assertSame('2026-08-15 20:00:00', $preserved->scheduled_at);
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

    public function test_active_shows_appear_on_dashboard(): void
    {
        $director = $this->createDirectorUser();
        $this->seedShow(['name' => 'Active Dashboard Show']);

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Active Dashboard Show', false)
            ->assertSee('Edit', false)
            ->assertSee('Archive', false);
    }

    public function test_archived_shows_do_not_appear_on_dashboard(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Hidden Dashboard Show']);

        $this->archiveShowAsDirector($director, $show);

        $this->actingAs($director)
            ->get('/studio')
            ->assertOk()
            ->assertDontSee('Hidden Dashboard Show', false);
    }

    public function test_active_shows_appear_on_shows_index(): void
    {
        $director = $this->createDirectorUser();
        $this->seedShow(['name' => 'Active Index Show']);

        $this->actingAs($director)->get('/studio/shows')
            ->assertOk()
            ->assertSee('Active Index Show', false)
            ->assertSee('View Archived', false);
    }

    public function test_archived_shows_do_not_appear_on_shows_index(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Hidden Index Show']);

        $this->archiveShowAsDirector($director, $show);

        $this->actingAs($director)->get('/studio/shows')
            ->assertOk()
            ->assertDontSee('Hidden Index Show', false);
    }

    public function test_archived_shows_appear_on_archived_page(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Archived List Show']);

        $this->archiveShowAsDirector($director, $show);

        $this->actingAs($director)->get('/studio/shows/archived')
            ->assertOk()
            ->assertSee('Archived List Show', false)
            ->assertSee('Restore', false);
    }

    public function test_director_can_edit_show(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow([
            'name' => 'Original Name',
            'description' => 'Original description.',
            'lifecycle_state' => Show::STATE_DRAFT,
        ]);

        $this->actingAs($director)->get(route('studio.shows.edit', $show))->assertOk();

        $this->actingAs($director)
            ->put(route('studio.shows.update', $show), [
                '_token' => session()->token(),
                'name' => 'Updated Name',
                'description' => 'Updated description.',
                'lifecycle_state' => Show::STATE_PLANNED,
            ])
            ->assertRedirect(route('studio.shows.show', $show));

        $show->refresh();

        $this->assertSame('Updated Name', $show->name);
        $this->assertSame('Updated description.', $show->description);
        $this->assertSame(Show::STATE_PLANNED, $show->lifecycle_state);
    }

    public function test_director_can_archive_show_without_deleting_row(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Archive Target Show']);
        $countBefore = DB::table('shows')->count();

        $this->archiveShowAsDirector($director, $show);

        $this->assertSame($countBefore, DB::table('shows')->count());
        $this->assertDatabaseHas('shows', [
            'id' => $show->id,
            'name' => 'Archive Target Show',
            'is_active' => false,
        ]);
    }

    public function test_director_can_restore_archived_show(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Restore Target Show']);

        $this->archiveShowAsDirector($director, $show);

        $this->actingAs($director)->get('/studio/shows/archived')->assertOk();

        $this->actingAs($director)
            ->patch(route('studio.shows.restore', $show), ['_token' => session()->token()])
            ->assertRedirect(route('studio.shows.index'));

        $show->refresh();
        $this->assertTrue($show->is_active);

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Restore Target Show', false);
    }

    public function test_musician_cannot_edit_archive_or_restore_shows(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $show = $this->seedShow(['name' => 'Protected Show']);

        $this->actingAs($musician)->get(route('studio.shows.edit', $show))->assertForbidden();

        $this->actingAs($musician)->get('/studio/shows')->assertOk();
        $this->actingAs($musician)
            ->patch(route('studio.shows.archive', $show), ['_token' => session()->token()])
            ->assertForbidden();

        $show->update(['is_active' => false]);

        $this->actingAs($musician)->get('/studio/shows/archived')->assertForbidden();

        $this->actingAs($musician)->get('/studio/shows')->assertOk();
        $this->actingAs($musician)
            ->patch(route('studio.shows.restore', $show), ['_token' => session()->token()])
            ->assertForbidden();
    }

    public function test_edit_does_not_affect_dormant_schedule_or_venue_columns(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Dormant Columns Show']);

        DB::table('shows')->where('id', $show->id)->update([
            'scheduled_at' => '2026-08-15 20:00:00',
            'venue_location' => 'Preserved Venue',
        ]);

        $this->actingAs($director)->get(route('studio.shows.edit', $show))->assertOk();

        $this->actingAs($director)
            ->put(route('studio.shows.update', $show), [
                '_token' => session()->token(),
                'name' => 'Renamed Show',
                'description' => 'New description.',
                'lifecycle_state' => Show::STATE_PLANNED,
            ])
            ->assertRedirect();

        $preserved = DB::table('shows')->where('id', $show->id)->first();

        $this->assertSame('Preserved Venue', $preserved->venue_location);
        $this->assertSame('2026-08-15 20:00:00', $preserved->scheduled_at);
    }

    public function test_no_delete_route_exists_for_shows(): void
    {
        $deleteRoutes = collect(app('router')->getRoutes())->filter(
            static fn ($route): bool => in_array('DELETE', $route->methods(), true)
                && str_contains($route->uri(), 'studio/shows')
        );

        $this->assertTrue($deleteRoutes->isEmpty());
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

    private function archiveShowAsDirector(User $director, Show $show): void
    {
        $this->actingAs($director)->get('/studio/shows')->assertOk();

        $this->actingAs($director)
            ->patch(route('studio.shows.archive', $show), ['_token' => session()->token()])
            ->assertRedirect(route('studio.shows.index'));
    }
}
