# PH043.01 — X32 Configuration Domain Discovery Audit

**Status:** Audit complete (read-only); PH043.06 send-control readiness appended  
**Date:** 2026-06-17 (PH043.01); 2026-06-17 (PH043.06)  
**Authority:** `docs/x32/PH043_X32_CONFIGURATION_CONTRACT.md`  
**Related:** `docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md`, `docs/x32/PH042_ROUTING_DISCOVERY_AUDIT.md`, `docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md`  
**Scope:** PH043.01 audit only — no application code changes

---

## Executive Summary

The project **already learns substantial mixer configuration** during Console Learn, but it is stored as a **flat baseline summary** (`channels`, `buses`, `dcas`, `matrices`, `fx`, plus routing and connectivity keys) — not as a separate `configuration` domain per PH043 contract.

**What works today:** Scene recall, operator scene number/name (live OSC), channel fader/mute/name/colour and selected processing flags, bus fader/mute/name/colour, DCA/matrix fader/mute, and virtual-console overview display for 32 input channels.

**What is missing for PH043:** Console identity (firmware, sample rate, clock), channel source/gain/phantom/icon/stereo-link/DCA membership/bus-send summary, real DCA/matrix/FX names and structure, bus stereo link and purpose, global settings (talkback, user assign), and a **dedicated configuration workspace** separate from routing.

**Boundary note:** Routing tables and source connectivity are **partially implemented** under PH042 (post-`bc1b360`). They must remain separate from PH043 configuration learn per contract. Connectivity (`/-stat/*`) is live-enriched on the routing page only — not a configuration page.

**Bottom line:** PH043.02 should introduce a structured `configuration` block in learned baselines, expand OSC reads for documented paths not yet queried, and avoid conflating routing patch data with configuration state.

---

## Verification Legend

| Column | Meaning |
|---|---|
| **Available** | Documented in X32 OSC protocol reference and/or readable in principle from desk |
| **Already Captured** | Queried during learn (live or fixture) and present in summary |
| **Stored** | Persists in `learned_summary_json` / `baseline_json` after save |
| **Displayed** | Shown in an operator-facing UI (console overview, routing, review, admin) |

OSC path status:

| Tag | Meaning |
|---|---|
| **Doc-verified** | Listed in Unofficial X32/M32 OSC Remote Protocol (Patrick-Gilles Maillot) |
| **In codebase** | Path helper exists in `X32OscAddressMap` or `X32InputChannelControlMap` |
| **Not in codebase** | No address helper; path known from external doc only |
| **Unknown** | Requires live-desk probe before implementation |

---

## Sources Inspected

### Contract & prior audits
- `docs/x32/PH043_X32_CONFIGURATION_CONTRACT.md`
- `docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md`
- `docs/x32/PH042_ROUTING_DISCOVERY_AUDIT.md`
- `docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md`

### OSC / transport
- `backend/app/Services/X32/X32OscAddressMap.php`
- `backend/app/Services/X32/X32InputChannelControlMap.php`
- `backend/app/Services/X32/X32SourceConnectivityOscAddressMap.php` *(connectivity — not configuration)*
- `backend/app/Services/X32/X32RoutingOscAddressMap.php` *(routing — not configuration)*
- `backend/app/Services/X32/OscUdpX32ConsoleSnapshotReader.php`
- `backend/app/Services/X32/FakeX32ConsoleSnapshotReader.php`
- `backend/app/Services/X32/RoutingX32ConsoleSnapshotReader.php`
- `backend/app/Services/X32/X32SceneMetadataService.php`
- `backend/app/Contracts/X32/X32OscConsoleClientInterface.php`
- `backend/app/Services/X32/OscUdpX32OscConsoleClient.php`
- `backend/app/Services/X32/FakeX32OscConsoleClient.php`

### Learn pipeline
- `backend/app/Services/Console/X32ConsoleLearningService.php`
- `backend/app/Services/Console/ShowConsoleBaselineService.php`
- `backend/app/Services/Console/ShowConsoleWorkspaceResolver.php`
- `backend/app/Models/ConsoleLearningSnapshot.php`
- `backend/app/Models/ShowConsoleBaseline.php`

### UI / builders
- `backend/app/Services/Console/VirtualConsoleStripBuilder.php`
- `backend/app/Services/Console/ShowConsoleStripEnricher.php`
- `backend/app/Services/Console/X32RoutingWorkspaceBuilder.php` *(routing domain)*
- `backend/app/Http/Controllers/ConsoleController.php`
- `backend/resources/views/console/workspace.blade.php`
- `backend/resources/views/console/_console-header.blade.php`
- `backend/resources/views/console/routing.blade.php`
- `backend/resources/views/console/review.blade.php`
- `backend/resources/views/console/baseline.blade.php`
- `backend/resources/views/console/_virtual-console.blade.php`

### Tests
- `backend/tests/Feature/ConsoleLearningTest.php`
- `backend/tests/Feature/ConsoleRoutingTest.php`
- `backend/tests/Unit/OscUdpX32ConsoleSnapshotReaderTest.php`
- `backend/tests/Unit/FakeX32ConsoleSnapshotReaderTest.php`
- `backend/tests/Unit/X32RoutingWorkspaceBuilderTest.php`
- `backend/tests/Unit/X32SceneMetadataServiceTest.php`
- `backend/tests/Feature/X32OscTransportTest.php`

### External OSC reference
- Unofficial X32/M32 OSC Remote Protocol (Maillot) — used for paths not yet in codebase

---

## Current Baseline Data Shape

Learn stores a **single JSON summary** (not yet split per PH043 contract):

```json
{
  "transport": "live_osc | fake_fixture",
  "console_type": "x32",
  "device_key": "...",
  "device_name": "...",
  "requested_scene_number": "02",
  "scene_number": "02",
  "scene_name": "…",
  "channels": [ /* 32 items */ ],
  "buses": [ /* 16 items */ ],
  "dcas": [ /* 8 items */ ],
  "matrices": [ /* 6 items */ ],
  "fx": [ /* 0 live; 4 fixture placeholders */ ],
  "routing": { /* PH042 domain */ }
}
```

**Gap vs PH043 contract:** No top-level `configuration`, `connectivity`, or `routing` separation. Connectivity lives under `routing.source_connectivity`. Configuration and mixer state are interleaved at the root.

**Timestamps:** `learned_at` on snapshot row; `saved_at` on baseline row — not duplicated inside summary JSON.

---

## Area 1 — Console Identity

| Domain | Item | OSC Path | Available | Already Captured | Stored | Displayed | Notes |
|---|---|---|---|---|---|---|---|
| Identity | Console name (desk) | `/info` → server_name; `/xinfo` → network name | Doc-verified | No | No | Partial | UI shows **IntegrationDevice.name**, not desk-reported name |
| Identity | Device profile | App `IntegrationConnectionProfile` | N/A (app) | Yes | Yes | Partial | Host/port from device config; not OSC |
| Identity | Firmware version | `/info` → console_version | Doc-verified | No | No | No | |
| Identity | Console model | `/info` → console_model; device `configuration.console_model` | Doc-verified / app | Partial | Partial | Partial | Enum from device config; live `/info` not read |
| Identity | Sample rate | `/-prefs/clockrate` `{48K, 44K1}` | Doc-verified | No | No | Partial | UI pill hardcodes **48K** when not live-linked |
| Identity | Clock source | `/-prefs/clocksource` | Doc-verified | No | No | No | |
| Identity | Current scene (operator) | Learn command + `scene_number` in summary | Yes | Yes | Yes | Yes | Subbar + routing header |
| Identity | Current scene name | `/-show/showfile/scene/NNN/name` | Doc-verified / In codebase | Yes (live) | Yes (live) | Yes | Routing header; live enrich if missing |

---

## Area 2 — Channel Configuration (CH 01–32)

| Domain | Item | OSC Path | Available | Already Captured | Stored | Displayed | Notes |
|---|---|---|---|---|---|---|---|
| Channel | Channel number | — | Yes | Yes | Yes | Yes | Index 1–32 |
| Channel | Name | `/ch/NN/config/name` | In codebase | Yes (live) | Yes | Yes | Overview strips + review tables |
| Channel | Colour | `/ch/NN/config/color` | In codebase | Yes (live) | Yes | Yes | Virtual strip colour |
| Channel | Icon | `/ch/NN/config/icon` | Doc-verified | No | No | No | |
| Channel | Source assignment | `/ch/NN/config/source` | Doc-verified | No | No | No | Per-channel tap; distinct from input **bank** routing (PH042) |
| Channel | Gain | `/headamp/NNN/gain` via `/-ha/NN/index` | Doc-verified / Partial map | No (live) | Partial | Partial | Control map defines path; **headamp index mapping not implemented**; fixture only sets `controls.gain` |
| Channel | Phantom power | `/headamp/NNN/phantom` | Doc-verified / Partial map | No (live) | Partial | Partial | Same headamp dependency |
| Channel | Stereo/mono (link) | `/config/chlink/1-2` … `31-32` | Doc-verified | No (live) | Partial | Partial | Fixture `stereo_link` in controls; UI-only flag, not OSC read |
| Channel | Mute | `/ch/NN/mix/on` | In codebase | Yes | Yes | Yes | |
| Channel | Fader | `/ch/NN/mix/fader` | In codebase | Yes | Yes | Yes | |
| Channel | Gate on | `/ch/NN/gate/on` | In codebase | Yes | Yes | Yes | Strip control |
| Channel | Compressor on | `/ch/NN/dyn/on` | In codebase | Yes | Yes | Yes | Strip control |
| Channel | EQ on | `/ch/NN/eq/on` | In codebase | Yes | Yes | Yes | Strip control |
| Channel | Pan | `/ch/NN/mix/pan` | In codebase | Yes | Yes | Yes | Strip control |
| Channel | Main L/R send | `/ch/NN/mix/st` | In codebase | Yes | Yes | Yes | Mix assignment, not output routing |
| Channel | DCA membership | `/ch/NN/grp/dca` (%int bitmap) | Doc-verified | No | No | No | |
| Channel | Bus send summary | `/ch/NN/mix/01…16` (level/on) | Doc-verified | Yes (PH043.05) | Yes | Yes (monitor Channels card) | 32×16 matrix; level+on all buses; pan/type on odd buses only |
| Channel | Bus send level | `/ch/NN/mix/BB/level` | Doc-verified (Maillot OSC) | Yes (PH043.05) | Yes | Yes | level [0.0…1.0(+10dB), 161] dB — `X32FaderScale` |
| Channel | Bus send on | `/ch/NN/mix/BB/on` | Doc-verified | Yes (PH043.05) | Yes | Yes | enum OFF/ON (int 0/1) |
| Channel | Bus send pan | `/ch/NN/mix/BB/pan` | Doc-verified | Yes (PH043.05) | Yes | Partial | linf [-100, 100, 2]; **odd BB only** (01,03…15) |
| Channel | Bus send tap/type | `/ch/NN/mix/BB/type` | Doc-verified | Yes (PH043.05) | Yes | Partial | int 0–5: in_lc, pre_eq, post_eq, pre_fader, post_fader, grp; **odd BB only** |
| Channel | Bus send pan follow | `/ch/NN/mix/BB/panFollow` | Doc-verified | Yes (PH043.05) | Stored | No | odd BB ≥03 only; not shown on Channels card in PH043.05 |
| Channel | Sends UI flag | — | UI-only | Partial | Partial | Yes | `sends` control opens UI; **no bus matrix read** |

---

## Area 3 — Bus Configuration (BUS 01–16)

| Domain | Item | OSC Path | Available | Already Captured | Stored | Displayed | Notes |
|---|---|---|---|---|---|---|---|
| Bus | Bus number | — | Yes | Yes | Yes | Yes | |
| Bus | Name | `/bus/NN/config/name` | In codebase | Yes (live) | Yes | Yes | Routing IEM grid + review |
| Bus | Colour | `/bus/NN/config/color` | In codebase | Yes (live) | Yes | Partial | Learned; not shown on routing IEM grid |
| Bus | Icon | `/bus/NN/config/icon` | Doc-verified | No | No | No | |
| Bus | Stereo/mono (link) | `/config/buslink/1-2` … `15-16` | Doc-verified | No | No | No | |
| Bus | Bus type / mode | `/bus/NN/config/...` (see bus config chapter) | Doc-verified | No | No | No | Exact mode path needs live probe; dynamics paths documented |
| Bus | Sends-on-fader | Bus layer SOF settings | Unknown | No | No | No | Requires investigation |
| Bus | Assigned outputs | `/outputs/*` (PH042) | Doc-verified | No* | Partial* | Partial | *Routing domain — derived in routing builder, not bus config page |
| Bus | Primary purpose | — | Derived | No | No | Partial | Operator labels inferred from names in routing IEM section only |
| Bus | Fader / mute | `/bus/NN/mix/fader`, `/bus/NN/mix/on` | In codebase | Yes | Yes | Yes (monitor bus workspace PH043.09) | Fader: `X32FaderScale` linear [0…1]; on int 0/1 — mute UI = `on === 0` |
| Bus | Master EQ on | `/bus/NN/eq/on` | Doc-verified (Maillot OSC) | Yes (PH043.04) | Yes | Yes (monitor bus workspace) | Enum OFF/ON (int 0/1) |
| Bus | EQ band type | `/bus/NN/eq/1…6/type` | Doc-verified | Yes (PH043.04) | Yes | Yes | int 0–13: LCut, LShv, PEQ, VEQ, HShv, HCut, BU6…LR24 |
| Bus | EQ band frequency | `/bus/NN/eq/1…6/f` | Doc-verified | Yes (PH043.04) | Yes | Yes | logf [20, 20000, 201] Hz |
| Bus | EQ band gain | `/bus/NN/eq/1…6/g` | Doc-verified | Yes (PH043.04) | Yes | Yes | linf [-15, 15, 0.25] dB |
| Bus | EQ band Q | `/bus/NN/eq/1…6/q` | Doc-verified | Yes (PH043.04) | Yes | Yes | logf [10.0, 0.3, 72] |
| Bus | EQ band on | `/bus/NN/eq/1…6/on` | Doc-verified | No | No | No | Per-band enable exists; monitor card uses master `/eq/on` only in PH043.04 |

---

## Area 4 — DCA Configuration (DCA 1–8)

| Domain | Item | OSC Path | Available | Already Captured | Stored | Displayed | Notes |
|---|---|---|---|---|---|---|---|
| DCA | DCA number | — | Yes | Yes | Yes | Yes | |
| DCA | Name | `/dca/N/config/name` | Doc-verified | No | Partial | Partial | Live reader uses placeholder `DCA N`; not queried |
| DCA | Colour | `/dca/N/config/color` | Doc-verified | No | No | No | |
| DCA | Member channels | Inverse of `/ch/NN/grp/dca` | Doc-verified | No | No | No | Requires bitmap decode |
| DCA | Member buses | `/bus/NN/grp/dca`, `/mtx/NN/grp/dca` | Doc-verified | No | No | No | |
| DCA | Fader / mute | `/dca/N/fader`, `/dca/N/on` | In codebase | Yes | Yes | Partial | Review table only |

---

## Area 5 — Matrix Configuration (MTRX 01–06)

| Domain | Item | OSC Path | Available | Already Captured | Stored | Displayed | Notes |
|---|---|---|---|---|---|---|---|
| Matrix | Matrix number | — | Yes | Yes | Yes | No | Learned; no matrix UI layer enabled |
| Matrix | Name | `/mtx/NN/config/name` | Doc-verified | No | Partial | No | Placeholder `MTRX N` in live reader |
| Matrix | Sources | Matrix mix sends + `/outputs/*/src` | Doc-verified | No | No | No | Output patch is PH042 routing |
| Matrix | Outputs | `/outputs/matrix/NN/src` etc. | Doc-verified | No* | Partial* | No | *Routing domain |
| Matrix | Purpose | — | Derived | No | No | No | |
| Matrix | Fader / mute | `/mtx/NN/mix/fader`, `/mix/on` | In codebase | Yes | Yes | No | Overview layer buttons disabled |

---

## Area 6 — FX Configuration

| Domain | Item | OSC Path | Available | Already Captured | Stored | Displayed | Notes |
|---|---|---|---|---|---|---|---|
| FX | Slot type (FX 1–4) | `/fx/N/type` | Doc-verified | No | No | No | Live learn sets `fx: []`; warning in learn result |
| FX | Slot type (FX 5–8) | `/fx/N/type` | Doc-verified | No | No | No | |
| FX | Effect name | Library / type enum label | Partial | No | Partial | No | Fixture only: generic `FX N` |
| FX | Routing role | `/fx/N/source/l`, `/source/r` | Doc-verified | No | No | No | |
| FX | Source buses | FX source enums | Doc-verified | No | No | No | |
| FX | Return channels | `/fxrtn/NN/config/name`, `/mix/*` | Doc-verified | No | No | No | Return fader path in map; returns not read |
| FX | Return name/icon/colour | `/fxrtn/NN/config/*` | Doc-verified | No | No | No | |
| FX | Return DCA group | `/fxrtn/NN/grp/dca` | Doc-verified | No | No | No | |

---

## Area 7 — Global Configuration

| Domain | Item | OSC Path | Available | Already Captured | Stored | Displayed | Notes |
|---|---|---|---|---|---|---|---|
| Global | Sample rate | `/-prefs/clockrate` | Doc-verified | No | No | Partial | Hardcoded UI pill |
| Global | Clock source | `/-prefs/clocksource` | Doc-verified | No | No | No | |
| Global | Oscillator / tone | `/-prefs/...` | Unknown | No | No | No | Needs scoped audit |
| Global | Talkback enable | `/config/talk/enable` | Doc-verified | No | No | No | |
| Global | Talkback source/levels | `/config/talk/A/*`, `/B/*` | Doc-verified | No | No | No | |
| Global | User assign | `/config/userctrl/{A,B,C}/*` | Doc-verified | No | No | No | Encoders + buttons |
| Global | Link preferences | `/config/linkcfg/*` | Doc-verified | No | No | No | HA/EQ/dyn/fader link globals |
| Global | Mono bus config | `/config/mono` | Doc-verified | No | No | No | |
| Global | Show control mode | `/-prefs/show_control` | Doc-verified | No | No | No | Scene vs cue vs snippet |

---

## Gap Analysis

### Already Implemented

| Capability | Location | PH043 relevance |
|---|---|---|
| Scene recall + operator scene number | `OscUdpX32ConsoleSnapshotReader`, `X32OscSceneRecallPacketBuilder` | Console identity |
| Scene name (live learn + routing enrich) | `readSceneName`, `X32SceneMetadataService` | Console identity |
| Channel learn (name, colour, fader, mute, core controls) | `readChannels` | Area 2 partial |
| Monitor send matrix learn (32×16) | `X32MonitorSendMatrixLearnCapture`, `attachChannelBusSends` | Area 2 — PH043.05 Channels card |
| Bus learn (name, colour, fader, mute) | `readBuses` | Area 3 partial |
| Bus master EQ learn (6 bands) | `X32BusEqLearnCapture`, `readBuses` | Area 3 — PH043.04 monitor EQ card |
| DCA/matrix fader/mute learn | `readDcas`, `readMatrices` | Areas 4–5 partial |
| Baseline persistence | `ShowConsoleBaselineService` | All areas |
| 32-channel virtual overview UI | `VirtualConsoleStripBuilder`, `workspace.blade.php` | Area 2 display (channels only) |
| Scene display in headers | `_console-header`, routing `buildLearnedMeta` | Area 1 |
| Real-time fader/mute write (control) | `ShowConsoleControlService` | Out of PH043 read scope; existing |

### Partially Implemented

| Capability | Gap |
|---|---|
| Console identity | Device name from app DB; firmware/sample rate/clock not read |
| Channel configuration | Icon, source, headamp gain/phantom, DCA, live stereo link; **bus sends learned (PH043.05)** |
| Bus configuration | Name/fader/mute + **master EQ (PH043.04)**; no link/type/purpose workspace |
| DCA configuration | Placeholder names; no colours or membership |
| Matrix configuration | Placeholder names; no dedicated UI layer |
| FX configuration | Explicitly empty on live learn; fixture placeholders only |
| Baseline structure | Flat summary; no `configuration` / `routing` / `connectivity` split |
| Layer navigation | BUS/DCA/MATRIX/FX tabs **disabled** in workspace UI |

### Available But Not Learned

| Priority | Items | OSC basis |
|---|---|---|
| High | DCA/matrix/FX return **names** | `/dca/N/config/name`, `/mtx/NN/config/name`, `/fxrtn/NN/config/name` |
| High | Channel **icon**, bus **icon** | `/ch/NN/config/icon`, `/bus/NN/config/icon` |
| High | **Headamp gain/phantom** with `/-ha/NN/index` mapping | `/headamp/NNN/gain`, `/headamp/NNN/phantom` |
| High | **DCA membership** bitmaps | `/ch/NN/grp/dca`, `/bus/NN/grp/dca` |
| Medium | **Channel source** | `/ch/NN/config/source` |
| Medium | **Stereo link** state | `/config/chlink/*`, `/config/buslink/*` |
| Medium | **FX slot types + sources** | `/fx/N/type`, `/fx/N/source/l|r` |
| Medium | **Console identity** block | `/info`, `/xinfo`, `/-prefs/clockrate`, `/-prefs/clocksource` |
| Lower | **Bus send matrix summary** | `/ch/NN/mix/01…16` (512+ paths — needs aggregation) |
| Lower | **Talkback + user assign** | `/config/talk/*`, `/config/userctrl/*` |

### Unknown / Needs Investigation

| Item | Reason |
|---|---|
| Bus “type” / sends-on-fader exact OSC paths | Not fully enumerated in codebase audit; requires targeted OSC doc section + live probe |
| Matrix “sources” as configuration vs routing | Overlap with `/outputs/*/src` — must assign to PH042 vs PH043 explicitly |
| Oscillator settings scope | Not listed in PH043 contract detail; confirm operator need |
| FX effect human-readable name | May require type-enum → label decoder, not a single name path |
| Optimal headamp index batch read | `/-ha/00…39/index` count and timing for 32 channels |

---

## Recommended PH043 Sequence

### PH043.02 — Configuration Learn Foundation

Read-only capture into structured `configuration` object. Suggested learn order:

1. **Console identity block** — `/info`, `/xinfo`, `/-prefs/clockrate`, `/-prefs/clocksource`, scene metadata (already partially done)
2. **Channel configuration completion** — icon, source, headamp gain/phantom (via `/-ha/`), chlink pairs, DCA membership
3. **Bus configuration completion** — icon, buslink, colour (already read), optional dynamics-on flags
4. **DCA configuration** — names, colours, derived membership lists
5. **Matrix configuration** — names; defer output/source patch to routing domain
6. **FX configuration** — slot types, sources, FX return names/levels
7. **Global configuration summary** — talkback enable/source, userctrl high-level state (not full encoder strings initially)

**Storage:** Introduce top-level keys per contract:

```json
{
  "configuration": { "identity": {}, "channels": [], "buses": [], "dcas": [], "matrices": [], "fx": {}, "global": {} },
  "routing": {},
  "connectivity": {}
}
```

Migrate existing root `channels`/`buses`/etc. into `configuration` without breaking current consumers (adapter layer or dual-write during transition).

### PH043.03 — Configuration Workspace UI

Dedicated read-only configuration page (or enabled layer tabs) answering PH043 operator questions in ~10 seconds:

1. Console + scene identity header
2. Channel configuration summary grid
3. Bus / DCA / Matrix / FX sections
4. Anomaly highlights (unnamed strips, empty FX, mismatched scene)

Do not duplicate routing flow map — link to routing workspace instead.

### PH043.04 — Configuration Validation Cycle

Validate against real X32 operation; defect correction only (same pattern as PH042.05).

---

## Open Questions

1. **Single learn vs split learn:** Should configuration and routing remain one “Learn From Console” action with separate summary sections, or split into two operator actions?
2. **Headamp mapping:** Confirm batch strategy for `/-ha/NN/index` across 32 channels without exceeding xremote query budgets.
3. **Bus send summary:** Full 512-path read vs derived “active sends only” heuristic for PH043 MVP?
4. **Matrix/FX overlap:** Which `/outputs/*` paths belong in PH043 “configuration purpose” vs PH042 “routing assignment”?
5. **Fixture transport:** PH043 configuration tests should use live OSC mocks only for names/paths — no invented fixture scene or config names (per PH042.05 precedent).
6. **Backward compatibility:** How long to dual-write flat `channels[]` at summary root for existing console overview vs new configuration workspace?
7. **M32 vs X32:** Confirm `/info` model strings and any M32-specific path differences before identity block implementation.

---

## Configuration Domains Audited

| Area | Contract section | Items audited | Substantially captured | Missing / partial |
|---|---|---|---|---|
| 1 | Console Identity | 8 | 2 (scene number/name) | 6 |
| 2 | Channel Configuration | 12 | 7 | 5 |
| 3 | Bus Configuration | 7 | 3 | 4 |
| 4 | DCA Configuration | 5 | 1 | 4 |
| 5 | Matrix Configuration | 5 | 1 | 4 |
| 6 | FX Configuration | 6 | 0 (live) | 6 |
| 7 | Global Configuration | 6 | 0 | 6 |

---

## Key Findings

1. **Configuration learn already exists implicitly** — the console learn pipeline captures mixer strips but not as a named PH043 domain.
2. **The overview workspace is a partial PH043 UI** — 32 channel strips only; other layers disabled.
3. **Routing workspace is not a configuration page** — it answers “where audio goes” (PH042), though it displays bus names for IEM context.
4. **Scene identity is the strongest PH043-adjacent feature** after PH042.05 (operator scene number + live scene name in routing header).
5. **FX learn is explicitly deferred** — live reader returns `fx: []` with a warning.
6. **Headamp-dependent controls are the largest channel-config blocker** — gain/phantom defined but not wired to real OSC indices.
7. **DCA/matrix names are placeholders in live learn** — operator cannot distinguish DCAs/matrices by desk label today.
8. **No `/info` or `/-prefs/*` reads** — firmware, sample rate, and clock are unknown to the application.
9. **Baseline JSON does not match PH043 contract shape** — separation of configuration/routing/connectivity is a PH043.02 prerequisite.
10. **Connectivity is implemented separately** (`X32SourceConnectivityCapture`) — correctly outside configuration; must not be merged into PH043 learn.

---

## PH043.06 — Monitor Send Control Readiness Audit

**Status:** Audit complete (read-only)  
**Date:** 2026-06-17  
**Route audited:** `/shows/{show}/console/bus/{bus}/layout`  
**Authority:** `docs/x32/PH043_X32_CONFIGURATION_CONTRACT.md` Area 2, `docs/x32/DECISION_LOG.md` X32-DEC-003, X32-DEC-004  
**Scope:** Readiness only — no OSC writes, no UI behaviour changes, no save/apply controls

### Executive Summary

The PH043.05 monitor send matrix learn pipeline provides **sufficient verified read infrastructure** to support PH043.07 live writes for **send level** and **send on/mute** on any bus index 01–16. Scaling, OSC path helpers, decoders, learned field envelopes, and monitor workspace UI fields already exist for these two parameters.

**Send pan, send type/tap, pan follow, and grouped fader writes are not ready** for PH043.07 initial implementation. Pan/type paths are odd-bus-only; stereo bus link state is learned but not applied to send write semantics; `tapToType` encoder and pan-follow UI are absent; grouped fader control is DOM-only with no persistence or OSC dispatch.

**Recommended PH043.07 scope:** Per-channel send level and send on writes for the selected monitor bus only (see X32-DEC-004).

### Sources Inspected (PH043.06)

| Layer | Files |
|---|---|
| Learn capture | `X32MonitorSendMatrixLearnCapture`, `OscUdpX32ConsoleSnapshotReader::attachChannelBusSends` |
| Decode / scale | `X32ChannelBusSendOscDecoder`, `X32FaderScale`, `X32OscParameterScale` |
| Assembly | `X32ConfigurationLearnAssembler::buildChannelSends` |
| Workspace UI | `X32MonitorsWorkspaceBuilder`, `_monitors-fader-track.blade.php`, `x32-monitors-group-control.js` |
| OSC paths | `X32OscAddressMap::channelBusSend*` |
| Write precedent (channel strip — not sends) | `ShowConsoleControlService`, `X32InputChannelControlMap` |
| Bus stereo link learn | `OscUdpX32ConsoleSnapshotReader::readBusLinkMap`, `X32ConfigurationLearnAssembler::buildBuses` |
| Tests | `X32MonitorSendMatrixLearnCaptureTest`, `X32ChannelBusSendOscDecoderTest`, `X32ConfigurationLearnAssemblerTest`, `X32FaderScaleTest`, `X32MonitorSendControlReadinessTest` |

### Field Readiness Matrix

Paths below are **read-verified in PH043.05** unless noted. Write path column follows Patrick Gilles Maillot X32/M32 OSC Remote Protocol convention: **same path, typed value** (corroborated by existing channel fader/mute writes in `X32InputChannelControlMap`). Live desk write round-trip for send paths is **not yet proven in this project** — marked *assumed same path, unproven live*.

#### 1. Channel-to-bus send level

| Question | Finding |
|---|---|
| OSC read path verified? | **Yes** — all 32×16 via `X32MonitorSendMatrixLearnCapture::captureSend` |
| OSC write path | *Assumed same:* `/ch/{01…32}/mix/{01…16}/level` — **unproven live** |
| Scaling confirmed? | **Yes** — `level [0.0…1.0(+10dB), 161]` → `X32FaderScale` (same as channel/bus faders) |
| Min/max confirmed? | **Yes** — linear `0.0` (−90 dB display floor) … `1.0` (+10 dB); `quantizeLinear` for write grid |
| Enum mapping | N/A (float) |
| Decoder available? | **Yes** — direct float read + `linearToDb` |
| Encoder required? | **Yes** — `dbToLinear` + `quantizeLinear` (exists; no send-specific wrapper yet) |
| UI field available? | **Yes** — Channels card fader track + `level_display` from learned send (`X32MonitorsWorkspaceBuilder`) |
| Safe for PH043.07? | **READY FOR PH043.07** |
| Remaining unknowns | Live write round-trip on desk; baseline/snapshot persistence strategy for send level after write |

#### 2. Channel-to-bus send on / monitor mute

| Question | Finding |
|---|---|
| OSC read path verified? | **Yes** — all 32×16 |
| OSC write path | *Assumed same:* `/ch/{01…32}/mix/{01…16}/on` — **unproven live** |
| Scaling confirmed? | **Yes** — int enum `0` = off, `1` = on |
| Min/max confirmed? | **Yes** — `0` or `1` only |
| Enum mapping confirmed? | **Yes** — `on === 1` → send active; monitor UI mute = `!on` (not inverted like channel strip `/mix/on`) |
| Decoder available? | **Yes** — direct int |
| Encoder required? | **Yes** — int `0`/`1`; **must not** use channel mute `invert_osc` |
| UI field available? | **Yes** — mute button state derived from learned send on |
| Safe for PH043.07? | **READY FOR PH043.07** |
| Remaining unknowns | Live write round-trip; confirm monitor mute toggle maps to send `on` not channel `mix/on` |

#### 3. Channel-to-bus send pan (odd buses only)

| Question | Finding |
|---|---|
| OSC read path verified? | **Yes** — odd buses 01, 03, …, 15 only (`busSupportsSendPan`) |
| OSC write path | *Assumed same:* `/ch/{01…32}/mix/{01,03…15}/pan` — **unproven live** |
| Scaling confirmed? | **Yes** — linf `[-100, 100, 2]` via `X32OscParameterScale::decodeLinf` / `encodeLinf` |
| Min/max confirmed? | **Yes** — −100 … +100, 2-step quantize |
| Enum mapping | N/A |
| Decoder available? | **Yes** — `decodePan` / `encodePan` |
| Encoder required? | **Yes** — `encodePan` exists |
| UI field available? | **Partial** — detail panel row when learned; no interactive pan control |
| Safe for PH043.07? | **NOT READY — NEEDS MORE DISCOVERY** |
| Remaining unknowns | Stereo-linked bus pan write side-effects on even partner; live write proof; odd-bus-only guard in write service |

#### 4. Channel-to-bus send type / tap (odd buses only)

| Question | Finding |
|---|---|
| OSC read path verified? | **Yes** — odd buses only |
| OSC write path | *Assumed same:* `/ch/{01…32}/mix/{01,03…15}/type` — **unproven live** |
| Scaling confirmed? | **Yes** — int `0`–`5` |
| Min/max confirmed? | **Yes** |
| Enum mapping confirmed? | **Yes (read)** — `typeToTap`: in_lc, pre_eq, post_eq, pre_fader, post_fader, grp |
| Decoder available? | **Yes** — `typeToTap` |
| Encoder required? | **Yes** — `tapToType` **missing** |
| UI field available? | **Partial** — read-only detail row for tap |
| Safe for PH043.07? | **DEFER — OUT OF INITIAL LIVE CONTROL SCOPE** |
| Remaining unknowns | `tapToType` encoder; operator need on monitor page; live write proof |

#### 5. Pan follow (odd buses ≥ 03 only)

| Question | Finding |
|---|---|
| OSC read path verified? | **Yes** — buses 03, 05, …, 15 (`busSupportsSendPanFollow`); not bus 01 |
| OSC write path | *Assumed same:* `/ch/{01…32}/mix/{03,05…15}/panFollow` — **unproven live** |
| Scaling confirmed? | **Yes** — int `0`/`1` |
| Enum mapping confirmed? | **Yes (read)** |
| Decoder available? | **Yes** — direct int |
| Encoder required? | Trivial int `0`/`1` |
| UI field available? | **No** — learned/stored; not rendered PH043.05 |
| Safe for PH043.07? | **DEFER — OUT OF INITIAL LIVE CONTROL SCOPE** |
| Remaining unknowns | Interaction with channel pan and stereo bus link; no operator UI |

#### 6. Grouped fader write

| Question | Finding |
|---|---|
| OSC read path verified? | N/A — uses per-channel learned send levels |
| OSC write path | Would require N× `/level` writes — **not implemented** |
| UI field available? | **Yes (UI-only)** — `x32-monitors-group-control.js` adjusts DOM locally |
| Safe for PH043.07? | **DEFER — OUT OF INITIAL LIVE CONTROL SCOPE** |
| Remaining unknowns | Batch write strategy, undo, xremote budget, group assignment persistence (excluded PH043.05) |

### Verified OSC Paths (PH043.05 — no invented paths)

| Parameter | OSC path | Bus indices |
|---|---|---|
| Send on | `/ch/{01…32}/mix/{01…16}/on` | All 16 |
| Send level | `/ch/{01…32}/mix/{01…16}/level` | All 16 |
| Send pan | `/ch/{01…32}/mix/{01,03,05,07,09,11,13,15}/pan` | Odd only |
| Send type | `/ch/{01…32}/mix/{01,03,05,07,09,11,13,15}/type` | Odd only |
| Pan follow | `/ch/{01…32}/mix/{03,05,07,09,11,13,15}/panFollow` | Odd ≥ 03 |

Helpers: `X32OscAddressMap::channelBusSendOn|Level|Pan|Type|PanFollow`.

### Scale / Range Summary

| Parameter | Documented scale | Project implementation | Round-trip tested |
|---|---|---|---|
| Level | `[0.0…1.0(+10dB), 161]` | `X32FaderScale` | **Yes** (`X32FaderScaleTest`, `X32MonitorSendControlReadinessTest`) |
| On | int 0/1 | direct int | **Yes** (readiness test) |
| Pan | linf `[-100, 100, 2]` | `X32OscParameterScale` + `X32ChannelBusSendOscDecoder` | **Yes** (encode/decode pan) |
| Type | int 0–5 | `TYPE_LABELS` + `typeToTap` | **Yes** (all six labels) |
| Pan follow | int 0/1 | direct int | Read only |

### Stereo Bus / Odd-Even Audit

| Question | Answer |
|---|---|
| Odd/even bus pairs represented correctly? | **Yes (read)** — even buses store level+on only; pan/tap envelopes use `reason: osc_path_not_on_even_bus_send` |
| Pan only on odd buses? | **Yes** — `busSupportsSendPan` = odd 1–16 |
| `/type` only on odd buses? | **Yes** — same guard as pan |
| What happens for even buses? | Level and on learned; pan/tap explicitly `not_learned` with even-bus reason — not silently omitted |
| Bus linking learned? | **Yes** — `/config/buslink/{1-2}` … `{15-16}` → `capture.bus_links` → `configuration.buses[n].stereo_link` |
| Bus linking required before pan writes? | **Likely yes for safe pan writes** — link state not consulted by monitor workspace; writing odd-bus pan on a stereo-linked pair may affect both channels — **unproven** |
| Level/on writes safe for mono and stereo contexts? | **Yes (initial assessment)** — each bus index has independent `/level` and `/on` paths regardless of link; even buses are first-class write targets |
| PH043.07 restrict to level/on only? | **Yes** — per X32-DEC-004 |

### Write-Readiness Classification

| Future write action | Classification | Rationale |
|---|---|---|
| Send level write | **READY FOR PH043.07** | Full read path, scale, UI, path helpers; channel fader write precedent |
| Send on/mute write | **READY FOR PH043.07** | Full read path, enum, UI; distinct from channel mute invert semantics |
| Send pan write | **NOT READY — NEEDS MORE DISCOVERY** | Odd-bus-only + stereo link side-effects unproven live |
| Send type/tap write | **DEFER — OUT OF INITIAL LIVE CONTROL SCOPE** | Missing `tapToType`; read-only UI; low monitor-page priority |
| Pan follow write | **DEFER — OUT OF INITIAL LIVE CONTROL SCOPE** | No UI; buses ≥ 03 only; interaction unknown |
| Grouped fader write | **DEFER — OUT OF INITIAL LIVE CONTROL SCOPE** | UI-only; needs batch level writes + persistence |

### Recommended PH043.07 Implementation Scope

1. **Add** `MonitorSendControlMap` (or extend address map) for send level/on only — paths from `X32OscAddressMap`, scale from `X32FaderScale`, on enum without invert.
2. **Wire** bus workspace Channels card fader + mute to OSC write for **selected bus** only — no changes to routing page or overview strip controls.
3. **Validate** live round-trip on real X32 for level and on before enabling batch/group features.
4. **Persist** written values to active baseline/snapshot send envelopes (mirror `ShowConsoleControlService` pattern).
5. **Exclude** pan, type, panFollow, group fader writes. Bus master fader/mute and bus EQ writes are implemented separately in PH043.08–PH043.09.

### PH043.09 — Monitor Bus Master Live Control

| Check | Result |
|---|---|
| Bus master fader OSC path | **Confirmed** — `/bus/{01…16}/mix/fader` via `X32OscAddressMap::busFader` |
| Bus master on/mute OSC path | **Confirmed** — `/bus/{01…16}/mix/on` via `X32OscAddressMap::busOn` |
| Fader scale | `X32FaderScale::quantizeLinear` — same as channel/bus faders |
| On/mute semantics | `on === 1` → bus active (unmuted); UI mute = `on === 0` — **not** inverted like channel strip `/ch/NN/mix/on` |
| Read-back pattern | `setFloat`/`setInt` → `queryFloat`/`queryOn` (8 retries for on) — UI updates from confirmed value only |
| Route scope | Selected monitor bus from `/shows/{show}/console/bus/{bus}/layout` only — body cannot override bus |
| Runtime gate | `runtime_mode: live` required |
| Write endpoint | `POST .../console/bus/{bus}/master` — parameters `level`, `mute` only |
| Excluded | Main LR, matrix, channel master, send fader, EQ, group fader, pan, tap, persistence |

### PH043.06 Confirmation

| Check | Result |
|---|---|
| OSC write commands added | **No** |
| Write services / controller actions added | **No** |
| Save/apply UI added | **No** |
| Monitor page behaviour changed | **No** |
| Readiness verification tests added | **Yes** — `X32MonitorSendControlReadinessTest` |
| Architectural decision recorded | **Yes** — X32-DEC-004 |

---

## Confirmation (PH043.01)

| Check | Result |
|---|---|
| Application code modified | **No** (PH043.01 only) |
| UI modified | **No** (PH043.01 only) |
| Tests modified | **No** (PH043.01 only) |
| Commits made | **No** (PH043.01 only) |
| Git diff scope | Untracked docs only: `PH043_X32_CONFIGURATION_CONTRACT.md` (prior), `PH043_CONFIGURATION_DISCOVERY_AUDIT.md` (this file) |

---

## Rollback Notes

Documentation only (PH043.01). PH043.06 adds audit section + X32-DEC-004 + readiness tests — revert `docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md` § PH043.06, `docs/x32/DECISION_LOG.md` X32-DEC-004, and `backend/tests/Unit/X32MonitorSendControlReadinessTest.php`.
