<?php

namespace App\Services;

use App\Models\Library\Song;
use App\Models\Library\SongAsset;
use App\Models\User;
use App\Support\CloudStudioMediaStorage;
use App\Support\SongAssetType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SongAssetStorageService
{
    public function __construct(
        private readonly CloudStudioMediaStorage $mediaStorage,
    ) {}

    public function store(
        Song $song,
        UploadedFile $file,
        string $assetType,
        string $label,
        ?string $notes,
        User $user,
    ): SongAsset {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new \InvalidArgumentException('Unable to read uploaded song asset.');
        }

        $originalFilename = $file->getClientOriginalName();
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'bin');
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $filename = Str::slug($baseName).'-'.Str::lower(Str::random(8)).'.'.$extension;
        $storageReference = $this->mediaStorage->songAssetReference($song->id, $assetType, $filename);

        $this->mediaStorage->putMediaObject($storageReference, $contents);

        $nextSort = (int) SongAsset::query()
            ->where('song_id', $song->id)
            ->max('sort_order') + 1;

        return SongAsset::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'asset_type' => $assetType,
            'label' => $label !== '' ? $label : $baseName,
            'storage_disk' => $this->mediaStorage->mediaDisk(),
            'storage_reference' => $storageReference,
            'original_filename' => $originalFilename,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'checksum' => hash('sha256', $contents),
            'uploaded_by' => $user->id,
            'sort_order' => $nextSort,
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return ['mp3', 'wav', 'mid', 'midi'];
    }

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/vnd.wave',
            'audio/midi',
            'audio/x-midi',
            'application/x-midi',
        ];
    }

    public function resolveAssetType(string $requestedType, UploadedFile $file): string
    {
        if (in_array($requestedType, SongAssetType::all(), true)) {
            return $requestedType;
        }

        return SongAssetType::inferFromFilename($file->getClientOriginalName());
    }

    public function destroy(SongAsset $asset): void
    {
        $reference = $asset->storage_reference;

        if ($reference !== null && $reference !== '') {
            $this->mediaStorage->delete($reference);
        }

        $asset->delete();
    }

    public function storeGeneratedLyricsPdf(Song $song, string $pdfContents, User $user): SongAsset
    {
        SongAsset::query()
            ->where('song_id', $song->id)
            ->where('asset_type', SongAssetType::LYRICS_PDF)
            ->get()
            ->each(fn (SongAsset $asset) => $this->destroy($asset));

        $originalFilename = str($song->name)->slug('_')->limit(60, '')->toString().'-lyrics.pdf';
        $filename = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)).'-'.Str::lower(Str::random(8)).'.pdf';
        $storageReference = $this->mediaStorage->songAssetReference($song->id, SongAssetType::LYRICS_PDF, $filename);

        $this->mediaStorage->putMediaObject($storageReference, $pdfContents);

        $nextSort = (int) SongAsset::query()
            ->where('song_id', $song->id)
            ->max('sort_order') + 1;

        return SongAsset::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'asset_type' => SongAssetType::LYRICS_PDF,
            'label' => 'Lyrics PDF',
            'storage_disk' => $this->mediaStorage->mediaDisk(),
            'storage_reference' => $storageReference,
            'original_filename' => $originalFilename,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdfContents),
            'checksum' => hash('sha256', $pdfContents),
            'uploaded_by' => $user->id,
            'sort_order' => $nextSort,
            'notes' => null,
        ]);
    }
}
