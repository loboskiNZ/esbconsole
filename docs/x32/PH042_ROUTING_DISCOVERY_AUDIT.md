# PH042.01A — X32 Routing Discovery Audit

**Status:** Audit complete (read-only)  
**Date:** 2026-06-16  
**Authority:** `docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md`  
**Scope:** PH042.01A audit only — no application code changes

---

## Executive Summary

The Routing workspace (PH041) is **UI-complete but data-incomplete**. The operator-facing shell, flow map, configuration detail row, and workflow action row are implemented and consume data from `X32RoutingWorkspaceBuilder`. However, **no X32 audio routing table is read from the console today**.

Current Learn From Console captures **mixer state** (channel fader/mute/name/colour/controls, bus/DCA/matrix faders) over OSC. The `routing` key in learned summaries is either a **fixture placeholder** (fake transport) or a **transport metadata stub** (live OSC). Neither path queries `/config/routing/*` or any other routing-table OSC address.

`X32OscAddressMap` and `X32InputChannelControlMap` define OSC paths for **channel processing and mix assignment** (`/ch/NN/mix/st` for Main L/R send), not for **input bank selection**, **Out 1–16 source assignment**, **XLR/AES50/Card/P16/User routing**, or **output patchbay tables**.

The UI correctly distinguishes **Suggested** vs **Learned** state for most zones, but today almost all routing zones render **Suggested** or **Not learned** scaffolds because the baseline lacks real routing data. The only routinely present learned routing fragment is fixture `main_lr: { left: BUS 15, right: BUS 16 }`, which surfaces as **partial FOH** — not full XLR output routing.

**Bottom line:** PH042.02 (OSC address audit) and PH042.03 (read-only routing learn expansion) are blockers before the Routing UI can show real learned routing. No routing write/sync should proceed until read-only learning is verified.

---

## Audit Matrix

| Routing Domain | Supported? | OSC Path Known? | Read From Console? | Stored In Baseline? | Used By UI? | Notes |
|---|---|---|---|---|---|---|
| **Input Banks** (IN 1–8, 9–16, 17–24, 25–32) | Partial | **No** | **No** | **No** | Yes | Builder consumes `routing.input_banks[]` with `bank`, `source_type`, `source_range`. No OSC paths defined. Contract example `/config/routing/IN/4` is illustrative only — not in code. |
| **Aux In / Aux Remap** | Missing | **No** | **No** | **No** | No | Not referenced in services, OSC maps, or UI builder. |
| **Local Inputs** | Missing | **No** | **No** | **No** | No | No local-input routing domain in learn or builder. |
| **AES50A Inputs** | Partial | **No** | **No** | **No** | Yes | UI infers Stagebox A from `input_banks` entries where `source_type` contains `AES50A`, or from `routing.stagebox_a`. No direct AES50-A input table read. |
| **AES50B Inputs** | Partial | **No** | **No** | **No** | Yes | Same as AES50A; Stagebox B inferred from `AES50B` in `input_banks` or `routing.stagebox_b`. |
| **Card / USB Inputs** | Partial | **No** | **No** | **No** | Yes | Builder supports `routing.ableton`, `routing.usb_card.inputs`. Template catalog provides suggested Ableton returns (CH 25–32). No Card/USB input bank OSC read. |
| **Out 1–16** | Missing | **No** | **No** | **No** | Partial | Advanced routing chip label only (`out_1_16` category). Builder `buildAdvanced()` expects `routing.advanced.out_1_16` — never populated by learn. |
| **Aux Out** | Missing | **No** | **No** | **No** | Partial | Advanced chip only (`aux_out`). No read or normalized model. |
| **User In** | Missing | **No** | **No** | **No** | No | Not in builder categories or learn. |
| **User Out** | Missing | **No** | **No** | **No** | Partial | Advanced chip only (`user_out`). No read. |
| **AES50A Outputs** | Partial | **No** | **No** | **No** | Partial | Builder `routing.aes50a.output_banks[]` supported. Not populated by learn. |
| **AES50B Outputs** | Partial | **No** | **No** | **No** | Partial | Builder `routing.aes50b.output_banks[]` supported. Not populated by learn. |
| **Card / USB Outputs** | Partial | **No** | **No** | **No** | Partial | Builder `routing.usb_card.outputs[]` supported. Not populated by learn. |
| **P16 / Ultranet** | Missing | **No** | **No** | **No** | Partial | Advanced chip only (`p16_ultranet`). No read. |
| **XLR Output Assignment** | Partial | **No** | **No** | **No** | Yes | Builder reads `routing.xlr_outputs[]` (`number`, `type`, `assignment`). Spare-output scan uses XLR 1–16. Never populated by learn. |
| **Main LR routing** | Partial | Partial | Partial | Partial | Yes | `/ch/NN/mix/st` read per channel (Main send on/off) — **not** Main L/R output patch routing. Fixture `routing.main_lr` hardcoded as `{ left: BUS 15, right: BUS 16 }`. UI shows partial FOH source labels. Out→XLR trace not implemented. |
| **Bus / IEM routing** | Partial | Partial | Partial | Partial | Yes | Bus **names/faders** learned via `/bus/NN/*`. IEM UI expects `routing.iem_mixes[]` (name, bus, output) — not derived from bus learn. Template placeholders used when absent. |
| **Matrix routing** | Partial | Partial | Partial | Partial | No (routing UI) | Matrix **faders/mute** read via `/mtx/NN/mix/*` for mixer workspace only. No matrix output-source routing in routing model or UI. |

**Legend:**  
- **Supported?** — application can represent/consume the domain today  
- **OSC Path Known?** — path exists in codebase address maps (not guessed from X32 firmware)  
- **Read From Console?** — live or fixture learn actually queries and captures the domain  
- **Stored In Baseline?** — persists in `baseline_json` / `learned_summary_json` after learn  
- **Used By UI?** — Routing workspace (PH041) renders the domain

---

## Code References / Files Inspected

### Contract & governance
- `docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md`

### OSC clients & transport
- `backend/app/Contracts/X32/X32ConsoleSnapshotReaderInterface.php`
- `backend/app/Contracts/X32/X32OscConsoleClientInterface.php`
- `backend/app/Services/X32/FakeX32OscConsoleClient.php` — generic float/int/string query; no routing paths
- `backend/app/Services/X32/OscUdpX32OscConsoleClient.php` — UDP send/query transport
- `backend/app/Services/X32/RoutingX32ConsoleSnapshotReader.php` — fixture vs live routing
- `backend/app/Providers/AppServiceProvider.php` — OSC client/snapshot reader bindings

### OSC address definitions
- `backend/app/Services/X32/X32OscAddressMap.php` — channel/bus/DCA/matrix/scene recall; **no `/config/routing`**
- `backend/app/Services/X32/X32InputChannelControlMap.php` — channel controls incl. `/ch/NN/mix/st`
- `backend/app/Services/X32/X32OscMessageCodec.php`
- `backend/app/Services/X32/X32OscSceneRecallPacketBuilder.php` — `/-action/goscene`

### Learning & baseline
- `backend/app/Services/X32/FakeX32ConsoleSnapshotReader.php` — fixture learn; `buildRouting()` placeholder
- `backend/app/Services/X32/OscUdpX32ConsoleSnapshotReader.php` — live learn; routing stub + warning
- `backend/app/Services/Console/X32ConsoleLearningService.php`
- `backend/app/Services/Console/ShowConsoleBaselineService.php`
- `backend/app/Services/Console/ShowConsoleWorkspaceResolver.php`
- `backend/database/migrations/2026_06_23_100000_create_console_learning_tables.php`
- `backend/app/Models/ConsoleLearningSnapshot.php`
- `backend/app/Models/ShowConsoleBaseline.php`

### Routing workspace (UI data layer)
- `backend/app/Services/Console/X32RoutingWorkspaceBuilder.php` — operator view builder; routing key consumer
- `backend/app/Services/Console/X32RoutingTemplateCatalog.php` — suggested scaffolds (not learned)
- `backend/app/Http/Controllers/ConsoleController.php` — `routingForShow()`
- `backend/resources/views/console/routing.blade.php`
- `backend/resources/views/console/_routing-flow-source-card.blade.php`
- `backend/resources/views/console/_routing-detail-row.blade.php`
- `backend/resources/views/console/_routing-bottom-row.blade.php`

### Tests & fixtures
- `backend/tests/Feature/ConsoleRoutingTest.php`
- `backend/tests/Feature/ConsoleLearningTest.php`
- `backend/tests/Unit/X32RoutingWorkspaceBuilderTest.php`
- `backend/tests/Unit/OscUdpX32ConsoleSnapshotReaderTest.php`
- `backend/tests/Unit/FakeX32ConsoleSnapshotReaderTest.php`
- `backend/tests/Unit/X32OscAddressMapTest.php`

---

## Existing Capabilities

### 1. Routing workspace UI (PH041) — complete scaffold
- Shell, flow row (Sources → Console → Destinations), configuration detail row, bottom workflow row
- Connection-first input source cards, channel allocation strip, outputs summary (FOH / IEMs / spares)
- Workspace-level Learned / Suggested / Not learned badges
- Learn action links to `shows.console.learn`; other workflow steps disabled

### 2. Routing data builder — ready to consume learned routing
`X32RoutingWorkspaceBuilder` already defines normalized keys the contract expects:

| Key | Purpose |
|---|---|
| `routing.input_banks[]` | 8-channel input bank source assignment |
| `routing.stagebox_a`, `routing.stagebox_b` | Operator stagebox zones |
| `routing.ableton`, `routing.usb_card` | Card/USB / Ableton returns |
| `routing.channel_sources[NN]` | Per-channel source attribution |
| `routing.main_lr`, `routing.foh` | FOH source/output interpretation |
| `routing.iem_mixes[]` | IEM mix name/bus/output |
| `routing.xlr_outputs[]` | Physical XLR assignments |
| `routing.aes50a`, `routing.aes50b` | AES50 output banks |
| `routing.advanced.*` | Advanced routing category summaries |

When keys are absent, builder falls back to **Suggested** templates from `X32RoutingTemplateCatalog` without faking learned state.

### 3. Console learn — mixer state only
Both transports successfully learn:
- 32 input channels (fader, mute, name, colour, gate/dyn/eq/pan/main_lr controls)
- 16 buses (fader, mute, name, colour)
- 8 DCAs, 6 matrices (fader, mute)
- Scene recall via `/-action/goscene`
- Raw OSC responses stored in `raw_snapshot_json.osc_responses`

### 4. Baseline persistence
Learned data stored as JSON:
- **`console_learning_snapshots.learned_summary_json`** — full summary including `routing` key
- **`console_learning_snapshots.raw_snapshot_json`** — raw OSC envelope
- **`show_console_baselines.baseline_json`** — copy of summary at save time

No dedicated routing table or migration exists; routing lives inside the summary blob.

### 5. OSC infrastructure reusable for routing reads
- `X32OscConsoleClientInterface` query methods (float/int/string)
- xremote refresh pattern in `OscUdpX32ConsoleSnapshotReader`
- Fake OSC client for tests
- `services.console_learn.osc_debug` logging hook

---

## Gaps

### Critical — no routing table OSC coverage
- **Zero** `/config/routing/*` paths in codebase (confirmed by repository search)
- No routing-specific reader service or normalizer
- Live learn explicitly warns: *"FX slot and routing detail reads are not yet implemented."*

### Learn output vs contract shape
Current `routing` object (fixture):

```json
{
  "source": "fixture",
  "note": "Raw routing data captured for future interpretation.",
  "main_lr": { "left": "BUS 15", "right": "BUS 16" },
  "device_key": "..."
}
```

Current `routing` object (live OSC):

```json
{
  "source": "live_osc",
  "host": "...",
  "port": 10023,
  "scene_recalled": 1
}
```

Contract minimum shape (`input_banks`, `local_inputs`, `aes50_*`, `out_1_16`, `xlr_outputs`, `raw_osc`, `derived_operator_view`, etc.) is **not produced**.

### Domains with no code path at all
- Aux In / Aux Remap
- Local Inputs (as routing sources)
- User In
- P16 / Ultranet (beyond UI chip label)
- Out 1–16 intermediate bank reads
- Aux Out reads

### Confusion risk — mix controls vs routing tables
| OSC path | What it is | What it is NOT |
|---|---|---|
| `/ch/NN/mix/st` | Channel → Main L/R **send** toggle | Input bank source for channel N |
| `/bus/NN/mix/fader` | Bus level | Bus → XLR/AES50 output assignment |
| `/mtx/NN/mix/fader` | Matrix level | Matrix routing table |

These are learned today but must not be mistaken for PH042 routing discovery.

### UI still mostly scaffold
- Flow row source cards show **Suggested** channel ranges (CH01–16 / CH17–24 / CH25–32) unless `input_banks` present
- Input detail cards show **Expected** connection status unless learned keys exist
- IEM mixes use template names (Ed IEM, Guitar IEM, …) when `iem_mixes` absent
- Advanced X32 Routing entry disabled; categories are labels only

---

## OSC Paths — Known vs Unknown

### Known in codebase (implemented & used in learn/control)

| OSC path pattern | Used for | Defined in |
|---|---|---|
| `/-action/goscene` | Scene recall | `X32OscAddressMap::sceneRecall()` |
| `/xremote` | Subscription refresh | `X32OscMessageCodec::buildXremote()` |
| `/ch/{01-32}/mix/fader` | Channel fader | `X32OscAddressMap`, `X32InputChannelControlMap` |
| `/ch/{01-32}/mix/on` | Channel mute (inverted) | same |
| `/ch/{01-32}/mix/pan` | Channel pan | same |
| `/ch/{01-32}/mix/st` | Main L/R send | same (`main_lr` control) |
| `/ch/{01-32}/config/name` | Channel name | `X32OscAddressMap` |
| `/ch/{01-32}/config/color` | Channel colour | same |
| `/ch/{01-32}/gate/on` | Gate on | `X32InputChannelControlMap` |
| `/ch/{01-32}/dyn/on` | Compressor on | same |
| `/ch/{01-32}/eq/on` | EQ on | same |
| `/bus/{01-16}/mix/fader`, `/mix/on` | Bus level/mute | `X32OscAddressMap` |
| `/bus/{01-16}/config/name`, `/config/color` | Bus label/colour | same |
| `/dca/{1-8}/fader`, `/on` | DCA level/mute | same |
| `/mtx/{01-06}/mix/fader`, `/mix/on` | Matrix level/mute | same |
| `/fxrtn/{01-08}/mix/fader` | FX return level | `X32OscAddressMap` (defined, **not read in learn**) |

### Assumed / unknown — do not implement without PH042.02 verification

The contract lists these domains but **no OSC paths are verified in this repository**:

| Domain | Contract reference | Codebase status |
|---|---|---|
| Input bank 1–4 | Illustrative `/config/routing/IN/4` | **Unknown — do not guess** |
| Aux In / Aux Remap | §1 Input Routing Banks | **Unknown** |
| Local input routing | §2 Local XLR Inputs | **Unknown** |
| AES50-A/B input routing | §3–4 | **Unknown** |
| Card/USB input routing | §5 | **Unknown** |
| Out 1–16 source assignment | §6 | **Unknown** |
| Local XLR output assignment | §7 | **Unknown** |
| AES50-A/B output routing | §8–9 | **Unknown** |
| Card/USB output routing | §10 | **Unknown** |
| Aux Out | §11 | **Unknown** |
| P16 / Ultranet | §12 | **Unknown** |
| User In / User Out | §13 | **Unknown** |
| Bus/Main/Matrix **output-source** routing | §14 | **Unknown** (mix paths ≠ routing tables) |

**Blocker:** PH042.02 must document verified paths from X32 OSC documentation and/or live desk probing before any read implementation.

---

## Where Learned Routing Data Is Stored

```
Learn From Console
       │
       ▼
ConsoleLearningSnapshot
  ├── learned_summary_json
  │     ├── channels[]      ← OSC read (mixer)
  │     ├── buses[]         ← OSC read (mixer)
  │     ├── dcas[]          ← OSC read (mixer)
  │     ├── matrices[]      ← OSC read (mixer)
  │     └── routing{}       ← stub/placeholder only today
  └── raw_snapshot_json
        └── osc_responses[] ← mixer OSC only today

Save Baseline
       │
       ▼
ShowConsoleBaseline
  └── baseline_json         ← copy of learned_summary_json

Routing UI (shows.console.routing)
       │
       ▼
ShowConsoleWorkspaceResolver → summary
       │
       ▼
X32RoutingWorkspaceBuilder → routingFlow, routingDetail, routingBottom
```

Channel **names** from `channels[]` feed the allocation strip labels. Routing zone state comes from `summary.routing.*` keys, which are largely empty.

---

## What the Routing UI Currently Consumes

| UI section | Data source | Learned today? |
|---|---|---|
| Header routing state badge | `routing` key presence + input/output detail flags | Partial (fixture `main_lr` only) |
| Flow row — Stagebox A/B/Ableton | `input_banks`, `stagebox_*`, templates | Suggested defaults |
| Flow row — Console Channels | Always CH01–CH32 | N/A (structural) |
| Flow row — FOH / IEMs | `main_lr`, `foh`, `iem_mixes`, templates | Partial FOH; suggested IEMs |
| Detail — production config | baseline name, routing state grid | Baseline metadata only |
| Detail — input connection cards | `stagebox_*`, `ableton` learned keys | Expected/Suggested |
| Detail — channel allocation strip | `channel_sources`, `channels[].name`, zone heuristics | Channel names yes; sources mostly not learned |
| Detail — FOH outputs | `foh`, `main_lr`, `xlr_outputs` | Partial (`main_lr` fixture) |
| Detail — IEM mixes | `iem_mixes` or template catalog | Template placeholders |
| Bottom — workflow actions | Static; Learn URL only | N/A |
| Bottom — advanced categories | `routing.advanced.*` | Not learned |

---

## What Must Be Added Before UI Shows Real Learned Routing

1. **PH042.02 — OSC address audit** (separate deliverable)  
   Verify and document all `/config/routing/*` paths per X32 firmware; mark uncertain paths explicitly.

2. **Routing OSC read module** (PH042.03)  
   Read-only queries for each verified domain; preserve `raw_osc[]` alongside normalized entries.

3. **Routing normalizer**  
   Map raw X32 strings (e.g. `AES50A 1-8`, `CARD 1-8`) → contract normalized model (`input_banks`, `aes50a`, etc.).

4. **Learn pipeline integration**  
   Extend `OscUdpX32ConsoleSnapshotReader` and `FakeX32ConsoleSnapshotReader` to populate full `routing` object; remove fixture fake `main_lr` unless sourced from real reads.

5. **Operator derivation layer**  
   Build `derived_operator_view` (Stagebox A/B, Ableton, FOH, IEMs) from raw tables — second pass, not first.

6. **Baseline shape stability**  
   Ensure saved `baseline_json.routing` matches builder expectations; add tests with real-shaped routing fixtures.

7. **PH042.04 UI activation**  
   Once learned data exists, builder will automatically prefer learned over suggested for most zones; verify badge logic and remove reliance on templates where learned data present.

**Not required for first real learned display:** edit model, preview diff, sync/write, migrations (unless routing blob size warrants separate column — not justified yet).

---

## Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Guessing OSC paths | **High** | PH042.02 audit before any reads; contract Rule 1 |
| Confusing `/ch/NN/mix/st` with input routing | **High** | Document in normalizer; separate domains in stored JSON |
| Fixture `main_lr` reported as learned routing | **Medium** | Replace with real reads; mark source provenance (`learned: true/false` per field) |
| Suggested scaffold mistaken for learned in ops | **Medium** | UI already labels Suggested/Expected; enforce in learn pipeline |
| Learn query volume / xremote timeout | **Medium** | Batch routing reads; reuse xremote refresh pattern |
| Routing learn breaks live fader control | **High** | Contract Rule 8 — isolated read phase; no writes |
| X32 vs M32 routing path differences | **Medium** | Verify per console type in PH042.02 |
| User In/User Out firmware variance | **Medium** | Feature-detect; store warnings |

---

## Recommended PH042 Implementation Sequence

Aligned with contract roadmap:

| Phase | ID | Deliverable | Depends on |
|---|---|---|---|
| 1 | **PH042.01A** | This audit | Contract |
| 2 | **PH042.02** | `PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md` — verified OSC paths only | This audit |
| 3 | **PH042.03** | Read-only routing learn expansion | PH042.02 |
| 4 | **PH042.03 tests** | Fixture + live-shaped routing tests; raw OSC preservation | PH042.03 |
| 5 | **PH042.04** | Populate Routing UI from learned routing | PH042.03 |
| 6 | **PH042.05** | Software-side edit model | PH042.04 |
| 7 | **PH042.06** | Preview diff | PH042.05 |
| 8 | **PH042.07** | Sync to console (write) | PH042.06 |

**Suggested PH042.03 read order (after OSC audit):**
1. Input banks (IN 1–4) — unlocks Stagebox A/B/Ableton inference  
2. Out 1–16 — intermediate layer for output tracing  
3. XLR outputs — FOH/IEM physical assignments  
4. AES50-A/B outputs — stagebox send side  
5. Card/USB in/out — Ableton round-trip  
6. Aux Out, P16, User In/Out — as verified available  
7. Derived operator view — last

---

## No-Code-Change Confirmation

This audit was **documentation only**.

- No PHP services, views, CSS, routes, tests, migrations, or config were modified.
- No routing reads, writes, or UI changes were implemented.
- No git commit was created.
- The only file created: `docs/x32/PH042_ROUTING_DISCOVERY_AUDIT.md`

---

## Related Documents

- `docs/x32/PH042_X32_ROUTING_DISCOVERY_CONTRACT.md` — authoritative routing discovery contract
- `docs/x32/PH042_X32_ROUTING_OSC_ADDRESS_AUDIT.md` — **not yet created** (PH042.02 next step)
