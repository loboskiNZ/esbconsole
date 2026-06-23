<?php

namespace App\Services\FolderChartImport;

use App\DataTransferObjects\FolderChartImport\FolderChartFileCandidate;
use App\DataTransferObjects\FolderChartImport\FolderChartImportConfig;
use RuntimeException;

class FolderChartImportScanner
{
    /**
     * @return list<array{folder_name: string, files: list<FolderChartFileCandidate>}>
     */
    public function scan(FolderChartImportConfig $config): array
    {
        $root = $config->rootPath;

        if (! is_dir($root)) {
            throw new RuntimeException("Import root directory not found: {$root}");
        }

        $entries = scandir($root) ?: [];
        $songs = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $folderPath = $root.DIRECTORY_SEPARATOR.$entry;

            if (! is_dir($folderPath)) {
                continue;
            }

            $files = $this->scanSongFolder($entry, $folderPath);
            $songs[] = [
                'folder_name' => $entry,
                'files' => $files,
            ];
        }

        usort($songs, fn (array $a, array $b) => strnatcasecmp($a['folder_name'], $b['folder_name']));

        return $songs;
    }

    /**
     * @return list<FolderChartFileCandidate>
     */
    private function scanSongFolder(string $folderName, string $folderPath): array
    {
        $entries = scandir($folderPath) ?: [];
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $absolutePath = $folderPath.DIRECTORY_SEPARATOR.$entry;

            if (! is_file($absolutePath)) {
                continue;
            }

            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            $stem = pathinfo($entry, PATHINFO_FILENAME);
            $validExtension = in_array($extension, FolderChartImportConfig::ALLOWED_EXTENSIONS, true);
            $mimeType = $validExtension ? $this->detectMimeType($absolutePath, $extension) : null;
            $checksum = is_readable($absolutePath) ? hash_file('sha256', $absolutePath) ?: null : null;

            $files[] = new FolderChartFileCandidate(
                songFolderName: $folderName,
                relativePath: $folderName.'/'.$entry,
                absolutePath: $absolutePath,
                originalFilename: $entry,
                filenameStem: $stem,
                extension: $extension,
                checksum: $checksum,
                fileSize: is_file($absolutePath) ? (int) filesize($absolutePath) : 0,
                mimeType: $mimeType,
                validExtension: $validExtension,
            );
        }

        usort($files, fn (FolderChartFileCandidate $a, FolderChartFileCandidate $b) => strnatcasecmp(
            $a->originalFilename,
            $b->originalFilename,
        ));

        return $files;
    }

    private function detectMimeType(string $absolutePath, string $extension): ?string
    {
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($absolutePath);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
    }
}
