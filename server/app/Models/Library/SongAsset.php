<?php

namespace App\Models\Library;

use App\Support\SongAssetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongAsset extends Model
{
    protected $table = 'song_assets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'song_id',
        'asset_type',
        'label',
        'storage_disk',
        'storage_reference',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
        'uploaded_by',
        'sort_order',
        'notes',
    ];

    public function getConnectionName(): ?string
    {
        return config('portal.library_connection');
    }

    /**
     * @return BelongsTo<Song, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function assetTypeLabel(): string
    {
        return SongAssetType::labelFor($this->asset_type);
    }

    public function formattedFileSize(): string
    {
        $bytes = (int) ($this->file_size ?? 0);

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }

    public function displayName(): string
    {
        $label = trim($this->label ?? '');

        return $label !== '' ? $label : $this->original_filename;
    }

    public function isInlinePlayable(): bool
    {
        $extension = strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));

        if (in_array($extension, ['mp3', 'wav'], true)) {
            return true;
        }

        $mime = strtolower($this->mime_type ?? '');

        return in_array($mime, ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/wave'], true);
    }
}
