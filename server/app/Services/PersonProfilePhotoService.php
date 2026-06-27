<?php

namespace App\Services;

use App\Models\Person;
use App\Support\CloudStudioMediaStorage;
use Illuminate\Http\UploadedFile;

class PersonProfilePhotoService
{
    public function __construct(
        private readonly ProfilePhotoDisplayGenerator $displayGenerator,
        private readonly CloudStudioMediaStorage $mediaStorage,
    ) {}

    public function storagePrefix(Person $person): string
    {
        return 'portal/profile-photos/'.$person->id;
    }

    /**
     * @return array{original: string, display: string}
     */
    public function store(Person $person, UploadedFile $file): array
    {
        $prefix = $this->storagePrefix($person);
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'jpg');
        $originalPath = $prefix.'/original.'.$extension;
        $displayPath = $prefix.'/display.jpg';

        $this->deleteExisting($person);

        $sourceAbsolute = $file->getRealPath();
        $displayTemp = tempnam(sys_get_temp_dir(), 'esb-profile-display-');

        if ($sourceAbsolute === false || $displayTemp === false) {
            throw new \RuntimeException('Unable to prepare profile photo upload.');
        }

        $this->displayGenerator->createFromFile($sourceAbsolute, $displayTemp);

        $this->mediaStorage->put($originalPath, (string) file_get_contents($sourceAbsolute));
        $this->mediaStorage->put($displayPath, (string) file_get_contents($displayTemp));

        @unlink($displayTemp);

        return [
            'original' => $originalPath,
            'display' => $displayPath,
        ];
    }

    public function deleteExisting(Person $person): void
    {
        $paths = array_filter([
            $person->profile_photo_path,
            $person->profile_photo_display_path,
        ]);

        foreach ($paths as $path) {
            $this->mediaStorage->delete($path);
        }

        $legacyPrefix = $this->storagePrefix($person);

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $this->mediaStorage->delete($legacyPrefix.'/profile.'.$extension);
        }
    }
}
