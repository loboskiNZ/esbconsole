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

    /*
    |--------------------------------------------------------------------------
    | Profile photos
    |--------------------------------------------------------------------------
    |
    | Stored on the local private disk by default. Served only through the
    | authenticated profile photo route — not via public URLs.
    |
    */

    'profile_photo_disk' => env('PORTAL_PROFILE_PHOTO_DISK', 'local'),

    'profile_photo_max_kb' => (int) env('PORTAL_PROFILE_PHOTO_MAX_KB', 5120),

];
