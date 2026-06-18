# PH044.04 — Effects Allocation Engine

**Status:** Implemented (read-only resolver)  
**Date:** 2026-06-18  
**Authority:** `docs/x32/DECISION_LOG.md` (X32-DEC-006, X32-DEC-007, X32-DEC-008)  
**Related:** `docs/x32/PH044_EFFECTS_DOMAIN_MODEL.md`, `docs/x32/PH044_EFFECTS_DISCOVERY_AUDIT.md`

---

## Purpose

Convert **song + package assignments** into a **read-only X32 FX slot deployment plan** without OSC writes, UI, or live deployment.

**Service:** `App\Services\Effects\EffectsAllocationResolver`  
**Entry:** `resolve(Song $song): EffectsAllocationResult`

---

## Allocation Rules

### 1. Package collection

| Source | Included when |
|---|---|
| **Permanent** | All active `effect_packages` with `package_type = permanent` |
| **Song assignment** | Enabled `song_effect_assignments` for the song (excluding duplicate permanent entries) |
| **Special treatment** | Only when assigned to the song |

Permanent packages apply to every song resolution (show-wide baseline).

### 2. Package ordering (for candidate merge precedence)

1. Permanent before song-assigned packages  
2. Lower `priority` value first (package or assignment priority)  
3. Lower `effect_package_items.priority` first  

### 3. Effect candidate merge

When the same `effect_definition` appears in multiple packages:

- Allocate **once**
- `package_sources` lists all requesting package slugs
- `is_required` = true if **any** membership is required
- Preferred slot / overrides taken from the **highest-precedence** package item (per ordering above)

### 4. Allocation sort order

Before slot assignment:

1. Required before optional  
2. Permanent-tier packages before song-assigned  
3. Package priority (ascending)  
4. Item priority (ascending)  

### 5. Slot groups

| `x32_slot_group` | Valid FX slots |
|---|---|
| `fx1_4` | 1–4 |
| `fx5_8` | 5–8 |
| `any` | 1–8 |

Algorithm enum IDs are **slot-group local** — the resolver never mixes FX1–4 and FX5–8 enum spaces.

### 6. Preferred slot

If `preferred_slot_number` is set and valid for the slot group:

- Use it when free → `READY` (no warning)
- If occupied or wrong group → warning + next free slot in group
- If no slot in group → required → `BLOCKED`; optional → dropped + warning

### 7. Non-slot effects

| `implementation_type` | Consumes FX slot? |
|---|---|
| `fx_slot` | Yes |
| `hybrid` | Yes **only when** package item sets `preferred_slot_number` (explicit FX deployment) |
| `channel_processing` | No |
| `main_processing` | No |

Non-slot effects appear in `non_slot_effects` with `slot_number: null`.

### 8. Capacity

- **8 FX slots** total (1–8)
- Required slot-consuming effects that cannot be placed → `blocking_conflicts` → `BLOCKED`
- Optional slot-consuming effects that cannot be placed → `dropped_optional_effects` + warning → `READY_WITH_WARNINGS`

### 9. Status

| Status | Condition |
|---|---|
| `READY` | All required effects satisfied; no warnings |
| `READY_WITH_WARNINGS` | All required satisfied; warnings (preferred slot fallback, dropped optional, conflicting fallback recall) |
| `BLOCKED` | One or more required slot-consuming effects unallocated |

---

## Result Structure

`EffectsAllocationResult::toArray()` returns:

| Key | Content |
|---|---|
| `song_id`, `song_name` | Song identity |
| `status` | `READY` \| `READY_WITH_WARNINGS` \| `BLOCKED` |
| `assigned_packages` | Permanent + song packages with source and priority |
| `allocated_effects` | Slot-consuming effects with slot numbers |
| `non_slot_effects` | Processing-only effects |
| `dropped_optional_effects` | Optional effects dropped for capacity |
| `blocking_conflicts` | Required effects that could not be allocated |
| `warnings` | Human-readable warning strings |
| `fallback_console_recall` | Recall metadata from song assignments |

Each allocated effect includes: definition id/slug/name, `package_sources`, `slot_number`, `slot_group`, algorithm code/id, implementation type, tempo behaviour, safety, overrides, timing rules.

---

## Fallback Console Recall

Collected from enabled `song_effect_assignments` where `fallback_console_recall_name` is set.

- Multiple entries are all returned  
- If name/type combinations differ → warning added (status may become `READY_WITH_WARNINGS`)

Used by a future deployment phase when automated package apply fails.

---

## Future Deployment Boundary (not implemented)

PH044.04 stops at the **plan**. A future `EffectsDeploymentService` would:

1. Call `EffectsAllocationResolver::resolve()`
2. Reject `BLOCKED` plans for runtime apply
3. Write `/fx/{slot}/type` and `/fx/{slot}/par/{nn}` only at **between-song/transition** boundaries (X32-DEC-006)
4. Honour `fallback_console_recall` on failure
5. Apply `timing_rules_json` when Ableton musical clock exists

No OSC, no algorithm switching, no UI in PH044.04.

---

## Tests

`tests/Feature/EffectsAllocationResolverTest.php` — 19 cases covering rules, status, slot groups, fallback recall, and negative route/OSC checks.

---

End of PH044.04 Effects Allocation Engine
