# PH058 — Cloud-First Canonical Schema Stabilisation Plan

Status: PH058 Amended (Cloud-First Canonical Schema — reliability framing)  
Authority: `docs/PROJECT_CHARTER.md`, `docs/DECISION_LOG.md` PH055–PH057, `AGENTS.md`  
Date: 2026-06-24  
Inputs: `docs/PH056_PRODUCTION_RECOVERY_PLAN.md`, PH057 schema audit (conversation), `docs/DATABASE_ARCHITECTURE.md`

---

## 1. Governance Review Evidence

| Document | Compliance |
|----------|------------|
| `AGENTS.md` | PH055 two-DB / three-workspace model; Production Safety Rules honoured — no mutation |
| `PROJECT_CHARTER.md` | Cloud collaboration + local performance authority preserved; Ableton timeline unchanged |
| `ARCHITECTURE.md` | ESB Data Architecture; schema parity mandatory for shared entities |
| `DATA_ARCHITECTURE.md` | Twelve logical domains; phase-aware authority; Person ≠ User ≠ Musician |
| `DATABASE_ARCHITECTURE.md` | Aggregate boundaries; cloud stores canonical master library + operational records after publish |
| `DOMAIN_MODEL.md` | Song Code + Cue Number; Person Invitation (`person_id`); ESB Studio as UX destination |
| `DECISION_LOG.md` | PH055 (185–188), PH056 (189–195) — recovery gated; PH047 person-first invitation |
| `ADR-001` | Peer authoring; checkout/version sync — compatible with Cloud-first canonical schema |
| PH057 | Shared ESB parity **failed** — parallel migration forks root cause |

**Charter alignment:** Cloud-first canonical schema does **not** make Cloud overwrite authority for song assets (PH054). It defines **one migration-defined structure** both workspaces must share. Data-state authority remains phase-aware (PH004).

---

## 2. Cloud-First Canonical Decision Statement

**Decision PH058:** The **Cloud Database** is the **canonical schema authority** for all **shared ESB entities**.

### Reliability framing (not hierarchy of importance)

Cloud-first canonical schema is a **reliability decision**, not a ranking of which workspace matters more. Live Stage is Priority #1 for show-day execution (Charter). Cloud is Priority #1 for **durable system-of-record** responsibilities.

| Cloud Database owns | Live Stage Database owns |
|-------------------|--------------------------|
| Backup | Rehearsal |
| Restore | Performance |
| Replication | Offline operation |
| Reference data (canonical) | Console / Ableton runtime execution |
| Long-term history | Pending local changes until synchronised |
| **Rebuild source for Live Stage** | **Operational continuity without Cloud** |

| Principle | Statement |
|-----------|-----------|
| **Cloud can rebuild Live Stage** | Governed pull from Cloud repopulates shared ESB entities + file cache on Live Stage |
| **Live Stage can operate without Cloud** | Rehearsal and performance must not depend on cloud connectivity; local DB + cache sufficient after sync-before-show |
| **Schema parity** | Same migration-defined structure — reliability requires predictable rebuild |
| **Data-state divergence** | Offline Live Stage may hold pending edits — not schema forks |

Cloud-first canonical schema does **not** make Cloud permanent overwrite authority for song assets (PH054). It does **not** demote Live Stage runtime authority (Ableton timeline, Local Show Runtime execution). It defines **one durable schema** Cloud can backup, restore, and use to rebuild Live Stage.

### Schema governance rules

| Rule | Statement |
|------|-----------|
| **Canonical DDL source** | Future shared-entity migrations are authored for Cloud Database first, then applied identically to Live Stage Database |
| **Live Stage obligation** | Live Stage Database **must match** Cloud schema for shared ESB entities — no parallel forks in `server/` or `backend/` |
| **Offline divergence** | Live Stage may hold different **row values**, versions, and checkout state offline — **not** different table/column definitions |
| **Runtime superset** | Live Stage may retain **runtime-only tables** not present on Cloud (timeline state, bridge health, execution logs, X32 console learning) |
| **Retirement** | `server/database/migrations/` duplicate shared-entity provisions (`130000` bands, `131000` band_people, `160000` studio library, etc.) are **deprecated** — not canonical |
| **Production** | PH056 recovery must complete on **stabilised Cloud schema** before populate or sync |

This **amends PH057 recommendation** (backend-owned DDL) in favour of operator-mandated **Cloud-first canonical authority**. Live Stage definitions are **merged into** Cloud canonical where Cloud is incomplete.

---

## 3. Missing Cloud Tables (vs Live Stage `esb_dev`)

Tables present on Live Stage but absent from current `server/` migrations.

| Table | Classification | Rationale |
|-------|----------------|-----------|
| `shows` | **Must exist in Cloud** | Show aggregate root; DATABASE_ARCHITECTURE §4 Show Domain |
| `show_playlist_items` | **Must exist in Cloud** | Show playlist; sync unit component |
| `ableton_show_files` | **Must exist in Cloud** | Required FK for `shows.ableton_show_file_id` |
| `performances` | **Must exist in Cloud** | Performance aggregate; musician portal needs schedule visibility |
| `performance_assignments` | **Must exist in Cloud** | Operational assignments per performance |
| `musicians` | **Must exist in Cloud** | Operational roster (distinct from Person); Assignments domain |
| `musician_band_roles` | **Must exist in Cloud** | Band role governance (PH020 migration) |
| `devices` | **Must exist in Cloud** | Musician devices; collaboration prep |
| `capabilities` | **Must exist in Cloud** | Musician ↔ Instrument Part |
| `assignments` | **Must exist in Cloud** | Musician ↔ Instrument Part mapping |
| `cues` | **Must exist in Cloud** | Song aggregate; `SSS.CCC` identity |
| `snippets` | **Must exist in Cloud** | PH027/PH028 domain |
| `cue_actions` | **Must exist in Cloud** | Action definitions at cue boundaries |
| `action_types` | **Must exist in Cloud** | Action domain support |
| `action_definitions` | **Must exist in Cloud** | Action domain support |
| `action_parameters` | **Must exist in Cloud** | Action domain support |
| `import_batches` | **Must exist in Cloud** | `charts.import_batch_id` FK; PH029–PH031 audit |
| `import_entity_mappings` | **Must exist in Cloud** | Legacy import audit |
| `venues` | **Requires operator decision** | Tour logistics; likely Cloud-canonical after publish |
| `festivals` | **Requires operator decision** | Application tracking; likely Cloud-canonical |
| `permissions` | **Cloud workspace** (Spatie) | Portal + director RBAC — shared if same auth model |
| `roles` | **Cloud workspace** | Spatie roles |
| `model_has_*` | **Cloud workspace** | Spatie pivots |
| `role_has_permissions` | **Cloud workspace** | Spatie |
| `invite_links` | **Legacy / quarantine** | Non-compliant (PH055-187); replace with `person_invitations` |
| `invite_link_acceptances` | **Legacy / quarantine** | Deprecate with `invite_links` |
| `runtime_events` | **Live Stage only** | Runtime State domain — not cloud-canonical during performance |
| `runtime_action_plans` | **Live Stage only** | Runtime execution |
| `runtime_action_items` | **Live Stage only** | Runtime execution |
| `runtime_audit_records` | **Live Stage only** | Runtime execution logs |
| `runtime_dispatches` | **Live Stage only** | Runtime dispatch |
| `runtime_dispatch_items` | **Live Stage only** | Runtime dispatch |
| `integration_devices` | **Live Stage only** | Show-day device connections |
| `integration_connection_profiles` | **Live Stage only** | Bridge configuration |
| `performance_device_assignments` | **Live Stage only** | Show-day device binding |
| `soundchecks` | **Live Stage only** | Pre-show validation state |
| `readiness_records` | **Live Stage only** | Performance readiness gate |
| `console_learning_snapshots` | **Live Stage only** | X32 OSC learning |
| `show_console_baselines` | **Live Stage only** | X32 console baseline |
| `effect_*` / `effects` | **Live Stage only** | PH044 X32 effects — Live Stage workspace |
| `song_effect_assignments` | **Live Stage only** | PH044 — performance routing |

---

## 4. Table Classification Summary

| Category | Count (approx.) | Action |
|----------|-----------------|--------|
| **Must exist in Cloud** | 22 shared ESB tables | Add to canonical Cloud migration manifest |
| **Cloud workspace only** | Spatie RBAC + `person_invitations` (future) | Cloud migrations; not required on Live Stage if auth differs |
| **Live Stage only** | ~20 runtime/X32/integration tables | Retain as Live Stage superset; never on Cloud |
| **Legacy / quarantine** | `invite_links`, `invite_link_acceptances` | Do not migrate; replace per PH047 |
| **Operator decision** | `venues`, `festivals` | Default: include in Cloud (tour prep collaboration) |

---

## 5. Definition Mismatch Table (shared tables in both paths)

| Table | Live Stage today | Cloud today (`server/`) | PH058 decision | Canonical outcome |
|-------|------------------|-------------------------|----------------|-------------------|
| `users` | `public_id`, `name`/`email` NOT NULL, email unique | `username`, `person_id`, `band_id`, `is_active`; no `public_id`; nullable name/email | **Merge both** | `id`, `public_id` (uuid unique), `username` (32, unique, nullable until portal), `person_id` FK, `band_id` FK, `name` nullable, `email` nullable non-unique, `email_verified_at`, `password`, `is_active` default true, `remember_token`, timestamps |
| `bands` | + `primary_director_musician_id` FK | Base only | **Merge** | Base + `primary_director_musician_id` nullable FK → `musicians` |
| `people` | PH045 base | + `bio`, `profile_photo_path`, `profile_photo_display_path` | **Merge** | PH045 + portal profile columns on canonical `people` |
| `instrument_reference` | No `slug` | `slug` unique NOT NULL | **Adopt Cloud** | Add `slug` to canonical; backfill Live Stage |
| `instrument_parts` | Aligned | Aligned | **Adopt either** (identical) | No change |
| `songs` | Full metadata + authoring (pending one migration locally) | Metadata + authoring (idempotent) | **Merge** | Full column set from both paths; `status` not `lifecycle_state` |
| `charts` | `song_id` FK, file metadata, `import_batch_id` FK | Same columns; **no `import_batches` table** | **Merge + fix** | Live Stage chart definition + `import_batches` on Cloud |
| `song_instrument_parts` | `chart_id` nullable | `chart_id` nullable | **Adopt either** (identical) | No change |
| `song_moods` | Aligned | Aligned | **Adopt either** | No change |
| `time_signatures` | Aligned | Aligned | **Adopt either** | No change |
| `musical_keys` | Aligned | Aligned | **Adopt either** | No change |

### `songs` canonical columns (merged)

`id`, `public_id`, `band_id`, `song_code` (char 3), `name`, `bpm`, `time_signature_id`, `musical_key_id`, `mood_id`, `description`, `notes`, `director_notes`, `status` (default `draft`), `genre`, `style`, `tempo_feel`, `count_in`, `mood_intention`, `performance_feel`, `arrangement_comments`, `reference_url`, `reference_title`, `reference_notes`, timestamps.

Indexes: `unique(band_id, song_code)`, `index(band_id, status)`.

### `users` merge note

Satisfies PH007 (`public_id` for sync) **and** PH047 (`username`, `person_id`, `band_id`, `is_active`). Director local app users and portal users share one `users` table on Cloud.

---

## 6. Canonical Cloud Schema Recommendation (shared ESB entities)

Convention: PostgreSQL 16+. All shared entities include `id` (bigint PK), `public_id` (uuid unique) where noted, `timestamps`.

### `bands`

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| id | bigint | NO | serial | PK |
| public_id | uuid | NO | — | unique |
| name | varchar | NO | — | |
| primary_director_musician_id | bigint | YES | — | FK → musicians.id nullOnDelete |
| created_at, updated_at | timestamp | YES | — | |

Indexes: `bands_public_id_unique`.

Ownership: Band domain. Cloud-canonical after publish. Live Stage cached.

---

### `users`

| Column | Type | Null | Default | Notes |
|--------|------|------|---------|-------|
| id | bigint | NO | serial | PK |
| public_id | uuid | NO | — | unique; PH007 sync identity |
| username | varchar(32) | YES | — | unique; PH047 portal login |
| person_id | bigint | YES | — | FK → people nullOnDelete |
| band_id | bigint | YES | — | FK → bands nullOnDelete |
| name | varchar | YES | — | |
| email | varchar | YES | — | contact; not login identifier |
| email_verified_at | timestamp | YES | — | PH048A gate |
| password | varchar | NO | — | hashed |
| is_active | boolean | NO | true | |
| remember_token | varchar | YES | — | |
| timestamps | | | | |

Indexes: `users_public_id_unique`, `users_username_unique`.

Ownership: Identity domain. Cloud authoritative. Live Stage session cache only.

Sync relevance: Portal accounts; not created at show time on Live Stage.

---

### `people` (+ child tables)

**`people`:** PH045 columns + `bio` text nullable, `profile_photo_path` varchar nullable, `profile_photo_display_path` varchar nullable.

Child tables unchanged from PH045: `person_secure_fields`, `person_files`, `person_instruments`, `person_iem_settings`.

Ownership: Band People domain. Cloud-canonical. Live Stage replica.

Sync relevance: Full personnel record; PH047 no credentials on Person.

---

### `instrument_reference`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id | bigint | NO | serial |
| public_id | uuid | NO | — |
| slug | varchar | NO | — | **unique** (Cloud canonical) |
| name | varchar | NO | — |
| family | varchar | YES | — |
| is_active | boolean | NO | true |
| timestamps | | | |

Index: `(is_active, name)`.

---

### `instrument_parts`

Standard M4 definition: `band_id` FK, `name`, `description`, `active`, `public_id`.

---

### `songs`

Merged definition (§5). FKs: `time_signature_id`, `musical_key_id`, `mood_id` → reference tables nullOnDelete.

Sync relevance: **High** — PH054 checkout/version target. `song_code` is business identity (PH010.01).

---

### `charts`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id, public_id | | | |
| song_id | bigint | NO | FK → songs |
| title | varchar | NO | |
| original_filename | varchar | YES | |
| storage_reference | varchar | NO | Spaces key / managed path |
| checksum | varchar | NO | |
| mime_type | varchar(127) | YES | |
| file_size | bigint | YES | |
| notes | text | YES | |
| import_batch_id | bigint | YES | FK → import_batches |
| timestamps | | | |

Unique: `(song_id, checksum)`.

Sync relevance: Metadata on Cloud; binary in Spaces with local cache on Live Stage.

---

### `song_instrument_parts`

`song_id`, `instrument_part_id`, `chart_id` nullable, `notes`, `public_id`. Unique `(song_id, instrument_part_id)`.

---

### `song_moods`, `time_signatures`, `musical_keys`

Reference tables per `2026_06_23_220000` / server provision — identical. Seed on Cloud provision.

---

### `shows`

| Column | Type | Null | Default |
|--------|------|------|---------|
| id, public_id | | | |
| band_id | bigint | NO | FK |
| ableton_show_file_id | bigint | NO | FK unique |
| name | varchar | NO | |
| description | text | YES | |
| lifecycle_state | varchar | NO | `draft` |
| timestamps | | | |

Index: `(band_id, lifecycle_state)`.

---

### `show_playlist_items`

`show_id`, `song_id`, `position`, `ableton_pgm` nullable. Unique `(show_id, position)`, unique `(show_id, song_id)`.

---

### `performances`

`band_id`, `show_id`, `venue`, `performance_date`, `status` default `planned`, `notes`, `public_id`.

---

### `musicians`, `devices`, `capabilities`, `assignments`

Per Live Stage M3–M5 definitions. `musicians` includes PH019 profile fields + `user_id` nullable FK when linked.

---

### `cues`

`song_id`, `cue_number` char(3), `name`, `description`, `notes`, `sequence_order` (PH028), `public_id`. Unique `(song_id, cue_number)`.

Sync relevance: **Critical** — `SSS.CCC` runtime identity component.

---

### `snippets`

PH028 extended: `source_type`, `freshness_state`, `is_active`, storage references, partial unique index on active `(song_instrument_part_id, cue_id)`.

---

### `import_batches` / `import_entity_mappings`

Per `2026_06_18_100000_create_import_batch_tables.php`. Required on Cloud before `charts.import_batch_id` FK is valid.

---

### Cloud workspace-only (not shared ESB parity)

| Table | Purpose |
|-------|---------|
| `person_invitations` | PH047 — replaces `invite_links` |
| `sessions`, `password_reset_tokens` | Laravel auth |
| Spatie RBAC tables | If portal and director share Cloud auth |

---

### Live Stage runtime-only (not on Cloud)

Listed in §3 — `runtime_*`, `soundchecks`, `readiness_records`, `console_learning_*`, `effect_*`, `integration_*`, `performance_device_assignments`.

---

## 7. Live Stage → Cloud Data Migration Plan (not executed)

**Prerequisite:** PH056 Path B fresh Cloud Database with PH058 canonical schema applied via governed migrations only.

### 7.1 Identity strategy

| Concern | Plan |
|---------|------|
| **Primary keys** | Preserve `public_id` (uuid) as cross-environment key; build `entity_id_map (source_env, table, old_id, new_id, public_id)` audit table during migration |
| **bigint `id`** | May differ on Cloud after insert — **never** use for sync; use `public_id` |
| **song_code** | Copy verbatim; validate `unique(band_id, song_code)` before insert |
| **Cue numbers** | Copy `cue_number` per song; validate `unique(song_id, cue_number)` |

### 7.2 Migration phases (documentation)

| Phase | Scope | Verification |
|-------|-------|--------------|
| **D0** | Forensic export Live Stage `pg_dump` + row counts | Count manifest |
| **D1** | Reference data: `song_moods`, `time_signatures`, `musical_keys`, `instrument_reference` | Row counts match |
| **D2** | `bands`, `musicians`, `people` + children | FK integrity check |
| **D3** | `users` merge insert with `public_id` + portal columns | No duplicate username |
| **D4** | Music library: `songs`, `cues`, `instrument_parts`, `song_instrument_parts`, `charts`, `snippets` | song_code + cue parity |
| **D5** | Shows: `ableton_show_files`, `shows`, `show_playlist_items`, `performances` | Playlist order preserved |
| **D6** | `import_batches`, `import_entity_mappings` | Audit trail intact |
| **D7** | Assignments, capabilities, devices | Orphan check |

### 7.3 File transfer

| Asset | Plan |
|-------|------|
| Chart PDFs | Upload to Spaces; `storage_reference` + `checksum` on Cloud row |
| Snippet binaries | Same — Spaces canonical, not DB blob |
| Person files | Spaces per PH045 governance |
| Profile photos | Spaces path in `profile_photo_path` |

### 7.4 Duplicate detection

- `charts`: dedupe on `(song_id, checksum)` — already unique
- `songs`: dedupe on `(band_id, song_code)`
- `people`: match on `public_id` first; secondary match on email+legal name for operator review
- `musicians` vs `people`: **do not auto-merge** — Person↔Musician mapping is follow-up (PH045-154)

### 7.5 Conflict handling

- Conflicting row versions: operator review queue — no silent overwrite (PH054)
- Empty Cloud starting point (Path B): no conflicts — copy only
- PITR path (Path A): conflicts likely — defer to PH056 operator choice

### 7.6 Rollback

- Pre-migration Cloud snapshot (PH056 §4.1)
- `entity_id_map` enables targeted rollback delete by batch
- Live Stage `esb_dev` unchanged until Cloud verification signed off

### 7.7 Verification counts

Post-migration checklist: per-table `COUNT(*)` Live Stage vs Cloud; `SUM` checksum on chart metadata; spot-check 10 songs for cue count parity; `users` with `person_id` link count.

---

## 8. Live Stage Rebuild / Realignment Plan (post-Cloud stabilisation)

### 8.1 Schema parity

1. Retire duplicate `server/` shared migrations.
2. Publish **Cloud Canonical Migration Manifest** (CCMM) — ordered list of migration files for shared entities.
3. Live Stage applies **same CCMM** to empty or rebuilt `esb_dev` / Live Stage Database.
4. Apply Live Stage **runtime superset migrations** after CCMM (effects, runtime, console learning).

### 8.2 Initial pull from Cloud

| Step | Action |
|------|--------|
| R1 | Cloud marked published-canonical for band scope |
| R2 | Live Stage runs governed **pull** (Published Package model per PH005 — future implementation) |
| R3 | Populate shared tables from Cloud connection or package export |
| R4 | Download file cache for charts/snippets to local storage |
| R5 | Apply runtime-only migrations on top |

### 8.3 Offline local state

- After pull, Live Stage holds full shared replica
- Offline edits create data-state divergence only
- PH054 checkout records: environment, user, base version, local version
- Inbound cloud sync blocked during `live` performance state (DATABASE_ARCHITECTURE §3)

### 8.4 Checkout / versioning compatibility

Canonical schema includes no checkout columns yet — PH054 implementation adds:

- `asset_checkouts` (or equivalent) on Cloud **and** Live Stage with identical definition
- Version columns on `songs`, `charts` when sync engine implemented

PH058 does not define checkout tables — flagged as **follow-up migration** after CCMM stabilised.

### 8.5 Runtime-only tables (remain Live Stage)

Never pulled from Cloud; created locally:

`runtime_*`, `soundchecks`, `readiness_records`, `console_learning_*`, `show_console_baselines`, `effect_*`, `effects`, `song_effect_assignments`, `integration_*`, `performance_device_assignments`.

---

## 9. Operator Decisions Required

| # | Decision | Default recommendation |
|---|----------|------------------------|
| 1 | Accept Cloud-first canonical authority (PH058) | Yes |
| 2 | Include `venues` / `festivals` in CCMM | Yes — tour collaboration |
| 3 | Unified `users` merge schema | Yes — §5 merge |
| 4 | Retire `server/` duplicate shared migrations | Yes |
| 5 | PH056 recovery path before data migration | Path B fresh Cloud DB |
| 6 | Spatie permissions on Cloud only vs both workspaces | Both — director portal may share Cloud auth |
| 7 | Venues/festivals scope | Include now vs defer |
| 8 | Person↔Musician link migration | Defer post-CCMM (PH045-154) |

---

## 10. Risks

| Risk | Level | Mitigation |
|------|-------|------------|
| Cloud-first while Cloud schema incomplete | **Critical** | PH058 CCMM must be complete before PH056 execution |
| `users` merge breaks director Breeze email auth | **High** | Unified migration + app-layer validation |
| Chart file transfer incomplete | **High** | Checksum verification per chart |
| Parallel migration forks continue | **Critical** | Freeze shared DDL in `server/` immediately |
| PH054 checkout tables undefined | **Medium** | Follow-up after CCMM |
| Production `defaultdb` contamination | **Critical** | PH056 Path B — do not reconcile in place |
| `invite_links` data loss on replacement | **Low** | Table empty/unused on production forensic |

---

## 11. Confirmation

| Check | Status |
|-------|--------|
| Production mutation | **None** |
| Migrations executed | **None** |
| DDL / data changes | **None** |
| Deploy | **None** |
| Feature work | **None** |
| Documentation | `docs/PH058_CLOUD_FIRST_SCHEMA_STABILISATION_PLAN.md` created |

---

## 12. Implementation Sequence (governance only — not authorised)

1. Record PH058 in `docs/DECISION_LOG.md` (operator request)
2. Publish CCMM migration file list
3. PH056 forensic export
4. Provision isolated Cloud Database
5. Apply CCMM to empty Cloud Database
6. Data migration phases D0–D7
7. Rebuild Live Stage from Cloud + runtime superset
8. PH048B unblocked after verification

---

End of PH058 — planning only
