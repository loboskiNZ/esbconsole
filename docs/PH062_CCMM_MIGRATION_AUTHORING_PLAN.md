# PH062 — CCMM Migration Authoring Plan

Status: Authoring plan — **migration files may be written; no production execution, deploy, data migration, or cluster cutover**  
Authority: PH059 CCMM, PH060 Gap Analysis, PH061 Execution Plan, PH061A (Track B deferred)  
Date: 2026-06-24

---

## 1. Purpose

PH062 defines **how** Cloud-first CCMM migration PHP files are authored, organised, validated, and retired from duplicate paths. It is the implementation blueprint for PH061 §3 migration packages.

| In scope | Out of scope |
|----------|--------------|
| Migration file layout, naming, package mapping | Production cluster provisioning (PH061 F0) |
| Authoring rules per CCMM entity | Live Stage data import execution (PH061 F4) |
| Retirement of `server/` duplicate DDL | PH054 sync engine |
| Fresh-DB validation strategy | Forge cutover |
| `cloud_recovery_entity_map` authoring | CCMM-12 Track B implementation (planning only) |
| Live Stage superset package outline | `person_invitations` UX (PH048B) |

**PH062 authorises writing migration files in Git.** **PH063** (or operator-runbook Gate 2+) authorises **running** them against a target database.

---

## 2. Tracks

| Track | Packages | When | Gate |
|-------|----------|------|------|
| **Track A — Core platform** | CCMM-00 → CCMM-11 | **First** — blocks Cloud recovery | Operator Gate 2 + fresh DB |
| **Track B — X32 console domain** | CCMM-12a → CCMM-12c | **After** Track A Gate 4 or parallel authoring | PH061A + operator Track B approval |
| **Track C — Live Stage superset** | LS-EXT-01+ | **After** Track A schema parity on Live Stage | PH061 §11 |

PH062 **must complete Track A authoring** before any production migrate. Track B files may be drafted in parallel but **must not** run on Cloud before Track A Gate 4 pass.

---

## 3. Repository layout

### 3.1 Canonical location (new)

```
/database/migrations/ccmm/          ← single CCMM authority (repo root)
/database/migrations/ccmm/README.md
/database/migrations/recovery/      ← cloud_recovery_entity_map + import audit (PH062)
/database/seeders/ccmm/             ← governed reference seeds (optional co-location)
```

### 3.2 Application load rules

| App | Loads | Does not load |
|-----|-------|---------------|
| **`/server/`** (Cloud Studio) | `database/migrations/ccmm/*`, `database/migrations/recovery/*`, Laravel infra in `server/database/migrations/0001_*` only | `backend/` superset; quarantined invite migrations on fresh Cloud |
| **`/backend/`** (Live Stage) | Same CCMM path **after** LS-2 parity step, then `backend/database/migrations/ls-ext/*` | CCMM duplicated inside `backend/` M-series for shared entities |

**Implementation:** Register CCMM path in each app's migration loader (e.g. `AppServiceProvider::boot` → `loadMigrationsFrom` pointing at repo-root `database/migrations/ccmm`). Exact wiring is PH062 implementation detail.

### 3.3 Historical files

| Path | Disposition |
|------|-------------|
| `server/database/migrations/2026_06_23_*` shared DDL | **Archive** — move to `server/database/migrations/_archived_ccmm_forks/`; exclude from migrate path |
| `server/database/migrations/2026_06_24_*` shared DDL | **Archive** |
| `server/database/migrations/*invite*` | **Archive** — never on fresh Cloud |
| `backend/database/migrations/*m2*`, `*m3*`, … `*m9*` shared | **Retain in Git** for audit; **replace** with CCMM path on Live Stage realignment |
| `backend/database/migrations/*runtime*`, `*integration*`, `*console_learning*`, `*ph044*` | **LS-EXT** — Live Stage superset only |

---

## 4. File naming convention

```
YYYY_MM_DD_HHMMSS_ccmm_{package}_{slug}.php
```

| Segment | Rule | Example |
|---------|------|---------|
| Timestamp | UTC order within package | `2026_07_01_100000` |
| `ccmm` | Fixed marker | `ccmm` |
| `{package}` | `ccmm00` … `ccmm11`, `ccmm12a` | `ccmm04` |
| `{slug}` | snake_case table or action | `create_musicians_table` |

**One logical package step per file** where practical. Multi-table packages may use sequential files in dependency order within the same package.

**Idempotency:** Fresh Cloud target is empty — migrations use `Schema::create`, not `Schema::createIfNotExists` guards, except recovery tooling tables.

---

## 5. Track A — package file plan

Each row is an **authoring unit**. Column definitions are **PH059 Part A** authority. Backend migrations listed as **reference only** — CCMM files are authored Cloud-first, not copied.

### CCMM-00 — Laravel infrastructure

| File slug | Tables | Notes |
|-----------|--------|-------|
| `ccmm00_laravel_cache_jobs` | cache, cache_locks, jobs, job_batches, failed_jobs | Retain in `server/database/migrations/0001_*` **or** consolidate here — operator chooses single path |
| `ccmm00_laravel_sessions_auth` | sessions, password_reset_tokens | users table **not** here — CCMM-04 |

**Rule:** Laravel infra stays runnable before CCMM-01. If kept in `0001_*`, CCMM-00 package is documentation-only grouping.

### CCMM-01 — Foundation

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm01_create_bands_table` | bands | A1 | `server/130000`, `backend/m2` |

Include `public_id`, `name`; `primary_director_musician_id` nullable **without FK** until CCMM-04.

### CCMM-02 — Reference data

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm02_create_instrument_reference` | instrument_reference | A9 | `server/131000` |
| `ccmm02_create_song_metadata_reference` | song_moods, time_signatures, musical_keys | A13–A15 | `server/220100`, `backend/220000` |
| `ccmm02_seed_reference_data` | — | seed | `InstrumentCatalog`, `SongMetadataReferenceSeeder` |

Seeder may be PHP migration calling seeder class or dedicated `database/seeders/ccmm/`.

### CCMM-03 — People

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm03_create_people_table` | people | A3 | `server/131000`, `backend/110000` |
| `ccmm03_create_person_children` | person_secure_fields, person_files, person_iem_settings | A4–A6 | same |
| `ccmm03_create_person_instruments` | person_instruments | A10 | same |

### CCMM-04 — Identity & roster

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm04_create_users_merged` | users | A2 | `server/0001` + `133000`; **add `public_id`** |
| `ccmm04_create_musicians` | musicians, musician_band_roles | A7–A8 | `backend/m3`, `backend/190000` |
| `ccmm04_extend_bands_director_fk` | bands | A1 FK | nullable → musicians |

**Critical:** `users` is **CREATE** on fresh Cloud (merged schema), not ALTER of skeleton.

### CCMM-05 — Music library core

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm05_create_songs` | songs | A12 | `server/160000`, `backend/m6`, authoring cols |
| `ccmm05_create_cues` | cues | A28 | `backend/m7` |
| `ccmm05_create_instrument_parts` | instrument_parts | A11 | `server/160000`, `backend/m4` |

### CCMM-06 — Charts & import audit

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm06_create_import_batches` | import_batches, import_entity_mappings | A18–A19 | `backend/100000` |
| `ccmm06_create_charts` | charts | A16 | FK `import_batch_id` → import_batches |
| `ccmm06_create_song_instrument_parts` | song_instrument_parts | A17 | `backend/100500` |
| `ccmm06_create_snippets` | snippets | A33 | `backend/ph028` — **music** snippets |

### CCMM-07 — Actions

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm07_create_action_types` | action_types | A29 | `backend/runtime_action` |
| `ccmm07_seed_action_types` | — | seed | action type catalogue |
| `ccmm07_create_action_domain` | action_definitions, action_parameters, cue_actions | A30–A32 | `backend/runtime_action` |

### CCMM-08 — Shows & performances

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm08_create_ableton_show_files` | ableton_show_files | A20 | `backend/110000` |
| `ccmm08_create_shows` | shows, show_playlist_items | A21–A22 | `backend/m8`, `110200` |
| `ccmm08_create_performances` | performances, performance_assignments | A23–A24 | `backend/110300`, `110400` |

### CCMM-09 — Devices & assignments

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm09_create_devices_capabilities` | devices, capabilities | A25–A26 | `backend/m3`, `m4` |
| `ccmm09_create_assignments` | assignments | A27 | `backend/m5` |

**Note:** PH059 Part E groups devices earlier (CCMM-5). PH061 package order (devices after shows) is **authoring authority** — FK graph allows either; PH062 follows PH061 §3.

### CCMM-10 — Venues & festivals

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm10_create_venues_festivals` | venues, festivals | A34–A35 | `backend/210000`, `220000` |

### CCMM-11 — Invitations (Cloud workspace)

| File slug | Tables | CCMM | Reference |
|-----------|--------|------|-----------|
| `ccmm11_create_person_invitations` | person_invitations | Part C | PH048B spec |

**Live Stage:** does not migrate CCMM-11 on parity apply (Cloud-only workspace table).

### CCMM-RECOVERY — Recovery audit

| File slug | Tables | Notes |
|-----------|--------|-------|
| `recovery_create_entity_map` | cloud_recovery_entity_map | PH061 §5.2; run before F4 data import |

---

## 6. Track B — X32 console domain (authoring deferred)

Planning authority: PH061A. **Do not author until operator approves Track B** or parallel draft with `// @ccmm-track-b` marker and excluded from default migrate manifest.

| Package | After | Tables | Source reference |
|---------|-------|--------|------------------|
| CCMM-12a | CCMM-05 | effect_definitions, effect_packages, effect_package_items, song_effect_assignments, effects, effect_parameters, effect_package_types, effect_package_item_parameters, effect_package_item_target_sections | `backend/ph044_*` |
| CCMM-12b | CCMM-08 | show_console_baselines | `backend/console_learning` |
| CCMM-12c | CCMM-07 | mix_moves | DOMAIN_MODEL M5 — **blocked** until schema exists |

**Exclude from Cloud:** `effect_library_*` unless operator merges into `effects` catalogue (PH061A decision 5).

---

## 7. Track C — Live Stage superset outline

Applied **only after** CCMM-00–10 parity verified on Live Stage (PH061 §11).

| Package | Tables | Notes |
|---------|--------|-------|
| LS-EXT-01 | integration_devices, integration_connection_profiles | PH059 Part B |
| LS-EXT-02 | performance_device_assignments | show-day binding |
| LS-EXT-03 | console_learning_snapshots | ephemeral learn |
| LS-EXT-04 | runtime_* (action execution, events, dispatch) | execution state |
| LS-EXT-05 | soundchecks, readiness_records | operational |
| LS-EXT-06 | permission_tables (Spatie) | if not Cloud-only |

**Location:** `backend/database/migrations/ls-ext/` with `ls_ext_{nn}_{slug}.php` naming.

**CCMM-12 Track B** on Live Stage: apply 12a–12b after LS-EXT-03 if console parity required.

---

## 8. Authoring rules

| # | Rule |
|---|------|
| 1 | **PH059 column list is authoritative** — diff against reference migration; fix drift in CCMM file |
| 2 | **Every shared entity has `public_id` uuid unique** where CCMM specifies |
| 3 | **FK ON DELETE** matches CCMM (RESTRICT vs CASCADE vs SET NULL) |
| 4 | **No quarantined tables** in any CCMM file |
| 5 | **No Live Stage superset** in CCMM path |
| 6 | **One migration path** per shared table — no new duplicates in `server/` or `backend/` |
| 7 | **Down()** must drop in reverse FK order for local dev rollback |
| 8 | **Comments** only for non-obvious CCMM deviations — not column narration |
| 9 | **PostgreSQL** types only — no MySQL-specific syntax |
| 10 | **Case-insensitive unique** on `users.username` per PH047A |

### users merge checklist (DRIFTED → ALIGNED)

| Requirement | Action in CCMM-04 |
|-------------|-------------------|
| `public_id` uuid NOT NULL unique | CREATE column |
| Portal columns retained | username, person_id, band_id, is_active, email_verified_at |
| email non-unique | no unique index on email |
| password NOT NULL | default for system rows if needed in seed |

### charts FK checklist

| Requirement | Action in CCMM-06 |
|-------------|-------------------|
| `import_batches` exists first | CCMM-06 file order |
| `charts.import_batch_id` | FK constrained |

---

## 9. Retirement procedure (`server/` forks)

| Step | Action |
|------|--------|
| R1 | Author full CCMM-01–10 in `database/migrations/ccmm/` |
| R2 | Move deprecated `server/` shared migrations to `_archived_ccmm_forks/` |
| R3 | Update `server` migrate path to load CCMM root only + Laravel `0001_*` |
| R4 | Fresh `php artisan migrate` on empty local Postgres — **must reach 35 CCMM tables** |
| R5 | Document archived file → CCMM file mapping in `database/migrations/ccmm/README.md` |
| R6 | **Do not delete** archived files from Git |

---

## 10. Validation strategy (pre-production)

### 10.1 Local fresh migrate test

```bash
# Target: empty local PostgreSQL database (not production)
cd server && php artisan migrate --database=ccmm_test
```

| Check | Pass criterion |
|-------|----------------|
| Migrate completes | exit 0 |
| Table count | 35 CCMM shared + Laravel infra + recovery map |
| Quarantine absent | `invite_links` not created |
| users | `public_id` column exists |
| charts FK | `import_batch_id` references `import_batches` |
| Rollback | `migrate:rollback --step=N` for one package without orphan errors |

### 10.2 Schema diff script (PH062 deliverable)

Automated compare:

- `information_schema.columns` vs PH059 manifest extract
- `pg_indexes` unique constraints spot-check
- FK graph via `pg_constraint`

Output: `storage/ccmm_validation/report.json` — not committed.

### 10.3 PHPUnit gate (optional PH062)

Feature test: `CcmmFreshMigrateTest` — migrate fresh sqlite/pgsql in-memory, assert table exists. **sqlite only for smoke**; PostgreSQL required for CI truth.

---

## 11. Seed authoring

| Seeder | Package | Tables | Idempotent |
|--------|---------|--------|------------|
| Default band | CCMM-02 or post-01 | bands | yes — `firstOrCreate` public_id |
| InstrumentCatalog | CCMM-02 | instrument_reference | yes |
| SongMetadataReferenceSeeder | CCMM-02 | song_moods, time_signatures, musical_keys | yes |
| Action types | CCMM-07 | action_types | yes |
| Effects catalogue | CCMM-12a | effects, effect_parameters | Track B only |

**No production identity seed** without operator approval (PH061 F2).

---

## 12. Recovery tooling authoring (PH062 scope)

| Artefact | Location | Purpose |
|----------|----------|---------|
| `cloud_recovery_entity_map` migration | `database/migrations/recovery/` | ID remap audit |
| Import command stubs | `server/app/Console/Commands/Recovery/` | PH063 execution |
| Validation command | `server/app/Console/Commands/Recovery/ValidateCcmmSchema.php` | Gate 3 automation |

Import commands are **stubbed** in PH062 — implementation body executes under PH063 with Gate 2 sign-off.

---

## 13. Gates (authoring vs execution)

| Gate | Requirement | Enables |
|------|-------------|---------|
| **PH062-A** | Track A migration files merged to `main` | Local fresh migrate test |
| **PH062-B** | Fresh migrate test pass on PostgreSQL | Operator review for Gate 2 |
| **Gate 2** (PH061) | Operator sign-off on recovery plan + PH062-A/B evidence | F0 provision |
| **Gate 3** | Schema validation on **isolated Cloud DB** | F4 data import |
| **PH062-C** | Track B files authored (optional) | Track B migrate after Gate 4 |

**PH062 completion ≠ production recovery.** Production migrate requires Gate 2 + isolated cluster.

---

## 14. PH063 handoff

PH063 — **CCMM Migration Execution Runbook** — will cover:

1. F0–F6 step-by-step operator commands  
2. Data import batch execution  
3. File migration to Spaces  
4. Gate 3–6 evidence templates  
5. Live Stage realignment (LS-1–LS-7)  
6. Rollback runbook per PH061 §9  

---

## 15. Operator decisions required

| # | Decision | Default |
|---|----------|---------|
| 1 | Repo-root `database/migrations/ccmm/` vs `server/database/migrations/ccmm/` | **Repo root** (shared) |
| 2 | Laravel infra in `0001_*` vs CCMM-00 consolidated | **Keep `0001_*`** |
| 3 | Approve Track A authoring start without Gate 2 | **Yes** — Git only |
| 4 | Track B parallel authoring | **Planning drafts only** |
| 5 | Archive vs delete `server/` forks | **Archive** |
| 6 | Spatie permissions — CCMM-00 or LS-EXT | **LS-EXT** (Live Stage) |
| 7 | PHPUnit CCMM fresh migrate in CI | **Yes** when files land |

---

## 16. Risks

| Risk | Mitigation |
|------|------------|
| CCMM file diverges from PH059 | Schema diff script; manifest is authority |
| Accidental migrate on production `defaultdb` | Gate 2; `.env` cluster name check in validation command |
| Backend M-series still runs on Cloud | Remove shared paths from backend Cloud deploy |
| Track B creep blocks Track A | Separate directories; Track B excluded from default migrate |
| users CREATE breaks existing server bootstrap | Fresh Cloud only until reconcile tooling |

---

## 17. Deliverables checklist

| # | Deliverable | Status |
|---|-------------|--------|
| 1 | `database/migrations/ccmm/README.md` | PH062 implementation |
| 2 | CCMM-01–11 migration files | PH062 implementation |
| 3 | `database/migrations/recovery/` entity map | PH062 implementation |
| 4 | Archived `server/` forks | PH062 implementation |
| 5 | `ValidateCcmmSchema` command | PH062 implementation |
| 6 | `CcmmFreshMigrateTest` | PH062 implementation |
| 7 | This authoring plan | **Complete** |

---

End of PH062 — authoring plan; implementation files follow in same phase
