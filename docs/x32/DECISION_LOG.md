# X32 Decision Log

X32 console workspace and integration decisions. Platform-wide governance remains in `docs/DECISION_LOG.md`.

---

## X32-DEC-001 — Monitor Buses Are First-Class Workspaces

| Field | Value |
|-------|-------|
| **Decision ID** | X32-DEC-001 |
| **Title** | Monitor Buses Are First-Class Workspaces |
| **Status** | Approved |

### Decision

Monitor buses are not treated solely as routing destinations. Each monitor bus represents a first-class workspace within the show model.

**Canonical route:**

```
shows/{show}/console/bus/{bus}/layout
```

The bus workspace is the single source of truth for:

- Monitor mix configuration
- Bus processing
- Bus EQ
- Bus dynamics
- Output assignments
- Future snapshots/presets
- Future musician self-mix

Engineer and musician views must operate on the same underlying bus workspace and data model.

| Role | Access |
|------|--------|
| **Engineer** | Full control |
| **Musician** | Permission-scoped control of assigned buses only |

### Relationship to Other Workspaces

| Workspace | Scope |
|-----------|--------|
| **Overview** | Channel-centric operational view |
| **Routing** | Signal flow and physical patching |
| **Configuration** | Console architecture and configuration overview |
| **Bus Workspace** | Monitor/IEM-centric workspace |

### Future Consequence

The IEM / Return Buses section becomes a navigation surface into bus workspaces rather than a static informational panel.

---

## X32-DEC-002 — Monitor Bus Master EQ Learned via Documented OSC Paths

| Field | Value |
|-------|-------|
| **Decision ID** | X32-DEC-002 |
| **Title** | Monitor Bus Master EQ Learned via Documented OSC Paths |
| **Status** | Approved (PH043.04) |

### Decision

Monitor bus master EQ for the `{BUSNAME} — EQ` card is learned read-only from X32 OSC bus-layer EQ parameters (`/bus/{NN}/eq/on` and six bands of `type`/`f`/`g`/`q`). Learned values are stored under `configuration.buses[n].eq` with explicit `learned` / `not_learned` field envelopes.

Placeholder scaffold defaults in the EQ card builder are **not** written into configuration learn output when OSC capture is absent.

PH043.04 scope excludes EQ writes, per-band `/eq/{n}/on`, channel/main/send EQ, and DSP-accurate graph rendering.

### Evidence

Patrick Gilles Maillot X32/M32 OSC Remote Protocol — bus EQ chapter; corroborated by project `config.bak` bus EQ dump (bus 01 six-band profile).

---

## X32-DEC-003 — Monitor Send Matrix Learned via Channel Mix Paths

| Field | Value |
|-------|-------|
| **Decision ID** | X32-DEC-003 |
| **Title** | Monitor Send Matrix Learned via Channel Mix Paths |
| **Status** | Approved (PH043.05) |

### Decision

Monitor send matrix for the Channels card is learned read-only from `/ch/{NN}/mix/{BB}/on` and `/level` for all 32×16 channel-to-bus pairs. Per-send `pan` and `type` (tap point) are learned only on **odd** bus indices (01, 03, …, 15) per OSC documentation — even buses omit those paths.

Learned values are stored under `configuration.channels[n].sends.buses[bus]` with `{ value, state, source }` envelopes. Channels card fader/mute display uses learned send level/on for the **selected monitor bus only** — not channel FOH fader/mute.

PH043.05 excludes send writes, FX/matrix/main sends, and group assignment persistence.

### Evidence

Patrick Gilles Maillot X32/M32 OSC Remote Protocol — channel mix sends chapter; `config.bak` channel mix level paths.

---

End of X32 Decision Log — X32-DEC-001, X32-DEC-002, X32-DEC-003
