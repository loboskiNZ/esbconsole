<?php

namespace App\Services\Runtime;

readonly class RuntimeExecutionSummary
{
    /**
     * @param  list<array{
     *     runtime_dispatch_item_id: int,
     *     adapter_key: string,
     *     item_status: string,
     *     result_status: ?string
     * }>  $results
     */
    public function __construct(
        public int $runtimeDispatchId,
        public string $status,
        public int $itemCount,
        public int $acknowledgedCount,
        public int $failedCount,
        public int $skippedCount,
        public array $results,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'runtime_dispatch_id' => $this->runtimeDispatchId,
            'status' => $this->status,
            'item_count' => $this->itemCount,
            'acknowledged_count' => $this->acknowledgedCount,
            'failed_count' => $this->failedCount,
            'skipped_count' => $this->skippedCount,
            'results' => $this->results,
        ];
    }
}
