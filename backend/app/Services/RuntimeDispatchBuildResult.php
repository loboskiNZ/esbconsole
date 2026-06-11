<?php

namespace App\Services;

use App\Models\RuntimeDispatch;

readonly class RuntimeDispatchBuildResult
{
    public function __construct(
        public RuntimeDispatch $runtimeDispatch,
        public bool $created,
    ) {}

    public function toArray(): array
    {
        return [
            'runtime_dispatch_id' => $this->runtimeDispatch->id,
            'runtime_dispatch_status' => $this->runtimeDispatch->status,
            'created' => $this->created,
            'dispatch_item_count' => $this->runtimeDispatch->runtimeDispatchItems->count(),
        ];
    }
}
