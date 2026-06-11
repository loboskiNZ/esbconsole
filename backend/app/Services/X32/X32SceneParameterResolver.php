<?php

namespace App\Services\X32;

class X32SceneParameterResolver
{
    public const MIN_SCENE = 1;

    public const MAX_SCENE = 100;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(array $payload): ?string
    {
        $parameters = is_array($payload['parameters'] ?? null)
            ? $payload['parameters']
            : $payload;

        $scene = $parameters['scene'] ?? $parameters['scene_number'] ?? null;

        if ($scene === null || $scene === '') {
            return null;
        }

        if (! is_numeric($scene)) {
            return null;
        }

        $sceneNumber = (int) $scene;

        if ($sceneNumber < self::MIN_SCENE || $sceneNumber > self::MAX_SCENE) {
            return null;
        }

        return (string) $sceneNumber;
    }
}
