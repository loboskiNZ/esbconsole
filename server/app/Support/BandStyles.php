<?php

namespace App\Support;

class BandStyles
{
    /**
     * @return list<string>|null
     */
    public static function normalize(mixed $input): ?array
    {
        if ($input === null) {
            return null;
        }

        if (is_array($input)) {
            $styles = collect($input)
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->map(fn (string $value) => trim($value))
                ->unique()
                ->values()
                ->all();

            return $styles === [] ? null : $styles;
        }

        if (! is_string($input)) {
            return null;
        }

        $segments = preg_split('/[\r\n,]+/', $input) ?: [];
        $styles = [];

        foreach ($segments as $segment) {
            $trimmed = trim($segment);

            if ($trimmed !== '') {
                $styles[] = $trimmed;
            }
        }

        $styles = array_values(array_unique($styles));

        return $styles === [] ? null : $styles;
    }

    /**
     * @param  list<string>|null  $styles
     */
    public static function toInputValue(?array $styles): string
    {
        if ($styles === null || $styles === []) {
            return '';
        }

        return implode("\n", $styles);
    }
}
