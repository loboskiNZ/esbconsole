# CCMM Migration Packages (PH063)

Sole schema authority: `docs/PH059_CLOUD_CANONICAL_MIGRATION_MANIFEST.md`  
Authoring plan: `docs/PH062_CCMM_MIGRATION_AUTHORING_PLAN.md`

## Execution order

| Order | Package | Files |
|------:|---------|-------|
| 0 | CCMM-00 Infrastructure | Manifest only — Laravel infra in `server/database/migrations/0001_*` |
| 1 | CCMM-01 Foundation | `000100_ccmm01_*` |
| 2 | CCMM-02 Reference Data | `000200_ccmm02_*` |
| 3 | CCMM-03 People | `000300_ccmm03_*` |
| 4 | CCMM-04 Identity & Roster | `000400_ccmm04_*` |
| 5 | CCMM-05 Music Library | `000500_ccmm05_*` |
| 6 | CCMM-06 Charts & Import | `000600_ccmm06_*` |
| 7 | CCMM-07 Actions | `000700_ccmm07_*` |
| 8 | CCMM-08 Shows & Performances | `000800_ccmm08_*` |
| 9 | CCMM-09 Devices & Assignments | `000900_ccmm09_*` |
| 10 | CCMM-10 Venues & Festivals | `001000_ccmm10_*` |
| 11 | CCMM-12 X32 Console | `001200_ccmm12_*` (before CCMM-11 per operator B12) |
| 12 | RECOVERY | `../recovery/001250_*` |
| 13 | CCMM-11 Invitations | `001300_ccmm11_*` |

**Not included:** `invite_links`, `invite_link_acceptances`, `runtime_*`, `console_learning_snapshots`, `integration_*`, Spatie permissions (LS-EXT).

Reference seeds (F2): `InstrumentCatalog`, `SongMetadataReferenceSeeder` — not in migration data inserts.
