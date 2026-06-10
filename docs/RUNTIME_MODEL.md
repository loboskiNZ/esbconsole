# Runtime Model

Status: PH006 Finalised  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: Canonical runtime and operational behaviour for the Live Performance Orchestration System

Related documents:

- Entity definitions: `docs/DOMAIN_MODEL.md`
- Integration architecture: `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`
- Infrastructure: `docs/ARCHITECTURE.md`
- Navigation: `docs/INFORMATION_ARCHITECTURE.md`

---

## 1. Runtime Purpose

The runtime model defines how the platform behaves during Soundcheck and live performance execution — after preparation is complete and before the show ends.

The runtime exists to:

- Follow Ableton as the authoritative timeline source
- Execute production Actions at Cue boundaries
- Deliver musician-centric guidance (charts, instructions, monitoring)
- Coordinate X32, lighting, and device integrations locally
- Operate fully offline during performance
- Keep the show running when individual subsystems fail

The runtime does **not**:

- Author playlists or cue progression
- Override Ableton timeline signals
- Require cloud connectivity during performance
- Block live execution when non-critical readiness warnings exist

The operator runs a **Performance** on **Local Show Runtime**. All runtime behaviour is scoped to the active Performance context.

---

## 2. Runtime Authority Rules

| Rule | Statement |
|------|-----------|
| Timeline authority | Ableton is the runtime timeline authority. |
| Follow, don't lead | The software follows Ableton. |
| No override | The software does not create or override cue progression during performance. |
| Local execution | Live performance runs locally. |
| Cloud optional | Cloud is not required during performance. |
| Local authority | Local runtime is authoritative during performance. |
| Cloud role | Cloud supports collaboration, backup, distribution, and preparation sync. |
| Reliability | The show must go on. |
| Host bridges | MIDI and DMX/USB hardware-facing integration runs on host OS — not inside Docker. See `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md`. |

### Authority Hierarchy (Runtime)

```
1. Ableton Live          → timeline (Song, Cue progression)
2. Local Show Runtime    → execution, display, integrations (during performance)
3. Cloud Environment     → not involved during live performance
```

During performance, canonical cloud data is a **stale copy** unless explicitly re-synced post-show. Local Show Runtime is the live source of truth for operational state, device connections, and action execution logs.

---

## 3. Runtime State Model

Runtime state is ephemeral — it exists for the duration of Soundcheck and live performance. It is distinct from master library entities and operational records (which are synced in before the show).

### Context State

| State | Description |
|-------|-------------|
| **Active Band** | The band context for the current runtime session. |
| **Active Show** | The Show template referenced by the active Performance. |
| **Active Performance** | The executable unit — venue, date, assignments, soundcheck. Operator runs this. |
| **Active Ableton Show File** | The Ableton file bound to the Active Show; source of playlist and PGM numbering. |

### Timeline State (derived from Ableton)

| State | Description |
|-------|-------------|
| **Current Song** | Song mapped from current PGM within Active Ableton Show File. |
| **Previous Song** | Prior song in playlist sequence (or null at show start). |
| **Next Song** | Upcoming song in playlist sequence (or null at show end). |
| **Current Cue** | Cue mapped from current CC16 within Current Song. |
| **Previous Cue** | Prior cue within Current Song (or prior song's last cue on song change). |
| **Next Cue** | Upcoming cue within Current Song (or next song's first cue on song change). |
| **Cue 0 / Preparation State** | Active when CC16 = 0; preparation phase before Cue 1 of Current Song. |

### Infrastructure State

| State | Description |
|-------|-------------|
| **Runtime Connection State** | Status of integrations: Ableton link, X32 link, lighting link, musician device connections. |
| **Runtime Sync State** | Last sync timestamp, sync completeness, pending conflicts (informational during performance). |
| **Runtime Readiness State** | Aggregate readiness from Soundcheck: system, production, musician (see §10). |

### State Transitions

```
[Sync complete] → Soundcheck → Readiness → Live Performance
                                              ↓
                                    Ableton PGM/CC16 drives
                                    timeline state updates
                                              ↓
                                    Actions execute on cue entry
                                              ↓
                                    Musician guidance updates
```

Timeline state updates **only** in response to Ableton protocol signals (PGM, CC16). The platform never publishes cue progression to Ableton during performance.

---

## 4. Ableton Protocol Model

Ableton Live is the external runtime master. The platform consumes protocol signals and maps them to canonical records.

### Protocol Signals

| Signal | Meaning |
|--------|---------|
| **PGM** | Identifies the song within the active Ableton Show File. |
| **CC16** | Identifies the cue/section within the active song. |

### PGM Rules

- PGM numbers are **show-scoped**, not globally unique.
- The same Song asset may map to different PGM values in different Shows.
- Mapping: `PGM value` + `Active Ableton Show File` → canonical Song record.

### CC16 Rules

| CC16 Value | Meaning |
|------------|---------|
| **0** | Preparation cue (Cue 0). |
| **1 and onward** | Song sections (Intro, Verse, Chorus, etc.). |

### Ableton Show File Ownership

The Ableton Show File owns:

- Playlist
- Song order
- Song numbers (PGM assignments)
- Cue timing
- Cue progression

The platform **imports** this data during preparation. It does **not** author or override it during performance.

### Platform Mapping

Integration ingress and bridge topology: `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` §5–§6.

```
Incoming: PGM + CC16 (from Ableton via MIDI Bridge)
    ↓
Lookup: Active Ableton Show File → Show → Song (by PGM) → Cue (by CC16)
    ↓
Update: Runtime timeline state
    ↓
Trigger: Action execution, musician guidance
```

Example:

```
PGM 12, CC16 1  →  Song 12 / Intro (Cue 1)
PGM 12, CC16 0  →  Song 12 / Preparation (Cue 0)
```

---

## 5. Cue 0 Preparation Model

Cue 0 (CC16 = 0) is the preparation phase before Cue 1 of the current song begins.

### Purpose

Cue 0 prepares the **next song** (or current song before first section) before musical sections begin.

### Cue 0 May Trigger

- Chart loading for assigned musicians
- Snippet preparation for upcoming Cue 1
- Musician instructions (role-specific, performance-specific)
- Monitor preparation (routing, levels context)
- Next-song awareness (Previous / Current / Next Song display)

### Behaviour Rules

| Rule | Statement |
|------|-----------|
| Default | Cue 0 behaviour is **automatic by default**. |
| Override | Musicians may override display and browse charts without changing authoritative runtime state. |
| Authority | Browsing override affects **display only** — Ableton timeline state remains authoritative. |
| Scope | Cue 0 actions and guidance are scoped to Current Song and assigned musicians. |

Cue 0 is not a musical section. It is a preparation window. Domain Actions may be attached to Cue 0 (e.g. pre-load charts, display instructions) but must not block Cue 1 entry.

---

## 6. Cue Context Model

The runtime maintains cue awareness for operator and musician displays.

### Minimum Required Context

| Context | Required |
|---------|----------|
| Previous Cue | Yes |
| Current Cue | Yes |
| Next Cue | Yes |

### Extended Context (Future)

- Runtime may later support deeper lookahead (e.g. Cue +2, +3).
- Not required for PH002 baseline.

### Musician Pre-Cue Guidance

- Musician devices should show upcoming instructions **before** the next cue where configured.
- Pre-cue instructions are guidance — they do not advance the timeline.
- Display updates on Cue 0 and as lookahead configuration permits.

### Song Context (Companion)

Cue context is always within Current Song. Song context (Previous / Current / Next Song) is maintained alongside cue context for playlist awareness.

---

## 7. Action Execution Model

Actions are attached to Cues in the domain model. At runtime, Actions execute when Ableton reaches the corresponding cue.

### Execution Rules

| Rule | Statement |
|------|-----------|
| Trigger | Actions execute automatically when Ableton reaches the cue (PGM + CC16 match). |
| Multiplicity | A cue may execute multiple actions. |
| Isolation | A failed action must not stop remaining actions in the same cue. |
| Logging | Failures must be logged and surfaced to the operator. |
| Non-blocking | Action failure must not block performance or timeline progression. |

### Action Categories

| Category | Description |
|----------|-------------|
| **Musician Instruction** | Display text or guidance to specific musician(s). |
| **Chart Navigation** | Load or switch chart snippet for assigned musician(s). |
| **X32 Mix Move** | Execute grouped X32 parameter changes (not scene recall). |
| **Light Mode** | Activate a lighting look/state. |
| **MIDI Action** | Send MIDI command to external device. |
| **OSC Action** | Send OSC message to external system. |
| **Ableton/Fallback Source Action** | Action sourced from or delegated to Ableton when platform coverage is incomplete. |

### Execution Flow

Integration pipeline detail: `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` §11.

```
Ableton: PGM + CC16 change (via MIDI Bridge → Runtime Event Bus)
    ↓
Platform: resolve Cue → load Actions for Cue
    ↓
For each Action (sequential or parallel per implementation):
    execute → log success/failure → continue
    ↓
Operator: failures surfaced in Live Show View (non-blocking)
```

Human-targeted actions (Musician Instruction, Chart Navigation) resolve targets via **Assignment** — instructions go to assigned musicians for the current Performance/Song/Cue context.

---

## 8. Musician Guidance Model

The musician device experience is **musician-centric**, not instrument-centric.

### Core Rules

| Rule | Statement |
|------|-----------|
| Identity | Device belongs to Musician, not Instrument Part. |
| Display | Musician sees consolidated instructions for their current assignment(s). |
| Navigation | Automatic chart/snippet navigation is the default. |
| Override | Manual musician override and chart browsing is allowed. |
| Targeting | Human guidance targets specific musicians (via Assignment). |

### Musician Device Display (Minimum)

- Previous Cue / Current Cue / Next Cue
- Current chart snippet (automatic by cue)
- Instructions (including Cue 0 preparation guidance)
- Monitor controls (More/Less Me, Click, Tracks — live-available)

### Assignment Resolution at Runtime

For each timeline update, runtime resolves:

```
Musician + Device → Assignment(s) for Current Performance/Song/Cue
    → Instrument Part(s) → Chart(s) → Snippet(s)
    → display on device
```

A musician with multiple assignments in the same cue context receives consolidated guidance.

### Override Behaviour

- Manual chart browse changes **local device display only**.
- Does not affect Ableton timeline, Action execution, or other musicians' displays.
- Automatic navigation resumes on next cue transition unless override persists by configuration.

---

## 9. Soundcheck Runtime Model

Soundcheck is a first-class pre-performance runtime process on Local Show Runtime.

### Scope

Soundcheck validates the active Performance before live execution:

- Ableton connection and Active Ableton Show File
- X32 connection and Mix Move readiness
- Lighting connection
- Musician device connections
- Assignments (coverage, chart availability)
- Monitor routing and levels
- Chart/snippet validation

### Musician Participation

Musicians participate directly during Soundcheck. Each musician may:

- Connect their Device
- Confirm chart visibility and correctness
- Check ears / adjust monitoring
- Confirm personal readiness

### Soundcheck vs Live Performance

| Phase | Soundcheck | Live Performance |
|-------|------------|------------------|
| Timeline authority | Ableton (optional/rehearsal mode) | Ableton (authoritative) |
| Musician monitoring adjustment | Yes | Yes |
| Action execution | Test/dry-run permitted | Automatic on cue |
| Operator focus | Validation | Execution |

Soundcheck completes when operator and musicians have validated systems. Results feed into Runtime Readiness State (§10).

---

## 10. Readiness State Model

Readiness is the aggregate gate state between Soundcheck and live performance execution.

### Readiness Dimensions

Readiness is **collaborative** — three independent dimensions:

| Dimension | Covers |
|-----------|--------|
| **System Readiness** | Ableton, X32, lighting, Local Show Runtime, network/local integrations. |
| **Production Readiness** | Assignments, playlist mapping, Actions configured, Production Configuration applied. |
| **Musician Readiness** | Device connections, chart validation, monitor checks, personal confirmations. |

Each dimension may be: ready, warning, or not-ready.

### Gate Behaviour

| Rule | Statement |
|------|-----------|
| Warnings | Readiness warnings do **not necessarily block** performance. |
| Operator decision | Operator may proceed to live execution with warnings logged. |
| Missing coverage | Missing assigned musicians or instrument coverage may fall back to Ableton. |
| No hard stop | Readiness is guidance for the operator — not a system-enforced lockout (unless configured by operator policy). |

### Fallback Rule

When platform assignment coverage is incomplete (musician unavailable, device disconnected, chart missing):

- Platform logs the gap and surfaces a warning.
- Ableton remains authoritative for timeline and may serve as fallback source for uncovered roles.
- The show must go on — degraded guidance is preferable to halted execution.

### State Flow

```
Soundcheck activity
    ↓
Aggregate: System + Production + Musician readiness
    ↓
Runtime Readiness State updated
    ↓
Operator: proceed to Live Performance (with or without warnings)
```

---

## 11. Local Runtime Authority

Local Show Runtime is the execution authority during Soundcheck and live performance.

### Local Show Runtime Owns (During Performance)

- Runtime state model (§3)
- Action execution and failure logs
- Musician device sessions and display state
- Runtime Connection State
- Runtime Readiness State (live updates)
- Monitor adjustment commands to X32

### Local Show Runtime Does Not Own

- Timeline progression (Ableton)
- Master library authoring (Director Local / Cloud)
- Canonical cloud records (Cloud — stale during performance)

### Pre-Performance Requirement

Remote canonical data must be synced into Local Show Runtime **before** performance begins. See §12 and §13.

Local Docker/runtime database may be used for show-safe offline operation. All data required for the active Performance must be present locally before the operator starts live execution.

---

## 12. Cloud Sync Runtime Rules

### Cloud Role (Outside Performance)

Cloud is canonical for:

- Master library
- Show and Performance records
- Assignments
- Collaboration and remote musician preparation

### Sync-Before-Show Pattern

```
Cloud (canonical)
    ↓ full sync
Local Show Runtime (performance-ready copy)
    ↓
Soundcheck → Readiness → Live Performance
```

| Rule | Statement |
|------|-----------|
| Pre-show sync | Remote canonical data must be synced into local runtime before performance. |
| During show | Sync is **not required** once performance starts. |
| Post-show | Optional sync back to cloud for logs, updates, backup. |
| Conflict | Sync conflicts are resolved in preparation — not during live performance. |

### Runtime Sync State

During performance, Runtime Sync State is **informational only**:

- Last sync timestamp
- Sync completeness indicator
- Pending conflict count (if any unresolved from pre-show)

Sync failures during performance are logged but do not affect live execution.

---

## 13. Offline Operation Rules

Live show must assume **no internet**.

### Requirements

| Rule | Statement |
|------|-----------|
| Assumption | Live performance assumes no internet connectivity. |
| Self-sufficient | Local Show Runtime must operate with all synced data locally. |
| No cloud calls | No runtime behaviour depends on cloud API availability during performance. |
| Integrations | Ableton, X32, and lighting integrations use local network links only. |
| Musician devices | Musician devices connect to Local Show Runtime on local network. |

### Offline-Capable Assets (Must Be Local)

- Active Performance and Assignments
- Show, Production Configuration, Ableton Show File reference
- Songs, Charts, Snippets, Cues, Actions
- Mix Moves, Light Modes
- Musician and Device records for available musicians
- Chart files (PDF/image assets)

### Offline Failure Mode

If Local Show Runtime fails entirely:

- Ableton continues independently (timeline authority).
- X32 and lighting may remain in last-known state.
- Operator falls back to manual production control.
- The show must go on.

---

## 14. Failure Handling Rules

| Scenario | Required Behaviour |
|----------|---------------------|
| Single Action failure | Log, surface to operator, continue remaining Actions and performance. |
| X32 unreachable | Log, surface warning, continue performance; monitor adjustments may be unavailable. |
| Lighting unreachable | Log, surface warning, continue performance. |
| Musician device disconnected | Log, surface warning; other musicians unaffected; fallback to Ableton if role uncovered. |
| Chart/snippet missing | Log, surface warning; musician may browse manually; performance continues. |
| Cloud unreachable during performance | No impact — cloud not required. |
| Ableton disconnect | Critical — timeline authority lost; operator must restore or run show manually from Ableton. |
| Local database error | Attempt graceful degradation; Ableton timeline continues; log for post-show recovery. |

### Principles

- **Never block timeline** — Ableton cue progression is never halted by platform failures.
- **Fail visible** — all failures logged and surfaced to operator in Live Show View.
- **Fail isolated** — one musician's or one action's failure does not cascade to others.
- **The show must go on** — degraded operation beats halted operation.

---

## 15. Runtime Concepts Glossary

| Term | Definition |
|------|------------|
| **Active Performance** | The Performance currently being executed on Local Show Runtime. |
| **Action** | Production event attached to a Cue; executes automatically at cue entry. |
| **Assignment** | Operational mapping of Musician to Instrument Part for a Performance/Song/Cue context. |
| **Ableton Protocol State** | Live PGM and CC16 values consumed from Ableton. |
| **Ableton Show File** | External file bound to a Show; owns playlist and PGM numbering. |
| **CC16** | MIDI control change identifying cue/section within current song (0 = preparation). |
| **Cue 0** | Preparation cue (CC16 = 0) before first musical section of a song. |
| **Cue Context** | Previous, Current, and Next Cue maintained by runtime for display and guidance. |
| **Local Show Runtime** | Offline-capable Docker stack executing Soundcheck and live performance. |
| **PGM** | Program change identifying song within active Ableton Show File. |
| **Pre-cue Guidance** | Instructions shown to musicians before the next cue transition. |
| **Readiness** | Aggregate system, production, and musician readiness after Soundcheck. |
| **Runtime Connection State** | Live status of Ableton, X32, lighting, and device integrations. |
| **Runtime Readiness State** | Live readiness dimensions during Soundcheck and at performance start. |
| **Runtime Sync State** | Informational sync metadata; not a live performance dependency. |
| **Sync-Before-Show** | Required pattern: full cloud → local sync before performance begins. |
| **Timeline Authority** | Ableton — sole source of Song and Cue progression during performance. |

---

End of Runtime Model — PH006
