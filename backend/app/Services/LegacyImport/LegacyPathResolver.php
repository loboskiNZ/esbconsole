<?php

namespace App\Services\LegacyImport;

use App\DataTransferObjects\LegacyImport\LegacyImportConfig;

class LegacyPathResolver
{
    public function __construct(
        private readonly LegacyImportConfig $config,
    ) {}

    public function resolveProjectRelative(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->config->projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, './'));
    }

    public function snippetApiPathToPhysical(string $apiPath): string
    {
        $relative = preg_replace('#^/api/#', '', $apiPath) ?? $apiPath;

        return $this->config->projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    public function isNoChartPlaceholder(string $path): bool
    {
        return str_ends_with(strtolower($path), 'nochart.txt');
    }

    public function expectedChartStorageReference(string $bandSlug, string $songCode, string $roleSlug): string
    {
        return "migrated/charts/{$bandSlug}/{$songCode}/{$roleSlug}.pdf";
    }

    public function expectedSnippetStorageReference(
        string $bandSlug,
        string $songCode,
        string $roleSlug,
        string $cueNumber,
    ): string {
        return "migrated/snippets/{$bandSlug}/{$songCode}/{$roleSlug}/{$cueNumber}.png";
    }

    public function fileChecksum(?string $absolutePath): ?string
    {
        if ($absolutePath === null || ! is_file($absolutePath)) {
            return null;
        }

        return hash_file('sha256', $absolutePath) ?: null;
    }

    public function discoverRoleChartPdf(string $legacySongId, string $roleSlug): ?string
    {
        $songDir = $this->config->chartsDir().DIRECTORY_SEPARATOR.$legacySongId;

        if (! is_dir($songDir)) {
            return null;
        }

        foreach (['pdf', 'PDF'] as $ext) {
            $candidate = $songDir.DIRECTORY_SEPARATOR.$roleSlug.'.'.$ext;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $files = scandir($songDir) ?: [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $lower = strtolower($file);

            if (str_starts_with($lower, $roleSlug.'.')) {
                return $songDir.DIRECTORY_SEPARATOR.$file;
            }
        }

        return null;
    }
}
