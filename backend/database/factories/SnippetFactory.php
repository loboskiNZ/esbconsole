<?php

namespace Database\Factories;

use App\Models\Chart;
use App\Models\Cue;
use App\Models\Snippet;
use App\Models\SongInstrumentPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Snippet>
 */
class SnippetFactory extends Factory
{
    protected $model = Snippet::class;

    public function definition(): array
    {
        return [
            'song_instrument_part_id' => SongInstrumentPart::factory(),
            'cue_id' => Cue::factory(),
            'source_type' => Snippet::SOURCE_CHART_CROP,
            'source_snippet_id' => null,
            'source_chart_id' => null,
            'freshness_state' => Snippet::FRESHNESS_CURRENT,
            'is_active' => true,
            'title' => 'Test Snippet '.fake()->word(),
            'storage_reference' => 'local-demo/snippets/'.fake()->uuid().'.png',
            'checksum' => fake()->sha256(),
            'annotation_storage_reference' => null,
            'markup_storage_reference' => null,
            'rendered_storage_reference' => null,
            'source_metadata' => null,
            'chart_revision_at_creation' => null,
            'notes' => null,
        ];
    }

    public function chartCrop(?Chart $chart = null, ?array $cropMetadata = null): static
    {
        return $this->state(function (array $attributes) use ($chart, $cropMetadata) {
            $resolvedChart = $chart;

            if ($resolvedChart === null && isset($attributes['source_chart_id'])) {
                $resolvedChart = Chart::query()->find($attributes['source_chart_id']);
            }

            return [
                'source_type' => Snippet::SOURCE_CHART_CROP,
                'source_chart_id' => $resolvedChart?->id,
                'chart_revision_at_creation' => $resolvedChart?->checksum,
                'source_metadata' => $cropMetadata ?? [
                    'page' => 1,
                    'x' => 10,
                    'y' => 20,
                    'width' => 400,
                    'height' => 300,
                ],
            ];
        });
    }

    public function clonedFrom(Snippet $source): static
    {
        return $this->state(fn () => [
            'source_type' => Snippet::SOURCE_CLONE,
            'source_snippet_id' => $source->id,
            'storage_reference' => 'local-demo/snippets/'.fake()->uuid().'.png',
            'checksum' => fake()->sha256(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function outOfDate(): static
    {
        return $this->state(fn () => ['freshness_state' => Snippet::FRESHNESS_OUT_OF_DATE]);
    }
}
