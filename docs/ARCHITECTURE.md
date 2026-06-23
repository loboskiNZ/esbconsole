# Architecture

Status: PH047 Amended (Band Portal Authentication & Canonical Identity)  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: System architecture for the Live Performance Orchestration System

Runtime behaviour: `docs/RUNTIME_MODEL.md`  
Integration architecture: `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`  
Data ownership and persistence: `docs/DATA_ARCHITECTURE.md`  
Database architecture and logical schema: `docs/DATABASE_ARCHITECTURE.md`  
Physical database and migration plan: `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`  
Foundation implementation plan: `docs/FOUNDATION_IMPLEMENTATION_PLAN.md`

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
| Database | PostgreSQL 16+ (DigitalOcean Managed PostgreSQL) |
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
| Database | PostgreSQL 16+ (Docker; synced copy) |
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

Full integration and runtime topology: `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`.

### Host OS vs Docker Boundary

| Layer | Components | Hardware access |
|-------|------------|-----------------|
| **Host OS** | Ableton Live, MIDI Bridge, Lighting/DMX Bridge, optional X32/OSC Bridge | Direct MIDI, USB-DMX, native APIs |
| **Docker (Local Show Runtime)** | Laravel app/API, local DB, Redis/Valkey, WebSocket/realtime, Local UI, file/cache volumes | **No** assumed direct MIDI or DMX hardware |

Host bridges communicate with Docker Local Show Runtime via approved local interfaces (HTTP, Redis pub/sub, WebSocket internal, or equivalent). Docker hosts application infrastructure — not direct hardware access.

### Integration / Runtime Component Topology

```
┌─ HOST OS ─────────────────────────────────────────────────────┐
│  Ableton ──MIDI──► MIDI Bridge ──event──┐                   │
│  Lighting Bridge ◄──command──┐           │                   │
│  X32 Bridge ◄──command───────┼───────────┼──► local interface │
└──────────────────────────────┼───────────┼────────────────────┘
                               │           │
┌─ DOCKER: Local Show Runtime ─┼───────────┘
│  Laravel App ◄──► Runtime Event Bus ◄──► Action Pipeline     │
│       │                    │                    │              │
│  Local DB              Redis/Valkey      WebSocket/Realtime    │
│       │                    │                    │              │
│  Local UI (Live Show View) │              Musician Devices     │
└────────────────────────────┴──────────────────────────────────┘
         (local network — no cloud required during performance)
```

### Ableton Integration

- Ableton emits PGM (Song) and CC16 (Cue) via MIDI
- **MIDI Bridge** (host) decodes and publishes TimelineEvents to Local Show Runtime
- Platform follows Ableton; does not own timeline
- Playlist imported from Ableton Show File during preparation
- PGM is show-scoped; platform maps to canonical Song records
- See `docs/RUNTIME_MODEL.md` §4 and `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` §5–§6

### X32 Integration

- Executes **Mix Moves** (grouped parameter changes) via **X32 Bridge**
- Monitor mix control during Soundcheck and live performance
- Not scene-recall based
- Failures logged and surfaced — non-blocking
- See `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` §8

### Lighting Integration

- Executes **Light Mode** Actions at Cue boundaries via **Lighting Bridge**
- Light Modes are authoring unit — bridge translates to DMX/Art-Net/sACN/lighting software
- USB-DMX hardware access is host-level
- Driven by Ableton Protocol State via Local Show Runtime
- See `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` §7

## Data Architecture Summary

Entity layers and ownership classes are defined in `docs/DATA_ARCHITECTURE.md`.

| Layer | Entities | Authority Class |
|-------|----------|-----------------|
| Master Library | Band, Musician, Person, Device, Instrument Part, Instrument Reference, Capability, Song, Chart, Snippet, Cue, Action, Mix Move, Light Mode, Production Configuration, Stage Plot, Tech Rider | Cloud-canonical (after publish) |
| Operational | Show, Performance, Assignment, Monitor Assignment, Soundcheck, Readiness | Hybrid (phase-dependent) |
| Runtime | Ableton Protocol State, Runtime State, Sync State | Derived/runtime-only or hybrid |

See `docs/DOMAIN_MODEL.md` for entity definitions.

### Band People / Production Personnel (PH045)

Band People is **canonical shared data** — one PostgreSQL schema for the local app and website.

| Rule | Statement |
|------|-----------|
| **Shared schema** | `people`, `person_secure_fields`, `person_files`, `instrument_reference`, `person_instruments`, `person_iem_settings` — no local-only or website-only duplicate tables. |
| **Musician vs Person** | `musicians` remains the operational domain (Performances, Assignments, Devices). `people` holds production personnel profiles; mapping is follow-up. |
| **Encryption** | Bank account, passport number, Air New Zealand points encrypted at rest via `person_secure_fields`. |
| **Private files** | `person_files.is_public` defaults false. |
| **IEM templates** | `person_iem_settings` are preference templates — not live console bus settings. |
| **Catalogs** | `instrument_reference` (personnel) and `instrument_parts` (operational roles) are separate; mapping may follow. |
| **Generated artifacts** | Stage plots, tech riders, input lists, monitor plans, festival packs are outputs generated from canonical data. |

## Database Architecture Summary

Logical database design is defined in `docs/DATABASE_ARCHITECTURE.md`. Physical technology, migration strategy, and delivery governance are defined in `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`.

### Selected Database Engine

**PostgreSQL 16+** for all environments (cloud, Director local, Local Show Runtime).

| Environment | Engine | Hosting |
|-------------|--------|---------|
| **Cloud** | PostgreSQL 16+ | DigitalOcean Managed PostgreSQL |
| **Director local** | PostgreSQL 16+ | Local instance or Docker |
| **Local Show Runtime** | PostgreSQL 16+ | Docker container |

Single engine chosen for migration parity, JSONB manifest support, and operational simplicity.

### Environment Database Topology

| Database | Purpose | Runtime dependency |
|----------|---------|-------------------|
| **Cloud** | Published canonical data; sync registry; audit | Not required during performance |
| **Director local** | Draft preparation; publish staging | Not required at show time |
| **Local Show Runtime** | Performance replica + runtime state + logs | **Required during performance** |

Schema changes via **Laravel migrations only** — see `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`.

Entity layers and ownership classes are defined in `docs/DATA_ARCHITECTURE.md`.

### Cloud / Local Database Boundary

| Data class | Cloud DB | Local runtime DB |
|------------|----------|------------------|
| Master library (published) | ✅ canonical | ✅ cached replica |
| Show / Performance (preparation) | ✅ canonical | ✅ pulled via Published Package |
| Assignments, Monitor Assignments | ✅ published | ✅ cached; locked at `live` |
| File asset metadata | ✅ canonical | ✅ manifest + cache status |
| Sync Package registry | ✅ canonical | ✅ local pull record |
| Runtime State, Ableton Protocol State | ❌ not live-canonical | ✅ authoritative during performance |
| Soundcheck / Readiness (show day) | optional post-sync | ✅ authoritative |
| Audit / history | ✅ long-term retention | show-day logs until post-sync |

Live performance **must not depend** on cloud database availability. All required records and file cache must exist in Local Show Runtime database before Soundcheck.

Schema implementation (migrations, models) must align with `docs/DATABASE_ARCHITECTURE.md`, `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`, and `docs/FOUNDATION_IMPLEMENTATION_PLAN.md`.

## Foundation Implementation Planning (PH008)

First implementable slice and stack baseline are defined in `docs/FOUNDATION_IMPLEMENTATION_PLAN.md`.

### Target stack (PH009)

| Component | Choice |
|-----------|--------|
| PHP | **8.4** (Forge, local dev, `/backend`, `/server`) |
| Backend | Laravel 13+ |
| Database | PostgreSQL 16+ |
| Cache/queue | Valkey 7+ (Redis-compatible) |
| Local dev | Docker Compose (app, postgres, valkey) |
| Auth | Laravel session auth — **username/password** on Band Portal (PH047); Laravel Breeze baseline for Director local app |
| Realtime | Laravel Reverb — planned post-foundation |
| Cloud files | DigitalOcean Spaces — post-foundation |

### First vertical slice

```
Login → Band context → Show list → Select active Show → Basic Playlist view
```

Proves Laravel foundation, PostgreSQL, auth, governed migrations, and Show/Playlist read path — **no runtime integrations**.

### Local development topology

```
Developer host
└── Docker Compose
    ├── Laravel app
    ├── PostgreSQL 16
    └── Valkey 7
Optional: existing client/ Vite dev server (port 5173)
No cloud, Ableton, or bridges required for foundation dev.
```

Cloud deployment and Local Show Runtime full stack remain future phases.

## Band Portal Application (`/server/`)

| Attribute | Value |
|-----------|-------|
| **URL** | `https://band.edandtheshadowboys.com` |
| **Purpose** | Band People onboarding, production personnel self-service, and future collaboration surfaces |
| **Database** | Shared PostgreSQL with canonical `people` schema (M3a) — same logical database as Director local app |
| **Auth consumer** | First implementation of M1 identity domain for portal members |

### Person vs User boundary (PH047)

| Layer | Responsibility |
|-------|----------------|
| **Person** | Canonical human/profile — legal name, artistic name, contact, travel, dietary, passport (secure fields), banking (secure fields), instruments, files, IEM templates |
| **User** | Authentication — `username`, password hash, access state, roles; **must link to Person**; must **not** duplicate Person profile or secure-field data |

Login credentials **never** belong on Person. Sensitive Person fields remain in `person_secure_fields` with application encryption — separate from password hashing.

### Band Portal deployment policy (PH047)

| Rule | Statement |
|------|-----------|
| **Default deploy path** | Agent or automation runs `./server/deploy/remote-deploy.sh` (with `--push` when committing) to trigger or verify Forge deploy hook after push to `main` |
| **Manual Forge deploy** | Operator uses Forge **Deploy Now** only for emergency recovery or explicit infrastructure intervention |
| **Verification** | After deploy, confirm `https://band.edandtheshadowboys.com/up` and targeted routes |

Details: `server/docs/FORGE_SETUP.md` §5 (Deploy from workstation).

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

End of Architecture — PH047
