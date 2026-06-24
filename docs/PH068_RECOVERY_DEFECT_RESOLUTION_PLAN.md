# PH068 — Recovery Defect Resolution Plan

Status: **Planning only** — no production commands, migrations, deploys, data/file migration, or schema changes  
Authority: PH067B (`docs/PH067B_LOCAL_RECOVERY_REHEARSAL_REPORT.md`), PH066, PH059 CCMM, AGENTS.md  
Date: 2026-06-25  
Rehearsal batch: `a0168483-1e88-4c6b-bb34-8e6b5a3314bb`

---

## Purpose

PH067B exercised the local recovery pipeline end-to-end and produced a **CONDITIONAL FAIL** on Gate 4. PH068 turns the four primary defects into a governed correction plan before any second rehearsal (PH067C) or Cloud recovery execution.

**This document authorises planning only.** Implementation belongs to PH069; validation belongs to PH067C.

---

## 1. Defect inventory

| Defect ID | Affected phase | Root cause | Impact | Severity | Corrective strategy | Validation required |
|-----------|----------------|------------|--------|----------|---------------------|---------------------|
| **RD-01** | `recovery:import-domain` — `bands` / `musicians` | Circular FK: `bands.primary_director_musician_id` → `musicians.id` while `musicians.band_id` → `bands.id`. CCMM-04 intentionally adds director FK after musicians exist; import uses single-pass insert with preserved IDs. | Band id=1 not imported (7/8 bands). All 17 musician rows skipped. | **Critical** | Three-pass deferred FK strategy (§2). Record deferred values in transform manifest; apply in Pass 3 via `cloud_recovery_entity_map`. | PH067C: bands 8/8, musicians 17/17; no FK errors on director column; entity_map entries for both tables complete. |
| **RD-02** | `recovery:import-domain` — cascade domains | RD-01 blocked band id=1 → dependent rows reference missing parent. | 0 songs, 0 charts, 0 shows, 0 baselines imported despite successful export. 2,467 rows skipped overall. | **Critical** | Resolve RD-01 first; enforce hard-stop verification if any band in export manifest fails import (§3). | PH067C: row counts ±0 for bands→songs→charts chain; verification `fk_orphans` = 0. |
| **RD-03** | `recovery:transform-domain` / `recovery:import-domain` — `effects` | `esb_dev` retains PH044 `effect_library_*` columns and tables; CCMM-12 target merged catalogue (`effects` / `effect_parameters`) and dropped `effect_library_item_id`, `source_effect_library_parameter_id`. | 194/520 effect-domain rows skipped; partial packages/parameters. | **High** | Transform rules strip legacy columns and remap library IDs to CCMM `effect_id` / `source_effect_parameter_id` (§4). No CCMM schema change unless defect proven. | PH067C: effects domain 520/520 or documented operator waivers; zero `Undefined column` import errors. |
| **RD-04** | `recovery:export-files` / `recovery:verify` | `storage_reference` values not resolved against any configured local root; `RecoveryFileManifestService` defaults to `storage_path()` when path is relative. Rehearsal host had no chart library mount. | 262/262 files classified `missing`; G4-7/G4-9 FAIL. | **High** | Configurable storage roots per domain (§5); missing-file classifier; operator documents mount path before PH067C. | PH067C: required chart files resolve OR classified `path_mismatch` / `operator_action_required` with manifest evidence; checksums computed for resolved files. |
| **RD-05** | `recovery:import-domain` — non-exported prerequisites | PH066 domain order includes `reference`, `instrument_parts`, `cues`, `song_instrument_parts`, `import_audit` not in export bundle; rehearsal fill-from-source works but ordering still depends on RD-01/02. | Secondary skips (e.g. 1,154 import_audit rows). | **Medium** | Keep rehearsal source-fill for non-exported domains; document in plan; optional explicit export in PH069. | PH067C: prerequisite domains imported before dependents per §3 dependency graph. |
| **RD-06** | `recovery:verify` | `entity_map_issues` reports all entries `cloud_id_pending` when import partial; no distinction between expected vs defect. | Noisy verification; `gate4_eligible` false even for tooling checks. | **Low** | PH069: verification report sections for deferred FK pass status and transform completeness. | PH067C: `entity_map_issues` = 0 after full import PASS. |

---

## 2. Import ordering correction

### 2.1 Problem statement

CCMM models the director FK correctly for **fresh migrate** (column nullable in CCMM-01; FK constraint in CCMM-04 after `musicians` exists). Recovery import must mirror that intent: **insert bands before musicians, but defer the director FK until musicians exist.**

### 2.2 Multi-pass strategy

```text
Pass 0 — reference (unchanged)
  song_moods, time_signatures, musical_keys
  Source: direct copy or R4 seed if empty

Pass 1 — bands (deferred director FK)
  INSERT bands WITH primary_director_musician_id = NULL
  STORE deferred_director_map: { band_source_id → primary_director_musician_source_id }
  WRITE entity_map for bands (source_id → cloud_id)

Pass 1b — people, users (unchanged order after bands)
  users.band_id remapped via entity_map

Pass 2 — musicians
  INSERT musicians WITH band_id remapped via entity_map
  WRITE entity_map for musicians

Pass 3 — deferred FK patch
  UPDATE bands SET primary_director_musician_id = map(musicians, deferred_director_map)
  WHERE deferred value NOT NULL
  RECORD patch in import_manifest.deferred_fk_patches[]

Pass 4+ — remaining domains in PH066 order
  instrument_parts → import_audit → songs → cues → charts → …
```

### 2.3 Transform manifest requirements

Extend `entity_map.json` (or companion `deferred_fk.json` v1):

```json
{
  "version": 1,
  "schema": "esb.recovery.deferred_fk/v1",
  "entries": [
    {
      "table": "bands",
      "column": "primary_director_musician_id",
      "row_source_id": 1,
      "referenced_table": "musicians",
      "referenced_source_id": 3
    }
  ]
}
```

Populated during `recovery:transform-domain` from source rows where `primary_director_musician_id IS NOT NULL`.

### 2.4 Entity map requirements

| Table | Pass | Map row required |
|-------|------|------------------|
| `bands` | 1 | Every inserted band |
| `musicians` | 2 | Every inserted musician |
| `bands.primary_director_musician_id` | 3 | Patch logged; no new map row (same band `cloud_id`) |

`cloud_recovery_entity_map` must include `batch_id`, `source_env`, `public_id` per PH066.

### 2.5 Validation

| Check | Command / artifact |
|-------|-------------------|
| Pass 1 bands count = source count | `import_manifest.domains[bands]` |
| Pass 2 musicians count = source count | `import_manifest.domains[musicians]` |
| Pass 3 patches applied = deferred entries | `import_manifest.deferred_fk_patches` |
| No orphan director FK | `recovery:verify` → `fk_orphans` |
| Director FK points to same band | SQL: `bands.primary_director_musician_id` → `musicians.band_id` consistency |

### 2.6 Rollback

`rollback_report.json` must list:

1. Reverse Pass 3 (nullable director FK) — optional if Pass 4+ not run
2. Delete domains in reverse PH066 order
3. Delete `cloud_recovery_entity_map` rows for `batch_id`

No schema rollback — data-only.

---

## 3. Band cascade recovery

### 3.1 Cascade mechanism (PH067B evidence)

Band id=1 failed → every row with `band_id = 1` failed downstream:

| Domain | FK column | Blocked by missing band id=1 |
|--------|-----------|-------------------------------|
| musicians | `band_id` | Yes — 17/17 skipped |
| songs | `band_id` | Yes — 79/79 skipped |
| charts | `song_id` (indirect) | Yes — songs never imported |
| shows | `band_id` | Yes |
| show_console_baselines | `band_id`, `show_id` | Yes — 4/4 skipped |
| performances | `show_id` (indirect) | Yes |
| snippets | `song_instrument_parts` chain | Yes — indirect |
| users | `band_id` | Partial — 7/7 imported (other band_ids) |

### 3.2 Dependency rules (mandatory import graph)

```text
bands
  └─ people (band_id)
       └─ users (person_id, band_id)
            └─ musicians (band_id, user_id)
                 └─ musician_band_roles
bands
  └─ songs (band_id)
       ├─ cues
       ├─ charts (song_id)
       ├─ song_instrument_parts (song_id, instrument_part_id, chart_id)
       │    └─ snippets
       └─ song_effect_assignments (after effects)
bands
  └─ shows (band_id)
       ├─ show_playlist_items
       ├─ performances
       │    └─ performance_assignments (musicians)
       └─ show_console_baselines (band_id, show_id)
```

### 3.3 Hard-stop rules (PH069)

| Rule ID | Condition | Action |
|---------|-----------|--------|
| **HS-01** | Any band in export bundle fails Pass 1 | Abort import; do not proceed to Pass 4+ |
| **HS-02** | `deferred_fk` patch fails in Pass 3 | Abort; emit rollback instructions |
| **HS-03** | Row count mismatch bands source vs target | `verification_report.passed = false`; block Gate 4 |
| **HS-04** | Child domain inserted > 0 while parent domain inserted = 0 | Flag `cascade_anomaly` in verification report |

### 3.4 People / users note

PH067B: `people` empty on source; `users` imported successfully for bands 2–8. After RD-01 fix, users linked to band 1 must re-validate `band_id` remap.

---

## 4. Effect transform rules

**Authority:** CCMM-12 (`database/migrations/ccmm/2026_07_01_001200_ccmm12_*.php`, `001210_*.php`). Source: PH044 `effect_library_*` on `esb_dev`. **No CCMM schema changes in PH068/PH069 unless a true CCMM defect is proven and logged in DECISION_LOG.**

### 4.1 Tables — export scope

| Source table (`esb_dev`) | Target table (CCMM-12) | Action |
|--------------------------|------------------------|--------|
| `effect_package_types` | `effect_package_types` | Direct copy (column match) |
| `effect_library_items` | — | **Do not import** — transform to `effects` lookup only |
| `effect_library_parameters` | — | **Do not import** — map to `effect_parameters` |
| `effects` | `effects` | Direct copy if columns ⊆ CCMM |
| `effect_parameters` | `effect_parameters` | Direct copy |
| `effect_definitions` | `effect_definitions` | Direct copy |
| `effect_packages` | `effect_packages` | Direct copy |
| `effect_package_items` | `effect_package_items` | Transform (§4.2) |
| `effect_package_item_parameters` | `effect_package_item_parameters` | Transform (§4.3) |
| `effect_package_item_target_sections` | `effect_package_item_target_sections` | Direct copy |
| `song_effect_assignments` | `song_effect_assignments` | Direct copy after songs imported |

### 4.2 `effect_package_items` field mapping

| Source field | Destination field | Rule |
|--------------|-------------------|------|
| `id` | `id` | Preserve if target empty; else remap |
| `effect_package_id` | `effect_package_id` | Remap via entity_map |
| `effect_definition_id` | `effect_definition_id` | Remap via entity_map |
| `effect_id` | `effect_id` | Use if set; else derive from `effect_library_item_id` (below) |
| `effect_library_item_id` | — | **DROP** — lookup `effects` by `(x32_algorithm_id, x32_slot_group)` or `x32_algorithm_code` from `effect_library_items` |
| `is_required`, `preferred_slot_number`, `slot_group_preference`, `routing_mode`, `target_section`, `return_destination`, `default_return_level`, `priority`, `parameter_overrides_json`, `timing_rules_json`, `notes` | same | Copy |
| (missing on source) | — | — |

**Operator decision:** If `effect_library_item_id` cannot map to exactly one `effects` row → classify `transform_warning`; row skipped unless operator supplies mapping table in batch config.

### 4.3 `effect_package_item_parameters` field mapping

| Source field | Destination field | Rule |
|--------------|-------------------|------|
| `source_effect_library_parameter_id` | — | **DROP** after remap |
| `source_effect_library_parameter_id` | `source_effect_parameter_id` | Map: `effect_library_parameters.id` → `effect_parameters.id` via parent effect mapping |
| `source_effect_parameter_id` (if already on source) | `source_effect_parameter_id` | Prefer if non-null |
| `parameter_number`, `parameter_name`, `value_type`, `value`, `min_value`, `max_value`, `unit`, `enum_values_json`, `scaling_notes`, `notes` | same | Copy |

### 4.4 Fields to drop on import (allowlist column filter)

Drop any column not in CCMM target schema:

- `effect_library_item_id`
- `source_effect_library_parameter_id`
- Any column from dropped tables `effect_library_items`, `effect_library_parameters`

### 4.5 Default values

| Field | Default when null after transform |
|-------|-----------------------------------|
| `effect_package_items.effect_id` | NULL allowed (CCMM nullable) — but prefer mapped value |
| `effect_package_item_parameters.source_effect_parameter_id` | NULL allowed — log warning if `value` present without source link |

### 4.6 Transform implementation locus

| Phase | Owner |
|-------|-------|
| `recovery:transform-domain` | Emit `effect_transform_map.json` with library→catalogue ID pairs |
| `recovery:import-domain` | Apply column filter + remap before INSERT |

### 4.7 Validation

- Zero `SQLSTATE[42703] Undefined column` errors in import_manifest
- `effects` + `effect_parameters` row counts match source (±0)
- Package item count match or documented waivers in `verification_report.warnings`

---

## 5. File resolution strategy

### 5.1 Resolution algorithm

For each `storage_reference` (and snippet auxiliary columns):

```text
1. If absolute path AND file_exists → use as-is
2. Else try RECOVERY_CHART_ROOT / storage_reference
3. Else try PORTAL_LIBRARY_STORAGE_ROOT / storage_reference
4. Else try backend storage_path('app/...') normalised
5. Else classify (§5.3) — do not silently mark pending
```

### 5.2 Configurable roots (PH069 env)

| Env key | Domain | Example local value |
|---------|--------|---------------------|
| `RECOVERY_CHART_ROOT` | charts | `/path/to/backend/storage/app/library/charts` |
| `RECOVERY_SNIPPET_ROOT` | snippets | `/path/to/backend/storage/app/snippets` |
| `RECOVERY_PROFILE_ROOT` | people_profiles, person_files | `server/storage/app/...` |
| `RECOVERY_ABLETON_ROOT` | ableton_show_files | `/path/to/ableton/shows` |
| `PORTAL_LIBRARY_STORAGE_ROOT` | charts (fallback) | `server/storage/app/library` |

Document in `config/recovery.php`; refuse production hosts per PH067A guards.

### 5.3 Missing file classification

| Class | Definition | Gate 4 impact |
|-------|------------|---------------|
| `required_missing` | `required: true` AND no file at resolved path | **FAIL** G4-9 |
| `optional_missing` | `required: false` AND missing | Warning only (G4-10) |
| `orphaned_db_row` | File exists but no DB row | Warning in verify |
| `path_mismatch` | DB path format incompatible with roots (e.g. Forge absolute on LS host) | **CONDITIONAL** — operator remap |
| `operator_action_required` | Content on another machine / not synced | Document in manifest; operator accepts or syncs before PH067C |

### 5.4 Manifest extensions (PH069)

`file_manifest.json` entries gain:

```json
{
  "resolution_class": "required_missing",
  "attempted_roots": ["/path/a", "/path/b"],
  "storage_reference_raw": "charts/…"
}
```

### 5.5 Chart `storage_reference` patterns (from PH066)

- Relative key: `charts/{band}/{song}/file.pdf`
- Absolute legacy path: `/home/forge/.../storage/...`
- Checksum: compare DB `checksum` column when file resolved

---

## 6. Rehearsal environment requirements (PH067C)

### 6.1 Databases

| Role | Name | Host | Notes |
|------|------|------|-------|
| Source | `esb_dev` | `127.0.0.1:5432` | Read-only via `RECOVERY_SOURCE_DB_*` |
| Target | `esb_recovery_validation` | `127.0.0.1:5432` | Fresh `migrate:fresh` before each rehearsal |
| Container | `backend-postgres-1` | Docker | Same as PH064/PH067B |

### 6.2 Safety env (mandatory)

```text
APP_ENV=local
RECOVERY_LOCAL_ACKNOWLEDGED=true
RECOVERY_REHEARSAL_MODE=true
RECOVERY_SOURCE_ENV=live_stage
```

### 6.3 Local storage paths

| Asset | Required for PH067C | Permission |
|-------|---------------------|------------|
| Chart library | **Yes** — mount or copy chart PDFs to `RECOVERY_CHART_ROOT` | Read-only for recovery user |
| Snippets | Recommended (1 row in source) | Read-only |
| Profile files | Optional (0 rows in PH067B) | Read-only |
| Ableton files | Optional (0 rows in PH067B) | Read-only |

**Operator action before PH067C:** Sync chart files from Live Stage library to local `RECOVERY_CHART_ROOT` such that ≥1 chart `storage_reference` resolves. Full 259/259 required for G4-7 PASS.

### 6.4 Pre-flight checklist

- [ ] `esb_recovery_validation` created; `migrate:fresh` PASS
- [ ] `ccmm:validate-schema` PASS on target
- [ ] Chart root configured and spot-check `file_exists` for 5 sample `storage_reference` values
- [ ] PH069 tooling deployed (deferred FK + effect transform + file classifier)
- [ ] Two consecutive dry-run PASS per PH066 §8 (data phase, then file phase)

---

## 7. Gate 4 reassessment

### 7.1 PH067B learnings applied to criteria

| Criterion | PH067B result | PH067C must prove |
|-----------|---------------|-----------------|
| G4-2 Mandatory domains | FAIL | All export domains with source rows > 0 imported |
| G4-3 Row counts ±0 | FAIL | Per-domain match in `verification_report.row_counts` |
| G4-4 FK orphans | PASS | Maintain 0 |
| G4-5 Duplicate `public_id` | PASS | Maintain 0 |
| G4-7 Chart checksums | FAIL | 100% for resolved required charts |
| G4-9 Mandatory files missing | FAIL | 0 `required_missing` OR operator waiver logged |
| G4-11 Rollback dry-run | PASS | Maintain PASS |
| **G4-13** (new) Deferred FK patches | N/A | All `deferred_fk` entries applied — **PASS** |
| **G4-14** (new) Effect transform | FAIL | 0 column drift errors; package rows match or waived |

### 7.2 PH067C success definition

PH067C **PASS** when:

1. RD-01 circular FK resolved (bands 8/8, musicians 17/17, Pass 3 complete)
2. RD-02 cascade cleared (songs, charts, shows, baselines row parity)
3. RD-03 effect transform clean (0 undefined column errors)
4. RD-04 required files resolve OR correctly classified with operator sign-off
5. `verification_report.passed = true` and `gate4_eligible = true` (local rehearsal sense)
6. `rollback_report.json` generated (dry-run)

**Cloud Gate 4** remains blocked until PH067C PASS **and** PH065 Gate 2 sign-off.

---

## 8. Tooling changes required (PH069)

| Change | Component | Description |
|--------|-----------|-------------|
| **T-01** Deferred FK pass | `RecoveryImportExecutor` | Pass 1 null director; Pass 3 UPDATE; `deferred_fk.json` reader |
| **T-02** Transform deferred FK | `RecoveryTransformService` | Emit `deferred_fk.json` from source bands |
| **T-03** Hard-stop cascade guard | `RecoveryImportExecutor` | HS-01–HS-04 rules |
| **T-04** Column allowlist filter | `RecoveryImportExecutor` / transform | Drop unknown columns per target schema introspection |
| **T-05** Effect transform map | `RecoveryEffectTransformService` (new) | Library→catalogue ID mapping; `effect_transform_map.json` |
| **T-06** Storage root config | `config/recovery.php` | `RECOVERY_*_ROOT` keys |
| **T-07** File path resolver | `RecoveryFileManifestService` | Multi-root resolution + `resolution_class` |
| **T-08** Missing file classifier | `RecoveryVerifyService` | Split `missing_files` by class |
| **T-09** Verification report v2 fields | `RecoveryVerifyService` | `deferred_fk_status`, `effect_transform_status`, `cascade_anomalies` |
| **T-10** Import manifest extensions | Report writer | `deferred_fk_patches[]`, `transform_warnings[]` |
| **T-11** Tests | `server/tests/Unit/Recovery/` | Deferred FK round-trip; effect column strip; path resolution |
| **T-12** Optional | `recovery:export-domain` | Export `reference` + prerequisite domains explicitly |

**PH067C** consumes PH069 tooling — no new features in PH067C except rehearsal execution and report.

---

## 9. Risks

| Risk | Classification | Mitigation |
|------|----------------|------------|
| RD-01 fix incorrect → silent wrong director | **Critical** | Pass 3 validation SQL; entity_map audit |
| Cascade hard-stop aborts mid-batch leaving partial target | **High** | Transaction per pass OR mandatory `migrate:fresh` before rehearsal |
| Effect library mapping ambiguous (duplicate x32_algorithm) | **High** | Operator mapping table; transform warnings |
| Chart files never synced locally | **High** | PH067C pre-flight; G4-7 remains FAIL until synced |
| `esb_dev` diverges further from CCMM | **Medium** | Periodic `ccmm:validate-schema` on source; align LS schema in separate governed phase |
| Verification noise (RD-06) | **Low** | PH069 report improvements |
| Operator assumes PH067C PASS = Cloud ready | **Critical** | DECISION_LOG + PH065 Gate 2 still required |

---

## 10. Recommendation

| Phase | Scope | Sequence |
|-------|-------|----------|
| **PH069** | Recovery tooling corrections — implement §8 (T-01–T-11) | **First** |
| **PH067C** | Second local recovery rehearsal — full pipeline on `esb_dev` → `esb_recovery_validation` with chart root mounted | **After PH069** |
| PH065-exec | Cloud R5/R6 | **Blocked** until PH067C PASS + Gate 2 |

**Do not** run PH067C before PH069 — rehearsal would repeat PH067B failures.

**Do not** modify CCMM migrations for legacy `effect_library_*` — transform at recovery layer per PH061A merge decision.

---

## References

- `docs/PH067B_LOCAL_RECOVERY_REHEARSAL_REPORT.md`
- `docs/PH066_DATA_MIGRATION_AND_VERIFICATION_TOOLING_PLAN.md`
- `database/migrations/ccmm/2026_07_01_000410_ccmm04_extend_bands_director_fk.php`
- `database/migrations/ccmm/2026_07_01_001210_ccmm12_create_effects_package_tables.php`
- `backend/database/migrations/2026_06_18_110000_ph044_effect_library_reference_schema.php`

---

End of PH068 defect resolution plan
