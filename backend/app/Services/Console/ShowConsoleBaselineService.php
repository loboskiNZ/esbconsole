<?php

namespace App\Services\Console;

use App\Enums\ConsoleLearningStatus;
use App\Enums\ConsoleType;
use App\Models\ConsoleLearningSnapshot;
use App\Models\ShowConsoleBaseline;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShowConsoleBaselineService
{
    public function saveFromSnapshot(ConsoleLearningSnapshot $snapshot, ?string $baselineName = null): ShowConsoleBaseline
    {
        if ($snapshot->learning_status !== ConsoleLearningStatus::Review) {
            throw ValidationException::withMessages([
                'snapshot' => 'Only review-state learning snapshots can be saved as a baseline.',
            ]);
        }

        $summary = $snapshot->learned_summary_json ?? [];
        $consoleTypeValue = (string) ($summary['console_type'] ?? ConsoleType::X32->value);
        $consoleType = ConsoleType::tryFrom($consoleTypeValue) ?? ConsoleType::X32;

        $name = $baselineName ?: sprintf(
            'Scene %s — %s',
            $summary['scene_number'] ?? $snapshot->requested_scene_number,
            $summary['device_name'] ?? 'Console',
        );

        return DB::transaction(function () use ($snapshot, $summary, $consoleType, $name) {
            ShowConsoleBaseline::query()
                ->where('show_id', $snapshot->show_id)
                ->where('active', true)
                ->update(['active' => false]);

            $baseline = ShowConsoleBaseline::create([
                'band_id' => $snapshot->band_id,
                'show_id' => $snapshot->show_id,
                'source_snapshot_id' => $snapshot->id,
                'baseline_name' => $name,
                'console_type' => $consoleType,
                'baseline_json' => $summary,
                'active' => true,
                'saved_at' => now(),
            ]);

            $snapshot->update([
                'learning_status' => ConsoleLearningStatus::Saved,
                'saved_at' => now(),
            ]);

            return $baseline->fresh(['show', 'sourceSnapshot.integrationDevice']);
        });
    }

    /**
     * @param  array{layer: string, index: int, parameter: string}  $parsed
     */
    public function applyOscValue(
        ShowConsoleBaseline $baseline,
        array $parsed,
        string $parameter,
        float|bool $value,
    ): ShowConsoleBaseline {
        $summary = $baseline->baseline_json ?? [];
        $layerKey = $parsed['layer'];
        $items = $summary[$layerKey] ?? [];

        foreach ($items as $position => $item) {
            if ((int) ($item['index'] ?? 0) !== $parsed['index']) {
                continue;
            }

            if ($parameter === 'fader') {
                $items[$position]['fader'] = round((float) $value, 4);
            } else {
                $items[$position]['mute'] = (bool) $value;
            }

            break;
        }

        $summary[$layerKey] = $items;
        $baseline->update(['baseline_json' => $summary]);

        return $baseline->fresh();
    }

    /**
     * @param  array{layer: string, index: int, parameter: string}  $parsed
     */
    public function applyOscValueToSnapshot(
        ConsoleLearningSnapshot $snapshot,
        array $parsed,
        string $parameter,
        float|bool $value,
    ): ConsoleLearningSnapshot {
        if ($snapshot->learning_status !== ConsoleLearningStatus::Review) {
            throw ValidationException::withMessages([
                'snapshot' => 'Only review-state snapshots can be edited in the console workspace.',
            ]);
        }

        $summary = $snapshot->learned_summary_json ?? [];
        $layerKey = $parsed['layer'];
        $items = $summary[$layerKey] ?? [];

        foreach ($items as $position => $item) {
            if ((int) ($item['index'] ?? 0) !== $parsed['index']) {
                continue;
            }

            if ($parameter === 'fader') {
                $items[$position]['fader'] = round((float) $value, 4);
            } else {
                $items[$position]['mute'] = (bool) $value;
            }

            break;
        }

        $summary[$layerKey] = $items;
        $snapshot->update(['learned_summary_json' => $summary]);

        return $snapshot->fresh();
    }

    public function applyChannelControl(
        ShowConsoleBaseline $baseline,
        int $channelNumber,
        string $controlKey,
        float|bool $value,
    ): ShowConsoleBaseline {
        $summary = $baseline->baseline_json ?? [];
        $summary['channels'] = $this->applyChannelControlToItems(
            $summary['channels'] ?? [],
            $channelNumber,
            $controlKey,
            $value,
        );
        $baseline->update(['baseline_json' => $summary]);

        return $baseline->fresh();
    }

    public function applyChannelControlToSnapshot(
        ConsoleLearningSnapshot $snapshot,
        int $channelNumber,
        string $controlKey,
        float|bool $value,
    ): ConsoleLearningSnapshot {
        if ($snapshot->learning_status !== ConsoleLearningStatus::Review) {
            throw ValidationException::withMessages([
                'snapshot' => 'Only review-state snapshots can be edited in the console workspace.',
            ]);
        }

        $summary = $snapshot->learned_summary_json ?? [];
        $summary['channels'] = $this->applyChannelControlToItems(
            $summary['channels'] ?? [],
            $channelNumber,
            $controlKey,
            $value,
        );
        $snapshot->update(['learned_summary_json' => $summary]);

        return $snapshot->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function applyChannelControlToItems(
        array $items,
        int $channelNumber,
        string $controlKey,
        float|bool $value,
    ): array {
        $found = false;

        foreach ($items as $position => $item) {
            if ((int) ($item['index'] ?? 0) !== $channelNumber) {
                continue;
            }

            $items[$position] = $this->mergeControlValue($item, $controlKey, $value);
            $found = true;

            break;
        }

        if (! $found) {
            $items[] = $this->mergeControlValue(['index' => $channelNumber], $controlKey, $value);
        }

        usort($items, static fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mergeControlValue(array $item, string $controlKey, float|bool $value): array
    {
        return match ($controlKey) {
            'fader' => array_merge($item, ['fader' => round((float) $value, 4)]),
            'mute' => array_merge($item, ['mute' => (bool) $value]),
            default => $this->mergeNestedControl($item, $controlKey, $value),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mergeNestedControl(array $item, string $controlKey, float|bool $value): array
    {
        $storageKey = match ($controlKey) {
            'gate_on' => 'gate_on',
            'compressor_on' => 'compressor_on',
            'eq_on' => 'eq_on',
            'main_lr' => 'main_lr',
            'stereo_link' => 'stereo_link',
            'sends' => 'sends_open',
            'gain' => 'gain',
            'phantom48v' => 'phantom48v',
            'pan' => 'pan',
            'meter' => 'meter',
            default => $controlKey,
        };

        $controls = is_array($item['controls'] ?? null) ? $item['controls'] : [];
        $controls[$storageKey] = $value;
        $item['controls'] = $controls;

        return $item;
    }
}
