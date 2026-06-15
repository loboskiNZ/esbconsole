<?php

namespace App\DataTransferObjects\Console;

/**
 * View model for one input channel strip in the Virtual X32 Console workspace.
 */
readonly class VirtualConsoleStrip
{
    public function __construct(
        public int $id,
        public int $channelNumber,
        public string $oscChannelNumber,
        public string $name,
        public int $color,
        public ?string $icon,
        public float $meterLevel,
        public bool $muted,
        public ?float $gain,
        public bool $phantom48v,
        public bool $gateOn,
        public bool $compressorOn,
        public bool $eqOn,
        public bool $sendsOpen,
        public float $pan,
        public bool $linked,
        public bool $mainLr,
        public float $faderLevel,
        public bool $isLocallyControlled = false,
        /** @var array<string, mixed> */
        public array $lastConfirmedState = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channelNumber' => $this->channelNumber,
            'oscChannelNumber' => $this->oscChannelNumber,
            'name' => $this->name,
            'color' => $this->color,
            'icon' => $this->icon,
            'meterLevel' => $this->meterLevel,
            'muted' => $this->muted,
            'gain' => $this->gain,
            'phantom48v' => $this->phantom48v,
            'gateOn' => $this->gateOn,
            'compressorOn' => $this->compressorOn,
            'eqOn' => $this->eqOn,
            'sendsOpen' => $this->sendsOpen,
            'pan' => $this->pan,
            'linked' => $this->linked,
            'mainLr' => $this->mainLr,
            'faderLevel' => $this->faderLevel,
            'isLocallyControlled' => $this->isLocallyControlled,
            'lastConfirmedState' => $this->lastConfirmedState,
        ];
    }
}
