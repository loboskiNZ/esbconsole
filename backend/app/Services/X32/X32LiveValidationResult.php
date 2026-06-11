<?php

namespace App\Services\X32;

use DateTimeInterface;

readonly class X32LiveValidationResult
{
    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $message,
        public int $bandId,
        public ?string $deviceKey,
        public ?string $scene,
        public string $mode,
        public array $context,
        public DateTimeInterface $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'message' => $this->message,
            'band_id' => $this->bandId,
            'device_key' => $this->deviceKey,
            'scene' => $this->scene,
            'mode' => $this->mode,
            'context' => $this->context,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
