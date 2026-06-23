<?php

namespace App\Models;

use App\Enums\PersonSecureFieldType;
use App\Services\PersonSecureFieldEncryption;
use Database\Factories\PersonSecureFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonSecureField extends Model
{
    /** @use HasFactory<PersonSecureFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'person_id',
        'field_type',
        'encrypted_value',
        'encryption_key_context',
        'last_four_preview',
        'metadata',
    ];

    protected $hidden = [
        'encrypted_value',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => PersonSecureFieldType::class,
            'metadata' => 'array',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function storeEncrypted(
        Person $person,
        PersonSecureFieldType $fieldType,
        string $plainText,
        ?array $metadata = null,
    ): self {
        $encryption = app(PersonSecureFieldEncryption::class);

        return self::query()->create([
            'person_id' => $person->id,
            'field_type' => $fieldType,
            'encrypted_value' => $encryption->encrypt($plainText),
            'encryption_key_context' => PersonSecureFieldEncryption::KEY_CONTEXT,
            'last_four_preview' => $encryption->lastFourPreview($plainText),
            'metadata' => $metadata,
        ]);
    }

    public function plaintextValue(): string
    {
        return app(PersonSecureFieldEncryption::class)->decrypt($this->encrypted_value);
    }
}
