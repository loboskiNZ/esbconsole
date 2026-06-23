<?php

namespace Database\Seeders;

use App\Models\MusicalKey;
use App\Models\SongMood;
use App\Models\TimeSignature;
use Illuminate\Database\Seeder;

class SongMetadataReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $moods = [
            ['name' => 'Neutral / Default', 'slug' => 'neutral', 'colour_hex' => '#5BC0EB', 'accent_colour_hex' => '#8ED4F0', 'sort_order' => 10],
            ['name' => 'Happy', 'slug' => 'happy', 'colour_hex' => '#FFB23E', 'accent_colour_hex' => '#FFD27A', 'sort_order' => 20],
            ['name' => 'Sad', 'slug' => 'sad', 'colour_hex' => '#4A7FD4', 'accent_colour_hex' => '#6B9EE8', 'sort_order' => 30],
            ['name' => 'Angry', 'slug' => 'angry', 'colour_hex' => '#D64545', 'accent_colour_hex' => '#E87070', 'sort_order' => 40],
            ['name' => 'Powerful', 'slug' => 'powerful', 'colour_hex' => '#E86B1F', 'accent_colour_hex' => '#F5934A', 'sort_order' => 50],
            ['name' => 'Romantic', 'slug' => 'romantic', 'colour_hex' => '#D44A9E', 'accent_colour_hex' => '#E876BA', 'sort_order' => 60],
            ['name' => 'Mysterious', 'slug' => 'mysterious', 'colour_hex' => '#7B4AD4', 'accent_colour_hex' => '#9A6FE8', 'sort_order' => 70],
            ['name' => 'Reflective', 'slug' => 'reflective', 'colour_hex' => '#4A56A8', 'accent_colour_hex' => '#6B76C8', 'sort_order' => 80],
            ['name' => 'Party', 'slug' => 'party', 'colour_hex' => '#6BCF2E', 'accent_colour_hex' => '#92E05A', 'sort_order' => 90],
        ];

        foreach ($moods as $mood) {
            SongMood::query()->updateOrCreate(
                ['slug' => $mood['slug']],
                array_merge($mood, ['active' => true]),
            );
        }

        $timeSignatures = ['4/4', '3/4', '6/8', '2/4', '12/8', '5/4', '7/8', '3/2'];

        foreach ($timeSignatures as $index => $label) {
            TimeSignature::query()->updateOrCreate(
                ['label' => $label],
                ['sort_order' => ($index + 1) * 10, 'active' => true],
            );
        }

        $keys = [
            ['label' => 'C major', 'tonic' => 'C', 'mode' => 'major'],
            ['label' => 'C minor', 'tonic' => 'C', 'mode' => 'minor'],
            ['label' => 'C# major', 'tonic' => 'C#', 'mode' => 'major'],
            ['label' => 'Db major', 'tonic' => 'Db', 'mode' => 'major'],
            ['label' => 'D major', 'tonic' => 'D', 'mode' => 'major'],
            ['label' => 'D minor', 'tonic' => 'D', 'mode' => 'minor'],
            ['label' => 'Eb major', 'tonic' => 'Eb', 'mode' => 'major'],
            ['label' => 'E major', 'tonic' => 'E', 'mode' => 'major'],
            ['label' => 'E minor', 'tonic' => 'E', 'mode' => 'minor'],
            ['label' => 'F major', 'tonic' => 'F', 'mode' => 'major'],
            ['label' => 'F minor', 'tonic' => 'F', 'mode' => 'minor'],
            ['label' => 'F# major', 'tonic' => 'F#', 'mode' => 'major'],
            ['label' => 'G major', 'tonic' => 'G', 'mode' => 'major'],
            ['label' => 'G minor', 'tonic' => 'G', 'mode' => 'minor'],
            ['label' => 'Ab major', 'tonic' => 'Ab', 'mode' => 'major'],
            ['label' => 'A major', 'tonic' => 'A', 'mode' => 'major'],
            ['label' => 'A minor', 'tonic' => 'A', 'mode' => 'minor'],
            ['label' => 'Bb major', 'tonic' => 'Bb', 'mode' => 'major'],
            ['label' => 'B major', 'tonic' => 'B', 'mode' => 'major'],
            ['label' => 'B minor', 'tonic' => 'B', 'mode' => 'minor'],
        ];

        foreach ($keys as $index => $key) {
            MusicalKey::query()->updateOrCreate(
                ['label' => $key['label']],
                array_merge($key, ['sort_order' => ($index + 1) * 10, 'active' => true]),
            );
        }
    }
}
