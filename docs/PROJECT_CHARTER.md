# X32/Ableton Software Rebuild Charter v0.2

Status: PH007 Finalised  
Owner: Ed Lobo  
Project: Ed and the Shadow Boys Production Platform  
Purpose: Charter Foundation for Full Refactor/Rebuild

Entity definitions: `docs/DOMAIN_MODEL.md`  
Runtime behaviour: `docs/RUNTIME_MODEL.md`  
Integration architecture: `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`  
Data ownership: `docs/DATA_ARCHITECTURE.md`  
Database architecture: `docs/DATABASE_ARCHITECTURE.md`  
Physical database plan: `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`  
UX and workflows: `docs/UX_MODEL.md`  
Navigation: `docs/INFORMATION_ARCHITECTURE.md`

## 1. Product Purpose

The purpose of this platform is to enable Ed and the Shadow Boys to design, prepare, rehearse, manage, and perform complex live productions.

The platform coordinates:

- Ableton
- X32
- Lighting
- Musicians
- Charts
- Devices
- Monitor mixes
- Production assets
- Show preparation
- Soundcheck
- Live performance

The platform is not primarily a database.

The platform is a:

**Live Performance Orchestration System**

## 2. Product Philosophy

### The Show Must Go On

Live performance is the highest priority.

The platform must continue operating during a performance even if:

- Internet is unavailable
- Cloud services are unavailable
- External systems are unreachable

### Ableton Is The Master

Ableton is the authoritative runtime source for:

- Current Song
- Current Cue
- Song progression
- Timeline progression

The software follows Ableton.

The software does not own the timeline.

### Guidance Over Restriction

The platform should assist performers.

The platform should not trap performers.

Examples:

- Automatic chart navigation with manual override
- Automatic assignments with operator override
- Automated preparation with musician flexibility

### Single Source Of Truth

Reusable information should exist once.

Shows reference assets.

Assets should not be duplicated unnecessarily.

## 3. Frontend Priority Order

The application must be designed from the performance moment backwards.

Priority order:

1. Live Show View
2. Soundcheck View
3. Show Preparation
4. Master Library Management

The live show experience is the centre of gravity of the product.

Preparation screens must never compromise:

- Reliability
- Clarity
- Performance
- Usability

of the live show experience.

## 4. Core Runtime Principle

### Local First

Assume:

**No Internet**

during live performance.

The platform must operate locally.

Cloud services are optional during runtime.

Cloud services support:

- Collaboration
- Backup
- Synchronisation
- Distribution

Cloud services do not run the show.

## 5. Core Domain Model

### Band

The top-level container for the act. All master assets and Shows belong to a Band.

Example: Ed and the Shadow Boys.

### Entity Hierarchy

```
Band
├── Musicians
├── Devices
├── Instrument Parts
├── Songs
│   ├── Charts
│   ├── Cues
│   └── Actions
├── Light Modes
├── Mix Moves
├── Production Configurations
├── Shows
│   ├── Ableton Show File
│   ├── Playlist
│   └── Production Configuration
└── Performances
    ├── Show
    ├── Venue
    ├── Date
    ├── Available Musicians
    ├── Assignments
    └── Soundcheck
```

### Global Assets (Master Library)

- Musicians
- Devices
- Instrument Parts
- Capabilities
- Songs (with Charts, Snippets, Cues, Actions)
- Light Modes
- Mix Moves
- Production Configurations
- Stage Plot
- Tech Rider

These exist independently of Shows.

### Shows

A Show is a rehearsed production.

Examples:

- Callejero Tour - Full Band
- Callejero Tour - Lo-Fi
- Callejero Tour - Acoustic

Different production variants are different Shows.

### Performances

A Performance is a specific occurrence of a Show.

Examples:

- Dunedin
- Wanaka
- Tokyo
- Denpasar

A Performance references a Show.

Full entity definitions: `docs/DOMAIN_MODEL.md`

## 6. Show Model

A Show contains:

- Ableton Show File
- Playlist
- Production Configuration

A Show must have:

**One Ableton Show File**

without exception.

A Show without an Ableton file is invalid.

## 7. Performance Model

A Performance contains:

- Venue
- Date
- Show
- Assignments
- Available Musicians
- Soundcheck State
- Device Connections

A Performance is what gets executed.

The operator runs:

**Performance**

not Show.

## 8. Song Model

Songs are global reusable assets.

Shows reference Songs.

Songs are not owned by Shows.

A Song contains:

- Charts
- Instrument Requirements
- Cues
- Actions

Songs may be progressively completed.

## 9. Cue Model

A Cue is:

**The start of a musical section**

Examples:

- Intro
- Verse 1
- Chorus
- Bridge
- Solo
- Ending

A Cue is not:

- Light Flash
- Delay On
- Effect Trigger

Those are Actions attached to a Cue.

## 10. Ableton Protocol Model

Ableton is authoritative.

Current protocol:

- PGM = Song
- CC16 = Cue

Example:

```
PGM 12
CC16 1
```

Song 12 / Intro

### Cue 0

Cue 0 is a preparation cue.

Purpose:

- Prepare musicians
- Load charts
- Load snippets
- Display instructions
- Prepare monitoring

before Cue 1 begins.

## 11. Playlist Model

Playlist source:

**Ableton Show File**

The software imports the playlist.

The software does not author the playlist.

Rules:

- Songs appear once.
- No duplicate songs in playlists.

Encore behaviour:

**Return to Song/Cue**

rather than duplicating songs.

## 12. Musician Model

Musicians are global assets.

Musicians are availability-driven.

Assignments are made per Performance.

A musician may:

- Play multiple instruments
- Sing multiple roles
- Change instruments during songs
- Change roles during songs

Voice roles are instrument parts.

Examples:

- Lead Vocal
- Harmony Vocal
- Backing Vocal

## 13. Assignment Model

Assignment is a standalone operational entity.

An Assignment maps a Musician to an Instrument Part within a Performance, optionally scoped to a Song or Cue.

Assignment is distinct from:

- **Capability** — what a Musician can do
- **Availability** — whether a Musician is present for a Performance
- **Instrument Part** — the role definition
- **Device** — how the Musician connects
- **Chart** — what the Musician views

Assignments are made per Performance. They may vary by Song and by Cue when musicians change instruments or roles during a show.

Full definition: `docs/DOMAIN_MODEL.md`

## 14. Device Model

Devices belong to musicians.

Devices do not belong to instruments.

A musician connects using their assigned device.

## 15. Musician Experience

The musician experience is musician-centric.

Not instrument-centric.

The musician sees:

- Previous Cue
- Current Cue
- Next Cue

At minimum.

The musician receives:

- Instructions
- Chart Snippets
- Preparation Guidance

for themselves.

## 16. Chart Model

Songs may contain:

**Multiple charts**

because different instruments require different charts.

Example:

```
Song
 ├─ Guitar Chart
 ├─ Bass Chart
 ├─ Vocal Chart
 └─ Keyboard Chart
```

Charts support snippets.

One snippet may be associated with one cue.

### Chart Navigation

Default:

**Automatic**

Cue changes may automatically display the correct snippet.

Override:

**Allowed**

Musicians may browse ahead or review other sections.

## 17. Production Assets

### Mix Moves

Reusable X32 actions.

Examples:

- Guitar Solo
- Vocal Lift
- Acoustic Section
- Finale Boost

Mix Moves consist of grouped parameter changes.

Not scene recalls.

### Light Modes

Reusable lighting assets.

Examples:

- Verse
- Chorus
- Ballad
- Solo
- Finale

Light Modes are referenced by cues.

## 18. Soundcheck Model

Soundcheck is a first-class concept.

Before a Performance:

```
Soundcheck
↓
Readiness
↓
Run Performance
```

Soundcheck includes:

- Ableton
- X32
- Lighting
- Devices
- Musicians
- Assignments
- Monitoring
- Charts

Musicians participate directly.

Musicians may:

- Confirm readiness
- Check ears
- Adjust monitoring
- Validate charts

## 19. Monitoring Model

Musicians own their monitor experience.

Musicians may adjust:

- More Me
- Less Me
- More Click
- Less Click
- More Tracks
- Less Tracks

during performance.

This remains available live.

## 20. Canonical Data Model

### Master Library

- Band
- Musicians
- Devices
- Instrument Parts
- Capabilities
- Songs
- Charts
- Snippets
- Cues
- Actions
- Light Modes
- Mix Moves
- Production Configurations
- Stage Plot
- Tech Rider

### Operational Layer

- Shows
- Performances
- Assignments
- Soundchecks
- Readiness

### Runtime State

- Local Runtime
- Sync State
- Ableton Protocol State

Shows reference master assets.

Assets are not duplicated.

Canonical definitions: `docs/DOMAIN_MODEL.md`

## 21. Infrastructure Architecture

Approved platform:

**Domain/DNS**  
User-owned domain

**Hosting**  
DigitalOcean Droplet

**Management**  
Laravel Forge

**Backend**  
Laravel

**Database**  
PostgreSQL or MySQL

**File Storage**  
DigitalOcean Spaces

**Authentication**  
Laravel Authentication

**Realtime**  
Laravel WebSockets / polling

**Live Runtime**  
Local cache support

## 22. Local Runtime Architecture

Three environments (see `docs/ARCHITECTURE.md`):

### Director Local Environment

Director workstation. Primary design and preparation environment.

### Cloud Environment

Collaboration environment.

Used for:

- Musician access
- Remote preparation
- Sync
- Backup
- Distribution

### Local Show Runtime

Offline-capable live performance execution.

Docker stack:

- Local database
- Local UI
- Local services
- Ableton integration
- X32 integration
- Lighting integration

The live show must continue without cloud connectivity.

## 23. Current Guiding Principle

- Creation is local.
- Collaboration is cloud.
- Performance is local.
- Ableton is master.
- The show must go on.

## 24. Runtime Model

Canonical runtime and operational behaviour is defined in `docs/RUNTIME_MODEL.md`.

This includes:

- Runtime authority rules (Ableton timeline, Local Show Runtime execution)
- Runtime state model (Active Performance, timeline state, readiness, connections)
- Ableton protocol model (PGM/CC16 mapping)
- Cue 0 preparation model
- Action execution model
- Musician guidance model
- Soundcheck and Readiness runtime behaviour
- Offline operation and failure handling rules

Implementation must conform to `docs/RUNTIME_MODEL.md` before database or application work begins.

## 25. Information Architecture & UX Model

Canonical user experience and navigation are defined in:

- `docs/UX_MODEL.md` — UX authority (personas, screens, workflows, principles)
- `docs/INFORMATION_ARCHITECTURE.md` — navigation authority (hierarchy, visibility)

This includes:

- User personas (Director, Musician, Tech, Administrator)
- Show-centric navigation model
- Frontend priority order (Live Show View first)
- Live Show View and Musician Device Experience behaviour
- Soundcheck, Show Preparation, and Master Library experiences
- Screen catalogue and UX principles

The Director is the primary user. All UX decisions must be justified against Live Show View priority.

Implementation must conform to `docs/UX_MODEL.md` and `docs/INFORMATION_ARCHITECTURE.md` before frontend implementation begins.

## 26. Data Architecture & Persistence Model

Canonical data ownership, sync boundaries, and persistence rules are defined in `docs/DATA_ARCHITECTURE.md`.

### Data Authority Principles

- Local-first design/preparation is primary.
- Cloud supports collaboration, sync, backup, and distribution.
- Local Show Runtime is authoritative during performance.
- Live performance must not depend on internet access.
- Sync must happen before show runtime.
- Cloud changes must not disrupt an active local performance.
- Reusable master data has one canonical owner.
- Shows and Performances reference canonical assets instead of duplicating them.
- Files are managed assets in DigitalOcean Spaces — not scattered local paths.
- Runtime cue state is local/live; cloud is not authoritative for live timeline.

Implementation must conform to `docs/DATA_ARCHITECTURE.md` before database schema design begins.

## 27. Database Architecture & Logical Schema Design

Canonical database architecture and logical schema design are defined in `docs/DATABASE_ARCHITECTURE.md`.

This includes:

- Logical data domains (Identity, Band, Music Library, Production Asset, Show, Performance, Assignment, Soundcheck/Readiness, Runtime State, Sync, File Asset, Audit/History)
- Entity-to-domain mapping for all canonical entities
- Aggregate boundaries (Band, Song, Show, Performance, Musician, Production Configuration, Runtime State)
- Cloud vs Local Show Runtime database responsibilities
- Published Show Package sync model
- File asset persistence (Spaces + local cache; production folders excluded from Git)
- Identity/access persistence (User vs Musician; Laravel auth)
- Audit, lifecycle, deletion, and migration principles

Implementation must conform to `docs/DATABASE_ARCHITECTURE.md` and `docs/DATA_ARCHITECTURE.md` before database migrations or physical schema implementation begins.

## 28. Integration & Runtime Architecture

Canonical integration and runtime architecture for live performance execution is defined in `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`.

This includes:

- Host OS vs Docker boundary (hardware-facing bridges on host; app infrastructure in Docker)
- MIDI Bridge architecture (Ableton PGM/CC16 ingress)
- Lighting / DMX Bridge architecture (Light Mode → DMX/Art-Net/sACN)
- X32 Bridge architecture (Mix Move → X32 OSC)
- Runtime Event Bus and Action Execution Pipeline
- Musician device communication on local network
- Connection State, failure handling, and offline integration operation

**MIDI and DMX/USB hardware-facing integrations are host-level bridge responsibilities.** Docker Local Show Runtime hosts the Laravel app, database, Redis/Valkey, WebSocket/realtime service, and Local UI — not direct MIDI or DMX hardware access.

Implementation of MIDI, lighting, X32, OSC, runtime event bus, or device realtime communication must conform to `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` before bridge or integration code is written.

## 29. Physical Database & Migration Planning

Canonical physical database technology choices, migration strategy, and delivery governance are defined in `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`.

This includes:

- **PostgreSQL 16+** selected for cloud, Director local, and Local Show Runtime databases
- Laravel migrations as the sole approved schema change mechanism
- Environment database topology and sync direction per database
- Initial migration domains (M1–M14) and dependency order
- Identifier, foreign key, indexing, file asset, runtime state, sync package, and audit persistence strategies
- Seed, rollback, backup, testing, and prohibited database practices

No migrations may be created until PH007 is complete and referenced. Physical schema implementation must conform to `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`, `docs/DATABASE_ARCHITECTURE.md`, and `docs/DATA_ARCHITECTURE.md`.

---

End of Charter v0.2 — PH007
