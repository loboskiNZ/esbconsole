<?php

namespace Tests\Feature;

use App\Models\Performance;
use App\Models\PerformanceAssignment;
use App\Models\Show;
use App\Models\User;
use App\Services\StudioPerformanceService;
use App\Services\StudioShowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioScheduleTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal.band_id' => 1]);
        $this->ensurePortalBand();
        Carbon::setTestNow('2026-06-01 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_shows_next_five_upcoming_performances_only(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Schedule Show']);

        foreach (range(1, 6) as $index) {
            $this->seedPerformance($show, [
                'location_name' => 'Venue '.$index,
                'performance_date' => '2026-06-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            ]);
        }

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('Venue 1', false)
            ->assertSee('Venue 5', false)
            ->assertDontSee('Venue 6', false)
            ->assertSee('View Calendar', false);
    }

    public function test_dashboard_performances_are_ordered_chronologically(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Ordered Show']);

        $this->seedPerformance($show, ['location_name' => 'Third Venue', 'performance_date' => '2026-06-20']);
        $this->seedPerformance($show, ['location_name' => 'First Venue', 'performance_date' => '2026-06-05']);
        $this->seedPerformance($show, ['location_name' => 'Second Venue', 'performance_date' => '2026-06-10']);

        $response = $this->actingAs($user)->get('/studio')->assertOk();
        $content = $response->getContent();
        $first = strpos($content, 'First Venue');
        $second = strpos($content, 'Second Venue');
        $third = strpos($content, 'Third Venue');

        $this->assertNotFalse($first);
        $this->assertNotFalse($second);
        $this->assertNotFalse($third);
        $this->assertTrue($first < $second && $second < $third);
    }

    public function test_user_can_rsvp_available_and_responded_at_is_set(): void
    {
        [$user, $musicianId] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'RSVP Show']);
        $performance = $this->seedPerformance($show, ['performance_date' => '2026-06-12']);

        $this->actingAs($user)->get('/studio')->assertOk();

        $this->actingAs($user)
            ->post(route('studio.performances.rsvp', $performance), [
                '_token' => session()->token(),
                'response' => 'yes',
            ])
            ->assertRedirect()
            ->assertSessionHas('rsvp_saved');

        $assignment = DB::table('performance_assignments')
            ->where('performance_id', $performance->id)
            ->where('musician_id', $musicianId)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertSame(PerformanceAssignment::AVAILABILITY_AVAILABLE, $assignment->availability_status);
        $this->assertNotNull($assignment->responded_at);
    }

    public function test_user_can_rsvp_unavailable_with_notes(): void
    {
        [$user, $musicianId] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Unavailable Show']);
        $performance = $this->seedPerformance($show, ['performance_date' => '2026-06-13']);

        $this->actingAs($user)->get('/studio')->assertOk();

        $this->actingAs($user)
            ->post(route('studio.performances.rsvp', $performance), [
                '_token' => session()->token(),
                'response' => 'no',
                'notes' => 'Away that weekend.',
            ])
            ->assertRedirect()
            ->assertSessionHas('rsvp_saved');

        $assignment = DB::table('performance_assignments')
            ->where('performance_id', $performance->id)
            ->where('musician_id', $musicianId)
            ->first();

        $this->assertSame(PerformanceAssignment::AVAILABILITY_UNAVAILABLE, $assignment->availability_status);
        $this->assertSame('Away that weekend.', $assignment->availability_notes);
    }

    public function test_rsvp_updates_only_current_users_record(): void
    {
        [$userA, $musicianA] = $this->createMusicianUser('Musician A');
        [$userB, $musicianB] = $this->createMusicianUser('Musician B');
        $show = $this->seedShow(['name' => 'Shared Show']);
        $performance = $this->seedPerformance($show, ['performance_date' => '2026-06-14']);

        $instrumentPartId = $this->seedInstrumentPart('Keys');
        DB::table('performance_assignments')->insert([
            'public_id' => (string) Str::uuid(),
            'performance_id' => $performance->id,
            'musician_id' => $musicianB,
            'instrument_part_id' => $instrumentPartId,
            'active' => true,
            'availability_status' => PerformanceAssignment::AVAILABILITY_AVAILABLE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($userA)->get('/studio')->assertOk();

        $this->actingAs($userA)
            ->post(route('studio.performances.rsvp', $performance), [
                '_token' => session()->token(),
                'response' => 'maybe',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('performance_assignments', [
            'performance_id' => $performance->id,
            'musician_id' => $musicianA,
            'availability_status' => PerformanceAssignment::AVAILABILITY_MAYBE,
        ]);

        $this->assertDatabaseHas('performance_assignments', [
            'performance_id' => $performance->id,
            'musician_id' => $musicianB,
            'availability_status' => PerformanceAssignment::AVAILABILITY_AVAILABLE,
        ]);
    }

    public function test_user_without_linked_musician_gets_friendly_failure(): void
    {
        $user = User::factory()->create();
        $this->assignMusicianRole($user);
        $show = $this->seedShow(['name' => 'No Link Show']);
        $performance = $this->seedPerformance($show, ['performance_date' => '2026-06-15']);

        $this->actingAs($user)->get('/studio')->assertOk();

        $this->actingAs($user)
            ->post(route('studio.performances.rsvp', $performance), [
                '_token' => session()->token(),
                'response' => 'yes',
            ])
            ->assertRedirect()
            ->assertSessionHas('rsvp_error');

        $this->assertSame(0, DB::table('performance_assignments')->count());
    }

    public function test_linked_musician_via_user_email_can_open_calendar_and_rsvp(): void
    {
        $user = User::factory()->create([
            'band_id' => 1,
            'email' => 'calendar-linked@example.com',
            'name' => 'Calendar Player',
        ]);
        $this->assignMusicianRole($user);
        $user->person->update(['email' => null, 'artistic_name' => 'Calendar Player']);

        $musicianId = (int) DB::table('musicians')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'user_id' => null,
            'first_name' => 'Calendar',
            'last_name' => 'Player',
            'display_name' => 'Calendar Player',
            'email' => 'calendar-linked@example.com',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $show = $this->seedShow(['name' => 'Email Linked Show']);
        $performance = $this->seedPerformance($show, ['performance_date' => '2026-06-19']);

        $this->actingAs($user)->get('/studio/calendar')
            ->assertOk()
            ->assertSee('studioCalendar', false)
            ->assertSee(', true, false,', false);

        $this->actingAs($user)
            ->post(route('studio.performances.rsvp', $performance), [
                '_token' => session()->token(),
                'response' => 'yes',
            ])
            ->assertRedirect()
            ->assertSessionHas('rsvp_saved');

        $this->assertDatabaseHas('performance_assignments', [
            'performance_id' => $performance->id,
            'musician_id' => $musicianId,
            'availability_status' => PerformanceAssignment::AVAILABILITY_AVAILABLE,
        ]);
    }

    public function test_linked_musician_can_rsvp_maybe(): void
    {
        [$user, $musicianId] = $this->createMusicianUser('Maybe Musician');
        $show = $this->seedShow(['name' => 'Maybe Show']);
        $performance = $this->seedPerformance($show, ['performance_date' => '2026-06-22']);

        $this->actingAs($user)->get('/studio/calendar')->assertOk();

        $this->actingAs($user)
            ->post(route('studio.performances.rsvp', $performance), [
                '_token' => session()->token(),
                'response' => 'maybe',
            ])
            ->assertRedirect()
            ->assertSessionHas('rsvp_saved');

        $this->assertDatabaseHas('performance_assignments', [
            'performance_id' => $performance->id,
            'musician_id' => $musicianId,
            'availability_status' => PerformanceAssignment::AVAILABILITY_MAYBE,
        ]);
    }

    public function test_musician_linked_to_other_band_cannot_rsvp_for_portal_band(): void
    {
        DB::table('bands')->insert([
            'id' => 2,
            'public_id' => (string) Str::uuid(),
            'name' => 'Other Band',
            'primary_director_musician_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'band_id' => 1,
            'email' => 'other-band@example.com',
        ]);
        $this->assignMusicianRole($user);
        $user->person->update(['email' => null]);

        DB::table('musicians')->insert([
            'public_id' => (string) Str::uuid(),
            'band_id' => 2,
            'user_id' => null,
            'first_name' => 'Other',
            'last_name' => 'Band',
            'display_name' => 'Other Band',
            'email' => 'other-band@example.com',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $show = $this->seedShow(['name' => 'Portal Band Show']);
        $performance = $this->seedPerformance($show, ['performance_date' => '2026-06-23']);

        $this->actingAs($user)->get('/studio/calendar')
            ->assertOk()
            ->assertSee(', false, false,', false);

        $this->actingAs($user)
            ->post(route('studio.performances.rsvp', $performance), [
                '_token' => session()->token(),
                'response' => 'yes',
            ])
            ->assertRedirect()
            ->assertSessionHas('rsvp_error');

        $this->assertSame(0, DB::table('performance_assignments')->count());
    }

    public function test_director_with_linked_musician_profile_can_still_view_calendar(): void
    {
        $director = $this->createDirectorUser();
        $director->forceFill(['email' => 'director-linked@example.com'])->save();
        $director->person?->update(['email' => null]);

        DB::table('musicians')->insert([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'user_id' => $director->id,
            'first_name' => 'Director',
            'last_name' => 'Linked',
            'display_name' => 'Director Linked',
            'email' => 'director-linked@example.com',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $show = $this->seedShow(['name' => 'Director Calendar Show']);
        $this->seedPerformance($show, ['performance_date' => '2026-06-24']);

        $this->actingAs($director)->get('/studio/calendar')
            ->assertOk()
            ->assertSee(', true, true,', false);
    }

    public function test_studio_calendar_page_loads_with_list_and_calendar_views(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Calendar Show']);
        $this->seedPerformance($show, [
            'location_name' => 'Calendar Venue',
            'performance_date' => '2026-06-18',
        ]);

        $this->actingAs($user)->get('/studio/calendar')
            ->assertOk()
            ->assertSee('Calendar', false)
            ->assertSee('List', false)
            ->assertSee('Week', false)
            ->assertSee('Month', false)
            ->assertSee('Calendar Venue', false);
    }

    public function test_ics_route_returns_calendar_payload_with_show_and_location(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'ICS Show']);
        $performance = $this->seedPerformance($show, [
            'performance_type' => Performance::TYPE_LIVE,
            'location_name' => 'ICS Venue',
            'location_address' => '1 Calendar Street',
            'performance_date' => '2026-06-21',
            'performance_time' => '20:00:00',
            'performance_duration_minutes' => 90,
            'briefing_notes' => 'Doors at 19:30.',
        ]);

        $response = $this->actingAs($user)->get(route('studio.performances.calendar', $performance));

        $response->assertOk();
        $this->assertStringContainsString('text/calendar', (string) $response->headers->get('content-type'));
        $response->assertSee('BEGIN:VCALENDAR', false);
        $response->assertSee('ICS Show', false);
        $response->assertSee('ICS Venue', false);
        $response->assertSee('Doors at 19:30.', false);
    }

    public function test_musician_cannot_edit_performance_metadata(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Protected Performance Show']);
        $performance = $this->seedPerformance($show);

        $this->actingAs($user)->get(route('studio.performances.edit', $performance))->assertForbidden();
    }

    public function test_director_can_still_edit_performance_metadata(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Director Edit Show']);
        $performance = $this->seedPerformance($show);

        $this->actingAs($director)->get(route('studio.performances.edit', $performance))->assertOk();
    }

    public function test_director_calendar_includes_add_performance_date_links(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Director Add Show']);
        $this->seedPerformance($show, ['performance_date' => '2026-06-18']);

        $this->actingAs($director)->get('/studio/calendar')
            ->assertOk()
            ->assertSee('esb-studio__calendar-date-link', false)
            ->assertSee(':aria-label="addPerformanceLabel(day)"', false)
            ->assertSee('studio\/performances\/create', false)
            ->assertSee(', false, true,', false);
    }

    public function test_musician_calendar_does_not_include_add_performance_date_links(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Musician Add Show']);
        $this->seedPerformance($show, ['performance_date' => '2026-06-18']);

        $this->actingAs($user)->get('/studio/calendar')
            ->assertOk()
            ->assertDontSee('esb-studio__calendar-date-link', false)
            ->assertDontSee(':aria-label="addPerformanceLabel(day)"', false)
            ->assertSee(', true, false,', false);
    }

    public function test_calendar_entries_display_performance_type_time_and_location(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Info Show']);
        $this->seedPerformance($show, [
            'performance_type' => Performance::TYPE_LIVE,
            'location_name' => 'Town Hall',
            'performance_date' => '2026-06-18',
            'performance_time' => '19:30:00',
        ]);

        $this->actingAs($user)->get('/studio/calendar')
            ->assertOk()
            ->assertSee('Live', false)
            ->assertSee('19:30', false)
            ->assertSee('Town Hall', false);
    }

    public function test_calendar_performance_entry_links_remain_correct(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Link Show']);
        $performance = $this->seedPerformance($show, [
            'performance_date' => '2026-06-18',
            'location_name' => 'Link Venue',
        ]);

        $this->actingAs($user)->get('/studio/calendar')
            ->assertOk()
            ->assertSee('Link Venue', false)
            ->assertSee(':href="card.show_url"', false)
            ->assertSee('\u0022id\u0022:'.$performance->id, false);
    }

    public function test_calendar_location_is_loaded_without_additional_queries(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Query Show']);
        $this->seedPerformance($show, [
            'location_name' => 'Inline Venue',
            'performance_date' => '2026-06-18',
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($director)->get('/studio/calendar')->assertOk();

        $queries = DB::getQueryLog();
        $locationTableQueries = array_filter($queries, static function (array $query): bool {
            return str_contains(strtolower($query['query']), 'locations');
        });

        $this->assertSame([], array_values($locationTableQueries));
    }

    public function test_rsvp_does_not_delete_performance_or_assignment_rows(): void
    {
        [$user] = $this->createMusicianUser();
        $show = $this->seedShow(['name' => 'Persist RSVP Show']);
        $performance = $this->seedPerformance($show, ['performance_date' => '2026-06-16']);
        $performanceCount = DB::table('performances')->count();
        $assignmentCount = DB::table('performance_assignments')->count();

        $this->actingAs($user)->get('/studio')->assertOk();

        $this->actingAs($user)
            ->post(route('studio.performances.rsvp', $performance), [
                '_token' => session()->token(),
                'response' => 'yes',
            ])
            ->assertRedirect();

        $this->assertSame($performanceCount, DB::table('performances')->count());
        $this->assertSame($assignmentCount + 1, DB::table('performance_assignments')->count());
    }

    /**
     * @return array{0: User, 1: int}
     */
    private function createMusicianUser(string $displayName = 'Test Musician'): array
    {
        $user = User::factory()->create();
        $this->assignMusicianRole($user);

        $musicianId = (int) DB::table('musicians')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Musician',
            'display_name' => $displayName,
            'email' => $user->person?->email,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $musicianId];
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedPerformance(Show $show, array $overrides = []): Performance
    {
        return app(StudioPerformanceService::class)->createPerformance(array_merge([
            'show_id' => $show->id,
            'performance_type' => Performance::TYPE_REHEARSAL,
            'status' => Performance::STATUS_NOT_CONFIRMED,
            'location_name' => 'Seed Venue',
            'performance_date' => '2026-08-01',
        ], $overrides));
    }

    private function seedInstrumentPart(string $name): int
    {
        return (int) DB::table('instrument_parts')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => $name,
            'description' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
