<?php

namespace App\Services\X32;

use InvalidArgumentException;

class X32OscMessageCodec
{
    public function buildQuery(string $path): string
    {
        return $this->padPath($path);
    }

    public function buildFloat(string $path, float $value): string
    {
        $message = $this->padPath($path);
        $message .= ',f';
        $message .= str_pad('', (4 - (strlen($message) % 4)) % 4, "\0");
        $message .= pack('G', $value);

        return $message;
    }

    public function buildInt(string $path, int $value): string
    {
        $message = $this->padPath($path);
        $message .= ',i';
        $message .= str_pad('', (4 - (strlen($message) % 4)) % 4, "\0");
        $message .= pack('N', $value);

        return $message;
    }

    public function parseFloatResponse(string $payload): float
    {
        $typeTagOffset = strpos($payload, ',f');

        if ($typeTagOffset === false) {
            throw new InvalidArgumentException('OSC response does not contain float type tag.');
        }

        $dataOffset = $typeTagOffset + 4;
        $dataOffset += (4 - ($dataOffset % 4)) % 4;

        if (strlen($payload) < $dataOffset + 4) {
            throw new InvalidArgumentException('OSC float response truncated.');
        }

        return unpack('G', substr($payload, $dataOffset, 4))[1];
    }

    public function parseIntResponse(string $payload): int
    {
        $typeTagOffset = strpos($payload, ',i');

        if ($typeTagOffset === false) {
            throw new InvalidArgumentException('OSC response does not contain int type tag.');
        }

        $dataOffset = $typeTagOffset + 4;
        $dataOffset += (4 - ($dataOffset % 4)) % 4;

        if (strlen($payload) < $dataOffset + 4) {
            throw new InvalidArgumentException('OSC int response truncated.');
        }

        return unpack('N', substr($payload, $dataOffset, 4))[1];
    }

    /**
     * Parse X32 on/off style enum responses that may arrive as int or float.
     */
    public function parseOnResponse(string $payload): int
    {
        if (str_contains($payload, ',i')) {
            return $this->parseIntResponse($payload) === 1 ? 1 : 0;
        }

        if (str_contains($payload, ',f')) {
            return $this->parseFloatResponse($payload) >= 0.5 ? 1 : 0;
        }

        throw new InvalidArgumentException('OSC response does not contain on/off enum type tag.');
    }

    public function parseStringResponse(string $payload): string
    {
        $typeTagOffset = strpos($payload, ',s');

        if ($typeTagOffset === false) {
            throw new InvalidArgumentException('OSC response does not contain string type tag.');
        }

        $dataOffset = $typeTagOffset + 4;
        $dataOffset += (4 - ($dataOffset % 4)) % 4;
        $end = strpos($payload, "\0", $dataOffset);

        if ($end === false) {
            throw new InvalidArgumentException('OSC string response is not null-terminated.');
        }

        return substr($payload, $dataOffset, $end - $dataOffset);
    }

    public function buildXremote(): string
    {
        return $this->padPath('/xremote');
    }

    private function padPath(string $path): string
    {
        $path = str_ends_with($path, "\0") ? $path : $path."\0";

        return $path.str_pad('', (4 - (strlen($path) % 4)) % 4, "\0");
    }
}
