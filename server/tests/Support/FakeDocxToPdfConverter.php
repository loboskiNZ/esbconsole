<?php

namespace Tests\Support;

use App\Contracts\DocxToPdfConverterInterface;
use RuntimeException;

class FakeDocxToPdfConverter implements DocxToPdfConverterInterface
{
    public function convert(string $docxPath, string $pdfPath): void
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException("DOCX source not found at {$docxPath}.");
        }

        $directory = dirname($pdfPath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory at {$directory}.");
        }

        file_put_contents($pdfPath, "%PDF-1.4\n%Fake setlist PDF for tests\nDOCX size: ".filesize($docxPath));
    }
}
