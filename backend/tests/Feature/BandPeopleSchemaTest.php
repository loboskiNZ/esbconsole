<?php

namespace Tests\Feature;

use App\Enums\PersonFileType;
use App\Enums\PersonSecureFieldType;
use App\Models\InstrumentReference;
use App\Models\Person;
use App\Models\PersonFile;
use App\Models\PersonIemSetting;
use App\Models\PersonInstrument;
use App\Models\PersonSecureField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BandPeopleSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_records_can_be_created_with_profile_fields(): void
    {
        $person = Person::factory()->create([
            'legal_first_name' => 'Jane',
            'legal_middle_names' => 'Marie',
            'legal_last_name' => 'Artist',
            'artistic_name' => 'JMA',
            'email' => 'jane@example.test',
            'phone' => '+64 21 555 0100',
            'gender' => 'female',
            'pronouns' => 'she/her',
            'city' => 'Auckland',
            'country' => 'New Zealand',
            'dietary_requirements' => 'Vegetarian',
            'notes' => 'Tour contact preferred by email',
        ]);

        $this->assertSame('Jane', $person->legal_first_name);
        $this->assertSame('Marie', $person->legal_middle_names);
        $this->assertSame('Artist', $person->legal_last_name);
        $this->assertSame('JMA', $person->artistic_name);
        $this->assertSame('jane@example.test', $person->email);
        $this->assertSame('+64 21 555 0100', $person->phone);
        $this->assertSame('female', $person->gender);
        $this->assertSame('she/her', $person->pronouns);
        $this->assertSame('Auckland', $person->city);
        $this->assertSame('New Zealand', $person->country);
        $this->assertSame('Vegetarian', $person->dietary_requirements);
        $this->assertSame('Tour contact preferred by email', $person->notes);
        $this->assertNotEmpty($person->public_id);
    }

    public function test_person_secure_fields_are_encrypted_at_rest(): void
    {
        $person = Person::factory()->create();
        $plainText = 'NZ1234567890';

        $field = PersonSecureField::storeEncrypted(
            $person,
            PersonSecureFieldType::PassportNumber,
            $plainText,
            ['issuer' => 'NZ'],
        );

        $rawEncrypted = DB::table('person_secure_fields')
            ->where('id', $field->id)
            ->value('encrypted_value');

        $this->assertNotSame($plainText, $rawEncrypted);
        $this->assertStringNotContainsString($plainText, (string) $rawEncrypted);
        $this->assertSame($plainText, $field->plaintextValue());
        $this->assertSame('7890', $field->last_four_preview);
        $this->assertSame(['issuer' => 'NZ'], $field->metadata);
    }

    public function test_secure_fields_can_be_associated_with_a_person_by_field_type(): void
    {
        $person = Person::factory()->create();

        PersonSecureField::storeEncrypted($person, PersonSecureFieldType::BankAccount, '12-3456-7890123-00');
        PersonSecureField::storeEncrypted($person, PersonSecureFieldType::AirNewZealandPoints, '987654321');

        $person->refresh();

        $this->assertCount(2, $person->secureFields);
        $this->assertTrue($person->secureFields->contains(
            fn (PersonSecureField $field) => $field->field_type === PersonSecureFieldType::BankAccount
        ));
        $this->assertTrue($person->secureFields->contains(
            fn (PersonSecureField $field) => $field->field_type === PersonSecureFieldType::AirNewZealandPoints
        ));
    }

    public function test_secure_fields_are_hidden_from_default_serialization(): void
    {
        $person = Person::factory()->create();
        $field = PersonSecureField::storeEncrypted(
            $person,
            PersonSecureFieldType::PassportNumber,
            'SECRET-PASSPORT-123',
        );

        $serialized = $field->toArray();

        $this->assertArrayNotHasKey('encrypted_value', $serialized);
        $this->assertArrayHasKey('last_four_preview', $serialized);
    }

    public function test_person_files_can_be_associated_and_are_non_public_by_default(): void
    {
        $person = Person::factory()->create();

        $file = PersonFile::factory()->create([
            'person_id' => $person->id,
            'file_type' => PersonFileType::PassportPhoto,
            'file_path' => 'people/'.$person->public_id.'/passport.jpg',
            'original_filename' => 'passport.jpg',
        ]);

        $this->assertFalse($file->is_public);
        $this->assertTrue($person->fresh()->files->contains($file));
        $this->assertSame(PersonFileType::PassportPhoto, $file->file_type);
    }

    public function test_instrument_reference_records_can_be_created(): void
    {
        $instrument = InstrumentReference::factory()->create([
            'name' => 'Electric Guitar',
            'family' => 'strings',
            'is_active' => true,
        ]);

        $this->assertSame('Electric Guitar', $instrument->name);
        $this->assertSame('strings', $instrument->family);
        $this->assertTrue($instrument->is_active);
        $this->assertNotEmpty($instrument->public_id);
    }

    public function test_person_can_be_assigned_one_or_more_instruments(): void
    {
        $person = Person::factory()->create();
        $guitar = InstrumentReference::factory()->create(['name' => 'Electric Guitar']);
        $vocals = InstrumentReference::factory()->create(['name' => 'Lead Vocal']);

        PersonInstrument::factory()->create([
            'person_id' => $person->id,
            'instrument_id' => $guitar->id,
            'role_label' => 'Lead',
            'is_primary' => true,
        ]);
        PersonInstrument::factory()->create([
            'person_id' => $person->id,
            'instrument_id' => $vocals->id,
            'role_label' => 'Harmony',
            'is_primary' => false,
        ]);

        $person->refresh();

        $this->assertCount(2, $person->personInstruments);
        $this->assertCount(2, $person->instruments);
        $this->assertTrue($person->instruments->contains($guitar));
        $this->assertTrue($person->instruments->contains($vocals));
    }

    public function test_person_iem_setting_templates_can_be_created_and_associated(): void
    {
        $person = Person::factory()->create();

        $template = PersonIemSetting::factory()->create([
            'person_id' => $person->id,
            'name' => 'Festival',
            'vocal_level' => 0.75,
            'own_instrument_level' => 0.60,
            'band_level' => 0.50,
            'click_level' => 0.40,
            'tracks_level' => 0.55,
            'reverb_level' => 0.20,
            'ambient_level' => 0.10,
            'notes' => 'Outdoor stage preset',
        ]);

        $this->assertTrue($person->fresh()->iemSettings->contains($template));
        $this->assertSame('Festival', $template->name);
        $this->assertSame('0.75', $template->vocal_level);
        $this->assertSame('Outdoor stage preset', $template->notes);
    }

    public function test_person_relationships_resolve_correctly(): void
    {
        $person = Person::factory()->create();
        $instrument = InstrumentReference::factory()->create();

        PersonSecureField::storeEncrypted($person, PersonSecureFieldType::PassportNumber, 'AB1234567');
        PersonFile::factory()->create(['person_id' => $person->id]);
        PersonInstrument::factory()->create([
            'person_id' => $person->id,
            'instrument_id' => $instrument->id,
        ]);
        PersonIemSetting::factory()->create(['person_id' => $person->id]);

        $person->load(['secureFields', 'files', 'personInstruments.instrument', 'iemSettings']);

        $this->assertCount(1, $person->secureFields);
        $this->assertCount(1, $person->files);
        $this->assertCount(1, $person->personInstruments);
        $this->assertTrue($person->personInstruments->first()->instrument->is($instrument));
        $this->assertCount(1, $person->iemSettings);
        $this->assertCount(1, $instrument->fresh()->personInstruments);
    }
}
