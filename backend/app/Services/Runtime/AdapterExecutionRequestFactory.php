<?php

namespace App\Services\Runtime;

use App\Models\RuntimeDispatchItem;

class AdapterExecutionRequestFactory
{
    public function fromDispatchItem(RuntimeDispatchItem $item): AdapterExecutionRequest
    {
        return new AdapterExecutionRequest(
            runtimeDispatchItemId: $item->id,
            adapterKey: $item->adapter_key,
            actionTypeCode: $item->action_type_code,
            payload: $item->payload ?? [],
            attemptNumber: $item->attempts + 1,
            occurredAt: now(),
        );
    }
}
