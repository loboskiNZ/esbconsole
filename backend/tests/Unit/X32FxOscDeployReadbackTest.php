<?php

namespace Tests\Unit;

use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\X32EffectParameterOscEncoder;
use App\Services\X32\X32FxOscDeployReadback;
use App\Services\X32\X32OscAddressMap;
use Tests\TestCase;

class X32FxOscDeployReadbackTest extends TestCase
{
    public function test_confirm_type_accepts_enum_string_response(): void
    {
        $fakeOsc = app(FakeX32OscConsoleClient::class);
        $path = X32OscAddressMap::fxType(7);
        $fakeOsc->seedString($path, 'GEQ');

        $readback = new X32FxOscDeployReadback($fakeOsc, new X32EffectParameterOscEncoder);

        $this->assertTrue($readback->confirmType('127.0.0.1', 10023, $path, 1, 'GEQ'));
    }

    public function test_fx_type_path_candidates_prefer_unpadded_slot(): void
    {
        $this->assertSame('/fx/7/type', X32OscAddressMap::fxType(7));
        $this->assertSame(['/fx/7/type', '/fx/07/type'], X32OscAddressMap::fxTypePathCandidates(7));
    }
}
