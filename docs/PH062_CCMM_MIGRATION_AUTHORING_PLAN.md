# PH062 — CCMM Migration Authoring Plan

Status: **Blueprint only** — no migration files, production mutation, DDL, deploys, schema changes, or data migration  
Authority: PH059 CCMM (sole schema authority), PH060, PH061, PH061A  
Date: 2026-06-24

---

## Core principle

The CCMM is the **sole schema authority**. No migration folder, application implementation, or production database is authoritative for shared entities.

```text
CCMM (PH059)
    ↓
Migration Authoring Plan (PH062)
    ↓
Migration Packages (CCMM-00 … CCMM-12)
    ↓
Cloud Database (fresh isolated cluster — PH056 Path B)
    ↓
Live Stage Parity (CCMM identical + LS-EXT superset)
```

**PH062 defines the blueprint.** **PH063** authors migration PHP files and executes against approved targets.

---

## 1. Migration ownership matrix

Ownership values: **Shared CCMM** · **Cloud Extension** · **Live Stage Extension** · **Runtime Only** · **Quarantined**

### 1.1 Identity & people

| Entity | Package | Ownership |
|--------|---------|-----------|
| `users` | CCMM-04 | Shared CCMM |
| `people` | CCMM-03 | Shared CCMM |
| `person_secure_fields` | CCMM-03 | Shared CCMM |
| `person_files` | CCMM-03 | Shared CCMM |
| `person_iem_settings` | CCMM-03 | Shared CCMM |
| `person_instruments` | CCMM-03 | Shared CCMM |
| `musicians` | CCMM-04 | Shared CCMM |
| `musician_band_roles` | CCMM-04 | Shared CCMM |
| `person_invitations` | CCMM-11 | Cloud Extension |

### 1.2 Bands & reference

| Entity | Package | Ownership |
|--------|---------|-----------|
| `bands` | CCMM-01, CCMM-04 (FK) | Shared CCMM |
| `instrument_reference` | CCMM-02 | Shared CCMM |
| `song_moods` | CCMM-02 | Shared CCMM |
| `time_signatures` | CCMM-02 | Shared CCMM |
| `musical_keys` | CCMM-02 | Shared CCMM |

### 1.3 Music library

| Entity | Package | Ownership |
|--------|---------|-----------|
| `songs` | CCMM-05 | Shared CCMM |
| `cues` | CCMM-05 | Shared CCMM |
| `instrument_parts` | CCMM-05 | Shared CCMM |
| `song_instrument_parts` | CCMM-06 | Shared CCMM |
| `snippets` | CCMM-06 | Shared CCMM — **music** chart snippets (PH027) |

### 1.4 Charts & import

| Entity | Package | Ownership |
|--------|---------|-----------|
| `import_batches` | CCMM-06 | Shared CCMM |
| `import_entity_mappings` | CCMM-06 | Shared CCMM |
| `charts` | CCMM-06 | Shared CCMM |

### 1.5 Shows & performances

| Entity | Package | Ownership |
|--------|---------|-----------|
| `ableton_show_files` | CCMM-08 | Shared CCMM |
| `shows` | CCMM-08 | Shared CCMM |
| `show_playlist_items` | CCMM-08 | Shared CCMM |
| `performances` | CCMM-08 | Shared CCMM |
| `performance_assignments` | CCMM-08 | Shared CCMM |

### 1.6 Actions

| Entity | Package | Ownership |
|--------|---------|-----------|
| `action_types` | CCMM-07 | Shared CCMM |
| `action_definitions` | CCMM-07 | Shared CCMM |
| `action_parameters` | CCMM-07 | Shared CCMM |
| `cue_actions` | CCMM-07 | Shared CCMM |
| `mix_moves` | CCMM-12 (placeholder) | Shared CCMM — **schema not defined**; blocked until M5 |

### 1.7 Devices & assignments

| Entity | Package | Ownership |
|--------|---------|-----------|
| `devices` | CCMM-09 | Shared CCMM |
| `capabilities` | CCMM-09 | Shared CCMM |
| `assignments` | CCMM-09 | Shared CCMM |

### 1.8 Venues & festivals

| Entity | Package | Ownership |
|--------|---------|-----------|
| `venues` | CCMM-10 | Shared CCMM |
| `festivals` | CCMM-10 | Shared CCMM |

### 1.9 Effects (PH061A — CCMM-12)

| Entity | Package | Ownership |
|--------|---------|-----------|
| `effect_definitions` | CCMM-12 | Shared CCMM |
| `effect_packages` | CCMM-12 | Shared CCMM |
| `effect_package_items` | CCMM-12 | Shared CCMM |
| `song_effect_assignments` | CCMM-12 | Shared CCMM |
| `effects` | CCMM-12 | Shared CCMM — algorithm reference catalogue |
| `effect_parameters` | CCMM-12 | Shared CCMM |
| `effect_package_types` | CCMM-12 | Shared CCMM |
| `effect_package_item_parameters` | CCMM-12 | Shared CCMM |
| `effect_package_item_target_sections` | CCMM-12 | Shared CCMM |
| `effect_library_items` | — | **Operator Decision** — merge into `effects` or quarantine |
| `effect_library_parameters` | — | **Operator Decision** — merge or quarantine |

### 1.10 Console baselines (PH061A — CCMM-12)

| Entity | Package | Ownership |
|--------|---------|-----------|
| `show_console_baselines` | CCMM-12 | Shared CCMM |
| `console_learning_snapshots` | LS-EXT-03 | Live Stage Extension |
| Channels, buses, routing, DCAs (conceptual) | — | **JSON inside `show_console_baselines.baseline_json`** — not separate tables |

### 1.11 Infrastructure & recovery

| Entity | Package | Ownership |
|--------|---------|-----------|
| `cache`, `cache_locks` | CCMM-00 | Cloud Extension |
| `jobs`, `job_batches`, `failed_jobs` | CCMM-00 | Cloud Extension |
| `sessions`, `password_reset_tokens` | CCMM-00 | Cloud Extension |
| `cloud_recovery_entity_map` | RECOVERY | Cloud Extension — recovery audit only |
| `permission_tables` (Spatie) | LS-EXT-06 | Live Stage Extension — default |

### 1.12 Live Stage extension & runtime

| Entity | Package | Ownership |
|--------|---------|-----------|
| `integration_devices` | LS-EXT-01 | Live Stage Extension |
| `integration_connection_profiles` | LS-EXT-01 | Live Stage Extension |
| `performance_device_assignments` | LS-EXT-02 | Live Stage Extension |
| `soundchecks` | LS-EXT-05 | Live Stage Extension |
| `readiness_records` | LS-EXT-05 | Live Stage Extension |
| `runtime_events` | LS-EXT-04 | Runtime Only |
| `runtime_action_plans` | LS-EXT-04 | Runtime Only |
| `runtime_action_items` | LS-EXT-04 | Runtime Only |
| `runtime_audit_records` | LS-EXT-04 | Runtime Only |
| `runtime_dispatches` | LS-EXT-04 | Runtime Only |
| `runtime_dispatch_items` | LS-EXT-04 | Runtime Only |
| `live_fader_state` | — | Runtime Only — not persisted as canonical row |
| `live_meter_state` | — | Runtime Only |
| `live_mute_state` | — | Runtime Only |
| `live_connection_state` | — | Runtime Only |
| `live_transport_state` | — | Runtime Only — Ableton authority |
| `live_heartbeat_state` | — | Runtime Only |

### 1.13 Quarantined

| Entity | Package | Ownership |
|--------|---------|-----------|
| `invite_links` | — | Quarantined |
| `invite_link_acceptances` | — | Quarantined |

**PH061A supersedes PH059 Part B** for `show_console_baselines` and effect package tables — reclassified from Live Stage superset to **Shared CCMM** in CCMM-12.

---

## 2. Migration package definitions

Canonical packages. Column definitions remain **PH059 Part A** authority; CCMM-12 columns follow **PH061A** + existing `backend/ph044_*` reference material.

### CCMM-00 — Infrastructure

| Field | Value |
|-------|-------|
| **Purpose** | Laravel operational tables required for Cloud Studio |
| **Entities** | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` |
| **Dependencies** | None |
| **Seeds** | None |

### CCMM-01 — Foundation

| Field | Value |
|-------|-------|
| **Purpose** | Band aggregate root |
| **Entities** | `bands` (`primary_director_musician_id` nullable until CCMM-04) |
| **Dependencies** | CCMM-00 |
| **Seeds** | Default band row (operator-approved) |

### CCMM-02 — Reference data

| Field | Value |
|-------|-------|
| **Purpose** | Global reference catalogues for music library |
| **Entities** | `instrument_reference`, `song_moods`, `time_signatures`, `musical_keys` |
| **Dependencies** | CCMM-01 |
| **Seeds** | `InstrumentCatalog`, `SongMetadataReferenceSeeder` |

### CCMM-03 — People

| Field | Value |
|-------|-------|
| **Purpose** | Band people and production personnel |
| **Entities** | `people`, `person_secure_fields`, `person_files`, `person_iem_settings`, `person_instruments` |
| **Dependencies** | CCMM-01 |
| **Seeds** | None |

### CCMM-04 — Identity & roster

| Field | Value |
|-------|-------|
| **Purpose** | Portal identity and musician roster |
| **Entities** | `users` (merged CCMM schema incl. `public_id`), `musicians`, `musician_band_roles`; `bands.primary_director_musician_id` FK |
| **Dependencies** | CCMM-01, CCMM-03 |
| **Seeds** | None |

### CCMM-05 — Music library

| Field | Value |
|-------|-------|
| **Purpose** | Song aggregate and cue identity |
| **Entities** | `songs`, `cues`, `instrument_parts` |
| **Dependencies** | CCMM-01, CCMM-02 |
| **Seeds** | None |

### CCMM-06 — Charts & import audit

| Field | Value |
|-------|-------|
| **Purpose** | Chart assets, import provenance, music snippets |
| **Entities** | `import_batches`, `import_entity_mappings`, `charts`, `song_instrument_parts`, `snippets` |
| **Dependencies** | CCMM-05; `import_batches` before `charts` FK |
| **Seeds** | None |

### CCMM-07 — Actions

| Field | Value |
|-------|-------|
| **Purpose** | Cue action definitions and parameters |
| **Entities** | `action_types`, `action_definitions`, `action_parameters`, `cue_actions` |
| **Dependencies** | CCMM-05 (`cues`) |
| **Seeds** | Action type catalogue |

### CCMM-08 — Shows & performances

| Field | Value |
|-------|-------|
| **Purpose** | Show aggregate, playlist, performance roster |
| **Entities** | `ableton_show_files`, `shows`, `show_playlist_items`, `performances`, `performance_assignments` |
| **Dependencies** | CCMM-01, CCMM-05, CCMM-04 (`musicians`) |
| **Seeds** | None |

### CCMM-09 — Devices & assignments

| Field | Value |
|-------|-------|
| **Purpose** | Musician devices and instrument assignments |
| **Entities** | `devices`, `capabilities`, `assignments` |
| **Dependencies** | CCMM-04, CCMM-05 |
| **Seeds** | None |

### CCMM-10 — Venues & festivals

| Field | Value |
|-------|-------|
| **Purpose** | Production venue metadata |
| **Entities** | `venues`, `festivals` |
| **Dependencies** | CCMM-01 |
| **Seeds** | None |

### CCMM-11 — Invitations

| Field | Value |
|-------|-------|
| **Purpose** | Person-first onboarding (PH048B) — Cloud workspace only |
| **Entities** | `person_invitations` |
| **Dependencies** | CCMM-03, CCMM-04 |
| **Seeds** | None |

### CCMM-12 — X32 console configuration

| Field | Value |
|-------|-------|
| **Purpose** | Musical FX packages, song assignments, show console baselines — show prep assets |
| **Entities** | `effect_definitions`, `effect_packages`, `effect_package_items`, `song_effect_assignments`, `effects`, `effect_parameters`, `effect_package_types`, `effect_package_item_parameters`, `effect_package_item_target_sections`, `show_console_baselines`; **`mix_moves` placeholder** (no DDL until M5 schema) |
| **Dependencies** | CCMM-05 (`songs`), CCMM-08 (`shows`) |
| **Seeds** | `EffectsAlgorithmReferenceSeeder` (effects catalogue); `effect_package_types` reference |

**Explicitly excluded from CCMM-12:** runtime state, OSC live values, telemetry, connection heartbeats, `console_learning_snapshots`, channel/bus/routing as normalized tables.

### RECOVERY — Recovery audit

| Field | Value |
|-------|-------|
| **Purpose** | ID remap for governed data import (PH061 §5.2) |
| **Entities** | `cloud_recovery_entity_map` |
| **Dependencies** | CCMM-00 |
| **Seeds** | None |

### LS-EXT — Live Stage extension (post-parity)

| Package | Entities |
|---------|----------|
| LS-EXT-01 | `integration_devices`, `integration_connection_profiles` |
| LS-EXT-02 | `performance_device_assignments` |
| LS-EXT-03 | `console_learning_snapshots` |
| LS-EXT-04 | `runtime_*` domain tables |
| LS-EXT-05 | `soundchecks`, `readiness_records` |
| LS-EXT-06 | Spatie `permission_tables` (default) |

---

## 3. Cloud build manifest

Package execution order for fresh Cloud Database. **B*** labels are build-phase identifiers; each maps 1:1 to a CCMM package.

| Phase | Package | Prerequisites | Validation checkpoint | Rollback boundary |
|-------|---------|---------------|----------------------|-------------------|
| **B0** | CCMM-00 Infrastructure | Empty database | Laravel infra tables exist | Roll back B0 only |
| **B1** | CCMM-01 Foundation | B0 | `bands` exists; `public_id` unique | Roll back B1–Bn |
| **B2** | CCMM-02 Reference data | B1 | Reference tables + seeds ≥1 row each | Roll back B2–Bn |
| **B3** | CCMM-03 People | B1 | `people` FK to bands | Roll back B3–Bn |
| **B4** | CCMM-04 Identity & roster | B1, B3 | `users.public_id` present; musicians FK | Roll back B4–Bn |
| **B5** | CCMM-05 Music library | B1, B2 | `songs` + `cues` FK graph | Roll back B5–Bn |
| **B6** | CCMM-06 Charts & import | B5 | `charts.import_batch_id` FK valid | Roll back B6–Bn |
| **B7** | CCMM-07 Actions | B5 | `action_types` seeded | Roll back B7–Bn |
| **B8** | CCMM-08 Shows & performances | B1, B4, B5 | Show playlist FK chain | Roll back B8–Bn |
| **B9** | CCMM-09 Devices & assignments | B4, B5 | Device → musician FK | Roll back B9–Bn |
| **B10** | CCMM-10 Venues & festivals | B1 | Venues FK to bands | Roll back B10–Bn |
| **B11** | CCMM-11 Invitations | B3, B4 | Cloud-only table present | Roll back B11 only |
| **B12** | CCMM-12 X32 console | B5, B8 | Effect tables + baselines FK | Roll back B12 only |
| **BR** | RECOVERY entity map | B0 | Audit table exists | Independent |

**Recommended execution order:**

```text
B0 → B1 → B2 → B3 → B4 → B5 → B6 → B7 → B8 → B9 → B10 → B12 → BR → B11
```

B12 before B11: console/effects prep does not depend on invitations. B11 last — Cloud workspace only.

**Gate 3 validation (PH061):** after B10 (+ B12 if in scope), assert **35 core CCMM tables** (+ CCMM-12 tables if B12 run) + zero quarantine tables.

**Rollback rule:** Never roll back across **BR** if data import batches recorded — truncate via `cloud_recovery_entity_map.batch_id` per PH061 §9.

---

## 4. Migration retirement matrix

**Rule:** Retire **ownership**, not **history**. No deletion. No rewriting Git history.

### 4.1 `server/database/migrations/`

| File | Classification | Superseded by |
|------|----------------|---------------|
| `0001_01_01_000000_create_users_table` | Retired Ownership (users portion) | CCMM-04 `users` |
| `0001_01_01_000000_create_users_table` | Canonical Source Material (sessions, password_reset_tokens) | CCMM-00 |
| `0001_01_01_000001_create_cache_table` | Canonical Source Material | CCMM-00 |
| `0001_01_01_000002_create_jobs_table` | Canonical Source Material | CCMM-00 |
| `2026_06_23_120000_create_invite_links_table` | Quarantined | CCMM-11 `person_invitations` |
| `2026_06_23_130000_create_bands_table` | Retired Ownership | CCMM-01 |
| `2026_06_23_131000_create_band_people_schema` | Retired Ownership | CCMM-03 |
| `2026_06_23_132000_provision_portal_reference_data` | Retired Ownership (seed logic) | CCMM-02 seeds |
| `2026_06_23_133000_reconcile_users_for_portal_auth` | Retired Ownership | CCMM-04 |
| `2026_06_23_134000_create_invite_link_acceptances_table` | Quarantined | — |
| `2026_06_23_140000_add_profile_identity_fields_to_people` | Retired Ownership | CCMM-03 (merged) |
| `2026_06_23_141000_add_profile_photo_display_path_to_people` | Retired Ownership | CCMM-03 (merged) |
| `2026_06_23_160000_provision_studio_library_read_tables` | Retired Ownership | CCMM-05, CCMM-06 |
| `2026_06_23_161000_provision_studio_library_storage_directories` | Cloud Extension (non-DB) | Retain as deploy helper |
| `2026_06_23_162000_open_studio_library_storage_for_operator_sync` | Cloud Extension (non-DB) | Retain as deploy helper |
| `2026_06_23_220100_provision_studio_song_metadata_tables` | Retired Ownership | CCMM-02 |
| `2026_06_24_120100_provision_studio_song_authoring_fields` | Retired Ownership | CCMM-05 (merged) |

**Archive target:** `server/database/migrations/_archived_ccmm_forks/` — excluded from migrate path.

### 4.2 `backend/database/migrations/` — shared entities (retired ownership)

| File | Classification | Superseded by |
|------|----------------|---------------|
| `0001_01_01_000000_create_users_table` | Retired Ownership | CCMM-04 |
| `0001_01_01_000001_create_cache_table` | Retired Ownership | CCMM-00 |
| `0001_01_01_000002_create_jobs_table` | Retired Ownership | CCMM-00 |
| `2026_06_10_140000_m2_create_bands_table` | Retired Ownership | CCMM-01 |
| `2026_06_10_140100_m6_create_songs_table` | Retired Ownership | CCMM-05 |
| `2026_06_10_140200_m8_create_shows_and_playlist_tables` | Retired Ownership | CCMM-08 |
| `2026_06_11_100000_m3_create_musicians_and_devices_tables` | Retired Ownership (musicians) | CCMM-04 |
| `2026_06_11_100100_m4_create_instrument_parts_and_capabilities_tables` | Retired Ownership | CCMM-05, CCMM-09 |
| `2026_06_11_100200_m5_create_assignments_table` | Retired Ownership | CCMM-09 |
| `2026_06_11_100300_expand_songs_table` | Retired Ownership | CCMM-05 (merged) |
| `2026_06_11_100400_m7_create_cues_table` | Retired Ownership | CCMM-05 |
| `2026_06_11_100500_create_song_instrument_parts_table` | Retired Ownership | CCMM-06 |
| `2026_06_11_100600_m9_create_charts_and_snippets_tables` | Retired Ownership | CCMM-06 |
| `2026_06_11_110000_create_ableton_show_files_table` | Retired Ownership | CCMM-08 |
| `2026_06_11_110100_expand_shows_table` | Retired Ownership | CCMM-08 (merged) |
| `2026_06_11_110200_formalise_show_playlist_items` | Retired Ownership | CCMM-08 (merged) |
| `2026_06_11_110300_create_performances_table` | Retired Ownership | CCMM-08 |
| `2026_06_11_110400_create_performance_assignments_table` | Retired Ownership | CCMM-08 |
| `2026_06_18_100000_create_import_batch_tables` | Retired Ownership | CCMM-06 |
| `2026_06_17_100000_ph028_snippet_domain_schema` | Retired Ownership | CCMM-06 |
| `2026_06_19_100000_add_user_id_to_musicians_table` | Retired Ownership | CCMM-04 (merged) |
| `2026_06_20_100000_add_band_people_roles_and_profile_fields` | Retired Ownership | CCMM-03 (merged) |
| `2026_06_21_100000_create_venues_table` | Retired Ownership | CCMM-10 |
| `2026_06_22_100000_create_festivals_table` | Retired Ownership | CCMM-10 |
| `2026_06_23_110000_create_band_people_schema` | Retired Ownership | CCMM-03 |
| `2026_06_23_220000_create_song_metadata_reference_tables` | Retired Ownership | CCMM-02 |
| `2026_06_24_100000_add_folder_import_fields_to_charts` | Canonical Source Material | CCMM-06 (merge columns) |
| `2026_06_24_120000_add_song_authoring_fields` | Canonical Source Material | CCMM-05 (merge columns) |

### 4.3 `backend/database/migrations/` — CCMM-12 source material

| File | Classification | Superseded by |
|------|----------------|---------------|
| `2026_06_18_100000_ph044_effects_domain_schema` | Canonical Source Material | CCMM-12 |
| `2026_06_18_110000_ph044_effect_library_reference_schema` | Canonical Source Material (legacy) | CCMM-12 / operator merge |
| `2026_06_18_120000_ph044_x32_effects_algorithm_reference_schema` | Canonical Source Material | CCMM-12 |
| `2026_06_18_130000_ph044_effects_operator_metadata` | Canonical Source Material | CCMM-12 (merged) |
| `2026_06_18_140000_ph044_effect_package_item_routing_plan` | Canonical Source Material | CCMM-12 (merged) |
| `2026_06_18_150000_ph044_effect_package_item_target_sections` | Canonical Source Material | CCMM-12 |
| `2026_06_23_100000_create_console_learning_tables` | Split | `show_console_baselines` → CCMM-12; `console_learning_snapshots` → LS-EXT-03 |

### 4.4 `backend/database/migrations/` — Live Stage extension

| File | Classification |
|------|----------------|
| `2026_06_10_134252_create_permission_tables` | Live Stage Extension |
| `2026_06_11_110500_create_soundchecks_table` | Live Stage Extension |
| `2026_06_11_110600_create_readiness_records_table` | Live Stage Extension |
| `2026_06_12_100000_create_runtime_action_domain_tables` | Live Stage Extension (runtime) |
| `2026_06_13_100000_create_runtime_event_domain_tables` | Runtime Only |
| `2026_06_14_100000_create_runtime_dispatch_domain_tables` | Runtime Only |
| `2026_06_15_100000_create_integration_device_tables` | Live Stage Extension |
| `2026_06_16_100000_create_performance_device_assignments_table` | Live Stage Extension |

---

## 5. Duplicate migration resolution plan

### 5.1 Duplicate entities (shared tables in both `server/` and `backend/`)

| Entity | server/ fork | backend/ fork | Resolution |
|--------|-------------|---------------|------------|
| `bands` | `130000` | `m2` | CCMM-01 sole authority; both forks retired |
| `users` | `0001` + `133000` | `0001` | CCMM-04 merged CREATE; both retired |
| `people` + children | `131000` | `110000`, `200000` | CCMM-03 merged; all retired |
| `instrument_reference` | `131000` | `110000` | CCMM-02 |
| `songs` | `160000`, `120100` | `m6`, `100300`, `120000` | CCMM-05 merged columns |
| `charts`, `song_instrument_parts` | `160000` | `m9`, `100500`, `240000` | CCMM-06 merged |
| `song_moods`, etc. | `220100` | `220000` | CCMM-02 |
| Laravel infra | `0001_*` | `0001_*` | CCMM-00 |

### 5.2 Conflicting migrations

| Conflict | Nature | Resolution |
|----------|--------|------------|
| `users` skeleton vs portal reconcile | server `0001` lacks `public_id`; `133000` alters | CCMM-04 **CREATE** full schema on fresh Cloud |
| `charts.import_batch_id` | Column without FK in server | CCMM-06 creates `import_batches` first, then FK |
| `invite_links` vs `person_invitations` | Competing onboarding models | Quarantine invite; CCMM-11 only |
| PH059 Part B vs PH061A | Effects/baselines listed LS-only in PH059 | PH061A wins — CCMM-12 Shared CCMM |
| `effect_library_*` vs `effects` | Duplicate catalogues | Operator merge decision before CCMM-12 author |

### 5.3 Supersession rule

1. New shared DDL **only** in `database/migrations/ccmm/` (repo root).  
2. Existing fork marked **Retired Ownership** — moved to `_archived_ccmm_forks/` or documented in README; **never deleted**.  
3. `migrations` table on fresh Cloud records **only** CCMM paths — no fork filenames.  
4. Column drift: CCMM manifest wins; reference migration is **diff input only**.

---

## 6. Live Stage extension boundary

### 6.1 Outside CCMM (never on Cloud Database)

| Category | Tables / concepts | Rationale |
|----------|-------------------|-----------|
| **Learning ephemeral** | `console_learning_snapshots` | Pre-baseline capture; promote to `show_console_baselines` for durability |
| **Connectivity** | `integration_devices`, `integration_connection_profiles` | Show-day bridge endpoints; offline runtime |
| **Performance binding** | `performance_device_assignments` | FK to integration devices; not show-prep asset |
| **Runtime execution** | `runtime_*` | Live cue/action execution state during performance |
| **Operational** | `soundchecks`, `readiness_records` | Human process gates; local show day |
| **OSC / telemetry** | `live_fader_state`, `live_meter_state`, `live_mute_state`, `live_connection_state`, `live_heartbeat_state` | Never canonical rows — bridge cache only |
| **Transport** | `live_transport_state` | Ableton authority — external to X32 DB |

### 6.2 Inside CCMM but JSON-document pattern

Channel, bus, matrix, DCA, routing, monitor send matrix — **not tables**. Stored in `show_console_baselines.baseline_json` per PH043/PH061A.

### 6.3 Parity rule

Live Stage Database = **CCMM-00–12 identical** + **LS-EXT** applied after parity verification (PH061 §11). Cloud Database = **CCMM-00–12** + **CCMM-11** + **RECOVERY** — no LS-EXT.

---

## 7. X32 package definition (CCMM-12)

### 7.1 Included entities

| Entity | Role |
|--------|------|
| `effect_definitions` | Package member identity |
| `effect_packages` | Named musical FX packages |
| `effect_package_items` | Ordered package membership |
| `song_effect_assignments` | Song ↔ package intent |
| `effects` | X32 algorithm reference catalogue |
| `effect_parameters` | Algorithm parameter definitions |
| `effect_package_types` | Package type enum |
| `effect_package_item_parameters` | Per-item parameter overrides |
| `effect_package_item_target_sections` | Routing section metadata |
| `show_console_baselines` | Show-scoped console configuration document |
| `mix_moves` | **Placeholder** — DOMAIN_MODEL M5; no DDL until schema defined |

### 7.2 Explicitly excluded

| Excluded | Reason |
|----------|--------|
| `console_learning_snapshots` | Ephemeral learn — LS-EXT-03 |
| `integration_devices` | Connection state — LS-EXT-01 |
| `performance_device_assignments` | Show-day binding — LS-EXT-02 |
| All `live_*` OSC state | Runtime only |
| Normalized `channels`, `buses`, `routing` tables | JSON-in-baseline strategy |
| X32 scene/snippet recall operations | OSC commands — not DB entities |
| `effect_library_*` | Pending operator merge/quarantine decision |

### 7.3 JSON baseline strategy

| Aspect | Rule |
|--------|------|
| **Storage** | `show_console_baselines.baseline_json` (JSONB) |
| **Content** | `configuration.*` (channels, buses, identity), `routing.*` (PH042), learned metadata |
| **Promotion** | Learn on desk → `console_learning_snapshots` (LS) → operator save → `show_console_baselines` (CCMM) |
| **Sync** | Row replicates Cloud ↔ Live Stage; JSON is the document payload |
| **Versioning** | PH054 checkout columns **not in CCMM v1** — future follow-up |
| **Size risk** | Monitor JSONB size; no normalization unless operator reverses PH061A decision |

### 7.4 CCMM-12 internal order (authoring)

```text
effect_package_types → effects → effect_parameters → effect_definitions
  → effect_packages → effect_package_items → effect_package_item_*
  → song_effect_assignments
show_console_baselines (after shows)
mix_moves — blocked
```

---

## 8. Migration naming standard

### 8.1 Repository layout (authoritative path — PH063)

```text
/database/migrations/ccmm/       ← Shared CCMM packages (repo root)
/database/migrations/recovery/   ← Recovery audit tables
/database/migrations/ls-ext/     ← Live Stage extension (backend loads)
/database/seeders/ccmm/          ← Governed reference seeds
```

### 8.2 Package naming

| Pattern | Example |
|---------|---------|
| Documentation | `CCMM-{NN}` where NN = 00–12, plus `RECOVERY`, `LS-EXT-{NN}` |
| Build phase | `B{N}` maps to `CCMM-{NN}` per §3 |

### 8.3 Migration file naming

```text
{YYYY}_{MM}_{DD}_{HHMMSS}_ccmm{NN}_{slug}.php
```

| Segment | Rule |
|---------|------|
| Timestamp | Strict chronological order within package |
| `ccmm{NN}` | Two-digit package: `ccmm00` … `ccmm12` |
| `slug` | `create_{table}_table`, `seed_{name}`, `extend_{table}_{purpose}` |
| LS-EXT | `ls_ext_{NN}_{slug}.php` |
| Recovery | `recovery_{slug}.php` |

### 8.4 Mandatory migration declarations

Every CCMM migration file **must** declare these three fields in a header block immediately after `<?php`:

| Field | Format | Example |
|-------|--------|---------|
| **CCMM Package** | `CCMM-{NN}`, `RECOVERY`, or `LS-EXT-{NN}` | `CCMM-05` |
| **Decision Reference** | `DECISION_LOG {id}` (comma-separated if multiple) | `DECISION_LOG 230` |
| **PH Reference** | Governing PH document(s) and section | `PH059 A12, PH062 §2` |

```php
<?php

// CCMM Package: CCMM-05
// Decision Reference: DECISION_LOG 230
// PH Reference: PH059 A12, PH062 §2

use Illuminate\Database\Migrations\Migration;
```

**Optional** supplementary tags (recommended, not a substitute for the three mandatory fields):

```php
// @ccmm-ownership Shared CCMM
// @ccmm-supersedes backend/2026_06_10_140100_m6_create_songs_table.php
```

PRs missing any mandatory declaration are **non-compliant** and must not merge.

### 8.5 Drift prevention

| Rule | Enforcement |
|------|-------------|
| No shared DDL outside `database/migrations/ccmm/` | Code review + CI path check |
| CCMM change before migration file | §9 process |
| No `Schema::create` for CCMM tables in `server/` or `backend/` | Lint rule (PH063) |
| Manifest diff required on PR | `ValidateCcmmSchema` command |

---

## 9. CCMM change process

```text
1. Proposal        — Issue / governance prompt describing domain need
2. Governance      — Review against DOMAIN_MODEL, DATA_ARCHITECTURE, DATABASE_ARCHITECTURE
3. CCMM update     — Amend PH059 (or governed addendum); DECISION_LOG entry
4. Authoring plan  — Update PH062 package mapping if packages affected
5. Migration       — PH063 authors PHP in database/migrations/ccmm/ only
6. Cloud           — Fresh migrate or governed ALTER package on isolated DB
7. Live Stage      — Parity apply + LS-EXT review if boundary crossed
```

| Step | Authority | Blocks |
|------|-----------|--------|
| Proposal | Operator / domain owner | — |
| Governance review | DECISION_LOG + charter stack | Step 3 |
| CCMM update | PH059 manifest | Step 5 |
| Migration authoring | PH062 + PH063 | Production migrate |
| Cloud apply | PH061 Gates 2–3 | Data import |
| Live Stage parity | PH061 §11 | PH054 sync |

**No direct migration creation without CCMM change.** Emergency production DDL is **forbidden** per AGENTS.md Production Safety Rules.

---

## 10. PH063 readiness assessment

### Classification: **Ready with Conditions**

| Criterion | Status |
|-----------|--------|
| CCMM manifest complete (PH059) | ✅ |
| Gap analysis complete (PH060) | ✅ |
| Recovery execution plan (PH061) | ✅ |
| X32 domain classified (PH061A) | ✅ |
| Migration authoring blueprint (PH062) | ✅ |
| Operator Gate 2 sign-off | ❌ Blocker for production |
| Fresh isolated Cloud cluster | ❌ Blocker for production |
| `database/migrations/ccmm/` exists | ❌ PH063 deliverable |
| `mix_moves` schema defined | ❌ CCMM-12 placeholder only |
| `effect_library_*` merge decision | ❌ Operator decision |
| PH061B console templates | ❌ Optional; not blocking core |

### PH063 scope (when authorised)

1. Create `database/migrations/ccmm/` CCMM-00–12 PHP files  
2. Create `database/migrations/recovery/` entity map  
3. Archive `server/` forks to `_archived_ccmm_forks/`  
4. Wire migration loader in `/server/` and `/backend/`  
5. `ValidateCcmmSchema` + `CcmmFreshMigrateTest`  
6. **Still no production migrate** until PH061 Gate 2 + isolated cluster  

### Blockers summary

| # | Blocker | Owner |
|---|---------|-------|
| 1 | Operator Gate 2 (PH061) | Operator |
| 2 | Migration PHP files not written | PH063 |
| 3 | `effect_library_*` disposition | Operator |
| 4 | `mix_moves` M5 schema undefined | Domain / M5 |
| 5 | Repo-root CCMM path wiring decision | Operator (default: repo root) |

---

## 11. Operator decisions required

| # | Decision | Default |
|---|----------|---------|
| 1 | Repo-root `database/migrations/ccmm/` | **Approved** |
| 2 | CCMM-12 in initial Cloud build (B12) vs post-Gate 4 | **Include B12** for show prep parity |
| 3 | `effect_library_*` merge into `effects` or quarantine | **Merge** |
| 4 | Laravel infra in `server/0001_*` vs CCMM-00 consolidated | **Keep `0001_*`** |
| 5 | Spatie permissions — Cloud or LS-EXT | **LS-EXT** |
| 6 | Authorise PH063 implementation start | **After this plan merge** |
| 7 | B12 before or after B11 invitations | **B12 before B11** |

---

## 12. Canonical migration path (summary)

| Path | Role |
|------|------|
| `database/migrations/ccmm/` | **Sole authority** for shared schema DDL |
| `database/migrations/recovery/` | Cloud recovery audit |
| `database/migrations/ls-ext/` | Live Stage superset |
| `server/database/migrations/_archived_ccmm_forks/` | Retired forks — audit only |
| `backend/database/migrations/` (M-series) | Retired ownership — audit only after realignment |

---

End of PH062 — blueprint only; no migration files authored
