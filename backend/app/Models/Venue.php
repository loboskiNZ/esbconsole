<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'band_id',
        'name',
        'country',
        'city',
        'address',
        'contact_name',
        'contact_phone',
        'contact_email',
        'facebook_tag',
        'instagram_tag',
        'tiktok_tag',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function searchText(): string
    {
        return mb_strtolower(collect([
            $this->name,
            $this->country,
            $this->city,
            $this->address,
            $this->contact_name,
            $this->contact_phone,
            $this->contact_email,
        ])->filter(fn (?string $value) => filled($value))->implode(' '));
    }

    public function matchesSearch(string $query): bool
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return true;
        }

        return str_contains($this->searchText(), $query);
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
