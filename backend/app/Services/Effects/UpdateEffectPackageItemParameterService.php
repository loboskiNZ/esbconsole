<?php

namespace App\Services\Effects;

use App\Models\EffectPackageItemParameter;
use Illuminate\Validation\ValidationException;

class UpdateEffectPackageItemParameterService
{
    public function update(EffectPackageItemParameter $parameter, ?string $value): EffectPackageItemParameter
    {
        $normalized = ($value === null || $value === '') ? null : trim($value);

        if ($normalized !== null) {
            $this->assertValidValue($parameter, $normalized);
        }

        $parameter->update(['value' => $normalized]);

        return $parameter->fresh();
    }

    private function assertValidValue(EffectPackageItemParameter $parameter, string $value): void
    {
        if ($parameter->value_type === 'enum') {
            $allowed = $parameter->enum_values_json ?? [];

            if (! in_array($value, $allowed, true)) {
                throw ValidationException::withMessages([
                    'value' => 'Selected value is not allowed for this parameter.',
                ]);
            }

            return;
        }

        if (in_array($parameter->value_type, ['linf', 'logf'], true) && ! is_numeric($value)) {
            throw ValidationException::withMessages([
                'value' => 'Parameter value must be numeric.',
            ]);
        }

        if (is_numeric($value) && $parameter->min_value !== null && $parameter->max_value !== null) {
            $numeric = (float) $value;
            $min = (float) $parameter->min_value;
            $max = (float) $parameter->max_value;

            if ($numeric < $min || $numeric > $max) {
                throw ValidationException::withMessages([
                    'value' => sprintf('Value must be between %s and %s.', $parameter->min_value, $parameter->max_value),
                ]);
            }
        }
    }
}
