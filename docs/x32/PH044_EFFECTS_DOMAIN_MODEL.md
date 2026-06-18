# PH044.03 — Effects Domain Model

**Status:** Implemented (domain layer only)  
**Date:** 2026-06-18  
**Authority:** `docs/x32/DECISION_LOG.md` (X32-DEC-006, X32-DEC-007)  
**Related:** `docs/x32/PH044_EFFECTS_DISCOVERY_AUDIT.md`, `docs/x32/PH044_EFFECTS_ALGORITHM_CATALOGUE.md`

---

## Purpose

Persist the **musical effects package model** in the database so songs can request effect packages before any X32 deployment exists. This phase defines definitions, packages, membership, and song assignments — not live OSC control or UI.

**Approved architecture:** Effects are managed as musical packages, not raw FX-slot editing first.

---

## Table Summary

| Table | Model | Purpose |
|---|---|---|
| `effect_definitions` | `EffectDefinition` | Canonical effect identity (algorithm code, slot group, safety, tempo behaviour) |
| `effect_packages` | `EffectPackage` | Named musical packages (Vocal/Horn, FOH, Dub, etc.) |
| `effect_package_items` | `EffectPackageItem` | Package ↔ definition membership with slot hints and overrides |
| `song_effect_assignments` | `SongEffectAssignment` | Song ↔ package requests with priority and fallback recall |

All domain tables use `public_id` (UUID) via `HasPublicId`.

---

## Relationship Map

```mermaid
erDiagram
    EffectDefinition ||--o{ EffectPackageItem : "included in"
    EffectPackage ||--o{ EffectPackageItem : contains
    EffectPackage ||--o{ SongEffectAssignment : "assigned via"
    Song ||--o{ SongEffectAssignments : requests
    Song }o--o{ EffectPackage : "through assignments"

    EffectDefinition {
        string slug UK
        string x32_algorithm_code
        int x32_algorithm_id nullable
        string x32_slot_group
    }
    EffectPackage {
        string slug UK
        string package_type
        int priority
    }
    EffectPackageItem {
        int effect_package_id FK
        int effect_definition_id FK
        int preferred_slot_number nullable
    }
    SongEffectAssignment {
        int song_id FK
        int effect_package_id FK
        string fallback_console_recall_name nullable
    }
```

### Eloquent relationships

| Model | Relationship |
|---|---|
| `EffectPackage` | `hasMany` `EffectPackageItem`, `belongsToMany` `EffectDefinition`, `hasMany` `SongEffectAssignment`, `belongsToMany` `Song` |
| `EffectDefinition` | `hasMany` `EffectPackageItem`, `belongsToMany` `EffectPackage` |
| `EffectPackageItem` | `belongsTo` package and definition |
| `Song` | `hasMany` `SongEffectAssignment`, `belongsToMany` `EffectPackage` |
| `SongEffectAssignment` | `belongsTo` `Song`, `EffectPackage` |

---

## Package Architecture

### Package types (`EffectPackageType`)

| Type | Meaning |
|---|---|
| `permanent` | Always part of show baseline (e.g. Standard Vocal/Horn, FOH Main) |
| `song_selectable` | Chosen per song (e.g. Reggae Dub, Horn Funk, Disco/Techno) |
| `special_treatment` | Exceptional aesthetic packages (e.g. Vintage Radio Vocal) |

Lower `priority` on `effect_packages` = higher precedence when resolving conflicts (documented convention).

### Reference packages (seeded)

| Slug | Name | Type |
|---|---|---|
| `standard-vocal-horn-package` | Standard Vocal/Horn Package | permanent |
| `foh-main-package` | FOH Main Package | permanent |
| `reggae-dub-package` | Reggae Dub Package | song_selectable |
| `horn-funk-package` | Horn Funk Package | song_selectable |
| `disco-techno-package` | Disco / Techno Package | song_selectable |
| `vintage-radio-vocal` | Vintage Radio Vocal | special_treatment |

Seeder: `Database\Seeders\EffectReferenceSeeder` — reference catalogue only, **no song assignments**.

---

## Effect Definition Fields

| Field | Role |
|---|---|
| `x32_algorithm_code` | Canonical four-letter identity (`PLAT`, `DLY`, `GEQ`, …) |
| `x32_algorithm_id` | Slot-group enum integer; **nullable** when unverified |
| `x32_slot_group` | `fx1_4`, `fx5_8`, or `any` |
| `implementation_type` | `fx_slot`, `channel_processing`, `main_processing`, `hybrid` |
| `tempo_behavior` | `tempo_aware`, `musical_time_aware`, `tempo_neutral` — stored now; clock integration later |
| `active_song_safety` | Mirrors PH044.01 safety audit |
| `default_parameters_json` | Default par values when known; deployment not implemented |

**Rule:** Do not invent algorithm IDs. Reference seeder uses Maillot-verified IDs from `PH044_EFFECTS_ALGORITHM_CATALOGUE.md` only.

---

## Package Items

`effect_package_items` defines membership:

- `is_required` — package incomplete without this effect
- `preferred_slot_number` — deployment hint (1–8), not enforced yet
- `slot_group_preference` — copied from definition slot group at seed time
- `parameter_overrides_json` — package-specific par overrides
- `timing_rules_json` — future musical-time rules (e.g. delay division); **no Ableton clock yet**

Unique constraint: `(effect_package_id, effect_definition_id)`.

---

## Song Effect Assignments

Links songs to requested packages before runtime deployment:

| Field | Purpose |
|---|---|
| `priority` | Order when multiple packages apply to one song |
| `assignment_type` | `default`, `song_specific`, `transition_only` |
| `enabled` | Soft disable without deleting assignment |
| `fallback_console_recall_name` | Console scene/snippet/cue name if package deploy fails |
| `fallback_console_recall_type` | `scene`, `snippet`, or `cue` |

Unique constraint: `(song_id, effect_package_id)`.

Deleting a song cascades assignments. Deleting a package in use is **restricted** (`restrictOnDelete`).

---

## Future Allocation Engine (not implemented)

PH044.03 stores **metadata only**. PH044.04 implements read-only plan generation — see `docs/x32/PH044_EFFECTS_ALLOCATION_ENGINE.md`.

A future **deployment** phase will:

1. Resolve active packages for a song (permanent + song assignments by priority)
2. Map definitions to physical FX slots respecting `x32_slot_group` and `preferred_slot_number`
3. Detect slot conflicts across concurrent packages
4. Enforce **between-song-only** algorithm changes (X32-DEC-006)
5. Apply `parameter_overrides_json` and later `timing_rules_json` with Ableton tempo
6. Fall back to `fallback_console_recall_*` on deployment failure

No `effect_slot_allocations` table in PH044.03 — allocation state remains runtime/ephemeral until a later phase justifies persistence.

---

## Intentionally Not Implemented

| Area | Status |
|---|---|
| Effects UI / routes | Not added |
| Live OSC read/write | Not added |
| X32 deployment / algorithm switching | Not added |
| Ableton clock / cue automation | Not added |
| Slot allocation persistence | Documented only |

---

## Enums

| Enum | Used on |
|---|---|
| `X32SlotGroup` | `EffectDefinition`, package item preference |
| `EffectImplementationType` | `EffectDefinition` |
| `EffectTempoBehavior` | `EffectDefinition` |
| `EffectActiveSongSafety` | `EffectDefinition` |
| `EffectPackageType` | `EffectPackage` |
| `SongEffectAssignmentType` | `SongEffectAssignment` |
| `FallbackConsoleRecallType` | `SongEffectAssignment` |

Category, target section, and effect role use string columns with model constants on `EffectDefinition` / `EffectPackage`.

---

## Tests

`tests/Feature/EffectDomainSchemaTest.php` — schema, relationships, constraints, reference seeder, negative checks for routes/OSC services.

---

End of PH044.03 Effects Domain Model
