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
