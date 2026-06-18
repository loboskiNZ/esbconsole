# PH044.01 — X32 Effects Discovery & Capability Audit

**Status:** Audit complete (read-only); no Effects UI or live control  
**Date:** 2026-06-18  
**Authority:** `docs/x32/DECISION_LOG.md`, PH042/PH043 discovery patterns  
**Related:** `docs/x32/PH044_EFFECTS_ALGORITHM_CATALOGUE.md`, `docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md` § Area 6  
**Scope:** PH044.01 discovery, documentation, verification scaffolding only

---

## Executive Summary

The X32/M32 provides **8 internal FX processor slots** (`FX1`–`FX8`) with **slot-dependent algorithm catalogues**, up to **64 parameters per slot**, **8 dedicated FX return channels** (`/fxrtn/01`–`08`), and **parallel insert routing** (`/-insert`, `/ch|bus|main|mtx/insert/*`). Effects are **not learned, displayed, or controlled** in the application today. Legacy `index.js` demonstrates historical `/fx/{slot}/type` and `/fx/{slot}/par/{nn}` write patterns; the Laravel backend defines only `/fxrtn/{NN}/mix/fader` in `X32OscAddressMap` and returns **empty `fx: []`** on live learn.

**Product direction (PH044):** Effects will be managed as **show/song-aware effect packages** — not raw FX-slot editing first. **Algorithm ID (four-letter code + slot-relative enum)** is canonical effect identity; **FX slot** is deployment location. **Algorithm changes are between-song/transition operations only** — never during an active song.

**Bottom line:** OSC paths for FX type, parameters, sources, returns, and inserts are **documentation-verified** (Maillot). Channel-level FX send OSC paths are **not listed** in that reference and require **live console probe** before send-level automation. PH044.02+ may add read-only FX learn; PH044.03+ may add package deployment writes with runtime safety gates.

---

## Verification Legend

| Tag | Meaning |
|---|---|
| **Doc-verified** | Listed in Unofficial X32/M32 OSC Remote Protocol (Patrick-Gilles Maillot) |
| **In codebase** | Path helper or precedent exists in repository |
| **Legacy precedent** | Used in `index.js` sync path; not in Laravel backend |
| **Not in codebase** | No application implementation |
| **Unknown** | Requires live-desk probe before implementation |

| Safety class | Meaning |
|---|---|
| **SAFE DURING SONG** | Low audible disruption risk when changed mid-performance |
| **SAFE BETWEEN SONGS** | Acceptable at song boundary or explicit transition cue |
| **NOT RECOMMENDED LIVE** | Audible disruption or routing risk; pre-show or transition only |
| **UNKNOWN** | Requires live console verification |

---

## Sources Inspected

### Contract & prior audits
- `docs/x32/DECISION_LOG.md`
- `docs/x32/PH042_ROUTING_DISCOVERY_AUDIT.md`
- `docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md`
- `docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md` § Area 6
- `docs/x32/PH043_X32_CONFIGURATION_CONTRACT.md` § Area 6

### OSC / transport (read-only)
- `backend/app/Services/X32/X32OscAddressMap.php`
- `backend/app/Services/X32/OscUdpX32ConsoleSnapshotReader.php`
- `backend/app/Services/X32/FakeX32ConsoleSnapshotReader.php`
- `backend/app/Contracts/X32/X32OscConsoleClientInterface.php`
- `index.js` — legacy FX type/parameter sync (`/fx/{n}/type`, `/fx/{n}/par/{nn}`)

### External OSC reference
- [Unofficial X32/M32 OSC Remote Protocol](https://x32ram.com/wp-content/uploads/download-files/X32-OSC.pdf) (Patrick-Gilles Maillot; June 2021 update cited on [author site](https://sites.google.com/site/patrickmaillot/x32))
- [pmaillot/X32-Behringer](https://github.com/pmaillot/X32-Behringer) — emulator and tooling corroboration
- Behringer forum note on **X-Air** FX-as-mix-bus mapping — **not assumed for X32** without desk proof

---

## 1. FX Rack Architecture

### Slot inventory

| Property | Value | Evidence |
|---|---|---|
| **Slot count** | 8 (`FX1` … `FX8`) | Maillot `/fx/[1…8]` |
| **Slot identifiers** | OSC paths `/fx/01` … `/fx/08` (zero-padded in practice) | Maillot; `index.js` uses `/fx/${slot}/` |
| **Algorithm ID read** | `/fx/{slot}/type` → int enum | Doc-verified; read/write same path |
| **Algorithm name** | No dedicated name OSC path; derive from type enum → catalogue (see PH044 catalogue) | Maillot appendix; `/-libs/fx/{001-100}/name` for **presets** only |
| **Parameters** | `/fx/{slot}/par/{01…64}` — up to 64 per algorithm | Doc-verified |
| **FX1–FX4 algorithm range** | Type enum `0…60` (61 algorithms) | Maillot Effects data table |
| **FX5–FX8 algorithm range** | Type enum `0…33` (34 algorithms) | Maillot Effects data table |
| **Stereo/mono** | FX processors are stereo engines; L/R source paths `/fx/{n}/source/l` and `/source/r`; insert uses `FXnL` / `FXnR` | Doc-verified |
| **Insert vs send/return** | Both supported (see §5 Routing) | Doc-verified |
| **FX return channels** | 8 returns `/fxrtn/01…08` (console channel IDs 40–47) | Maillot FX Return chapter |
| **Algorithm assignment readable** | Yes — query `/fx/{slot}/type` | Doc-verified; **not implemented** in learn |
| **Algorithm assignment writable** | Yes — write int to `/fx/{slot}/type` | Doc-verified; legacy `index.js` precedent; **no Laravel write service** |

### Slot split (critical)

FX slots are **not algorithm-interchangeable**:

- **FX1–FX4:** Full creative catalogue — reverbs, delays, modulation, combined FX, and many processors (enum `0…60`).
- **FX5–FX8:** **Processing-biased** subset — GEQ, dynamics, enhancers, guitar amp, etc. (enum `0…33`). **No Hall/Room/Delay reverbs** in FX5–8 enum table.

The **same four-letter code** (e.g. `GEQ`) maps to **different type integers** on FX1–4 vs FX5–8. Package design must use **algorithm code + target slot class**, not a single global integer.

### Codebase state

| Capability | Status |
|---|---|
| FX type learn | **Not implemented** — live learn `fx: []` |
| FX parameter learn | **Not implemented** |
| FX return fader path | `X32OscAddressMap::fxReturnLevel()` — **defined, not read in learn** |
| FX UI tab | Navigation label exists (`effects` tab); **no Effects workspace** |
| FX OSC writes (Laravel) | **None** |

---

## 2. Algorithm Catalogue

Full verified tables: **`docs/x32/PH044_EFFECTS_ALGORITHM_CATALOGUE.md`**

Summary:

| Slot group | Count | Categories present |
|---|---|---|
| FX1–FX4 | 61 | Reverb, Plate, Hall, Room, Delay, Dub-style (MODD, D/RV), Chorus, Flanger, Phaser, Compressor, Limiter, Graphic EQ, Enhancer, Special FX, guitar/amp, pitch |
| FX5–FX8 | 34 | Graphic EQ, TrueEQ, DeEsser, Limiter, Compressor, Enhancer, Exciter, Imager, guitar/amp, phaser, filter, panner, suboctaver |

**Canonical identity:** four-letter OSC code (`HALL`, `DLY`, `GEQ`, …) + slot-group enum value. Preset library uses `/-libs/fx/{001-100}/type` (separate from live slot type).

---

## 3. Parameter Discovery

Parameters follow `/fx/{slot}/par/{01…64}` with algorithm-specific meaning documented in Maillot **Effects Parameters** chapter (§ starting ~page 89).

### Representative verified parameters (package candidates)

| Algorithm | Code | Par# | Name | Type & range | Read/write path |
|---|---|---|---|---|---|
| Hall Reverb | HALL | 1 | Pre Delay | linf [0…200] | `/fx/{slot}/par/01` |
| Hall Reverb | HALL | 2 | Decay | logf [0.2…5] | `/fx/{slot}/par/02` |
| Hall Reverb | HALL | 6 | Level | linf [-12…+12] | `/fx/{slot}/par/06` |
| Plate Reverb | PLAT | 2 | Decay | logf [0.5…10] | `/fx/{slot}/par/02` |
| Stereo Delay | DLY | 1 | Mix | linf [0…100] | `/fx/{slot}/par/01` |
| Stereo Delay | DLY | 2 | Time | linf [1…3000] | `/fx/{slot}/par/02` |
| Modulation Delay | MODD | 1 | Time | linf [1…3000] | `/fx/{slot}/par/01` |
| Modulation Delay | MODD | 9 | Type | enum [AMB, CLUB, HALL] | `/fx/{slot}/par/09` |
| Stereo Graphic EQ | GEQ | 1–31 | Eq Level L/R | linf [-15…+15] each | `/fx/{slot}/par/01`…`31` |
| Stereo Graphic EQ | GEQ | 32 | Master Level L/R | linf [-15…+15] | `/fx/{slot}/par/32` |
| Precision Limiter | LIM | 1 | Input Gain | linf [0…18] | `/fx/{slot}/par/01` |
| Precision Limiter | LIM | 5 | Attack | logf [0.05…1] | `/fx/{slot}/par/05` |
| Fair Comp | FAC | (see Maillot) | Thr, ratio, etc. | per algorithm table | `/fx/{slot}/par/{nn}` |

**Uncertainty:** Unused parameters for a given algorithm may still accept OSC writes; behaviour of out-of-range values is **not verified live**. Full per-algorithm tables (all 61 + 34 types) remain in Maillot PDF — reproduced selectively in catalogue for package candidates only.

**Main FOH EQ/compressor:** Not FX-slot algorithms. Main stereo uses `/main/st/eq/*`, `/main/st/dyn/*` — separate from `/fx/*`. Main insert may host FX5–8 class processors via `/main/st/insert/sel`.

---

## 4. Routing Discovery

### FX input routing (`/fx/{slot}/source`)

| Path | Type | Values (FX1–4) | Role |
|---|---|---|---|
| `/fx/{1…4}/source/l` | enum int [0…17] | INS, MIX1…MIX16, M/C | Left (or mono) input tap |
| `/fx/{1…4}/source/r` | enum int [0…17] | same | Right input tap |
| `/fx/{5…8}/source/l` | **Not listed separately** | Assumed same pattern | **Unknown — verify on desk** |
| `/fx/{5…8}/source/r` | **Not listed separately** | Assumed same pattern | **Unknown — verify on desk** |

- **INS:** Insert mode — FX input fed from channel/bus insert point (paired with `/-insert/fx{n}L|R`).
- **MIX1…MIX16:** Send/return — FX input fed from mix bus send matrix.
- **M/C:** Main mono center tap.

### FX output / returns

| Path | Role |
|---|---|
| `/fxrtn/{01…08}/mix/fader` | FX return master level |
| `/fxrtn/{01…08}/mix/on` | FX return mute |
| `/fxrtn/{01…08}/mix/st` | FX return → Main L/R send |
| `/fxrtn/{01…08}/mix/{01…16}/on`, `/level` | FX return → mix bus sends |
| `/fxrtn/{01…08}/eq/*` | 4-band return EQ |
| `/outputs/*/src` | May include `DirectOut FX 1L…4R` sources | 

FX slot *n* typically pairs with FX return *n* (console convention; **pairing not OSC-proven in this audit**).

### Insert model

| Path | Role |
|---|---|
| `/ch/{01…32}/insert/on`, `/pos`, `/sel` | Channel insert — sel includes FX1L…FX8R, AUX1…6 |
| `/bus/{01…16}/insert/*` | Bus insert |
| `/main/st/insert/*`, `/main/m/insert/*` | Main insert |
| `/mtx/{01…06}/insert/*` | Matrix insert |
| `/-insert/fx{1…8}L`, `/-insert/fx{1…8}R` | Maps FX processor input to channel index |

### Send/return model (typical FOH flow)

```
Channel → (mix bus send) → FX source MIXn → FX processor → FX return n → Main LR / buses
Channel → (HOME FX send — OSC path UNKNOWN) → FX processor → FX return
Channel → insert sel FXnL/R → FX processor (insert) → return to channel chain
```

### Channel FX send levels

| Item | Status |
|---|---|
| Dedicated `/ch/NN/mix/fx/…` or similar | **Not found** in Maillot OSC document |
| `/ch/NN/mix/{01…16}/level` | Doc-verified for **mix buses** — may indirectly feed FX when `/fx/N/source` = MIXn |
| `/meters/9` | Documents effect **send** and **return** meters per FX slot | Confirms sends exist; does not expose write path |
| X-Air forum mapping (FX = mix buses 7–10) | **X-Air only** — do not transfer to X32 without proof |

**Gap:** Per-channel FX send fader OSC paths (HOME screen encoders) require **live console verification**.

### Relationship summary

| Entity | Relationship to FX |
|---|---|
| **Channels** | May send via mix buses → FX source; may use insert; HOME FX sends (**path unknown**) |
| **Mix buses 01–16** | FX processor inputs (MIX1–16); also destinations for FX return sends |
| **FX returns 01–08** | Post-processor mix channels; send to Main and buses |
| **Main LR** | Receives FX returns via `/fxrtn/NN/mix/st`; may host insert FX |
| **Matrix** | Insert point for FX; matrix dynamics key source includes FX returns |

---

## 5. Runtime Safety Audit

### Hard operating rule

> **Effects algorithm changes are allowed only before show, between songs, or during explicit transition cues. Effects algorithm changes must not be performed during an active song.**

### Action classifications

| Action | Classification | Rationale |
|---|---|---|
| **Algorithm change** (`/fx/{slot}/type`) | **NOT RECOMMENDED LIVE** / **SAFE BETWEEN SONGS** only | Reinitializes DSP block; audible gap; parameter set invalidates |
| **Parameter change** (`/fx/{slot}/par/{nn}`) | **SAFE DURING SONG** (single params); **SAFE BETWEEN SONGS** (bulk preset load) | Normal mix automation; bulk change during song risks audibility |
| **FX send level change** | **SAFE DURING SONG** | Standard send automation — **once OSC path verified** |
| **FX return level change** (`/fxrtn/NN/mix/fader`) | **SAFE DURING SONG** | Return fader ride |
| **FX return mute** (`/fxrtn/NN/mix/on`) | **SAFE DURING SONG** | Standard mute |
| **FX routing change** (`/fx/NN/source/*`, mix bus feeds) | **NOT RECOMMENDED LIVE** | Routing clicks / wrong-source risk |
| **FX insert assignment** (`/insert/*`, `/-insert/fx*`) | **NOT RECOMMENDED LIVE** | Insert re-patch audible |
| **Main FOH graphic EQ** (`/main/st/eq/*`) | **SAFE DURING SONG** (single band); caution on broad retune | FOH tonal balance |
| **Main FOH compressor** (`/main/st/dyn/*`) | **SAFE DURING SONG** (threshold/ratio); **SAFE BETWEEN SONGS** (full dyn profile) | GR pumping if aggressive mid-song |

Platform enforcement (future): package deployment API must **reject algorithm/type writes** when show runtime state = active song unless `transition_cue` flag set.

---

## 6. Package Feasibility (initial — presets not finalised)

| Package | Likely slot class | Candidate algorithms (code) | Notes |
|---|---|---|---|
| **Standard Vocal Package** | FX1–4 | PLAT, ROOM, HALL; DLY (slap); DES (de-esser on FX5–8) | Plate/room vocal verbs; short delay; de-ess on processor slots |
| **Reggae Dub Package** | FX1–4 | MODD, DLY, D/RV, D/CR; FILT | Mod delay with AMB/CLUB/HALL type; dub echo; filter sweeps |
| **Horn Funk Package** | FX1–4 | ROOM, CHAM; PHAS, FLNG, ROTA; DLY (short) | Room verb; modulation; Leslie optional |
| **Disco / Techno Package** | FX1–4 | DLY, 4TAP, MODD; FLNG; GATE (FX moments) | Rhythmic delay; flanger; gated verb accents |
| **FOH Main Package** | FX5–8 + Main | GEQ or TEQ; LIM; FAC or ULC on Main `/main/st/dyn` | Graphic EQ in FX slot; limiter; bus comp on Main dynamics — **not FX algorithm** |

All packages deploy to **pre-assigned slot map** defined at show setup; song transitions may swap **parameter presets** within same algorithm; algorithm swaps only at boundaries.

---

## 7. Gaps Requiring Live Console Verification

1. **Channel HOME-screen FX send OSC paths** — not in Maillot parameter tables.
2. **FX5–8 `/source/l|r` paths** — FX1–4 documented explicitly; FX5–8 assumed identical.
3. **FX slot ↔ FX return pairing** — conventional 1:1; confirm on desk.
4. **Type enum write behaviour** — confirm instant switch vs fade; parameter reset rules.
5. **M32 parity** — Maillot documents M32 family; confirm firmware-specific algorithm list unchanged.
6. **Scene safe "Effects" group** — recall behaviour when platform deploys packages mid-show.

---

## 8. PH044.01 Constraints Confirmed

| Constraint | Status |
|---|---|
| No Effects UI | ✓ |
| No Effects routes | ✓ |
| No live write services | ✓ |
| No OSC write commands (Laravel) | ✓ |
| No migrations | ✓ |
| No monitor workspace changes | ✓ |
| No routing/configuration learn changes | ✓ |

---

## 9. Suggested Follow-On Phases (documentation only)

| Phase | Scope |
|---|---|
| PH044.02 | Read-only FX learn (`type`, `source`, `par` snapshot, return names/levels) |
| PH044.03 | Effect package schema + between-song deployment contract |
| PH044.04 | Effects workspace UI (package view, not raw slot editor) |
| PH044.05 | Live parameter writes with safety gate |

---

End of PH044.01 Effects Discovery Audit
