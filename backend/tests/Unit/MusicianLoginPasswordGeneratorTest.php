<?php

namespace Tests\Unit;

use App\Services\MusicianLoginPasswordGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MusicianLoginPasswordGeneratorTest extends TestCase
{
    #[Test]
    public function generated_password_is_eight_characters_with_required_classes(): void
    {
        $generator = new MusicianLoginPasswordGenerator;

        for ($i = 0; $i < 25; $i++) {
            $password = $generator->generate();

            $this->assertSame(8, strlen($password));
            $this->assertTrue($generator->satisfiesRequirements($password));
        }
    }
}
