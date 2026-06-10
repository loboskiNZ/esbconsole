# Physical Database & Migration Plan

Status: PH007 Finalised  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: Physical database technology choices, migration strategy, initial schema plan, and delivery governance before any database implementation

Related documents:

- Logical schema design: `docs/DATABASE_ARCHITECTURE.md`
- Data ownership and persistence: `docs/DATA_ARCHITECTURE.md`
- Runtime behaviour: `docs/RUNTIME_MODEL.md`
- Integration architecture: `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`
- Infrastructure: `docs/ARCHITECTURE.md`

This document defines **technology selection**, **environment topology**, **migration ordering**, **identifier/FK/index strategies**, and **delivery governance** — not migration files, table DDL, Eloquent models, or seeders.

**Out of scope for PH007:** migrations, models, seeders, factories, API routes, application code, `.env` changes, package installs, production database configuration.

---

## 1. Purpose

PH007 establishes the physical database and migration planning gate for the Live Performance Orchestration System.

Goals:

- Select database engine(s) for cloud, Director local, and Local Show Runtime
- Define Laravel migration framework as sole schema change mechanism
- Plan initial migration domains and dependency order
- Specify identifier, FK, indexing, file asset, runtime, sync, and audit persistence strategies
- Define rollback, backup, testing, and prohibited practices
- Provide implementation readiness checklist before first migration is written

Physical schema implementation **must follow** this document, `docs/DATABASE_ARCHITECTURE.md`, and `docs/DATA_ARCHITECTURE.md`.

---

## 2. Governing References

| Document | Role in PH007 |
|----------|---------------|
| `docs/DATA_ARCHITECTURE.md` | Ownership, sync, lifecycle, file Git exclusion |
| `docs/DATABASE_ARCHITECTURE.md` | Logical domains, aggregates, entity mapping |
| `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` | Runtime state locality; bridge event logs |
| `docs/RUNTIME_MODEL.md` | Runtime state concepts; cue timeline |
| `docs/DOMAIN_MODEL.md` | Entity definitions |
| `docs/DECISION_LOG.md` | PH007 decisions 083–092 |

---

## 3. Database Technology Decision

### Recommendation: **PostgreSQL 16+ for all environments**

| Criterion | PostgreSQL | MySQL |
|-----------|------------|-------|
| Laravel first-class support | ✅ Excellent | ✅ Good |
| DigitalOcean managed DB | ✅ Managed PostgreSQL | ✅ Managed MySQL |
| Docker local support | ✅ Official `postgres` image | ✅ Official `mysql` image |
| Relational integrity | ✅ Strong FK/check constraints | ✅ Adequate |
| JSON metadata (manifests, Action params) | ✅ **JSONB** indexed, queryable | ⚠️ JSON type less mature for indexing |
| Sync package manifest storage | ✅ JSONB + checksum columns | ⚠️ Possible but weaker |
| Same engine cloud + local | ✅ **Recommended** | Possible |
| Operational simplicity (one engine) | ✅ **Single dialect, one backup tooling** | Split-brain if mixed |
| Ed's stated preference | ✅ Aligns with preference | — |

### Decision

**Use PostgreSQL consistently** across:

1. Cloud database (DigitalOcean Managed PostgreSQL)
2. Director local database (local PostgreSQL instance or Docker)
3. Local Show Runtime database (Docker PostgreSQL container)

### Why one engine everywhere

| Reason | Detail |
|--------|--------|
| **Migration parity** | Same migration files run identically in all environments |
| **Sync testing** | Package pull/push tested against same SQL dialect |
| **JSONB manifests** | Published package manifests, Action metadata, audit payloads |
| **Reduced cognitive load** | One engine for Ed to operate, backup, and troubleshoot |
| **Laravel ecosystem** | PostgreSQL is Laravel's preferred advanced-feature target |

MySQL is **not rejected** for technical failure — it is **not selected** because PostgreSQL offers stronger JSONB support and unified-environment simplicity with no operational benefit from mixing engines.

---

## 4. Cloud Database Decision

| Attribute | Value |
|-----------|-------|
| **Engine** | PostgreSQL 16+ |
| **Hosting** | DigitalOcean Managed PostgreSQL |
| **Management** | Laravel Forge provisioning |
| **Access** | Laravel app on Forge Droplet; SSL required |
| **Extensions** | `uuid-ossp` or native UUID; `pgcrypto` optional for checksums |
| **Character set** | UTF-8 |
| **Timezone** | UTC stored; local display in application layer |

Cloud database holds **published canonical** master library, operational records, sync package registry, file asset metadata (Spaces references), and long-term audit history.

---

## 5. Local Runtime Database Decision

| Attribute | Value |
|-----------|-------|
| **Engine** | PostgreSQL 16+ (same major version as cloud) |
| **Hosting** | Docker container in Local Show Runtime stack |
| **Volume** | Named Docker volume for data persistence across restarts |
| **Scope** | Performance-ready replica + runtime state + show-day logs |
| **Cloud dependency** | **None during performance** |

Local Show Runtime database is **authoritative during performance** for runtime state, Soundcheck, Readiness, and execution logs. Schema is a **superset** of cloud entity tables plus runtime-only tables.

---

## 6. Director Local Database Decision

| Attribute | Value |
|-----------|-------|
| **Engine** | PostgreSQL 16+ |
| **Hosting** | Local PostgreSQL (native install or Docker on Director workstation) |
| **Scope** | Draft creation/editing; publish staging; optional offline preparation |
| **Authority** | Director-local-canonical until publish |

Director local database uses the **same schema** as cloud. Draft records distinguished by lifecycle/publish state — not a separate schema fork.

---

## 7. Migration Framework

| Rule | Statement |
|------|-----------|
| **Sole mechanism** | Laravel migrations are the **only** approved schema change method. |
| **Version control** | All migrations committed to Git in `database/migrations/`. |
| **No manual DDL** | No manual production schema edits outside migration workflow. |
| **No ad hoc changes** | No untracked schema modifications on any environment. |
| **Rollback required** | Every migration must implement `down()` or document non-reversibility with approval. |
| **Naming** | `{YYYY}_{MM}_{DD}_{HHMMSS}_{description}.php` — Laravel convention. |
| **Grouping** | Migrations grouped by domain (see §9) — may be multiple files per domain. |
| **Governance gate** | Migrations must not be created until PH007 is complete and referenced. |
| **Review** | Show-critical migrations reviewed against DATABASE_ARCHITECTURE before merge. |

---

## 8. Environment Database Topology

### 1. Cloud database

| Attribute | Value |
|-----------|-------|
| **Purpose** | Canonical published data; collaboration; sync package registry; audit archive |
| **Authority** | Cloud-canonical after publish |
| **Data scope** | Full master library + operational records + sync/audit (no live runtime cue state) |
| **Sync direction** | Receives publish from Director Local; serves pull to Local Show Runtime |
| **Backup** | Daily automated DO managed backups + pre-publish snapshots |
| **Runtime dependency** | **Not required during live performance** |

### 2. Director local database

| Attribute | Value |
|-----------|-------|
| **Purpose** | Primary preparation; draft editing; publish staging |
| **Authority** | Director-local-canonical until publish |
| **Data scope** | Same schema as cloud; draft + published mirror |
| **Sync direction** | Publish → Cloud; pull updates ← Cloud |
| **Backup** | Local pg_dump before major publish; optional cloud backup after publish |
| **Runtime dependency** | Not required at show time (runtime has own DB) |

### 3. Local Show Runtime database

| Attribute | Value |
|-----------|-------|
| **Purpose** | Offline show execution; runtime state; Soundcheck/Readiness; logs |
| **Authority** | Local-runtime-authoritative during performance |
| **Data scope** | Pulled package replica + runtime-only tables |
| **Sync direction** | Pull ← Cloud (pre-show); optional push → Cloud (post-performance logs) |
| **Backup** | Pre-performance snapshot; post-performance export optional |
| **Runtime dependency** | **Required during performance** — show cannot execute without it |

---

## 9. Initial Migration Domains

Migrations are organised into domain groups. Each group may comprise one or more migration files.

| # | Domain group | Logical tables (conceptual) |
|---|--------------|----------------------------|
| M1 | **Identity / access** | users, roles, permissions, role_user, musician_user_links |
| M2 | **Band / organisation** | bands |
| M3 | **Musicians / devices** | musicians, devices |
| M4 | **Instrument parts / capabilities** | instrument_parts, capabilities |
| M5 | **Production assets** | mix_moves, light_modes, production_configurations |
| M6 | **Songs / cues / actions** | songs, cues, actions, action_references |
| M7 | **Charts / snippets / file assets** | charts, snippets, file_assets |
| M8 | **Shows / playlists** | shows, show_playlist_items, stage_plots, tech_riders |
| M9 | **Performances / assignments** | performances, performance_musician_availability, assignments, monitor_assignments |
| M10 | **Soundcheck / readiness** | soundchecks, readiness_records, readiness_dimensions |
| M11 | **Sync packages** | published_packages, package_manifests, package_entity_checksums, package_file_checksums, sync_states |
| M12 | **Runtime state / logs** | runtime_sessions, runtime_timeline_state, connection_states, action_execution_logs, timeline_event_logs, bridge_health_logs |
| M13 | **Audit / history** | audit_logs, publish_audits, sync_audits, entity_change_history |
| M14 | **Supporting** | lifecycle enums as check constraints; indexes; band scoping |

---

## 10. Initial Migration Order

Order respects foreign key dependencies (parent before child):

```
M1  Identity / access
    ↓
M2  Band / organisation
    ↓
M3  Musicians / devices          M4  Instrument parts / capabilities
    ↓                                ↓
M5  Production assets (mix_moves, light_modes, production_configurations)
    ↓
M6  Songs / cues / actions  ←── references M4 (instrument parts), M5 (mix_moves, light_modes)
    ↓
M7  Charts / snippets / file_assets  ←── references M6 (songs, cues)
    ↓
M8  Shows / playlists  ←── references M2, M5, M6, M7
    ↓
M9  Performances / assignments  ←── references M3, M4, M8
    ↓
M10 Soundcheck / readiness  ←── references M9
    ↓
M11 Sync packages  ←── references M8, M9, M7 (file manifest)
    ↓
M12 Runtime state / logs  ←── references M9, M11 (local runtime only tables; may be separate migration path)
    ↓
M13 Audit / history  ←── references all publishable entities
    ↓
M14 Supporting indexes and constraints
```

### Dependency reasoning

| Dependency | Reason |
|------------|--------|
| Band before all master assets | Band scoping FK on every tenant table |
| Musicians before Assignments | Assignment maps Musician |
| Instrument Parts before Capabilities, Assignments | Role catalog prerequisite |
| Songs before Cues before Actions | Aggregate hierarchy |
| File assets after Charts | Chart owns file reference |
| Shows after Songs | Playlist references Songs |
| Performances after Shows | Performance references Show |
| Assignments after Performances + Musicians | Operational mapping |
| Sync packages after Performances + file assets | Package assembly |
| Runtime tables after Performances | Runtime session scoped to Performance |
| Audit after entities exist | Audit references entity types/IDs |

---

## 11. Table Naming Principles

| Rule | Convention |
|------|------------|
| **Plural snake_case** | `songs`, `mix_moves`, `published_packages` |
| **Pivot tables** | Alphabetical singular: `musician_user`, `role_user` |
| **Junction with metadata** | Descriptive: `show_playlist_items`, `performance_musician_availability` |
| **Runtime prefix** | Runtime-only tables: `runtime_*` (e.g. `runtime_sessions`, `runtime_timeline_state`) |
| **Audit prefix** | `audit_*` or `*_audits` |
| **No abbreviations** | `instrument_parts` not `inst_parts` |
| **Band scoping** | `band_id` FK on all tenant-scoped tables |

---

## 12. Primary Key and Identifier Strategy

### Internal primary keys

| Use | Type | Notes |
|-----|------|-------|
| **Primary key (all tables)** | `bigint` auto-increment (`id`) | Laravel convention; join performance |
| **Never expose internally** | — | API uses public ID where needed |

### Public / stable IDs

| Use | Type | Notes |
|-----|------|-------|
| **API / sync reference** | `uuid` column (`public_id`) | Generated on create; stable across environments |
| **Package reference** | `uuid` | Published package identity |
| **File asset reference** | `uuid` | Canonical file record ID |

### Sync-safe identifiers

| Rule | Statement |
|------|-----------|
| **public_id immutable** | Never changes after create |
| **Cloud ID vs local ID** | Internal `id` may differ across environments; sync uses `public_id` |
| **Package version** | Monotonic integer per Show/Performance + content checksum |

### External references (not primary keys)

| Reference | Storage | Notes |
|-----------|---------|-------|
| **Ableton PGM** | Integer on `show_playlist_items` or mapping table | Show-scoped; not globally unique |
| **Ableton CC16** | Integer on `cues` | Song-scoped cue number |
| **Spaces object** | `file_assets.spaces_bucket`, `spaces_key` | Canonical file identity |
| **Local cache path** | `file_asset_cache.local_path`, `checksum` | Runtime only; not canonical |

---

## 13. Foreign Key and Relationship Strategy

### Required constraints

| Rule | Statement |
|------|-----------|
| **FK on all references** | Every `*_id` column has FK constraint unless documented exception |
| **Band scoping** | All tenant entities FK to `bands` |
| **Not null** | Required relationships enforced at DB level where domain requires |

### Cascade rules

| Relationship | ON DELETE |
|--------------|-----------|
| Band → child entities | **RESTRICT** (cannot delete Band with assets) |
| Song → Cues | **RESTRICT** if Cues exist (archive workflow instead) |
| Cue → Actions | **RESTRICT** if Actions exist |
| Show → Performances | **RESTRICT** if Performances exist |
| Performance → Assignments | **RESTRICT** during `live` state (application enforced) |
| Musician → Devices | **CASCADE** (devices belong to musician) |
| File asset → owning entity | **RESTRICT** if referenced by published Show |

### Soft-delete / archive

| Rule | Statement |
|------|-----------|
| **Preferred** | `archived_at` timestamp — not hard delete |
| **Soft delete** | `deleted_at` for User/auth only where Laravel convention applies |
| **Published entities** | Archive only — never hard delete published Show/Song with dependents |

### Protected entities

Shows, Songs, Performances in `live` or `published` state — structural deletes blocked at application and FK level.

---

## 14. Indexing Strategy

| Index target | Reason |
|--------------|--------|
| `band_id` on all tenant tables | Band scoping queries |
| `public_id` unique | API and sync lookup |
| `(band_id, lifecycle_state)` | Filter draft/published/archived |
| `performances.show_id` | Show → Performance lookup |
| `assignments(performance_id, song_id, cue_id)` | Runtime assignment resolution |
| `show_playlist_items(show_id, position)` | Playlist ordering |
| `cues(song_id, cue_number)` | CC16 mapping |
| `file_assets(public_id)` | Manifest resolution |
| `published_packages(performance_id, version)` | Sync pull lookup |
| `action_execution_logs(performance_id, created_at)` | Show-day log queries |
| JSONB GIN indexes | Action params, package manifest queries (selective) |

Index creation may be in dedicated M14 migration group after tables exist.

---

## 15. File Asset Reference Strategy

Aligns with PH005 and PH004 Git exclusion policy.

| Layer | Stores | Canonical? |
|-------|--------|------------|
| **`file_assets` table** | `public_id`, type, size, checksum, `spaces_bucket`, `spaces_key`, owning entity FK, lifecycle | ✅ Yes (metadata) |
| **DigitalOcean Spaces** | Binary content | ✅ Yes (remote file) |
| **`file_asset_cache` table** (runtime) | `file_asset_id`, `local_path`, `checksum`, `cached_at`, `verified_at` | ❌ Cache only |
| **Disk folders** | `/resources/`, `/songs/`, `/charts/`, `/uploads/` | ❌ Not Git; not canonical |

### Rules

- Database **never** stores random local-only path as canonical reference.
- `spaces_bucket` + `spaces_key` is canonical object identity.
- Local cache path is implementation detail on runtime DB only.
- Soundcheck compares manifest checksums against cache — not Git paths.

---

## 16. Runtime State Persistence Strategy

### Persist locally (Local Show Runtime DB)

| State | Table (conceptual) | Retention |
|-------|-------------------|-----------|
| Active Performance session | `runtime_sessions` | Performance duration + archive |
| Current/previous/next Song/Cue | `runtime_timeline_state` | Updated on each TimelineEvent |
| Connection state (Ableton, X32, lighting, bridges) | `connection_states` | Show day |
| Readiness aggregate | `readiness_records` | Show day + optional cloud sync |
| Soundcheck progress | `soundchecks` | Show day + optional cloud sync |
| Action execution logs | `action_execution_logs` | Show day + optional cloud sync |
| Timeline event logs (PGM/CC16) | `timeline_event_logs` | Show day + optional cloud sync |
| Bridge health | `bridge_health_logs` | Diagnostic retention |

### NOT cloud-authoritative during performance

| Data | Rule |
|------|------|
| Live PGM/CC16 values | Runtime local only |
| Current cue timeline state | Runtime local only |
| In-progress Action execution | Runtime local only |
| Device session state | Runtime local only |
| Bridge heartbeat | Runtime local only |

Post-performance optional sync to cloud audit/archive tables — never live canonical.

---

## 17. Sync Package Persistence Strategy

| Record (conceptual) | Purpose |
|---------------------|---------|
| **`published_packages`** | Package header: `public_id`, version, performance_id, show_id, checksum, lifecycle_state, published_at |
| **`package_manifests`** | JSONB manifest: entity public_id list by type |
| **`package_entity_checksums`** | Per-entity content hash for drift detection |
| **`package_file_checksums`** | Per file_asset checksum in package |
| **`sync_states`** | Pull status: `synced`, `pending`, `stale`, `conflict`, `blocked` |
| **`local_package_pulls`** (runtime) | Local pull record: package_id, pulled_at, verified, local_checksum |
| **`publish_audits`** | Who published, when, validation result |

### Sync flow persistence

```
Publish → published_packages (cloud) + manifest + checksums
Pull    → local_package_pulls (runtime) + sync_states update
Live    → sync_states = blocked (performance active)
Post    → optional log sync back to cloud audit
```

---

## 18. Audit / History Persistence Strategy

| Event | Table (conceptual) | Minimum fields |
|-------|-------------------|----------------|
| Publish actions | `publish_audits` | user_id, show_id, performance_id, package_version, result, timestamp |
| Sync actions | `sync_audits` | direction, package_id, outcome, timestamp |
| Assignment changes | `entity_change_history` | entity_type, public_id, before/after JSONB, user_id, timestamp |
| Chart/snippet changes | `entity_change_history` | same pattern |
| File asset changes | `entity_change_history` | file_asset public_id, checksum change |
| Soundcheck events | `soundcheck_events` | performance_id, milestone, actor, timestamp |
| Readiness changes | `readiness_change_logs` | dimension, old_state, new_state, timestamp |
| Runtime action failures | `action_execution_logs` | action_id, cue context, error, timestamp |
| Performance start/finish | `performance_events` | performance_id, event_type, timestamp |

Audit tables live primarily on **cloud** for long-term retention. Runtime captures show-day events locally first; post-performance sync optional.

---

## 19. Seed Data Strategy

### Minimal approved seeds (M1 domain)

| Seed | Content | Notes |
|------|---------|-------|
| **Band** | One initial Band ("Ed and the Shadow Boys") | Required scope root |
| **Roles** | Director, Musician, Tech, Administrator | Permission foundation |
| **Permissions** | CRUD scopes per role | Laravel Spatie or native — implementation choice |
| **Admin user** | One Director/Admin bootstrap user | Created via artisan command — **not hard-coded email in migration** |
| **System states** | Lifecycle enum reference data if lookup tables used | Optional |

### Explicitly NOT seeded without approval

- Sample Songs, Shows, Performances
- Sample musicians with real emails
- Production chart files
- Mix Moves / Light Modes production data

Seed data runs via Laravel seeders — version-controlled — never manual INSERT in production.

---

## 20. Rollback Strategy

| Scenario | Strategy |
|----------|----------|
| **Migration up failure** | Fix forward migration; do not leave partial state — use transactions where supported |
| **Migration down** | Every migration implements `down()` dropping created objects in reverse order |
| **Non-reversible migration** | Document in migration header comment; requires explicit approval (data transform, column type narrowing) |
| **Production rollback** | `php artisan migrate:rollback --step=N` on affected environment only after backup |
| **Show-day rollback** | **Never** rollback runtime DB during live performance |
| **Cloud rollback** | Restore from DO managed backup if migration caused data loss |

Rollback of schema does not rollback published data — data restore is separate (see §21).

---

## 21. Backup and Restore Strategy

| Environment | Backup method | Frequency |
|-------------|---------------|-----------|
| **Cloud** | DigitalOcean managed automated backup | Daily + pre-major-publish manual snapshot |
| **Director local** | `pg_dump` to local backup directory | Before each publish; weekly scheduled |
| **Local Show Runtime** | `pg_dump` or volume snapshot | Pre-performance; post-performance optional |

### Restore rules

- Cloud restore from DO backup for disaster recovery.
- Runtime restore from pre-performance snapshot if pull corrupted local DB.
- Restored database must match migration version — run `migrate:status` after restore.

---

## 22. Migration Testing Requirements

Before any migration merges to main:

| Test | Requirement |
|------|-------------|
| **Fresh migrate** | `migrate:fresh` succeeds on empty PostgreSQL |
| **Rollback** | `migrate:rollback` succeeds for the migration group |
| **Re-migrate** | `migrate` after rollback succeeds |
| **FK integrity** | No orphan records after seed + sample data insert |
| **Cloud parity** | Same migration files run on Docker PostgreSQL (runtime simulation) |
| **Band scoping** | All tenant queries include band_id in tests |
| **No production data** | Tests use factories — not production dumps |

CI pipeline to run migration tests — deferred to implementation (OQ-005).

---

## 23. Prohibited Database Practices

| # | Prohibition |
|---|-------------|
| 1 | **No production assets in Git** — charts, uploads, resources, songs folders excluded |
| 2 | **No manual database edits outside migrations** — no phpMyAdmin/psql hotfixes in production |
| 3 | **No browser/localStorage authoritative data** — database is source of truth |
| 4 | **No hard-coded production IDs** in migrations or seeders |
| 5 | **No hard-coded musician/user emails** in migrations |
| 6 | **No untracked schema changes** — every DDL change is a migration file |
| 7 | **No migrations that silently drop show-critical data** — destructive drops require explicit approval and backup |
| 8 | **No runtime cue state made cloud-authoritative during performance** |
| 9 | **No canonical local filesystem path in cloud database** — Spaces reference only |
| 10 | **No MySQL/MariaDB** unless PH007 decision formally amended |

---

## 24. Open Questions / Deferred Decisions

| ID | Question | Deferred to |
|----|----------|-------------|
| OQ-001 | Spatie Permission vs native Laravel roles | Implementation |
| OQ-002 | Separate DB per Band vs single DB multi-tenant | Future scale (default: single DB, band_id scope) |
| OQ-003 | Director local: native Postgres vs Docker | Ed's workstation setup |
| OQ-004 | Runtime DB: include all cloud tables or subset views | Implementation (default: full replica + runtime tables) |
| OQ-005 | CI migration test pipeline | DevOps setup |
| OQ-006 | Partitioning for timeline_event_logs at scale | Future |
| OQ-007 | Read replicas for cloud | Future scale |
| OQ-008 | Encryption at rest configuration on DO | Infrastructure setup |

---

## 25. Implementation Readiness Checklist

Before writing the first migration file:

| # | Gate | Status |
|---|------|--------|
| 1 | PH001–PH004 domain and data architecture complete | ✅ |
| 2 | PH005 logical DATABASE_ARCHITECTURE complete | ✅ |
| 3 | PH006 integration architecture complete | ✅ |
| 4 | PH007 this document complete | ✅ |
| 5 | PostgreSQL selected for all environments | ✅ Decision 083 |
| 6 | Migration order defined (§10) | ✅ |
| 7 | Identifier strategy defined (§12) | ✅ |
| 8 | File asset strategy aligned (§15) | ✅ |
| 9 | Prohibited practices documented (§23) | ✅ |
| 10 | Laravel project scaffold with PostgreSQL `.env.example` | ⬜ Implementation |
| 11 | Docker Compose PostgreSQL service for runtime | ⬜ Implementation |
| 12 | First migration M1 (identity/access) written | ⬜ PH008 |

**PH007 complete — first migration (PH008) may proceed when checklist items 10–12 are addressed in implementation phase.**

---

## 26. Glossary

| Term | Definition |
|------|------------|
| **Migration domain** | Grouped set of related tables migrated together (M1–M14). |
| **public_id** | UUID exposed in API/sync — stable across environments. |
| **Published Package** | Versioned sync unit with manifest and checksums. |
| **Runtime session** | Local Show Runtime DB scope for one Performance execution. |
| **JSONB** | PostgreSQL binary JSON — used for manifests and flexible metadata. |
| **pg_dump** | PostgreSQL logical backup utility. |
| **band_id** | Tenant scope FK on all master library entities. |

---

End of Physical Database & Migration Plan — PH007
