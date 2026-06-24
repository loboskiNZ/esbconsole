# PH065 — Forensic Export Checklist

Status: Gate 1 evidence — **read-only exports only**  
Authority: PH056 §4.1, PH065 R1  
Date: 2026-06-24

**Target:** Production forensic cluster `pr-esbdata-68105.db.on-forge.com` / `defaultdb` (re-verify before execution).

---

## Database

| # | Item | Command / method | Done | Evidence path | SHA256 |
|---|------|------------------|:----:|---------------|--------|
| D1 | Full custom `pg_dump` | `pg_dump -Fc -f forensic/defaultdb_full.dump` | ☐ | | |
| D2 | Schema-only dump | `pg_dump --schema-only -f forensic/defaultdb_schema.sql` | ☐ | | |
| D3 | Migrations table export | `\copy migrations TO migrations.csv CSV HEADER` | ☐ | | |
| D4 | Row counts — core tables | See §Row counts | ☐ | | |
| D5 | FK inventory | Query `information_schema` / `pg_constraint` | ☐ | | |
| D6 | DO cluster snapshot | DigitalOcean console snapshot | ☐ | | |
| D7 | PITR point noted | Record earliest restorable timestamp | ☐ | | |

### Row counts (minimum)

| Table | Count | Notes |
|-------|------:|-------|
| `users` | | |
| `people` | | |
| `bands` | | |
| `invite_links` | | Quarantined |
| `invite_link_acceptances` | | Quarantined |
| `songs` | | |
| `charts` | | |
| `migrations` | | Expect ~66 |

---

## Files

| # | Item | Source | Done | Evidence path | SHA256 |
|---|------|--------|:----:|---------------|--------|
| F1 | Chart PDFs / library | `PORTAL_LIBRARY_STORAGE_ROOT` / Spaces | ☐ | | |
| F2 | Snippet binaries | Live Stage storage / Spaces | ☐ | | |
| F3 | Profile images | `people.profile_photo_path` | ☐ | | |
| F4 | Person files | `person_files.file_path` | ☐ | | |
| F5 | Ableton show files | `ableton_show_files` if any | ☐ | | |
| F6 | Forge storage snapshot | `/home/forge/.../storage` | ☐ | | |

---

## Configuration

| # | Item | Source | Done | Evidence path |
|---|------|--------|:----:|---------------|
| C1 | Band Portal `.env` snapshot | Forge (redact secrets in stored copy) | ☐ | |
| C2 | Website `.env` snapshot | Forge | ☐ | |
| C3 | Forge site settings | Site ID, domain, web directory | ☐ | |
| C4 | Deploy scripts | `remote-deploy.sh`, deploy hook | ☐ | |
| C5 | Cron jobs | Forge scheduler | ☐ | |
| C6 | Database connection matrix | Site → DB host → database name | ☐ | |

---

## Verification

| # | Item | Done | Notes |
|---|------|:----:|-------|
| V1 | Checksum manifest created | ☐ | `forensic/manifest.sha256` |
| V2 | Export hashes recorded in incident log | ☐ | |
| V3 | Exports stored off-cluster | ☐ | Not only on Forge server |
| V4 | Operator verified dump restorable | ☐ | `pg_restore --list` on D1 |
| V5 | Gate 1 signed in incident log | ☐ | |

---

## Gate 1 pass criteria

- [ ] D1 + D3 complete minimum
- [ ] D6 or D7 backup path confirmed
- [ ] V1 + V2 complete
- [ ] No production DDL during export

---

End of Forensic Export Checklist
