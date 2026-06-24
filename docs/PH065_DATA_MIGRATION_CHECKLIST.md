# PH065 — Data Migration Checklist

Status: R5 — **tooling required (PH066+)** — checklist for when import commands exist  
Authority: PH061 §5, PH065 R5  
Date: 2026-06-24

**Default source:** Live Stage `esb_dev` (local). **Destination:** new Cloud `esb_cloud`.  
**Identity:** preserve `public_id`; remap `id` via `cloud_recovery_entity_map`.

---

## Cross-cutting

| # | Item | Done |
|---|------|:----:|
| X1 | Gate 3 pass | ☐ |
| X2 | Import batch UUID assigned | ☐ |
| X3 | `cloud_recovery_entity_map` empty before batch | ☐ |
| X4 | Import order follows FK dependency | ☐ |
| X5 | No import from `invite_links` / quarantined tables | ☐ |

---

## Domain checklist

| Domain | Source table(s) | Destination | Validation | Rollback |
|--------|-----------------|-------------|------------|----------|
| **bands** | `bands` | `bands` | count=1; `public_id` | delete batch rows |
| **people** | `people`, children | same | FK chain; count match | batch rollback |
| **users** | `users` | `users` | all have `public_id`; username unique | batch rollback |
| **musicians** | `musicians`, `musician_band_roles` | same | count match | batch rollback |
| **songs** | `songs` | `songs` | `song_code` unique per band | batch rollback |
| **charts** | `charts` | `charts` | count + checksum metadata | batch rollback |
| **snippets** | `snippets` | `snippets` | count per song | batch rollback |
| **shows** | `ableton_show_files`, `shows`, `show_playlist_items` | same | playlist order | batch rollback |
| **performances** | `performances`, `performance_assignments` | same | FK to show/musician | batch rollback |
| **devices** | `devices`, `capabilities`, `assignments` | same | orphan check | batch rollback |
| **effects** | `effect_*`, `song_effect_assignments` | same | package FK integrity | batch rollback |
| **console baselines** | `show_console_baselines` | same | `baseline_json` not null | batch rollback |

---

## Per-domain validation

| Check | Criterion | Pass |
|-------|-----------|:----:|
| Row counts | Source vs Cloud ±0 per domain | ☐ |
| `public_id` | No nulls on migrated entities | ☐ |
| `song_code` | 3-char; unique `(band_id, song_code)` | ☐ |
| `cue_number` | unique per `song_id` | ☐ |
| FK integrity | Zero orphans post-import | ☐ |
| Map table | Every imported row has map entry | ☐ |

---

## Rollback

```text
php artisan recovery:rollback-batch --batch=<UUID>   # PH066+ placeholder
```

Delete in reverse FK order using `cloud_recovery_entity_map`. Live Stage **unchanged**.

---

## Not migrated to Cloud

`runtime_*`, `console_learning_snapshots`, `integration_*`, `performance_device_assignments`, `soundchecks`, `readiness_records`.

---

End of Data Migration Checklist
