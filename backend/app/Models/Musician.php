<?php

namespace App\Models;

use App\Enums\BandRole;
use App\Models\Concerns\HasPublicId;
use Database\Factories\MusicianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Musician extends Model
{
    /** @use HasFactory<MusicianFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'band_id',
        'user_id',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'notes',
        'dietary_preferences',
        'allergies',
        'accessibility_notes',
        'travel_notes',
        'emergency_contact_notes',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(Capability::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function performanceAssignments(): HasMany
    {
        return $this->hasMany(PerformanceAssignment::class);
    }

    public function bandRoles(): HasMany
    {
        return $this->hasMany(MusicianBandRole::class);
    }

    /**
     * @return array<int, string>
     */
    public function bandRoleValues(): array
    {
        return $this->bandRoles->pluck('role')->all();
    }

    /**
     * @return array<int, string>
     */
    public function bandRoleLabels(): array
    {
        return collect($this->bandRoleValues())
            ->map(fn (string $role) => BandRole::tryFrom($role)?->label() ?? $role)
            ->all();
    }

    public function hasBandRole(BandRole $role): bool
    {
        return in_array($role->value, $this->bandRoleValues(), true);
    }
}
