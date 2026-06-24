# PH066 — Data Migration and Verification Tooling Plan

Status: **Planning and design only** — no production commands, migrations, deploys, data/file migration, or schema changes  
Authority: PH061 §5–6, PH065 R5/R6, PH059 CCMM, PH064 validation  
Date: 2026-06-24

---

## Purpose

PH065 Gate 4 is **blocked** until governed tooling exists to migrate and verify Live Stage → Cloud data and files. PH066 defines that tooling architecture, entity plans, report formats, dry-run requirements, rollback strategy, and Gate 4 pass criteria.

**PH067** will implement commands and run local dry-run/rehearsal (see §12).

---

## 1. Migration tooling architecture

### 1.1 Command surface (Laravel Artisan — `server/`)

All commands live under `App\Console\Commands\Recovery\`. **Every write command requires `--dry-run` support.**

| Command | Phase | Purpose |
|---------|-------|---------|
| `recovery:export-domain` | Export | Read Live Stage rows → JSONL domain bundle + export manifest |
| `recovery:transform-domain` | Transform | Normalize types, assign missing `public_id`, resolve FK placeholders |
| `recovery:import-domain` | Import | Insert into Cloud DB; write `cloud_recovery_entity_map` |
| `recovery:export-files` | Export | Build file manifest from DB `storage_reference` columns |
| `recovery:upload-files` | Import | Upload to Spaces; optional path rewrite |
| `recovery:verify` | Verify | Row counts, FK, checksums, orphans → verification report |
| `recovery:rollback-batch` | Rollback | Delete Cloud rows/files by `batch_id`; emit rollback report |
| `recovery:plan` | Dry-run | Print dependency order + counts without I/O writes |

### 1.2 Pipeline

```text
Live Stage DB / storage
    → export manifest (JSON)
    → transform (optional per-domain)
    → import manifest (JSON) + entity map rows
    → file manifest (JSON) + SHA256
    → upload to Spaces
    → verification report (JSON)
    → Gate 4 decision
```

### 1.3 Configuration

| Env / config key | Meaning |
|------------------|---------|
| `RECOVERY_SOURCE_DB_*` | Live Stage read-only connection |
| `RECOVERY_TARGET_DB_*` | Cloud write connection (new cluster only) |
| `RECOVERY_BATCH_ID` | UUID v4 per recovery run |
| `RECOVERY_SOURCE_ENV` | `live_stage` (default) |
| `RECOVERY_SPACES_DISK` | Laravel disk name for Cloud uploads |
| `RECOVERY_DRY_RUN` | `true` — no INSERT/UPLOAD |

### 1.4 Outputs directory

```text
storage/recovery/{batch_id}/
  export_manifest.json
  import_manifest.json
  entity_map_snapshot.json      # copy of map table subset
  files_manifest.json
  verification_report.json
  rollback_report.json
  domains/{domain}.jsonl
```

---

## 2. Source and destination

| Layer | Source | Destination |
|-------|--------|-------------|
| **Database** | Live Stage PostgreSQL (`esb_dev` or operator-approved LS DB) | New Cloud PostgreSQL (`esb_cloud` on Path B cluster) |
| **Read access** | `RECOVERY_SOURCE_DB_*` — **SELECT only** | — |
| **Write access** | — | `RECOVERY_TARGET_DB_*` — INSERT + map table only via tooling |
| **Chart files** | `backend/storage/...`, `PORTAL_LIBRARY_STORAGE_ROOT`, local cache | DigitalOcean Spaces (`charts/` prefix) |
| **Snippets** | Live Stage storage per `snippets.*_storage_reference` | Spaces (`snippets/`) |
| **Profile / person files** | Local paths / legacy Spaces | Spaces (`people/`) |
| **Ableton files** | Local / `ableton_show_files.storage_reference` | Spaces (`ableton/`) |

**Excluded sources:** forensic production `defaultdb`, quarantined `invite_links*`, runtime-only tables.

**Live Stage is never mutated** during Cloud recovery.

---

## 3. Entity migration plan

### 3.1 Dependency order (import batches)

| Order | Domain key | Tables | Depends on |
|------:|------------|--------|------------|
| 1 | `reference` | `song_moods`, `time_signatures`, `musical_keys` | — (or R4 seed if empty on LS) |
| 2 | `bands` | `bands` | — |
| 3 | `people` | `people`, `person_secure_fields`, `person_files`, `person_iem_settings`, `person_instruments` | `bands`, `instrument_reference` (R4 seed) |
| 4 | `users` | `users` | `bands`, `people` |
| 5 | `musicians` | `musicians`, `musician_band_roles` | `bands`, `users` (optional FK) |
| 6 | `bands_director` | update `bands.primary_director_musician_id` | `musicians` |
| 7 | `instrument_parts` | `instrument_parts` | `bands` |
| 8 | `import_audit` | `import_batches`, `import_entity_mappings` | `bands`, `users` |
| 9 | `songs` | `songs` | `bands`, reference FKs |
| 10 | `cues` | `cues` | `songs` |
| 11 | `charts` | `charts` | `songs`, `import_batches` |
| 12 | `song_instrument_parts` | `song_instrument_parts` | `songs`, `instrument_parts`, `charts` |
| 13 | `snippets` | `snippets` | `song_instrument_parts`, `cues`, `charts` |
| 14 | `actions` | `action_definitions`, `action_parameters`, `cue_actions` | `bands`, `cues`, `action_types` (R4/CCMM-07 seed) |
| 15 | `shows` | `ableton_show_files`, `shows`, `show_playlist_items` | `bands`, `songs` |
| 16 | `performances` | `performances`, `performance_assignments` | `shows`, `musicians`, `instrument_parts`, `songs`, `cues` |
| 17 | `devices` | `devices`, `capabilities`, `assignments` | `musicians`, `instrument_parts` |
| 18 | `venues` | `venues`, `festivals` | `bands` |
| 19 | `effects` | `effect_package_types`, `effects`, `effect_parameters`, `effect_definitions`, `effect_packages`, `effect_package_items`, `effect_package_item_parameters`, `effect_package_item_target_sections`, `song_effect_assignments` | `songs`; catalogue may be R4 seed |
| 20 | `console_baselines` | `show_console_baselines` | `bands`, `shows` |
| 21 | `mix_moves` | `mix_moves` | — (placeholder rows only if present) |

### 3.2 Per-domain specification

| Domain | Key strategy | Validation rules |
|--------|--------------|------------------|
| **bands** | Preserve `public_id` | count match; exactly 1 default band if expected |
| **people** | Preserve `public_id`; remap `band_id` | child FKs resolve; no orphan `person_id` |
| **users** | Preserve or generate `public_id` v4 | no null `public_id`; username CI unique |
| **musicians** | Preserve `public_id`; remap `band_id`, `user_id` | roles unique per musician |
| **instruments** | `instrument_reference` from R4 seed; `person_instruments` remap FKs | slug match catalogue |
| **songs** | Preserve `public_id`, `song_code` | unique `(band_id, song_code)`; BPM/refs valid |
| **charts** | Preserve `public_id`; remap `song_id`, `import_batch_id` | unique `(song_id, checksum)`; file manifest row |
| **snippets** | Preserve `public_id`; remap SIP/cue/chart FKs | active partial unique respected |
| **cues** | Preserve `public_id`, `cue_number` | unique per song; SSS.CCC spot check |
| **shows** | Preserve `public_id`; playlist `position` order | `ableton_show_file_id` unique |
| **performances** | Preserve `public_id` | assignment FKs valid |
| **devices** | Preserve `public_id` | capabilities unique pairs |
| **assignments** | Preserve `public_id` | musician ↔ instrument_part |
| **effects** | Preserve package/definition `public_id` where present | `song_effect_assignments` unique per song+package |
| **console baselines** | Preserve `public_id`; `source_snapshot_id` copied as opaque bigint | `baseline_json` not null |
| **venues / festivals** | Preserve `public_id` | `band_id` valid |
| **import audit** | Preserve `public_id`; preserve `manifest_json` | mapping keys unique per batch |

### 3.3 Not migrated

`runtime_*`, `console_learning_snapshots`, `integration_*`, `performance_device_assignments`, `soundchecks`, `readiness_records`, `invite_links*`.

---

## 4. Identity mapping

### 4.1 Rules

| Rule | Detail |
|------|--------|
| **Canonical identity** | `public_id` (uuid) — cross-environment stable |
| **Runtime identity** | `song_code` + `cue_number` (PH010.01) — validated, not remapped |
| **bigint remap** | Source `id` → Cloud `id` via map; FK columns resolved at import time |
| **Duplicate detection** | Reject if `(table, public_id)` exists on Cloud with different source row |
| **Username** | Case-insensitive collision check before insert |

### 4.2 `cloud_recovery_entity_map` usage

On each successful insert:

```text
source_env=live_stage, table_name, source_id, cloud_id, public_id, migrated_at, batch_id
```

Import reads map to resolve parent FKs: `parent_cloud_id = map.cloud_id WHERE source_id = row.parent_source_id`.

### 4.3 Rollback mapping

`recovery:rollback-batch --batch={uuid}`:

1. Query map grouped by `table_name` in **reverse dependency order** (§3.1 reversed).
2. DELETE Cloud rows by `cloud_id`.
3. DELETE map rows for batch.
4. Emit `rollback_report.json` with counts per table.

Files: delete Spaces objects listed in `files_manifest.json` where `batch_id` matches.

---

## 5. File migration plan

| Asset class | DB columns | Source path resolution | Destination prefix | Checksum |
|-------------|------------|------------------------|--------------------|----------|
| Chart PDFs | `charts.storage_reference`, `checksum` | LS disk + `PORTAL_LIBRARY_*` | `charts/{public_id}/` | SHA256 |
| Snippets | `storage_reference`, annotation/markup/rendered | LS storage | `snippets/{public_id}/` | SHA256 each |
| Profile images | `people.profile_photo_path` | LS / legacy | `people/profiles/` | SHA256 |
| Person files | `person_files.file_path` | LS / legacy | `people/files/` | SHA256 |
| Ableton | `ableton_show_files.storage_reference` | LS | `ableton/{public_id}/` | SHA256 |

### 5.1 Tooling flow

1. `recovery:export-files --domain=charts` → `files_manifest.json` entries.
2. `recovery:upload-files --dry-run` → missing-file report only.
3. `recovery:upload-files` → upload + store destination key; update Cloud row if prefix changes.
4. Permissions: private ACL for person files; signed URL smoke test in verify.

### 5.2 Missing files

| Severity | Handling |
|----------|----------|
| **Mandatory** (chart with active song) | Gate 4 **FAIL** |
| **Optional** (orphan path, inactive snippet) | Report in `verification_report.warnings`; operator may accept |

---

## 6. Verification tooling

### 6.1 `recovery:verify` checks

| Check | Method |
|-------|--------|
| Row counts | `COUNT(*)` source vs Cloud per domain |
| FK integrity | SQL orphan query (same as `ccmm:validate-schema` FK logic) |
| Orphan records | Rows with unresolvable `storage_reference` |
| Duplicate keys | Unique constraint probe per CCMM |
| Checksum validation | Re-hash Spaces object vs DB `checksum` |
| File existence | Head object for each manifest entry |
| Application readiness | Optional `--smoke-url` for `/up` pre-cutover |
| Map completeness | Every imported source row has map entry |

### 6.2 Human-readable summary

Command prints PASS/FAIL table; full detail in JSON report.

---

## 7. Machine-readable reports

### 7.1 Export manifest

```json
{
  "schema": "esb.recovery.export_manifest/v1",
  "batch_id": "uuid",
  "source_env": "live_stage",
  "exported_at": "ISO8601",
  "domains": [
    {
      "domain": "songs",
      "tables": ["songs"],
      "row_count": 42,
      "bundle_path": "domains/songs.jsonl",
      "checksum_sha256": "hex"
    }
  ]
}
```

### 7.2 Import manifest

```json
{
  "schema": "esb.recovery.import_manifest/v1",
  "batch_id": "uuid",
  "imported_at": "ISO8601",
  "dry_run": false,
  "domains": [
    {
      "domain": "songs",
      "inserted": 42,
      "skipped": 0,
      "errors": []
    }
  ]
}
```

### 7.3 Entity map snapshot

```json
{
  "schema": "esb.recovery.entity_map/v1",
  "batch_id": "uuid",
  "entries": [
    {
      "table_name": "songs",
      "source_id": 1,
      "cloud_id": 1,
      "public_id": "uuid"
    }
  ]
}
```

### 7.4 File manifest

```json
{
  "schema": "esb.recovery.files_manifest/v1",
  "batch_id": "uuid",
  "files": [
    {
      "entity": "charts",
      "public_id": "uuid",
      "source_path": "/abs/path/or/key",
      "destination_key": "charts/uuid/file.pdf",
      "sha256": "hex",
      "bytes": 12345,
      "status": "pending|uploaded|missing"
    }
  ]
}
```

### 7.5 Verification report

```json
{
  "schema": "esb.recovery.verification_report/v1",
  "batch_id": "uuid",
  "verified_at": "ISO8601",
  "passed": true,
  "row_counts": { "songs": { "source": 42, "cloud": 42, "match": true } },
  "fk_orphans": [],
  "checksum_mismatches": [],
  "missing_files": [],
  "warnings": [],
  "gate4_eligible": true
}
```

### 7.6 Rollback report

```json
{
  "schema": "esb.recovery.rollback_report/v1",
  "batch_id": "uuid",
  "rolled_back_at": "ISO8601",
  "tables": [{ "table_name": "snippets", "deleted_rows": 10 }],
  "files_deleted": 10,
  "map_rows_deleted": 500,
  "complete": true
}
```

---

## 8. Dry-run requirement

| Command | Dry-run behaviour |
|---------|-------------------|
| `recovery:import-domain` | Parse bundles, resolve FKs, **no INSERT**; write dry-run import manifest |
| `recovery:upload-files` | Validate paths exist, compute checksums, **no PUT** |
| `recovery:rollback-batch` | List rows/files that **would** be deleted |
| `recovery:verify` | Always read-only |

**Operator rule:** Two consecutive dry-run PASS reports required before write mode for each phase (data, then files).

---

## 9. Rollback strategy

| Window | Scope | Mechanism |
|--------|-------|-----------|
| Pre-cutover | DB rows | `recovery:rollback-batch` via map reverse order |
| Pre-cutover | Files | Delete by `files_manifest.destination_key` for batch |
| Pre-cutover | Partial domain failure | Roll back entire `batch_id` — no partial commit across domains |
| Post-cutover | See `PH065_ROLLBACK_RUNBOOK.md` Path D | `.env` revert; forensic restore last resort |

Live Stage source remains unchanged throughout.

---

## 10. Gate 4 pass criteria

Gate 4 passes when **all** mandatory criteria met and verification report has `"gate4_eligible": true`:

| # | Criterion |
|---|-----------|
| G4-1 | Gate 3 already passed on Cloud |
| G4-2 | All **mandatory** domains imported (operator defines empty vs migrate — minimum: schema-ready reference + bands if applicable) |
| G4-3 | Row counts: source vs Cloud **±0** per imported domain |
| G4-4 | Zero FK orphan rows on Cloud |
| G4-5 | Zero duplicate `public_id` / unique constraint violations |
| G4-6 | All `users` rows have non-null `public_id` |
| G4-7 | Chart file checksum match **100%** for migrated charts |
| G4-8 | Snippet checksum match **100%** if snippets migrated |
| G4-9 | Missing **mandatory** files = 0 |
| G4-10 | Missing optional files documented in `warnings` with operator acceptance |
| G4-11 | `rollback_report` dry-run succeeds (proves reversibility) |
| G4-12 | `verification_report.json` archived to incident log |

**Empty Cloud start:** If operator chooses no data migration, Gate 4 may pass with G4-2 waived and documented — files G4-7–9 N/A.

---

## 11. Risks

| Risk | Level | Mitigation |
|------|-------|------------|
| Write to wrong database | **Critical** | Separate env vars; host allowlist; dry-run first |
| FK remap bug | **Critical** | Domain-ordered import; map table; verify FK pass |
| File path drift LS vs Cloud | **High** | files_manifest; missing-file report |
| Large `baseline_json` payloads | **Medium** | Stream JSONL; batch size limits |
| Effects catalogue duplication | **Medium** | Seed on Cloud R4; import assignments only |
| Re-run partial batch | **High** | Single batch_id; idempotent reject on duplicate public_id |
| Spaces permission errors | **Medium** | pre-flight ACL test in dry-run |

---

## 12. PH067 readiness

### Recommendation: **PH067 = Tooling implementation + local dry-run rehearsal**

| Track | PH067 scope |
|-------|-------------|
| **PH067A** | Implement `recovery:*` Artisan commands in `/server/` per §1 |
| **PH067B** | Local dry-run against `esb_dev` → `esb_ccmm_validation` (or sibling DB) |
| **PH067C** | Optional full rehearsal with sample domain subset + file upload to dev Spaces bucket |

**Not PH067:** Production Cloud execution (requires Gate 2 + R5 in PH065 runbook).

### PH067 readiness checklist

| Criterion | Status |
|-----------|--------|
| PH066 plan complete | ✅ |
| CCMM schema on Cloud/local target | ✅ PH064 |
| `cloud_recovery_entity_map` exists | ✅ PH063 |
| Gate 2 signed | ❌ Operator |
| Recovery commands implemented | ❌ PH067A |

---

End of PH066 — planning only; no implementation in this phase
