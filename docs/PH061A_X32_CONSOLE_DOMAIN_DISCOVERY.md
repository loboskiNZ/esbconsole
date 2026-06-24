# PH061A — X32/M32 Console Domain Discovery and Classification

Status: Domain modelling only — **no code, migrations, DDL, deploys, production commands, or schema changes**  
Authority: PH059 CCMM, PH061 recovery plan, `docs/x32/DECISION_LOG.md`, `docs/DOMAIN_MODEL.md`  
Date: 2026-06-24

---

## 1. Purpose

PH061A discovers and classifies the **X32/M32 Console Domain** at business/domain level to determine what must join the Cloud Canonical Migration Manifest (CCMM).

**Core finding:** Current CCMM models the **production platform** (songs, shows, people) but not the **console configuration and effects package domain** implemented in `/backend/` and documented in PH043–PH044. Much console state is **document-aggregated JSON** (baselines), not normalized relational tables.

---

## 2. Domain boundaries (governance)

| Domain | Question | Persistence pattern today |
|--------|----------|---------------------------|
| **Routing (PH042)** | Where does audio go? | Learned JSON in `baseline_json` / routing workspace |
| **Configuration (PH043)** | How is the console configured? | Learned JSON under `configuration.*` |
| **Connectivity** | Is hardware connected? | `integration_devices` + runtime OSC |
| **Effects packages (PH044)** | What musical FX packages apply? | Relational: packages, definitions, song assignments |
| **Learning** | What was captured from desk? | `console_learning_snapshots` → promoted to `show_console_baselines` |
| **Runtime** | What is the desk doing now? | OSC read/write; not canonical DB rows |

**Name collision warning:** Platform **`snippets`** (PH027 music chart crops) ≠ X32 **console snippets** (scene recall artefacts). CCMM `snippets` table is music domain only.

---

## 3. X32 domain inventory

### 3.1 Implemented persistence (Live Stage Database today)

| Table / artefact | Domain area | Model |
|------------------|-------------|-------|
| `effect_definitions` | Effects | Package member identity |
| `effect_packages` | Effects | Named musical packages |
| `effect_package_items` | Effects | Package membership |
| `song_effect_assignments` | Effects | Song ↔ package intent |
| `effects` | Effects reference | X32 algorithm catalogue |
| `effect_parameters` | Effects reference | Algorithm parameters |
| `effect_package_types` | Effects reference | Package type enum |
| `effect_library_items` | Effects reference | Legacy library bridge |
| `effect_library_parameters` | Effects reference | Legacy |
| `effect_package_item_parameters` | Effects | Item parameter overrides |
| `effect_package_item_target_sections` | Effects | Routing section metadata |
| `show_console_baselines` | Show configuration | Show-scoped console baseline document |
| `console_learning_snapshots` | Learning | Ephemeral learn capture |
| `integration_devices` | Connectivity | X32/console network identity |
| `integration_connection_profiles` | Connectivity | OSC/MIDI connection profiles |
| `performance_device_assignments` | Performance | Show-day device binding |

### 3.2 Documented / conceptual (no dedicated table)

| Concept | Where represented |
|---------|-------------------|
| `consoles` | Partially `integration_devices`; identity in `baseline_json.configuration.identity` |
| `console_models` | `configuration.identity.console_type` / `console_type` on baseline |
| `firmware_versions` | `configuration.identity.firmware` (learned JSON) |
| `channels`, `buses`, `matrices`, `DCAs` | `configuration.channels[]`, `configuration.buses[]`, etc. |
| `routing` | `learned_summary_json.routing` / PH042 model |
| `monitor_mixes` | Bus workspace + send matrix in configuration JSON |
| `mute_groups`, `preamps`, `scribble_strips` | PH043 contract; mostly learned JSON |
| `mix_moves` | **DOMAIN_MODEL** — planned M5; **not migrated** |
| `light_modes` | Planned M5; not in scope here |
| X32 `scenes` | Scene recall via OSC; `identity.scene_number` in JSON |
| X32 `snippets` | Fallback recall names on `song_effect_assignments` only |
| `console_templates` | Conceptual; overlaps `effect_packages` (permanent) + baselines |
| `learned_configurations` | `console_learning_snapshots` + `baseline_json` |

### 3.3 Runtime / ephemeral (not persisted as canonical rows)

| Concept | Handling |
|---------|----------|
| `live_fader_state` | OSC read/write; optional runtime cache |
| `live_meter_state` | OSC metering; not stored |
| `live_mute_state` | OSC; channel/bus/send writes |
| `live_connection_state` | Bridge health; `integration_devices.connection_status` |
| `live_transport_state` | Ableton authority — outside X32 DB |
| `live_heartbeat_state` | Bridge telemetry; runtime only |

---

## 4. Entity classification matrix

| Entity | Classification | Rationale |
|--------|----------------|-----------|
| **consoles** (logical desk) | Operator Decision Required | Model as `integration_devices` vs new `consoles` registry |
| **console_models** | Cloud Only (reference) | Stable catalogue; seed on Cloud |
| **firmware_versions** | Runtime Only | Point-in-time learn; not canonical row |
| **console_profiles** | CCMM Shared Entity (future) | Reusable prep templates across shows |
| **console_capabilities** | Cloud Only (reference) | OSC/feature matrix for UI gating |
| **channels** | Legacy / Deprecated (as tables) | Use JSON inside `show_console_baselines`; PH043 learn model |
| **buses** | Legacy / Deprecated (as tables) | Bus workspace JSON; X32-DEC-001 |
| **matrices** | Legacy / Deprecated (as tables) | JSON in baseline |
| **DCAs** | Legacy / Deprecated (as tables) | JSON in baseline |
| **mute_groups** | Legacy / Deprecated (as tables) | JSON or future extension |
| **monitor_mixes** | Legacy / Deprecated (as tables) | Send matrix in configuration JSON |
| **routing** | Legacy / Deprecated (as tables) | PH042 JSON document; not normalized |
| **preamps** | Legacy / Deprecated (as tables) | Channel config JSON |
| **scribble_strips** | Legacy / Deprecated (as tables) | Channel metadata JSON |
| **effect_types** | Cloud Only (reference) | `action_types` + `effect_package_types` |
| **effect_definitions** | **CCMM Shared Entity** | Master library; song-linked via packages |
| **effect_packages** | **CCMM Shared Entity** | Show/song-aware packages (X32-DEC-006/007) |
| **effect_chains** | Legacy / Deprecated | Represented by `effect_package_items` ordering |
| **effect_assignments** | **CCMM Shared Entity** | `song_effect_assignments` |
| **effect_parameters** | Cloud Only (reference) | `effects`/`effect_parameters` catalogue |
| **inserts** | Legacy / Deprecated | FX slot deployment — runtime allocation plan |
| **console_baselines** | **CCMM Shared Entity** | `show_console_baselines` — show prep asset |
| **show_console_baselines** | **CCMM Shared Entity** | Must survive backup/restore for show prep |
| **console_templates** | Operator Decision Required | Merge with baselines vs separate table |
| **scenes (X32)** | Live Stage Only (operational) | Recall trigger; identity in baseline JSON |
| **snippets (X32)** | Live Stage Only | Console recall; not music `snippets` |
| **scene_elements** | Runtime Only | Transient recall composition |
| **snippet_elements** | Runtime Only | Transient |
| **learned_configurations** | Live Stage Only | `console_learning_snapshots` — pre-baseline |
| **learned_channels** | Live Stage Only | JSON in snapshot |
| **learned_routing** | Live Stage Only | JSON in snapshot |
| **learned_effects** | Live Stage Only | JSON until packaged |
| **learned_console_profiles** | Live Stage Only | Pre-promotion learn |
| **performance_console_assignments** | Operator Decision Required | Not implemented; may map to performance + device |
| **console_device_assignments** | Live Stage Only | `performance_device_assignments` |
| **integration_devices** | Live Stage Only | Show-day connectivity (PH059 Part B) |
| **integration_connection_profiles** | Live Stage Only | Bridge config |
| **live_fader_state** | Runtime Only | |
| **live_meter_state** | Runtime Only | |
| **live_mute_state** | Runtime Only | |
| **live_connection_state** | Runtime Only | |
| **live_transport_state** | Runtime Only | Ableton domain |
| **live_heartbeat_state** | Runtime Only | |
| **mix_moves** | **CCMM Shared Entity** (planned) | DOMAIN_MODEL master library; cue Actions |
| **effects** (algorithm ref) | Cloud Only (reference) | Catalogue seed — like `instrument_reference` |
| **music snippets** | **CCMM Shared Entity** (existing) | PH027 — not X32 |

---

## 5. Cloud vs Live Stage boundaries

### 5.1 Belongs in CCMM (Cloud + Live Stage parity)

Must survive backup, restore, replication, show preparation, rehearsal planning:

| CCMM expansion entity | Depends on |
|-----------------------|------------|
| `effect_definitions` | — |
| `effect_packages` | — |
| `effect_package_items` | packages, definitions |
| `song_effect_assignments` | songs, packages |
| `effects` | reference catalogue |
| `effect_parameters` | effects |
| `effect_package_types` | reference |
| `effect_package_item_parameters` | items |
| `effect_package_item_target_sections` | items |
| `show_console_baselines` | shows, bands |
| `mix_moves` (when implemented) | bands |
| `x32_console_models` (proposed reference) | optional CCMM-12 |
| `x32_console_capabilities` (proposed reference) | optional |

**JSON-in-row pattern:** Channel/bus/routing **configuration** remains inside `show_console_baselines.baseline_json` — **not** normalized CCMM tables (PH061A decision).

### 5.2 Cloud reference only (seed on Cloud; replicated read-only)

| Entity | Notes |
|--------|-------|
| `effects`, `effect_parameters` | Maillot-verified algorithm catalogue |
| `effect_package_types` | Enum reference |
| `x32_console_models` | Proposed |
| `x32_console_capabilities` | Proposed |

### 5.3 Live Stage only

| Entity | Why |
|--------|-----|
| `console_learning_snapshots` | Ephemeral learn; promote to baseline for durability |
| `integration_devices` | Show-day connection |
| `integration_connection_profiles` | Bridge endpoints |
| `performance_device_assignments` | Performance runtime binding |
| X32 scene recall operations | OSC commands |
| Allocation plan execution state | PH044 read-only plan → future runtime |

### 5.4 Runtime only (never Cloud)

All `live_*` entities; OSC meter/fader caches; bridge heartbeats; in-flight write queues.

---

## 6. Relationship analysis

```
Band
 └── Show ──────────────────────┬── show_console_baselines (1..n, 1 active)
 │                              └── console_learning_snapshots (ephemeral)
 └── Song ─── song_effect_assignments ─── effect_packages
 │                    └── effect_package_items ─── effect_definitions
 │                                              └── effects (catalogue)
 └── Performance ─── performance_device_assignments ─── integration_devices (LS only)
 └── Cue ─── cue_actions ─── action_definitions ─── mix_moves (CCMM future)
```

| Relationship | Ownership | Lifecycle | Reuse | Sync relevance |
|--------------|-----------|-----------|-------|----------------|
| Show → Baseline | Show aggregate | draft → active → archived | per show variant | **High** — show prep |
| Song → Effect package | Song aggregate | song authoring | packages global | **High** — PH054 peer |
| Package → Definitions | Package aggregate | library | across songs | **High** |
| Baseline → Configuration JSON | Baseline document | learn → save → deploy | snapshot of desk | **High** |
| Performance → Device | Performance | show day | per performance | **Low** — LS only |
| Cue → Mix Move | Cue aggregate | cue authoring | mix_moves global | **High** when implemented |

---

## 7. CCMM impact assessment

### 7.1 New CCMM entities required (PH061A recommendation)

| Entity | CCMM package (proposed) |
|--------|-------------------------|
| `effect_definitions` | CCMM-12a |
| `effect_packages` | CCMM-12a |
| `effect_package_items` | CCMM-12a |
| `song_effect_assignments` | CCMM-12a |
| `effects` | CCMM-12a (reference seed) |
| `effect_parameters` | CCMM-12a |
| `effect_package_types` | CCMM-12a |
| `effect_package_item_parameters` | CCMM-12a |
| `effect_package_item_target_sections` | CCMM-12a |
| `show_console_baselines` | CCMM-12b |
| `mix_moves` | CCMM-12c (when M5 implemented) |

**Not added as tables:** channels, buses, routing, DCAs — remain JSON in `show_console_baselines`.

### 7.2 Existing CCMM entities affected

| Entity | Impact |
|--------|--------|
| `songs` | FK parent for `song_effect_assignments` — already CCMM |
| `shows` | FK parent for `show_console_baselines` |
| `bands` | FK on baselines and effect definitions scope |
| `cues` / `action_definitions` | Future link to `mix_moves` |
| `performances` | No CCMM change; device assignments stay LS |

### 7.3 New migration groups (PH062+)

| Package | After | Contents |
|---------|-------|----------|
| **CCMM-12a** Effects library | CCMM-05 (songs) | effect_* tables + seeds |
| **CCMM-12b** Console baselines | CCMM-08 (shows) | show_console_baselines |
| **CCMM-12c** Mix moves | CCMM-07 | mix_moves (blocked until M5 schema) |

### 7.4 Recovery dependency updates (PH061)

Insert after **C8 songs**:

| Phase | Entity |
|-------|--------|
| **C8a** | CCMM-12a effects tables + catalogue seed |
| **C8b** | `song_effect_assignments` data (if any) |
| **C15a** | CCMM-12b `show_console_baselines` after shows |
| **C15b** | Baseline JSON file artefacts (if stored externally — currently JSONB in row) |

**Blocker:** PH061 core recovery can proceed without CCMM-12; **full show prep parity** requires CCMM-12 before Live Stage realignment for console/effects.

### 7.5 Live Stage superset (unchanged)

Remain **excluded** from Cloud:

- `console_learning_snapshots`
- `integration_devices`, `integration_connection_profiles`
- `performance_device_assignments`
- All runtime/effect deployment execution state

---

## 8. Operator decisions required

| # | Decision | Default recommendation |
|---|----------|------------------------|
| 1 | Normalize channels/buses as tables vs JSON-in-baseline | **JSON-in-baseline** (current architecture) |
| 2 | `console_learning_snapshots` on Cloud for audit | **Live Stage only** |
| 3 | `integration_devices` as `consoles` registry on Cloud | **Live Stage only** |
| 4 | Include CCMM-12 in PH061 recovery scope | **Phase 2** after core CCMM-01–10 |
| 5 | Retire `effect_library_*` legacy tables | Operator — merge into `effects` catalogue |
| 6 | `mix_moves` CCMM timing | With M5 migration implementation |
| 7 | Rename X32 snippet terminology in docs | Avoid collision with music `snippets` |

---

## 9. Risks

| Risk | Level |
|------|-------|
| CCMM-12 scope creep before core recovery | **High** |
| `song_effect_assignments` on Cloud without effects tables | **Critical** if songs migrated first |
| JSON baseline documents too large for replication | **Medium** |
| Music vs X32 snippet naming collision | **Medium** |
| `effect_library_*` duplicate of `effects` | **Medium** — schema debt |
| Normalizing 32×16 routing as tables | **Critical** complexity — rejected |

---

## 10. PH062 / PH061 sequencing recommendation

| Track | Scope | When |
|-------|-------|------|
| **Track A** | PH061 core CCMM-00–10 (platform) | Gate 2 — first |
| **Track B** | CCMM-12 X32 console domain | After Track A Gate 4 OR parallel planning only |
| **Track C** | Live Stage superset + runtime | After Track A schema parity |

PH061A does **not** block Track A; it defines Track B requirements.

---

End of PH061A — domain discovery only
