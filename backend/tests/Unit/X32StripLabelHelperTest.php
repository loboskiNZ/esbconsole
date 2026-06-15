<?php

namespace Tests\Unit;

use App\Services\X32\X32StripLabelHelper;
use PHPUnit\Framework\TestCase;

class X32StripLabelHelperTest extends TestCase
{
    public function test_hides_placeholder_scene_names(): void
    {
        $this->assertNull(X32StripLabelHelper::displayName('CH 01 Scene 01', 1));
        $this->assertSame('Kick', X32StripLabelHelper::displayName('Kick', 1));
    }
}
