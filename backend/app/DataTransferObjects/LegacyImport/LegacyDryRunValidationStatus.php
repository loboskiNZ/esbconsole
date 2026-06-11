<?php

namespace App\DataTransferObjects\LegacyImport;

final class LegacyDryRunValidationStatus
{
    public const PASS = 'PASS';

    public const PASS_WITH_WARNINGS = 'PASS_WITH_WARNINGS';

    public const BLOCKED = 'BLOCKED';
}
