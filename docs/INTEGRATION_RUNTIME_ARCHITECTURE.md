# Integration & Runtime Architecture

Status: PH006 Finalised  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: Canonical integration and runtime architecture for live performance execution

Related documents:

- Runtime behaviour: `docs/RUNTIME_MODEL.md`
- Entity definitions: `docs/DOMAIN_MODEL.md`
- Infrastructure: `docs/ARCHITECTURE.md`
- Data ownership: `docs/DATA_ARCHITECTURE.md`
- Database design: `docs/DATABASE_ARCHITECTURE.md`

This document defines **how** local integrations connect, communicate, and fail during Soundcheck and live performance — not implementation code, Docker compose files, package installs, or bridge source code.

**Out of scope for PH006:** application code, migrations, services, API routes, frontend, Docker configuration, MIDI/DMX/OSC/X32/WebSocket implementation.

---

## 1. Purpose

PH006 establishes the canonical integration and runtime architecture for the Live Performance Orchestration System.

Goals:

- Define host OS vs Docker boundaries for hardware-facing integrations
- Specify MIDI Bridge, Lighting/DMX Bridge, and X32 Bridge responsibilities
- Document Ableton event flow from timeline signal to Action execution
- Define Runtime Event Bus, Action Execution Pipeline, and musician device communication
- Govern connection state, failure handling, offline operation, and local network model
- Provide the integration gate before bridge or realtime implementation begins

Integration implementation **must comply** with this document and `docs/RUNTIME_MODEL.md`.

---

## 2. Runtime Integration Principles

| # | Principle |
|---|-----------|
| 1 | **Ableton is the runtime master.** Timeline authority (PGM/CC16) originates from Ableton — never overridden by bridges or Local Show Runtime. |
| 2 | **Live performance runs locally without internet.** Cloud is not required once performance starts. |
| 3 | **Docker hosts app infrastructure — not direct hardware access.** MIDI and USB-DMX hardware-facing integration runs at host OS level. |
| 4 | **Bridges are translators, not owners.** Bridges decode/encode protocol; Local Show Runtime owns show state and Action orchestration. |
| 5 | **The show must go on.** Non-critical integration failures must not halt performance or remaining cue Actions. |
| 6 | **Failed Actions are logged and surfaced.** Operator awareness without timeline blocking. |
| 7 | **Authoring uses domain assets.** Light Modes and Mix Moves — not raw DMX values or X32 scene recalls as primary models. |
| 8 | **Musician devices are local-network clients.** No cloud dependency during performance. |
| 9 | **Manual chart browsing is display-only.** Must not change authoritative runtime state. |
| 10 | **Sync-before-show.** Required package and file cache present locally before integration-dependent Soundcheck. |

---

## 3. Host OS vs Docker Boundary

### Host OS responsibilities

Processes that require direct hardware access or low-latency OS-level I/O run on the **host machine**, outside Docker:

| Component | Host reason |
|-----------|-------------|
| **Ableton Live** | DAW; MIDI source; not containerised |
| **MIDI Bridge** | Direct access to physical/virtual MIDI ports |
| **Lighting Bridge / DMX Bridge** | USB-DMX interfaces; some native lighting software APIs |
| **X32 Bridge** (if host-level) | Optional when OSC routing requires host network binding |
| **OSC Bridge** (if host-level) | Optional when host-level port binding or routing required |

### Docker / Local Show Runtime responsibilities

Containerised application infrastructure — no assumed direct MIDI or DMX hardware access:

| Component | Role |
|-----------|------|
| **Laravel app / API** | Business logic, Action orchestration, state management |
| **Local runtime database** | Performance-ready data, runtime state, logs |
| **Redis / Valkey** | Cache, queues, pub/sub backing store |
| **WebSocket / realtime service** | Musician device push; Live Show View updates |
| **Local UI** | Director Live Show View, Soundcheck screens |
| **Local file / cache volumes** | Chart PDFs, Ableton Show File cache, asset manifest |

### Boundary rule

```
Host bridges  ←→  approved local interface  ←→  Docker Local Show Runtime
     (hardware)              (HTTP/WebSocket/Redis/message bus)        (orchestration)
```

Docker services **must not** be assumed to have direct MIDI or DMX hardware access. Network lighting protocols (Art-Net, sACN/E1.31) **may** be invoked from Docker or host depending on reliability constraints — see §7.

---

## 4. Local Show Runtime Topology

### Host machine

```
┌─────────────────────────────────────────────────────────────┐
│  HOST OS                                                     │
│                                                              │
│  ┌──────────┐   ┌─────────────┐   ┌──────────────────────┐ │
│  │ Ableton  │──►│ MIDI Bridge │──►│ approved local       │ │
│  │ Live     │   │ (PGM/CC16)  │   │ interface            │ │
│  └──────────┘   └─────────────┘   └──────────┬───────────┘ │
│                                               │             │
│  ┌──────────────────┐  ┌───────────────────┐│             │
│  │ Lighting Bridge  │◄─┤                   ││             │
│  │ / DMX Bridge     │  │  Optional:        ││             │
│  └──────────────────┘  │  X32 Bridge       ││             │
│  ┌──────────────────┐  │  OSC Bridge       ││             │
│  │ X32 (network)    │◄─┤                   ││             │
│  └──────────────────┘  └───────────────────┘│             │
└──────────────────────────────────────────────┼─────────────┘
                                               │
┌──────────────────────────────────────────────┼─────────────┐
│  DOCKER — Local Show Runtime                 ▼             │
│                                                              │
│  ┌────────────┐  ┌──────────┐  ┌─────────┐  ┌───────────┐ │
│  │ Laravel    │  │ Local DB │  │ Redis/  │  │ WebSocket │ │
│  │ App/API    │  │          │  │ Valkey  │  │ / Realtime│ │
│  └────────────┘  └──────────┘  └─────────┘  └───────────┘ │
│  ┌────────────┐  ┌──────────────────────────────────────┐  │
│  │ Local UI   │  │ File / cache volumes                 │  │
│  └────────────┘  └──────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
         ▲
         │ local network (Wi-Fi / Ethernet)
         │
  ┌──────┴──────┐
  │  Musician   │
  │  Devices    │
  └─────────────┘
```

### Approved local interfaces (host ↔ Docker)

| Interface | Use |
|-----------|-----|
| HTTP/REST (localhost) | Bridge → runtime event ingestion |
| WebSocket (internal) | Realtime fan-out to UI and devices |
| Redis pub/sub or streams | Event bus backing; decoupled bridge ingress |
| Unix domain socket / named pipe | Alternative low-latency bridge ingress (implementation choice) |

Specific protocol selection is deferred to implementation (see §19).

---

## 5. MIDI Bridge Architecture

### Role

The **MIDI Bridge** runs on the **host OS**. It is the sole ingress path for Ableton timeline signals into Local Show Runtime.

### Responsibilities

| Responsibility | Owner |
|----------------|-------|
| Listen to Ableton MIDI / virtual MIDI port | MIDI Bridge |
| Decode PGM (song) and CC16 (cue) | MIDI Bridge |
| Publish runtime timeline event to Local Show Runtime | MIDI Bridge |
| Map PGM + Active Show File → canonical Song | Local Show Runtime |
| Map CC16 → canonical Cue | Local Show Runtime |
| Own show state | Local Show Runtime — **not** MIDI Bridge |
| Override Ableton | **Never** |

### Protocol decoding

| Signal | Rule |
|--------|------|
| **PGM** | Identifies song within active Ableton Show File (show-scoped). |
| **CC16** | Identifies cue/section within active song. |
| **CC16 = 0** | Preparation cue (Cue 0). |
| **CC16 ≥ 1** | Song sections (Intro, Verse, Chorus, etc.). |

### MIDI Bridge must not

- Store canonical show data
- Execute Actions directly
- Send cue progression commands to Ableton
- Block or delay on downstream failures (fire-and-forward with local retry/logging only)

### Output event (conceptual)

```
TimelineEvent {
  pgm: number
  cc16: number
  timestamp: ISO8601
  source: "midi-bridge"
}
```

Published to Local Show Runtime via approved local interface.

---

## 6. Ableton Event Flow

Canonical event flow from Ableton timeline change to subsystem execution:

```
Ableton Live
    │  PGM / CC16 via MIDI
    ▼
MIDI Bridge (host)
    │  decode; publish TimelineEvent
    ▼
Runtime Event Bus (Local Show Runtime)
    │  ingest TimelineEvent
    ▼
Local Runtime — Resolve Song / Cue
    │  lookup Active Show File → Song (PGM) → Cue (CC16)
    │  update Runtime State (Previous/Current/Next)
    ▼
Action Execution Pipeline
    │  load Actions for Cue
    │  dispatch by category (parallel/ordered groups)
    ├──► Musician Devices (chart, instructions, cue context)
    ├──► X32 Bridge → Mix Moves
    ├──► Lighting Bridge → Light Modes
    ├──► OSC / MIDI output Actions
    └──► Runtime Logs / Live Show View
```

### Key invariants

- Ableton drives timing — platform reacts.
- Resolution and Action loading happen in Local Show Runtime, not in MIDI Bridge.
- Cue 0 triggers preparation Actions and musician guidance without blocking Cue 1 entry.

---

## 7. Lighting / DMX Bridge Architecture

### Authoring model

- Lighting is controlled through reusable **Light Modes** (domain assets).
- Cue **Actions** reference Light Modes — not raw DMX channel values as the primary authoring model.
- Raw DMX is an **implementation translation** layer inside the Lighting Bridge.

### Lighting Bridge responsibilities

| Responsibility | Owner |
|----------------|-------|
| Receive Light Mode activation command from Local Show Runtime | Lighting Bridge |
| Translate Light Mode → output protocol | Lighting Bridge |
| USB-DMX direct hardware access | Lighting Bridge (host OS) |
| Art-Net / sACN / E1.31 over network | Lighting Bridge or Docker (see below) |
| Lighting software commands (QLC+, etc.) | Lighting Bridge (host) |

### Output protocols (translation targets)

| Protocol | Typical access |
|----------|----------------|
| **DMX (USB interface)** | Host-level Lighting Bridge only |
| **Art-Net** | Host or Docker (network UDP) |
| **sACN / E1.31** | Host or Docker (network UDP) |
| **Lighting software API** | Host-level |
| **Future protocols** | Via bridge plugin pattern |

### Placement rule

| Access type | Placement |
|-------------|-----------|
| USB-DMX hardware | **Host OS** — not inside Docker |
| Network lighting (Art-Net, sACN) | Host or Docker — chosen at implementation based on reliability and OS networking constraints |
| Domain Light Mode definition | Local Show Runtime database (synced from cloud) |

### Failure rule

Lighting Bridge failure is **non-blocking**. Action logged and surfaced; remaining cue Actions continue; performance does not stop.

---

## 8. X32 Bridge Architecture

### Authoring model

- X32 actions use reusable **Mix Moves** — grouped parameter changes.
- Mix Moves are **not** primarily X32 scene recalls.
- Cue Actions reference Mix Moves; X32 Bridge translates to X32 protocol commands.

### X32 Bridge responsibilities

| Responsibility | Owner |
|----------------|-------|
| Receive Mix Move execution command | X32 Bridge |
| Translate Mix Move → X32 OSC/API commands | X32 Bridge |
| Monitor mix adjustments (Soundcheck / live) | X32 Bridge or direct runtime path |
| Own mix state canonical definition | Local Show Runtime (Mix Move asset) |

### Connection model

- X32 is typically network-attached on local show network.
- X32 Bridge may run host-level or as Docker sidecar with network access — implementation choice (OQ-003).
- X32 communication uses X32 OSC protocol (approved).

### Failure rule

X32 Bridge failure is **non-blocking**. Mix Move Action logged and surfaced; remaining Actions continue; musician monitoring may degrade but performance continues.

---

## 9. OSC / MIDI Output Architecture

Some Actions send **outbound** OSC or MIDI commands to external systems (not ingress from Ableton).

| Aspect | Rule |
|--------|------|
| **Trigger** | Action Execution Pipeline on cue entry |
| **Authoring** | Action category: MIDI Action or OSC Action |
| **Execution** | Local Show Runtime dispatches to OSC Bridge or MIDI output path |
| **Placement** | Host-level when hardware port binding required; Docker when network-only |
| **Failure** | Logged; non-blocking |

OSC/MIDI output Actions are distinct from MIDI Bridge ingress (Ableton → runtime).

---

## 10. Runtime Event Bus

The **Runtime Event Bus** is the internal message path within Local Show Runtime connecting ingress, orchestration, and fan-out.

### Event categories

| Category | Source | Consumers |
|----------|--------|-----------|
| **TimelineEvent** | MIDI Bridge | State resolver, Action pipeline, Live Show View |
| **ActionCommand** | Action pipeline | X32 Bridge, Lighting Bridge, OSC Bridge, musician push |
| **ActionResult** | Bridges / executors | Logs, Live Show View, readiness |
| **ConnectionEvent** | Health monitors | Connection State, Readiness, Live Show View |
| **MusicianEvent** | Device clients | Monitor adjustments (local only; not timeline) |
| **DisplayOverrideEvent** | Musician device | Chart browse override (display-only) |

### Bus principles

| Principle | Statement |
|-----------|-----------|
| **Single timeline ingress** | Only MIDI Bridge publishes TimelineEvents during performance. |
| **Idempotent cue handling** | Duplicate PGM/CC16 events handled gracefully (no double Action burst). |
| **Decoupled fan-out** | Musician push and operator UI subscribe to bus — not direct bridge coupling. |
| **Local only** | Event bus does not traverse cloud during performance. |
| **Backing store** | Redis/Valkey or in-process — implementation choice. |

---

## 11. Action Execution Pipeline

### Trigger

Cue reached via Ableton → MIDI Bridge → Runtime Event Bus → Song/Cue resolved.

### Pipeline stages

```
1. Cue entry detected (PGM + CC16 match)
2. Load Actions for Cue from local runtime database
3. Group Actions by execution policy
4. Execute groups (parallel within group; ordered between groups if required)
5. Collect ActionResult per Action
6. Log all results
7. Surface failures to Live Show View (non-blocking)
8. Push musician-facing Actions to device channel
```

### Execution grouping (logical)

| Group | Actions | Parallelism |
|-------|---------|-------------|
| **Human guidance** | Musician Instruction, Chart Navigation | Parallel per musician |
| **Production** | Mix Move, Light Mode, MIDI, OSC | Parallel unless Action declares order dependency |
| **Fallback** | Ableton/Fallback Source | After primary or on coverage gap |

### Failure policy (baseline)

| Rule | Statement |
|------|-----------|
| **Isolation** | Failure of one Action does not block remaining Actions in the cue. |
| **Logging** | Every failure recorded with Action ID, Cue, timestamp, error. |
| **Surfacing** | Operator sees failure in Live Show View — not modal blocking. |
| **Timeline** | Ableton cue progression unaffected by Action failure. |
| **Critical vs non-critical** | Baseline: all production Actions treated as non-blocking; critical classification deferred (OQ-005). |

---

## 12. Musician Device Communication

### Connection model

- Musician devices connect to **Local Show Runtime** on the **local network** (Wi-Fi or Ethernet).
- Authentication against local runtime (cached credentials / session from pre-show sync).
- **No cloud connectivity required** during performance.

### Data pushed to devices

| Data | Source |
|------|--------|
| Current Song | Runtime State (from PGM) |
| Current Cue / Previous / Next Cue | Runtime State (from CC16) |
| Chart / Snippet updates | Assignment + Cue → Chart/Snippet resolution |
| Musician-specific instructions | Actions targeted via Assignment |
| Cue 0 preparation guidance | Cue 0 Actions + Assignment |
| Monitor controls | X32 path (local) |

### Transport

| Layer | Technology |
|-------|------------|
| **Primary** | WebSocket or SSE from Local Show Runtime realtime service |
| **Fallback** | HTTP polling (degraded; Soundcheck should warn) |

### Manual chart browsing

| Rule | Statement |
|------|-----------|
| **Allowed** | Musician may browse charts/snippets ahead or review past sections. |
| **Display only** | Override affects device display — not authoritative Runtime State. |
| **Timeline** | Ableton timeline and operator Live Show View unaffected. |
| **Event** | DisplayOverrideEvent on bus — logged optionally. |

---

## 13. Connection State Model

Runtime tracks connection health for Soundcheck, Readiness, and Live Show View.

| Connection | Monitored | Impact if down |
|------------|-----------|----------------|
| **Ableton** | MIDI Bridge heartbeat + last TimelineEvent age | Timeline stale; operator warning; last-known state displayed |
| **MIDI Bridge** | Bridge health endpoint / last event timestamp | No new cue updates; operator alert |
| **Local Show Runtime** | App health / database | Show cannot execute — critical |
| **X32** | OSC response / heartbeat | Mix Move Actions fail; monitor may degrade; non-blocking |
| **Lighting Bridge** | Bridge health / last command ACK | Light Mode Actions fail; non-blocking |
| **Musician device** | WebSocket presence | Individual device offline; others continue |
| **Local network** | Latency / packet loss (optional) | Degraded musician push; warning |
| **Cloud** | Not monitored during performance | No impact by design |

Connection State feeds **System Readiness** dimension (see `docs/RUNTIME_MODEL.md` §10).

---

## 14. Failure Handling Model

| Failure | Handling |
|---------|----------|
| **Ableton disconnected** | MIDI Bridge detects silence; Connection State → degraded; operator warning; hold last cue context; no auto timeline advance |
| **MIDI Bridge disconnected** | No TimelineEvents; operator alert; Soundcheck/Live Show View show bridge offline; manual operator awareness |
| **Local runtime unavailable** | Critical — performance cannot proceed; Soundcheck blocks or warns prominently |
| **X32 unavailable** | Mix Move Actions log failure; monitor adjustments unavailable; performance continues |
| **Lighting unavailable** | Light Mode Actions log failure; performance continues |
| **Musician device disconnected** | Device marked offline; other devices continue; musician reconnects to local runtime |
| **Local network degraded** | WebSocket retry; polling fallback; operator warning; core execution continues |
| **Cloud unavailable** | **No impact** during performance — by design |
| **File asset missing** | Detected at Soundcheck; production readiness warning; chart Action may fail non-blocking |
| **Action execution failure** | Logged; surfaced; remaining Actions in cue execute; timeline unaffected |

All failures follow: **log → surface → continue** unless Local Show Runtime itself is down.

---

## 15. Offline Operation Model

| Phase | Cloud required? | Integration behaviour |
|-------|-----------------|----------------------|
| **Preparation** | Optional (sync/publish) | Director Local ↔ Cloud |
| **Pre-show sync** | Required for pull | Published Package → Local Show Runtime |
| **Soundcheck** | No | All bridges and devices on local network |
| **Live performance** | **No** | Host bridges + Docker runtime autonomous |
| **Post-performance** | Optional | Logs/readiness may sync back |

### Offline requirements before performance

- Published Show Package pulled to local runtime database
- Required file assets cached locally with verified checksums
- Ableton Show File available to Ableton on host
- MIDI Bridge configured and connected to Ableton virtual port
- X32 and lighting bridges configured for show network
- Musician devices authenticated against local runtime

Local runtime database/cache is **authoritative during performance**.

---

## 16. Local Network Model

### Network zones

| Zone | Members |
|------|---------|
| **Show LAN** | Host machine, Docker runtime (published ports), X32, musician devices, optional lighting network nodes |
| **Internet** | Not required during performance |
| **Cloud** | Not in performance data path |

### Addressing assumptions

- Local Show Runtime UI and API reachable at stable host/LAN address (e.g. `show.local` or static IP).
- Musician devices join same LAN as runtime (venue Wi-Fi or dedicated show network).
- X32 on same subnet or routable show network.
- Art-Net/sACN may use dedicated lighting VLAN — bridge routes accordingly.

### Ports and discovery

Specific port assignments deferred to implementation (OQ-004). mDNS/Bonjour for `show.local` discovery optional.

---

## 17. Security / Trust Boundaries

| Boundary | Trust model |
|----------|-------------|
| **Host bridges → Local Show Runtime** | Localhost or authenticated internal API; bridges are trusted local processes |
| **Musician devices → Local Show Runtime** | Authenticated session (Laravel auth cache); HTTPS/WSS on LAN optional (venue policy) |
| **Local Show Runtime → X32 / lighting** | Show network; no internet exposure required |
| **Cloud → Local Show Runtime** | Blocked inbound during `live` performance state |
| **Musician device → timeline** | **No write path** — display override only |
| **MIDI Bridge → Ableton** | Read-only consumption of MIDI output |

Musicians may update permitted self-service data only. Show-critical data changes require Director authority and publish workflow.

---

## 18. Logging and Observability

| Log type | Capture location | Sync to cloud |
|----------|------------------|---------------|
| TimelineEvent ingress | Local Show Runtime | Optional post-performance |
| Action execution (success/failure) | Local Show Runtime | Optional post-performance |
| Bridge health / connection changes | Local Show Runtime + bridge local logs | Optional post-performance |
| Musician device connect/disconnect | Local Show Runtime | Optional post-performance |
| DisplayOverrideEvent | Local Show Runtime | Optional |
| Cue transition history | Local Show Runtime | Optional post-performance |
| Operator notes | Local Show Runtime | Optional post-performance |

### Operator visibility

- **Live Show View** — primary observability surface during performance.
- **Soundcheck** — connection and file readiness before show.
- **Bridge local logs** — diagnostics only; not operator-primary.

Logs support post-show analysis — not real-time cloud dashboards during performance.

---

## 19. Deferred Implementation Decisions

| ID | Question | Deferred to |
|----|----------|-------------|
| OQ-001 | Approved local interface: HTTP vs Redis streams vs Unix socket for bridge ingress | Implementation kickoff |
| OQ-002 | X32 Bridge host vs Docker placement | Implementation |
| OQ-003 | Art-Net/sACN from Docker vs host | Implementation / venue networking |
| OQ-004 | Port assignments and mDNS discovery | Implementation |
| OQ-005 | Critical vs non-critical Action classification | Product policy |
| OQ-006 | WebSocket vs SSE for musician push | Implementation |
| OQ-007 | Action execution ordering dependencies schema | Schema / PH005.01 |
| OQ-008 | MIDI Bridge technology (Node, Python, etc.) | Implementation |
| OQ-009 | Multiple Ableton instances / failover | Future scope |
| OQ-010 | Bridge process supervision (systemd, launchd, Docker Compose sidecar metadata) | Docker configuration phase |

None block PH006 logical integration design completion.

---

## 20. Glossary

| Term | Definition |
|------|------------|
| **Bridge** | Host-level or network service translating between hardware/protocol and Local Show Runtime. |
| **Lighting Bridge** | Host-level service translating Light Mode commands to DMX/Art-Net/sACN/lighting software. |
| **MIDI Bridge** | Host-level service decoding Ableton PGM/CC16 and publishing TimelineEvents. |
| **Mix Move** | Reusable grouped X32 parameter change — not primarily a scene recall. |
| **Light Mode** | Reusable lighting look/state — authoring unit for lighting Actions. |
| **Local Show Runtime** | Docker-hosted application stack for show-day execution. |
| **Runtime Event Bus** | Internal message path connecting ingress, orchestration, and fan-out. |
| **TimelineEvent** | PGM + CC16 (+ timestamp) ingress event from MIDI Bridge. |
| **X32 Bridge** | Service translating Mix Move commands to X32 OSC protocol. |
| **Display override** | Musician chart browse affecting display only — not timeline authority. |

---

End of Integration & Runtime Architecture — PH006
