<?php

namespace App\Services\Runtime;

use DateTimeInterface;

readonly class AdapterExecutionRequest
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $runtimeDispatchItemId,
        public string $adapterKey,
        public string $actionTypeCode,
        public array $payload,
        public int $attemptNumber,
        public DateTimeInterface $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'runtime_dispatch_item_id' => $this->runtimeDispatchItemId,
            'adapter_key' => $this->adapterKey,
            'action_type_code' => $this->actionTypeCode,
            'payload' => $this->payload,
            'attempt_number' => $this->attemptNumber,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
