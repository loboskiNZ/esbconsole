<?php

namespace App\Services\X32;

use App\Models\EffectPackageItemParameter;
use Illuminate\Validation\ValidationException;

class X32EffectParameterOscEncoder
{
    private const LOGF_STEPS = 201;

    /** @var list<int> */
    private const LOGF_STEP_CANDIDATES = [201, 101, 72, 51, 41, 100, 200, 256];

    public function encode(EffectPackageItemParameter $parameter): float
    {
        $value = $parameter->value;

        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                'value' => sprintf('Parameter %d has no value to deploy.', $parameter->parameter_number),
            ]);
        }

        return match ($parameter->value_type) {
            'linf' => $this->encodeLinf($parameter, $value),
            'logf' => $this->encodeLogf($parameter, $value),
            'enum' => $this->encodeEnum($parameter, $value),
            default => throw ValidationException::withMessages([
                'value' => sprintf('Unsupported parameter type "%s".', $parameter->value_type),
            ]),
        };
    }

    public function encodeInt(EffectPackageItemParameter $parameter): int
    {
        return (int) round($this->encode($parameter));
    }

    public function decode(EffectPackageItemParameter $parameter, float $normalized): float
    {
        $min = (float) ($parameter->min_value ?? 0.0);
        $max = (float) ($parameter->max_value ?? 1.0);

        return match ($parameter->value_type) {
            'linf' => X32OscParameterScale::decodeLinf($normalized, $min, $max, 0.0),
            'logf' => X32OscParameterScale::decodeLogf($normalized, $min, $max, self::LOGF_STEPS),
            'enum' => $normalized,
            default => $normalized,
        };
    }

    public function writeConfirmed(EffectPackageItemParameter $parameter, float $encoded, float $confirmed): bool
    {
        if (abs($encoded - $confirmed) < 0.0001) {
            return true;
        }

        return match ($parameter->value_type) {
            'linf' => $this->linfWriteConfirmed($parameter, $confirmed),
            'logf' => $this->logfWriteConfirmed($parameter, $encoded, $confirmed),
            'enum' => abs(round($encoded) - round($confirmed)) < 0.5,
            default => false,
        };
    }

    public function valuesMatch(float $requested, float $confirmed): bool
    {
        return abs($requested - $confirmed) < 0.0001;
    }

    private function encodeLinf(EffectPackageItemParameter $parameter, string $value): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'value' => 'Parameter value must be numeric.',
            ]);
        }

        $min = (float) ($parameter->min_value ?? 0.0);
        $max = (float) ($parameter->max_value ?? 1.0);

        return X32OscParameterScale::encodeLinf((float) $value, $min, $max, 0.0);
    }

    private function encodeLogf(EffectPackageItemParameter $parameter, string $value): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'value' => 'Parameter value must be numeric.',
            ]);
        }

        $min = (float) ($parameter->min_value ?? 0.0);
        $max = (float) ($parameter->max_value ?? 1.0);

        return X32OscParameterScale::encodeLogf((float) $value, $min, $max, self::LOGF_STEPS);
    }

    private function encodeEnum(EffectPackageItemParameter $parameter, string $value): float
    {
        $options = $parameter->enum_values_json ?? [];
        $index = array_search($value, $options, true);

        if ($index === false) {
            throw ValidationException::withMessages([
                'value' => 'Selected value is not allowed for this parameter.',
            ]);
        }

        return (float) $index;
    }

    private function linfWriteConfirmed(EffectPackageItemParameter $parameter, float $confirmed): bool
    {
        $expected = (float) $parameter->value;
        $decoded = $this->decode($parameter, $confirmed);
        $range = max(0.001, (float) $parameter->max_value - (float) $parameter->min_value);
        $tolerance = max($range * 0.01, 0.05);

        return abs($expected - $decoded) <= $tolerance;
    }

    private function logfWriteConfirmed(EffectPackageItemParameter $parameter, float $encoded, float $confirmed): bool
    {
        if (abs($encoded - $confirmed) < 0.005) {
            return true;
        }

        $expected = (float) $parameter->value;
        $min = (float) ($parameter->min_value ?? 0.0);
        $max = (float) ($parameter->max_value ?? 1.0);
        $range = max(0.001, $max - $min);
        $tolerance = max($range * 0.03, $this->logfMinimumTolerance($parameter));

        foreach (self::LOGF_STEP_CANDIDATES as $steps) {
            $decoded = X32OscParameterScale::decodeLogf($confirmed, $min, $max, $steps);

            if (abs($expected - $decoded) <= $tolerance) {
                return true;
            }
        }

        return false;
    }

    private function logfMinimumTolerance(EffectPackageItemParameter $parameter): float
    {
        $max = (float) ($parameter->max_value ?? 1.0);

        if ($max <= 2.0) {
            return 0.02;
        }

        if ($max <= 20.0) {
            return 0.1;
        }

        return 1.0;
    }
}
