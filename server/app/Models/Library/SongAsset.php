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
}
