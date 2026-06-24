# PH067C — Second Local Recovery Rehearsal Report

Status: **CONDITIONAL PASS** — data recovery tooling validated; file phase bounded conditional  
Date: 2026-06-25  
Authority: PH069 tooling corrections, PH068 defect plan, PH066 Gate 4  
Batch: `62d2bef1-f1d2-4dd3-89b3-a2513c6a3094`  
**No production mutation.** Local Docker PostgreSQL only.

---

## Environment and safety

| Check | Value |
|-------|-------|
| `APP_ENV` | `local` |
| Source | `127.0.0.1:5432` / `esb_dev` |
| Target | `127.0.0.1:5432` / `esb_recovery_validation` |
| Container | `backend-postgres-1` |
| `RECOVERY_REHEARSAL_MODE` | `true` |
| `RECOVERY_LOCAL_ACKNOWLEDGED` | `true` |
| `RECOVERY_CHART_ROOT` | `server/storage/app/library` |
| `PORTAL_LIBRARY_STORAGE_ROOT` | `server/storage/app/library` |
| Production host blocked | Not used |
| Target `migrate:fresh` | PASS (48 CCMM tables) |

Artifacts: `server/storage/recovery/62d2bef1-f1d2-4dd3-89b3-a2513c6a3094/`

---

## PH067B → PH067C comparison

| Metric | PH067B | PH067C |
|--------|--------|--------|
| Bands imported | 7/8 | **8/8** |
| Musicians imported | 0 | **7/7** |
| Songs imported | 0 | **79/79** |
| Charts imported | 0 | **259/259** |
| Effects domain errors | 194 skipped | **0 errors** |
| Total import errors | 430 lines | **0** |
| Deferred FK replay | N/A | **1/1 applied** |
| Cascade block | Yes (`bands_blocked`) | **No** |
| Row count parity | 7/11 domains FAIL | **All tables match** |
| FK orphans | 0 | **0** |

---

## 1. Plan summary

Command: `recovery:plan --json` (schema v2)

| Field | Value |
|-------|-------|
| Critical path | reference → bands → musicians → songs → charts → shows → console_baselines |
| Deferred FK path | bands → musicians → bands.deferred_fk_replay |
| Deferred FK candidates | 1 |
| Blocked if bands fail | songs, charts, shows, performances, console_baselines |
| Non-exported prerequisites | reference, instrument_parts, import_audit, cues, song_instrument_parts, actions, venues, mix_moves |

---

## 2. Export summary

| Step | Result |
|------|--------|
| `export-domain --dry-run` | PASS |
| `export-domain --execute` | PASS |

| Domain | Rows exported |
|--------|--------------:|
| bands | 8 |
| users | 7 |
| musicians | 17 (bundle lines incl. roles) |
| songs | 79 |
| charts | 259 |
| snippets | 1 |
| shows | 57 |
| performances | 2 |
| devices | 3 |
| effects | 520 |
| console_baselines | 4 |

`export_manifest.json` + `domains/*.jsonl` generated.

---

## 3. File summary

Command: `recovery:export-files --batch=…`

| Metric | Count |
|--------|------:|
| Total file entries | 263 |
| Resolved (`resolved`) | 1 |
| Required missing | 258 |
| Optional missing | 4 |
| Path mismatch | 0 |
| Operator action required | 0 |

`file_manifest.json` and `missing_files_report.json` generated. Missing files are **correctly classified** as `required_missing` — chart PDFs not present on rehearsal host (only 2 files exist under `RECOVERY_CHART_ROOT`). Not a tooling failure.

---

## 4. Transform summary

Command: `recovery:transform-domain --batch=…`

| Artifact | Result |
|----------|--------|
| `entity_map.json` | 957 entries |
| `deferred_fk.json` | 1 entry (band id=1 → musician id=6) |
| `effect_transform_report.json` | 9 library item maps, 77 parameter maps |
| Duplicate `public_id` | **0** |

Deferred FK entry:

```json
{
  "table": "bands",
  "column": "primary_director_musician_id",
  "row_source_id": 1,
  "referenced_source_id": 6,
  "public_id": "bef29738-65b0-443f-ac49-3406c5eba501"
}
```

---

## 5. Import summary

| Step | Result |
|------|--------|
| `import-domain --dry-run` | PASS |
| `import-domain --execute` | PASS (exit 0) |

| Domain | Inserted | Skipped | Errors | Blocked |
|--------|----------:|--------:|-------:|---------|
| reference | 37 | 0 | 0 | no |
| bands | 8 | 0 | 0 | no |
| users | 7 | 0 | 0 | no |
| musicians | 17 | 0 | 0 | no |
| songs | 79 | 0 | 0 | no |
| charts | 259 | 0 | 0 | no |
| shows | 57 | 0 | 0 | no |
| effects | 520 | 0 | 0 | no |
| console_baselines | 4 | 0 | 0 | no |
| **All domains** | **0 errors** | | | |

`dependency_block_report.json`: `bands_blocked: false`, `blocked_domains: []`

---

## 6. Deferred FK result

`deferred_fk_report.json`:

| Field | Value |
|-------|-------|
| queued | 1 |
| applied | 1 |
| unresolved | 0 |
| complete | **true** |

Post-import validation: `bands.id=1.primary_director_musician_id = 6` on target.

**RD-01 resolved.**

---

## 7. Effect transform result

| Field | Value |
|-------|-------|
| Effects domain inserted | 520/520 |
| Import SQL column errors | **0** |
| `ambiguous_count` | 0 |
| `operator_review` | empty |

Column filter + legacy field drop prevented PH067B `Undefined column` failures.

**RD-03 resolved.**

---

## 8. Per-domain row count verification

| Domain | Source | Target | Match |
|--------|-------:|-------:|:-----:|
| bands | 8 | 8 | PASS |
| users | 7 | 7 | PASS |
| musicians | 7 | 7 | PASS |
| songs | 79 | 79 | PASS |
| charts | 259 | 259 | PASS |
| snippets | 1 | 1 | PASS |
| shows | 3 | 3 | PASS |
| performances | 1 | 1 | PASS |
| effects | 95 | 95 | PASS |
| show_console_baselines | 4 | 4 | PASS |

All CCMM tables in `verification_report.row_counts` show `match: true`.

---

## 9. Verification summary (v2)

`verification_report.json` schema **v2**

| Check | Result |
|-------|--------|
| `fk_orphans` | 0 |
| `duplicate_public_ids` | 0 |
| `entity_map_issues` | 0 |
| `deferred_fk.complete` | **true** |
| `effect_transform.present` | true |
| `file_resolution.resolved` | 1 |
| `file_resolution.required_missing` | 258 |
| `passed` | false (file blocker only) |
| `gate4_eligible` | false |
| `gate4_readiness.blockers` | `["required_files_missing"]` |

---

## 10. Rollback report

`recovery:rollback-batch` generated `rollback_report.json` (dry-run only; no destructive execution).

---

## 11. Gate 4 rehearsal assessment

| Criterion | PH067B | PH067C | Result |
|-----------|--------|--------|--------|
| G4-1 Cloud Gate 3 | N/A | N/A | N/A |
| G4-2 Mandatory domains | FAIL | **PASS** | Data domains imported |
| G4-3 Row counts ±0 | FAIL | **PASS** | All tables match |
| G4-4 FK orphans | PASS | **PASS** | 0 |
| G4-5 Duplicate `public_id` | PASS | **PASS** | 0 |
| G4-6 Users `public_id` | PASS | **PASS** | 7/7 |
| G4-7 Chart checksums | FAIL | **CONDITIONAL** | 1/259 resolved; 258 missing locally |
| G4-8 Snippet checksums | CONDITIONAL | **CONDITIONAL** | Optional missing |
| G4-9 Mandatory files = 0 | FAIL | **CONDITIONAL** | Classified `required_missing` |
| G4-10 Optional documented | PASS | **PASS** | 4 optional |
| G4-11 Rollback dry-run | PASS | **PASS** | Report generated |
| G4-12 Report archived | PASS | **PASS** | Batch + this doc |
| G4-13 Deferred FK | FAIL | **PASS** | complete=true |
| G4-14 Effect transform | FAIL | **PASS** | 520/520, 0 ambiguity |

**Overall Gate 4 rehearsal:** **CONDITIONAL PASS**

- **Data phase:** PASS — PH068 defects RD-01, RD-02, RD-03, RD-05, RD-06 resolved in rehearsal
- **File phase:** CONDITIONAL — RD-04 tooling works; operator must sync chart library to local root for full G4-7/G4-9 PASS

---

## 12. Defect resolution status

| Defect | PH067C outcome |
|--------|----------------|
| RD-01 Circular FK | **Resolved** |
| RD-02 Band cascade | **Resolved** |
| RD-03 Effect drift | **Resolved** |
| RD-04 File paths | **Tooling OK** — content not on host |
| RD-05 Dependency visibility | **Resolved** (plan v2) |
| RD-06 Verification noise | **Resolved** (v2 report) |

---

## 13. Recommendation

1. **PH067C data recovery tooling objective: PASS** — proceed to file-phase preparation before Cloud R5/R6.
2. **Cloud recovery remains blocked** until PH065 Gate 2 sign-off and operator syncs chart files to rehearsal root (or accepts file waiver).
3. **No PH070 tooling correction required** unless operator wants enhanced Forge-path rewriting for `path_mismatch` class.
4. Optional **PH067D**: file upload rehearsal to dev Spaces bucket after local chart sync.

---

## Commands used (local only)

```bash
docker exec backend-postgres-1 psql -U esb -d postgres -c "CREATE DATABASE esb_recovery_validation;"

cd server
export APP_ENV=local DB_CONNECTION=pgsql
export RECOVERY_LOCAL_ACKNOWLEDGED=true RECOVERY_REHEARSAL_MODE=true
export RECOVERY_SOURCE_DB_*=…esb_dev…
export RECOVERY_TARGET_DB_*=…esb_recovery_validation…
export RECOVERY_CHART_ROOT=/path/to/server/storage/app/library
export PORTAL_LIBRARY_STORAGE_ROOT=$RECOVERY_CHART_ROOT

php artisan migrate:fresh --force
export BATCH_ID=62d2bef1-f1d2-4dd3-89b3-a2513c6a3094

php artisan recovery:plan --json
php artisan recovery:export-domain --dry-run --batch=$BATCH_ID
php artisan recovery:export-domain --execute --batch=$BATCH_ID
php artisan recovery:export-files --batch=$BATCH_ID
php artisan recovery:transform-domain --batch=$BATCH_ID
php artisan recovery:import-domain --dry-run --batch=$BATCH_ID
php artisan recovery:import-domain --execute --batch=$BATCH_ID
php artisan recovery:verify --batch=$BATCH_ID
php artisan recovery:rollback-batch --batch=$BATCH_ID
```

---

End of PH067C report
