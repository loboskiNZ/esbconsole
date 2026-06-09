# Architecture

Status: PH004 Finalised  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: System architecture for the Live Performance Orchestration System

Runtime behaviour: `docs/RUNTIME_MODEL.md`  
Data ownership and persistence: `docs/DATA_ARCHITECTURE.md`

## Guiding Principles

- Creation is local.
- Collaboration is cloud.
- Performance is local.
- Ableton is master.
- The show must go on.

## Runtime Authority Model

During live performance, authority is split by concern:

| Concern | Authority | Document |
|---------|-----------|----------|
| Timeline (Song, Cue progression) | Ableton Live | `docs/RUNTIME_MODEL.md` §4 |
| Execution, display, integrations | Local Show Runtime | `docs/RUNTIME_MODEL.md` §11 |
| Master library, collaboration | Cloud Environment | `docs/ARCHITECTURE.md` (this document) |
| Entity definitions | Domain model | `docs/DOMAIN_MODEL.md` |

The software follows Ableton. It does not create or override cue progression during performance. Local Show Runtime is authoritative for all non-timeline runtime behaviour once performance begins.

Full runtime rules: `docs/RUNTIME_MODEL.md`

## Data Authority Model

Persistence authority is phase-aware. See `docs/DATA_ARCHITECTURE.md` for full matrices.

| Phase | Data Authority |
|-------|----------------|
| **Draft preparation** | Director Local (until publish) |
| **Published / collaboration** | Cloud (canonical) |
| **Live performance** | Local Show Runtime (runtime + readiness) |
| **Live timeline** | Ableton (external; not persisted as canonical) |

### Core Data Rules

- Local-first design/preparation is primary.
- Cloud supports collaboration, sync, backup, and distribution.
- Local Show Runtime is authoritative during performance.
- Sync-before-show is required; cloud is not required during performance.
- Cloud changes must not disrupt an active local performance.
- Shows and Performances reference canonical assets — no duplication.
- Files are managed assets in DigitalOcean Spaces with local cache before show.

Full data governance: `docs/DATA_ARCHITECTURE.md`

## Persistence Environment Responsibilities

| Environment | Persistence Role |
|-------------|-------------------|
| **Director Local** | Draft creation/editing; publish source; local DB + file cache |
| **Cloud** | Published canonical records; Laravel DB + Spaces; musician collaboration |
| **Local Show Runtime** | Performance-ready replica; runtime state; execution logs; offline authority |

### Director Local Environment

Primary design and preparation workstation.

- Authoring of master library assets (Songs, Charts, Mix Moves, Light Modes, etc.)
- Show and Performance preparation
- Local editing with sync to cloud
- May host Local Show Runtime for rehearsal

**Role:** Create and prepare. Source of local creation.

### Cloud Environment

Collaboration, backup, and distribution layer.

- Canonical store for master library and operational records
- Musician remote access for preparation
- Sync hub for Director Local and Show Runtime environments
- Backup and multi-user collaboration

**Role:** Collaborate and sync. Not required during live performance.

### Local Show Runtime

Offline-capable live performance execution environment.

- Docker-based local stack
- Local database and cache
- Local UI (Live Show View, Soundcheck)
- Ableton, X32, and Lighting integrations
- Operates with no internet connectivity

**Role:** Execute the show. Authoritative during performance.

## Environment Flow

```
Director Local Environment
        │
        │  sync (push/pull)
        ▼
Cloud Environment ──────────────────────┐
(DigitalOcean / Forge / Laravel)        │
        │                               │
        │  sync-before-show (required)  │ not required during
        ▼                               │ live performance
Local Show Runtime                      │
(Docker / Local DB / Local UI)          │
        │                               │
        ▼                               ▼
Performance Execution            (cloud optional)
```

## Sync-Before-Performance Data Flow

Explicit data flow from preparation to live execution. See `docs/DATA_ARCHITECTURE.md` §6–§7.

```
Director Local (draft)
    │ publish (validated)
    ▼
Cloud (published canonical + Spaces files)
    │ pull Published Package
    ▼
Local Show Runtime (performance-ready + file cache)
    │ Soundcheck / Readiness validation
    ▼
Live Performance (local authoritative; inbound sync blocked)
    │ post-performance (optional)
    ▼
Cloud (logs, readiness history, audit)
```

## Local-First Performance Runtime

Live performance is local-first by design:

- Live show assumes **no internet** (see `docs/RUNTIME_MODEL.md` §13).
- All data required for the active Performance must be present on Local Show Runtime before show start.
- Musician devices connect to Local Show Runtime on the local network.
- Ableton, X32, and lighting integrations use local links only.
- Cloud unavailability during performance has **zero impact** on live execution.

Local Show Runtime continues operating when cloud, internet, or external services (except local integrations) are unreachable.

## Sync-Before-Show Pattern

Sync is a **preparation requirement**, not a runtime dependency.

```
Preparation phase:
  Cloud (canonical) ──full sync──► Local Show Runtime

Performance phase:
  Local Show Runtime executes autonomously
  Cloud not required
  Sync optional (post-show backup/logs)
```

### Sync Rules

- Cloud is canonical for master library and operational records outside live execution.
- Local Show Runtime is authoritative during live performance.
- **Remote canonical data must be synced into Local Show Runtime before performance begins.**
- Sync is not required once performance starts.
- Sync conflicts are resolved in preparation — not during live performance.
- Sync State is informational during performance.

### Sync Is For

- Collaboration
- Backup
- Distribution
- Remote musician preparation
- Pre-show data replication to Local Show Runtime

### Sync Is Not For

- Driving the live timeline (Ableton owns this)
- Required connectivity during performance
- Real-time runtime state (Local Show Runtime owns this during show)

## Remote Canonical Environment (Cloud)

| Component | Technology |
|-----------|------------|
| Domain/DNS | User-owned domain |
| Hosting | DigitalOcean Droplet |
| Management | Laravel Forge |
| Backend | Laravel API / application |
| Database | PostgreSQL or MySQL (DigitalOcean) |
| File Storage | DigitalOcean Spaces |
| Authentication | Laravel Authentication |
| Realtime / Sync | Laravel WebSockets or polling (first) |

**Responsibilities:**

- Canonical master library storage
- Show and Performance records
- Musician collaboration access
- Sync orchestration
- Backup

**Not responsible for:** Live timeline authority (Ableton) or runtime execution during performance.

## Local Show Runtime

| Component | Technology |
|-----------|------------|
| Containerisation | Docker |
| Database | Local database (synced copy) |
| UI | Local web UI |
| Cache | Local cache as needed |
| Integrations | Ableton, X32, Lighting |

**Responsibilities:**

- Live Show View (Priority #1)
- Soundcheck execution
- Ableton Protocol State consumption (PGM/CC16)
- Action execution (Mix Moves, Light Modes, and other Action categories)
- Musician device experience
- Offline operation
- Runtime state management (see `docs/RUNTIME_MODEL.md` §3)

**Must continue operating when:**

- Internet is unavailable
- Cloud services are unavailable
- External systems (except Ableton/X32/Lighting local links) are unreachable

## Integration Architecture

### Ableton Integration

- Consumes PGM (Song) and CC16 (Cue) protocol
- Platform follows Ableton; does not own timeline
- Playlist imported from Ableton Show File
- PGM is show-scoped; platform maps to canonical Song records
- See `docs/RUNTIME_MODEL.md` §4

### X32 Integration

- Executes Mix Moves (grouped parameter changes)
- Monitor mix control during Soundcheck and live performance
- Not scene-recall based

### Lighting Integration

- Executes Light Mode Actions at Cue boundaries
- Driven by Ableton Protocol State via Local Runtime

## Data Architecture Summary

Entity layers and ownership classes are defined in `docs/DATA_ARCHITECTURE.md`.

| Layer | Entities | Authority Class |
|-------|----------|-----------------|
| Master Library | Band, Musician, Device, Instrument Part, Capability, Song, Chart, Snippet, Cue, Action, Mix Move, Light Mode, Production Configuration, Stage Plot, Tech Rider | Cloud-canonical (after publish) |
| Operational | Show, Performance, Assignment, Monitor Assignment, Soundcheck, Readiness | Hybrid (phase-dependent) |
| Runtime | Ableton Protocol State, Runtime State, Sync State | Derived/runtime-only or hybrid |

See `docs/DOMAIN_MODEL.md` for entity definitions.

## Frontend Architecture Alignment

Priority order (see `docs/INFORMATION_ARCHITECTURE.md`):

1. Live Show View
2. Soundcheck
3. Show Preparation
4. Master Library Management

Live Show View and Soundcheck run on Local Show Runtime. Preparation and library management may use Director Local or Cloud.

## Reliability Requirements

- Local Show Runtime must boot and operate without cloud connectivity.
- Action failures must not block performance — the show must go on (see `docs/RUNTIME_MODEL.md` §14).
- Musician device experience remains available live (monitor adjustments, chart override).
- Operator runs Performance, not Show.
- Readiness warnings do not necessarily block performance.

---

End of Architecture — PH004
