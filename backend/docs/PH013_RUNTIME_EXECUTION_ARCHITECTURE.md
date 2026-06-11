# PH013 – Runtime Execution Architecture

## Status

Architecture Phase

No runtime execution is implemented in PH013.

Purpose:

Define the execution architecture that will later connect Ableton cue events to X32, Lighting, Musician Devices, Video Systems, and future runtime outputs.

---

# 1. Purpose

The Runtime Execution Architecture provides a deterministic, auditable, local-first execution pipeline for live performances.

It defines:

* Runtime event ingestion
* Cue resolution
* Action planning
* Action dispatch preparation
* Adapter boundaries
* Failure handling
* Audit requirements

It does not define:

* MIDI implementation
* X32 implementation
* DMX implementation
* OSC implementation
* WebSocket implementation

Those are future phases.

---

# 2. Core Runtime Principle

Ableton is authoritative.

The runtime does not decide which cue is active.

Ableton decides.

The platform reacts.

Runtime identity:

SSS.CCC

Example:

001.003

Where:

* SSS = Song Code
* CCC = Cue Number

The runtime identity is derived and never stored as a primary key.

---

# 3. Runtime Event Lifecycle

## 3.1 Event Source

Future event sources may include:

* Ableton MIDI
* Ableton OSC
* Manual Operator Trigger
* Rehearsal Simulation
* Testing Tools

All sources must ultimately generate a Runtime Event.

---

## 3.2 Runtime Event

Represents a cue transition entering the platform.

Example:

001.003

Fields:

```text
event_id
performance_id

source
event_type

runtime_identity

song_code
cue_number

received_at

payload
```

Example:

```json
{
  "source": "ABLETON",
  "event_type": "CUE_ENTER",
  "runtime_identity": "001.003"
}
```

---

# 4. Cue Resolution

Runtime identity is translated into:

```text
Song
Cue
```

Example:

```text
001.003
```

resolves to:

```text
Song 001
Cue 003
```

Validation:

* Song must exist
* Cue must exist
* Cue must belong to song

If resolution fails:

Runtime Event status becomes:

```text
FAILED_RESOLUTION
```

No execution proceeds.

---

# 5. Action Resolution

PH012 introduced:

```text
CueActionResolver
```

The resolver remains authoritative.

Pipeline must never bypass it.

Resolution returns:

```text
Ordered enabled actions
```

Example:

```text
Cue
001.003

1 X32_SCENE
2 LIGHT_SCENE
3 MUSICIAN_MESSAGE
```

Only enabled actions are returned.

---

# 6. Runtime Action Plan

A Runtime Action Plan represents the complete execution plan for a single Runtime Event.

Generated after successful cue resolution.

Fields:

```text
plan_id

runtime_event_id

performance_id

runtime_identity

status

created_at
```

Statuses:

```text
PENDING

READY

BLOCKED

CANCELLED

COMPLETED

FAILED
```

---

# 7. Runtime Action Items

Each resolved action becomes an Action Item.

Example:

```text
Recall Scene 05
```

Fields:

```text
action_item_id

runtime_action_plan_id

action_definition_id

action_type

sort_order

parameters

status
```

Statuses:

```text
PENDING

READY

DISPATCHED

ACKNOWLEDGED

FAILED

SKIPPED
```

---

# 8. Deterministic Ordering

Actions must execute in deterministic order.

Ordering authority:

```text
CueAction.sort_order
```

Secondary ordering:

```text
CueAction.id
```

Example:

```text
1 Blackout

2 Recall X32 Scene

3 Stage Wash Blue

4 Musician Message
```

Order must never depend on:

* database retrieval order
* threading
* adapter speed

---

# 9. Execution Pipeline

Logical flow:

```text
Runtime Event
        ↓
Cue Resolution
        ↓
CueActionResolver
        ↓
Runtime Action Plan
        ↓
Runtime Action Items
        ↓
Execution Dispatcher
        ↓
Execution Adapters
```

Execution adapters receive prepared actions only.

Adapters must never perform business resolution.

---

# 10. Adapter Boundary Rules

Adapters are technology translators.

Examples:

```text
X32Adapter

LightingAdapter

MusicianDeviceAdapter

VideoAdapter

CustomAdapter
```

Adapters must:

* receive action item
* execute action
* return result

Adapters must not:

* query songs
* query cues
* query playlists
* resolve runtime identities
* determine action order
* contain show logic

Bad:

```text
If cue 001.003 then recall scene 05
```

Good:

```text
Execute scene 05
```

---

# 11. Local-First Runtime Model

Live performance must not depend on internet access.

Required:

```text
Local runtime

Local database

Local cache

Local action plans

Local execution
```

Cloud services support:

* preparation
* administration
* synchronization

Cloud services must not be required to continue a performance.

---

# 12. Failure Handling

Failure of one action must not stop unrelated actions.

Example:

```text
Lighting fails
```

Does not automatically prevent:

```text
X32 Scene Recall

Musician Message
```

The runtime should support:

```text
CONTINUE_ON_FAILURE
```

per action category in future phases.

---

# 13. Runtime Audit Model

Every stage should eventually be auditable.

Required audit chain:

```text
Event Received

Cue Resolved

Actions Planned

Actions Dispatched

Adapter Result

Final Outcome
```

Future operators must be able to answer:

```text
What happened?

When?

Why?

Which action failed?

Which adapter failed?
```

---

# 14. Performance Context

Execution always occurs within a Performance.

Operators run:

```text
Performance
```

Not:

```text
Show
```

Runtime actions should therefore eventually be associated with:

```text
performance_id
```

for auditing and replay purposes.

---

# 15. Future Phase Boundaries

## PH014

Runtime Event Domain

Potential scope:

* Runtime Events
* Runtime Action Plans
* Runtime Action Items
* Audit Records

No hardware execution.

---

## PH015

Ableton Runtime Ingestion

Potential scope:

* MIDI Listener
* OSC Listener
* Runtime Event Creation

No X32 execution.

---

## PH016

Execution Dispatcher

Potential scope:

* Action Dispatch Engine
* Action Queuing
* Retry Policies

No hardware execution.

---

## PH017+

Technology Adapters

Potential scope:

* X32 Adapter
* Lighting Adapter
* Musician Device Adapter
* Video Adapter

---

# 16. Non-Goals

This phase does not implement:

* MIDI
* OSC
* X32 networking
* DMX output
* WebSockets
* Runtime daemon
* Worker processes
* Hardware communication
* Execution services

PH013 defines architecture only.

---

# 17. Success Criteria

PH013 is complete when the project can clearly answer:

* What happens when Ableton emits 001.003?
* How is a cue resolved?
* How are actions prepared?
* How are actions ordered?
* How are actions dispatched?
* What belongs in adapters?
* What belongs in business logic?
* How does the platform remain offline-capable?
* How will future execution be audited?

No hardware execution is required for PH013 completion.
