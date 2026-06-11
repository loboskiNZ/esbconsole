# PH031 — Dry-Run Migration Validation

Status: Complete  
Baseline: PH030 parser foundation (`b011eb9`)  
Scope: Dry-run validation report generation — **no canonical writes, no asset copying**

---

## 1. Purpose

Produce a structured migration validation report from legacy sources using the PH030 parser. Operators and PH032 import tooling consume this report as the safety gate before controlled import.

---

## 2. Entry Points

### Service (programmatic)

```php
app(LegacyDryRunValidationService::class)->validate(LegacyImportConfig $config);
// Returns LegacyDryRunValidationReport
```

### Artisan command

```bash
php artisan legacy:import-dry-run \
  --root=/path/to/legacy/project \
  [--setlist=/path/to/setlists.json] \
  [--musicians=/path/to/musicians.json] \
  [--setlist-id=default] \
  [--band-slug=default-band] \
  [--output=/path/to/report.json] \
  [--summary]
```

| Option | Purpose |
|--------|---------|
| `--root` | Legacy project root (required) — directory containing `setlists.json` by default |
| `--setlist` | Override path to `setlists.json` |
| `--musicians` | Override path to `musicians.json` |
| `--setlist-id` | Import specific setlist (default: `activeSetlistId`) |
| `--output` | Write JSON report to file; prints summary to console |
| `--summary` | Append human-readable summary after JSON stdout |

**Real legacy show:** Point `--root` at the production legacy directory (e.g. repository root with `setlists.json`, `charts/`, `uploads/`). Missing files are reported — not assumed present in git.

---

## 3. Report Structure

```json
{
  "status": "PASS | PASS_WITH_WARNINGS | BLOCKED",
  "import_batch_id": "uuid",
  "legacy_setlist_id": "default",
  "show_name": "Thieves Alley",
  "project_root": "/abs/path",
  "generated_at": "ISO8601",
  "counts": { ... },
  "asset_findings": { ... },
  "mapping_findings": { ... },
  "issues": { ... }
}
```

### Counts

`setlists`, `songs`, `playlist_items`, `cues`, `synthetic_cue_000`, `instrument_part_candidates`, `song_instrument_part_candidates`, `chart_candidates`, `snippet_candidates`, `musician_candidates`

### Asset findings

| Key | Content |
|-----|---------|
| `existing_chart_files` | Chart candidates with file on disk |
| `missing_chart_files` | Expected charts not found |
| `existing_snippet_files` | Snippet PNG candidates present |
| `missing_snippet_files` | Snippet metadata without file |
| `nochart_txt_skipped` | Placeholder assignments skipped |
| `upload_fallback_usage` | Charts resolved via uploads/assignments |
| `shared_chart_candidates` | One chart, multiple role slugs |
| `checksum_duplicate_groups` | Same checksum, multiple candidate keys |

### Mapping findings

| Key | Content |
|-----|---------|
| `legacy_song_id_to_song_code` | Timestamp ID → canonical song_code |
| `legacy_cue_index_to_cue_number` | 0-based index → cue_number |
| `cue_number_to_sequence_order` | Identity → performance order |
| `role_string_to_instrument_part` | Role slug → normalized name |
| `chart_assignment_to_shared_chart` | Shared chart dedup map |
| `snippet_to_sip_cue` | Snippet → SIP + Cue target |

### Issues

`unresolved_roles`, `missing_files`, `zero_cue_songs`, `orphan_snippets`, `duplicate_mappings`, `ambiguous_chart_assignments`, `blockers`, `warnings`

---

## 4. Validation Status Rules

| Status | Condition |
|--------|-----------|
| **BLOCKED** | Any parser/import blocker (missing playlist song, song_code overflow, empty playlist, etc.) |
| **PASS_WITH_WARNINGS** | No blockers; any warning-class issue (missing assets, zero-cue songs, unresolved roles, etc.) |
| **PASS** | No blockers and no warning-class issues |

Exit codes (Artisan):

| Status | Exit code |
|--------|-----------|
| PASS | 0 |
| PASS_WITH_WARNINGS | 0 |
| BLOCKED | `Command::INVALID` (2) |

---

## 5. Hard Boundaries

| Rule | Status |
|------|--------|
| No canonical entity writes | ✅ |
| No asset copying | ✅ |
| No import_batches write in PH031 | ✅ In-memory report only |
| No frontend / runtime / X32 changes | ✅ |

---

## 6. Implementation References

| Component | Location |
|-----------|----------|
| Validation service | `App\Services\LegacyImport\LegacyDryRunValidationService` |
| Report builder | `App\Services\LegacyImport\LegacyDryRunReportBuilder` |
| Report DTO | `App\DataTransferObjects\LegacyImport\LegacyDryRunValidationReport` |
| Artisan command | `App\Console\Commands\LegacyImportDryRunCommand` |
| Tests | `tests/Feature/LegacyDryRunValidationTest.php` |

---

## 7. Recommended Next Phase

**PH032 — Controlled Legacy Show Import** — write canonical entities when status ≠ BLOCKED and operator approves; persist `import_batches` record.

---

End of PH031 — Dry-Run Migration Validation
