<?php

namespace App\Services\Integration;

use DateTimeInterface;

readonly class IntegrationValidationResult
{
    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_UNSUPPORTED = 'unsupported';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $message,
        public array $context,
        public DateTimeInterface $occurredAt,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public static function valid(?string $message = null, array $context = []): self
    {
        return new self(
            success: true,
            status: self::STATUS_VALID,
            message: $message,
            context: $context,
            occurredAt: now(),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function invalid(?string $message = null, array $context = []): self
    {
        return new self(
            success: false,
            status: self::STATUS_INVALID,
            message: $message,
            context: $context,
            occurredAt: now(),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function skipped(?string $message = null, array $context = []): self
    {
        return new self(
            success: false,
            status: self::STATUS_SKIPPED,
            message: $message,
            context: $context,
            occurredAt: now(),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function unsupported(?string $message = null, array $context = []): self
    {
        return new self(
            success: false,
            status: self::STATUS_UNSUPPORTED,
            message: $message,
            context: $context,
            occurredAt: now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'message' => $this->message,
            'context' => $this->context,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
