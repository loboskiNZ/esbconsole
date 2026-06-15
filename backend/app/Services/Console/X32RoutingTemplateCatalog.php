<?php

namespace App\Services\Console;

/**
 * Future production configuration templates for the Routing workspace.
 *
 * These are operator presets — not learned console state.
 */
class X32RoutingTemplateCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function productionConfigurations(): array
    {
        return [
            $this->configuration(
                id: 'duo',
                name: 'Duo',
                description: 'Compact duo with local mics and stereo playback returns.',
            ),
            $this->configuration(
                id: 'lofi_setup',
                name: 'LoFi Setup',
                description: 'Lo-fi band layout with minimal stageboxes and playback channels.',
            ),
            $this->configuration(
                id: 'full_band_setup',
                name: 'Full Band Setup',
                description: 'Dual 16-channel stageboxes with full channel allocation.',
            ),
            $this->configuration(
                id: 'four_piece_rock',
                name: '4 Piece Rock Band',
                description: 'Rock four-piece with drums, bass, guitar, and vocals on stageboxes.',
            ),
            $this->configuration(
                id: 'custom',
                name: 'Custom',
                description: 'Build a custom production configuration for this show.',
            ),
        ];
    }

    /**
     * Common ESB suggested Ableton return mapping — not learned desk state.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestedAbletonReturns(): array
    {
        $rows = [];

        for ($index = 1; $index <= 8; $index++) {
            $channel = 24 + $index;

            $rows[] = [
                'return' => sprintf('Ableton Return %d', $index),
                'desk_channel' => sprintf('CH %02d', $channel),
                'card_usb' => sprintf('Card %02d', $index),
                'label' => sprintf('Return %d → CH %02d', $index, $channel),
            ];
        }

        return $rows;
    }

    /**
     * Suggested IEM mix labels for detail row display — not learned desk state.
     *
     * @return list<array<string, string>>
     */
    public function suggestedIemMixLabels(): array
    {
        return [
            ['name' => 'Ed IEM', 'bus' => 'Bus 1', 'output' => 'Not learned'],
            ['name' => 'Guitar IEM', 'bus' => 'Bus 2', 'output' => 'Not learned'],
            ['name' => 'Bass IEM', 'bus' => 'Bus 3', 'output' => 'Not learned'],
            ['name' => 'Keys IEM', 'bus' => 'Bus 4', 'output' => 'Not learned'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(string $id, string $name, string $description): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'kind' => 'future_configuration',
        ];
    }
}
