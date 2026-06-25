<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const KEY_DIRECTOR = 'director';

    public const KEY_MUSICIAN = 'musician';

    public const KEY_SOUND_TECH = 'sound_tech';

    public const KEY_ASSISTANT = 'assistant';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'role_key',
        'name',
        'description',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['band_id', 'assigned_at', 'assigned_by'])
            ->withTimestamps();
    }
}
