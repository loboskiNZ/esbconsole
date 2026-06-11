<?php

namespace App\Services\Runtime;

use DateTimeInterface;

readonly class AdapterExecutionResult
{
    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_UNSUPPORTED = 'unsupported';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $adapterKey,
        public bool $success,
        public string $status,
        public ?string $message,
        public array $context,
        public DateTimeInterface $occurredAt,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public static function acknowledged(
        string $adapterKey,
        ?string $message = null,
        array $context = [],
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            adapterKey: $adapterKey,
            success: true,
            status: self::STATUS_ACKNOWLEDGED,
            message: $message,
            context: $context,
            occurredAt: $occurredAt ?? now(),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function failed(
        string $adapterKey,
        ?string $message = null,
        array $context = [],
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            adapterKey: $adapterKey,
            success: false,
            status: self::STATUS_FAILED,
            message: $message,
            context: $context,
            occurredAt: $occurredAt ?? now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'adapter_key' => $this->adapterKey,
            'success' => $this->success,
            'status' => $this->status,
            'message' => $this->message,
            'context' => $this->context,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
