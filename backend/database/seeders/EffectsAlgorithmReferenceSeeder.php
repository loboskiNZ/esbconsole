<?php

namespace Database\Seeders;

use App\Enums\EffectImplementationType;
use App\Enums\X32SlotGroup;
use App\Models\X32Effect;
use App\Models\X32EffectParameter;
use Database\Seeders\Support\X32EffectsAlgorithmCatalogue;
use Database\Seeders\Support\X32EffectsOperatorCatalogue;
use Illuminate\Database\Seeder;

/**
 * PH044 X32 algorithm reference — effects and verified parameters only.
 * Does not seed packages, song assignments, or demo data.
 */
class EffectsAlgorithmReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAlgorithms();
        $this->seedVerifiedParameters();
    }

    private function seedAlgorithms(): void
    {
        foreach (X32EffectsAlgorithmCatalogue::fx1To4Algorithms() as [$algorithmId, $code, $name, $category]) {
            $this->upsertEffect($code, $name, $algorithmId, X32SlotGroup::Fx1To4, $category);
        }

        foreach (X32EffectsAlgorithmCatalogue::fx5To8Algorithms() as [$algorithmId, $code, $name, $category]) {
            $this->upsertEffect($code, $name, $algorithmId, X32SlotGroup::Fx5To8, $category);
        }
    }

    private function upsertEffect(
        string $code,
        string $name,
        int $algorithmId,
        X32SlotGroup $slotGroup,
        string $category,
    ): void {
        $operator = X32EffectsOperatorCatalogue::forCode($code);

        X32Effect::query()->updateOrCreate(
            [
                'effect_code' => $code,
                'x32_slot_group' => $slotGroup,
            ],
            [
                'effect_name' => $name,
                'x32_algorithm_id' => $algorithmId,
                'category' => $category,
                'implementation_type' => EffectImplementationType::FxSlot,
                'description' => sprintf(
                    'Maillot-verified %s algorithm for %s slots.',
                    $code,
                    $slotGroup->label(),
                ),
                'operator_name' => $operator['operator_name'],
                'operator_description' => $operator['operator_description'],
                'recommended_for_json' => $operator['recommended_for_json'],
                'operator_category' => $operator['operator_category'],
                'difficulty' => $operator['difficulty'],
                'starter_notes' => $operator['starter_notes'],
                'is_active' => true,
            ],
        );
    }

    private function seedVerifiedParameters(): void
    {
        $this->seedParametersForCode('HALL', X32SlotGroup::Fx1To4, $this->hallParameters());
        $this->seedParametersForCode('PLAT', X32SlotGroup::Fx1To4, $this->plateParameters());
        $this->seedParametersForCode('AMBI', X32SlotGroup::Fx1To4, $this->ambiParameters());
        $this->seedParametersForCode('ROOM', X32SlotGroup::Fx1To4, $this->roomParameters());
        $this->seedParametersForCode('4TAP', X32SlotGroup::Fx1To4, $this->fourTapParameters());
        $this->seedParametersForCode('DIMC', X32SlotGroup::Fx1To4, $this->dimcParameters());
        $this->seedParametersForCode('FILT', X32SlotGroup::Fx1To4, $this->filtParameters());
        $this->seedParametersForCode('FILT', X32SlotGroup::Fx5To8, $this->filtParameters());
        $this->seedParametersForCode('DLY', X32SlotGroup::Fx1To4, $this->delayParameters());
        $this->seedParametersForCode('MODD', X32SlotGroup::Fx1To4, $this->moddParameters());
        $this->seedParametersForCode('GEQ', X32SlotGroup::Fx1To4, $this->geqParameters());
        $this->seedParametersForCode('GEQ', X32SlotGroup::Fx5To8, $this->geqParameters());
        $this->seedParametersForCode('LIM', X32SlotGroup::Fx1To4, $this->limiterParameters());
        $this->seedParametersForCode('LIM', X32SlotGroup::Fx5To8, $this->limiterParameters());
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     */
    private function seedParametersForCode(string $code, X32SlotGroup $slotGroup, array $parameters): void
    {
        $effect = X32Effect::query()
            ->where('effect_code', $code)
            ->where('x32_slot_group', $slotGroup)
            ->first();

        if ($effect === null) {
            return;
        }

        foreach ($parameters as $parameter) {
            X32EffectParameter::query()->updateOrCreate(
                [
                    'effect_id' => $effect->id,
                    'parameter_number' => $parameter['parameter_number'],
                ],
                array_merge($parameter, ['is_active' => true]),
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hallParameters(): array
    {
        return [
            $this->linf(1, 'Pre Delay', '100', '0', '200'),
            $this->logf(2, 'Decay', '2', '0.2', '5'),
            $this->linf(3, 'Size', '50', '2', '100'),
            $this->logf(4, 'Damping', '5000', '1000', '20000'),
            $this->linf(5, 'Diffuse', '15', '1', '30'),
            $this->linf(6, 'Level', '0', '-12', '12'),
            $this->logf(7, 'Lo Cut', '80', '10', '500'),
            $this->logf(8, 'Hi Cut', '8000', '200', '20000'),
            $this->logf(9, 'Bass Multi', '1', '0.5', '2'),
            $this->linf(10, 'Spread', '25', '0', '50'),
            $this->linf(11, 'Shape', '125', '0', '250'),
            $this->linf(12, 'Mod Speed', '50', '0', '100'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plateParameters(): array
    {
        return [
            $this->linf(1, 'Pre Delay', '100', '0', '200'),
            $this->logf(2, 'Decay', '2', '0.5', '10'),
            $this->linf(3, 'Size', '50', '2', '100'),
            $this->logf(4, 'Damping', '5000', '1000', '20000'),
            $this->linf(5, 'Diffuse', '15', '1', '30'),
            $this->linf(6, 'Level', '0', '-12', '12'),
            $this->logf(7, 'Lo Cut', '80', '10', '500'),
            $this->logf(8, 'Hi Cut', '8000', '200', '20000'),
            $this->logf(9, 'Bass Multi', '1', '0.5', '2'),
            $this->logf(10, 'Xover', '200', '10', '500'),
            $this->linf(11, 'Mod Depth', '25', '1', '50'),
            $this->linf(12, 'Mod Speed', '50', '0', '100'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ambiParameters(): array
    {
        return [
            $this->linf(1, 'Pre Delay', '100', '0', '200'),
            $this->logf(2, 'Decay', '2', '0.2', '7.3'),
            $this->linf(3, 'Size', '50', '2', '100'),
            $this->logf(4, 'Damping', '5000', '1000', '20000'),
            $this->linf(5, 'Diffuse', '15', '1', '30'),
            $this->linf(6, 'Level', '0', '-12', '12'),
            $this->logf(7, 'Lo Cut', '80', '10', '500'),
            $this->logf(8, 'Hi Cut', '8000', '200', '20000'),
            $this->linf(9, 'Modulate', '50', '0', '100'),
            $this->linf(10, 'Tail Gain', '50', '0', '100'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function roomParameters(): array
    {
        return [
            $this->linf(1, 'Pre Delay', '100', '0', '200'),
            $this->logf(2, 'Decay', '2', '0.3', '29'),
            $this->linf(3, 'Size', '40', '4', '76'),
            $this->logf(4, 'Damping', '5000', '1000', '20000'),
            $this->linf(5, 'Diffuse', '50', '0', '100'),
            $this->linf(6, 'Level', '0', '-12', '12'),
            $this->logf(7, 'Lo Cut', '80', '10', '500'),
            $this->logf(8, 'Hi Cut', '8000', '200', '20000'),
            $this->logf(9, 'Bass Multi', '1', '0.25', '4'),
            $this->linf(10, 'Spread', '25', '0', '50'),
            $this->linf(11, 'Shape', '125', '0', '250'),
            $this->linf(12, 'Spin', '50', '0', '100'),
            $this->linf(13, 'Echo L', '600', '0', '1200'),
            $this->linf(14, 'Echo R', '600', '0', '1200'),
            $this->linf(15, 'Echo Feed L', '0', '-100', '100'),
            $this->linf(16, 'Echo Feed R', '0', '-100', '100'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fourTapParameters(): array
    {
        return [
            $this->linf(1, 'Time', '500', '1', '3000', 'ms'),
            $this->linf(2, 'Gain Base', '50', '0', '100'),
            $this->linf(3, 'Feedback', '50', '0', '100'),
            $this->logf(4, 'Lo Cut', '80', '10', '500'),
            $this->logf(5, 'Hi Cut', '8000', '200', '20000'),
            $this->linf(6, 'Spread', '3', '0', '6'),
            $this->enum(7, 'Factor A', '1', ['1/4', '3/8', '1/2', '2/3', '1', '4/3', '3/2', '2', '3']),
            $this->linf(8, 'Gain A', '50', '0', '100'),
            $this->enum(9, 'Factor B', '1', ['1/4', '3/8', '1/2', '2/3', '1', '4/3', '3/2', '2', '3']),
            $this->linf(10, 'Gain B', '50', '0', '100'),
            $this->enum(11, 'Factor C', '1', ['1/4', '3/8', '1/2', '2/3', '1', '4/3', '3/2', '2', '3']),
            $this->linf(12, 'Gain C', '50', '0', '100'),
            $this->enum(13, 'Cross Feed', 'OFF', ['OFF', 'ON']),
            $this->enum(14, 'Mono', 'OFF', ['OFF', 'ON']),
            $this->enum(15, 'Dry', 'OFF', ['OFF', 'ON']),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dimcParameters(): array
    {
        return [
            $this->enum(1, 'Active', 'ON', ['OFF', 'ON']),
            $this->enum(2, 'Mode', 'ST', ['M', 'ST']),
            $this->enum(3, 'Dry', 'OFF', ['OFF', 'ON']),
            $this->enum(4, 'Mode 1', 'ON', ['OFF', 'ON']),
            $this->enum(5, 'Mode 2', 'OFF', ['OFF', 'ON']),
            $this->enum(6, 'Mode 3', 'OFF', ['OFF', 'ON']),
            $this->enum(7, 'Mode 4', 'OFF', ['OFF', 'ON']),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filtParameters(): array
    {
        return [
            $this->logf(1, 'Speed', '1', '0.05', '20'),
            $this->linf(2, 'Depth', '50', '0', '100'),
            $this->linf(3, 'Resonance', '50', '0', '100'),
            $this->logf(4, 'Base', '1000', '20', '15000'),
            $this->enum(5, 'Mode', 'LP', ['LP', 'HP', 'BP', 'NO']),
            $this->linf(6, 'Mix', '50', '0', '100'),
            $this->enum(7, 'Wave', 'SIN', ['TRI', 'SIN', 'SAW', 'SAW-', 'RMP', 'SQU', 'RND']),
            $this->linf(8, 'Phase', '90', '0', '180'),
            $this->linf(9, 'Env. Modulation', '0', '-100', '100'),
            $this->logf(10, 'Attack', '50', '10', '250'),
            $this->logf(11, 'Release', '100', '10', '500'),
            $this->linf(12, 'Drive', '50', '0', '100'),
            $this->enum(13, '4 Pole', '2POL', ['2POL', '4POL']),
            $this->enum(14, 'Side Chain', 'OFF', ['OFF', 'ON']),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function delayParameters(): array
    {
        return [
            $this->linf(1, 'Mix', '25', '0', '100'),
            $this->linf(2, 'Time', '500', '1', '3000'),
            $this->enum(3, 'Mode', 'ST', ['ST', 'X', 'M']),
            $this->enum(4, 'Factor L', '1/4', ['1/4', '3/8', '1/2', '2/3', '1', '4/3', '3/2', '2', '3']),
            $this->enum(5, 'Factor R', '1/4', ['1/4', '3/8', '1/2', '2/3', '1', '4/3', '3/2', '2', '3']),
            $this->linf(6, 'Offset L/R', '0', '-100', '100'),
            $this->logf(7, 'Lo Cut', '80', '10', '500'),
            $this->logf(8, 'Hi Cut', '8000', '200', '20000'),
            $this->logf(9, 'Feed Lo Cut', '80', '10', '500'),
            $this->linf(10, 'Feed Left', '50', '1', '100'),
            $this->linf(11, 'Feed Right', '50', '1', '100'),
            $this->logf(12, 'Feed Hi Cut', '8000', '200', '20000'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function moddParameters(): array
    {
        return [
            $this->linf(1, 'Time', '500', '1', '3000'),
            $this->enum(2, 'Delay', '1', ['1', '1/2', '2/3', '3/2']),
            $this->linf(3, 'Feed', '50', '0', '100'),
            $this->logf(4, 'Lo Cut', '80', '10', '500'),
            $this->logf(5, 'Hi Cut', '8000', '200', '20000'),
            $this->linf(6, 'Depth', '50', '0', '100'),
            $this->logf(7, 'Rate', '1', '0.05', '10'),
            $this->enum(8, 'Setup', 'PAR', ['PAR', 'SER']),
            $this->enum(9, 'Type', 'AMB', ['AMB', 'CLUB', 'HALL']),
            $this->linf(10, 'Decay', '5', '1', '10'),
            $this->logf(11, 'Damping', '5000', '1000', '20000'),
            $this->linf(12, 'Balance', '0', '-100', '100'),
            $this->linf(13, 'Mix', '50', '0', '100'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function geqParameters(): array
    {
        $rows = [];

        for ($band = 1; $band <= 31; $band++) {
            $rows[] = $this->linf($band, "Eq Level L/R {$band}", '0', '-15', '15');
        }

        $rows[] = $this->linf(32, 'Master Level L/R', '0', '-15', '15');

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function limiterParameters(): array
    {
        return [
            $this->linf(1, 'Input Gain', '6', '0', '18'),
            $this->linf(2, 'Out Gain', '-6', '-18', '0'),
            $this->linf(3, 'Squeeze', '50', '0', '100'),
            $this->linf(4, 'Knee', '5', '0', '10'),
            $this->logf(5, 'Attack', '0.1', '0.05', '1'),
            $this->logf(6, 'Release', '200', '20', '2000'),
            $this->enum(7, 'Stereo Link', 'ON', ['OFF', 'ON']),
            $this->enum(8, 'Auto Gain', 'OFF', ['OFF', 'ON']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linf(int $number, string $name, string $default, string $min, string $max, ?string $unit = null): array
    {
        return [
            'parameter_number' => $number,
            'parameter_name' => $name,
            'value_type' => 'linf',
            'default_value' => $default,
            'min_value' => $min,
            'max_value' => $max,
            'unit' => $unit,
            'enum_values_json' => null,
            'scaling_notes' => 'Maillot linf range verified in PH044 catalogue.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logf(int $number, string $name, string $default, string $min, string $max): array
    {
        return [
            'parameter_number' => $number,
            'parameter_name' => $name,
            'value_type' => 'logf',
            'default_value' => $default,
            'min_value' => $min,
            'max_value' => $max,
            'unit' => null,
            'enum_values_json' => null,
            'scaling_notes' => 'Maillot logf range verified in PH044 catalogue.',
        ];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, mixed>
     */
    private function enum(int $number, string $name, string $default, array $values): array
    {
        return [
            'parameter_number' => $number,
            'parameter_name' => $name,
            'value_type' => 'enum',
            'default_value' => $default,
            'min_value' => null,
            'max_value' => null,
            'unit' => null,
            'enum_values_json' => $values,
            'scaling_notes' => 'Maillot enum values verified in PH044 catalogue.',
        ];
    }
}
