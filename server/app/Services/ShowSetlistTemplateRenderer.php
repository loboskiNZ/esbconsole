<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

class ShowSetlistTemplateRenderer
{
    /**
     * @param  list<array{
     *     idx: int,
     *     title: string,
     *     song_code: string,
     *     key: string,
     *     bpm: string,
     *     duration: string,
     *     instrument_parts: string,
     *     notes: string,
     * }>  $songs
     * @param  array<string, string>  $headerValues
     */
    public function render(
        string $templatePath,
        string $outputDocxPath,
        string $setlistName,
        array $songs,
        array $headerValues = [],
    ): void {
        if (! is_file($templatePath)) {
            throw new RuntimeException("Setlist template not found at {$templatePath}.");
        }

        $preparedTemplate = $this->prepareTemplateCopy($templatePath);

        try {
            $template = new TemplateProcessor($preparedTemplate);
            $template->setMacroOpeningChars('{');
            $template->setMacroClosingChars('}');

            $values = array_merge(['setlist' => $setlistName], $headerValues);
            $template->setValues($this->escapeTemplateValues($values));

            if ($songs === []) {
                $template->deleteBlock('songs');
            } else {
                $template->cloneBlock('songs', count($songs), true, true);

                foreach ($songs as $index => $song) {
                    $row = (string) ($index + 1);

                    foreach ($this->escapeTemplateValues($song) as $field => $value) {
                        $template->setValue("{$field}#{$row}", $value);
                    }
                }
            }

            $directory = dirname($outputDocxPath);

            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException("Unable to create temporary directory at {$directory}.");
            }

            $template->saveAs($outputDocxPath);
        } finally {
            @unlink($preparedTemplate);
        }
    }

    private function prepareTemplateCopy(string $templatePath): string
    {
        $tempCopy = rtrim(sys_get_temp_dir(), '/').'/esb-setlist-templates/'.uniqid('template-', true).'.docx';

        if (! is_dir(dirname($tempCopy)) && ! mkdir(dirname($tempCopy), 0700, true) && ! is_dir(dirname($tempCopy))) {
            throw new RuntimeException('Unable to create temporary template directory.');
        }

        if (! copy($templatePath, $tempCopy)) {
            throw new RuntimeException('Unable to copy setlist template for processing.');
        }

        $zip = new ZipArchive;

        if ($zip->open($tempCopy) !== true) {
            throw new RuntimeException('Unable to open setlist template copy.');
        }

        $documentXml = $zip->getFromName('word/document.xml');

        if ($documentXml === false) {
            $zip->close();
            throw new RuntimeException('Setlist template is missing word/document.xml.');
        }

        $documentXml = str_replace('{#songs}', '{songs}', $documentXml);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $tempCopy;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function escapeTemplateValues(array $values): array
    {
        $escaped = [];

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $escaped[$key] = $this->escapeTemplateValue($value);
        }

        return $escaped;
    }

    private function escapeTemplateValue(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
