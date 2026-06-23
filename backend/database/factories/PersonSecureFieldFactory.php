<?php

namespace Database\Factories;

use App\Enums\PersonSecureFieldType;
use App\Models\Person;
use App\Models\PersonSecureField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonSecureField>
 */
class PersonSecureFieldFactory extends Factory
{
    protected $model = PersonSecureField::class;

    public function definition(): array
    {
        $plainText = fake()->numerify('##########');

        return [
            'person_id' => Person::factory(),
            'field_type' => PersonSecureFieldType::PassportNumber,
            'encrypted_value' => 'placeholder',
            'encryption_key_context' => 'person_secure_field',
            'last_four_preview' => mb_substr($plainText, -4),
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PersonSecureField $field): void {
            if ($field->encrypted_value === 'placeholder') {
                $field->encrypted_value = app(\App\Services\PersonSecureFieldEncryption::class)
                    ->encrypt(fake()->numerify('##########'));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withPlaintext(string $plainText, ?PersonSecureFieldType $fieldType = null, ?array $metadata = null): static
    {
        return $this->state(function (array $attributes) use ($plainText, $fieldType, $metadata) {
            $encryption = app(\App\Services\PersonSecureFieldEncryption::class);

            return [
                'field_type' => $fieldType ?? PersonSecureFieldType::PassportNumber,
                'encrypted_value' => $encryption->encrypt($plainText),
                'encryption_key_context' => \App\Services\PersonSecureFieldEncryption::KEY_CONTEXT,
                'last_four_preview' => $encryption->lastFourPreview($plainText),
                'metadata' => $metadata,
            ];
        });
    }
}
