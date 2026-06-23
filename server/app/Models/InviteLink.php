<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InviteLink extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'token_hash',
        'expires_at',
        'revoked_at',
        'used_count',
        'max_uses',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'used_count' => 'integer',
            'max_uses' => 'integer',
        ];
    }

    public static function generateRawToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findValidByToken(string $token): ?self
    {
        $invite = static::query()
            ->where('token_hash', static::hashToken($token))
            ->first();

        if ($invite === null || ! $invite->isValid()) {
            return null;
        }

        return $invite;
    }

    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function canAcceptAnotherRegistration(): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        if ($this->max_uses === null) {
            return true;
        }

        return $this->used_count < $this->max_uses;
    }

    /**
     * @return HasMany<InviteLinkAcceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(InviteLinkAcceptance::class);
    }
}
