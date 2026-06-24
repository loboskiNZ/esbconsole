# PH059 — Cloud Canonical Migration Manifest (CCMM)

Status: Schema authority — **no migrations, DDL, production commands, deploys, or data migration**  
Authority: `docs/DECISION_LOG.md` PH058, `docs/PH058_CLOUD_FIRST_SCHEMA_STABILISATION_PLAN.md`  
Date: 2026-06-24  
Engine: PostgreSQL 16+

---

## 1. Purpose

The **Cloud Canonical Migration Manifest (CCMM)** is the formal schema definition for **shared ESB entities**. It is the reference that both **Cloud Database** and **Live Stage Database** must implement with identical structure before recovery migrations are written.

### Reliability framing (PH058)

Cloud-first canonical schema is a **reliability decision** — not workspace hierarchy.

| Cloud Database | Live Stage Database |
|----------------|---------------------|
| Backup, restore, replication, audit | Rehearsal, performance, offline operation |
| Reference data, long-term history | Console / Ableton runtime execution |
| **Rebuild source** | **Pending offline changes until sync** |

**Cloud can rebuild Live Stage.** **Live Stage can operate without Cloud.**

### CCMM scope

| In scope | Out of scope |
|----------|--------------|
| Shared ESB entity tables (Part A) | Migration PHP files (PH060+) |
| Live Stage superset tables (Part B) | Production execution |
| Future `person_invitations` (Part C) | `invite_links` quarantine |
| Deprecated `server/` forks (Part D) | PH054 checkout/version columns (follow-up) |

---

## 2. Manifest conventions

| Field | Meaning |
|-------|---------|
| **Owner** | Aggregate / domain authority after publish |
| **Sync** | `replicated` = Cloud ↔ Live Stage shared rows; `cloud-only` = Cloud workspace; `live-only` = Live Stage superset |
| **Workspaces** | CS = Cloud Studio, W = Website, LS = Live Stage |
| **PH054** | Future checkout/version relevance — `yes` / `no` / `future` |

Types use PostgreSQL equivalents. `timestamps` = `created_at`, `updated_at` nullable timestamp.

---

## Part A — Shared CCMM entities (Cloud + Live Stage parity)

### A1. `bands`

| Attribute | Value |
|-----------|-------|
| **Domain** | Band / Organisation |
| **Owner** | Band aggregate root |
| **Workspaces** | CS, W, LS |
| **Sync** | replicated |
| **PH054** | no |

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| id | bigint | NO | serial | PK |
| public_id | uuid | NO | — | |
| name | varchar(255) | NO | — | |
| primary_director_musician_id | bigint | YES | — | FK → musicians.id ON DELETE SET NULL |
| created_at | timestamp | YES | — | |
| updated_at | timestamp | YES | — | |

**PK:** `id`  
**FKs:** `primary_director_musician_id` → `musicians(id)`  
**Unique:** `public_id`  
**Indexes:** `bands_public_id_unique`  
**Seed:** Default band row (operator-approved)  
**Files:** none  
**Versioning:** no

---

### A2. `users`

| Attribute | Value |
|-----------|-------|
| **Domain** | Identity |
| **Owner** | Cloud (credentials authoritative) |
| **Workspaces** | CS, LS (director); not created on LS at show time |
| **Sync** | replicated |
| **PH054** | no |

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| id | bigint | NO | serial | PK |
| public_id | uuid | NO | — | PH007 sync identity |
| username | varchar(32) | YES | — | PH047 portal login; lowercase stored |
| person_id | bigint | YES | — | FK → people; portal accounts |
| band_id | bigint | YES | — | FK → bands |
| name | varchar(255) | YES | — | display |
| email | varchar(255) | YES | — | contact only; not login |
| email_verified_at | timestamp | YES | — | PH048A Studio gate |
| password | varchar(255) | NO | — | hashed |
| is_active | boolean | NO | true | |
| remember_token | varchar(100) | YES | — | |
| created_at | timestamp | YES | — | |
| updated_at | timestamp | YES | — | |

**PK:** `id`  
**FKs:** `person_id` → `people(id)` ON DELETE SET NULL; `band_id` → `bands(id)` ON DELETE SET NULL  
**Unique:** `public_id`; `username` (case-insensitive unique index — PH047A)  
**Indexes:** `users_public_id_unique`, `users_username_unique`  
**Nullable rules:** `username` required for portal login accounts; director accounts may use email-era bootstrap until reconciled. `person_id` required for portal users (PH047).  
**Seed:** none in CCMM  
**Files:** none  
**Versioning:** no

---

### A3. `people`

| Attribute | Value |
|-----------|-------|
| **Domain** | Band People / Production Personnel |
| **Owner** | Cloud after publish |
| **Workspaces** | CS, W, LS |
| **Sync** | replicated |
| **PH054** | no |

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| id | bigint | NO | serial | PK |
| public_id | uuid | NO | — | |
| band_id | bigint | NO | — | FK → bands |
| legal_first_name | varchar(255) | NO | — | |
| legal_middle_names | varchar(255) | YES | — | |
| legal_last_name | varchar(255) | NO | — | |
| artistic_name | varchar(255) | YES | — | |
| email | varchar(255) | YES | — | contact |
| phone | varchar(255) | YES | — | |
| gender | varchar(255) | YES | — | |
| pronouns | varchar(255) | YES | — | |
| city | varchar(255) | YES | — | |
| country | varchar(255) | YES | — | |
| dietary_requirements | text | YES | — | |
| notes | text | YES | — | |
| bio | text | YES | — | portal profile |
| profile_photo_path | varchar(255) | YES | — | storage path |
| profile_photo_display_path | varchar(255) | YES | — | derived display |
| created_at | timestamp | YES | — | |
| updated_at | timestamp | YES | — | |

**PK:** `id`  
**FKs:** `band_id` → `bands(id)` ON DELETE RESTRICT  
**Unique:** `public_id`  
**Indexes:** `(band_id, legal_last_name, legal_first_name)`  
**Seed:** none  
**Files:** `profile_photo_path` → Spaces; Person files in `person_files`  
**Versioning:** no

**Child tables (shared):** `person_secure_fields`, `person_files`, `person_iem_settings` — per PH045; identical on Cloud and Live Stage.

---

### A4. `person_secure_fields`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| person_id | bigint | NO | FK → people CASCADE |
| field_type | varchar(64) | NO | |
| encrypted_value | text | NO | |
| encryption_key_context | varchar(128) | NO | |
| last_four_preview | varchar(16) | YES | |
| metadata | json | YES | |
| timestamps | | | |

**Unique:** `(person_id, field_type)`

---

### A5. `person_files`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| person_id | bigint | NO | FK → people CASCADE |
| file_type | varchar(64) | NO | |
| file_path | varchar(255) | NO | managed path |
| original_filename | varchar(255) | NO | |
| mime_type | varchar(255) | YES | |
| size_bytes | bigint | YES | |
| expires_at | timestamp | YES | |
| notes | text | YES | |
| is_public | boolean | NO | false |
| timestamps | | | |

**Indexes:** `(person_id, file_type)`, `is_public`

---

### A6. `person_iem_settings`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| person_id | bigint | NO | FK → people CASCADE |
| name | varchar(255) | NO | template name |
| vocal_level | decimal(5,2) | YES | |
| own_instrument_level | decimal(5,2) | YES | |
| band_level | decimal(5,2) | YES | |
| click_level | decimal(5,2) | YES | |
| tracks_level | decimal(5,2) | YES | |
| reverb_level | decimal(5,2) | YES | |
| ambient_level | decimal(5,2) | YES | |
| notes | text | YES | |
| timestamps | | | |

**Index:** `(person_id, name)` — templates only; not live bus state (PH045)

---

### A7. `musicians`

| Attribute | Value |
|-----------|-------|
| **Domain** | Musician (operational roster) |
| **Owner** | Cloud after publish |
| **Workspaces** | CS, LS |
| **Sync** | replicated |
| **PH054** | no |

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| id | bigint | NO | serial | PK |
| public_id | uuid | NO | — | |
| band_id | bigint | NO | — | FK → bands |
| user_id | bigint | YES | — | FK → users; optional link |
| first_name | varchar(255) | NO | — | |
| last_name | varchar(255) | NO | — | |
| display_name | varchar(255) | NO | — | |
| email | varchar(255) | YES | — | |
| notes | text | YES | — | |
| dietary_preferences | text | YES | — | |
| allergies | text | YES | — | |
| accessibility_notes | text | YES | — | |
| travel_notes | text | YES | — | |
| emergency_contact_notes | text | YES | — | |
| active | boolean | NO | true | |
| timestamps | | | | |

**PK:** `id`  
**FKs:** `band_id` → `bands`; `user_id` → `users` ON DELETE SET NULL  
**Unique:** `public_id`  
**Indexes:** `(band_id, active)`  
**Note:** Musician ≠ Person (PH045-154); mapping follow-up

---

### A8. `musician_band_roles`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| musician_id | bigint | NO | FK → musicians CASCADE |
| role | varchar(64) | NO | enum values per app |
| timestamps | | | |

**Unique:** `(musician_id, role)`  
**Index:** `role`

---

### A9. `instrument_reference`

| Attribute | Value |
|-----------|-------|
| **Domain** | Band People (personnel catalog) |
| **Owner** | Cloud |
| **Workspaces** | CS, W, LS |
| **Sync** | replicated |
| **PH054** | no |

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| id | bigint | NO | serial | PK |
| public_id | uuid | NO | — | |
| slug | varchar(255) | NO | — | **canonical** (PH059) |
| name | varchar(255) | NO | — | |
| family | varchar(255) | YES | — | |
| is_active | boolean | NO | true | |
| timestamps | | | | |

**Unique:** `public_id`, `slug`  
**Index:** `(is_active, name)`  
**Seed:** `InstrumentCatalog` reference rows (operator-approved)

---

### A10. `person_instruments`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| person_id | bigint | NO | FK → people CASCADE |
| instrument_id | bigint | NO | FK → instrument_reference RESTRICT |
| role_label | varchar(255) | YES | |
| is_primary | boolean | NO | false |
| notes | text | YES | |
| timestamps | | | |

**Unique:** `(person_id, instrument_id)`

---

### A11. `instrument_parts`

| Attribute | Value |
|-----------|-------|
| **Domain** | Production Asset |
| **Owner** | Cloud after publish |
| **Workspaces** | CS, LS |
| **Sync** | replicated |
| **PH054** | future |

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| band_id | bigint | NO | FK → bands |
| name | varchar(255) | NO | |
| description | text | YES | |
| active | boolean | NO | true |
| timestamps | | | |

**Unique:** `public_id`  
**Index:** `(band_id, active)`

---

### A12. `songs`

| Attribute | Value |
|-----------|-------|
| **Domain** | Music Library |
| **Owner** | Cloud after publish; peer authoring PH054 |
| **Workspaces** | CS, LS |
| **Sync** | replicated |
| **PH054** | **yes** — checkout/version target |

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| id | bigint | NO | serial | PK |
| public_id | uuid | NO | — | |
| band_id | bigint | NO | — | FK → bands |
| song_code | char(3) | NO | — | PH010.01; unique per band |
| name | varchar(255) | NO | — | title |
| bpm | smallint | YES | — | |
| time_signature_id | bigint | YES | — | FK → time_signatures |
| musical_key_id | bigint | YES | — | FK → musical_keys |
| mood_id | bigint | YES | — | FK → song_moods |
| description | text | YES | — | |
| notes | text | YES | — | |
| director_notes | text | YES | — | |
| status | varchar(255) | NO | `'draft'` | lifecycle; not `lifecycle_state` |
| genre | varchar(100) | YES | — | authoring |
| style | varchar(100) | YES | — | |
| tempo_feel | varchar(100) | YES | — | |
| count_in | smallint | YES | — | |
| mood_intention | text | YES | — | |
| performance_feel | text | YES | — | |
| arrangement_comments | text | YES | — | |
| reference_url | varchar(2048) | YES | — | |
| reference_title | varchar(255) | YES | — | |
| reference_notes | text | YES | — | |
| timestamps | | | | |

**PK:** `id`  
**FKs:** band, time_signature_id, musical_key_id, mood_id — SET NULL on delete for refs  
**Unique:** `public_id`; `(band_id, song_code)`  
**Index:** `(band_id, status)`  
**Versioning:** PH054 future columns: `base_version`, `cloud_version`, `live_stage_version` — **not in CCMM v1**

---

### A13. `song_moods`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| name | varchar(255) | NO | |
| slug | varchar(255) | NO | unique |
| colour_hex | char(7) | NO | |
| accent_colour_hex | char(7) | NO | |
| description | text | YES | |
| sort_order | smallint | NO | 0 |
| active | boolean | NO | true |
| timestamps | | | |

**Seed:** `SongMetadataReferenceSeeder`

---

### A14. `time_signatures`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| label | varchar(255) | NO | unique |
| sort_order | smallint | NO | 0 |
| active | boolean | NO | true |
| timestamps | | | |

**Seed:** `SongMetadataReferenceSeeder`

---

### A15. `musical_keys`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| label | varchar(255) | NO | unique |
| tonic | varchar(4) | NO | |
| mode | varchar(16) | NO | |
| sort_order | smallint | NO | 0 |
| active | boolean | NO | true |
| timestamps | | | |

**Seed:** `SongMetadataReferenceSeeder`

---

### A16. `charts`

| Attribute | Value |
|-----------|-------|
| **Domain** | Music Library / File Asset |
| **Owner** | Cloud (metadata); binary in Spaces |
| **Workspaces** | CS, LS |
| **Sync** | replicated metadata; file cache on LS |
| **PH054** | **yes** |

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| song_id | bigint | NO | FK → songs |
| title | varchar(255) | NO | |
| original_filename | varchar(255) | YES | |
| storage_reference | varchar(255) | NO | Spaces key |
| checksum | varchar(255) | NO | |
| mime_type | varchar(127) | YES | |
| file_size | bigint | YES | |
| notes | text | YES | |
| import_batch_id | bigint | YES | FK → import_batches |
| timestamps | | | |

**Unique:** `public_id`; `(song_id, checksum)`  
**Files:** PDF/binary at `storage_reference`; local cache on Live Stage  
**Parent:** `import_batches` **required on Cloud** before FK valid

---

### A17. `song_instrument_parts`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| song_id | bigint | NO | FK → songs |
| instrument_part_id | bigint | NO | FK → instrument_parts |
| chart_id | bigint | YES | FK → charts |
| notes | text | YES | |
| timestamps | | | |

**Unique:** `public_id`; `(song_id, instrument_part_id)`

---

### A18. `import_batches`

| Attribute | Value |
|-----------|-------|
| **Domain** | Audit / Migration |
| **Owner** | Cloud |
| **Workspaces** | CS, LS |
| **Sync** | replicated |

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| band_id | bigint | YES | FK → bands |
| legacy_setlist_id | varchar(255) | NO | |
| status | varchar(255) | NO | `'dry_run'` |
| manifest_json | json | YES | |
| report_json | json | YES | |
| started_at | timestamp | YES | |
| completed_at | timestamp | YES | |
| initiated_by_user_id | bigint | YES | FK → users |
| timestamps | | | |

**Index:** `(band_id, status)`

---

### A19. `import_entity_mappings`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| import_batch_id | bigint | NO | FK → import_batches CASCADE |
| entity_type | varchar(255) | NO | |
| legacy_key | varchar(255) | NO | |
| entity_id | bigint | YES | |
| public_id | uuid | YES | |
| timestamps | | | |

**Unique:** `(import_batch_id, entity_type, legacy_key)`

---

### A20. `ableton_show_files`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| band_id | bigint | NO | FK → bands |
| name | varchar(255) | NO | |
| storage_reference | varchar(255) | NO | |
| checksum | varchar(255) | NO | |
| notes | text | YES | |
| timestamps | | | |

**Index:** `band_id`  
**Files:** Ableton `.als` in Spaces

---

### A21. `shows`

| Attribute | Value |
|-----------|-------|
| **Domain** | Show |
| **Owner** | Cloud after publish |
| **Workspaces** | CS, LS |
| **Sync** | replicated |
| **PH054** | future |

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| band_id | bigint | NO | FK → bands |
| ableton_show_file_id | bigint | NO | FK → ableton_show_files; unique |
| name | varchar(255) | NO | |
| description | text | YES | |
| lifecycle_state | varchar(255) | NO | `'draft'` |
| timestamps | | | |

**Index:** `(band_id, lifecycle_state)`

---

### A22. `show_playlist_items`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| show_id | bigint | NO | FK → shows |
| song_id | bigint | NO | FK → songs |
| position | integer | NO | |
| ableton_pgm | smallint | YES | |
| timestamps | | | |

**Unique:** `(show_id, position)`; `(show_id, song_id)`  
**Index:** `(show_id, position)`

---

### A23. `performances`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| band_id | bigint | NO | FK → bands |
| show_id | bigint | NO | FK → shows |
| venue | varchar(255) | NO | |
| performance_date | date | NO | |
| status | varchar(255) | NO | `'planned'` |
| notes | text | YES | |
| timestamps | | | |

**Indexes:** `(band_id, status)`; `(show_id, performance_date)`

---

### A24. `performance_assignments`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| performance_id | bigint | NO | FK → performances |
| musician_id | bigint | NO | FK → musicians |
| instrument_part_id | bigint | NO | FK → instrument_parts |
| song_id | bigint | YES | FK → songs |
| cue_id | bigint | YES | FK → cues |
| active | boolean | NO | true |
| timestamps | | | |

**Index:** `(performance_id, active)`

---

### A25. `devices`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| musician_id | bigint | NO | FK → musicians |
| device_name | varchar(255) | NO | |
| device_type | varchar(255) | NO | |
| active | boolean | NO | true |
| timestamps | | | |

**Index:** `(musician_id, active)`

---

### A26. `capabilities`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| musician_id | bigint | NO | FK → musicians |
| instrument_part_id | bigint | NO | FK → instrument_parts |
| timestamps | | | |

**Unique:** `(musician_id, instrument_part_id)`

---

### A27. `assignments`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| musician_id | bigint | NO | FK → musicians |
| instrument_part_id | bigint | NO | FK → instrument_parts |
| active | boolean | NO | true |
| timestamps | | | |

**Indexes:** `(musician_id, active)`; `(instrument_part_id, active)`

---

### A28. `cues`

| Attribute | Value |
|-----------|-------|
| **Domain** | Music Library |
| **PH054** | **yes** — `SSS.CCC` component |

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| song_id | bigint | NO | FK → songs |
| cue_number | char(3) | NO | PH010.01 |
| name | varchar(255) | NO | |
| description | text | YES | |
| notes | text | YES | |
| sequence_order | smallint | NO | 0 |
| timestamps | | | |

**Unique:** `(song_id, cue_number)`  
**Index:** `(song_id, cue_number)`; `(song_id, sequence_order)`

---

### A29. `action_types`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| code | varchar(255) | NO | unique |
| name | varchar(255) | NO | |
| description | text | YES | |
| timestamps | | | |

**Seed:** X32_SCENE, LIGHT_MODE, MUSICIAN_MESSAGE, etc. (per backend seeder)

---

### A30. `action_definitions`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| band_id | bigint | NO | FK → bands |
| action_type_id | bigint | NO | FK → action_types |
| code | varchar(255) | NO | |
| name | varchar(255) | NO | |
| description | text | YES | |
| enabled | boolean | NO | true |
| timestamps | | | |

**Unique:** `(band_id, code)`

---

### A31. `action_parameters`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| action_definition_id | bigint | NO | FK → action_definitions CASCADE |
| parameter_name | varchar(255) | NO | |
| parameter_value | text | NO | |
| timestamps | | | |

**Unique:** `(action_definition_id, parameter_name)`

---

### A32. `cue_actions`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| cue_id | bigint | NO | FK → cues |
| action_definition_id | bigint | NO | FK → action_definitions |
| sort_order | integer | NO | 0 |
| enabled | boolean | NO | true |
| timestamps | | | |

**Unique:** `(cue_id, action_definition_id)`  
**Index:** `(cue_id, sort_order, id)`

---

### A33. `snippets`

| Attribute | Value |
|-----------|-------|
| **Domain** | Music Library (PH027/PH028) |
| **PH054** | **yes** |

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | |
| song_instrument_part_id | bigint | NO | FK → song_instrument_parts |
| cue_id | bigint | NO | FK → cues |
| title | varchar(255) | NO | |
| storage_reference | varchar(255) | NO | |
| checksum | varchar(255) | NO | |
| notes | text | YES | |
| source_type | varchar(32) | NO | `'chart_crop'` |
| source_snippet_id | bigint | YES | FK → snippets |
| source_chart_id | bigint | YES | FK → charts |
| freshness_state | varchar(32) | NO | `'current'` |
| is_active | boolean | NO | true |
| annotation_storage_reference | varchar(255) | YES | |
| markup_storage_reference | varchar(255) | YES | |
| rendered_storage_reference | varchar(255) | YES | |
| source_metadata | json | YES | |
| chart_revision_at_creation | varchar(255) | YES | |
| timestamps | | | |

**Partial unique index:** `(song_instrument_part_id, cue_id) WHERE is_active IS TRUE`  
**Files:** snippet binaries in Spaces; local cache on Live Stage

---

### A34. `venues` — **shared canonical** (PH059)

| Attribute | Value |
|-----------|-------|
| **Domain** | Operations / Tour |
| **Owner** | Cloud after publish |
| **Workspaces** | CS, W, LS |
| **Sync** | replicated |
| **Classification** | shared canonical entity |

Per `2026_06_21_100000_create_venues_table.php` — full column set unchanged.

---

### A35. `festivals` — **shared canonical** (PH059)

| Attribute | Value |
|-----------|-------|
| **Domain** | Operations / Tour |
| **Owner** | Cloud after publish |
| **Workspaces** | CS, W, LS |
| **Sync** | replicated |
| **Classification** | shared canonical entity |

Per `2026_06_22_100000_create_festivals_table.php` — full column set unchanged.

---

## Part B — Live Stage superset (NOT on Cloud Database)

These tables are **defined** for Live Stage completeness but **excluded from Cloud Database** per PH059.

| Table | Why Live Stage only |
|-------|---------------------|
| `runtime_events` | Live timeline/event ingress — not cloud-canonical during performance |
| `runtime_action_plans` | Execution planning state |
| `runtime_action_items` | Per-action execution state |
| `runtime_audit_records` | Show-day execution audit |
| `runtime_dispatches` | Dispatch orchestration |
| `runtime_dispatch_items` | Dispatch line items |
| `soundchecks` | Pre-show validation session state |
| `readiness_records` | Performance readiness gate — human process, local |
| `console_learning_snapshots` | X32 OSC learning — PH043 |
| `show_console_baselines` | X32 console baseline per show |
| `effects` | PH044 X32 algorithm catalog — console domain |
| `effect_parameters` | PH044 |
| `effect_definitions` | PH044 legacy/reference |
| `effect_packages` | PH044 |
| `effect_package_items` | PH044 |
| `effect_package_item_parameters` | PH044 |
| `effect_package_item_target_sections` | PH044 |
| `effect_package_types` | PH044 |
| `effect_library_items` | PH044 reference |
| `effect_library_parameters` | PH044 reference |
| `song_effect_assignments` | PH044 show/song routing |
| `integration_devices` | Show-day device connection state |
| `integration_connection_profiles` | Bridge connection profiles |
| `performance_device_assignments` | FK → `integration_devices`; show-day binding |

**Laravel infra (both environments, not ESB domain):** `cache`, `cache_locks`, `sessions`, `password_reset_tokens`, `jobs`, `job_batches`, `failed_jobs` — present on both; not CCMM shared ESB entities.

**Spatie RBAC:** `permissions`, `roles`, `model_has_*`, `role_has_permissions` — Cloud workspace; Live Stage may mirror if director auth shares Cloud model.

---

## Part C — Invite model

### Quarantined (not canonical)

| Table | Status |
|-------|--------|
| `invite_links` | **Legacy drift** — no `person_id`; open shared links |
| `invite_link_acceptances` | **Quarantined** with `invite_links` |

Do not migrate. Do not include in CCMM migrations.

### Canonical future (Cloud workspace — PH060+)

**`person_invitations`** (name per PH047/PH048A):

| Column | Notes |
|--------|-------|
| id | PK |
| public_id | uuid |
| person_id | FK → people **NOT NULL** — Person exists before invite |
| token_hash | unique |
| expires_at | |
| revoked_at | nullable |
| accepted_at | nullable |
| created_by_user_id | FK → users |
| status | draft/sent/accepted/expired/revoked |
| timestamps | |

**Rules:** Person-first (PH047-168); single-use; no open shared invite as canonical onboarding model.

---

## Part D — Deprecated `server/` shared migrations

Do not use for CCMM implementation. Replace with governed Cloud-first migrations (PH060+).

| Deprecated file | Replacement |
|-----------------|-------------|
| `2026_06_23_130000_create_bands_table.php` | CCMM A1 |
| `2026_06_23_131000_create_band_people_schema.php` | CCMM A3–A6, A10 |
| `2026_06_23_133000_reconcile_users_for_portal_auth.php` | CCMM A2 |
| `2026_06_23_160000_provision_studio_library_read_tables.php` | CCMM A11–A17 |
| `2026_06_23_220100_provision_studio_song_metadata_tables.php` | CCMM A12–A15 |
| `2026_06_24_120100_provision_studio_song_authoring_fields.php` | CCMM A12 columns |
| `2026_06_23_120000_create_invite_links_table.php` | Part C `person_invitations` |
| `2026_06_23_134000_create_invite_link_acceptances_table.php` | quarantined |

---

## Part E — CCMM migration group order (documentation only)

| Group | Tables | Dependency |
|-------|--------|------------|
| CCMM-1 | bands | — |
| CCMM-2 | instrument_reference, song_moods, time_signatures, musical_keys | bands |
| CCMM-3 | people, person_* children | bands |
| CCMM-4 | users | bands, people |
| CCMM-5 | musicians, musician_band_roles, devices | bands, users |
| CCMM-6 | instrument_parts, capabilities, assignments | bands, musicians |
| CCMM-7 | import_batches | bands, users |
| CCMM-8 | songs | bands, refs |
| CCMM-9 | cues, charts, song_instrument_parts, snippets | songs |
| CCMM-10 | action_types, action_definitions, action_parameters, cue_actions | bands, cues |
| CCMM-11 | ableton_show_files, shows, show_playlist_items | bands, songs |
| CCMM-12 | performances, performance_assignments | shows, musicians |
| CCMM-13 | venues, festivals | bands |
| CCMM-14 | person_invitations | people, users (Cloud workspace) |

Live Stage applies CCMM-1–13 identically, then **superset** migrations (Part B).

---

## Part F — Entity index

| # | Table | Part |
|---|-------|------|
| 1 | bands | A |
| 2 | users | A |
| 3 | people | A |
| 4 | person_secure_fields | A |
| 5 | person_files | A |
| 6 | person_iem_settings | A |
| 7 | musicians | A |
| 8 | musician_band_roles | A |
| 9 | instrument_reference | A |
| 10 | person_instruments | A |
| 11 | instrument_parts | A |
| 12 | songs | A |
| 13 | song_moods | A |
| 14 | time_signatures | A |
| 15 | musical_keys | A |
| 16 | charts | A |
| 17 | song_instrument_parts | A |
| 18 | import_batches | A |
| 19 | import_entity_mappings | A |
| 20 | ableton_show_files | A |
| 21 | shows | A |
| 22 | show_playlist_items | A |
| 23 | performances | A |
| 24 | performance_assignments | A |
| 25 | devices | A |
| 26 | capabilities | A |
| 27 | assignments | A |
| 28 | cues | A |
| 29 | action_types | A |
| 30 | action_definitions | A |
| 31 | action_parameters | A |
| 32 | cue_actions | A |
| 33 | snippets | A |
| 34 | venues | A |
| 35 | festivals | A |

**Shared CCMM entities: 35 tables** (+ `person_invitations` future Cloud-only)

---

End of PH059 CCMM — schema authority document only
