<?php

namespace App\Contracts\Runtime;

use App\Models\RuntimeDispatchItem;
use App\Services\Runtime\AdapterExecutionRequest;
use App\Services\Runtime\AdapterExecutionResult;

interface RuntimeAdapterInterface
{
    public function adapterKey(): string;

    public function supports(RuntimeDispatchItem $item): bool;

    public function execute(AdapterExecutionRequest $request): AdapterExecutionResult;
}
