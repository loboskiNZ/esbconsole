<?php

namespace App\Services\X32;

use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Models\EffectPackageItemParameter;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class X32FxOscDeployReadback
{
    public function __construct(
        private readonly X32OscConsoleClientInterface $oscClient,
        private readonly X32EffectParameterOscEncoder $parameterEncoder,
    ) {}

    public function resolveTypePath(string $host, int $port, int $slot): string
    {
        foreach (X32OscAddressMap::fxTypePathCandidates($slot) as $path) {
            if ($this->pathResponds($host, $port, $path)) {
                return $path;
            }
        }

        return X32OscAddressMap::fxType($slot);
    }

    public function resolveParameterPath(string $host, int $port, int $slot, int $parameterNumber): string
    {
        foreach (X32OscAddressMap::fxParameterPathCandidates($slot, $parameterNumber) as $path) {
            if ($this->parameterPathResponds($host, $port, $path)) {
                return $path;
            }
        }

        return X32OscAddressMap::fxParameter($slot, $parameterNumber);
    }

    public function confirmType(
        string $host,
        int $port,
        string $path,
        int $algorithmId,
        ?string $effectCode,
    ): bool {
        usleep(200_000);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            if ($attempt > 0) {
                usleep(250_000);
            }

            if ($this->matchesTypeInt($host, $port, $path, $algorithmId)) {
                return true;
            }

            if ($effectCode !== null && $this->matchesTypeEnum($host, $port, $path, $effectCode)) {
                return true;
            }
        }

        return false;
    }

    public function confirmParameter(
        string $host,
        int $port,
        string $path,
        EffectPackageItemParameter $parameter,
        float $encoded,
    ): bool {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($attempt > 0) {
                usleep(150_000);
            }

            try {
                $confirmed = $parameter->value_type === 'enum'
                    ? (float) $this->readEnumValue($host, $port, $path)
                    : $this->oscClient->queryFloat($host, $port, $path);

                if ($this->parameterEncoder->writeConfirmed($parameter, $encoded, $confirmed)) {
                    return true;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return false;
    }

    private function readEnumValue(string $host, int $port, string $path): int
    {
        try {
            return $this->oscClient->queryInt($host, $port, $path);
        } catch (InvalidArgumentException) {
            return $this->oscClient->queryOn($host, $port, $path);
        }
    }

    private function parameterPathResponds(string $host, int $port, string $path): bool
    {
        try {
            $this->oscClient->queryFloat($host, $port, $path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pathResponds(string $host, int $port, string $path): bool
    {
        try {
            $this->oscClient->queryInt($host, $port, $path);

            return true;
        } catch (InvalidArgumentException) {
            try {
                $this->oscClient->queryString($host, $port, $path);

                return true;
            } catch (Throwable) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }
    }

    private function matchesTypeInt(string $host, int $port, string $path, int $algorithmId): bool
    {
        try {
            return $this->oscClient->queryInt($host, $port, $path) === $algorithmId;
        } catch (InvalidArgumentException|RuntimeException) {
            return false;
        }
    }

    private function matchesTypeEnum(string $host, int $port, string $path, string $effectCode): bool
    {
        try {
            $confirmed = strtoupper(trim($this->oscClient->queryString($host, $port, $path)));

            return $confirmed === strtoupper($effectCode);
        } catch (Throwable) {
            return false;
        }
    }
}
