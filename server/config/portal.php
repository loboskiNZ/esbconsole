<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Band Portal tenant
    |--------------------------------------------------------------------------
    |
    | The Band Portal operates within a single band context. The band record is
    | provisioned by migration — not seeder — and referenced by Person/User rows.
    |
    */

    'band_id' => (int) env('PORTAL_BAND_ID', 1),

];
