<?php

namespace App\Models;

use App\Enums\FestivalApplicationStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\FestivalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Festival extends Model
{
    /** @use HasFactory<FestivalFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'band_id',
        'name',
        'country',
        'city',
        'website',
        'contact_name',
        'contact_phone',
        'contact_email',
        'application_url',
        'application_deadline',
        'festival_date_notes',
        'application_status',
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
            'application_deadline' => 'date',
            'application_status' => FestivalApplicationStatus::class,
        ];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function searchText(): string
    {
        $status = $this->application_status instanceof FestivalApplicationStatus
            ? $this->application_status
            : FestivalApplicationStatus::tryFrom((string) $this->application_status);

        return mb_strtolower(collect([
            $this->name,
            $this->country,
            $this->city,
            $this->website,
            $this->contact_name,
            $this->contact_phone,
            $this->contact_email,
            $this->application_url,
            $status?->value,
            $status?->label(),
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
