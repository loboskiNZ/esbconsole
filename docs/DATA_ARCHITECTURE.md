# Data Architecture & Persistence Model

Status: PH007 Finalised  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: Canonical data ownership, persistence authority, sync boundaries, and lifecycle rules before database schema design

Related documents:

- Entity definitions: `docs/DOMAIN_MODEL.md`
- Runtime behaviour: `docs/RUNTIME_MODEL.md`
- Logical schema design: `docs/DATABASE_ARCHITECTURE.md`
- Physical database and migration plan: `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`
- Infrastructure: `docs/ARCHITECTURE.md`

This document defines **what** data exists, **where** it is authoritative, **how** it syncs, and **when** it transitions — not table names or schema implementation.

---

## 1. Purpose

PH004 establishes the persistence and data authority model for the Live Performance Orchestration System.

Goals:

- Define canonical ownership for every governed entity
- Separate cloud collaboration from local performance authority
- Enable sync-before-show without runtime cloud dependency
- Prevent duplicate master data and silent overwrites
- Govern file assets as managed objects, not scattered paths
- Provide lifecycle and conflict rules before schema design

**Out of scope for PH004:** database table names, migrations, Eloquent models, API design.

---

## 2. Data Authority Principles

| # | Principle |
|---|-----------|
| 1 | **Local-first design/preparation is primary.** Director Local is the primary creation and editing environment. |
| 2 | **Cloud supports collaboration, sync, backup, and distribution.** Cloud is not the show-day runtime. |
| 3 | **Local Show Runtime is authoritative during performance.** Once performance starts, local data wins for runtime concerns. |
| 4 | **Live performance must not depend on internet access.** All required data and files must exist locally before show start. |
| 5 | **Sync must happen before show runtime.** Remote canonical data is pulled into Local Show Runtime explicitly before performance. |
| 6 | **Cloud changes must not disrupt an active local performance.** No inbound sync overwrites active performance state. |
| 7 | **Reusable master data has one canonical owner.** Single source of truth per entity class. |
| 8 | **Shows and Performances reference assets; they do not duplicate them.** Reference-by-ID, not copy-by-value. |
| 9 | **Files are managed assets.** Metadata + object references — not ad hoc local-only paths. |
| 10 | **Runtime cue state is local/live.** Cloud does not control or store live cue progression. |
| 11 | **Production assets are not Git assets.** `/resources/`, `/songs/`, `/charts/`, and `/uploads/` are managed production/runtime folders — not version-controlled in Git. |

---

## 3. Persistence Environments

### 1. Director Local Environment

| Attribute | Value |
|-----------|-------|
| **Role** | Primary design and preparation |
| **Persistence** | Local database and file cache |
| **Can create/edit** | Shows, Songs, assets, cue mappings, assignments, production data |
| **Authority** | Director-local-canonical until published to cloud |
| **Sync** | Publishes to cloud; pulls updates from cloud |

### 2. Cloud Environment

| Attribute | Value |
|-----------|-------|
| **Role** | Collaboration, musician access, backup, remote preparation, distribution |
| **Persistence** | PostgreSQL 16+ on DigitalOcean Managed PostgreSQL; files in DigitalOcean Spaces |
| **Stack** | Laravel on Forge-managed Droplet |
| **Authority** | Cloud-canonical for published master library and operational records |
| **Sync** | Receives publishes from Director Local; serves pulls to Local Show Runtime |

### 3. Local Show Runtime

| Attribute | Value |
|-----------|-------|
| **Role** | Offline-capable live performance execution |
| **Persistence** | Docker local database and cache |
| **Authority** | Authoritative during performance for runtime, readiness, and execution logs |
| **Sync** | Pulls published package before performance; optional post-performance push |

---

## 4. Canonical Entity Ownership Matrix

For each entity: canonical owner, primary edit environment, cloud presence, local runtime presence, sync direction, and notes.

| Entity | Canonical Owner | Primary Edit Environment | Cloud Presence | Local Runtime Presence | Sync Direction | Notes |
|--------|-----------------|--------------------------|----------------|------------------------|----------------|-------|
| **Band** | Cloud (published) | Director Local / Administrator | Yes | Yes (cached) | Director → Cloud → Runtime | Top-level scope; rarely changes |
| **User** | Cloud | Cloud / Administrator | Yes | Auth cache only | Cloud → all clients | Auth identity; not a Musician |
| **Musician** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Global person record |
| **Device** | Cloud (published) | Director Local / Musician (self) | Yes | Yes (cached) | Bidirectional (musician self-service permitted) | Belongs to Musician |
| **Instrument Part** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Global role catalog |
| **Capability** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Musician eligibility |
| **Song** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Global reusable asset |
| **Chart** | Cloud (published) | Director Local | Yes | Yes (cached + file) | Director → Cloud → Runtime | File asset in Spaces |
| **Snippet** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Part of Chart/Song |
| **Cue** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Authored section; not live state |
| **Action** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Attached to Cue |
| **Mix Move** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Reusable X32 asset |
| **Light Mode** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Reusable lighting asset |
| **Production Configuration** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Show wiring template |
| **Show** | Cloud (published) | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Requires Ableton Show File ref |
| **Performance** | Hybrid | Director Local / Cloud | Yes | Yes (authoritative at show time) | Director → Cloud → Runtime pull | Executable unit |
| **Assignment** | Hybrid | Director Local | Yes | Yes (cached) | Director → Cloud → Runtime | Operational mapping |
| **Stage Plot** | Cloud (published) | Director Local | Yes | Yes (cached + file) | Director → Cloud → Runtime | Informational document |
| **Tech Rider** | Cloud (published) | Director Local | Yes | Yes (cached + file) | Director → Cloud → Runtime | Informational document |
| **Soundcheck** | Local Runtime (during show day) | Local Show Runtime | Optional post-sync | Yes (authoritative) | Runtime → Cloud (post-performance) | Pre-show process state |
| **Readiness** | Local Runtime (during show day) | Local Show Runtime | Optional post-sync | Yes (authoritative) | Runtime → Cloud (post-performance) | Gate state aggregate |
| **Monitor Assignment** | Hybrid | Director Local / Tech | Yes | Yes (cached) | Director → Cloud → Runtime | Monitor routing per Performance |
| **Ableton Protocol State** | Derived/runtime-only | — | No (live) | Yes (live input) | Not synced live | PGM/CC16 ephemeral |
| **Runtime State** | Derived/runtime-only | — | No (live) | Yes (authoritative) | Post-performance optional | Timeline, connections, execution |
| **Sync State** | Hybrid | All environments | Yes | Yes | Bidirectional (observable) | Metadata about sync, not show data |

---

## 5. Cloud vs Local Authority Matrix

| Entity | Classification |
|--------|----------------|
| Band | Cloud-canonical |
| User | Cloud-canonical |
| Musician | Cloud-canonical |
| Device | Hybrid with explicit sync rules |
| Instrument Part | Cloud-canonical |
| Capability | Cloud-canonical |
| Song | Cloud-canonical |
| Chart | Cloud-canonical |
| Snippet | Cloud-canonical |
| Cue | Cloud-canonical |
| Action | Cloud-canonical |
| Mix Move | Cloud-canonical |
| Light Mode | Cloud-canonical |
| Production Configuration | Cloud-canonical |
| Show | Cloud-canonical (after publish) |
| Performance | Hybrid with explicit sync rules |
| Assignment | Hybrid with explicit sync rules |
| Stage Plot | Cloud-canonical |
| Tech Rider | Cloud-canonical |
| Soundcheck | Local-runtime-authoritative (during show day) |
| Readiness | Local-runtime-authoritative (during show day) |
| Monitor Assignment | Hybrid with explicit sync rules |
| Ableton Protocol State | Derived/runtime-only |
| Runtime State | Derived/runtime-only |
| Sync State | Hybrid with explicit sync rules |
| Published Package / Sync Package | Hybrid with explicit sync rules |

### Classification Definitions

| Class | Meaning |
|-------|---------|
| **Cloud-canonical** | Published truth lives in cloud; local copies are replicas. |
| **Director-local-canonical until published** | Draft edits live on Director Local; cloud receives explicit publish. |
| **Local-runtime-authoritative** | Local Show Runtime owns live truth; cloud receives optional post-show copy. |
| **Hybrid with explicit sync rules** | Authority shifts by phase; sync rules define direction and protection. |
| **Derived/runtime-only** | Never canonical in cloud during performance; computed or ephemeral. |

**Director-local-canonical until published** applies to all master library and show preparation entities while in draft state on Director Local.

---

## 6. Sync Model

### Flow Overview

```
Director Local  ──publish──►  Cloud  ◄──review/update──  Musicians
                                 │
                                 │ pull (sync-before-show)
                                 ▼
                          Local Show Runtime
                                 │
                                 │ live performance (no cloud dependency)
                                 │
                                 └──post-performance──►  Cloud (logs, readiness history)
```

### Sync Rules

| Phase | Behaviour |
|-------|-----------|
| **Preparation** | Director publishes prepared data to cloud. Musicians access cloud to review/update permitted content. |
| **Pre-show** | Local Show Runtime pulls required published package before performance. |
| **During performance** | Local runtime must not depend on cloud. No inbound sync overwrites active state. |
| **Post-performance** | Selected logs, readiness records, and runtime history may sync back to cloud. |
| **Observability** | Sync must be explicit and observable (Sync State visible to operator). |

### Sync Triggers

| Trigger | Direction | When |
|---------|-----------|------|
| **Publish** | Director Local → Cloud | Director completes preparation batch |
| **Musician update** | Musician → Cloud → Director Local | Permitted self-service fields only |
| **Runtime pull** | Cloud → Local Show Runtime | Before Soundcheck / performance |
| **Post-show push** | Local Show Runtime → Cloud | After performance completes (optional) |
| **Conflict resolution** | Operator-mediated | When detected; never silent for show-critical data |

### Sync Package

A **Published Package** (or Sync Package) is the atomic unit pulled to Local Show Runtime. Contains:

- Performance record and Show reference
- Assignments, Monitor Assignments
- Referenced master assets (Songs, Charts, Actions, Mix Moves, Light Modes, etc.)
- Required file assets (cached locally)
- Sync State metadata (version, timestamp, checksum)

See §7 Publish Model.

---

## 7. Publish Model

### Definition

**Publish** is the explicit act of making Director Local prepared data available to cloud collaborators and Local Show Runtime consumers.

Draft local changes are **not** visible to musicians or runtime until published.

### Publish Transitions

```
Director Local (draft)  ──publish──►  Cloud (published)  ──pull──►  Local Show Runtime (performance-ready)
```

### What Publish Means

| Aspect | Behaviour |
|--------|-----------|
| **Visibility** | Draft local changes become available to cloud and musicians. |
| **Runtime consumption** | Published show package can be pulled to Local Show Runtime. |
| **Validation** | Publication should validate required dependencies before accepting. |
| **Versioning** | Each publish creates an observable version increment. |
| **Rollback** | Previous published version remains addressable for recovery. |

### Publication Validation (Required Dependencies)

Before publish succeeds, validate:

- Show has valid Ableton Show File reference
- Playlist import complete; PGM mappings resolvable
- Referenced Songs exist and are publishable
- Assignments reference available Musicians and valid Instrument Parts
- Chart file assets exist in managed storage
- Mix Moves and Light Modes referenced by Actions exist
- No unresolved conflicts from prior sync

Failed validation blocks publish — does not partially publish show-critical data.

### Published Package

A **Published Package** is the validated, versioned snapshot of a Show/Performance and all referenced assets ready for runtime pull.

---

## 8. Conflict Model

### Baseline Rules

| Rule | Statement |
|------|-----------|
| **Director authority** | Director changes are authoritative for production structure (Songs, Shows, Assignments, Actions, etc.). |
| **Musician authority** | Musician changes are authoritative only for permitted musician-owned data (Device registration, personal preferences, permitted self-service fields). |
| **Active performance protection** | Active local performance state must not be overwritten by cloud sync. |
| **Surfacing** | Conflicts must be surfaced for operator review. |
| **No silent overwrite** | No silent overwrite of show-critical data. |

### Conflict Scenarios

| Scenario | Resolution |
|----------|------------|
| Director and Director (two locals) | Latest publish wins with conflict detection; operator merges. |
| Director and Musician (Device) | Musician-owned fields: musician wins. Production fields: director wins. |
| Cloud publish during active performance | **Rejected** — inbound sync blocked until performance ends. |
| Stale runtime pull | Operator warned at Soundcheck; re-pull required before performance. |
| Missing file asset | Detected at publish or Soundcheck; blocks readiness warning. |

### Conflict States

Sync State records: `synced`, `pending`, `conflict`, `stale`, `blocked` (performance active).

---

## 9. File Storage Model

### Approved Remote Storage

**DigitalOcean Spaces** is the approved remote file storage for published assets.

### Managed File Asset Types

| Asset Type | Examples |
|------------|----------|
| Charts | PDF, image notation |
| Tech Rider | PDF, document |
| Stage Plot | PDF, image |
| Ableton Show File | `.als` or approved export (reference + optional mirror) |
| Exports | Published package archives, backups |

### File Record Requirements

File records store:

- **Metadata** — name, type, size, checksum, owning entity, version
- **Object reference** — Spaces bucket/key or equivalent managed URI
- **Not** ad hoc local-only paths as canonical reference

Local environments maintain **cache copies** with cache path as implementation detail — not canonical identity.

### Local Runtime File Rules

| Rule | Statement |
|------|-----------|
| **Pre-show cache** | Local runtime must cache required show files before performance. |
| **Detection** | Missing local files detected during Soundcheck / readiness validation. |
| **No live fetch** | Performance does not fetch files from cloud. |
| **Integrity** | Checksum verification on pull; mismatch surfaces warning. |

### Git Exclusion Policy (Production Assets)

The following folders are **managed production/runtime asset directories** and **must not be Git-tracked**:

| Folder | Contents |
|--------|----------|
| `/resources/` | Ableton projects, logos, sample media, production resources |
| `/songs/` | Runtime song-related assets |
| `/charts/` | Chart PDFs and snippet images |
| `/uploads/` | User-uploaded chart and document binaries |

Git stores application code, governance documentation, database schema/migrations (when implemented), and tiny approved static UI assets only.

Canonical file identity lives in **DigitalOcean Spaces** (remote) and **File Asset records** in the database. Local paths are cache implementation details.

Logical schema for file persistence: `docs/DATABASE_ARCHITECTURE.md` §12.

---

## 10. User/Auth Data Model

### Approved Authentication

**Laravel authentication** is approved for user identity and session management.

### User vs Musician

| Concept | Definition |
|---------|------------|
| **User** | Authentication identity — login credentials, roles, permissions. |
| **Musician** | Domain entity — performance person, global asset. |

Users and Musicians are **related but not identical**:

- A Musician may have a linked User account (for device login).
- Not every User is a Musician (Director, Tech, Administrator).
- Not every Musician requires a User (guest/sub — policy decision at implementation).

### Role Permissions

| Role | Data Access |
|------|-------------|
| **Director** | Full read/write on production structure; publish authority. |
| **Musician** | Read assigned Performance data; edit permitted self-service fields only. |
| **Tech** | Read production and system data; edit Console/Lights/Monitor context as authorised. |
| **Administrator** | User management, Band config; not live performance operation. |

### Musician Self-Service (Permitted Edits)

Musicians may edit without Director approval:

- Device registration and connection preferences
- Personal monitor preference defaults (where configured)
- Personal readiness confirmation during Soundcheck

Musicians may **not** edit without authorisation:

- Assignments, Songs, Shows, Actions, Mix Moves, Light Modes
- Other musicians' data

---

## 11. Runtime Data Model

### Locality Rule

**Current cue state is local runtime state.** Cloud does not control live cue state.

### Runtime Input

| Input | Source | Storage |
|-------|--------|---------|
| PGM | Ableton (live) | Runtime State (ephemeral) |
| CC16 | Ableton (live) | Runtime State (ephemeral) |
| Device connections | Local network | Runtime State |
| Action execution results | Local integrations | Runtime log (local) |

### Runtime History and Logging

| Data | Capture | Sync |
|------|---------|------|
| Action success/failure log | Local Show Runtime | Optional post-performance → Cloud |
| Connection state changes | Local Show Runtime | Optional post-performance → Cloud |
| Cue transition history | Local Show Runtime | Optional post-performance → Cloud |
| Operator notes during performance | Local Show Runtime | Optional post-performance → Cloud |

### What Cloud Does Not Store (Live)

- Live PGM/CC16 values
- Real-time device session state
- In-progress Action execution state

Post-performance archival of runtime history is optional and operator-configured.

---

## 12. Readiness Data Model

Readiness data is **local-runtime-authoritative** during show day.

### Structure

Readiness aggregates three dimensions (see `docs/RUNTIME_MODEL.md` §10):

| Dimension | Data Sources |
|-----------|--------------|
| **System readiness** | Connection State (Ableton, X32, lighting, runtime health) |
| **Production readiness** | Assignment coverage, playlist mapping, file cache completeness |
| **Musician readiness** | Device connections, chart validation, personal confirmations |

### Persistence

| Phase | Location |
|-------|----------|
| During Soundcheck | Local Show Runtime (authoritative) |
| At performance start | Snapshot captured locally |
| Post-performance | Optional sync to cloud for history |

### Readiness vs Sync State

| Concept | Purpose |
|---------|---------|
| **Readiness** | Can we perform? (operational gate) |
| **Sync State** | Is data current? (replication metadata) |

Both may surface warnings during Soundcheck; neither necessarily blocks performance.

---

## 13. Audit / History Principles

| Principle | Statement |
|-----------|-----------|
| **Publish audit** | Every publish records who, when, version, and validation result. |
| **Sync audit** | Every sync pull/push records timestamp, direction, package version, outcome. |
| **Runtime audit** | Action failures and connection losses logged locally with timestamp. |
| **Conflict audit** | Conflicts recorded with both sides preserved until resolved. |
| **No silent mutation** | Show-critical changes require observable trail. |
| **Retention** | Audit history retained in cloud; local runtime retains show-day logs minimum until post-sync. |

Audit data supports rollback analysis — not real-time performance control.

---

## 14. Lifecycle State Model

Initial lifecycle states for governed entities and packages.

### Song

| State | Meaning |
|-------|---------|
| `draft` | Created; incomplete cues/charts/actions. |
| `in_progress` | Actively being authored. |
| `complete` | All required sections authored for intended use. |
| `archived` | Retired; not assignable to new Shows. |

### Chart

| State | Meaning |
|-------|---------|
| `draft` | Created; snippets incomplete. |
| `complete` | Snippets mapped; file asset uploaded. |
| `archived` | Retired. |

### Show

| State | Meaning |
|-------|---------|
| `draft` | Created; preparation incomplete. |
| `published` | Published to cloud; available for Performance and runtime pull. |
| `archived` | Retired; no new Performances. |

### Performance

| State | Meaning |
|-------|---------|
| `draft` | Created; date/venue/assignments incomplete. |
| `scheduled` | Dated and prepared; not yet synced to runtime. |
| `synced` | Published package pulled to Local Show Runtime. |
| `soundcheck` | Soundcheck in progress on local runtime. |
| `live` | Performance executing — inbound sync blocked. |
| `completed` | Performance finished; post-sync eligible. |
| `archived` | Historical record. |

### Assignment

| State | Meaning |
|-------|---------|
| `draft` | Created; not yet published. |
| `published` | Available in cloud and runtime package. |
| `locked` | Locked at performance start; no structural edits during live execution. |

### Soundcheck

| State | Meaning |
|-------|---------|
| `not_started` | Performance synced but Soundcheck not begun. |
| `in_progress` | Soundcheck active on local runtime. |
| `complete` | Soundcheck finished; Readiness recorded. |

### Readiness

| State | Meaning |
|-------|---------|
| `not_ready` | One or more dimensions not ready. |
| `warning` | Issues noted; operator may proceed. |
| `ready` | All dimensions acceptable. |

### Sync Package / Published Package

| State | Meaning |
|-------|---------|
| `draft` | Package being assembled; not pullable. |
| `published` | Validated and available in cloud. |
| `pulled` | Successfully pulled to Local Show Runtime. |
| `stale` | Source published version newer than local copy. |
| `conflict` | Pull or merge conflict detected; operator review required. |

---

## 15. Data Governance Rules

| Rule | Statement |
|------|-----------|
| **Schema gate** | Database/schema work must not proceed unless aligned to this document, `docs/DATABASE_ARCHITECTURE.md`, and `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`. |
| **No-Git assets** | Production asset folders (`/resources/`, `/songs/`, `/charts/`, `/uploads/`) must not be Git-tracked. |
| **No duplicate masters** | One canonical Song, Chart, Mix Move, etc. — Shows reference, never embed copies. |
| **Reference integrity** | Published packages validate all references resolve before pull. |
| **Phase-aware authority** | Authority class depends on lifecycle phase (draft/published/live). |
| **Performance lock** | Performance in `live` state blocks inbound cloud sync. |
| **File governance** | All production files are managed assets with metadata and object references. |
| **Musician boundary** | Musician edits constrained to permitted self-service scope. |
| **Runtime isolation** | Runtime state never canonical in cloud during live performance. |
| **Explicit sync only** | No background silent sync of show-critical data during performance. |
| **Show must go on** | Data architecture must not introduce performance-blocking dependencies. |

---

## 16. Glossary

| Term | Definition |
|------|------------|
| **Canonical owner** | Environment or phase holding authoritative truth for an entity. |
| **Cloud-canonical** | Published truth resides in cloud database and Spaces. |
| **Director Local** | Primary preparation environment on Director workstation. |
| **Draft** | Unpublished local state; not visible to musicians or runtime. |
| **File asset** | Managed file with metadata and object reference in Spaces. |
| **Hybrid entity** | Authority shifts by lifecycle phase with explicit sync rules. |
| **Local Show Runtime** | Offline Docker execution environment for show day. |
| **Monitor Assignment** | Operational monitor routing binding for a Performance. |
| **Publish** | Explicit transition making draft data cloud-visible and runtime-pullable. |
| **Published Package** | Validated sync unit pulled to Local Show Runtime before performance. |
| **Runtime-only** | Ephemeral or derived data; never cloud-authoritative during performance. |
| **Sync-before-show** | Required pull of published package before Soundcheck/performance. |
| **Sync State** | Observable metadata about replication status — not show content. |
| **User** | Authentication identity; related to but distinct from Musician. |

---

End of Data Architecture — PH007
