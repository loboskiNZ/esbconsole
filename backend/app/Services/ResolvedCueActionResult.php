<?php

namespace App\Services;

readonly class ResolvedCueActionResult
{
    /**
     * @param  list<array{
     *     cue_action_id: int,
     *     sort_order: int,
     *     action_definition_code: string,
     *     action_definition_name: string,
     *     action_type_code: string,
     *     action_type_name: string,
     *     parameters: array<string, string>
     * }>  $actions
     */
    public function __construct(
        public int $cueId,
        public string $songCode,
        public string $cueNumber,
        public string $runtimeIdentity,
        public array $actions,
    ) {}

    public function toArray(): array
    {
        return [
            'cue_id' => $this->cueId,
            'song_code' => $this->songCode,
            'cue_number' => $this->cueNumber,
            'runtime_identity' => $this->runtimeIdentity,
            'actions' => $this->actions,
        ];
    }
}
