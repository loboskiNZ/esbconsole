# PH043 – X32 Configuration Contract

## Status

Draft – Architecture Definition

---

# Purpose

The purpose of PH043 is to define the X32 Configuration Domain.

Routing is already governed by PH042.

PH043 governs the operational configuration of the console itself.

This phase must answer:

> How is the console configured?

not

> Where is audio routed?

and not

> Is the hardware connected?

Those concerns belong to separate domains.

---

# Domain Boundaries

## Routing Domain (PH042)

Responsible for:

* Input routing
* Output routing
* AES50 routing
* Card routing
* User routing
* Main LR output assignment
* Monitor output assignment
* Routing visualization
* Routing learn
* Routing synchronization

Question answered:

> Where does audio go?

---

## Configuration Domain (PH043)

Responsible for:

* Console operating configuration
* Channel configuration
* Bus configuration
* DCA configuration
* Matrix configuration
* FX configuration
* Console identity
* Scene configuration
* Global console settings

Question answered:

> How is the console configured?

---

## Connectivity Domain

Responsible for:

* Stagebox presence
* AES50 link status
* Card presence
* Hardware availability
* Clock synchronization status

Question answered:

> Is the hardware available?

---

# PH043 Goals

The operator should be able to open a single page and immediately understand:

* Which scene is active
* How channels are configured
* How buses are configured
* How DCAs are configured
* How matrices are configured
* Which FX are loaded
* Which major console settings are active

without opening the physical console.

---

# Configuration Areas

## Area 1 – Console Identity

Purpose:

Identify the current operating console.

Required Information:

* Console name
* Device profile
* Firmware version
* Console model
* Sample rate
* Clock source
* Current scene
* Current scene name

Status:

Read-only

---

## Area 2 – Channel Configuration

Purpose:

Display how channels are configured.

Required Information:

Per channel:

* Channel number
* Channel name
* Source assignment
* Stereo/mono
* Phantom power
* Gain
* Colour
* Icon
* Mute status
* DCA assignment
* Bus send summary (monitor workspace — PH043.05)

Per channel, per monitor bus (`configuration.channels[n].sends.buses[bus]`):

* `level` — linear 0–1 (+10 dB scale) and dB value, `unit: dB`, `source` OSC path
* `on` — send active (monitor mute is inverse of send off)
* `pan` — where available (odd buses only)
* `tap` — tap/pre/post mode from `/type` enum (odd buses only)

OSC read paths (Patrick Gilles Maillot X32/M32 OSC Remote Protocol):

| Field | OSC path | Scale / enum |
|---|---|---|
| Send on | `/ch/{01…32}/mix/{01…16}/on` | enum int 0/1 |
| Send level | `/ch/{01…32}/mix/{01…16}/level` | level [0.0…1.0(+10dB), 161] |
| Send pan | `/ch/{01…32}/mix/{01,03…15}/pan` | linf [-100, 100, 2] |
| Send tap/type | `/ch/{01…32}/mix/{01,03…15}/type` | int 0–5: in_lc, pre_eq, post_eq, pre_fader, post_fader, grp |
| Pan follow | `/ch/{01…32}/mix/{03,05…15}/panFollow` | enum int 0/1 (learned; not rendered PH043.05) |

No `/tap` OSC path — X32 uses `/type` for tap point. Even bus sends have no per-send pan/type paths.

Missing capture omits `sends` key — Channels card shows placeholder fader (unity) and `—` level display.

Status:

Learnable

Future:

Editable

---

## Area 3 – Bus Configuration

Purpose:

Display monitor and mix structure.

Required Information:

Per bus:

* Bus number
* Bus name
* Stereo/mono
* Linked status
* Sends-on-fader availability
* Assigned outputs
* Primary purpose
* Bus master EQ (monitor workspace — PH043.04)
* Bus master fader and mute (monitor workspace — PH043.09)

Per bus master (monitor workspace Bus Master card, when learned):

| Field | OSC path | Scale / semantics |
|---|---|---|
| Fader | `/bus/{01…16}/mix/fader` | `X32FaderScale` linear [0.0…1.0 (+10 dB)] |
| On / mute | `/bus/{01…16}/mix/on` | int 0/1 — `1` = bus active; UI mute when `on === 0` |

Live writes (PH043.09): write → read-back → UI from confirmed value only. Requires `runtime_mode: live`. No baseline persistence on write.

Per bus master EQ (when learned):

* EQ on/off (`configuration.buses[n].eq.on`)
* Six bands (`configuration.buses[n].eq.bands[]`):
  * `number` (1–6)
  * `mode` (LCUT, LSHV, PEQ, VEQ, HSHV, HCUT; or OSC filter types BU6…LR24 with unsupported reason)
  * `frequency_hz`
  * `gain_db`
  * `q`

OSC read paths (Patrick Gilles Maillot X32/M32 OSC Remote Protocol):

| Field | OSC path | Scale |
|---|---|---|
| Master EQ on | `/bus/{01…16}/eq/on` | enum int 0/1 |
| Band type | `/bus/{01…16}/eq/{1…6}/type` | int 0–13 |
| Band frequency | `/bus/{01…16}/eq/{1…6}/f` | logf [20, 20000, 201] Hz |
| Band gain | `/bus/{01…16}/eq/{1…6}/g` | linf [-15, 15, 0.25] dB |
| Band Q | `/bus/{01…16}/eq/{1…6}/q` | logf [10.0, 0.3, 72] |

Fields not learned in PH043.04: per-band `/eq/{n}/on`, channel EQ, main LR EQ, send EQ.

Learned values use `{ value, state: "learned" }`. Missing capture omits `eq` key — UI shows placeholder scaffold without claiming X32 origin.

Graph rendering remains visual approximation only — not DSP accurate.

* Ed IEM
* Guitar IEM
* Click
* Tracks
* Broadcast

Status:

Learnable

Future:

Editable

---

## Area 4 – DCA Configuration

Purpose:

Display console control groups.

Required Information:

Per DCA:

* DCA number
* DCA name
* Member channels
* Member buses
* Colour

Examples:

* Band
* Vocals
* FX
* Tracks

Status:

Learnable

Future:

Editable

---

## Area 5 – Matrix Configuration

Purpose:

Display matrix routing structure.

Required Information:

Per matrix:

* Matrix number
* Matrix name
* Sources
* Outputs
* Purpose

Examples:

* Broadcast
* Livestream
* Overflow
* Recording

Status:

Learnable

Future:

Editable

---

## Area 6 – FX Configuration

Purpose:

Display loaded effects.

Required Information:

Per FX slot:

* Slot number
* Effect type
* Effect name
* Routing role
* Source buses
* Return channels

Examples:

* Vocal Reverb
* Delay
* Drum Reverb
* Multiband Compressor

Status:

Learnable

Future:

Editable

---

# Scene Awareness Requirements

PH043 must be scene-aware.

Every learned configuration must record:

* Scene number
* Scene name
* Console device
* Learn timestamp

The operator must always know:

> Which scene am I looking at?

---

# Baseline Requirements

Configuration learn data must be stored separately from routing learn data.

Required Structure:

```json
{
  "configuration": {},
  "routing": {},
  "connectivity": {}
}
```

Configuration must never depend on routing assumptions.

Routing must never depend on configuration assumptions.

Connectivity must never depend on either.

---

# Operator Experience Requirements

The page must answer:

1. What console am I connected to?
2. What scene is active?
3. How are channels configured?
4. How are buses configured?
5. How are DCAs configured?
6. How are matrices configured?
7. What FX are active?
8. Is anything unusual?

within approximately 10 seconds.

The page must be understandable by:

* FOH engineers
* Monitor engineers
* Band leaders
* Technical operators

without requiring navigation into deep console menus.

---

# PH043 Phase Sequence

## PH043.01

Configuration Domain Discovery Audit

Objective:

Identify available OSC paths and learn sources.

No UI changes.

No write functionality.

---

## PH043.02

Configuration Learn Foundation

Objective:

Capture configuration from live X32.

Read-only.

---

## PH043.03

Configuration Workspace UI

Objective:

Visualize learned configuration.

Read-only.

---

## PH043.04

Configuration Validation Cycle

Objective:

Validate against real console operation.

Defect correction only.

---

## PH043.05+

Future Editing

Out of scope until learn and validation are complete.

---

# Non-Goals

PH043 must not implement:

* Routing editor
* Routing synchronization
* Connectivity monitoring
* Show execution
* Scene execution
* MIDI control
* OSC control writes

Those belong to separate domains.

---

# Success Criteria

PH043 is successful when an operator can:

* Learn configuration from a real X32
* Open the configuration page
* Understand the console setup within seconds
* Validate that the console matches expectations
* Identify configuration anomalies quickly

without touching the physical console.
