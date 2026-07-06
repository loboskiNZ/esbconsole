<?php

namespace Tests\Unit;

use App\Services\TaggedLyricsParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaggedLyricsParserTest extends TestCase
{
    private TaggedLyricsParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new TaggedLyricsParser;
    }

    public function test_tags_render_as_section_headings(): void
    {
        $sections = $this->parser->parse(<<<'LYRICS'
{intro}
Instrumental opening

{verse1}
First verse lyrics go here
LYRICS);

        $this->assertSame('Intro', $sections[0]['heading']);
        $this->assertSame(['Instrumental opening', ''], $sections[0]['lines']);
        $this->assertSame('Verse 1', $sections[1]['heading']);
        $this->assertSame(['First verse lyrics go here'], $sections[1]['lines']);
    }

    #[DataProvider('humanizedTagProvider')]
    public function test_unknown_and_known_tags_humanize_safely(string $tag, string $expected): void
    {
        $this->assertSame($expected, $this->parser->humanizeTag($tag));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function humanizedTagProvider(): array
    {
        return [
            'intro' => ['{intro}', 'Intro'],
            'verse1' => ['{verse1}', 'Verse 1'],
            'verse with space' => ['{verse 1}', 'Verse 1'],
            'chorus1' => ['{chorus1}', 'Chorus 1'],
            'middle 8' => ['{middle 8}', 'Middle 8'],
            'solo' => ['{solo}', 'Solo'],
            'custom tag' => ['{custom-section}', 'Custom Section'],
        ];
    }

    public function test_line_breaks_and_blank_lines_are_preserved(): void
    {
        $sections = $this->parser->parse(<<<'LYRICS'
{chorus}
Line one
Line two

Line after blank
LYRICS);

        $this->assertSame([
            'Line one',
            'Line two',
            '',
            'Line after blank',
        ], $sections[0]['lines']);
    }

    public function test_braces_inside_lyric_lines_are_not_treated_as_tags(): void
    {
        $this->assertFalse($this->parser->isTagLine('She said {hello} to me'));
        $this->assertFalse($this->parser->isTagLine('{intro} with extra text'));

        $sections = $this->parser->parse(<<<'LYRICS'
{verse1}
She said {hello} to me
Not a {tag}
LYRICS);

        $this->assertSame('Verse 1', $sections[0]['heading']);
        $this->assertSame([
            'She said {hello} to me',
            'Not a {tag}',
        ], $sections[0]['lines']);
    }
}
