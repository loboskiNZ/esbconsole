# PH043.01 — X32 Configuration Domain Discovery Audit

**Status:** Audit complete (read-only)  
**Date:** 2026-06-17  
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
| Bus | Fader / mute | `/bus/NN/mix/fader`, `/mix/on` | In codebase | Yes | Yes | Partial | Review table; not dedicated bus workspace |
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

## Confirmation

| Check | Result |
|---|---|
| Application code modified | **No** |
| UI modified | **No** |
| Tests modified | **No** |
| Commits made | **No** |
| Git diff scope | Untracked docs only: `PH043_X32_CONFIGURATION_CONTRACT.md` (prior), `PH043_CONFIGURATION_DISCOVERY_AUDIT.md` (this file) |

---

## Rollback Notes

Documentation only. Delete `docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md` to revert this audit deliverable.
