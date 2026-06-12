<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class VenueLibraryTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_director_can_open_venues_index(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create(['name' => 'Test Band']);

        $this->actingAs($user)
            ->get(route('venues.index'))
            ->assertOk()
            ->assertSee('Venues — Test Band')
            ->assertSee('Active Venues');
    }

    public function test_director_can_create_and_edit_venue(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $this->actingAs($user)->post(route('venues.store'), [
            'name' => 'Harbour Hall',
            'country' => 'New Zealand',
            'city' => 'Auckland',
            'address' => '1 Quay St',
            'contact_name' => 'Alex Rivers',
            'contact_phone' => '021 555 0100',
            'contact_email' => 'alex@harbourhall.test',
            'facebook_tag' => '@harbourhall',
            'instagram_tag' => '@harbourhallnz',
            'tiktok_tag' => '@harbourhall',
        ])->assertRedirect(route('venues.index'));

        $venue = Venue::query()->where('name', 'Harbour Hall')->firstOrFail();
        $this->assertSame('Auckland', $venue->city);
        $this->assertSame('@harbourhall', $venue->facebook_tag);

        $this->actingAs($user)->put(route('venues.update', $venue), [
            'name' => 'Harbour Hall',
            'city' => 'Wellington',
            'contact_email' => 'bookings@harbourhall.test',
        ])->assertRedirect(route('venues.index'));

        $venue->refresh();
        $this->assertSame('Wellington', $venue->city);
        $this->assertSame('bookings@harbourhall.test', $venue->contact_email);
    }

    public function test_director_can_archive_and_restore_venue(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $venue = Venue::factory()->for($band)->create(['name' => 'Archive Test Hall']);

        $this->actingAs($user)
            ->post(route('venues.archive', $venue))
            ->assertRedirect(route('venues.index'));

        $venue->refresh();
        $this->assertFalse($venue->active);
        $this->assertDatabaseHas('venues', ['id' => $venue->id, 'active' => false]);

        $this->actingAs($user)
            ->get(route('venues.index'))
            ->assertOk()
            ->assertSee('Archived Venues')
            ->assertSee('Archive Test Hall');

        $this->actingAs($user)
            ->post(route('venues.restore', $venue))
            ->assertRedirect(route('venues.index'));

        $this->assertTrue($venue->fresh()->active);
    }

    public function test_bulk_create_ignores_blank_lines_and_reports_duplicates(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        Venue::factory()->for($band)->create(['name' => 'Existing Hall']);

        $lines = implode("\n", [
            'North Stage | NZ | Christchurch | 10 High St | Sam Lee | 021 111 2222 | sam@north.test | @north | @northnz | @north',
            '',
            '   ',
            'South Room | NZ | Dunedin | 22 Low St | Pat Ng | 021 333 4444 | pat@south.test | @south | @southnz | @south',
            'Existing Hall | NZ | Auckland | 1 Main Rd | Dup Person | 021 999 8888 | dup@existing.test | @dup | @dupnz | @dup',
            'North Stage | NZ | Wellington | 5 Other St | Repeat | 021 000 0000 | repeat@north.test | @repeat | @repeatnz | @repeat',
        ]);

        $response = $this->actingAs($user)->post(route('venues.bulk-store'), [
            'venue_lines' => $lines,
        ]);

        $response->assertRedirect(route('venues.bulk-create'));
        $response->assertSessionHas('bulk_result.created_count', 2);
        $response->assertSessionHas('bulk_result.skipped_count', 2);

        $bulkResult = $response->getSession()->get('bulk_result');
        $this->assertSame(['North Stage', 'South Room'], array_column($bulkResult['created'], 'name'));

        $skippedNames = array_column($bulkResult['skipped'], 'name');
        $this->assertContains('Existing Hall', $skippedNames);
        $this->assertContains('North Stage', $skippedNames);

        $this->assertDatabaseHas('venues', ['name' => 'North Stage', 'city' => 'Christchurch']);
        $this->assertDatabaseHas('venues', ['name' => 'South Room', 'contact_email' => 'pat@south.test']);
        $this->assertSame(3, Venue::query()->where('band_id', $band->id)->count());
    }

    public function test_venue_list_displays_contact_and_social_fields(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();

        Venue::factory()->for($band)->create([
            'name' => 'Display Test Venue',
            'country' => 'New Zealand',
            'city' => 'Hamilton',
            'address' => '88 Lake Rd',
            'contact_name' => 'Jordan Price',
            'contact_phone' => '07 123 4567',
            'contact_email' => 'jordan@display.test',
            'facebook_tag' => '@displayvenue',
            'instagram_tag' => '@displayvenuenz',
            'tiktok_tag' => '@displayvenue',
        ]);

        $this->actingAs($user)
            ->get(route('venues.index'))
            ->assertOk()
            ->assertSee('Display Test Venue')
            ->assertSee('New Zealand')
            ->assertSee('Hamilton')
            ->assertSee('88 Lake Rd')
            ->assertSee('Jordan Price')
            ->assertSee('07 123 4567')
            ->assertSee('jordan@display.test')
            ->assertSee('Facebook: @displayvenue')
            ->assertSee('Instagram: @displayvenuenz')
            ->assertSee('TikTok: @displayvenue');
    }

    public function test_venue_search_matches_expected_fields(): void
    {
        $venue = Venue::factory()->make([
            'name' => 'Riverside Theatre',
            'country' => 'New Zealand',
            'city' => 'Napier',
            'address' => '100 Marine Parade',
            'contact_name' => 'Casey Morgan',
            'contact_phone' => '06 555 1212',
            'contact_email' => 'casey@riverside.test',
        ]);

        $this->assertTrue($venue->matchesSearch('riverside'));
        $this->assertTrue($venue->matchesSearch('napier'));
        $this->assertTrue($venue->matchesSearch('casey'));
        $this->assertTrue($venue->matchesSearch('casey@riverside.test'));
        $this->assertTrue($venue->matchesSearch('06 555'));
        $this->assertTrue($venue->matchesSearch('marine parade'));
        $this->assertFalse($venue->matchesSearch('wellington'));
    }

    public function test_single_create_rejects_duplicate_venue_name_for_band(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        Venue::factory()->for($band)->create(['name' => 'Shared Name Hall']);

        $this->actingAs($user)
            ->post(route('venues.store'), ['name' => 'shared name hall'])
            ->assertSessionHasErrors('name');
    }
}
