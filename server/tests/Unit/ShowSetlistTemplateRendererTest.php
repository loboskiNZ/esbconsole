<?php

namespace Tests\Unit;

use App\Services\ShowSetlistTemplateRenderer;
use Tests\TestCase;

class ShowSetlistTemplateRendererTest extends TestCase
{
    public function test_renderer_fills_setlist_and_song_blocks_in_order(): void
    {
        $templatePath = dirname(base_path()).'/templates/esb_setlist_template_tagged.docx';
        $outputPath = storage_path('app/temp/setlist-template-test.docx');

        @mkdir(dirname($outputPath), 0777, true);

        app(ShowSetlistTemplateRenderer::class)->render(
            templatePath: $templatePath,
            outputDocxPath: $outputPath,
            setlistName: 'Ordered Setlist Show',
            songs: [
                [
                    'idx' => 1,
                    'title' => 'Alpha Song',
                    'song_code' => '001',
                    'key' => 'G major',
                    'bpm' => '110',
                    'duration' => '00:03:30',
                    'instrument_parts' => 'Bass',
                    'notes' => 'First note',
                ],
                [
                    'idx' => 2,
                    'title' => 'Beta Song',
                    'song_code' => '002',
                    'key' => '—',
                    'bpm' => '130',
                    'duration' => '—',
                    'instrument_parts' => 'Keys',
                    'notes' => 'Second note',
                ],
            ],
        );

        $text = implode("\n", $this->docxPlainText($outputPath));

        $this->assertStringContainsString('Setlist: Ordered Setlist Show', $text);
        $this->assertStringContainsString('1. Alpha Song', $text);
        $this->assertStringContainsString('2. Beta Song', $text);
        $this->assertStringContainsString('First note', $text);
        $this->assertStringContainsString('Second note', $text);

        @unlink($outputPath);
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
