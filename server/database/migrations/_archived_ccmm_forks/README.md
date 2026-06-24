# Archived CCMM fork migrations (server/)

**Status:** Retired ownership — not loaded by `php artisan migrate`.

These files are preserved for forensic audit per PH062. Canonical schema authority is `database/migrations/ccmm/`.

| Archived file | Superseded by |
|---------------|---------------|
| `2026_06_23_130000_create_bands_table.php` | CCMM-01 |
| `2026_06_23_131000_create_band_people_schema.php` | CCMM-03 |
| `2026_06_23_132000_provision_portal_reference_data.php` | CCMM-02 seeds (F2) |
| `2026_06_23_133000_reconcile_users_for_portal_auth.php` | CCMM-04 |
| `2026_06_23_160000_provision_studio_library_read_tables.php` | CCMM-05, CCMM-06 |
| `2026_06_23_220100_provision_studio_song_metadata_tables.php` | CCMM-02 |
| `2026_06_24_120100_provision_studio_song_authoring_fields.php` | CCMM-05 |
| `2026_06_23_120000_create_invite_links_table.php` | Quarantined |
| `2026_06_23_134000_create_invite_link_acceptances_table.php` | Quarantined |
| `2026_06_23_140000_*`, `141000_*` | CCMM-03 (merged) |

Do not delete. Do not run on fresh Cloud.
