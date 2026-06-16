# PH042.02 — X32 Routing OSC Address Audit

**Status:** Audit complete (documentation only)  
**Date:** 2026-06-16  
**Authority:** `docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md`  
**Prior audit:** `docs/x32/PH042_ROUTING_DISCOVERY_AUDIT.md`  
**Scope:** OSC address verification and architecture — no routing reads, writes, UI, or code changes

---

## 1. Executive Summary

PH042.01A confirmed that **zero routing OSC paths exist in the application codebase**. This audit identifies the **authoritative X32/M32 OSC address model** required for PH042.03 read-only routing learn.

Primary evidence source: **Unofficial X32/M32 OSC Remote Protocol** (Patrick-Gilles Maillot, Behringer-sourced documentation). This document lists explicit `/config/routing/*`, `/config/userrout/*`, and `/outputs/*` address patterns with integer enum mappings. Paths are **documented externally** but **not verified in this repository or on live hardware** as part of this audit.

### Key corrections vs contract illustration

| Contract example | Actual documented pattern |
|---|---|
| `/config/routing/IN/4` (illustrative) | `/config/routing/IN/25-32` (range-based, not index 4) |
| Single “routing path” per domain | Layered model: input banks → Out 1–16 → output patch (`/outputs/main`) → physical destinations |

### Codebase state (unchanged)

| Area | Routing OSC coverage |
|---|---|
| `X32OscAddressMap.php` | Mixer only (`/ch`, `/bus`, `/dca`, `/mtx`, scene recall) |
| `X32InputChannelControlMap.php` | Channel controls incl. `/ch/NN/mix/st` — **not routing tables** |
| `OscUdpX32ConsoleSnapshotReader.php` | Reads mixer paths; routing stub only |
| `FakeX32ConsoleSnapshotReader.php` | Fixture `routing.main_lr` placeholder; no OSC routing queries |

### Verification legend (used throughout)

| Status | Meaning in this audit |
|---|---|
| **Verified** | OSC path explicitly documented in official/unofficial X32 OSC protocol reference with parameter type and enum range |
| **Partially Verified** | Path documented but FW-gated, enum label ambiguous, or requires index-to-label decoder not fully specified in doc excerpt |
| **Assumed** | Inferred from related documented paths or UI/manual behaviour; not independently listed |
| **Unknown** | No authoritative path found — **do not implement without further verification** |

**Important:** “Verified” here means **documentation-verified**, not **live-desk-verified**. PH042.03 must include live console probe tests before production reliance.

---

## 2. Routing Domain Matrix

| Routing Domain | Path Known (Docs)? | Path in Codebase? | Live-Desk Verified? | PH042.03 Ready? | Notes |
|---|---|---|---|---|---|
| Input Banks | Yes | No | No | After enum decoder | `/config/routing/IN/1-8` … `25-32` |
| Aux In / Remap | Yes | No | No | After enum decoder | `/config/routing/IN/AUX`, `PLAY/AUX` |
| Local Inputs | Partial | No | No | Via IN bank enums | `AN1-8` … `AN25-32` labels in IN bank values |
| AES50A Inputs | Partial | No | No | Via IN bank enums | `A1-8` … `A41-48` in IN bank values |
| AES50B Inputs | Partial | No | No | Via IN bank enums | `B1-8` … `B41-48` in IN bank values |
| Card / USB Inputs | Yes | No | No | After enum decoder | IN bank `CARD1-8` …; separate CARD out paths |
| Out 1–16 | Yes | No | No | After enum decoder | `/config/routing/OUT/1-4` … `13-16` |
| Aux Out | Yes | No | No | After src decoder | `/outputs/aux/[01…06]/src` |
| User In | Yes | No | No | FW 4.0+ check | `/config/userrout/in/01…32` |
| User Out | Yes | No | No | FW 4.0+ check | `/config/userrout/out/01…48` |
| AES50A Outputs | Yes | No | No | After enum decoder | `/config/routing/AES50A/1-8` … `41-48` |
| AES50B Outputs | Yes | No | No | After enum decoder | `/config/routing/AES50B/1-8` … `41-48` |
| Card / USB Outputs | Yes | No | No | After enum decoder | `/config/routing/CARD/1-8` … `25-32` |
| P16 / Ultranet | Yes | No | No | After src decoder | `/outputs/p16/[01…16]/src` (+ P16 refs in routing enums) |
| XLR Output Assignment | Yes | No | No | After src decoder | `/outputs/main/[01…16]/src` |
| Main LR Routing | Partial | Partial | No | Trace outputs + OUT banks | `/outputs/main/NN/src`; not `/ch/NN/mix/st` |
| Bus Routing | Partial | Partial | No | Derive from outputs | Bus names `/bus/NN/config/name`; patch via `/outputs/*/src` |
| IEM Routing | Partial | No | No | Derived domain | No single OSC path; bus + output patch |
| Matrix Routing | Partial | Partial | No | Derive from outputs | Matrix patch via `/outputs/*/src`; processing via `/mtx/NN/*` |

---

## 3. OSC Address Catalogue

### 3.1 Input Banks

**Routing Domain:** Input Banks  
**Purpose:** Determine which 8-channel source block feeds console input channels 1–32 (Record path).  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/config/routing/routswitch` | enum int | `0` = REC (uses IN block), `1` = PLAY (uses PLAY block) |
| `/config/routing/IN/1-8` | enum int | `[0…23]` |
| `/config/routing/IN/9-16` | enum int | `[0…23]` |
| `/config/routing/IN/17-24` | enum int | `[0…23]` |
| `/config/routing/IN/25-32` | enum int | `[0…23]` |

**Enum labels (IN banks):** `{AN1-8, AN9-16, AN17-24, AN25-32, A1-8, A9-16, A17-24, A25-32, A33-40, A41-48, B1-8, B9-16, B17-24, B25-32, B33-40, B41-48, CARD1-8, CARD9-16, CARD17-24, CARD25-32, UIN1-8, UIN9-16, UIN17-24, UIN25-32}`

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol — Routing chapter; scene file node list  
**Notes:** Contract example `/config/routing/IN/4` is **incorrect**. Banks use **channel-range path segments**, not bank index. Must read `routswitch` first to know whether IN or PLAY block is active.

---

### 3.2 Aux In / Remap

**Routing Domain:** Aux In / Aux Remap  
**Purpose:** Aux input return block routing into the console (Record and Playback paths).  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/config/routing/IN/AUX` | enum int | `[0…15]` |
| `/config/routing/PLAY/AUX` | enum int | `[0…15]` |
| `/config/routing/PLAY/1-8` | enum int | `[0…23]` (same family as IN banks) |
| `/config/routing/PLAY/9-16` | enum int | `[0…23]` |
| `/config/routing/PLAY/17-24` | enum int | `[0…23]` |
| `/config/routing/PLAY/25-32` | enum int | `[0…23]` |

**IN/AUX enum labels:** `{AUX1-4, AN1-2, AN1-4, AN1-6, A1-2, A1-4, A1-6, B1-2, B1-4, B1-6, CARD1-2, CARD1-4, CARD1-6, UIN1-2, UIN1-4, UIN1-6}` — doc notes AUX1-6 internally but OSC label stays AUX1-4 for backward compatibility.

**Verification Status:** Partially Verified  
**Evidence Source:** X32 OSC Remote Protocol § routing; FW compatibility note on AUX label  
**Notes:** Separate from `/config/auxlink/1-2` … `7-8` (aux **return channel pair linking**, not input bank routing). Do not conflate.

---

### 3.3 Local Inputs

**Routing Domain:** Local Inputs  
**Purpose:** Local analog XLR input sockets as routing sources.  
**OSC Path(s):**

| Path | Role |
|---|---|
| `/config/routing/IN/*` enum values `AN1-8` … `AN25-32` | Local input blocks selected as input bank sources |
| `/config/routing/OUT/*` enum values `AN1-4` … `AN29-32` | Local blocks as Out 1–16 sources |
| `/headamp/[000…127]/gain` | Preamp gain (supports source identification) |
| `/headamp/[000…127]/phantom` | Phantom power state |
| `/-ha/[00…39]/index` | Read-only: actual headamp index used as channel source |
| `/ch/[01…32]/config/source` | Per-channel source override `{OFF, In01…32, Aux 1…6, USB L/R, Fx, Bus 01…16}` — **not the same as input bank routing** |

**Verification Status:** Partially Verified  
**Evidence Source:** X32 OSC Remote Protocol — routing enums + headamp chapter  
**Notes:** “Local Inputs” for operator model = **`AN*` labels in IN bank enums**, not a separate `/config/routing/LOCAL` path. Per-channel `/ch/NN/config/source` is a secondary layer (e.g. direct bus tap) and must be stored separately from bank routing.

---

### 3.4 AES50A Inputs

**Routing Domain:** AES50A Inputs  
**Purpose:** Digital audio received on AES50-A port, mapped into console channels via input banks.  
**OSC Path(s):**

| Path | Role |
|---|---|
| `/config/routing/IN/*` enum values `A1-8` … `A41-48` | AES50-A input blocks as input bank sources |
| `/config/userrout/in/01…32` | Index `33…80` = AES50-A 1…48 (User In patch table) |

**Verification Status:** Partially Verified  
**Evidence Source:** X32 OSC Remote Protocol — IN bank enum + userrout/in index table  
**Notes:** No standalone `/config/routing/AES50A/IN` path. AES50-A **input** side is selected through IN bank enums. AES50-A **output** side uses separate paths (§3.11).

---

### 3.5 AES50B Inputs

**Routing Domain:** AES50B Inputs  
**Purpose:** Digital audio received on AES50-B port.  
**OSC Path(s):**

| Path | Role |
|---|---|
| `/config/routing/IN/*` enum values `B1-8` … `B41-48` | AES50-B input blocks as input bank sources |
| `/config/userrout/in/01…32` | Index `81…128` = AES50-B 1…48 |

**Verification Status:** Partially Verified  
**Evidence Source:** X32 OSC Remote Protocol  
**Notes:** Same layered model as AES50A.

---

### 3.6 Card / USB Inputs

**Routing Domain:** Card / USB Inputs  
**Purpose:** Audio from recording/playback card returning into console channels (e.g. Ableton returns).  
**OSC Path(s):**

| Path | Role |
|---|---|
| `/config/routing/IN/*` enum values `CARD1-8` … `CARD25-32` | Card input blocks as input bank sources |
| `/config/routing/PLAY/*` | Playback-path card input selection |
| `/config/userrout/in/01…32` | Index `129…160` = Card In 1…32 |

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol  
**Notes:** **Separate from Card outputs** (`/config/routing/CARD/*` §3.13). Application must not conflate Card In (into desk) with Card Out (from desk to DAW).

---

### 3.7 Out 1–16

**Routing Domain:** Out 1–16  
**Purpose:** Intermediate output bank — assigns sources to Out 1–16 before physical output patching.  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/config/routing/OUT/1-4` | enum int | `[0…35]` |
| `/config/routing/OUT/5-8` | enum int | `[0…35]` |
| `/config/routing/OUT/9-12` | enum int | `[0…35]` |
| `/config/routing/OUT/13-16` | enum int | `[0…35]` |

**Enum labels include:** Local (`AN*`), AES50-A/B (`A*`, `B*`), Card (`CARD*`), Out self-reference (`OUT*`), P16 (`P16*`), Aux (`AUX/CR`, `AUX/TB`), User Out/In (`UOUT*`, `UIN*`), and more.

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol — OUT routing section  
**Notes:** Critical intermediate layer for tracing FOH/IEM paths. Enum set differs between `1-4/9-12` vs `5-8/13-16` groupings (4-wide vs 4-wide offset ranges).

---

### 3.8 Aux Out

**Routing Domain:** Aux Out  
**Purpose:** Auxiliary output source assignment (monitors, recording, sidefills).  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/outputs/aux/[01…06]/src` | int | `[0…76]` |
| `/outputs/aux/[01…06]/pos` | enum | Tap point (PRE/POST etc.) |
| `/outputs/aux/[01…06]/invert` | enum | OFF/ON |

**Src enum:** `{OFF, Main L, Main R, M/C, MixBus 01…16, Matrix 1…6, DirectOut Ch 01…32, DirectOut Aux 1…8, DirectOut FX 1L…4R, Monitor L, Monitor R, Talkback}`

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol — `/outputs/aux` chapter  
**Notes:** Also referenced as routing enum labels `AUX1-6/Mon`, `AuxIN1-6/TB` in AES50/OUT banks. Full aux routing may require **both** routing-bank and output-patch reads.

---

### 3.9 User In

**Routing Domain:** User In  
**Purpose:** Flexible user input patching (FW 4.0+).  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/config/userrout/in/01…32` | int | `[0…168]` |

**Index map:** OFF, Local In 1…32, AES50-A 1…48, AES50-B 1…48, Card In 1…32, Aux In 1…6, TB Internal, TB External.

**Verification Status:** Verified (documentation) — **FW 4.0+**  
**Evidence Source:** X32 OSC Remote Protocol — userrout/in  
**Notes:** Also appears as `UIN1-8` … `UIN25-32` labels in IN bank enums. User In may **override or complement** standard input banks — live validation required to determine precedence.

---

### 3.10 User Out

**Routing Domain:** User Out  
**Purpose:** Flexible user output patching (FW 4.0+).  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/config/userrout/out/01…48` | int | `[0…208]` |

**Index map:** OFF, Local In 1…32, AES50-A/B 1…48, Card In 1…32, Aux In 1…6, TB, Outputs 1…16, P16 1…16, AUX 1…6, Monitor L/R.

**Verification Status:** Verified (documentation) — **FW 4.0+**  
**Evidence Source:** X32 OSC Remote Protocol — userrout/out  
**Notes:** Also referenced as `UOUT1-8` … `UOUT41-48` in AES50/OUT routing enums.

---

### 3.11 AES50A Outputs

**Routing Domain:** AES50A Outputs  
**Purpose:** Assign console signal paths to AES50-A port outputs (6 × 8-channel banks).  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/config/routing/AES50A/1-8` | enum int | `[0…35]` |
| `/config/routing/AES50A/9-16` | enum int | `[0…35]` |
| `/config/routing/AES50A/17-24` | enum int | `[0…35]` |
| `/config/routing/AES50A/25-32` | enum int | `[0…35]` |
| `/config/routing/AES50A/33-40` | enum int | `[0…35]` |
| `/config/routing/AES50A/41-48` | enum int | `[0…35]` |

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol; X32 user manual §7.4.6 AES50 routing screen  
**Notes:** Enum includes Local, AES50-A/B, Card, Out 1–16, P16, Aux, User Out/In blocks. **Do not confuse with AES50-A input bank selection** (§3.4).

---

### 3.12 AES50B Outputs

**Routing Domain:** AES50B Outputs  
**Purpose:** Assign console signal paths to AES50-B port outputs.  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/config/routing/AES50B/1-8` | enum int | `[0…35]` |
| `/config/routing/AES50B/9-16` | enum int | `[0…35]` |
| `/config/routing/AES50B/17-24` | enum int | `[0…35]` |
| `/config/routing/AES50B/25-32` | enum int | `[0…35]` |
| `/config/routing/AES50B/33-40` | enum int | `[0…35]` |
| `/config/routing/AES50B/41-48` | enum int | `[0…35]` |

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol  
**Notes:** Same enum family as AES50A outputs.

---

### 3.13 Card / USB Outputs

**Routing Domain:** Card / USB Outputs  
**Purpose:** Assign console signals to card/USB outputs (recording/DAW capture).  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/config/routing/CARD/1-8` | enum int | `[0…35]` (same enum family as AES50 OUT) |
| `/config/routing/CARD/9-16` | enum int | `[0…35]` |
| `/config/routing/CARD/17-24` | enum int | `[0…35]` |
| `/config/routing/CARD/25-32` | enum int | `[0…35]` |
| `/outputs/rec/[01…02]/src` | int | `[0…76]` (recorder output patch) |
| `/outputs/rec/[01…02]/pos` | enum | Tap point |

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol  
**Notes:** CARD routing paths assign **what the desk sends to the card**. Distinct from CARD as an **input bank source** (§3.6).

---

### 3.14 P16 / Ultranet

**Routing Domain:** P16 / Ultranet  
**Purpose:** Personal monitoring (Ultranet/P16) output source assignment.  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/outputs/p16/[01…16]/src` | int | `[0…76]` |
| `/outputs/p16/[01…16]/pos` | enum | Tap point |
| `/outputs/p16/[01…16]/invert` | enum | OFF/ON |
| `/outputs/p16/[01…16]/iQ/group` | enum | iQ speaker group |
| `/outputs/p16/[01…16]/iQ/speaker` | enum | Speaker type |
| Routing enum refs | — | `P161-8`, `P169-16` in AES50/OUT/CARD enums |

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol — `/outputs/p16` chapter  
**Notes:** No separate `/config/routing/P16/*` path in scene node list. P16 appears as **output patch** (`/outputs/p16`) and as **routing enum destination** in other tables.

---

### 3.15 XLR Output Assignment

**Routing Domain:** XLR Output Assignment  
**Purpose:** Physical/local output socket source assignment (Analog Out screen).  
**OSC Path(s):**

| Path | Type | Value range |
|---|---|---|
| `/outputs/main/[01…16]/src` | int | `[0…76]` |
| `/outputs/main/[01…16]/pos` | enum | Tap point |
| `/outputs/main/[01…16]/invert` | enum | OFF/ON |
| `/outputs/main/[01…16]/delay/on` | enum | Delay |
| `/outputs/main/[01…16]/delay/time` | linf | Delay time |

**Src enum:** `{OFF, Main L, Main R, M/C, MixBus 01…16, Matrix 1…6, DirectOut Ch 01…32, DirectOut Aux 1…8, DirectOut FX 1L…4R, Monitor L, Monitor R, Talkback}`

**Verification Status:** Verified (documentation)  
**Evidence Source:** X32 OSC Remote Protocol — `/outputs/main` chapter  
**Notes:** Maps to application's `routing.xlr_outputs[]` model. Full FOH trace may require combining `/outputs/main/NN/src` with `/config/routing/OUT/*` chain. Exact XLR socket-to-index mapping may vary by console model — **live validation required**.

---

### 3.16 Main LR Routing

**Routing Domain:** Main LR Routing  
**Purpose:** How Main L/R reaches physical outputs (FOH).  
**OSC Path(s):**

| Path | Role | Routing relevance |
|---|---|---|
| `/outputs/main/[01…16]/src` | Output patch | Select `Main L`, `Main R` as source (indices in `[0…76]` enum) |
| `/config/routing/OUT/*` | Out 1–16 banks | Intermediate layer if XLR fed via Out bank |
| `/main/st/mix/fader` | Main bus level | **Processing — not routing** |
| `/main/st/config/name` | Main bus name | Label only |
| `/ch/[01…32]/mix/st` | Channel → Main send | **Send assignment — not output routing** |

**Verification Status:** Partially Verified  
**Evidence Source:** X32 OSC Remote Protocol; codebase `X32InputChannelControlMap` (mix/st only)  
**Notes:** Current codebase reads `/ch/NN/mix/st` and incorrectly treats this as routing evidence. PH042.03 must trace **output patch + OUT banks**, not channel Main sends. Fixture `routing.main_lr: {left: BUS 15, right: BUS 16}` has **no OSC basis** and must be removed when real reads exist.

---

### 3.17 Bus Routing

**Routing Domain:** Bus Routing  
**Purpose:** Mix bus output routing — which bus feeds which output path.  
**OSC Path(s):**

| Path | Role |
|---|---|
| `/outputs/main/[01…16]/src` | Select `MixBus 01…16` |
| `/outputs/aux/[01…06]/src` | Select `MixBus 01…16` |
| `/outputs/p16/[01…16]/src` | Select `MixBus 01…16` |
| `/outputs/aes/[01…02]/src` | AES output patch |
| `/config/routing/AES50A/*`, `AES50B/*`, `OUT/*`, `CARD/*` | May reference Out/bus blocks |
| `/bus/[01…16]/config/name` | Bus label (already learned) |
| `/bus/[01…16]/mix/fader` | Bus level (**processing, not routing**) |

**Verification Status:** Partially Verified  
**Evidence Source:** X32 OSC Remote Protocol  
**Notes:** “Bus routing” in operator terms = **output patch source selection** + **bus name**. No single `/config/routing/BUS` path.

---

### 3.18 IEM Routing

**Routing Domain:** IEM Routing  
**Purpose:** Personal monitor mix output path (operator: Ed IEM, Guitar IEM, etc.).  
**OSC Path(s):**

| Path | Role |
|---|---|
| `/bus/[01…16]/config/name` | IEM mix label |
| `/outputs/main/[01…16]/src` | Bus → XLR/AES path |
| `/outputs/p16/[01…16]/src` | Bus → P16/Ultranet path |
| `/config/routing/AES50A/*`, `AES50B/*` | Bus/Out → stagebox send |

**Verification Status:** Partially Verified (derived domain)  
**Evidence Source:** X32 OSC Remote Protocol + operator model in contract  
**Notes:** **No native IEM OSC path.** Application must derive `routing.iem_mixes[]` from bus names + output trace. This is a **normalization/derivation** step in PH042.03, not a single OSC query.

---

### 3.19 Matrix Routing

**Routing Domain:** Matrix Routing  
**Purpose:** Matrix bus output assignment and matrix processing.  
**OSC Path(s):**

| Path | Role |
|---|---|
| `/outputs/main/[01…16]/src` | Select `Matrix 1…6` |
| `/outputs/aux/[01…06]/src` | Select `Matrix 1…6` |
| `/outputs/p16/[01…16]/src` | Select `Matrix 1…6` |
| `/mtx/[01…06]/mix/fader` | Matrix level (**processing — already in codebase**) |
| `/mtx/[01…06]/mix/on` | Matrix mute (**processing — already in codebase**) |
| `/mtx/[01…06]/config/name` | Matrix label |

**Verification Status:** Partially Verified  
**Evidence Source:** X32 OSC Remote Protocol; codebase reads matrix faders only  
**Notes:** Matrix **routing** = output patch selection. Matrix **processing** already learned but is not routing discovery.

---

## 4. Verified vs Assumed Addresses

### 4.1 Documented and ready for PH042.03 implementation planning

These paths appear explicitly in the X32 OSC protocol reference and scene node lists:

```
/config/routing/routswitch
/config/routing/IN/1-8
/config/routing/IN/9-16
/config/routing/IN/17-24
/config/routing/IN/25-32
/config/routing/IN/AUX
/config/routing/PLAY/1-8
/config/routing/PLAY/9-16
/config/routing/PLAY/17-24
/config/routing/PLAY/25-32
/config/routing/PLAY/AUX
/config/routing/AES50A/1-8 … /41-48
/config/routing/AES50B/1-8 … /41-48
/config/routing/CARD/1-8 … /25-32
/config/routing/OUT/1-4
/config/routing/OUT/5-8
/config/routing/OUT/9-12
/config/routing/OUT/13-16
/config/userrout/in/01…32
/config/userrout/out/01…48
/outputs/main/[01…16]/src
/outputs/aux/[01…06]/src
/outputs/p16/[01…16]/src
/outputs/aes/[01…02]/src
/outputs/rec/[01…02]/src
```

### 4.2 Already in codebase — NOT routing discovery (do not repurpose)

```
/ch/[01…32]/mix/fader|on|pan|st
/ch/[01…32]/config/name|color
/bus/[01…16]/mix/fader|on
/bus/[01…16]/config/name
/mtx/[01…06]/mix/fader|on
/dca/[1…8]/fader|on
/-action/goscene
/xremote
```

### 4.3 Assumed / requires live validation

| Item | Why assumed |
|---|---|
| Exact int → label decoder for `[0…23]`, `[0…35]`, `[0…76]` indices | Doc lists label sets but full index ordering requires appendix tables or live probe |
| XLR socket number ↔ `/outputs/main/NN` index | Model-dependent (X32 Compact vs Full) |
| User In/Out interaction with standard IN banks | FW 4.0+ feature interaction unclear |
| `/ch/NN/config/source` vs input bank precedence | Both exist; relative priority on desk unknown |
| `routswitch` PLAY vs REC active block | Must read switch before interpreting IN vs PLAY paths |

### 4.4 Unknown — do not implement without verification

| Item | Status |
|---|---|
| `/config/routing/IN/4` (contract illustration) | **Invalid path** — use range paths |
| Dedicated `/config/routing/P16/*` | Not in scene node list |
| Dedicated `/config/routing/AUXOUT/*` | Use `/outputs/aux` instead |
| Any routing path not listed in §4.1 | **Unknown — do not guess** |

---

## 5. Firmware Risks

| Risk | Detail | Mitigation |
|---|---|---|
| FW 4.0+ gating | `/config/userrout/in`, `/config/userrout/out` marked FW 4.0+ | Read `/-stat/info` or equivalent; store FW in baseline; skip User paths on older FW |
| AUX label backward compatibility | Doc: “really AUX1-6 but stays AUX1-4 for backward compatibility” | Build enum decoder tolerant of label variants |
| PLAY vs REC routing blocks | `routswitch` selects IN vs PLAY parameter sets | Always read `routswitch` first |
| DP48 commands | `/config/dp48/*` FW 4.0+ — personal monitoring ecosystem | Out of scope unless P16/IEM extended |
| Enum range changes | `[0…23]` vs `[0…35]` vs `[0…76]` differ by parameter | Separate decoders per path family |
| Scene safe / partial recall | Routing may be scene-safe protected | Compare learned routing against scene recall context |

---

## 6. X32 vs M32 Risks

| Risk | Detail | Mitigation |
|---|---|---|
| OSC path parity | Documentation covers “X32/M32 mixer family” together | Treat paths as shared unless live testing proves otherwise |
| Physical output count | M32/Rack variants may have fewer physical outputs | Output index validation on live desk |
| AES50 port labelling | Operator “Stagebox A = AES50-A” is convention not guaranteed | Store raw AES50-A/B; derive operator labels separately |
| `/outputs/main/[01…16]` mapping | 16 output paths exist in protocol; physical XLR count may differ | Live probe per device model in `integration_devices.configuration.console_model` |
| User routing availability | User In/Out may be limited on some models | Feature-detect via FW + probe |

---

## 7. Recommended PH042.03 Learn Order

Safest read-only implementation sequence:

| Order | Domain | Primary OSC paths | Rationale |
|---|---|---|---|
| **1** | Input Banks | `/config/routing/routswitch`, `/config/routing/IN/*` | Unlocks Stagebox A/B + Ableton channel allocation — highest operator value |
| **2** | Card / USB Inputs | IN bank CARD values + `/config/routing/CARD/*` (outputs) | Ableton round-trip; separate in vs out |
| **3** | Out 1–16 | `/config/routing/OUT/*` | Required intermediate layer for output tracing |
| **4** | XLR / Output Patch | `/outputs/main/[01…16]/src` | FOH and spare outputs; maps to `xlr_outputs[]` |
| **5** | AES50 Outputs | `/config/routing/AES50A/*`, `AES50B/*` | Stagebox send side / IEM distribution |
| **6** | Aux Out | `/outputs/aux/[01…06]/src` | Monitor/recording aux paths |
| **7** | P16 / Ultranet | `/outputs/p16/[01…16]/src` | Personal monitor outputs |
| **8** | User Routing | `/config/userrout/in/*`, `/config/userrout/out/*` | FW 4.0+; complex; lowest priority |
| **9** | Derived views | Normalizer only | `derived_operator_view`, `iem_mixes[]`, FOH trace — **after raw reads** |

### PH042.03 implementation architecture (recommended)

```
OscUdpX32ConsoleSnapshotReader
  └── X32RoutingOscReader (new — read only)
        ├── readRoutingSwitch()
        ├── readInputBanks()
        ├── readOutBanks()
        ├── readAes50Outputs()
        ├── readCardRouting()
        ├── readOutputPatches()  // /outputs/main|aux|p16
        └── readUserRouting()    // FW-gated
  └── X32RoutingEnumDecoder (new — int → label maps from OSC appendix)
  └── X32RoutingNormalizer (new — raw → baseline routing JSON shape)
```

Store in baseline:
- `routing.raw_osc[]` — every query path + int value
- `routing.input_banks[]`, etc. — normalized entries with `learned: true`
- `routing.warnings[]` — FW skips, decode failures

---

## 8. Open Questions

1. **Exact enum index tables:** Full integer-to-label mapping for `[0…23]`, `[0…35]`, and `[0…76]` requires OSC protocol appendix tables or live probing. Has the project desk been probed for sample values?
2. **X32 Compact output mapping:** Which `/outputs/main/NN` indices correspond to physical XLR 1–16 on the deployed console model?
3. **User In/Out vs IN banks:** When User routing is configured, does it override IN bank reads for affected channels?
4. **`/ch/NN/config/source`:** Should per-channel source overrides be learned as part of routing discovery or treated as a separate domain?
5. **Playback path:** Is `routswitch = PLAY` relevant for live show operation, or only REC path needed initially?
6. **M32 verification:** Is any deployed hardware M32/Rack requiring separate validation?
7. **Query budget:** Full routing read may require 80+ OSC queries. Does xremote refresh cadence (every 40 queries in current learn) need adjustment?
8. **Headamp correlation:** Should `/-ha/NN/index` be read per channel to corroborate input bank routing?

---

## 9. No-Code-Change Confirmation

This audit was **documentation only**.

- No PHP, JS, CSS, views, routes, tests, migrations, or config were modified.
- No routing reads or writes were added.
- No UI changes were made.
- No git commit was created.
- The only file created: `docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md`

### Evidence sources consulted

| Source | Use |
|---|---|
| `docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md` | Domain requirements |
| `docs/x32/PH042_ROUTING_DISCOVERY_AUDIT.md` | Codebase gap analysis |
| `backend/app/Services/X32/X32OscAddressMap.php` | Existing OSC paths |
| `backend/app/Services/X32/X32InputChannelControlMap.php` | Channel control paths |
| `backend/app/Services/X32/OscUdpX32ConsoleSnapshotReader.php` | Current learn scope |
| `backend/app/Services/X32/FakeX32ConsoleSnapshotReader.php` | Fixture routing stub |
| `backend/app/Services/Console/X32RoutingWorkspaceBuilder.php` | UI consumer keys |
| X32 OSC Remote Protocol (Patrick-Gilles Maillot) | Routing OSC address catalogue |
| X32 User Manual §7.4.6 | AES50 routing screen behaviour |

---

## Related Documents

- `docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md`
- `docs/x32/PH042_ROUTING_DISCOVERY_AUDIT.md`
- Next: **PH042.03** — Read-only routing learn expansion
