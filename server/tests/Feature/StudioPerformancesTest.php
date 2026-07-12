<?php

namespace Tests\Feature;

use App\Models\Performance;
use App\Models\Show;
use App\Models\User;
use App\Services\StudioPerformanceService;
use App\Services\StudioShowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class StudioPerformancesTest extends TestCase
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

    public function test_performances_table_audit_respected_existing_schema(): void
    {
        $this->assertTrue(Schema::hasTable('performances'));
        $this->assertTrue(Schema::hasTable('performance_assignments'));

        foreach ([
            'id',
            'public_id',
            'band_id',
            'show_id',
            'venue',
            'performance_date',
            'status',
            'notes',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('performances', $column),
                "Expected legacy performances column {$column} to remain present."
            );
        }

        foreach ([
            'performance_type',
            'location_name',
            'location_address',
            'prep_time',
            'performance_time',
            'performance_duration_minutes',
            'packup_time',
            'briefing_notes',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('performances', $column),
                "Expected additive performances column {$column} to be present."
            );
        }

        foreach ([
            'id',
            'public_id',
            'performance_id',
            'musician_id',
            'instrument_part_id',
            'active',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('performance_assignments', $column),
                "Expected legacy performance_assignments column {$column} to remain present."
            );
        }

        $this->assertTrue(Schema::hasColumn('performance_assignments', 'availability_status'));
        $this->assertTrue(Schema::hasColumn('performance_assignments', 'availability_notes'));
    }

    public function test_director_can_create_rehearsal_performance(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'LO-FI Show']);

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', $this->performancePayload($show, [
                'performance_type' => Performance::TYPE_REHEARSAL,
                'location_name' => 'Studio A',
            ]))
            ->assertRedirect();

        $performance = Performance::query()->where('show_id', $show->id)->first();

        $this->assertNotNull($performance);
        $this->assertSame(Performance::TYPE_REHEARSAL, $performance->performance_type);
        $this->assertSame('Studio A', $performance->location_name);
        $this->assertSame('Studio A', $performance->venue);
    }

    public function test_director_can_create_live_performance(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Christmas Show']);

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', $this->performancePayload($show, [
                'performance_type' => Performance::TYPE_LIVE,
                'status' => Performance::STATUS_CONFIRMED,
            ]))
            ->assertRedirect();

        $performance = Performance::query()->where('show_id', $show->id)->firstOrFail();

        $this->assertSame(Performance::TYPE_LIVE, $performance->performance_type);
        $this->assertSame(Performance::STATUS_CONFIRMED, $performance->status);
    }

    public function test_performance_must_belong_to_a_show(): void
    {
        $director = $this->createDirectorUser();

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', [
                '_token' => session()->token(),
                'show_id' => 99999,
                'performance_type' => Performance::TYPE_REHEARSAL,
                'status' => Performance::STATUS_NOT_CONFIRMED,
                'location_name' => 'Nowhere',
                'performance_date' => '2026-09-01',
            ])
            ->assertSessionHasErrors('show_id');
    }

    public function test_performance_status_can_be_confirmed_or_not_confirmed(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Status Show']);

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', $this->performancePayload($show, [
                'status' => Performance::STATUS_NOT_CONFIRMED,
            ]))
            ->assertRedirect();

        $notConfirmed = Performance::query()->where('show_id', $show->id)->firstOrFail();
        $this->assertSame(Performance::STATUS_NOT_CONFIRMED, $notConfirmed->status);

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', $this->performancePayload($show, [
                'location_name' => 'Second Venue',
                'performance_date' => '2026-10-01',
                'status' => Performance::STATUS_CONFIRMED,
            ]))
            ->assertRedirect();

        $confirmed = Performance::query()
            ->where('show_id', $show->id)
            ->where('status', Performance::STATUS_CONFIRMED)
            ->first();

        $this->assertNotNull($confirmed);
    }

    public function test_cancelled_is_accepted_as_a_valid_status(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Cancelled Show']);

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', $this->performancePayload($show, [
                'status' => Performance::STATUS_CANCELLED,
            ]))
            ->assertRedirect();

        $performance = Performance::query()->where('show_id', $show->id)->firstOrFail();

        $this->assertSame(Performance::STATUS_CANCELLED, $performance->status);
        $this->assertSame('Cancelled', $performance->statusLabel());
    }

    public function test_invalid_performance_statuses_are_rejected(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Invalid Status Show']);

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', $this->performancePayload($show, [
                'status' => 'tentative',
            ]))
            ->assertSessionHasErrors('status');
    }

    public function test_create_form_includes_calendar_date_picker_input(): void
    {
        $director = $this->createDirectorUser();
        $this->seedShow(['name' => 'Picker Show']);

        $this->actingAs($director)->get('/studio/performances/create')
            ->assertOk()
            ->assertSee('id="performance-date"', false)
            ->assertSee('type="date"', false)
            ->assertSee('esb-studio__date-input', false);
    }

    public function test_director_can_access_create_with_valid_date_query_parameter(): void
    {
        $director = $this->createDirectorUser();
        $this->seedShow(['name' => 'Date Query Show']);

        $this->actingAs($director)
            ->get('/studio/performances/create?date=2026-07-18')
            ->assertOk()
            ->assertSee('value="2026-07-18"', false);
    }

    public function test_old_input_takes_precedence_over_calendar_date_query_parameter(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Old Input Show']);

        $this->actingAs($director)->get('/studio/performances/create?date=2026-07-18')->assertOk();

        $this->actingAs($director)
            ->post('/studio/performances', [
                '_token' => session()->token(),
                'show_id' => '',
                'performance_type' => Performance::TYPE_REHEARSAL,
                'status' => Performance::STATUS_NOT_CONFIRMED,
                'location_name' => 'Rehearsal Room',
                'performance_date' => '2026-08-01',
            ])
            ->assertSessionHasErrors('show_id');

        $this->actingAs($director)
            ->get('/studio/performances/create?date=2026-07-18')
            ->assertOk()
            ->assertSee('value="2026-08-01"', false)
            ->assertDontSee('value="2026-07-18"', false);
    }

    public function test_invalid_date_query_parameter_does_not_cause_server_error(): void
    {
        $director = $this->createDirectorUser();
        $this->seedShow(['name' => 'Invalid Date Show']);

        $this->actingAs($director)
            ->get('/studio/performances/create?date=not-a-date')
            ->assertOk()
            ->assertDontSee('value="not-a-date"', false);

        $this->actingAs($director)
            ->get('/studio/performances/create?date=2026-13-45')
            ->assertOk()
            ->assertDontSee('value="2026-13-45"', false);
    }

    public function test_musician_cannot_access_create_with_date_query_parameter(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $this->seedShow(['name' => 'Protected Date Show']);

        $this->actingAs($musician)
            ->get('/studio/performances/create?date=2026-07-18')
            ->assertForbidden();

        $this->actingAs($musician)
            ->post('/studio/performances', $this->performancePayload($this->seedShow(['name' => 'Blocked Store Show'])))
            ->assertForbidden();
    }

    public function test_edit_form_includes_calendar_date_picker_input(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Edit Picker Show']);
        $performance = $this->seedPerformance($show);

        $this->actingAs($director)->get(route('studio.performances.edit', $performance))
            ->assertOk()
            ->assertSee('id="performance-date"', false)
            ->assertSee('type="date"', false)
            ->assertSee('esb-studio__date-input', false);
    }

    public function test_cancelled_performance_displays_in_list_and_overview(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Cancelled List Show']);
        $performance = $this->seedPerformance($show, [
            'status' => Performance::STATUS_CANCELLED,
            'performance_date' => '2026-03-21',
        ]);

        $this->actingAs($director)->get('/studio/performances')
            ->assertOk()
            ->assertSee('Cancelled', false)
            ->assertSee('Sat 21 Mar 2026', false);

        $this->actingAs($director)->get(route('studio.performances.show', $performance))
            ->assertOk()
            ->assertSee('Cancelled', false)
            ->assertSee('Sat 21 Mar 2026', false);
    }

    public function test_director_can_edit_performance_to_cancelled_without_deleting_row(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Edit Cancel Show']);
        $performance = $this->seedPerformance($show, [
            'status' => Performance::STATUS_CONFIRMED,
            'location_name' => 'Original Venue',
        ]);
        $countBefore = DB::table('performances')->count();

        $this->actingAs($director)->get(route('studio.performances.edit', $performance))->assertOk();

        $this->actingAs($director)
            ->put(route('studio.performances.update', $performance), array_merge(
                $this->performancePayload($show, [
                    'status' => Performance::STATUS_CANCELLED,
                    'location_name' => 'Original Venue',
                ]),
            ))
            ->assertRedirect(route('studio.performances.show', $performance));

        $this->assertSame($countBefore, DB::table('performances')->count());
        $performance->refresh();
        $this->assertSame(Performance::STATUS_CANCELLED, $performance->status);
        $this->assertSame('Original Venue', $performance->location_name);
    }

    public function test_performance_appears_in_performances_list(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Listed Show']);
        $performance = $this->seedPerformance($show, [
            'location_name' => 'Town Hall',
            'performance_date' => '2026-08-20',
            'performance_time' => '19:30:00',
        ]);

        $this->actingAs($director)->get('/studio/performances')
            ->assertOk()
            ->assertSee('Listed Show', false)
            ->assertSee('Town Hall', false)
            ->assertSee($performance->typeLabel(), false)
            ->assertSee($performance->statusLabel(), false);
    }

    public function test_performance_appears_on_show_overview(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Overview Show']);
        $performance = $this->seedPerformance($show, [
            'location_name' => 'Festival Green',
            'performance_date' => '2026-08-22',
        ]);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee($performance->typeLabel(), false)
            ->assertSee($performance->statusLabel(), false)
            ->assertSee('Sat 22 Aug 2026', false)
            ->assertSee('Festival Green', false)
            ->assertSee('Open', false);
    }

    public function test_performance_overview_displays_fields(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Detail Show']);
        $performance = $this->seedPerformance($show, [
            'performance_type' => Performance::TYPE_LIVE,
            'status' => Performance::STATUS_CONFIRMED,
            'location_name' => 'Opera House',
            'location_address' => '123 Main St',
            'performance_date' => '2026-09-15',
            'prep_time' => '16:00:00',
            'performance_time' => '20:00:00',
            'performance_duration_minutes' => 120,
            'packup_time' => '23:00:00',
            'briefing_notes' => 'Doors at 19:00.',
        ]);

        $this->actingAs($director)->get(route('studio.performances.show', $performance))
            ->assertOk()
            ->assertSee('Detail Show', false)
            ->assertSee('Live', false)
            ->assertSee('Confirmed', false)
            ->assertSee('Opera House', false)
            ->assertSee('123 Main St', false)
            ->assertSee('Tue 15 Sep 2026', false)
            ->assertSee('16:00', false)
            ->assertSee('20:00', false)
            ->assertSee('120 min', false)
            ->assertSee('23:00', false)
            ->assertSee('Doors at 19:00.', false)
            ->assertSee('No availability records yet.', false);
    }

    public function test_musician_cannot_create_or_edit_performance(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $show = $this->seedShow(['name' => 'Protected Show']);
        $performance = $this->seedPerformance($show);

        $this->actingAs($musician)->get('/studio/performances/create')->assertForbidden();

        $this->actingAs($musician)->get(route('studio.performances.edit', $performance))->assertForbidden();

        $this->actingAs($musician)->get('/studio/performances')->assertOk();

        $this->actingAs($musician)
            ->put(route('studio.performances.update', $performance), array_merge(
                $this->performancePayload($show),
                ['_token' => csrf_token()]
            ))
            ->assertForbidden();
    }

    public function test_availability_records_display_if_present(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Availability Show']);
        $performance = $this->seedPerformance($show);
        $musicianId = $this->seedMusician('Alex Shadow');
        $instrumentPartId = $this->seedInstrumentPart('Keys');

        DB::table('performance_assignments')->insert([
            'public_id' => (string) Str::uuid(),
            'performance_id' => $performance->id,
            'musician_id' => $musicianId,
            'instrument_part_id' => $instrumentPartId,
            'song_id' => null,
            'cue_id' => null,
            'active' => true,
            'availability_status' => 'available',
            'availability_notes' => 'Can do soundcheck early.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($director)->get(route('studio.performances.show', $performance))
            ->assertOk()
            ->assertSee('Alex Shadow', false)
            ->assertSee('Available', false)
            ->assertSee('Can do soundcheck early.', false)
            ->assertDontSee('No availability records yet.', false);
    }

    public function test_performance_management_never_deletes_performance_rows(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Persist Show']);
        $existing = $this->seedPerformance($show, ['location_name' => 'Existing Venue']);
        $countBefore = DB::table('performances')->count();

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', $this->performancePayload($show, [
                'location_name' => 'Another Venue',
                'performance_date' => '2026-11-01',
            ]))
            ->assertRedirect();

        $this->assertSame($countBefore + 1, DB::table('performances')->count());
        $this->assertDatabaseHas('performances', ['id' => $existing->id, 'location_name' => 'Existing Venue']);
    }

    public function test_show_rows_are_not_modified_except_intended_relationships(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow([
            'name' => 'Untouched Show',
            'description' => 'Original description.',
            'lifecycle_state' => Show::STATE_PLANNED,
        ]);

        $this->beginPerformanceSession($director)
            ->post('/studio/performances', $this->performancePayload($show))
            ->assertRedirect();

        $this->assertDatabaseHas('shows', [
            'id' => $show->id,
            'name' => 'Untouched Show',
            'description' => 'Original description.',
            'lifecycle_state' => Show::STATE_PLANNED,
        ]);
    }

    public function test_director_sees_add_performance_in_director_tools(): void
    {
        $director = $this->createDirectorUser();

        $this->actingAs($director)->get('/studio')
            ->assertOk()
            ->assertSee('Add Performance', false)
            ->assertSee(route('studio.performances.create'), false);
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function performancePayload(Show $show, array $overrides = []): array
    {
        return array_merge([
            '_token' => session()->token(),
            'show_id' => $show->id,
            'performance_type' => Performance::TYPE_REHEARSAL,
            'status' => Performance::STATUS_NOT_CONFIRMED,
            'location_name' => 'Rehearsal Room',
            'location_address' => '1 Band Street',
            'performance_date' => '2026-08-01',
            'prep_time' => '17:00',
            'performance_time' => '19:00',
            'performance_duration_minutes' => 90,
            'packup_time' => '21:00',
            'briefing_notes' => 'Bring charts.',
        ], $overrides);
    }

    private function beginPerformanceSession(User $director): self
    {
        $this->actingAs($director)->get('/studio/performances/create')->assertOk();

        return $this;
    }

    private function seedMusician(string $displayName): int
    {
        return (int) DB::table('musicians')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'user_id' => null,
            'first_name' => 'Alex',
            'last_name' => 'Shadow',
            'display_name' => $displayName,
            'email' => null,
            'notes' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
