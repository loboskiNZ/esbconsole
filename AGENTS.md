# X32/Ableton Rebuild Agent Governance

Status: PH005 Finalised

## Authority Order

1. `docs/PROJECT_CHARTER.md`
2. `docs/DOMAIN_MODEL.md`
3. `docs/RUNTIME_MODEL.md`
4. `docs/DATA_ARCHITECTURE.md`
5. `docs/DATABASE_ARCHITECTURE.md`
6. `docs/UX_MODEL.md`
7. `docs/INFORMATION_ARCHITECTURE.md`
8. `docs/ARCHITECTURE.md`
9. `docs/DECISION_LOG.md`
10. Implementation Tasks

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
- Cue 0 = Preparation Cue.
- Soundcheck is a first-class concept.
- Musicians are global; availability is performance-level.
- Devices belong to musicians.
- Instrument Parts are global.
- Musicians may change instruments/roles by song or cue.
- Musician device view is musician-centric.
- Mix Moves and Light Modes are reusable assets.
- The show must go on.
- Production assets (`/resources/`, `/songs/`, `/charts/`, `/uploads/`) are not Git assets.

## Canonical Entities (PH001)

Band, Musician, Device, Instrument Part, Capability, Song, Chart, Snippet, Cue, Action, Mix Move, Light Mode, Production Configuration, Show, Performance, Assignment, Stage Plot, Tech Rider, Soundcheck, Readiness, Local Runtime, Sync State, Ableton Protocol State.

Full definitions: `docs/DOMAIN_MODEL.md`  
Runtime behaviour: `docs/RUNTIME_MODEL.md`  
Data ownership: `docs/DATA_ARCHITECTURE.md`  
Database architecture: `docs/DATABASE_ARCHITECTURE.md`  
UX and workflows: `docs/UX_MODEL.md`  
Navigation: `docs/INFORMATION_ARCHITECTURE.md`

## Development Rules

- Make scoped changes only.
- Do not invent domain concepts.
- Do not invent workflows.
- Do not invent terminology.
- Protect live show reliability.
- Protect local-first operation.
- Protect Ableton authority.
- Documentation/governance tasks must not produce application code, migrations, routes, or UI.
- Database migrations and schema implementation must not proceed unless aligned with `docs/DATABASE_ARCHITECTURE.md` and `docs/DATA_ARCHITECTURE.md`.

## Completion Report

Every implementation must return:

1. Summary
2. Files Modified
3. Technical Notes
4. Verification Performed
5. Risks / Observations
6. Rollback Notes
