<?php

namespace App\Services\Runtime;

use App\Contracts\Runtime\RuntimeAdapterInterface;
use App\Exceptions\Runtime\AdapterNotFoundException;

class AdapterRegistry
{
    /** @var array<string, RuntimeAdapterInterface> */
    private array $adapters = [];

    public function register(RuntimeAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->adapterKey()] = $adapter;
    }

    public function has(string $adapterKey): bool
    {
        return array_key_exists($adapterKey, $this->adapters);
    }

    public function resolve(string $adapterKey): RuntimeAdapterInterface
    {
        if (! $this->has($adapterKey)) {
            throw AdapterNotFoundException::forKey($adapterKey);
        }

        return $this->adapters[$adapterKey];
    }

    /**
     * @return list<string>
     */
    public function registeredKeys(): array
    {
        return array_keys($this->adapters);
    }
}
