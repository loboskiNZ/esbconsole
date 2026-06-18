<?php

namespace Tests\Unit;

use App\Models\EffectPackageItemParameter;
use App\Services\X32\X32EffectParameterOscEncoder;
use App\Services\X32\X32OscParameterScale;
use Tests\TestCase;

class X32EffectParameterOscEncoderTest extends TestCase
{
    public function test_logf_limiter_attack_accepts_desk_quantized_read_back(): void
    {
        $encoder = new X32EffectParameterOscEncoder;
        $parameter = new EffectPackageItemParameter([
            'parameter_number' => 5,
            'parameter_name' => 'Attack',
            'value_type' => 'logf',
            'value' => '0.1',
            'min_value' => '0.05',
            'max_value' => '1',
        ]);

        $encoded = $encoder->encode($parameter);
        $deskNormalized = X32OscParameterScale::encodeLogf(0.11, 0.05, 1.0, 72);

        $this->assertTrue($encoder->writeConfirmed($parameter, $encoded, $deskNormalized));
    }

    public function test_enum_parameter_write_confirmed_by_index(): void
    {
        $encoder = new X32EffectParameterOscEncoder;
        $parameter = new EffectPackageItemParameter([
            'parameter_number' => 7,
            'parameter_name' => 'Stereo Link',
            'value_type' => 'enum',
            'value' => 'ON',
            'enum_values_json' => ['OFF', 'ON'],
        ]);

        $encoded = $encoder->encode($parameter);

        $this->assertTrue($encoder->writeConfirmed($parameter, $encoded, 1.0));
        $this->assertSame(1, $encoder->encodeInt($parameter));
    }
}
