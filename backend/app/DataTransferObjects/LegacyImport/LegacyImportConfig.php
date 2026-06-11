<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyImportConfig
{
    public function __construct(
        public string $projectRoot,
        public ?string $setlistId = null,
        public ?string $bandSlug = null,
        public ?string $setlistsPathOverride = null,
        public ?string $musiciansPathOverride = null,
    ) {}

    public static function fromPaths(
        string $projectRoot,
        ?string $setlistsPath = null,
        ?string $musiciansPath = null,
        ?string $setlistId = null,
        ?string $bandSlug = null,
    ): self {
        return new self(
            projectRoot: $projectRoot,
            setlistId: $setlistId,
            bandSlug: $bandSlug,
            setlistsPathOverride: $setlistsPath,
            musiciansPathOverride: $musiciansPath,
        );
    }

    public function setlistsPath(): string
    {
        return $this->setlistsPathOverride ?? $this->projectRoot.DIRECTORY_SEPARATOR.'setlists.json';
    }

    public function musiciansPath(): string
    {
        return $this->musiciansPathOverride ?? $this->projectRoot.DIRECTORY_SEPARATOR.'musicians.json';
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
