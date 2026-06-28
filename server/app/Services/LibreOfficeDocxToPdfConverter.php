<?php

namespace App\Services;

use App\Contracts\DocxToPdfConverterInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

class LibreOfficeDocxToPdfConverter implements DocxToPdfConverterInterface
{
    public function convert(string $docxPath, string $pdfPath): void
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException("DOCX source not found at {$docxPath}.");
        }

        $outputDir = dirname($pdfPath);
        $binary = $this->resolveBinary();

        $process = Process::fromShellCommandline(sprintf(
            '%s --headless --convert-to pdf --outdir %s %s',
            escapeshellarg($binary),
            escapeshellarg($outputDir),
            escapeshellarg($docxPath),
        ));
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'LibreOffice PDF conversion failed.');
        }

        $expectedPdf = $outputDir.'/'.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';

        if (! is_file($expectedPdf)) {
            throw new RuntimeException('LibreOffice did not produce a PDF file.');
        }

        if ($expectedPdf !== $pdfPath && ! rename($expectedPdf, $pdfPath)) {
            throw new RuntimeException('Unable to move converted PDF into place.');
        }
    }

    private function resolveBinary(): string
    {
        $configured = config('portal.setlist_pdf_binary');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach (['soffice', 'libreoffice', '/Applications/LibreOffice.app/Contents/MacOS/soffice'] as $candidate) {
            $process = Process::fromShellCommandline(sprintf('%s --version', escapeshellarg($candidate)));
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        throw new RuntimeException('LibreOffice (soffice) is not available for PDF conversion.');
    }
}
