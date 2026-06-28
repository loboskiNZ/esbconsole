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
    | Studio role guard (legacy Spatie-compatible roles table)
    |--------------------------------------------------------------------------
    |
    | Production may share a legacy roles table that requires guard_name.
    | Studio system roles use this guard when inserting or backfilling rows.
    |
    */

    'studio_role_guard' => env('PORTAL_STUDIO_ROLE_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Profile photos
    |--------------------------------------------------------------------------
    |
    | New uploads target the media disk (esb-media). Legacy local files remain
    | readable via dual-read fallback. Served only through authenticated routes.
    |
    */

    'media_disk' => env('PORTAL_MEDIA_DISK', 'media'),

    'profile_photo_disk' => env('PORTAL_PROFILE_PHOTO_DISK', 'media'),

    'profile_photo_max_kb' => (int) env('PORTAL_PROFILE_PHOTO_MAX_KB', 25600),

    'profile_photo_display_max_edge' => (int) env('PORTAL_PROFILE_PHOTO_DISPLAY_MAX_EDGE', 960),

    /*
    |--------------------------------------------------------------------------
    | Band profile assets
    |--------------------------------------------------------------------------
    |
    | New uploads target the media disk (esb-media). Legacy local files remain
    | readable via dual-read fallback. Served only through authenticated routes.
    |
    */

    'band_asset_disk' => env('PORTAL_BAND_ASSET_DISK', 'media'),

    'band_logo_max_kb' => (int) env('PORTAL_BAND_LOGO_MAX_KB', 5120),

    'band_photo_max_kb' => (int) env('PORTAL_BAND_PHOTO_MAX_KB', 25600),

    /*
    |--------------------------------------------------------------------------
    | Governed music library (read-only from portal)
    |--------------------------------------------------------------------------
    |
    | When portal and Director share PostgreSQL, set PORTAL_LIBRARY_CONNECTION=library
    | and leave LIBRARY_DB_* unset so the library connection uses portal DB creds.
    | Chart PDFs are served from private storage — never via public URLs.
    |
    */

    'library_connection' => env('PORTAL_LIBRARY_CONNECTION', 'library'),

    'library_chart_disk' => env('PORTAL_LIBRARY_CHART_DISK', 'library'),

    /*
    |--------------------------------------------------------------------------
    | Private chart PDF root (shared Forge storage — never public web root)
    |--------------------------------------------------------------------------
    |
    | Must point to .../storage/app/library — not .../library/charts.
    | storage_reference values already include the charts/ prefix.
    |
    */

    'library_storage_root' => env('PORTAL_LIBRARY_STORAGE_ROOT'),

    /*
    |--------------------------------------------------------------------------
    | Song assets (audio / MIDI library files on esb-media)
    |--------------------------------------------------------------------------
    */

    'song_asset_max_kb' => (int) env('PORTAL_SONG_ASSET_MAX_KB', 153600),

    /*
    |--------------------------------------------------------------------------
    | Show setlist PDF generation
    |--------------------------------------------------------------------------
    |
    | Generated setlist PDFs are stored on the media disk (esb-media). The DOCX
    | template is filled server-side and converted with LibreOffice headless.
    |
    */

    'setlist_template_path' => env(
        'PORTAL_SETLIST_TEMPLATE_PATH',
        dirname(base_path()).'/templates/esb_setlist_template_tagged.docx',
    ),

    'setlist_pdf_binary' => env('PORTAL_SETLIST_PDF_BINARY'),

    'setlist_runtime_home' => env('PORTAL_SETLIST_RUNTIME_HOME', '/home/forge'),

    'setlist_temp_path' => env('PORTAL_SETLIST_TEMP_PATH'),

];
