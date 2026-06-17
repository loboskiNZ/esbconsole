<?php

namespace App\Services\X32;

use App\Contracts\X32\X32OscConsoleClientInterface;
use RuntimeException;

/**
 * In-memory X32 OSC console client for tests and dry-run workspace control.
 */
class FakeX32OscConsoleClient implements X32OscConsoleClientInterface
{
    /** @var array<string, float|int|string> */
    private array $values = [];

    public function seedFloat(string $path, float $value): void
    {
        $this->values[$path] = $value;
    }

    public function seedInt(string $path, int $value): void
    {
        $this->values[$path] = $value;
    }

    public function seedString(string $path, string $value): void
    {
        $this->values[$path] = $value;
    }

    /** @return list<array{host: string, port: int, path: string, type: string, value: float|int}> */
    public function writes(): array
    {
        return $this->writeLog;
    }

    /** @var list<array{host: string, port: int, payload?: string, path?: string, type: string, value?: float|int}> */
    private array $writeLog = [];

    public bool $shouldFail = false;

    /** @var list<string> */
    public array $queryFailPaths = [];

    public function queryFloat(string $host, int $port, string $path): float
    {
        $this->ensureAvailable();
        $this->ensureQueryAvailable($path);

        $value = $this->values[$path] ?? 0.0;

        return is_float($value) ? $value : (float) $value;
    }

    public function queryInt(string $host, int $port, string $path): int
    {
        $this->ensureAvailable();
        $this->ensureQueryAvailable($path);

        $value = $this->values[$path] ?? 0;

        return is_int($value) ? $value : (int) $value;
    }

    public function queryOn(string $host, int $port, string $path): int
    {
        $this->ensureAvailable();
        $this->ensureQueryAvailable($path);

        $value = $this->values[$path] ?? 0;

        if (is_float($value)) {
            return $value >= 0.5 ? 1 : 0;
        }

        return ((int) $value) === 1 ? 1 : 0;
    }

    public function queryString(string $host, int $port, string $path): string
    {
        $this->ensureAvailable();

        $value = $this->values[$path] ?? '';

        return is_string($value) ? $value : (string) $value;
    }

    public function setFloat(string $host, int $port, string $path, float $value): void
    {
        $this->ensureAvailable();
        $this->values[$path] = $value;
        $this->writeLog[] = compact('host', 'port', 'path') + ['type' => 'float', 'value' => $value];
    }

    public function setInt(string $host, int $port, string $path, int $value): void
    {
        $this->ensureAvailable();
        $this->values[$path] = $value;
        $this->writeLog[] = compact('host', 'port', 'path') + ['type' => 'int', 'value' => $value];
    }

    public function sendPacket(string $host, int $port, string $payload): void
    {
        $this->ensureAvailable();
        $this->writeLog[] = compact('host', 'port', 'payload') + ['type' => 'packet'];
    }

    private function ensureAvailable(): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Fake X32 OSC client unavailable.');
        }
    }

    private function ensureQueryAvailable(string $path): void
    {
        if (in_array($path, $this->queryFailPaths, true)) {
            throw new RuntimeException('Fake X32 OSC read-back failed for '.$path);
        }
    }
}
