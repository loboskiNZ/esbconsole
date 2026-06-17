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

## X32-DEC-004 — PH043.07 Monitor Send Writes Restricted to Level and On

| Field | Value |
|-------|-------|
| **Decision ID** | X32-DEC-004 |
| **Title** | PH043.07 Monitor Send Writes Restricted to Level and On |
| **Status** | Approved (PH043.06) |

### Decision

PH043.07 may implement live OSC writes for **channel-to-bus send level** and **send on** only, targeting `/ch/{01…32}/mix/{01…16}/level` and `/on` for the **selected monitor bus** on the bus workspace route.

PH043.07 initial scope **excludes**:

- Send pan writes (`/pan` on odd buses)
- Send type/tap writes (`/type` on odd buses)
- Pan follow writes (`/panFollow` on odd buses ≥ 03)
- Grouped fader batch writes (UI-only today; requires multi-send write strategy)
- Bus master fader/mute writes
- Bus EQ writes (per X32-DEC-002)

Send level writes use `X32FaderScale::dbToLinear` + `quantizeLinear` (same fader scale as channel/bus faders). Send on writes use int `0`/`1` with **no** `invert_osc` — unlike channel strip mute (`/ch/NN/mix/on`), send on `1` means send active.

Even buses (02, 04, …, 16) are valid write targets for level/on. Stereo bus link state is learned (`/config/buslink/*`) but does not change level/on OSC paths; pan/type writes remain deferred until stereo-linked send behaviour is live-proven.

### Evidence

PH043.06 readiness audit — `docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md` § PH043.06; existing read path verification in `X32MonitorSendMatrixLearnCapture`, `X32ChannelBusSendOscDecoder`, `X32ConfigurationLearnAssembler`; channel fader write precedent in `ShowConsoleControlService` / `X32InputChannelControlMap`.

---

## X32-DEC-005 — PH043.09 Monitor Bus Master Writes Restricted to Fader and On

| Field | Value |
|-------|-------|
| **Decision ID** | X32-DEC-005 |
| **Title** | PH043.09 Monitor Bus Master Writes Restricted to Fader and On |
| **Status** | Approved (PH043.09) |

### Decision

PH043.09 may implement live OSC writes for **monitor bus master fader** and **bus on/mute** only, targeting `/bus/{01…16}/mix/fader` and `/bus/{01…16}/mix/on` for the **selected monitor bus** on the bus workspace route.

Bus fader writes use `X32FaderScale::quantizeLinear`. Bus on writes use int `0`/`1` where `1` means bus active; UI mute inverts visually (`muted` → write `0`). This matches learn capture (`mute => $on === 0`) and differs from channel strip mute invert semantics.

PH043.09 initial scope **excludes**: Main LR, matrix, channel master, send level/on, bus EQ, group fader, pan, tap, snapshots, and baseline persistence on write.

### Evidence

`X32OscAddressMap::busFader`, `busOn`; `OscUdpX32ConsoleSnapshotReader::readBuses`; PH043.07/PH043.08 live-control precedent (`ShowConsoleMonitorSendControlService`, `ShowConsoleMonitorBusEqControlService`).

---

End of X32 Decision Log — X32-DEC-001, X32-DEC-002, X32-DEC-003, X32-DEC-004, X32-DEC-005
