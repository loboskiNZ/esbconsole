<?php

namespace App\Services\LegacyImport;

use App\DataTransferObjects\LegacyImport\LegacyImportConfig;
use RuntimeException;

class LegacySetlistLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(LegacyImportConfig $config): array
    {
        $path = $config->setlistsPath();

        if (! is_file($path)) {
            throw new RuntimeException("Legacy setlists file not found: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("Invalid setlists.json at {$path}");
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveActiveSetlist(array $setlistData, ?string $setlistId): array
    {
        $id = $setlistId ?? ($setlistData['activeSetlistId'] ?? null);

        if ($id === null || ! isset($setlistData['setlists'][$id])) {
            throw new RuntimeException('Legacy setlist not found in setlists.json');
        }

        return [
            'id' => $id,
            'setlist' => $setlistData['setlists'][$id],
            'songs' => $setlistData['songs'] ?? [],
        ];
    }
}
