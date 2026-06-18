<?php

namespace App\Enums;

enum EffectPackageDeploymentPlanStatus: string
{
    case Ready = 'ready';
    case ReadyWithWarnings = 'ready_with_warnings';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'READY',
            self::ReadyWithWarnings => 'READY WITH WARNINGS',
            self::Blocked => 'BLOCKED',
        };
    }
}
