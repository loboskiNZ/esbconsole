# PH064 — CCMM Migration Loader and Local PostgreSQL Validation

Status: Local validation complete — **no production mutation**  
Date: 2026-06-24  
Database: `esb_ccmm_validation` on local Docker PostgreSQL (`backend-postgres-1`, port 5432)

---

## Loader wiring

| App | Mechanism |
|-----|-----------|
| `server/` | `AppServiceProvider::loadMigrationsFrom()` via `database/ccmm_migration_paths.php` |
| `backend/` | Same governed paths — no file duplication |

Paths loaded:

- `database/migrations/ccmm/`
- `database/migrations/recovery/`

## Archived server forks

11 migrations moved to `server/database/migrations/_archived_ccmm_forks/` (not auto-loaded).

Laravel `0001_*` retained; `users` table removed from `0001` — owned by CCMM-04.

## Validation result

| Check | Result |
|-------|--------|
| `migrate:fresh` | **PASS** |
| CCMM tables (48) | **48 / 48** |
| Total public tables | **56** (48 CCMM + 8 Laravel infra) |
| Missing tables | **none** |
| Unexpected tables | **none** |
| Forbidden tables | **none** |
| FK orphan violations | **none** |
| `ccmm:validate-schema` | **PASS** |
| `CcmmFreshMigrateTest` | **PASS** (1 test, 12 assertions) |
| CCMM-11 rollback/re-migrate | **PASS** |

### Index spot-checks

| Index | Present |
|-------|---------|
| `users_public_id_unique` | yes |
| `users_username_lower_unique` | yes |
| `charts_import_batch_id_foreign` | yes |
| `snippets_active_sip_cue_unique` | yes |

### Package execution order

```text
0001_* (Laravel infra)
2026_06_23_161000, 162000 (storage helpers)
CCMM-00 → CCMM-01 → … → CCMM-10 → CCMM-12 → RECOVERY → CCMM-11
```

## Commands used (local only)

```bash
docker compose -f backend/compose.foundation.yaml up -d postgres
docker exec backend-postgres-1 psql -U esb -d esb_dev -c "CREATE DATABASE esb_ccmm_validation;"

cd server && APP_ENV=local DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 \
  DB_DATABASE=esb_ccmm_validation DB_USERNAME=esb DB_PASSWORD=esb_secret \
  php artisan migrate:fresh --force

php artisan ccmm:validate-schema --json
php artisan test --filter=CcmmFreshMigrateTest
```

**Not used:** production `.env`, Forge, remote SSH, DigitalOcean cluster.

---

End of PH064 validation report
