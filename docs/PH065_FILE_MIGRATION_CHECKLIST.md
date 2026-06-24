# PH065 — File Migration Checklist

Status: R6 — Cloud-canonical file assets (DigitalOcean Spaces)  
Authority: PH061 §6, PH065 R6  
Date: 2026-06-24

---

## Pre-flight

| # | Item | Done |
|---|------|:----:|
| F0 | Gate 3 pass | ☐ |
| F1 | R5 complete for file-linked entities | ☐ |
| F2 | Spaces bucket provisioned / credentials in Forge | ☐ |
| F3 | Source manifest exported with SHA256 | ☐ |

---

## Asset classes

| Asset | DB field | Source | Destination | Done |
|-------|----------|--------|-------------|:----:|
| **Chart PDFs** | `charts.storage_reference` | Live Stage cache / `PORTAL_LIBRARY_*` | Spaces | ☐ |
| **Snippets** | `snippets.storage_reference` (+ annotation/markup/rendered) | Live Stage / Spaces | Spaces | ☐ |
| **Profile images** | `people.profile_photo_path` | Local/Spaces | Spaces | ☐ |
| **Person files** | `person_files.file_path` | Spaces private | Spaces | ☐ |
| **Ableton assets** | `ableton_show_files.storage_reference` | Local/Spaces | Spaces | ☐ |

---

## Verification

| # | Check | Criterion | Pass |
|---|-------|-----------|:----:|
| V1 | Checksum match | 100% SHA256 manifest match | ☐ |
| V2 | Count match | `COUNT(rows)` = files uploaded per class | ☐ |
| V3 | Storage resolvable | Every `storage_reference` returns object | ☐ |
| V4 | No orphan files | No uploaded files without DB row | ☐ |
| V5 | Sample open | 10 chart PDFs via signed URL smoke test | ☐ |
| V6 | Cloud row update | Paths updated if prefix convention changes | ☐ |

---

## Commands (placeholder — PH066+)

```bash
# php artisan recovery:upload-files --manifest=recovery/files_manifest.json --batch=<UUID>
# sha256sum -c recovery/files_manifest.sha256
```

---

## Rollback

| Trigger | Action |
|---------|--------|
| Checksum mismatch | Pause R7/R8; do not cut over |
| Partial upload | Delete Spaces prefix for `batch_id`; re-run |
| Wrong bucket | STOP; verify credentials |

---

End of File Migration Checklist
