# PH042 — X32 Routing Discovery Contract

## Purpose

This document is the working contract for the next X32/Ableton routing phase.

It exists so the implementation agent does not make vague assumptions about “routing”. In this project, **X32 routing always means audio routing**, not IT/network routing.

The next phase must connect the Routing workspace to the real console by first learning the X32 routing state accurately. Editing, previewing, syncing, and saving configurations must come later.

---

## Project Rule: Routing Means Audio Signal Routing

When this project says **routing**, it means:

- input source routing
- local input routing
- AES50-A input/output routing
- AES50-B input/output routing
- USB/Card input/output routing
- XLR output routing
- Aux output routing
- P16/Ultranet routing
- User In/User Out routing where supported
- bus, main, matrix, direct-out, monitor, and output-source assignment

It does **not** mean:

- IP networking
- DNS
- subnets
- server routing
- Laravel routes
- web routes
- Cloudflare routing

---

## Operator Model

The operator thinks in production terms:

```text
Stagebox A
Stagebox B
Ableton
FOH
IEMs
```

The X32 stores routing in technical routing tables.

The software must bridge those two models.

The UI may show:

```text
Stagebox A → Console Channels → FOH / IEMs
```

But the learning system must capture the real X32 routing tables underneath.

---

## Current Common User Setup

The user’s common setup is:

```text
Stagebox A:
- 16-channel stagebox
- connected via AES50-A
- commonly feeds console channels 1–16

Stagebox B:
- 16-channel stagebox
- connected via AES50-B
- commonly feeds console channels 17–24 when Ableton uses 25–32
- may feed channels 17–32 in setups without Ableton

Ableton:
- 8 USB/Card return channels
- commonly mapped to console channels 25–32
```

Common suggested layout:

```text
CH01–16 = Stagebox A / AES50-A 1–16
CH17–24 = Stagebox B / AES50-B 1–8
CH25–32 = Ableton / Card or USB 1–8
```

This common setup may be shown as **expected/default scaffold**, but must not be stored or reported as learned console state unless the console actually reports it.

---

## X32 Routing Reality

The X32 is not a single free-form patchbay. It uses a mixture of:

1. **8-channel block routing**
2. **per-output source assignment**
3. **intermediate output banks** such as Out 1–16, Aux Out, P16/Ultranet, User Out
4. **physical/digital destination assignment** such as local XLR, AES50-A, AES50-B, Card/USB

The application must therefore avoid oversimplified assumptions such as:

```text
Channel 1 always equals local input 1
XLR 1 always equals Main L
AES50-A always equals Stagebox A
Card 1 always equals Ableton Return 1
```

Those are common conventions, not guaranteed facts.

---

## High-Level Signal Model

The software should understand routing as layered signal movement:

```text
Physical / digital sources
        ↓
X32 input routing banks
        ↓
Console input channels
        ↓
Channel processing / sends / buses / mains / matrices
        ↓
Output source assignments
        ↓
Out 1–16 / Aux Out / P16 / User Out / Card Out
        ↓
Physical / digital outputs
```

Operator-facing model:

```text
Stagebox A + Stagebox B + Ableton
        ↓
Console Channels
        ↓
FOH + IEMs + Recording / Stream / Spares
```

---

# PH042 Implementation Roadmap

## PH042.01 — Routing Discovery Contract

Create and maintain this contract in the project repository.

Expected project file path:

```text
docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md
```

The agent must read this document before implementing any routing activation work.

## PH042.02 — OSC Address Audit

Before reading/writing routing, audit the current codebase and identify the actual OSC paths available for X32 routing.

Deliverable:

```text
docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md
```

The audit must list:

- known OSC paths already implemented
- routing paths present in code but not used
- routing paths missing from code
- uncertain paths requiring manual verification
- explicit “do not guess” notes

No sync/write implementation may happen until this audit exists.

## PH042.03 — Read-Only Routing Learn Expansion

Expand Learn From Console to capture real routing data into the learned baseline.

No editing.
No writing to console.
No template saving.

## PH042.04 — Populate Routing UI From Learned Routing

Replace scaffold/expected state with learned routing state where available.

## PH042.05 — Software-Side Edit Model

Allow routing to be edited in the application without writing to the console.

## PH042.06 — Preview Diff

Show exactly what will change before the console is modified.

## PH042.07 — Sync to Console

Only after learn, edit, and preview are reliable, write routing changes back to the X32.

---

# Routing Domains To Learn

The learning system must eventually capture the following domains.

## 1. Input Routing Banks

Purpose:
Determine what source feeds console channels.

Expected routing banks:

```text
Input 1–8
Input 9–16
Input 17–24
Input 25–32
Aux In / Aux Remap where supported
User In where supported
```

Possible sources:

```text
Local
AES50-A
AES50-B
Card / USB
Aux
User In
```

Must learn:

- bank range
- source type
- source offset/range
- whether value was read from console
- raw OSC path/value
- normalized application interpretation

Example normalized model:

```json
{
  "domain": "input_banks",
  "bank": "inputs_25_32",
  "console_channels": [25, 26, 27, 28, 29, 30, 31, 32],
  "source_type": "card_usb",
  "source_range": "card_1_8",
  "learned": true,
  "raw": {
    "osc_path": "/config/routing/IN/4",
    "value": "CARD 1-8"
  }
}
```

The exact OSC path above is illustrative only. The implementation agent must audit the actual supported paths before coding.

---

## 2. Local XLR Inputs

Purpose:
Represent local console input sockets as possible sources.

Must learn/represent:

- availability of local inputs
- local input source ranges available to input banks
- whether any local inputs are currently feeding channels

Do not assume local inputs are unused just because stageboxes exist.

---

## 3. AES50-A Input Side

Purpose:
Represent digital audio arriving from AES50-A, commonly Stagebox A.

Must learn/represent:

- AES50-A input source ranges used by input banks
- bank mappings into console channels
- possible stagebox association if known
- raw values and normalized mappings

Operator interpretation:

```text
Stagebox A
```

Technical interpretation:

```text
AES50-A inputs
```

The software may label AES50-A as Stagebox A in the UI only if that association is configured, learned, or expected for the current scaffold.

---

## 4. AES50-B Input Side

Purpose:
Represent digital audio arriving from AES50-B, commonly Stagebox B.

Must learn/represent:

- AES50-B input source ranges used by input banks
- bank mappings into console channels
- whether channels 17–24 or 17–32 are fed from AES50-B
- raw values and normalized mappings

Operator interpretation:

```text
Stagebox B
```

Technical interpretation:

```text
AES50-B inputs
```

---

## 5. Card / USB Input Side

Purpose:
Represent audio returning from Ableton or another computer/recording source into the console.

Must learn/represent:

- whether Card/USB is used as an input bank source
- which Card/USB range feeds which console channels
- likely Ableton return allocation when configured
- raw values and normalized mappings

Common operator interpretation:

```text
Ableton Returns → CH25–32
```

Technical interpretation:

```text
Card/USB 1–8 → Input bank 25–32
```

Do not confuse Card/USB inputs into the console with Card/USB outputs from the console.

---

## 6. Out 1–16

Purpose:
Represent the internal output source assignment bank commonly used as an intermediate layer before physical/digital outputs.

Must learn:

- each Out 1–16 source
- source tap if available
- source category such as Main, Bus, Matrix, Direct Out, Monitor, etc.
- raw OSC path/value
- normalized source

Example operator interpretation:

```text
Bus 1 = Ed IEM
Bus 2 = Guitar IEM
Main L/R = FOH
```

Technical layer may be:

```text
Out 1 = Main L
Out 2 = Main R
Out 3 = Mix Bus 1
Out 4 = Mix Bus 2
```

The software must preserve the technical layer and separately derive operator labels.

---

## 7. Local XLR Outputs

Purpose:
Represent the physical XLR outputs on the console.

Must learn/represent:

- which internal output bank feeds each local XLR output
- whether XLR outputs are sourced from Out 1–16, User Out, or another output bank depending on X32 model/firmware
- FOH assignment if Main L/R is mapped to local XLR
- IEM assignment if buses are mapped to local XLR

Operator interpretation:

```text
FOH Left → XLR 1
FOH Right → XLR 2
Ed IEM → XLR 3
```

Technical interpretation may require tracing:

```text
Main L → Out 1 → XLR 1
Bus 1 → Out 3 → XLR 3
```

---

## 8. AES50-A Output Side

Purpose:
Represent audio sent out of the console through AES50-A to stagebox outputs or other AES50 devices.

Must learn:

- AES50-A output banks
- each bank’s source group
- whether Out 1–16, User Out, P16, Card, or other sources are assigned
- raw and normalized values

Operator interpretation:

```text
IEMs sent to Stagebox A outputs
```

Technical interpretation:

```text
AES50-A Out 1–8 = Out 1–8
```

Do not confuse AES50-A inputs with AES50-A outputs.

---

## 9. AES50-B Output Side

Purpose:
Represent audio sent out of the console through AES50-B.

Same rules as AES50-A output side.

Operator interpretation may be:

```text
Stagebox B outputs
Additional monitor sends
Spare AES50-B output bank
```

---

## 10. Card / USB Output Side

Purpose:
Represent audio sent from the console to Ableton/DAW/recording computer.

Must learn:

- Card Out banks
- source of Card Out 1–32
- whether channels, direct outs, buses, mains, or user outs feed the card
- tap points if available

Operator interpretation:

```text
Recording feed
Ableton capture
Virtual soundcheck send
```

Technical interpretation:

```text
Direct Outs CH1–32 → Card Out 1–32
```

This is separate from Ableton returns into the console.

---

## 11. Aux Out

Purpose:
Represent auxiliary output assignments.

Must learn:

- Aux Out source assignment
- whether Aux outputs are used for monitors, recording, sidefills, spare feeds, or other destinations
- raw and normalized values

---

## 12. P16 / Ultranet

Purpose:
Represent personal monitoring output assignments.

Must learn:

- P16 channel source assignments
- source categories
- whether used for IEM/personal mixer routing

Operator interpretation:

```text
Personal monitor feeds
```

Technical interpretation:

```text
P16 channel source assignments
```

---

## 13. User In / User Out

Purpose:
Represent flexible user patching where firmware/model supports it.

Must learn:

- User In assignments
- User Out assignments
- source/destination mapping
- whether other routing banks depend on User In/User Out

Important:
The software must not assume only fixed 8-channel routing if User routing is available.

---

## 14. Buses / Main / Matrix Source Interpretation

Purpose:
Interpret output source assignments into operator-friendly destinations.

Must represent:

- Main L/R
- Mono/Centre if applicable
- Mix Buses 1–16
- Matrix 1–6
- Direct Outs
- Monitor / Solo sources where relevant

This domain is used to label outputs as:

```text
FOH
IEM
Monitor
Recording
Stream
Spare
Unknown
```

The software must store raw routing first, then derive operator meaning second.

---

# Required Learned Routing Data Shape

The application should store learned routing in a structured JSON model within the baseline summary or a dedicated future routing model.

Minimum read-only learned structure:

```json
{
  "routing": {
    "learned_at": "2026-06-16T00:00:00+12:00",
    "source": "x32_console",
    "input_banks": [],
    "local_inputs": [],
    "aes50_a_inputs": [],
    "aes50_b_inputs": [],
    "card_inputs": [],
    "out_1_16": [],
    "xlr_outputs": [],
    "aes50_a_outputs": [],
    "aes50_b_outputs": [],
    "card_outputs": [],
    "aux_outputs": [],
    "p16_outputs": [],
    "user_in": [],
    "user_out": [],
    "derived_operator_view": {
      "stagebox_a": {},
      "stagebox_b": {},
      "ableton": {},
      "foh": {},
      "iems": [],
      "spares": []
    },
    "raw_osc": [],
    "warnings": []
  }
}
```

Raw OSC reads must be preserved or auditable during early phases.

---

# Hard Rules For Implementation Agents

## Rule 1 — No guessing OSC paths

If an OSC path is unknown, the agent must stop and report it as unknown.

Do not invent routing paths.

## Rule 2 — Read before write

No routing write/sync may be implemented until read-only learning exists and is tested.

## Rule 3 — Raw first, derived second

The system must store raw learned routing data first.

Operator labels such as Stagebox A, Ableton, FOH, and IEM are derived interpretations.

## Rule 4 — Suggested is not learned

Expected/common setup may be displayed for scaffold purposes, but must never be reported as learned state.

## Rule 5 — No hidden console writes

Learning may read from the console only.

Editing software-side configuration must not write to the console until explicit sync is implemented.

## Rule 6 — Preview before sync

Any future write/sync phase must include a preview/diff showing what will change.

## Rule 7 — Preserve operator model

The UI should remain production-facing:

```text
Stagebox A
Stagebox B
Ableton
FOH
IEMs
```

The backend may contain X32 technical routing tables.

## Rule 8 — Do not break live console controls

Routing learn/sync must not interfere with:

- fader control
- mute control
- existing OSC control map
- console overview
- learned baseline display

---

# PH042.01 Agent Task

The first implementation task is not to code routing read/write.

The first task is to add this document to the project and produce the OSC audit.

Required agent output:

1. Confirm this document exists at:

```text
docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md
```

2. Search the codebase for routing-related OSC paths, services, adapters, packets, tests, and learned summary keys.

3. Produce:

```text
docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md
```

4. The audit must include:

- current code paths inspected
- existing routing-related OSC support
- missing routing domains
- unknown/uncertain OSC paths
- recommended next read-only implementation sequence
- explicit blocker list for anything that cannot be verified

5. No routing write/sync code.

6. No UI changes.

7. No database migration unless explicitly justified and approved.

8. No commit until reviewed.

---

# Acceptance Criteria For PH042.01

- Contract document added to project.
- OSC routing address audit document produced.
- No console writes implemented.
- No UI changes implemented.
- No unrelated code refactors.
- Agent clearly identifies what is known vs unknown.
- Agent does not guess OSC paths.
- Existing tests pass.

