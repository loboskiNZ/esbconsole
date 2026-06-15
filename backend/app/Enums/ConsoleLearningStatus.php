<?php

namespace App\Enums;

enum ConsoleLearningStatus: string
{
    case Learning = 'learning';
    case Review = 'review';
    case Saved = 'saved';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Learning => 'Learning',
            self::Review => 'Review',
            self::Saved => 'Saved',
            self::Failed => 'Failed',
        };
    }
}
