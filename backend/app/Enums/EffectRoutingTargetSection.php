<?php

namespace App\Enums;

enum EffectRoutingTargetSection: string
{
    case NotConfigured = 'not_configured';
    case Vocals = 'vocals';
    case BackingVocals = 'backing_vocals';
    case Horns = 'horns';
    case Drums = 'drums';
    case Guitar = 'guitar';
    case Bass = 'bass';
    case Keys = 'keys';
    case Foh = 'foh';
    case SpecialFx = 'special_fx';

    public function label(): string
    {
        return match ($this) {
            self::NotConfigured => 'Not configured',
            self::Vocals => 'Vocals',
            self::BackingVocals => 'Backing Vocals',
            self::Horns => 'Horns',
            self::Drums => 'Drums',
            self::Guitar => 'Guitar',
            self::Bass => 'Bass',
            self::Keys => 'Keys',
            self::Foh => 'FOH',
            self::SpecialFx => 'Special FX',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function selectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => $case !== self::NotConfigured,
        ));
    }

    public function orderIndex(): int
    {
        static $indices = null;

        if ($indices === null) {
            $indices = array_flip(array_map(
                fn (self $case) => $case->value,
                self::cases(),
            ));
        }

        return $indices[$this->value] ?? PHP_INT_MAX;
    }

    /**
     * @return list<string>
     */
    public static function selectableValues(): array
    {
        return array_map(
            fn (self $case) => $case->value,
            self::selectableCases(),
        );
    }
}
