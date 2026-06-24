# X32/Ableton Rebuild Agent Governance

Status: PH058 Amended (Cloud-First Canonical Schema Stabilisation)

## Authority Order

1. `docs/PROJECT_CHARTER.md`
2. `docs/DOMAIN_MODEL.md`
3. `docs/RUNTIME_MODEL.md`
4. `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`
5. `docs/DATA_ARCHITECTURE.md`
6. `docs/DATABASE_ARCHITECTURE.md`
7. `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`
8. `docs/FOUNDATION_IMPLEMENTATION_PLAN.md`
9. `docs/UX_MODEL.md`
10. `docs/INFORMATION_ARCHITECTURE.md`
11. `docs/ARCHITECTURE.md`
12. `docs/DECISION_LOG.md`
13. Implementation Tasks

If a task conflicts with a higher authority document:

STOP

Return:

NOT READY – CHARTER CONFLICT

## Product Purpose

The platform is a Live Performance Orchestration System.

The platform coordinates:

- Ableton
- X32
- Lighting
- Musicians
- Charts
- Devices
- Monitoring
- Soundcheck
- Performance execution

## Environment Terminology (PH054 / PH055)

Use these names consistently in documentation and implementation:

| Term | Meaning |
|------|---------|
| **Cloud Studio** | Server environment — musician portal (Band Portal / ESB Studio), cloud-hosted collaboration, song/chart/performance management (`/server/`, Forge-hosted) |
| **Website** | Public cloud-hosted web surfaces (e.g. band journey, marketing, public content) — separate deployable app, same Cloud Database as Cloud Studio |
| **Live Stage** | Local performance environment — Director preparation host and Local Show Runtime for rehearsal and performance (`/backend/`, offline-capable) |

Do **not** introduce alternative terms for these environments.

### ESB data architecture (PH055)

| Layer | Model |
|-------|--------|
| **Logical** | One ESB data architecture — shared entity definitions across workspaces |
| **Physical databases** | **Two** — Cloud Database and Live Stage Database |
| **Workspaces** | **Three** — Cloud Studio, Website, Live Stage |

| Workspace | Physical database | Codebase (current) |
|-----------|-------------------|-------------------|
| Cloud Studio | Cloud Database | `/server/` |
| Website | Cloud Database | separate Forge site (e.g. edandtheshadows) |
| Live Stage | Live Stage Database | `/backend/` + Local Show Runtime stack |

**Schema parity** is mandatory between Cloud Database and Live Stage Database for all shared ESB entities. Offline operation creates **data-state divergence**, not schema divergence.

### Reliability responsibilities (PH058)

Cloud-first canonical schema is a **reliability decision** — not a hierarchy of workspace importance.

| Cloud Database (system of record) | Live Stage Database (operational continuity) |
|-----------------------------------|---------------------------------------------|
| Backup, restore, replication | Rehearsal, performance, offline operation |
| Reference data, long-term history | Console / Ableton runtime execution |
| Rebuild source for Live Stage | Pending local changes until synchronised |

**Cloud can rebuild Live Stage.** **Live Stage can operate without Cloud.**

Full plan: `docs/PH058_CLOUD_FIRST_SCHEMA_STABILISATION_PLAN.md`  
**Schema authority:** `docs/PH059_CLOUD_CANONICAL_MIGRATION_MANIFEST.md` (CCMM)  
**Gap analysis:** `docs/PH060_CCMM_IMPLEMENTATION_GAP_ANALYSIS.md` (PH061 gate)  
**Recovery execution:** `docs/PH061_CLOUD_RECOVERY_EXECUTION_PLAN.md` (Gate 2+)  
**CCMM migration authoring:** `docs/PH062_CCMM_MIGRATION_AUTHORING_PLAN.md` (Git only until Gate 2)  
**X32 console domain:** `docs/PH061A_X32_CONSOLE_DOMAIN_DISCOVERY.md` (CCMM-12 Track B)

Formal ADR: `docs/adr/ADR-001-cloud-studio-live-stage-synchronisation.md`

### Synchronisation warnings (PH054)

When designing or implementing song-management features:

- **Do not assume Cloud Studio is overwrite authority.** Cloud Studio and Live Stage are peer authoring environments.
- **Do not assume permanent connectivity.** Live Stage may be offline ~50% of the time; no critical rehearsal or performance workflow may depend on cloud access.
- **Do not design synchronisation using last-write-wins.** Require checkout, version comparison, diff generation, and operator-controlled conflict resolution.

## Non-Negotiable Rules

- Ableton is the authoritative runtime timeline.
- Live Show View is Priority #1.
- The platform must operate without internet.
- Shows must continue if cloud services are unavailable.
- Shows require one Ableton Show File.
- Ableton owns playlist and cue progression.
- Songs are reusable global assets.
- Shows reference Songs.
- Performances execute Shows.
- Cue = start of a song section.
- Cue 0 = Preparation Cue (Cue Number `000`).
- Song Code + Cue Number (`SSS.CCC`) is the canonical runtime identity for Songs and Cues.
- Soundcheck is a first-class concept.
- Musicians are global; availability is performance-level.
- Devices belong to musicians.
- Instrument Parts are global.
- Musicians may change instruments/roles by song or cue.
- Musician device view is musician-centric.
- Mix Moves and Light Modes are reusable assets.
- The show must go on.
- Production assets (`/resources/`, `/songs/`, `/charts/`, `/uploads/`) are not Git assets.
- MIDI and DMX/USB hardware integration runs on host OS bridges — not inside Docker.

## Canonical Entities (PH001)

Band, Musician, Device, Instrument Part, Capability, Song, Chart, Snippet, Cue, Action, Mix Move, Light Mode, Production Configuration, Show, Performance, Assignment, Stage Plot, Tech Rider, Soundcheck, Readiness, Local Runtime, Sync State, Ableton Protocol State.

Full definitions: `docs/DOMAIN_MODEL.md`  
Runtime behaviour: `docs/RUNTIME_MODEL.md`  
Integration architecture: `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`  
Data ownership: `docs/DATA_ARCHITECTURE.md`  
Database architecture: `docs/DATABASE_ARCHITECTURE.md`  
Physical database plan: `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`  
Foundation implementation plan: `docs/FOUNDATION_IMPLEMENTATION_PLAN.md`  
UX and workflows: `docs/UX_MODEL.md`  
Navigation: `docs/INFORMATION_ARCHITECTURE.md`

## Production Safety Rules (PH055 — mandatory)

During production data-integrity investigations or incidents:

- **No ad hoc production DDL** — no `ALTER TABLE`, manual column adds, or schema edits outside Laravel migrations on any environment.
- **No manual `INSERT` into `migrations`** — migration history must reflect only `php artisan migrate` execution.
- **No marking migrations as run manually** — including via tinker, SQL client, or SSH one-liners.
- **No feature work** during production data-integrity investigations — documentation and read-only diagnosis only until the operator closes the incident.
- **No production mutation** without an operator-approved incident procedure — including deploy, migrate, seed, sync, truncate, refresh, or destructive Artisan commands.

Violations require incident documentation in `docs/DECISION_LOG.md` before further implementation resumes.

Active production incident recovery plan: `docs/PH056_PRODUCTION_RECOVERY_PLAN.md` (PH056 — **no production mutation until operator closes incident**).

## Development Rules

- Make scoped changes only.
- Do not invent domain concepts.
- Do not invent workflows.
- Do not invent terminology.
- Protect live show reliability.
- Protect local-first operation.
- Protect Ableton authority.
- Documentation/governance tasks must not produce application code, migrations, routes, or UI.
- Database migrations and schema implementation must comply with `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`, `docs/DATABASE_ARCHITECTURE.md`, and `docs/DATA_ARCHITECTURE.md`.
- **PH009 and subsequent implementation prompts must follow `docs/FOUNDATION_IMPLEMENTATION_PLAN.md`.**
- Implementation of MIDI, lighting, X32, OSC, runtime event bus, or device realtime communication must comply with `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`.
- All future runtime, Ableton, cue, snippet, chart, musician guidance, and validation work must use **Song Code + Cue Number** (`SSS.CCC`) as the canonical runtime identity — not database `id` values. See `docs/DOMAIN_MODEL.md`, `docs/RUNTIME_MODEL.md` §4.1, `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` §6.1, and `docs/DECISION_LOG.md` PH010.01.

## Completion Report

Every implementation must return:

1. Summary
2. Files Modified
3. Technical Notes
4. Verification Performed
5. Risks / Observations
6. Rollback Notes
