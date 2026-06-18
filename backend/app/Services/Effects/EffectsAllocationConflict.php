<?php

namespace App\Services\Effects;

readonly class EffectsAllocationConflict
{
    /**
     * @param  list<string>  $packageSources
     */
    public function __construct(
        public int $effectDefinitionId,
        public string $effectSlug,
        public string $effectName,
        public array $packageSources,
        public string $reason,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'effect_definition_id' => $this->effectDefinitionId,
            'effect_slug' => $this->effectSlug,
            'effect_name' => $this->effectName,
            'package_sources' => $this->packageSources,
            'reason' => $this->reason,
        ];
    }
}
