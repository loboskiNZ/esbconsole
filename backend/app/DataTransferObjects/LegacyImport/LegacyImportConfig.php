<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyImportConfig
{
    public function __construct(
        public string $projectRoot,
        public ?string $setlistId = null,
        public ?string $bandSlug = null,
    ) {}

    public function setlistsPath(): string
    {
        return $this->projectRoot.DIRECTORY_SEPARATOR.'setlists.json';
    }

    public function musiciansPath(): string
    {
        return $this->projectRoot.DIRECTORY_SEPARATOR.'musicians.json';
    }

    public function chartsDir(): string
    {
        return $this->projectRoot.DIRECTORY_SEPARATOR.'charts';
    }

    public function uploadsDir(): string
    {
        return $this->projectRoot.DIRECTORY_SEPARATOR.'uploads';
    }
}
