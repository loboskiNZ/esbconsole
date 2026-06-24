# PH067D — Local File Recovery Rehearsal Report

Status: **PASS** — file recovery tooling validated; bounded conditional on demo seed row only  
Date: 2026-06-25  
Authority: PH067C report, PH069 file resolution tooling, PH066 Gate 4 G4-7/G4-9  
Batch: `6a231d04-ab3f-47d8-bece-4ee2044abc0a`  
**No production mutation.** Local files and local PostgreSQL only.

---

## Executive summary

PH067C proved data recovery with **258 chart files missing** because `RECOVERY_CHART_ROOT` pointed at `server/storage/app/library` (2 PDFs on disk). PH067D re-ran file export with the correct local chart library at `backend/storage/app/private` and confirms:

| Metric | PH067C | PH067D |
|--------|--------|--------|
| Charts resolved | 1 | **258** |
| Charts required_missing | 258 | **1** |
| SHA256 computed | 1 | **258** |
| path_mismatch | 0 | 0 |
| Tooling regression | — | **None** |

**Answer to PH067D question:** Yes — when the chart library is available locally, file recovery completes successfully with checksums and accurate classification.

---

## 1. Governance review evidence

Reviewed and complied with: `AGENTS.md`, `PROJECT_CHARTER.md`, recovery authority chain (PH055–PH069), `docs/PH067C_SECOND_LOCAL_RECOVERY_REHEARSAL_REPORT.md`. No production execution, no Forge/DigitalOcean, no Spaces upload, no production file access.

---

## 2. Source file locations

### Discovery

| Location | Role | PDF / file count | Used for rehearsal |
|----------|------|-----------------:|--------------------|
| `backend/storage/app/private` | Live Stage chart library (`charts/{band}/{song}/…`) | **253 PDFs** | **Yes — `RECOVERY_CHART_ROOT`** |
| `server/storage/app/imports/charts` | Import staging (song-folder layout) | 340 PDFs | No — different path layout |
| `server/storage/app/library` | Portal library stub (PH067C root) | 2 PDFs | No — insufficient for rehearsal |
| `backend/tests/fixtures/legacy/` | Test fixtures | 6 PDFs | No — test data only |

No local snippet, profile, person-file, or Ableton show-file trees were found outside demo metadata rows. Domains without exported bundles receive placeholder `optional_missing` entries (expected tooling behaviour).

### Configured roots (local only)

| Env var | Path |
|---------|------|
| `RECOVERY_CHART_ROOT` | `backend/storage/app/private` |
| `PORTAL_LIBRARY_STORAGE_ROOT` | `backend/storage/app/private` |
| `RECOVERY_SNIPPET_ROOT` | `backend/storage/app/private` |
| `RECOVERY_PROFILE_ROOT` | `backend/storage/app/private` |
| `RECOVERY_ABLETON_ROOT` | `backend/storage/app/private` |

No production Forge paths referenced.

### Permissions

Sample chart `backend/storage/app/private/charts/1/031/alto_sax.pdf`: readable (`-rw-r--r--`), accessible to Laravel process, no symlink issues observed.

---

## 3. Batch ID

`6a231d04-ab3f-47d8-bece-4ee2044abc0a`

Artifacts: `server/storage/recovery/6a231d04-ab3f-47d8-bece-4ee2044abc0a/`

---

## 4. File counts by domain

| Domain | DB references | Files found (resolved) | Missing | Classification |
|--------|---------------:|-----------------------:|--------:|----------------|
| **Charts** | 259 | **258** | 1 | 1 `required_missing` (demo seed) |
| **Snippets** | 1 | 0 | 1 | `optional_missing` (demo seed) |
| **People profiles** | 1 | 0 | 1 | `optional_missing` (no bundle — placeholder) |
| **Person files** | 1 | 0 | 1 | `optional_missing` (no bundle — placeholder) |
| **Ableton show files** | 1 | 0 | 1 | `optional_missing` (no bundle — placeholder) |
| **Total** | **263** | **258** | **5** | |

---

## 5. Resolution summary

`missing_files_report.json` summary:

| Class | Count |
|-------|------:|
| `resolved` | 258 |
| `required_missing` | 1 |
| `optional_missing` | 4 |
| `path_mismatch` | 0 |
| `orphaned_db_row` | 0 |
| `operator_action_required` | 0 |

The single `required_missing` chart is demo metadata only:

- `public_id`: `00a3affc-13c8-4efd-8863-ecbb919b094b`
- `storage_reference`: `local-demo/charts/demo-vocal.pdf`
- Genuinely absent from all configured roots — correctly classified, not a tooling failure.

---

## 6. Checksum summary

| Check | Result |
|-------|--------|
| SHA256 generated | **258 / 258 resolved files** |
| File sizes populated | **258 / 258** |
| Manifest entries complete | **263 / 263** |
| DB checksum alignment (sample) | PASS — e.g. chart `cb91d8e4-…` manifest SHA256 matches source `checksum` column |

Sample resolved entry:

```json
{
  "storage_reference_raw": "charts/1/005/4WhqFVvlQAKHwZz9gM9Tchtyvaa8hCjrgXxe6bPp.pdf",
  "sha256": "41122074539bab4e85606269d27bc838c2dc57b79a2601bb22e752f2cafd1de6",
  "bytes": 16537,
  "resolution_class": "resolved"
}
```

---

## 7. Missing file summary

| Missing item | Reason | Acceptable |
|--------------|--------|:----------:|
| `local-demo/charts/demo-vocal.pdf` | Demo seed row — metadata only, no file on disk | Yes |
| `local-demo/snippets/demo-guitar-chorus.png` | Demo seed row — optional domain | Yes |
| `people_profiles/pending` | No `people_profiles` domain bundle exported | Yes |
| `person_files/pending` | No `person_files` domain bundle exported | Yes |
| `ableton_show_files/pending` | No `ableton_show_files` domain bundle exported | Yes |

---

## 8. Verification summary

Command: `recovery:verify --batch=6a231d04-ab3f-47d8-bece-4ee2044abc0a`

Schema **v2** — `file_resolution` block:

```json
{
  "resolved": 258,
  "required_missing": 1,
  "optional_missing": 4,
  "path_mismatch": 0,
  "operator_action_required": 0
}
```

| Check | Result |
|-------|--------|
| `file_resolution` present | PASS |
| `required_missing` reduced vs PH067C (258 → 1) | PASS |
| Tooling regression | None |
| Row counts (target from PH067C) | All match |
| `verification_report.version` | 2 |

**Note:** `passed: false` and `gate4_eligible: false` on this batch because import was not re-run (file-only scope). Blockers: `required_files_missing` (1 demo chart) and `deferred_fk_unresolved` (transform-only batch — deferred FK already proven in PH067C batch `62d2bef1-…`). These do not indicate file-resolution tooling failure.

---

## 9. G4-7 assessment (chart checksums)

| Criterion | PH067C | PH067D | Result |
|-----------|--------|--------|--------|
| Checksums for resolved charts | 1/259 | **258/259** | **PASS** |
| Demo/metadata chart | N/A | 1 missing (no file exists) | **CONDITIONAL** |

**G4-7: CONDITIONAL PASS** — all production chart PDFs checksum; single demo seed row has no file (acceptable).

---

## 10. G4-9 assessment (mandatory files)

| Criterion | PH067C | PH067D | Result |
|-----------|--------|--------|--------|
| `required_missing` (charts) | 258 | **1** | **PASS** (tooling) |
| Misclassification | None | None | **PASS** |
| `path_mismatch` | 0 | 0 | **PASS** |

**G4-9: CONDITIONAL PASS** — one demo chart row correctly classified `required_missing`; zero tooling misclassifications.

---

## 11. Recommendation

1. **PH067D file recovery objective: PASS** — mount `backend/storage/app/private` (or synced copy) as `RECOVERY_CHART_ROOT` before Cloud file migration.
2. **Operator action before Cloud R6:** sync full chart library to rehearsal host; exclude or waive demo seed rows (`local-demo/*`).
3. **No PH070 tooling correction required** for file resolution — RD-04 closed for local rehearsal.
4. **Cloud recovery** remains blocked on PH065 Gate 2 sign-off; data path (PH067C) and file path (PH067D) are both validated locally.

---

## 12. Confirmation no production mutation occurred

`APP_ENV=local`, `RECOVERY_REHEARSAL_MODE=true`, `RECOVERY_LOCAL_ACKNOWLEDGED=true`. Source DB `esb_dev` and target `esb_recovery_validation` on local Docker PostgreSQL only. No Forge, DigitalOcean, Spaces, or production file paths used.

---

## Commands used

```bash
source /tmp/ph067d-rehearsal.env   # local roots + DB vars (not committed)
cd server
BATCH_ID=6a231d04-ab3f-47d8-bece-4ee2044abc0a

php artisan recovery:export-domain --execute --batch=$BATCH_ID
php artisan recovery:export-files --batch=$BATCH_ID
php artisan recovery:transform-domain --batch=$BATCH_ID
php artisan recovery:verify --batch=$BATCH_ID
```

---

End of PH067D report
