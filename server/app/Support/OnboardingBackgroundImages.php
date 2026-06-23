<?php

namespace App\Support;

class OnboardingBackgroundImages
{
    /**
     * @return list<string>
     */
    public static function resolve(): array
    {
        $bandpicsDirectory = public_path('images/bandpics');

        $backgroundImages = is_dir($bandpicsDirectory)
            ? collect(scandir($bandpicsDirectory) ?: [])
                ->reject(fn (string $file) => in_array($file, ['.', '..'], true))
                ->filter(fn (string $file) => (bool) preg_match('/\.(jpe?g|png)$/i', $file))
                ->reject(fn (string $file) => str_contains(strtolower($file), '_logo'))
                ->sort()
                ->values()
                ->map(fn (string $file) => asset('images/bandpics/'.$file))
                ->all()
            : [];

        if ($backgroundImages === []) {
            return [asset('images/portal/ESB-Lobofest3.jpg')];
        }

        return $backgroundImages;
    }
}
