<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Band;
use App\Services\BandContext;
use Illuminate\Database\Eloquent\Model;

trait ResolvesBand
{
    protected function band(): Band
    {
        $band = app(BandContext::class)->resolve();

        abort_unless($band, 404, 'No band configured.');

        return $band;
    }

    protected function ensureBandOwns(Model $model, string $foreignKey = 'band_id'): void
    {
        abort_unless((int) $model->{$foreignKey} === $this->band()->id, 404);
    }

    protected function ensureSongBelongsToBand(Model $song): void
    {
        $this->ensureBandOwns($song);
    }

    protected function ensureShowBelongsToBand(Model $show): void
    {
        $this->ensureBandOwns($show);
    }
}
