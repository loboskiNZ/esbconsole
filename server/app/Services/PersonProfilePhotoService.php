<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersonProfilePhotoService
{
    public function disk(): string
    {
        return (string) config('portal.profile_photo_disk', 'local');
    }

    public function storagePrefix(Person $person): string
    {
        return 'portal/profile-photos/'.$person->id;
    }

    public function store(Person $person, UploadedFile $file): string
    {
        $disk = $this->disk();
        $prefix = $this->storagePrefix($person);
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'jpg');
        $path = $prefix.'/profile.'.$extension;

        if ($person->profile_photo_path !== null) {
            Storage::disk($disk)->delete($person->profile_photo_path);
        }

        Storage::disk($disk)->putFileAs($prefix, $file, 'profile.'.$extension);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    public function initials(Person $person): string
    {
        $name = trim((string) $person->artistic_name);

        if ($name === '') {
            $name = $person->legal_first_name;
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return Str::upper(collect($parts)->take(2)->map(
            fn (string $part) => Str::substr($part, 0, 1),
        )->implode(''));
    }
}
