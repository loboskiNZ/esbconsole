<?php

namespace App\Services\Console;

use App\Enums\ConsoleLearningStatus;
use App\Models\ConsoleLearningSnapshot;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;

/**
 * Resolves what the show console workspace should display: preview snapshot, saved baseline, or nothing.
 */
class ShowConsoleWorkspaceResolver
{
    public const MODE_PREVIEW = 'preview';

    public const MODE_SAVED = 'saved';

    public const MODE_EMPTY = 'empty';

    /**
     * @return array{
     *     mode: string,
     *     pendingSnapshot?: ConsoleLearningSnapshot,
     *     baseline?: ShowConsoleBaseline,
     *     summary: array<string, mixed>,
     *     sourceSnapshot?: ConsoleLearningSnapshot|null
     * }
     */
    public function resolve(Show $show): array
    {
        $pendingSnapshot = $this->pendingSnapshotForShow($show);

        if ($pendingSnapshot !== null) {
            return [
                'mode' => self::MODE_PREVIEW,
                'pendingSnapshot' => $pendingSnapshot,
                'baseline' => $this->activeBaselineForShow($show),
                'summary' => $pendingSnapshot->learned_summary_json ?? [],
                'sourceSnapshot' => $pendingSnapshot,
            ];
        }

        $baseline = $this->activeBaselineForShow($show);

        if ($baseline !== null) {
            return [
                'mode' => self::MODE_SAVED,
                'baseline' => $baseline,
                'summary' => $baseline->baseline_json ?? [],
                'sourceSnapshot' => $baseline->sourceSnapshot,
            ];
        }

        return [
            'mode' => self::MODE_EMPTY,
            'summary' => [],
            'sourceSnapshot' => null,
        ];
    }

    public function pendingSnapshotForShow(Show $show): ?ConsoleLearningSnapshot
    {
        return ConsoleLearningSnapshot::query()
            ->where('show_id', $show->id)
            ->where('learning_status', ConsoleLearningStatus::Review)
            ->with('integrationDevice.integrationConnectionProfiles')
            ->latest('learned_at')
            ->first();
    }

    public function activeBaselineForShow(Show $show): ?ShowConsoleBaseline
    {
        return ShowConsoleBaseline::query()
            ->where('show_id', $show->id)
            ->where('active', true)
            ->with('sourceSnapshot.integrationDevice')
            ->first();
    }
}
