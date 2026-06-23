<?php

namespace App\Services;

use RuntimeException;

class ProfilePhotoDisplayGenerator
{
    public function __construct(
        private readonly int $maxEdge = 960,
        private readonly int $jpegQuality = 85,
    ) {}

    public function createFromFile(string $sourcePath, string $destinationPath): void
    {
        if (! extension_loaded('gd')) {
            copy($sourcePath, $destinationPath);

            return;
        }

        $image = $this->loadImage($sourcePath);

        if ($image === false) {
            throw new RuntimeException('Unable to read profile photo for display processing.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $this->maxEdge / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            imagedestroy($image);
            throw new RuntimeException('Unable to allocate display image canvas.');
        }

        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        );

        if (! imagejpeg($canvas, $destinationPath, $this->jpegQuality)) {
            imagedestroy($image);
            imagedestroy($canvas);
            throw new RuntimeException('Unable to write profile display image.');
        }

        imagedestroy($image);
        imagedestroy($canvas);
    }

    /**
     * @return \GdImage|resource|false
     */
    private function loadImage(string $sourcePath): mixed
    {
        $mime = mime_content_type($sourcePath) ?: '';

        return match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp')
                ? imagecreatefromwebp($sourcePath)
                : false,
            default => false,
        };
    }
}
