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
* Bus send summary

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

Examples:

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
