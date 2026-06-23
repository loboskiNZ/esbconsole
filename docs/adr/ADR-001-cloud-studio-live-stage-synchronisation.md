# ADR-001 — Cloud Studio ↔ Live Stage Synchronisation Model

| Field | Value |
|-------|-------|
| **ADR ID** | ADR-001 |
| **Title** | Cloud Studio ↔ Live Stage Synchronisation Model |
| **Status** | Accepted |
| **Date** | 2026-06-24 |
| **Authority** | `docs/PROJECT_CHARTER.md`, `docs/ARCHITECTURE.md`, `docs/DECISION_LOG.md` (Decision 182) |

---

## Context

The platform consists of two distinct operating environments:

**Cloud Studio**

- Server-hosted environment
- Musician portal
- Song, chart, and performance management
- May be edited by directors and authorised users
- Normally online

**Live Stage**

- Local runtime environment
- X32 / Ableton performance system
- Must remain fully operational without internet access
- May also create and edit songs and related assets
- Connectivity is not guaranteed during rehearsal or performance

Prior governance established cloud canonicality for published master library data (Decisions 022, 051, 053) and offline-capable local performance execution. Song asset authoring now requires an explicit peer-environment synchronisation model that prevents silent overwrites while preserving offline-first rehearsal and performance reliability.

Decision **178A** (Musician Evaluation Prohibition) remains in force — synchronisation and conflict review are operator-controlled preparation workflows, not musician scoring or readiness inference.

---

## Decision

### Terminology

Use the following terms consistently:

| Term | Meaning |
|------|---------|
| **Cloud Studio** | Server environment — musician portal, cloud-hosted collaboration, song/chart/performance management |
| **Live Stage** | Local performance environment — rehearsal and performance runtime, offline-capable |

Do **not** use alternative terms for these environments in new documentation or implementation.

**Mapping note (legacy docs):** Cloud Studio corresponds to the Cloud Environment / Band Portal / ESB Studio surfaces. Live Stage corresponds to Director Local and Local Show Runtime authoring and execution hosts. Runtime timeline authority during performance remains with Ableton (`docs/RUNTIME_MODEL.md`).

### Authoring Authority

Both Cloud Studio and Live Stage may:

- Create songs
- Edit songs
- Edit song metadata
- Edit charts
- Edit performance-related song information

**Neither environment is the permanent overwrite authority.**

### Offline-First Principle

Live Stage must be able to operate with no internet connection.

The architecture must assume that Live Stage may be offline approximately **50% of the time**.

No critical rehearsal or performance workflow may depend on cloud connectivity.

### Checkout Model

Before editing, a song must be **checked out**.

Checkout applies to:

- Song metadata
- Charts
- Song briefs / director notes
- Instrument assignments
- Future song-related assets

While checked out:

- Other environments may **read**
- Other environments may **not silently overwrite**

Checkout information must identify:

- Environment
- User
- Timestamp
- Version

#### Offline Checkout

Live Stage may create local checkouts while offline.

Offline edits create **pending local changes**.

Pending changes must record:

- Song identifier
- Base version
- Local version
- Changed fields
- Changed assets
- Timestamp
- Origin environment

### Synchronisation Authority

Synchronisation is **initiated by Live Stage**.

Process:

```
Live Stage
  → Request Synchronisation
  → Retrieve cloud state
  → Detect checkouts
  → Compare versions
  → Produce diff
  → Present conflicts
  → Operator decision
  → Synchronise approved changes
```

### Conflict Resolution

**No automatic last-write-wins behaviour.**

If both environments changed the same song:

- A diff must be produced
- Conflicts must be visible
- Operator must choose resolution

Conflict review should be possible at **field level** where practical.

Examples:

- BPM changed
- Key changed
- Mood changed
- Director notes changed
- Chart file replaced

### Versioning Requirement

Song synchronisation must be **version-aware**.

Synchronisation decisions must compare:

| Version | Role |
|---------|------|
| **Base Version** | Common ancestor at checkout or last successful sync |
| **Current Cloud Version** | Cloud Studio state |
| **Current Live Stage Version** | Live Stage state |

Version comparison is the authority model. **Timestamp alone is not sufficient.**

### Future Design Constraint

All future song-management features must support:

- Offline Live Stage operation
- Explicit synchronisation
- Checkout awareness
- Diff generation
- Conflict resolution

No future implementation may assume constant internet connectivity.

---

## Rationale

Live Stage is a rehearsal and performance system and must continue functioning when connectivity is unavailable.

Cloud Studio and Live Stage are **peer authoring environments**.

Safe synchronisation requires:

- Explicit checkout
- Version tracking
- Diff review
- Operator-controlled conflict resolution

Silent overwrites are prohibited.

---

## Consequences

### Positive

- Rehearsal and performance remain reliable without cloud connectivity
- Directors and operators retain control over conflicting edits
- Field-level review supports musical direction decisions (metadata, charts, briefs)

### Negative / Cost

- Song sync implementation is more complex than simple push/pull or upsert
- All environments must track version and checkout state for governed assets

### Neutral

- Runtime performance authority (Ableton timeline, Local Show Runtime execution) is unchanged
- Decision 178A prohibition on musician evaluation is unchanged

---

## Compliance

| Requirement | ADR-001 position |
|-------------|------------------|
| Offline performance (`PROJECT_CHARTER.md`) | Reinforced — Live Stage offline ~50% assumed |
| Ableton runtime authority | No conflict — timeline authority unchanged |
| Decision 178A | No conflict — sync is preparation workflow, not evaluation |
| Decisions 022, 051, 053 | Amended for **song asset authoring** — peer model replaces silent cloud overwrite; publish/sync-before-show patterns remain for operational deployment |

---

## References

- `docs/ARCHITECTURE.md` — Cloud Studio and Live Stage Architecture
- `docs/DECISION_LOG.md` — PH054 / Decision 182
- `AGENTS.md` — Environment terminology and synchronisation warnings
- `docs/RUNTIME_MODEL.md` — Local runtime authority during performance
- `docs/DATA_ARCHITECTURE.md` — Data ownership matrices

---

End of ADR-001
