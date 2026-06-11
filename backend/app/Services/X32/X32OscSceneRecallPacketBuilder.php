<?php

namespace App\Services\X32;

class X32OscSceneRecallPacketBuilder
{
    public function oscPath(string $scene): string
    {
        $sceneNumber = (int) $scene;

        return '/3/scene/'.str_pad((string) $sceneNumber, 2, '0', STR_PAD_LEFT);
    }

    public function build(string $scene): string
    {
        return $this->encodeAddressOnlyMessage($this->oscPath($scene));
    }

    private function encodeAddressOnlyMessage(string $address): string
    {
        return $this->padOscString($address).$this->padOscString(',');
    }

    private function padOscString(string $value): string
    {
        $value .= "\0";
        $padding = (4 - (strlen($value) % 4)) % 4;

        return $value.str_repeat("\0", $padding);
    }
}
