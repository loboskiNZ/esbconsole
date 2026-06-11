<?php

namespace App\Services\X32;

class X32OscSceneRecallPacketBuilder
{
    public const OSC_ADDRESS = '/-action/goscene';

    public const OSC_TYPE_TAG = ',i';

    public function oscPath(string $scene): string
    {
        return self::OSC_ADDRESS;
    }

    public function build(string $scene): string
    {
        return $this->encodeGosceneMessage((int) $scene);
    }

    private function encodeGosceneMessage(int $scene): string
    {
        return $this->padOscString(self::OSC_ADDRESS)
            .$this->padOscString(self::OSC_TYPE_TAG)
            .pack('N', $scene);
    }

    private function padOscString(string $value): string
    {
        $value .= "\0";
        $padding = (4 - (strlen($value) % 4)) % 4;

        return $value.str_repeat("\0", $padding);
    }
}
