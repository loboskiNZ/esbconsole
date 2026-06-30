<?php

namespace Tests\Feature;

use App\Contracts\DocxToPdfConverterInterface;
use App\Models\InstrumentReference;
use App\Models\Library\MusicalKey;
use App\Models\Library\Song;
use App\Models\Library\SongMood;
use App\Models\Library\TimeSignature;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\ShowSetlistGeneration;
use App\Models\User;
use App\Services\StudioShowPlaylistService;
use App\Services\StudioShowService;
use App\Services\StudioShowSetlistPdfService;
use App\Support\InstrumentCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\Support\FakeDocxToPdfConverter;
use Tests\TestCase;

class StudioShowSetlistPdfTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeDocxToPdfConverter::$lastDocxPath = null;
        FakeDocxToPdfConverter::$lastDocxContents = null;

        config([
            'portal.band_id' => 1,
            'portal.library_connection' => 'sqlite',
            'portal.setlist_template_path' => dirname(base_path()).'/templates/esb_setlist_template_tagged.docx',
        ]);

        $this->ensurePortalBand();
        $this->seedReferenceTables();
        $this->app->instance(DocxToPdfConverterInterface::class, new FakeDocxToPdfConverter);

        Storage::fake('media');
        Storage::fake('local');
    }

    public function test_director_can_generate_setlist_pdf(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Summer Setlist Show']);
        $first = $this->seedSong('Opening Song', bpm: 120);
        $second = $this->seedSong('Closing Song', bpm: 96);
        $this->seedPlaylistItem($show, $first, position: 1, notes: 'Walk-on energy');
        $this->seedPlaylistItem($show, $second, position: 2);

        $response = $this->generateSetlistAs($director, $show);

        $response->assertRedirect(route('studio.shows.show', $show).'#playlist');
        $response->assertSessionHas('setlist_pdf_generated', true);

        $generation = ShowSetlistGeneration::query()->where('show_id', $show->id)->first();
        $this->assertNotNull($generation);
        $this->assertSame($director->id, $generation->generated_by);
        $this->assertStringStartsWith("library/setlists/{$show->id}/setlist-", $generation->storage_reference);
        $this->assertStringEndsWith('.pdf', $generation->storage_reference);
        Storage::disk('media')->assertExists($generation->storage_reference);
        $this->assertSame('media', $generation->storage_disk);

        $this->actingAs($director)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Download Setlist PDF', false)
            ->assertSee('Regenerate Setlist PDF', false)
            ->assertSee(route('studio.shows.setlist.download', $show), false);
    }

    public function test_musician_cannot_generate_setlist_pdf(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $show = $this->seedShow(['name' => 'Musician Setlist Show']);
        $song = $this->seedSong('One Song');
        $this->seedPlaylistItem($show, $song);

        $this->actingAs($musician)->get(route('studio.shows.show', $show))->assertOk();

        $this->actingAs($musician)->post(route('studio.shows.setlist.generate', $show), [
            '_token' => session()->token(),
        ])->assertForbidden();

        $this->assertSame(0, ShowSetlistGeneration::query()->count());
    }

    public function test_musician_can_download_generated_setlist(): void
    {
        $director = $this->createDirectorUser();
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);

        $show = $this->seedShow(['name' => 'Download Setlist Show']);
        $song = $this->seedSong('Shared Song');
        $this->seedPlaylistItem($show, $song);

        $this->startPortalSession($director, $show);

        $this->actingAs($director)->post(route('studio.shows.setlist.generate', $show), [
            '_token' => session()->token(),
        ])->assertRedirect();

        $this->actingAs($musician)->get(route('studio.shows.setlist.download', $show))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($musician)->get(route('studio.shows.show', $show))
            ->assertOk()
            ->assertSee('Download Setlist PDF', false)
            ->assertDontSee('Generate Setlist PDF', false)
            ->assertDontSee('Regenerate Setlist PDF', false);
    }

    public function test_generated_setlist_docx_includes_playlist_and_song_notes(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Notes Setlist Show']);
        $song = $this->seedSong('Teardrop', bpm: 120);
        $song->update([
            'notes' => "Intro starts with percussion only.\nLeave space before verse.",
        ]);
        $this->seedPlaylistItem($show, $song, position: 22, notes: "Gat chords:\nIntro: A\nPart 1:\nA, G, D, A");

        $this->generateSetlistAs($director, $show)->assertRedirect();

        $docxContents = FakeDocxToPdfConverter::$lastDocxContents;
        $this->assertNotNull($docxContents);

        $docxPath = tempnam(sys_get_temp_dir(), 'setlist-notes-test-').'.docx';
        file_put_contents($docxPath, $docxContents);

        $text = implode("\n", $this->docxPlainText($docxPath));

        @unlink($docxPath);

        $this->assertStringContainsString('1. Teardrop', $text);
        $this->assertStringContainsString('Song notes:', $text);
        $this->assertStringContainsString('Intro starts with percussion only.', $text);
        $this->assertStringContainsString('Notes:', $text);
        $this->assertStringContainsString('Gat chords:', $text);
        $this->assertStringContainsString('Intro: A', $text);
        $this->assertStringContainsString('A, G, D, A', $text);
    }

    public function test_generated_setlist_stores_playlist_hash(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Ordered Setlist Show']);
        $first = $this->seedSong('Alpha Song', bpm: 110);
        $second = $this->seedSong('Beta Song', bpm: 130);
        $this->seedPlaylistItem($show, $first, position: 1, notes: 'First note');
        $this->seedPlaylistItem($show, $second, position: 2, notes: 'Second note');

        $expectedHash = app(StudioShowSetlistPdfService::class)->playlistHash($show->id);

        $this->startPortalSession($director, $show);

        $this->actingAs($director)->post(route('studio.shows.setlist.generate', $show), [
            '_token' => session()->token(),
        ])->assertRedirect();

        $generation = ShowSetlistGeneration::query()->where('show_id', $show->id)->firstOrFail();
        $this->assertSame($expectedHash, $generation->playlist_hash);
    }

    public function test_regeneration_creates_updated_pdf_after_playlist_changes(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Regenerate Setlist Show']);
        $first = $this->seedSong('First Song');
        $second = $this->seedSong('Second Song');
        $this->seedPlaylistItem($show, $first, position: 1);
        $this->seedPlaylistItem($show, $second, position: 2);

        $this->startPortalSession($director, $show);

        $this->actingAs($director)->post(route('studio.shows.setlist.generate', $show), [
            '_token' => session()->token(),
        ])->assertRedirect();

        $firstGeneration = ShowSetlistGeneration::query()->where('show_id', $show->id)->firstOrFail();
        $firstReference = $firstGeneration->storage_reference;
        $firstHash = $firstGeneration->playlist_hash;

        app(StudioShowPlaylistService::class)->reorderPlaylistItems(
            $show,
            ShowPlaylistItem::query()->where('show_id', $show->id)->active()->orderByDesc('position')->pluck('id')->all(),
        );

        sleep(1);

        $this->startPortalSession($director, $show);

        $this->actingAs($director)->post(route('studio.shows.setlist.generate', $show), [
            '_token' => session()->token(),
        ])->assertRedirect();

        $generations = ShowSetlistGeneration::query()->where('show_id', $show->id)->orderBy('id')->get();
        $this->assertCount(2, $generations);

        $latest = $generations->last();
        $this->assertNotSame($firstReference, $latest->storage_reference);
        $this->assertNotSame($firstHash, $latest->playlist_hash);
        Storage::disk('media')->assertExists($firstReference);
        Storage::disk('media')->assertExists($latest->storage_reference);

        $latestForShow = app(StudioShowSetlistPdfService::class)->latestForShow($show->id);
        $this->assertSame($latest->id, $latestForShow?->id);
    }

    public function test_pdf_stored_on_media_disk_without_local_permanent_file(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Storage Setlist Show']);
        $song = $this->seedSong('Storage Song');
        $this->seedPlaylistItem($show, $song);

        $this->startPortalSession($director, $show);

        $this->actingAs($director)->post(route('studio.shows.setlist.generate', $show), [
            '_token' => session()->token(),
        ])->assertRedirect();

        $generation = ShowSetlistGeneration::query()->firstOrFail();
        Storage::disk('media')->assertExists($generation->storage_reference);
        Storage::disk('local')->assertMissing($generation->storage_reference);

        $tempPattern = rtrim(sys_get_temp_dir(), '/').'/esb-setlist-'.$show->id.'-*';
        $this->assertSame([], glob($tempPattern) ?: []);
    }

    public function test_generation_surfaces_user_error_instead_of_server_error_when_temp_dir_fails(): void
    {
        $director = $this->createDirectorUser();
        $show = $this->seedShow(['name' => 'Temp Failure Show']);
        $song = $this->seedSong('Temp Song');
        $this->seedPlaylistItem($show, $song);

        config(['portal.setlist_temp_path' => '/root/no-write-setlist-temp']);

        $this->startPortalSession($director, $show);

        $this->actingAs($director)->post(route('studio.shows.setlist.generate', $show), [
            '_token' => session()->token(),
        ])
            ->assertRedirect(route('studio.shows.show', $show).'#playlist')
            ->assertSessionHas('setlist_pdf_error', 'Unable to generate setlist PDF right now.');

        $this->assertSame(0, ShowSetlistGeneration::query()->count());
    }

    private function startPortalSession(User $user, Show $show): void
    {
        $this->actingAs($user)->get(route('studio.shows.show', $show))->assertOk();
    }

    private function generateSetlistAs(User $user, Show $show)
    {
        $this->startPortalSession($user, $show);

        return $this->actingAs($user)->post(route('studio.shows.setlist.generate', $show), [
            '_token' => session()->token(),
        ]);
    }

    private function seedSong(string $name, ?int $bpm = null): Song
    {
        return Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => $name,
            'song_code' => str_pad((string) (Song::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'bpm' => $bpm,
            'status' => 'draft',
        ]);
    }

    private function seedPlaylistItem(
        Show $show,
        Song $song,
        int $position = 1,
        ?string $notes = null,
    ): ShowPlaylistItem {
        return ShowPlaylistItem::query()->create([
            'show_id' => $show->id,
            'song_id' => $song->id,
            'position' => $position,
            'notes' => $notes,
            'is_active' => true,
        ]);
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

    private function seedReferenceTables(): void
    {
        if (! SongMood::query()->exists()) {
            SongMood::query()->create([
                'name' => 'Happy',
                'slug' => 'happy',
                'colour_hex' => '#FFB23E',
                'accent_colour_hex' => '#FFD27A',
                'sort_order' => 20,
                'active' => true,
            ]);

            TimeSignature::query()->create(['label' => '4/4', 'sort_order' => 10, 'active' => true]);
            MusicalKey::query()->create(['label' => 'G major', 'tonic' => 'G', 'mode' => 'major', 'sort_order' => 10, 'active' => true]);
        }

        if (! InstrumentReference::query()->exists()) {
            foreach (InstrumentCatalog::definitions() as $instrument) {
                InstrumentReference::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'slug' => $instrument['slug'],
                    'name' => $instrument['name'],
                    'family' => $instrument['family'] ?? null,
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function docxPlainText(string $docxPath): array
    {
        $zip = new \ZipArchive;
        $zip->open($docxPath);
        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        $plain = strip_tags(str_replace('</w:p>', "\n", $xml));

        return array_values(array_filter(array_map('trim', preg_split('/\R+/', $plain) ?: [])));
    }
}
