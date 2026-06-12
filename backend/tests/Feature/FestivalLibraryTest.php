<?php

namespace Tests\Feature;

use App\Enums\FestivalApplicationStatus;
use App\Models\Band;
use App\Models\Festival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class FestivalLibraryTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_director_can_open_festivals_index(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create(['name' => 'Test Band']);

        $this->actingAs($user)
            ->get(route('festivals.index'))
            ->assertOk()
            ->assertSee('Festivals — Test Band')
            ->assertSee('Active Festivals');
    }

    public function test_director_can_create_and_edit_festival(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $this->actingAs($user)->post(route('festivals.store'), [
            'name' => 'Harbour Music Festival',
            'country' => 'New Zealand',
            'city' => 'Auckland',
            'website' => 'https://harbourfest.test',
            'contact_name' => 'Alex Rivers',
            'contact_phone' => '021 555 0100',
            'contact_email' => 'alex@harbourfest.test',
            'application_url' => 'https://harbourfest.test/apply',
            'application_deadline' => '2026-09-30',
            'festival_date_notes' => 'Usually March',
            'application_status' => FestivalApplicationStatus::Applied->value,
            'facebook_tag' => '@harbourfest',
            'instagram_tag' => '@harbourfestnz',
            'tiktok_tag' => '@harbourfest',
        ])->assertRedirect(route('festivals.index'));

        $festival = Festival::query()->where('name', 'Harbour Music Festival')->firstOrFail();
        $this->assertSame('Auckland', $festival->city);
        $this->assertSame(FestivalApplicationStatus::Applied, $festival->application_status);
        $this->assertSame('@harbourfest', $festival->facebook_tag);

        $this->actingAs($user)->put(route('festivals.update', $festival), [
            'name' => 'Harbour Music Festival',
            'city' => 'Wellington',
            'application_status' => FestivalApplicationStatus::Accepted->value,
        ])->assertRedirect(route('festivals.index'));

        $festival->refresh();
        $this->assertSame('Wellington', $festival->city);
        $this->assertSame(FestivalApplicationStatus::Accepted, $festival->application_status);
    }

    public function test_director_can_archive_and_restore_festival(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $festival = Festival::factory()->for($band)->create(['name' => 'Archive Test Festival']);

        $this->actingAs($user)
            ->post(route('festivals.archive', $festival))
            ->assertRedirect(route('festivals.index'));

        $festival->refresh();
        $this->assertFalse($festival->active);
        $this->assertDatabaseHas('festivals', ['id' => $festival->id, 'active' => false]);

        $this->actingAs($user)
            ->get(route('festivals.index'))
            ->assertOk()
            ->assertSee('Archived Festivals')
            ->assertSee('Archive Test Festival');

        $this->actingAs($user)
            ->post(route('festivals.restore', $festival))
            ->assertRedirect(route('festivals.index'));

        $this->assertTrue($festival->fresh()->active);
    }

    public function test_bulk_create_ignores_blank_lines_duplicates_and_invalid_statuses(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        Festival::factory()->for($band)->create(['name' => 'Existing Festival']);

        $lines = implode("\n", [
            'North Coast Festival | NZ | Whangarei | https://north.test | Sam Lee | 021 111 2222 | sam@north.test | https://north.test/apply | 2026-07-01 | January | applied | @north | @northnz | @north',
            '',
            '   ',
            'South Sound Festival | NZ | Dunedin | https://south.test | Pat Ng | 021 333 4444 | pat@south.test | https://south.test/apply | 2026-08-01 | February | totally_invalid | @south | @southnz | @south',
            'Existing Festival | NZ | Auckland | https://dup.test | Dup | 021 999 8888 | dup@existing.test | https://dup.test/apply | 2026-09-01 | March | not_applied | @dup | @dupnz | @dup',
            'North Coast Festival | NZ | Wellington | https://repeat.test | Repeat | 021 000 0000 | repeat@north.test | https://repeat.test/apply | 2026-10-01 | April | not_applied | @repeat | @repeatnz | @repeat',
        ]);

        $response = $this->actingAs($user)->post(route('festivals.bulk-store'), [
            'festival_lines' => $lines,
        ]);

        $response->assertRedirect(route('festivals.bulk-create'));
        $response->assertSessionHas('bulk_result.created_count', 2);
        $response->assertSessionHas('bulk_result.skipped_count', 2);

        $bulkResult = $response->getSession()->get('bulk_result');
        $this->assertEqualsCanonicalizing(
            ['North Coast Festival', 'South Sound Festival'],
            array_column($bulkResult['created'], 'name'),
        );

        $skippedNames = array_column($bulkResult['skipped'], 'name');
        $this->assertContains('Existing Festival', $skippedNames);
        $this->assertContains('North Coast Festival', $skippedNames);

        $south = Festival::query()->where('name', 'South Sound Festival')->firstOrFail();
        $this->assertSame(FestivalApplicationStatus::NotApplied, $south->application_status);

        $this->assertSame(3, Festival::query()->where('band_id', $band->id)->count());
    }

    public function test_festival_list_displays_contact_application_and_social_fields(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();

        Festival::factory()->for($band)->create([
            'name' => 'Display Test Festival',
            'country' => 'New Zealand',
            'city' => 'Hamilton',
            'website' => 'https://displayfest.test',
            'contact_name' => 'Jordan Price',
            'contact_phone' => '07 123 4567',
            'contact_email' => 'jordan@display.test',
            'application_url' => 'https://displayfest.test/apply',
            'application_deadline' => '2026-11-15',
            'festival_date_notes' => 'Held in summer',
            'application_status' => FestivalApplicationStatus::UnderReview,
            'facebook_tag' => '@displayfest',
            'instagram_tag' => '@displayfestnz',
            'tiktok_tag' => '@displayfest',
        ]);

        $this->actingAs($user)
            ->get(route('festivals.index'))
            ->assertOk()
            ->assertSee('Display Test Festival')
            ->assertSee('New Zealand')
            ->assertSee('Hamilton')
            ->assertSee('https://displayfest.test')
            ->assertSee('Jordan Price')
            ->assertSee('07 123 4567')
            ->assertSee('jordan@display.test')
            ->assertSee('https://displayfest.test/apply')
            ->assertSee('Status: Under Review')
            ->assertSee('Facebook: @displayfest')
            ->assertSee('Instagram: @displayfestnz')
            ->assertSee('TikTok: @displayfest');
    }

    public function test_festival_search_matches_expected_fields(): void
    {
        $festival = Festival::factory()->make([
            'name' => 'Riverside Music Festival',
            'country' => 'New Zealand',
            'city' => 'Napier',
            'website' => 'https://riverside.test',
            'contact_name' => 'Casey Morgan',
            'contact_phone' => '06 555 1212',
            'contact_email' => 'casey@riverside.test',
            'application_url' => 'https://riverside.test/apply',
            'application_status' => FestivalApplicationStatus::Waitlisted,
        ]);

        $this->assertTrue($festival->matchesSearch('riverside'));
        $this->assertTrue($festival->matchesSearch('napier'));
        $this->assertTrue($festival->matchesSearch('casey'));
        $this->assertTrue($festival->matchesSearch('casey@riverside.test'));
        $this->assertTrue($festival->matchesSearch('06 555'));
        $this->assertTrue($festival->matchesSearch('https://riverside.test/apply'));
        $this->assertTrue($festival->matchesSearch('waitlisted'));
        $this->assertTrue($festival->matchesSearch('waitlist'));
        $this->assertFalse($festival->matchesSearch('wellington'));
    }

    public function test_single_create_rejects_duplicate_festival_name_for_band(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        Festival::factory()->for($band)->create(['name' => 'Shared Name Festival']);

        $this->actingAs($user)
            ->post(route('festivals.store'), [
                'name' => 'shared name festival',
                'application_status' => FestivalApplicationStatus::NotApplied->value,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_application_status_normalize_defaults_invalid_to_not_applied(): void
    {
        $this->assertSame(
            FestivalApplicationStatus::NotApplied,
            FestivalApplicationStatus::normalize('invalid_status'),
        );
        $this->assertSame(
            FestivalApplicationStatus::NotApplied,
            FestivalApplicationStatus::normalize(''),
        );
        $this->assertSame(
            FestivalApplicationStatus::Accepted,
            FestivalApplicationStatus::normalize('accepted'),
        );
    }
}
