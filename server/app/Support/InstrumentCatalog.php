<?php

namespace App\Support;

class InstrumentCatalog
{
    /**
     * @return list<array{slug: string, name: string, family?: string}>
     */
    public static function definitions(): array
    {
        return [
            ['slug' => 'scaffold-vocals', 'name' => 'Vocals', 'family' => 'Voice'],
            ['slug' => 'scaffold-electric-guitar', 'name' => 'Electric Guitar', 'family' => 'Guitar'],
            ['slug' => 'scaffold-acoustic-guitar', 'name' => 'Acoustic Guitar', 'family' => 'Guitar'],
            ['slug' => 'scaffold-bass-guitar', 'name' => 'Bass Guitar', 'family' => 'Guitar'],
            ['slug' => 'scaffold-drums', 'name' => 'Drums', 'family' => 'Percussion'],
            ['slug' => 'scaffold-percussion', 'name' => 'Percussion', 'family' => 'Percussion'],
            ['slug' => 'scaffold-keys', 'name' => 'Keys', 'family' => 'Keyboard'],
            ['slug' => 'scaffold-accordion', 'name' => 'Accordion', 'family' => 'Keyboard'],
            ['slug' => 'scaffold-machines', 'name' => 'Machines', 'family' => 'Electronic'],
            ['slug' => 'scaffold-trumpet', 'name' => 'Trumpet', 'family' => 'Brass'],
            ['slug' => 'scaffold-trombone', 'name' => 'Trombone', 'family' => 'Brass'],
            ['slug' => 'scaffold-clarinet', 'name' => 'Clarinet', 'family' => 'Woodwind'],
            ['slug' => 'scaffold-alto-sax', 'name' => 'Alto Sax', 'family' => 'Woodwind'],
            ['slug' => 'scaffold-tenor-sax', 'name' => 'Tenor Sax', 'family' => 'Woodwind'],
            ['slug' => 'scaffold-baritone-sax', 'name' => 'Baritone Sax', 'family' => 'Woodwind'],
            ['slug' => 'scaffold-sousaphone', 'name' => 'Sousaphone', 'family' => 'Brass'],
            ['slug' => 'scaffold-cuatro', 'name' => 'Cuatro', 'family' => 'Guitar'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_column(self::definitions(), 'slug');
    }
}
