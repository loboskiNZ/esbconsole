<?php

namespace Tests\Feature;

use App\Contracts\DocxToPdfConverterInterface;
use App\Models\Library\Chart;
use App\Models\Library\InstrumentPart;
use App\Models\Library\MusicalKey;
use App\Models\Library\Song;
use App\Models\Library\SongAsset;
use App\Models\Library\SongInstrumentPart;
use App\Models\Library\SongMood;
use App\Models\Library\TimeSignature;
use App\Models\User;
use App\Support\SongAssetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsStudioRoles;
use Tests\Concerns\EnsuresPortalBand;
use Tests\Support\FakeDocxToPdfConverter;
use Tests\TestCase;

class StudioSongLyricsTest extends TestCase
{
    use AssignsStudioRoles;
    use EnsuresPortalBand;
    use RefreshDatabase;

    private const EXAMPLE_LYRICS = <<<'LYRICS'
{intro}
Instrumental opening

{verse1}
First verse lyrics go here

{chorus1}
Chorus lyrics go here

{bridge}
Bridge lyrics go here

{outro}
Ending lyrics or notes
LYRICS;

    protected function setUp(): void
    {
        parent::setUp();

        FakeDocxToPdfConverter::$lastDocxPath = null;
        FakeDocxToPdfConverter::$lastDocxContents = null;

        config([
            'portal.band_id' => 1,
            'portal.library_connection' => 'sqlite',
        ]);

        $this->ensurePortalBand();
        $this->seedReferenceTables();
        $this->app->instance(DocxToPdfConverterInterface::class, new FakeDocxToPdfConverter);

        Storage::fake('media');
        Storage::fake('local');
    }

    public function test_song_edit_form_does_not_nest_lyrics_pdf_form(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Form Structure Song');

        $this->actingAs($director)->get(route('songs.edit', $song))
            ->assertOk()
            ->assertSee('action="'.route('songs.lyrics.pdf', $song).'"', false)
            ->assertSee('esb-studio__song-lyrics-generate-form', false)
            ->assertSee('id="song-edit-form"', false);
    }

    public function test_put_to_lyrics_pdf_route_is_not_allowed(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Method Check Song');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->put(route('songs.lyrics.pdf', $song), [
            '_token' => session()->token(),
        ])->assertStatus(405);
    }

    public function test_director_sees_lyrics_section_on_song_edit_page(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Lyrics Edit Song');

        $this->actingAs($director)->get(route('songs.edit', $song))
            ->assertOk()
            ->assertSee('for="song-lyrics">Lyrics', false)
            ->assertSee('{intro}', false)
            ->assertSee('Generate Lyrics PDF below', false)
            ->assertSee(route('songs.lyrics.pdf', $song), false);
    }

    public function test_director_can_save_and_reload_tagged_lyrics(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Tagged Lyrics Song');
        $keyId = MusicalKey::query()->where('label', 'G major')->value('id');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->put(route('songs.update', $song), [
            '_token' => session()->token(),
            'name' => 'Tagged Lyrics Song',
            'bpm' => 96,
            'musical_key_id' => $keyId,
            'notes' => 'Playlist-visible notes',
            'lyrics' => self::EXAMPLE_LYRICS,
        ])->assertRedirect(route('studio.charts.show', $song));

        $song->refresh();
        $this->assertSame(self::EXAMPLE_LYRICS, $song->lyrics);
        $this->assertSame('Playlist-visible notes', $song->notes);
        $this->assertSame(96, $song->bpm);

        $this->actingAs($director)->get(route('songs.edit', $song))
            ->assertOk()
            ->assertSee('Instrumental opening', false)
            ->assertSee('{chorus1}', false);
    }

    public function test_director_can_generate_lyrics_pdf_from_saved_lyrics(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('PDF Lyrics Song');
        $song->update(['lyrics' => self::EXAMPLE_LYRICS]);

        $response = $this->actingAs($director)->post(route('songs.lyrics.pdf', $song), [
            '_token' => session()->token(),
        ]);

        $response
            ->assertRedirect(route('songs.edit', $song))
            ->assertSessionHas('lyrics_pdf_generated', true);

        $docx = FakeDocxToPdfConverter::$lastDocxContents;
        $this->assertNotNull($docx);
        $documentXml = $this->docxDocumentXml($docx);
        $this->assertStringContainsString('PDF Lyrics Song', $documentXml);
        $this->assertStringContainsString('INTRO', $documentXml);
        $this->assertStringContainsString('VERSE 1', $documentXml);
        $this->assertStringContainsString('CHORUS 1', $documentXml);
        $this->assertStringContainsString('Instrumental opening', $documentXml);
        $this->assertStringNotContainsString('{intro}', $documentXml);
        $this->assertStringNotContainsString('{chorus1}', $documentXml);

        $asset = SongAsset::query()
            ->where('song_id', $song->id)
            ->where('asset_type', SongAssetType::LYRICS_PDF)
            ->first();
        $this->assertNotNull($asset);
        $this->assertSame('Lyrics PDF', $asset->label);
        Storage::disk('media')->assertExists($asset->storage_reference);
    }

    public function test_braces_inside_lyric_lines_remain_in_pdf_body(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('Brace Lyrics Song');
        $song->update([
            'lyrics' => "{verse1}\nShe said {hello} to me\nNot a {tag}",
        ]);

        $this->actingAs($director)->post(route('songs.lyrics.pdf', $song), [
            '_token' => session()->token(),
        ])
            ->assertRedirect(route('songs.edit', $song))
            ->assertSessionHas('lyrics_pdf_generated', true);

        $documentXml = $this->docxDocumentXml(FakeDocxToPdfConverter::$lastDocxContents);
        $this->assertStringContainsString('She said {hello} to me', $documentXml);
        $this->assertStringContainsString('Not a {tag}', $documentXml);
    }

    public function test_generate_lyrics_pdf_requires_saved_lyrics(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSong('No Lyrics Yet');

        $this->actingAs($director)->post(route('songs.lyrics.pdf', $song), [
            '_token' => session()->token(),
        ])
            ->assertRedirect(route('songs.edit', $song))
            ->assertSessionHas('lyrics_pdf_error');
    }

    public function test_musician_cannot_edit_lyrics_or_generate_pdf(): void
    {
        $musician = User::factory()->create();
        $this->assignMusicianRole($musician);
        $song = $this->seedSong('Protected Lyrics Song');
        $song->update(['lyrics' => self::EXAMPLE_LYRICS]);

        $this->actingAs($musician)->get(route('songs.edit', $song))->assertForbidden();
        $this->actingAs($musician)->post(route('songs.lyrics.pdf', $song), [
            '_token' => session()->token(),
        ])->assertForbidden();
    }

    public function test_existing_chart_and_file_behaviour_is_unchanged_after_lyrics_save(): void
    {
        $director = $this->createDirectorUser();
        $song = $this->seedSongWithRelationships('Relationship Song', '040');

        $this->actingAs($director)->get(route('songs.edit', $song));

        $this->actingAs($director)->put(route('songs.update', $song), [
            '_token' => session()->token(),
            'name' => 'Relationship Song',
            'lyrics' => self::EXAMPLE_LYRICS,
        ])->assertRedirect(route('studio.charts.show', $song));

        $this->assertSame(1, Chart::query()->where('song_id', $song->id)->count());
        $this->assertSame(1, SongAsset::query()->where('song_id', $song->id)->count());
        $this->assertSame(1, SongInstrumentPart::query()->where('song_id', $song->id)->count());
    }

    private function seedSong(string $name, string $songCode = '001'): Song
    {
        return Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => $songCode,
            'name' => $name,
            'status' => Song::STATUS_READY,
        ]);
    }

    private function seedSongWithRelationships(string $name, string $songCode): Song
    {
        $song = $this->seedSong($name, $songCode);

        $part = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => 'Bass',
            'active' => true,
        ]);

        $chart = Chart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'title' => 'Bass Chart',
            'original_filename' => 'bass.pdf',
            'storage_reference' => 'charts/1/'.$songCode.'/bass.pdf',
            'checksum' => hash('sha256', 'bass'),
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);

        SongInstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
            'chart_id' => $chart->id,
        ]);

        SongAsset::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'asset_type' => 'reference_track',
            'label' => 'Reference MP3',
            'storage_disk' => 'library',
            'storage_reference' => 'assets/1/'.$songCode.'/reference.mp3',
            'original_filename' => 'reference.mp3',
            'checksum' => hash('sha256', 'reference'),
            'mime_type' => 'audio/mpeg',
            'file_size' => 500,
            'sort_order' => 1,
        ]);

        return $song;
    }

    private function seedReferenceTables(): void
    {
        if (SongMood::query()->exists()) {
            return;
        }

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

    private function docxDocumentXml(?string $docx): string
    {
        $this->assertNotNull($docx);

        $zip = new \ZipArchive;
        $temp = tempnam(sys_get_temp_dir(), 'lyrics-docx-');
        file_put_contents($temp, $docx);

        try {
            $this->assertTrue($zip->open($temp) === true);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            return $xml !== false ? $xml : '';
        } finally {
            @unlink($temp);
        }
    }
}
