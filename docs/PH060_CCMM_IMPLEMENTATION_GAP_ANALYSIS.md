# PH060 — CCMM Implementation Gap Analysis

Status: Analysis only — **no production mutation, migrations, DDL, deploys, schema changes, data migration, or sync**  
Authority: `docs/PH059_CLOUD_CANONICAL_MIGRATION_MANIFEST.md` (CCMM)  
Date: 2026-06-24  
Inputs: `server/database/migrations/`, PH057 audit, PH056 production forensic

---

## 1. Scope

Compare **PH059 CCMM** against **current Cloud implementation** (`/server/` migrations + models).

| Layer | Definition |
|-------|------------|
| **Cloud implementation (code)** | What `server/database/migrations/` define on a **fresh** Cloud Database |
| **Production forensic** | Contaminated `defaultdb` (PH056) — **not authoritative**; noted separately |

PH060 determines what must change before Cloud can become the canonical database. **No implementation yet.**

---

## 2. Cloud Database Inventory

### 2.1 CCMM entities (35 shared tables)

See PH059 Part F.

### 2.2 Actual tables from `server/` migrations (fresh migrate)

| Category | Tables |
|----------|--------|
| **CCMM shared** | `bands`, `users`, `people`, `person_secure_fields`, `person_files`, `person_instruments`, `person_iem_settings`, `instrument_reference`, `instrument_parts`, `songs`, `charts`, `song_instrument_parts`, `song_moods`, `time_signatures`, `musical_keys` |
| **Quarantined** | `invite_links`, `invite_link_acceptances` |
| **Laravel infra** | `cache`, `cache_locks`, `sessions`, `password_reset_tokens`, `jobs`, `job_batches`, `failed_jobs` |
| **CCMM missing** | 20 shared tables (see §4) |

**Fresh Cloud table count (ESB):** 15 shared + 2 quarantined = **17 domain tables**  
**CCMM required:** 35 shared tables → **gap: 20 missing**

### 2.3 `server/` migration inventory (16 files)

| Migration | Creates / alters |
|-----------|-------------------|
| `0001_01_01_000000_create_users_table` | users, password_reset_tokens, sessions |
| `0001_01_01_000001_create_cache_table` | cache, cache_locks |
| `0001_01_01_000002_create_jobs_table` | jobs, job_batches, failed_jobs |
| `2026_06_23_120000_create_invite_links_table` | invite_links ⚠ quarantined |
| `2026_06_23_130000_create_bands_table` | bands |
| `2026_06_23_131000_create_band_people_schema` | people, person_* , instrument_reference, person_instruments |
| `2026_06_23_132000_provision_portal_reference_data` | **seed** bands, instrument_reference |
| `2026_06_23_133000_reconcile_users_for_portal_auth` | users columns |
| `2026_06_23_134000_create_invite_link_acceptances_table` | invite_link_acceptances ⚠ |
| `2026_06_23_140000_add_profile_identity_fields_to_people` | people bio, profile_photo_path |
| `2026_06_23_141000_add_profile_photo_display_path_to_people` | people profile_photo_display_path |
| `2026_06_23_160000_provision_studio_library_read_tables` | instrument_parts, songs, charts, song_instrument_parts |
| `2026_06_23_161000_provision_studio_library_storage_directories` | storage dirs (non-DB) |
| `2026_06_23_162000_open_studio_library_storage_for_operator_sync` | env/storage (non-DB) |
| `2026_06_23_220100_provision_studio_song_metadata_tables` | song_moods, time_signatures, musical_keys; songs FKs |
| `2026_06_24_120100_provision_studio_song_authoring_fields` | songs authoring columns |

### 2.4 Reference tables

| Table | In server? | Seeded? |
|-------|------------|---------|
| `instrument_reference` | yes | `132000` + InstrumentCatalog |
| `song_moods` | yes | **no** in server (backend seeds in 220000) |
| `time_signatures` | yes | **no** in server |
| `musical_keys` | yes | **no** in server |
| `action_types` | **no** | — |

### 2.5 Production forensic overlay (PH056 — not code authority)

| Item | State |
|------|-------|
| Host | `pr-esbdata-68105.db.on-forge.com` / `defaultdb` |
| Migrations recorded | **66** (server + backend + Website) |
| Manual DDL on `users` | governance violation |
| Backend-only tables likely present | musicians, shows, effects, runtime_*, etc. |
| Row counts | users=0, people=0, bands=1, songs=0 |

Production is **not** a clean Cloud implementation — PH061 must target **fresh isolated Cloud DB** per PH056 Path B.

---

## 3. CCMM Entity Inventory

| CCMM Entity | Exists in server/? | Notes |
|-------------|-------------------|-------|
| bands | yes | idempotent create |
| users | yes | skeleton + reconcile |
| people | yes | PH045 + profile |
| person_secure_fields | yes | |
| person_files | yes | |
| person_iem_settings | yes | |
| musicians | **no** | |
| musician_band_roles | **no** | |
| instrument_reference | yes | includes slug |
| person_instruments | yes | |
| instrument_parts | yes | |
| songs | yes | metadata + authoring |
| song_moods | yes | empty without seed |
| time_signatures | yes | empty without seed |
| musical_keys | yes | empty without seed |
| charts | yes | import_batch_id column only |
| song_instrument_parts | yes | |
| import_batches | **no** | |
| import_entity_mappings | **no** | |
| ableton_show_files | **no** | |
| shows | **no** | |
| show_playlist_items | **no** | |
| performances | **no** | |
| performance_assignments | **no** | |
| devices | **no** | |
| capabilities | **no** | |
| assignments | **no** | |
| cues | **no** | |
| action_types | **no** | |
| action_definitions | **no** | |
| action_parameters | **no** | |
| cue_actions | **no** | |
| snippets | **no** | |
| venues | **no** | |
| festivals | **no** | |
| invite_links | yes | quarantined |
| invite_link_acceptances | yes | quarantined |

---

## 4. Entity Gap Analysis (status matrix)

| Entity | Status | Reason |
|--------|--------|--------|
| bands | **PARTIAL** | Missing `primary_director_musician_id` (blocked until musicians) |
| users | **DRIFTED** | Missing `public_id`; CCMM merge not implemented |
| people | **ALIGNED** | PH045 + bio + profile photo paths |
| person_secure_fields | **ALIGNED** | |
| person_files | **ALIGNED** | |
| person_iem_settings | **ALIGNED** | |
| musicians | **MISSING** | No server migration |
| musician_band_roles | **MISSING** | |
| instrument_reference | **ALIGNED** | slug present |
| person_instruments | **ALIGNED** | |
| instrument_parts | **ALIGNED** | |
| songs | **ALIGNED** | All CCMM columns in server migrations |
| song_moods | **PARTIAL** | Table exists; **no seed** in server path |
| time_signatures | **PARTIAL** | Table exists; **no seed** |
| musical_keys | **PARTIAL** | Table exists; **no seed** |
| charts | **PARTIAL** | `import_batch_id` without FK; parent table missing |
| song_instrument_parts | **ALIGNED** | |
| import_batches | **MISSING** | |
| import_entity_mappings | **MISSING** | |
| ableton_show_files | **MISSING** | |
| shows | **MISSING** | |
| show_playlist_items | **MISSING** | |
| performances | **MISSING** | |
| performance_assignments | **MISSING** | |
| devices | **MISSING** | |
| capabilities | **MISSING** | |
| assignments | **MISSING** | |
| cues | **MISSING** | |
| action_types | **MISSING** | |
| action_definitions | **MISSING** | |
| action_parameters | **MISSING** | |
| cue_actions | **MISSING** | |
| snippets | **MISSING** | |
| venues | **MISSING** | |
| festivals | **MISSING** | |
| invite_links | **QUARANTINED** | Legacy open invite |
| invite_link_acceptances | **QUARANTINED** | |

**Summary:** ALIGNED 9 · PARTIAL 5 · DRIFTED 1 · MISSING 20 · QUARANTINED 2

---

## 5. Column-Level Review (PARTIAL + DRIFTED)

### `users` — DRIFTED

| Column | CCMM | Current server | Action |
|--------|------|----------------|--------|
| public_id | uuid NOT NULL unique | **absent** | **add** |
| username | varchar(32) nullable unique | present | align CI unique index |
| person_id | FK people | present | none |
| band_id | FK bands | present | none |
| name | nullable | nullable (reconcile) | none |
| email | nullable non-unique | nullable; unique dropped on pgsql | none |
| is_active | boolean default true | present | none |
| email_verified_at | nullable | present | none |

**Data impact:** schema + identity reconciliation (`public_id` backfill for any existing rows)

### `bands` — PARTIAL

| Column | CCMM | Current | Action |
|--------|------|---------|--------|
| primary_director_musician_id | FK musicians nullable | **absent** | **add** after musicians CCMM migration |

**Data impact:** schema only (nullable)

### `charts` — PARTIAL

| Column | CCMM | Current | Action |
|--------|------|---------|--------|
| import_batch_id | FK → import_batches | bigint nullable, **no FK** | add FK after import_batches created |

**Data impact:** schema only

### `song_moods`, `time_signatures`, `musical_keys` — PARTIAL

| Issue | CCMM | Current | Action |
|-------|------|---------|--------|
| Reference seed | SongMetadataReferenceSeeder | **not run** in server migrations | add governed seed step PH061 |

**Data impact:** schema only (tables exist); seed data required

---

## 6. Migration Ownership Review

| Entity | Current migration source | Status |
|--------|-------------------------|--------|
| bands | `server/130000` + `backend/140000_m2` **duplicate** | **retire server/**; CCMM from Cloud-first package |
| users | `server/0001` + `133000`; `backend/0001` **duplicate** | **merge** CCMM A2; retire both forks |
| people + children | `server/131000` + `backend/110000` **duplicate** | **retire server/** idempotent fork |
| instrument_reference | server/131000 | migrate to CCMM; keep slug |
| instrument_parts, songs, charts, SIP | `server/160000` + backend M-series **duplicate** | **retire server/** provision |
| song metadata refs | `server/220100` + `backend/220000` **duplicate** | **retire server/**; single CCMM + seed |
| song authoring | `server/120100` + `backend/120000` **duplicate** | **retire server/** |
| musicians → festivals | **backend only** today | **create** Cloud-first CCMM migrations (PH061) |
| invite_links | `server/120000`, `134000` | **quarantine**; no CCMM migration |
| runtime/effects | **backend only** | **exclude** from Cloud (PH059 Part B) |

**Rule:** After PH061, **one migration path** owns each shared entity — authored Cloud-first, not duplicated in `server/` and `backend/`.

---

## 7. Cloud Completion Matrix

| Entity | Status | Required Action |
|--------|--------|-----------------|
| bands | PARTIAL | extend (`primary_director_musician_id` after musicians) |
| users | DRIFTED | merge (`public_id` + portal columns) |
| people | ALIGNED | none |
| person_secure_fields | ALIGNED | none |
| person_files | ALIGNED | none |
| person_iem_settings | ALIGNED | none |
| musicians | MISSING | create |
| musician_band_roles | MISSING | create |
| instrument_reference | ALIGNED | none (verify seed) |
| person_instruments | ALIGNED | none |
| instrument_parts | ALIGNED | none |
| songs | ALIGNED | none |
| song_moods | PARTIAL | seed |
| time_signatures | PARTIAL | seed |
| musical_keys | PARTIAL | seed |
| charts | PARTIAL | extend FK + create import_batches |
| song_instrument_parts | ALIGNED | none |
| import_batches | MISSING | create |
| import_entity_mappings | MISSING | create |
| ableton_show_files | MISSING | create |
| shows | MISSING | create |
| show_playlist_items | MISSING | create |
| performances | MISSING | create |
| performance_assignments | MISSING | create |
| devices | MISSING | create |
| capabilities | MISSING | create |
| assignments | MISSING | create |
| cues | MISSING | create |
| action_types | MISSING | create + seed |
| action_definitions | MISSING | create |
| action_parameters | MISSING | create |
| cue_actions | MISSING | create |
| snippets | MISSING | create |
| venues | MISSING | create |
| festivals | MISSING | create |
| invite_links | QUARANTINED | retire later |
| invite_link_acceptances | QUARANTINED | retire later |
| person_invitations | MISSING (future) | create PH048B; not blocker for CCMM core |

---

## 8. Data Impact Analysis

| Action | Entities | Impact type |
|--------|----------|-------------|
| none | people, person_*, instrument_parts, songs, song_instrument_parts, instrument_reference (schema) | — |
| schema only | bands extension, charts FK, import_batches, all MISSING creates | DDL only on fresh DB |
| schema + seed | song_moods, time_signatures, musical_keys, action_types, instrument_reference | reference data |
| schema + identity reconciliation | users (`public_id` backfill) | existing rows if any |
| schema + file migration | charts, snippets (when populated), ableton_show_files, person_files | Spaces + checksum verify |
| schema + data migration | Live Stage → Cloud (PH058 §7) | post-CCMM; not PH060 |
| quarantine | invite_links | no migration to CCMM; drop after person_invitations |

---

## 9. Recovery Dependency Sequence (PH061)

| Phase | Entities | Blockers |
|-------|----------|----------|
| **C0** | PH056 forensic export; isolated Cloud DB provision | operator path approval |
| **C1** | `bands` | none |
| **C2** | `instrument_reference`, `song_moods`, `time_signatures`, `musical_keys` + seeds | bands |
| **C3** | `people`, person_* children | bands |
| **C4** | `users` (merged schema) | bands, people |
| **C5** | `musicians`, `musician_band_roles` | bands; users optional |
| **C6** | `bands.primary_director_musician_id` | musicians |
| **C7** | `import_batches` | bands, users |
| **C8** | `songs` | bands, C2 refs |
| **C9** | `cues` | songs |
| **C10** | `instrument_parts`, `import_entity_mappings` | bands; C7 for mappings |
| **C11** | `charts`, `song_instrument_parts` | songs, C7, C10 |
| **C12** | `snippets` | C9, C11 |
| **C13** | `action_types` (+seed), `action_definitions`, `action_parameters`, `cue_actions` | bands, cues |
| **C14** | `devices`, `capabilities`, `assignments` | musicians, instrument_parts |
| **C15** | `ableton_show_files`, `shows`, `show_playlist_items` | bands, songs |
| **C16** | `performances`, `performance_assignments` | C15, musicians |
| **C17** | `venues`, `festivals` | bands |
| **C18** | `person_invitations` (Cloud workspace) | people, users |
| **C19** | Retire quarantine tables; deprecate `server/` duplicate migrations | C18 or operator sign-off |
| **C20** | Live Stage CCMM parity apply + superset | Cloud verified |

**Critical path:** C1 → C2 → C3 → C4 → C8 → C11 → C15 → C16 (minimum for show execution parity)

---

## 10. Quarantine Review

| Item | Disposition | Timing |
|------|-------------|--------|
| `invite_links` | **remove from canonical model** | keep temporarily on prod forensic DB only |
| `invite_link_acceptances` | **remove from canonical model** | retire with invite_links |
| `server/130000` bands | **archive** | retire when CCMM-1 replaces |
| `server/131000` band_people | **archive** | retire when CCMM-3 replaces |
| `server/160000` studio library | **archive** | retire when CCMM-8–11 replace |
| `server/220100` metadata | **archive** | retire when CCMM-2 replaces |
| `server/120100` authoring | **archive** | merged into CCMM songs |
| `server/120000`, `134000` invite | **archive** | do not run on fresh Cloud |
| Production `defaultdb` | **forensic read-only** | decommission after export |

---

## 11. Risk Classification by Entity

| Risk | Entities / items |
|------|------------------|
| **Critical** | users DRIFTED (no public_id); 20 MISSING tables; production 66-migration contamination; duplicate server/backend migrations; charts broken FK |
| **High** | missing shows/performances/cues/snippets; unseeded reference tables; invite_links on code path |
| **Medium** | bands partial; venues/festivals missing; action domain missing |
| **Low** | aligned person subtree; instrument_parts; song_instrument_parts |

---

## 12. Operator Decisions Required

1. Approve PH061 dependency sequence (§9)  
2. Confirm fresh Cloud DB (PH056 Path B) before any CCMM migration execution  
3. Approve retirement of all `server/` duplicate shared migrations (§10)  
4. Approve `person_invitations` timing (C18) vs PH048B  
5. Confirm SongMetadataReferenceSeeder runs on Cloud provision  
6. Accept empty reference tables until seed step if Path B  

---

## 13. PH061 Readiness Gate

Cloud **cannot** become canonical database until:

| # | Gate |
|---|------|
| 1 | 20 MISSING entities have CCMM migrations (PH061) |
| 2 | `users` DRIFTED → ALIGNED (`public_id`) |
| 3 | `charts` + `import_batches` FK complete |
| 4 | Reference seeds defined |
| 5 | `server/` duplicate migrations retired from deploy path |
| 6 | Production isolated from contaminated `defaultdb` |
| 7 | Quarantined tables excluded from fresh migrate |

**Current readiness: NOT READY** — 20/35 shared entities missing or drifted at code level.

---

End of PH060 — analysis only
