<?php

namespace App\Console\Commands;

use App\Services\X32\X32LiveValidationResult;
use App\Services\X32\X32LiveValidationService;
use Illuminate\Console\Command;

class ValidateX32LiveCommand extends Command
{
    protected $signature = 'x32:validate-live
                            {band_id : Band ID for the configured X32 device}
                            {scene : Scene number to recall (X32_SCENE only)}
                            {--device= : Optional X32 device_key}
                            {--confirm-live : Required flag to allow live UDP validation}
                            {--operator= : Optional operator label}
                            {--notes= : Optional validation notes}';

    protected $description = 'Run an operator-controlled live X32 scene recall validation (X32_SCENE only)';

    public function __construct(
        private readonly X32LiveValidationService $validationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->validationService->validate(
            bandId: (int) $this->argument('band_id'),
            scene: (string) $this->argument('scene'),
            confirmLive: (bool) $this->option('confirm-live'),
            deviceKey: $this->option('device') ?: null,
            operatorLabel: $this->option('operator') ?: null,
            notes: $this->option('notes') ?: null,
        );

        $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT));

        return match ($result->status) {
            X32LiveValidationResult::STATUS_ACKNOWLEDGED => self::SUCCESS,
            X32LiveValidationResult::STATUS_BLOCKED, X32LiveValidationResult::STATUS_SKIPPED => self::INVALID,
            default => self::FAILURE,
        };
    }
}
