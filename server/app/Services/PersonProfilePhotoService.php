<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PersonProfilePhotoService
{
    public function __construct(
        private readonly ProfilePhotoDisplayGenerator $displayGenerator,
    ) {}

    public function disk(): string
    {
        return (string) config('portal.profile_photo_disk', 'local');
    }

    public function storagePrefix(Person $person): string
    {
        return 'portal/profile-photos/'.$person->id;
    }

    /**
     * @return array{original: string, display: string}
     */
    public function store(Person $person, UploadedFile $file): array
    {
        $disk = $this->disk();
        $prefix = $this->storagePrefix($person);
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'jpg');
        $originalPath = $prefix.'/original.'.$extension;
        $displayPath = $prefix.'/display.jpg';

        $this->deleteExisting($person);

        Storage::disk($disk)->putFileAs($prefix, $file, 'original.'.$extension);

        $sourceAbsolute = Storage::disk($disk)->path($originalPath);
        $displayAbsolute = Storage::disk($disk)->path($displayPath);

        $this->displayGenerator->createFromFile($sourceAbsolute, $displayAbsolute);

        return [
            'original' => $originalPath,
            'display' => $displayPath,
        ];
    }

    public function deleteExisting(Person $person): void
    {
        $disk = $this->disk();
        $paths = array_filter([
            $person->profile_photo_path,
            $person->profile_photo_display_path,
        ]);

        foreach ($paths as $path) {
            Storage::disk($disk)->delete($path);
        }

        $legacyPrefix = $this->storagePrefix($person);

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            Storage::disk($disk)->delete($legacyPrefix.'/profile.'.$extension);
        }
    }
}
