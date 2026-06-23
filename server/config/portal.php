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

    'profile_photo_max_kb' => (int) env('PORTAL_PROFILE_PHOTO_MAX_KB', 25600),

    'profile_photo_display_max_edge' => (int) env('PORTAL_PROFILE_PHOTO_DISPLAY_MAX_EDGE', 960),

    /*
    |--------------------------------------------------------------------------
    | Governed music library (read-only from portal)
    |--------------------------------------------------------------------------
    |
    | When portal and Director share PostgreSQL, leave library_connection null
    | so library models use the default connection. Chart PDFs are served from
    | private storage — never via public URLs.
    |
    */

    'library_connection' => env('PORTAL_LIBRARY_CONNECTION'),

    'library_chart_disk' => env('PORTAL_LIBRARY_CHART_DISK', 'library'),

];
