<?php

namespace App\DataTransferObjects\LegacyImport;

final readonly class LegacyDryRunValidationReport
{
    /**
     * @param  array<string, int|float>  $counts
     * @param  array<string, mixed>  $assetFindings
     * @param  array<string, mixed>  $mappingFindings
     * @param  array<string, mixed>  $issues
     */
    public function __construct(
        public string $status,
        public string $importBatchId,
        public string $legacySetlistId,
        public string $showName,
        public string $projectRoot,
        public array $counts,
        public array $assetFindings,
        public array $mappingFindings,
        public array $issues,
        public string $generatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'import_batch_id' => $this->importBatchId,
            'legacy_setlist_id' => $this->legacySetlistId,
            'show_name' => $this->showName,
            'project_root' => $this->projectRoot,
            'generated_at' => $this->generatedAt,
            'counts' => $this->counts,
            'asset_findings' => $this->assetFindings,
            'mapping_findings' => $this->mappingFindings,
            'issues' => $this->issues,
        ];
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $flags) ?: '{}';
    }
}
