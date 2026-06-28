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

        $docxPath = realpath($docxPath) ?: $docxPath;
        $outputDir = realpath(dirname($pdfPath)) ?: dirname($pdfPath);
        $pdfPath = $outputDir.'/'.basename($pdfPath);
        $binary = $this->resolveBinary();
        $profileDir = $this->createProfileDirectory();
        $userInstallation = 'file://'.$profileDir;

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0700, true) && ! is_dir($outputDir)) {
            throw new RuntimeException("Unable to create PDF output directory at {$outputDir}.");
        }

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--norestore',
                '-env:UserInstallation='.$userInstallation,
                '--convert-to',
                'pdf',
                '--outdir',
                $outputDir,
                $docxPath,
            ]);
            $process->setTimeout(120);
            $process->setEnv([
                'HOME' => $this->resolveHomeDirectory($profileDir),
                'TMPDIR' => sys_get_temp_dir(),
            ]);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'LibreOffice PDF conversion failed.');
            }

            $expectedPdf = $outputDir.'/'.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';

            if (! is_file($expectedPdf)) {
                $created = glob($outputDir.'/*.pdf') ?: [];

                throw new RuntimeException($this->missingPdfMessage($expectedPdf, $created, $process));
            }

            if ($expectedPdf !== $pdfPath && ! rename($expectedPdf, $pdfPath)) {
                throw new RuntimeException('Unable to move converted PDF into place.');
            }
        } finally {
            $this->cleanupProfileDirectory($profileDir);
        }
    }

    private function resolveBinary(): string
    {
        $configured = config('portal.setlist_pdf_binary');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach (['soffice', 'libreoffice', '/Applications/LibreOffice.app/Contents/MacOS/soffice'] as $candidate) {
            $process = new Process([$candidate, '--version']);
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        throw new RuntimeException('LibreOffice (soffice) is not available for PDF conversion.');
    }

    private function resolveHomeDirectory(string $profileDir): string
    {
        $configured = config('portal.setlist_runtime_home');

        if (is_string($configured) && $configured !== '' && is_dir($configured)) {
            return $configured;
        }

        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');

        if ($home !== '' && is_dir($home)) {
            return $home;
        }

        if (is_dir('/home/forge')) {
            return '/home/forge';
        }

        return $profileDir;
    }

    private function createProfileDirectory(): string
    {
        $profileDir = sys_get_temp_dir().'/lo-setlist-'.bin2hex(random_bytes(8));

        if (! mkdir($profileDir, 0700, true) && ! is_dir($profileDir)) {
            throw new RuntimeException("Unable to create LibreOffice profile directory at {$profileDir}.");
        }

        return $profileDir;
    }

    /**
     * @param  list<string>  $created
     */
    private function missingPdfMessage(string $expectedPdf, array $created, Process $process): string
    {
        $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        $details = $output !== '' ? " LibreOffice output: {$output}" : '';

        if ($created === []) {
            return "LibreOffice did not produce a PDF file at {$expectedPdf}.{$details}";
        }

        return sprintf(
            'LibreOffice did not produce a PDF file at %s. Found: %s.%s',
            $expectedPdf,
            implode(', ', $created),
            $details,
        );
    }

    private function cleanupProfileDirectory(string $profileDir): void
    {
        if (! is_dir($profileDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($profileDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($profileDir);
    }
}
