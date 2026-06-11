<?php

namespace App\Services\LegacyImport;

class LegacyRoleNormalizer
{
    /** @var array<string, string> */
    private const ALIASES = [
        'machines' => 'Machines',
        'singer' => 'Singer',
        'trumpet' => 'Trumpet',
        'guitarrist' => 'Guitar',
        'guitar' => 'Guitar',
        'keyboard' => 'Keyboard',
        'keys' => 'Keyboard',
        'drummer' => 'Drums',
        'drums' => 'Drums',
        'sous' => 'Sousaphone',
        'sousaphone' => 'Sousaphone',
        'alto sax' => 'Alto Sax',
        'alto_sax' => 'Alto Sax',
        'bari sax' => 'Baritone Sax',
        'baritone sax' => 'Baritone Sax',
        'bari' => 'Baritone Sax',
        'cuatro' => 'Cuatro',
        'trombone' => 'Trombone',
        'bass' => 'Bass',
    ];

    public function slug(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($value)) ?? '');
    }

    public function normalize(string $legacyRole): string
    {
        $slug = $this->slug($legacyRole);

        if (isset(self::ALIASES[$slug])) {
            return self::ALIASES[$slug];
        }

        if (isset(self::ALIASES[strtolower(trim($legacyRole))])) {
            return self::ALIASES[strtolower(trim($legacyRole))];
        }

        $words = preg_split('/[\s_]+/', trim($legacyRole)) ?: [];
        $normalized = implode(' ', array_map(
            fn (string $word) => ucfirst(strtolower($word)),
            array_filter($words),
        ));

        return $normalized !== '' ? $normalized : 'Unknown';
    }

    public function catalogKey(string $legacyRole): string
    {
        return $this->slug($legacyRole);
    }
}
