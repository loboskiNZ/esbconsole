<?php

namespace App\Services\Console;

use Carbon\Carbon;

/**
 * Builds the read-only PH043 Configuration workspace view model from learned summary data.
 */
class X32ConfigurationWorkspaceBuilder
{
    /** @var list<string> */
    private const REQUIRED_SECTIONS = [
        'identity',
        'globals',
        'channels',
        'buses',
        'dcas',
        'matrices',
        'fx',
    ];

    /** @var list<string> */
    private const CORE_IDENTITY_FIELDS = [
        'console_name',
        'device_key',
        'model',
        'scene_number',
    ];

    /** @var list<string> */
    private const OPERATOR_MISSING_AUDIT_LABELS = [
        'firmware' => 'Firmware not captured yet',
        'sample_rate' => 'Sample rate not captured yet',
        'clock_source' => 'Clock source not captured yet',
        'fx_inventory' => 'FX inventory not captured yet',
        'dca_membership' => 'DCA membership not captured yet',
        'matrix_sources' => 'Matrix sources not captured yet',
    ];

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function build(array $summary): array
    {
        $configuration = is_array($summary['configuration'] ?? null)
            ? $summary['configuration']
            : null;

        $audit = $this->evaluateAudit($configuration);

        return [
            'header' => $this->buildHeader($summary, $configuration),
            'status' => $this->buildStatus($audit),
            'status_legend' => $this->buildStatusLegend($audit),
            'identity' => $this->buildIdentityRow($summary, $configuration, $audit),
            'learned_at_display' => $this->formatLearnedTimestamp(
                is_array($configuration) ? ($configuration['learned_at'] ?? null) : null,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $configuration
     * @return array<string, mixed>
     */
    private function buildHeader(array $summary, ?array $configuration): array
    {
        $consoleName = $this->resolveConsoleName($configuration, $summary);
        $sceneSegment = $this->formatSceneSegment($configuration, $summary);

        return [
            'context' => 'ESB Console',
            'title' => 'X32 Configuration',
            'learn_context' => sprintf('Learned from %s · %s', $consoleName, $sceneSegment),
        ];
    }

    /**
     * @param  array{state: string, missing_items: list<string>}  $audit
     * @return array{state: string, label: string, hint: string}
     */
    private function buildStatus(array $audit): array
    {
        return match ($audit['state']) {
            'not_learned' => [
                'state' => 'not_learned',
                'label' => 'Not learned',
                'hint' => 'Configuration has not been learned yet',
            ],
            'needs_attention' => [
                'state' => 'needs_attention',
                'label' => 'Needs attention',
                'hint' => 'Warnings or structural issues require review',
            ],
            'complete' => [
                'state' => 'complete',
                'label' => 'Complete',
                'hint' => 'PH043.02 configuration structure captured and healthy',
            ],
            default => [
                'state' => 'partial',
                'label' => 'Partial',
                'hint' => 'Some PH043 audit areas are not fully captured yet',
            ],
        };
    }

    /**
     * @param  array{state: string, missing_items: list<string>}  $audit
     * @return list<array{state: string, label: string, description: string, active: bool}>
     */
    private function buildStatusLegend(array $audit): array
    {
        $currentState = $audit['state'];

        return [
            [
                'state' => 'complete',
                'label' => 'Complete',
                'description' => 'Configuration exists, no warnings, and current PH043.02 audit-required areas are captured sufficiently.',
                'active' => $currentState === 'complete',
            ],
            [
                'state' => 'partial',
                'label' => 'Partial',
                'description' => 'Configuration exists but one or more PH043 audit-required areas are not fully captured.',
                'active' => $currentState === 'partial',
            ],
            [
                'state' => 'needs_attention',
                'label' => 'Needs attention',
                'description' => 'Warnings are present or required configuration sections are structurally missing or corrupt.',
                'active' => $currentState === 'needs_attention',
            ],
            [
                'state' => 'not_learned',
                'label' => 'Not learned',
                'description' => 'No configuration block has been learned for this console yet.',
                'active' => $currentState === 'not_learned',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $configuration
     * @param  array{state: string, missing_items: list<string>}  $audit
     * @return array<string, mixed>
     */
    private function buildIdentityRow(array $summary, ?array $configuration, array $audit): array
    {
        $identity = is_array($configuration['identity'] ?? null) ? $configuration['identity'] : [];
        $globals = is_array($configuration['globals'] ?? null) ? $configuration['globals'] : [];
        $warnings = is_array($configuration['warnings'] ?? null) ? $configuration['warnings'] : [];
        $source = (string) ($configuration['source'] ?? $summary['transport'] ?? 'unknown');
        $learnedAt = $this->formatLearnedTimestamp($configuration['learned_at'] ?? null);

        $warningsCount = count($warnings);

        $learnStatusFields = [
            $this->displayField('Source', $this->formatLearnSource($source)),
            $this->displayField('Learned At', $learnedAt ?? 'Not captured yet'),
            $this->displayField('Warnings', (string) $warningsCount, attention: $warningsCount > 0),
        ];

        foreach ($audit['missing_items'] as $missingItem) {
            $learnStatusFields[] = $this->displayField('Not yet captured', $missingItem, attention: true);
        }

        return [
            'console' => [
                'title' => 'Console',
                'icon' => 'console',
                'fields' => [
                    $this->displayField('Console Name', $this->fieldDisplay($identity['console_name'] ?? null, $this->resolveConsoleName($configuration, $summary))),
                    $this->displayField('Device Key', $this->fieldDisplay($identity['device_key'] ?? null)),
                    $this->displayField('Model', $this->fieldDisplay($identity['model'] ?? null)),
                    $this->displayField('Firmware', $this->fieldDisplay(
                        $identity['firmware'] ?? $globals['firmware'] ?? null,
                    )),
                ],
            ],
            'scene' => [
                'title' => 'Scene',
                'icon' => 'scene',
                'fields' => [
                    $this->displayField('Scene Number', $this->fieldDisplay(
                        $identity['scene_number'] ?? null,
                        $this->resolveSceneNumber($configuration, $summary),
                    )),
                    $this->displayField('Scene Name', $this->fieldDisplay(
                        $identity['scene_name'] ?? null,
                        $this->resolveSceneName($configuration, $summary),
                    )),
                    $this->displayField('Learned At', $learnedAt ?? 'Not captured yet'),
                ],
            ],
            'clock' => [
                'title' => 'Sample Rate / Clock',
                'icon' => 'clock',
                'fields' => [
                    $this->displayField('Sample Rate', $this->fieldDisplay(
                        $globals['sample_rate'] ?? $identity['sample_rate'] ?? null,
                        null,
                        fn (mixed $value): string => $this->formatSampleRate($value),
                    )),
                    $this->displayField('Clock Source', $this->fieldDisplay(
                        $globals['clock_source'] ?? $identity['clock_source'] ?? null,
                        null,
                        fn (mixed $value): string => $this->formatClockSource($value),
                    )),
                ],
            ],
            'learn_status' => [
                'title' => 'Learn Status',
                'icon' => 'learn_status',
                'fields' => $learnStatusFields,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     * @return array{state: string, missing_items: list<string>}
     */
    private function evaluateAudit(?array $configuration): array
    {
        if ($configuration === null) {
            return [
                'state' => 'not_learned',
                'missing_items' => [],
            ];
        }

        if ($this->hasStructuralIssues($configuration)) {
            return [
                'state' => 'needs_attention',
                'missing_items' => [],
            ];
        }

        $warnings = is_array($configuration['warnings'] ?? null) ? $configuration['warnings'] : [];

        if ($warnings !== []) {
            return [
                'state' => 'needs_attention',
                'missing_items' => [],
            ];
        }

        $missingItems = $this->collectMissingAuditItems($configuration);

        if ($missingItems !== []
            || ! $this->hasCoreIdentityCaptured($configuration)
            || ! $this->hasAuditSectionsPopulated($configuration)) {
            return [
                'state' => 'partial',
                'missing_items' => $missingItems,
            ];
        }

        return [
            'state' => 'complete',
            'missing_items' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function hasStructuralIssues(array $configuration): bool
    {
        foreach (self::REQUIRED_SECTIONS as $section) {
            if (! array_key_exists($section, $configuration) || ! is_array($configuration[$section])) {
                return true;
            }
        }

        $fx = $configuration['fx'];

        return ! array_key_exists('learned', $fx);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function hasCoreIdentityCaptured(array $configuration): bool
    {
        $identity = $configuration['identity'];

        foreach (self::CORE_IDENTITY_FIELDS as $field) {
            if (! $this->isFieldLearned($identity[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function hasAuditSectionsPopulated(array $configuration): bool
    {
        foreach (['channels', 'buses', 'dcas', 'matrices'] as $section) {
            $items = $configuration[$section];

            if (! is_array($items) || $items === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return list<string>
     */
    private function collectMissingAuditItems(array $configuration): array
    {
        $identity = $configuration['identity'];
        $globals = $configuration['globals'];
        $missing = [];

        if (! $this->isConfigurationValueLearned($identity['firmware'] ?? null, $globals['firmware'] ?? null)) {
            $missing[] = self::OPERATOR_MISSING_AUDIT_LABELS['firmware'];
        }

        if (! $this->isConfigurationValueLearned($globals['sample_rate'] ?? null, $identity['sample_rate'] ?? null)) {
            $missing[] = self::OPERATOR_MISSING_AUDIT_LABELS['sample_rate'];
        }

        if (! $this->isConfigurationValueLearned($globals['clock_source'] ?? null, $identity['clock_source'] ?? null)) {
            $missing[] = self::OPERATOR_MISSING_AUDIT_LABELS['clock_source'];
        }

        $fx = $configuration['fx'];

        if (($fx['learned'] ?? false) !== true) {
            $missing[] = self::OPERATOR_MISSING_AUDIT_LABELS['fx_inventory'];
        }

        if ($this->hasUnlearnedDcaMembership($configuration['dcas'])) {
            $missing[] = self::OPERATOR_MISSING_AUDIT_LABELS['dca_membership'];
        }

        if ($this->hasUnlearnedMatrixSources($configuration['matrices'])) {
            $missing[] = self::OPERATOR_MISSING_AUDIT_LABELS['matrix_sources'];
        }

        return $missing;
    }

    /**
     * @param  array<int, array<string, mixed>>  $dcas
     */
    private function hasUnlearnedDcaMembership(array $dcas): bool
    {
        if ($dcas === []) {
            return true;
        }

        foreach ($dcas as $dca) {
            if (! is_array($dca)) {
                continue;
            }

            if (! $this->isFieldLearned($dca['membership'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrices
     */
    private function hasUnlearnedMatrixSources(array $matrices): bool
    {
        if ($matrices === []) {
            return true;
        }

        foreach ($matrices as $matrix) {
            if (! is_array($matrix)) {
                continue;
            }

            if (! $this->isFieldLearned($matrix['sources'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{value?: mixed, state?: string}|null  ...$candidates
     */
    private function isConfigurationValueLearned(?array ...$candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->isFieldLearned($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    private function formatSceneSegment(?array $configuration, array $summary): string
    {
        $sceneNumber = $this->resolveSceneNumber($configuration, $summary);

        if ($sceneNumber === null) {
            return 'Scene unknown';
        }

        $sceneName = $this->resolveSceneName($configuration, $summary);
        $segment = sprintf('Scene %s', $sceneNumber);

        if ($sceneName !== null) {
            $segment .= sprintf(' – %s', $sceneName);
        }

        return $segment;
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    private function resolveConsoleName(?array $configuration, array $summary): string
    {
        $fromConfiguration = $configuration !== null
            ? $this->fieldValue($configuration['identity']['console_name'] ?? null)
            : null;

        if ($fromConfiguration !== null) {
            return $fromConfiguration;
        }

        $fromSummary = trim((string) ($summary['device_name'] ?? ''));

        return $fromSummary !== '' ? $fromSummary : 'Console';
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    private function resolveSceneNumber(?array $configuration, array $summary): ?string
    {
        if ($configuration !== null) {
            $fromConfiguration = $this->fieldValue($configuration['identity']['scene_number'] ?? null);

            if ($fromConfiguration !== null) {
                return $fromConfiguration;
            }
        }

        foreach ([
            $summary['scene_number'] ?? null,
            $summary['requested_scene_number'] ?? null,
        ] as $candidate) {
            $formatted = trim((string) $candidate);

            if ($formatted !== '') {
                return $formatted;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    private function resolveSceneName(?array $configuration, array $summary): ?string
    {
        if ($configuration !== null) {
            $fromConfiguration = $this->fieldValue($configuration['identity']['scene_name'] ?? null);

            if ($fromConfiguration !== null) {
                return $fromConfiguration;
            }
        }

        $fromSummary = trim((string) ($summary['scene_name'] ?? ''));

        return $fromSummary !== '' ? $fromSummary : null;
    }

    /**
     * @param  array{value?: mixed, state?: string}|null  $field
     */
    private function isFieldLearned(?array $field): bool
    {
        return is_array($field) && ($field['state'] ?? '') === 'learned';
    }

    /**
     * @param  array{value?: mixed, state?: string}|null  $field
     */
    private function fieldValue(?array $field): ?string
    {
        if (! $this->isFieldLearned($field)) {
            return null;
        }

        $value = trim((string) ($field['value'] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array{value?: mixed, state?: string}|null  $field
     * @param  callable(mixed): string|null  $formatter
     * @return array{value: string, captured: bool}
     */
    private function fieldDisplay(?array $field, ?string $fallback = null, ?callable $formatter = null): array
    {
        if ($this->isFieldLearned($field)) {
            $rawValue = $field['value'] ?? null;
            $formatted = $formatter !== null ? $formatter($rawValue) : trim((string) $rawValue);

            if ($formatted !== '') {
                return [
                    'value' => $formatted,
                    'captured' => true,
                ];
            }
        }

        if ($fallback !== null && $fallback !== '') {
            return [
                'value' => $fallback,
                'captured' => false,
            ];
        }

        return [
            'value' => 'Not captured yet',
            'captured' => false,
        ];
    }

    /**
     * @return array{label: string, value: string, captured: bool, attention?: bool}
     */
    private function displayField(string $label, array|string $value, bool $attention = false): array
    {
        if (is_string($value)) {
            return [
                'label' => $label,
                'value' => $value,
                'captured' => $value !== 'Not captured yet' && $value !== 'Not available yet',
                'attention' => $attention,
            ];
        }

        return [
            'label' => $label,
            'value' => $value['value'],
            'captured' => $value['captured'],
            'attention' => $attention,
        ];
    }

    private function formatLearnSource(string $source): string
    {
        return match ($source) {
            'live_osc' => 'Live Console',
            'fake_fixture' => 'Preview data',
            default => 'Not available yet',
        };
    }

    private function formatLearnedTimestamp(mixed $learnedAt): ?string
    {
        if (! is_string($learnedAt) || trim($learnedAt) === '') {
            return null;
        }

        try {
            return Carbon::parse($learnedAt)->format('j M Y, H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatSampleRate(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            '48K' => '48 kHz',
            '44K1' => '44.1 kHz',
            default => $normalized,
        };
    }

    private function formatClockSource(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'INT' => 'Internal',
            'AES50A' => 'AES50 A',
            'AES50B' => 'AES50 B',
            'AES50' => 'AES50',
            'EXP' => 'Expansion card',
            default => $normalized,
        };
    }
}
