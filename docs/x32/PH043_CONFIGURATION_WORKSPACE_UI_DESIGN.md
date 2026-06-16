# PH043.03A — X32 Configuration Workspace UI Design

**Status:** Design complete (no implementation)  
**Date:** 2026-06-17  
**Authority:** `docs/x32/PH043_X32_CONFIGURATION_CONTRACT.md`  
**Related:** `docs/x32/PH043_CONFIGURATION_DISCOVERY_AUDIT.md`, PH043.02 (`baseline_json.configuration`)  
**Scope:** Operator-facing read-only configuration workspace — documentation only

---

## Purpose

Design a dedicated **Configuration** workspace where an operator can answer, within about ten seconds:

1. What scene and console configuration am I looking at?
2. How are channels configured?
3. How are buses configured?
4. What DCAs are active?
5. What matrices are active?
6. What FX are configured or missing?
7. What is incomplete or unusual?

This page is **not** a technical report, JSON viewer, or routing editor. It is a read-only operator workspace that consumes normalized configuration from PH043.02.

---

## Domain Boundaries (Non-Negotiable)

| Domain | This page | Elsewhere |
|---|---|---|
| **Configuration (PH043)** | Channel, bus, DCA, matrix, FX, console identity | This page |
| **Routing (PH042)** | Reference only (source/output labels derived from routing pointers) | Audio Routing workspace |
| **Connectivity** | No live hardware status | Routing workspace (source cards) |
| **Console control** | No fader/mute writes, no sync | Overview workspace (future) |

The Configuration page may **link** to Routing (“View audio routing”) but must not embed routing editing, connectivity tiles, or sync actions.

---

## Data Source

Primary read model:

```
baseline_json.configuration          (active baseline)
learned_summary_json.configuration   (unsaved preview after learn)
```

Fallback during transition: flat root `channels[]`, `buses[]`, etc. are **not** used by this page once PH043.03B ships — the builder reads `configuration` only.

Each configuration field follows the PH043.02 envelope:

```json
{ "value": <mixed>, "state": "learned" | "not_learned", "reason": "<internal>" }
```

The UI **never** surfaces `reason` codes or internal field names to operators.

Supporting metadata (not displayed as primary content):

- `configuration.learned_at` — used for “Learned …” timestamp in identity row
- `configuration.source` — `live_osc` vs fixture; affects operator copy for missing data
- `configuration.warnings[]` — fed into Configuration Health section

Diagnostic evidence (`raw_snapshot_json.configuration_capture`) is **admin/debug only** — not shown on this operator page.

---

## Page Placement & Navigation

Add a **Configuration** tab to the ESB Console tab bar (alongside Overview and Routing).

| Tab | Route (proposed) | Status |
|---|---|---|
| Overview | existing | Live control workspace |
| **Configuration** | `shows.console.configuration` (PH043.03B) | **New — this design** |
| Routing | existing | Audio routing workspace |

Configuration is read-only in PH043.03. No Save, Sync, or Edit actions on this page. The top bar may retain the global **Learn** action (existing console learn flow).

---

## Page Layout (Top to Bottom)

```
┌─────────────────────────────────────────────────────────────────────────┐
│ 1. Header Row                                                           │
├─────────────────────────────────────────────────────────────────────────┤
│ 2. Console Identity Row (4 cards)                                       │
├─────────────────────────────────────────────────────────────────────────┤
│ 3. Channel Configuration Row (32 scribble-style tiles)                  │
├─────────────────────────────────────────────────────────────────────────┤
│ 4. Bus / IEM Configuration Row (4×4 grid)                             │
├─────────────────────────────────────────────────────────────────────────┤
│ 5. DCA / Matrix Row (two side-by-side cards)                            │
├─────────────────────────────────────────────────────────────────────────┤
│ 6. FX Row                                                               │
├─────────────────────────────────────────────────────────────────────────┤
│ 7. Configuration Health / Gaps                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

Visual language should align with existing ESB Console workspaces (`vx32-*` patterns from Overview and Routing) but optimized for **scanning configuration**, not live control.

---

## Section 1 — Header Row

### Operator content

| Element | Copy / behaviour |
|---|---|
| Context label | **ESB Console** |
| Page title | **X32 Configuration** |
| Learned context line | `Learned from {console_name} · Scene {scene_number} – {scene_name}` |
| Configuration status badge | **Complete** / **Partial** / **Needs attention** |
| Secondary link | “View audio routing →” (links to Routing tab; no inline routing map) |

### Learned context line rules

| Field | Source | Display when missing |
|---|---|---|
| Console name | `configuration.identity.console_name` | Use device name from app if learned; otherwise “Console” |
| Scene number | `configuration.identity.scene_number` | “Scene —” |
| Scene name | `configuration.identity.scene_name` | Omit name segment; show `Scene 02` only |

Example (full):  
`Learned from Main X32 · Scene 02 – Band Rehearsal`

Example (no scene name):  
`Learned from Main X32 · Scene 02`

### Configuration status badge (derived)

Computed by `X32ConfigurationWorkspaceBuilder` (PH043.03B) from `configuration` field states and anomaly rules (Section 7).

| Status | Operator meaning | Typical conditions |
|---|---|---|
| **Complete** | All major configuration areas captured; no blocking gaps | Rare at PH043.03 launch — reserved for future when FX + globals are fully learned |
| **Partial** | Usable snapshot; some areas not captured yet | Default for live learn today (FX, firmware, some DCA/matrix fields) |
| **Needs attention** | Configuration may be misleading or unusually empty | Generic strip names on active channels, scene name missing on live learn, >50% buses unnamed, zero learned channel names, etc. |

Badge styling mirrors Routing workspace state pills (learned / partial / not-learned visual tiers).

### Fields consumed

- `configuration.identity.console_name`
- `configuration.identity.scene_number`
- `configuration.identity.scene_name`
- `configuration.learned_at`
- Aggregated health from all sections (for badge)

---

## Section 2 — Console Identity Row

Four compact summary cards in a horizontal row.

### Card A — Console

| Label | Source field | Display |
|---|---|---|
| Console | `identity.console_name` | Learned value |
| Model | `identity.model` | e.g. **X32** |
| Device | `identity.device_key` | Secondary line; monospace avoided — plain text profile key |

### Card B — Scene

| Label | Source field | Display |
|---|---|---|
| Scene | `identity.scene_number` | **Scene 02** |
| Scene name | `identity.scene_name` | Name or “Not captured yet” |
| Learned | `configuration.learned_at` | Relative + absolute timestamp, e.g. “Learned 12 Jun 2026, 14:32” |

### Card C — Sample Rate / Clock

| Label | Source field | Display |
|---|---|---|
| Sample rate | `globals.sample_rate` or `identity.sample_rate` | **48 kHz** / **44.1 kHz** / “Not captured yet” |
| Clock source | `globals.clock_source` or `identity.clock_source` | **Internal** / **AES50 A** / etc. / “Not captured yet” |

Prefer `globals.*` when both exist; they mirror identity for clock/sample rate.

### Card D — Learn Status

| Label | Source | Display |
|---|---|---|
| Learn source | `configuration.source` | **Live console** (`live_osc`) or **Preview data** (fixture / non-live) |
| Firmware | `globals.firmware` or `identity.firmware` | Version string or “Not captured yet” |
| Routing link | — | “Audio routing →” text link only |

### Unknown / not learned display rules (Identity row)

| `state` | Operator label |
|---|---|
| `learned` | Show formatted `value` |
| `not_learned` | **Not captured yet** |

Never show: `fixture_transport`, `info_query_not_implemented`, `desk_scene_name_unavailable`, or other reason codes.

For **Preview data** (fixture transport), identity cards still render but clock/firmware/scene name consistently show “Not captured yet” — do not imply live desk values.

---

## Section 3 — Channel Configuration Row

Primary visual section. Section title: **Channels (1–32)**.

### Layout

- 32 tiles in a single row on wide screens (horizontal scroll on smaller viewports), matching scribble-strip mental model from the physical X32.
- Each tile is **read-only** — no fader drag, no mute toggle.
- Tile width ~scribble strip; height shows name, colour band, and indicator chips.

### Per-tile content

| Element | Source field | Display rules |
|---|---|---|
| Channel number | `channels[n].number` | Top-left: **1** … **32** (not “CH 01”) |
| Channel name | `channels[n].name` | Prominent; if not learned or generic → **Unnamed** with muted styling |
| Colour | `channels[n].colour` | Left colour band using X32 colour index → palette mapping (reuse Overview palette) |
| Mute | `channels[n].mute` | Muted icon/badge when `value === true` |
| Fader level | `channels[n].fader` | Optional thin level bar (read-only); omit if not learned |
| Source reference | `channels[n].source_reference` | See mapping below |
| Gate | `channels[n].processing.gate_on` | **G** chip — active / inactive / unavailable |
| Compressor | `channels[n].processing.compressor_on` | **C** chip |
| EQ | `channels[n].processing.eq_on` | **EQ** chip |
| Main L/R | `channels[n].processing.main_lr` | **LR** chip |
| Stereo link | `channels[n].stereo_link` | **Link** badge on linked pairs (show on both channels) |
| DCA membership | `channels[n].dca_membership` | **DCA 1·3** style list, or “Not captured yet” |
| Icon | `channels[n].icon` | Optional small icon glyph when learned; omit slot when not captured |

### Source reference — operator mapping

Do **not** show JSON domains or OSC paths.

| Learned `value` shape | Operator label |
|---|---|
| Routing bank pointer (`source_type` + `source_range`) | `{Source type label} · {range}` — e.g. **AES50 A · A1–8**, **Local · 17–24**, **Card · 1–8** |
| Desk source index only | **Desk input · {index}** |
| Not learned | **Source not captured yet** |

Source type labels: map `aes50_a` → “AES50 A”, `aes50_b` → “AES50 B”, `card` → “Card”, `local` → “Local”, etc. (builder-owned map).

### Processing chip rules

| `state` | Chip appearance |
|---|---|
| `learned`, `value: true` | Filled / active |
| `learned`, `value: false` | Outline / off |
| `not_learned` | Greyed chip with tooltip “Not captured yet” (no “off” implication) |

### Tile anomaly highlights

Apply subtle border or warning dot when:

- Name is not learned or is generic (`CH 01` pattern)
- Source not captured on a channel that has a learned fader/name (likely in use)
- Mute learned `true` on named channel (informational, not error)

### Fields consumed

- `configuration.channels[]` — all per-channel fields listed above

---

## Section 4 — Bus / IEM Configuration Row

Section title: **Buses & Monitors (1–16)**.

### Layout

- 4 columns × 4 rows = 16 buses.
- Each cell is one bus line (not a fader strip).

### Per-bus line content

| Element | Source field | Display rules |
|---|---|---|
| Number | `buses[n].number` | **1** … **16** only — never prefix “BUS” |
| Name | `buses[n].name` | Bus name; if not learned → **Unnamed** |
| Mute | `buses[n].mute` | Muted indicator |
| Fader | `buses[n].fader` | Compact level bar or numeric shorthand (optional) |
| Stereo link | `buses[n].stereo_link` | **Linked** badge when learned true |
| Purpose | `buses[n].purpose` | When learned (inferred from name): show as subtle role tag, e.g. **Ed IEM** |
| Output assignment | `buses[n].output_assignment` | See mapping below |

### Output assignment — operator mapping

| Condition | Operator label |
|---|---|
| Learned routing pointer with `output_range` | **Out {range}** or formatted block label from routing normalized data |
| Not learned | **Output not resolved** |

Link text below grid: “Output patching is shown on the Audio Routing page →”

### Fields consumed

- `configuration.buses[]` — all per-bus fields listed above

---

## Section 5 — DCA / Matrix Row

Two equal-width cards side by side.

### Card A — DCAs (1–8)

Section title inside card: **DCAs**

Each DCA row:

| Element | Source field | Display |
|---|---|---|
| Number | `dcas[n].number` | **1** … **8** |
| Name | `dcas[n].name` | Name or **Unnamed** |
| Mute | `dcas[n].mute` | Muted indicator |
| Fader | `dcas[n].fader` | Level bar (optional) |
| Colour | `dcas[n].colour` | Small colour swatch when learned |
| Membership | `dcas[n].membership` | **Channels: 1, 2, 5** / **Buses: —** / “Not captured yet” |

Membership display (PH043.02 today): `membership` is often `not_learned`. Show honest gap copy — do not infer from channel DCA chips alone on this card (channel tiles may show partial membership when learned).

### Card B — Matrices (1–6)

Section title inside card: **Matrices**

Each matrix row:

| Element | Source field | Display |
|---|---|---|
| Number | `matrices[n].number` | **1** … **6** |
| Name | `matrices[n].name` | Name or **Unnamed** |
| Mute | `matrices[n].mute` | Muted indicator |
| Fader | `matrices[n].fader` | Level bar (optional) |
| Sources | `matrices[n].sources` | PH043.02: always not in scope — show **Sources not captured yet** |

### Fields consumed

- `configuration.dcas[]`
- `configuration.matrices[]`

---

## Section 6 — FX Row

Section title: **FX Processors**

### When FX not learned (PH043.02 default)

`configuration.fx.learned === false`

Display a single calm empty state:

> **FX inventory not learned yet**  
> Effect slots and returns have not been captured from the console. Re-learn from a live console after FX learn is enabled.

Do **not**:

- Invent slot names or effect types
- Copy fixture `fx[]` placeholders into this page
- Show empty slot grids implying “empty desk”

### When FX learned (future)

Grid of 8 slots (FX 1–8). Per slot:

| Element | Source (future) | Display |
|---|---|---|
| Slot number | `fx.slots[n].number` | **1** … **8** |
| Effect type | `fx.slots[n].type` | Type label |
| Effect name | `fx.slots[n].name` | Name or **Unnamed** |
| Role | `fx.slots[n].role` | e.g. **Reverb**, **Delay** |

Until implemented, treat all FX UI as gated on `fx.learned === true`.

### Fields consumed

- `configuration.fx.learned`
- `configuration.fx.reason` (internal only — drives empty state variant, not shown)
- `configuration.fx.slots[]` (future)

---

## Section 7 — Configuration Health / Gaps

Section title: **Configuration Health**

Closing summary answering: *What is incomplete or unusual?*

### Structure

Two sub-lists:

**A. Not captured yet** — expected gaps from PH043.02 learn scope  
**B. Needs attention** — anomalies that may indicate a problem

### A. Not captured yet (standard gaps)

Render only items whose fields are `not_learned` across the snapshot. Use operator copy:

| Internal gap | Operator message |
|---|---|
| `configuration.fx.learned === false` | FX processors not captured |
| `identity.firmware` / `globals.firmware` not learned | Console firmware not captured |
| `globals.sample_rate` not learned | Sample rate not captured |
| `globals.clock_source` not learned | Clock source not captured |
| `identity.scene_name` not learned (live) | Scene name not captured from desk |
| Channel `icon` predominantly not learned | Channel icons not captured |
| Channel `dca_membership` not learned | Channel DCA assignments not captured |
| Bus `stereo_link` not learned | Bus link state not captured |
| DCA `membership` not learned | DCA group membership not captured |
| Matrix `sources` not learned | Matrix source configuration not captured |

If no gaps: show “All available configuration areas were captured.”

### B. Needs attention (anomalies)

| Condition | Operator message |
|---|---|
| Named channel count below threshold | Few channel names captured — verify scene recall |
| Many generic bus names | Several buses still have default names |
| Scene name missing on live learn | Scene name unavailable — confirm show file on desk |
| `configuration.warnings[]` non-empty | Surface sanitized warning strings (strip transport jargon) |
| Muted + named channels count unusually high | Many named channels are muted — confirm intentional |

Anomaly list empty state: “No unusual configuration patterns detected.”

### Configuration status badge linkage

| Badge | Rule |
|---|---|
| **Needs attention** | Any anomaly in list B, OR scene name missing on live learn, OR >8 unnamed active channels |
| **Partial** | Default when learn succeeded but list A has items |
| **Complete** | List A empty AND `fx.learned` AND firmware learned AND no anomalies (future-ready) |

### Fields consumed

- All `configuration.*` field `state` values
- `configuration.warnings[]`
- `configuration.fx.learned`
- Derived counts from channels/buses/dcas/matrices arrays

---

## Unknown / Not Learned Display Rules (Global)

These rules apply to every section:

1. **Never imply a value is known when `state !== 'learned'`.**
2. **Default copy:** “Not captured yet” for missing scalar fields.
3. **Reference fields:** “Not resolved” / “Source not captured yet” / “Output not resolved” where context-specific copy is clearer.
4. **Do not show** internal `reason` codes, JSON keys, OSC paths, or storage layer names (`baseline_json`, `learned_summary_json`, `configuration_capture`).
5. **Do not show** raw routing JSON — only denormalized operator labels from routing pointers.
6. **Generic desk names** (`CH 01`, `Bus 01`, `DCA 1`, `MTRX 1`) display as **Unnamed** — same rule as PH043.02 assembler generic-name detection.
7. **Preview / fixture data:** show **Preview data** badge in Learn Status card; do not present fixture values as live desk truth.
8. **Boolean fields:** `not_learned` ≠ `false`. Use unavailable chip styling, not “off”.
9. **Empty arrays** with `learned` state: show “None” (e.g. DCA membership learned as empty list).

---

## Builder & View Architecture (PH043.03B — Not Implemented Here)

Proposed backend surface (for implementation planning only):

| Component | Responsibility |
|---|---|
| `X32ConfigurationWorkspaceBuilder` | Denormalize `configuration` → view model; compute status badge and health lists |
| `ConsoleController::configuration` | Resolve active baseline or preview snapshot; render page |
| `console/configuration.blade.php` | Read-only layout per this design |
| `_configuration-*.blade.php` partials | Header, identity cards, channel tiles, bus grid, DCA/matrix, FX, health |

Builder inputs: `ShowConsoleWorkspaceResolver` output + `baseline_json.configuration` or preview equivalent.

Builder must **not** read `routing` except to resolve pointer labels already embedded in `source_reference` and `output_assignment` values.

---

## Suggested Implementation Phases (PH043.03B)

### Phase B1 — Shell & identity

- Add Configuration tab and route
- Header row + learned context line
- Configuration status badge (partial logic OK)
- Console Identity row (4 cards)
- Feature tests: page loads, shows scene/console, no write controls

### Phase B2 — Channel grid

- 32 read-only scribble tiles
- Name, colour, mute, processing chips, source reference labels
- DCA membership when learned
- Tests: learned vs not_learned chip behaviour, generic name → Unnamed

### Phase B3 — Bus grid

- 4×4 bus lines with mute, fader, link, purpose, output assignment
- Output “not resolved” copy
- Link to Routing page

### Phase B4 — DCA & Matrix cards

- 8 DCA rows + 6 matrix rows
- Honest membership/sources gap copy

### Phase B5 — FX & Configuration Health

- FX empty state (not learned)
- Health / gaps section
- Full status badge rules
- Integration tests against fixture + mocked live configuration payloads

### Phase B6 — Polish & parity

- Responsive tile scrolling
- Align colour palette with Overview strips
- Accessibility pass (tile labels, status badges)
- Cross-link from Routing header (“View configuration →”) — optional, read-only link

---

## Out of Scope (PH043.03)

- Configuration editing or sync-to-console
- Routing editing or connectivity monitoring on this page
- FX slot display when not learned (no placeholders)
- Admin raw JSON / `configuration_capture` viewer
- Scene execution, show execution, MIDI/OSC writes
- Migrations (existing JSON columns sufficient)

---

## Success Criteria

PH043.03A design is satisfied when an operator can mock-review this document and confirm:

- [ ] Scene and console identity are immediately visible in the header
- [ ] Channel configuration is scannable in one row without opening menus
- [ ] Bus/IEM structure is readable without routing editor noise
- [ ] DCA and matrix state is visible with honest gaps
- [ ] FX absence is explicit, not faked
- [ ] Incomplete and unusual items are summarized at the bottom
- [ ] Routing and connectivity remain separate domains

---

## Confirmation

| Check | Result |
|---|---|
| Application code modified | **No** |
| Blade / CSS / tests modified | **No** |
| Commits made | **No** |
| Deliverable | `docs/x32/PH043_CONFIGURATION_WORKSPACE_UI_DESIGN.md` |

---

## Rollback Notes

Documentation only. Delete `docs/x32/PH043_CONFIGURATION_WORKSPACE_UI_DESIGN.md` to revert this design deliverable.
