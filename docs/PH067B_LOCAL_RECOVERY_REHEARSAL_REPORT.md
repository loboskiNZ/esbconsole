# PH067B — Local Recovery Rehearsal Report

Status: **Rehearsal complete — Gate 4 CONDITIONAL FAIL**  
Date: 2026-06-25  
Authority: PH066, PH067A, PH065 Gate 4 criteria  
**No production mutation.** Local Docker PostgreSQL only.

---

## Environment

| Role | Database | Host | Port |
|------|----------|------|------|
| **Source** | `esb_dev` | `127.0.0.1` | 5432 |
| **Target** | `esb_recovery_validation` | `127.0.0.1` | 5432 |
| Container | `backend-postgres-1` | — | — |

**Safety flags:** `APP_ENV=local`, `RECOVERY_LOCAL_ACKNOWLEDGED=true`, `RECOVERY_REHEARSAL_MODE=true`

**Forbidden (not used):** `defaultdb`, Forge, DigitalOcean, remote PostgreSQL, deploys, Spaces uploads.

---

## Batch

| Field | Value |
|-------|-------|
| **Batch ID** | `a0168483-1e88-4c6b-bb34-8e6b5a3314bb` |
| **Artifacts** | `server/storage/recovery/a0168483-1e88-4c6b-bb34-8e6b5a3314bb/` |

### Pipeline executed

| Step | Command | Result |
|------|---------|--------|
| 1 | `recovery:plan --json` | PASS |
| 2 | `recovery:export-domain --execute` | PASS |
| 3 | `recovery:export-files` | PASS |
| 4 | `recovery:transform-domain` | PASS |
| 5 | `recovery:import-domain --execute` | PARTIAL (377 inserted, 2467 skipped) |
| 6 | `recovery:verify` | FAIL (`passed: false`) |
| 7 | `recovery:rollback-batch` | PASS (dry-run report only) |

---

## 1. Plan output

**Migration order:** reference → bands → people → users → musicians → instrument_parts → import_audit → songs → cues → charts → song_instrument_parts → snippets → actions → shows → performances → devices → venues → effects → console_baselines → mix_moves

**Entity export order:** bands → people → users → musicians → songs → charts → snippets → shows → performances → devices → effects → console_baselines

**File order:** charts → snippets → people_profiles → person_files → ableton_show_files

Full JSON: `storage/recovery/a0168483-1e88-4c6b-bb34-8e6b5a3314bb_plan.json`

---

## 2. Domain export summary

| Domain | Exported rows | Bundle checksum (sha256 prefix) |
|--------|--------------:|----------------------------------|
| bands | 8 | `706e4861…` |
| people | 0 | empty |
| users | 7 | — |
| musicians | 17 | — |
| songs | 79 | — |
| charts | 259 | — |
| snippets | 1 | — |
| shows | 57 | — |
| performances | 2 | — |
| devices | 3 | — |
| effects | 520 | — |
| console_baselines | 4 | — |

**Warnings:** Export counts exceed live `pg_stat` approximations for some tables (e.g. songs 79 exported vs 50 approx) — export uses full table scan; acceptable for rehearsal evidence.

---

## 3. File export summary

| Metric | Count |
|--------|------:|
| Total file entries | 262 |
| Required (charts) | 259 |
| Optional (snippets + placeholders) | 3 |
| Status `pending` (path exists) | 0 |
| Status `missing` | 262 |

**Finding:** All chart file paths resolved from `storage_reference` were missing on the rehearsal host. File migration cannot proceed without local chart storage root configuration (expected PH067C / operator path mapping).

---

## 4. Transform / entity map summary

| Metric | Value |
|--------|------:|
| Entity map entries | 957 |
| Duplicate `public_id` detected | 0 |
| `public_id_preservation` | true |
| `bigint_remap_ready` | true (all entries; `cloud_id` null until import completes) |

Artifact: `entity_map.json` (219 KB)

---

## 5. Import summary

| Domain | Inserted | Skipped | Error samples |
|--------|----------:|--------:|---------------|
| reference | 37 | 0 | — |
| bands | 7 | 1 | `bands:1` — `primary_director_musician_id` FK (musicians not yet imported) |
| users | 7 | 0 | — |
| musicians | 0 | 17 | `band_id=1` missing (band 1 failed) |
| instrument_parts | 0 | 36 | FK / ordering |
| import_audit | 0 | 1154 | FK to bands/users |
| songs | 0 | 79 | `band_id=1` missing |
| cues | 0 | 16 | FK to songs |
| charts | 0 | 259 | FK to songs |
| song_instrument_parts | 0 | 438 | FK chain |
| snippets | 0 | 1 | FK chain |
| shows | 0 | 57 | FK chain |
| performances | 0 | 2 | FK chain |
| devices | 0 | 3 | FK chain |
| venues | 0 | 206 | FK chain |
| effects | 326 | 194 | Schema drift — legacy `effect_library_*` columns |
| console_baselines | 0 | 4 | `band_id=1` missing |
| **Total** | **377** | **2467** | 430 error lines (capped at 50/domain in manifest) |

**Entity map rows written:** 377 (`cloud_recovery_entity_map`)

---

## 6. Per-domain row count comparison

| Domain table(s) | Source (`esb_dev`) | Target (`esb_recovery_validation`) | Match |
|-----------------|-------------------:|-----------------------------------:|:-----:|
| bands | 8 | 7 | FAIL |
| people | 0 | 0 | PASS |
| users | 7 | 7 | PASS |
| musicians | 7 | 0 | FAIL |
| songs | 79 | 0 | FAIL |
| charts | 259 | 0 | FAIL |
| snippets | 1 | 0 | FAIL |
| shows | 3 | 0 | FAIL |
| performances | 1 | 0 | FAIL |
| effects | 95 | 95 | PASS |
| show_console_baselines | 4 | 0 | FAIL |

---

## 7. Verification summary

| Check | Result |
|-------|--------|
| Overall `passed` | **false** |
| `gate4_eligible` | **false** |
| FK orphans (post-import sample) | 0 |
| Duplicate `public_id` | 0 |
| `entity_map_issues` | 957 (`cloud_id_pending` — partial import) |
| Missing required files | 259 |
| `checksum_mismatches` | 0 (not evaluated — files missing) |

Artifact: `verification_report.json` (97 KB)

---

## 8. Rollback report

Dry-run rollback report generated. **No destructive rollback executed.**

| Field | Value |
|-------|------:|
| `executed` | false |
| `dry_run` | true |
| Map rows would delete | 377 |
| Files would delete | 377 (import manifest proxy) |

Artifact: `rollback_report.json`

---

## 9. Gate 4 rehearsal assessment (PH066 §9)

| Criterion | Result | Notes |
|-----------|--------|-------|
| **G4-1** Gate 3 passed on Cloud | **N/A** | Local rehearsal only; Cloud Gate 3 not in scope |
| **G4-2** Mandatory domains imported | **FAIL** | songs, charts, musicians, shows, baselines not imported |
| **G4-3** Row counts ±0 per domain | **FAIL** | 7/11 compared domains mismatch |
| **G4-4** Zero FK orphans | **PASS** | 0 orphans detected on imported subset |
| **G4-5** Zero duplicate `public_id` | **PASS** | 0 duplicates |
| **G4-6** All users have `public_id` | **PASS** | 7/7 users imported with `public_id` |
| **G4-7** Chart checksum 100% | **FAIL** | 259/259 chart files missing locally |
| **G4-8** Snippet checksum 100% | **CONDITIONAL** | 1 snippet; file missing — N/A until paths configured |
| **G4-9** Missing mandatory files = 0 | **FAIL** | 259 missing |
| **G4-10** Optional files documented | **PASS** | 3 optional entries in manifest |
| **G4-11** Rollback dry-run succeeds | **PASS** | Report generated; reversibility documented |
| **G4-12** Verification report archived | **PASS** | JSON in batch directory + this report |

**Overall Gate 4 rehearsal:** **CONDITIONAL FAIL** — tooling pipeline operational; data/file parity not achieved.

---

## 10. Known failures (evidence only — no PH067B fixes)

### Failure A — Circular FK import ordering (bands ↔ musicians)

| Phase | Import domain `bands` / `musicians` |
|-------|---------------------------------------|
| **Cause** | `bands.primary_director_musician_id` FK requires musician row before band id=1 inserts; musicians require `band_id` FK |
| **Effect** | Band id=1 skipped → cascade failure across songs, charts, shows, baselines |
| **Corrective action** | PH068: deferred FK pass — import bands with nullable director FK, import musicians, update director FK; or topological sort within domain |

### Failure B — Live Stage schema drift vs CCMM target

| Phase | Import domain `effects` |
|-------|---------------------------|
| **Cause** | `esb_dev` retains legacy columns: `effect_library_item_id`, `source_effect_library_parameter_id` not in CCMM-12 target |
| **Effect** | 194/520 effect rows skipped |
| **Corrective action** | PH068: transform step strips unknown columns; align `esb_dev` to CCMM or add migration transform map |

### Failure C — Chart file path resolution

| Phase | `recovery:export-files` |
|-------|---------------------------|
| **Cause** | `storage_reference` paths not present under configured local storage roots on rehearsal host |
| **Effect** | 262/262 files `missing` |
| **Corrective action** | Configure `PORTAL_LIBRARY_STORAGE_ROOT` / backend storage mount; PH067C dev Spaces bucket rehearsal |

---

## 11. Recommendation

1. **PH067B objective met for tooling rehearsal** — end-to-end pipeline runs locally with manifests, entity map, partial import, verification, and rollback report.
2. **Gate 4 not eligible** — do not proceed to Cloud R5/R6 until Failures A–C are addressed in a follow-up implementation phase (PH068+).
3. **Prioritise Failure A** — circular FK handling blocks majority of domain data.
4. **Align `esb_dev` to CCMM** before next rehearsal — reduces transform/import surprises.
5. **Mount chart storage** before file-phase Gate 4 criteria can pass.

---

## 12. Commands used (local only)

```bash
docker exec backend-postgres-1 psql -U esb -d postgres -c "CREATE DATABASE esb_recovery_validation;"

cd server && APP_ENV=local \
  RECOVERY_LOCAL_ACKNOWLEDGED=true RECOVERY_REHEARSAL_MODE=true \
  RECOVERY_SOURCE_DB_HOST=127.0.0.1 RECOVERY_SOURCE_DB_DATABASE=esb_dev \
  RECOVERY_SOURCE_DB_USERNAME=esb RECOVERY_SOURCE_DB_PASSWORD=esb_secret \
  RECOVERY_TARGET_DB_HOST=127.0.0.1 RECOVERY_TARGET_DB_DATABASE=esb_recovery_validation \
  RECOVERY_TARGET_DB_USERNAME=esb RECOVERY_TARGET_DB_PASSWORD=esb_secret \
  php artisan migrate:fresh --force

export BATCH_ID=a0168483-1e88-4c6b-bb34-8e6b5a3314bb
php artisan recovery:plan --json
php artisan recovery:export-domain --batch=$BATCH_ID --execute
php artisan recovery:export-files --batch=$BATCH_ID
php artisan recovery:transform-domain --batch=$BATCH_ID
php artisan recovery:import-domain --batch=$BATCH_ID --execute
php artisan recovery:verify --batch=$BATCH_ID
php artisan recovery:rollback-batch --batch=$BATCH_ID
```

---

End of PH067B rehearsal report
