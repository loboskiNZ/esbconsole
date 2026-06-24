# PH061 — Cloud Recovery Execution Plan

Status: Planning only — **no production mutation, migrations, DDL, deploys, data migration, sync, or schema changes**  
Authority: PH059 CCMM, PH060 Gap Analysis, PH056 Path B (operator-approved for planning)  
Date: 2026-06-24

---

## 1. Recovery Architecture

### 1.1 Target topology

```
┌─────────────────────────────────────────────────────────────┐
│  CLOUD (DigitalOcean Managed PostgreSQL — NEW cluster)      │
│  Database: esb_cloud (name TBD by operator)                 │
├─────────────────────────────────────────────────────────────┤
│  Workspaces:                                                │
│    • Cloud Studio  → band.edandtheshadowboys.com (/server/) │
│    • Website       → edandtheshadows Forge site (co-tenant) │
│  Schema: PH059 CCMM (35 shared ESB tables)                  │
│  + Laravel infra + person_invitations (PH048B)              │
│  EXCLUDES: invite_links*, runtime_*, effect_*, integration_*  │
└─────────────────────────────────────────────────────────────┘
                              │
                    governed pull / publish
                    (post-recovery; PH054 future)
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  LIVE STAGE (local PostgreSQL — esb_dev / runtime volume)   │
│  Workspace: Director + Local Show Runtime (/backend/)       │
├─────────────────────────────────────────────────────────────┤
│  Schema: CCMM identical + Live Stage superset (PH059 Part B)│
│  Runtime authority: rehearsal, performance, offline, X32    │
└─────────────────────────────────────────────────────────────┘

* invite_links quarantined — not on fresh Cloud
```

### 1.2 Authority boundaries

| Concern | Authority | Database |
|---------|-----------|----------|
| **Schema definition (shared entities)** | CCMM → Cloud-first DDL | Both must match |
| **Durable system of record** | Cloud | Cloud Database |
| **Backup / restore / rebuild source** | Cloud | Cloud Database |
| **Rehearsal / performance execution** | Live Stage | Live Stage Database |
| **Offline operation** | Live Stage | Live Stage Database |
| **Console / Ableton runtime** | Live Stage | Live Stage superset tables |
| **Song asset data-state (PH054)** | Peer environments | Checkout/version — future |
| **Timeline during performance** | Ableton | External — not DB-canonical |

### 1.3 Sync responsibilities (post-recovery)

| Direction | When | Mechanism (planned) |
|-----------|------|---------------------|
| Live Stage → Cloud | Publish / operator sync | Governed package; PH054 checkout |
| Cloud → Live Stage | Pre-show pull | Published Package; sync-before-show |
| Runtime → Cloud | Post-performance optional | Logs/audit archive — not live cue state |
| Cloud → Live Stage during `live` | **Blocked** | DATABASE_ARCHITECTURE live boundary |

**PH061 does not implement sync** — defines prerequisites only.

### 1.4 Forensic legacy

| Asset | Disposition |
|-------|-------------|
| Production `defaultdb` | Read-only forensic; export retained |
| Historical `server/` + `backend/` migrations in Git | **Preserved for audit** — not executed on fresh Cloud |
| `invite_links` on forensic DB | Quarantined; no migration |

---

## 2. CCMM Implementation Plan (per entity)

| Entity | Action | Rationale |
|--------|--------|-----------|
| bands | **EXTEND** | RETAIN core; add `primary_director_musician_id` after musicians |
| users | **MERGE** | CREATE unified CCMM schema; add `public_id`; retain portal columns |
| people | **RETAIN** | ALIGNED with CCMM |
| person_secure_fields | **RETAIN** | ALIGNED |
| person_files | **RETAIN** | ALIGNED |
| person_iem_settings | **RETAIN** | ALIGNED |
| musicians | **CREATE** | MISSING from Cloud |
| musician_band_roles | **CREATE** | MISSING |
| instrument_reference | **RETAIN** | ALIGNED; verify seed |
| person_instruments | **RETAIN** | ALIGNED |
| instrument_parts | **RETAIN** | ALIGNED |
| songs | **RETAIN** | ALIGNED in server migrations |
| song_moods | **RETAIN** + seed | PARTIAL — table exists |
| time_signatures | **RETAIN** + seed | PARTIAL |
| musical_keys | **RETAIN** + seed | PARTIAL |
| charts | **EXTEND** | RETAIN columns; add FK to import_batches |
| song_instrument_parts | **RETAIN** | ALIGNED |
| import_batches | **CREATE** | MISSING; charts FK parent |
| import_entity_mappings | **CREATE** | MISSING |
| ableton_show_files | **CREATE** | MISSING; shows dependency |
| shows | **CREATE** | MISSING |
| show_playlist_items | **CREATE** | MISSING |
| performances | **CREATE** | MISSING |
| performance_assignments | **CREATE** | MISSING |
| devices | **CREATE** | MISSING |
| capabilities | **CREATE** | MISSING |
| assignments | **CREATE** | MISSING |
| cues | **CREATE** | MISSING |
| action_types | **CREATE** + seed | MISSING |
| action_definitions | **CREATE** | MISSING |
| action_parameters | **CREATE** | MISSING |
| cue_actions | **CREATE** | MISSING |
| snippets | **CREATE** | MISSING |
| venues | **CREATE** | MISSING |
| festivals | **CREATE** | MISSING |
| invite_links | **QUARANTINE** | Not CCMM; exclude from fresh build |
| invite_link_acceptances | **QUARANTINE** | Not CCMM |
| person_invitations | **CREATE** (post-core) | PH048B; Cloud workspace only |

---

## 3. Migration Package Plan

Packages are **documentation groupings** for PH062 migration file authoring. **No migration files in PH061.**

### CCMM-00 — Laravel Infrastructure

| Contents | Notes |
|----------|-------|
| `cache`, `cache_locks` | Laravel |
| `jobs`, `job_batches`, `failed_jobs` | queue |
| `sessions`, `password_reset_tokens` | auth infra |

**Not CCMM ESB entities** — required for Cloud Studio.

### CCMM-01 — Foundation

| Tables | Action |
|--------|--------|
| `bands` | CREATE full CCMM (incl. `primary_director_musician_id` nullable; FK added in CCMM-04) |

### CCMM-02 — Reference Data

| Tables | Action |
|--------|--------|
| `instrument_reference` | CREATE |
| `song_moods`, `time_signatures`, `musical_keys` | CREATE |
| Seeders: `InstrumentCatalog`, `SongMetadataReferenceSeeder` | F2 |

### CCMM-03 — People

| Tables | Action |
|--------|--------|
| `people` | CREATE (PH045 + profile columns) |
| `person_secure_fields`, `person_files`, `person_iem_settings` | CREATE |
| `person_instruments` | CREATE |

### CCMM-04 — Identity & Roster

| Tables | Action |
|--------|--------|
| `users` | CREATE merged CCMM |
| `musicians`, `musician_band_roles` | CREATE |
| `bands` | EXTEND FK `primary_director_musician_id` |

### CCMM-05 — Music Library Core

| Tables | Action |
|--------|--------|
| `songs` | CREATE full CCMM |
| `cues` | CREATE |
| `instrument_parts` | CREATE |

### CCMM-06 — Charts & Import Audit

| Tables | Action |
|--------|--------|
| `import_batches`, `import_entity_mappings` | CREATE |
| `charts` | CREATE with FK |
| `song_instrument_parts` | CREATE |
| `snippets` | CREATE (PH028) |

### CCMM-07 — Actions

| Tables | Action |
|--------|--------|
| `action_types` | CREATE + seed |
| `action_definitions`, `action_parameters`, `cue_actions` | CREATE |

### CCMM-08 — Shows & Performances

| Tables | Action |
|--------|--------|
| `ableton_show_files` | CREATE |
| `shows`, `show_playlist_items` | CREATE |
| `performances`, `performance_assignments` | CREATE |

### CCMM-09 — Devices & Assignments

| Tables | Action |
|--------|--------|
| `devices`, `capabilities`, `assignments` | CREATE |

### CCMM-10 — Venues & Festivals

| Tables | Action |
|--------|--------|
| `venues`, `festivals` | CREATE |

### CCMM-11 — Invitations (Cloud workspace)

| Tables | Action |
|--------|--------|
| `person_invitations` | CREATE per PH059 Part C |

**Excluded from all packages:** `invite_links`, `invite_link_acceptances`, Live Stage superset (PH059 Part B).

### Package dependency order

```
CCMM-00 → CCMM-01 → CCMM-02 → CCMM-03 → CCMM-04 → CCMM-05 → CCMM-06 → CCMM-07 → CCMM-08 → CCMM-09 → CCMM-10 → CCMM-11
```

### Migration ownership (PH062)

| Location | Rule |
|----------|------|
| `database/migrations/ccmm/` (proposed) | Cloud-first CCMM packages — single authority |
| `server/database/migrations/` | Laravel infra + **no new shared entity DDL**; retire Part D duplicates |
| `backend/database/migrations/` | Live Stage superset only after CCMM parity path |

Historical files **preserved in Git** for audit — not run on fresh Cloud.

---

## 4. Fresh Cloud Build Plan

### F0 — Provision

| Step | Action | Owner |
|------|--------|-------|
| F0.1 | DO snapshot/export forensic `defaultdb` (PH056 §4.1) | Operator |
| F0.2 | Provision **new** DO PostgreSQL cluster | Operator |
| F0.3 | Create database + user; SSL; record credentials in Forge **only** for Cloud Studio (+ Website if co-tenant) | Operator |
| F0.4 | Update `band.edandtheshadowboys.com` `.env` `DB_*` — **do not deploy until F5 pass** | Operator |
| F0.5 | Confirm `backend/` and Website **not** pointed at new cluster until Live Stage realignment phase | Operator |

### F1 — Apply CCMM packages

| Step | Action |
|------|--------|
| F1.1 | Deploy CCMM migration files (PH062) via `php artisan migrate` from `/server/` **only** |
| F1.2 | Execute packages CCMM-00 through CCMM-10 in order |
| F1.3 | **Do not** run `backend/artisan migrate` against Cloud Database |
| F1.4 | Record `migrations` table export post-F1 |

### F2 — Seed reference data

| Seeder | Tables |
|--------|--------|
| Default band | `bands` (id=1, operator-approved) |
| `InstrumentCatalog` | `instrument_reference` |
| `SongMetadataReferenceSeeder` | `song_moods`, `time_signatures`, `musical_keys` |
| Action types seed | `action_types` |

**No production identity seed** without operator approval.

### F3 — Validate schema

See §8 Schema acceptance. **Gate 3.**

### F4 — Load migrated data

See §5 Live Stage Data Migration. Execute only after F3 pass.

### F5 — Validate integrity

See §8 Data + file acceptance. **Gate 4.**

### F6 — Enable application

| Step | Action |
|------|--------|
| F6.1 | Point Band Portal Forge site to new cluster (if not done F0) |
| F6.2 | Deploy application release via governed `remote-deploy.sh` — **only after Gate 5** |
| F6.3 | Smoke test: `/up`, login scaffold, Studio read paths |
| F6.4 | **Do not** enable onboarding (PH048B) until `person_invitations` (CCMM-11) + operator sign-off |

---

## 5. Live Stage Data Migration Plan

**Source:** Live Stage Database `esb_dev` (local) — authoritative for existing dev data.  
**Destination:** Fresh Cloud Database post-F3.  
**Method:** Governed export/import scripts (PH062+) — not ad hoc SQL.

### 5.1 Cross-cutting strategy

| Concern | Strategy |
|---------|----------|
| **Primary key** | Preserve `public_id`; remap `id` via `cloud_recovery_entity_map` |
| **Conflict** | Fresh Cloud empty → insert only; conflicts only if re-run |
| **Validation** | Row counts + FK checks per domain |
| **Order** | Follow package dependency (§3) |

### 5.2 `cloud_recovery_entity_map` (audit table — created in PH062)

| Column | Purpose |
|--------|---------|
| source_env | `live_stage` |
| table_name | entity |
| source_id | bigint from Live Stage |
| cloud_id | bigint on Cloud |
| public_id | uuid |
| migrated_at | timestamp |
| batch_id | recovery batch UUID |

Supports rollback and PH054 future sync.

### 5.3 Domain plans

| Domain | Source tables | Destination | Key strategy | Conflicts | Validation |
|--------|---------------|-------------|--------------|-----------|------------|
| **bands** | bands | bands | public_id | none (fresh) | count=1 |
| **users** | users | users | generate public_id if missing | username unique | count; no null public_id |
| **people** | people + children | same | public_id | — | FK person_id chain |
| **musicians** | musicians, musician_band_roles | same | public_id | — | count match |
| **songs** | songs | songs | public_id + song_code | unique(band_id,song_code) | code parity |
| **charts** | charts | charts | public_id; storage_reference | checksum unique per song | count + checksum |
| **cues** | cues | cues | public_id; cue_number per song | unique(song_id,cue_number) | SSS.CCC spot check |
| **snippets** | snippets | snippets | public_id | — | count per song |
| **shows** | ableton_show_files, shows, show_playlist_items | same | public_id | playlist position | order preserved |
| **performances** | performances, performance_assignments | same | public_id | — | FK to show/musician |
| **devices** | devices, capabilities, assignments | same | public_id | — | orphan check |
| **import audit** | import_batches, import_entity_mappings | same | public_id | — | manifest JSON intact |

**Not migrated from Live Stage to Cloud:** runtime_*, effect_*, soundchecks, readiness_records, console_learning_*, integration_*, performance_device_assignments.

**Not migrated from forensic production:** invite_links, contaminated migration history.

---

## 6. File Migration Plan

### 6.1 Charts

| Item | Detail |
|------|--------|
| **Source (dev)** | Live Stage local cache + `storage_reference` paths |
| **Source (legacy)** | Operator chart library paths (`PORTAL_LIBRARY_*`, local `storage/app/library`) |
| **Destination** | DigitalOcean Spaces bucket (Cloud-canonical) |
| **DB field** | `charts.storage_reference`, `checksum`, `mime_type`, `file_size` |
| **Verification** | SHA256 checksum match post-upload; `COUNT(charts)` = files uploaded |

### 6.2 Snippets

| Item | Detail |
|------|--------|
| **Source** | Live Stage snippet binaries per `storage_reference` |
| **Destination** | Spaces |
| **Verification** | Checksum per snippet row |

### 6.3 Media assets

| Asset | Source | Destination |
|-------|--------|-------------|
| Person files | `person_files.file_path` | Spaces private |
| Profile photos | `people.profile_photo_path` | Spaces |
| Ableton show files | `ableton_show_files.storage_reference` | Spaces |

### 6.4 Verification procedure

1. Export manifest: `{entity, public_id, storage_reference, checksum}` from Live Stage  
2. Upload to Spaces with same checksum algorithm (SHA256)  
3. Update Cloud rows if path convention changes (document prefix scheme)  
4. Re-query checksums; **0 mismatches** required for Gate 4  
5. Sample open 10 chart PDFs via signed URL smoke test  

---

## 7. Identity Reconciliation Plan

### 7.1 `public_id` strategy

| Entity | Rule |
|--------|------|
| All CCMM entities with `public_id` | Copy from Live Stage when present |
| `users` missing `public_id` | Generate uuid v4 on migrate; **never** reuse email as identity |
| Runtime identity | Song Code + Cue Number unchanged (PH010.01) — not `public_id` |

### 7.2 bigint key handling

- Live Stage `id` → Cloud `id` may differ  
- `cloud_recovery_entity_map` records mapping  
- Application code must use `public_id` for cross-environment references in recovery tooling  
- FK inserts use map to resolve parent `cloud_id`

### 7.3 PH054 compatibility

- CCMM v1 has no version columns  
- `public_id` + `song_code`/`cue_number` support future checkout model  
- Recovery map table retained for conflict resolution tooling

### 7.4 Rollback (identity)

- Drop Cloud data batch by `batch_id` using map table reverse order  
- Live Stage unchanged during Cloud-only recovery  
- Forensic export remains restore of last resort

---

## 8. Verification Plan

### 8.1 Schema acceptance (Gate 3)

| Check | Criterion |
|-------|-----------|
| CCMM entity count | 35 shared tables present |
| Quarantine absent | no `invite_links` on fresh Cloud |
| FK validation | `php artisan` or SQL: zero orphan FKs |
| Index validation | CCMM unique indexes exist (spot-check via `pg_indexes`) |
| users schema | `public_id`, `username`, `person_id`, `band_id`, `is_active` present |
| charts FK | `import_batch_id` → `import_batches` |

### 8.2 Data acceptance (Gate 4)

| Check | Criterion |
|-------|-----------|
| Row counts | Live Stage vs Cloud per §5.3 — match ±0 |
| song_code | all songs have 3-char code; unique per band |
| cue_number | unique per song |
| Reference seeds | song_moods ≥1; time_signatures ≥1; musical_keys ≥1; instrument_reference ≥17 |
| users | 0 rows acceptable initially; if migrated, all have public_id |

### 8.3 File acceptance (Gate 4)

| Check | Criterion |
|-------|-----------|
| Chart checksums | 100% match manifest |
| Snippet checksums | 100% match if snippets migrated |
| No orphan files | every `storage_reference` resolvable |

### 8.4 Application acceptance (Gate 5)

| Surface | Test |
|---------|------|
| Health | `GET /up` → 200 |
| Database | login page loads without 500 |
| Charts | Studio library read (if data present) |
| Shows | N/A until data migrated |
| Onboarding | **disabled** until CCMM-11 + PH048B |

---

## 9. Rollback Plan

| Phase | Rollback trigger | Rollback action | Evidence |
|-------|------------------|-----------------|----------|
| F0 | Wrong cluster credentials | Do not migrate; delete empty DB | F0 export |
| F1 | migrate failure mid-package | `migrate:rollback` on Cloud only if safe; else drop DB reprovision | migrations export |
| F2 | Bad seed data | Truncate seeded tables; re-seed | seed log |
| F3 | Schema check fail | Stop; do not F4; fix forward in PH062 | validation report |
| F4 | Data import failure | Delete batch via map table; restore from pre-F4 snapshot | map batch_id |
| F5 | Checksum mismatch | Pause; do not F6; fix files re-upload | file manifest |
| F6 | App 500 post-cutover | Revert Forge `.env` to forensic read-only cluster; maintenance page | deploy log |

**Never** rollback Live Stage DB during Cloud recovery. **Never** manual `INSERT INTO migrations`.

---

## 10. Production Safety Gates

| Gate | Requirement | Blocks |
|------|-------------|--------|
| **Gate 1** | Forensic exports complete (PH056 §4.1) | F0+ |
| **Gate 2** | Operator sign-off on PH061 plan | F0+ |
| **Gate 3** | Cloud schema validation pass (§8.1) | F4, F6 |
| **Gate 4** | Data + file validation pass (§8.2–8.3) | F6 |
| **Gate 5** | Application validation pass (§8.4) | Public onboarding, PH048B |
| **Gate 6** | Incident closure recorded in DECISION_LOG | Decommission forensic DB |

All gates require **written operator approval** in incident log.

---

## 11. Live Stage Realignment Plan (post-Cloud stabilisation)

### 11.1 Schema parity process

| Step | Action |
|------|--------|
| LS-1 | Freeze Live Stage schema changes |
| LS-2 | Apply same CCMM-00–10 migrations to Live Stage Database (empty or migratеd) |
| LS-3 | Apply Live Stage superset packages (runtime, effects, console) **after** CCMM |
| LS-4 | Verify shared table `information_schema` match Cloud |

### 11.2 Initial replication

| Step | Action |
|------|--------|
| LS-5 | Pull shared entity rows from Cloud (or re-run migration map import) |
| LS-6 | Pull file cache from Spaces to local storage |
| LS-7 | Runtime tables remain local-empty until show day |

### 11.3 Offline operation

- Live Stage holds full shared replica post-pull  
- Offline edits = data-state divergence only  
- No schema changes without CCMM update on **both** databases

### 11.4 Sync readiness (PH054 future)

| Prerequisite | Status after PH061 execution |
|--------------|------------------------------|
| Schema parity | Ready |
| public_id on all entities | Ready |
| Version/checkout columns | **Not in CCMM v1** — PH062+ follow-up |
| Diff engine | Not implemented |

### 11.5 PH054 compatibility

Recovery establishes **identical schema + public_id identity**. Checkout/version/sync engine is separate implementation phase — not blocked by PH061 completion but not included.

---

## 12. Remaining Operator Decisions

| # | Decision | Default |
|---|----------|---------|
| 1 | New Cloud cluster name and Forge credential cutover date | Required |
| 2 | Website co-tenancy on new cluster | Per PH056 |
| 3 | Migrate Live Stage `esb_dev` data vs empty Cloud start | Migrate if dev data wanted |
| 4 | CCMM-11 `person_invitations` timing vs PH048B | After Gate 5 |
| 5 | Spatie RBAC on Cloud — include in CCMM-00 or separate | Operator |
| 6 | Decommission forensic `defaultdb` date | After Gate 6 |
| 7 | Person ↔ Musician linking migration | Defer post-recovery |
| 8 | Approve PH062 migration file location (`database/migrations/ccmm/`) | Required before code |

---

## 13. PH062 Handoff

PH061 authorises **planning complete**. PH062 will:

1. Author CCMM migration PHP files per packages §3  
2. Author recovery import tooling  
3. Author validation scripts  
4. **Still no production execution** until Gate 2 sign-off per runbook  

---

End of PH061 — execution plan only; no implementation
