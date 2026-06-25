<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class InviteLink extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'token_hash',
        'token_ciphertext',
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

    public static function sealToken(string $rawToken): string
    {
        return Crypt::encryptString($rawToken);
    }

    public function revealToken(): ?string
    {
        if ($this->token_ciphertext === null || $this->token_ciphertext === '') {
            return null;
        }

        try {
            return Crypt::decryptString($this->token_ciphertext);
        } catch (DecryptException) {
            return null;
        }
    }

    public function inviteUrl(): ?string
    {
        $token = $this->revealToken();

        if ($token === null) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/invite/'.$token;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithToken(string $name, string $rawToken, Carbon $expiresAt, array $attributes = []): self
    {
        return static::create(array_merge([
            'name' => $name,
            'token_hash' => static::hashToken($rawToken),
            'token_ciphertext' => static::sealToken($rawToken),
            'expires_at' => $expiresAt,
        ], $attributes));
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
