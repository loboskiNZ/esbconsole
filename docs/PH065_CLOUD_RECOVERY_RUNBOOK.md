# PH065 — Cloud Recovery Runbook

Status: **Operational manual only** — no production execution authorised by this document  
Authority: PH056 Path B, PH061, PH063, PH064  
Date: 2026-06-24

**Execution is blocked until Gate 2 sign-off** (`docs/PH065_GATE2_SIGNOFF_PACKAGE.md`).

---

## Authority chain

```text
PH059 CCMM → PH063 migrations → PH064 local validation → Gate 2 sign-off → this runbook
```

| Workspace | Database | App path |
|-----------|----------|----------|
| Cloud Studio | **New** Cloud Database (not `defaultdb`) | `/server/` |
| Website | Same Cloud Database (if co-tenant) | Forge site |
| Live Stage | **Unchanged** during Cloud recovery | `/backend/` |

---

## Phase R0 — Preparation

| Field | Detail |
|-------|--------|
| **Objective** | Confirm prerequisites, freeze feature work, assign roles |
| **Prerequisites** | PH064 PASS; Gate 2 not yet signed |
| **Commands** | None on production |

**Operator actions**

1. Confirm PH055 Production Safety Rules acknowledged by all participants.
2. Complete `docs/PH065_GATE2_SIGNOFF_PACKAGE.md` review.
3. Schedule recovery window and rollback window.
4. Disable Forge deploy hooks / auto-migrate on Band Portal until R3 complete.
5. Confirm Live Stage `backend/` `.env` does **not** point at new Cloud cluster.

**Expected outputs:** Signed Gate 2 package; incident log entry; maintenance page ready (optional).

**Validation:** Gate 2 signed; recovery window recorded.

**Rollback trigger:** Missing sign-off → **STOP** — do not proceed to R1.

---

## Phase R1 — Forensic Capture

| Field | Detail |
|-------|--------|
| **Objective** | Immutable export of contaminated production state |
| **Prerequisites** | Gate 1 evidence (may complete with R1); Gate 2 signed |
| **Checklist** | `docs/PH065_FORENSIC_EXPORT_CHECKLIST.md` |

**Commands (operator — read-only / export only)**

```bash
# Example — adjust host/credentials from Forge; run from operator workstation
pg_dump "postgresql://USER:PASS@pr-esbdata-68105.db.on-forge.com:25060/defaultdb?sslmode=require" \
  --format=custom --file=forensic/defaultdb_$(date +%Y%m%d_%H%M%S).dump

pg_dump ... --schema-only --file=forensic/defaultdb_schema_$(date +%Y%m%d).sql

psql ... -c "\copy (SELECT * FROM migrations ORDER BY id) TO 'forensic/migrations_$(date +%Y%m%d).csv' CSV HEADER"
```

**Expected outputs:** `pg_dump` file; schema dump; migrations CSV; row-count manifest; SHA256 hashes per `PH065_FORENSIC_EXPORT_CHECKLIST.md`.

**Validation:** Gate 1 pass — all checklist items checked; hashes recorded.

**Rollback trigger:** Export failure → retry; do not mutate production until export verified or operator accepts risk.

---

## Phase R2 — Cloud Database Provisioning

| Field | Detail |
|-------|--------|
| **Objective** | Provision **new** isolated PostgreSQL cluster (PH056 Path B) |
| **Prerequisites** | Gate 1 + Gate 2; forensic export retained |
| **Checklist** | `docs/PH065_CLOUD_PROVISIONING_CHECKLIST.md` |

**Commands (DigitalOcean / Forge — operator console)**

1. Create Managed PostgreSQL 16 cluster (new name, e.g. `esb-cloud-prod`).
2. Create database `esb_cloud` (operator-approved name).
3. Create application user with least privilege; store credentials in Forge **only** for Cloud Studio.
4. Enable automated backups + PITR.
5. **Do not** update Band Portal `.env` until R3 Gate 3 pass.

**Expected outputs:** Connection string documented; firewall rules; backup policy enabled; empty database.

**Validation:** `psql` connect test from operator IP; `SELECT version();` → PostgreSQL 16+.

**Rollback trigger:** Wrong cluster/credentials → delete empty cluster; reprovision. Forensic DB untouched.

---

## Phase R3 — CCMM Migration Execution

| Field | Detail |
|-------|--------|
| **Objective** | Apply CCMM schema to **new** Cloud Database only |
| **Prerequisites** | R2 complete; `server/` release includes PH063/PH064 loader |
| **Checklist** | `docs/PH065_MIGRATION_EXECUTION_CHECKLIST.md` |

**Commands (against NEW cluster only — use explicit env, not production `.env`)**

```bash
cd server

export APP_ENV=production
export DB_CONNECTION=pgsql
export DB_HOST=<NEW_CLUSTER_HOST>
export DB_PORT=25060
export DB_DATABASE=esb_cloud
export DB_USERNAME=<NEW_USER>
export DB_PASSWORD=<NEW_PASSWORD>
export DB_SSLMODE=require

php artisan migrate --force
php artisan ccmm:validate-schema --json | tee recovery/r3_schema_validation.json
```

**Expected outputs:** 25 migration rows in `migrations` table; 48 CCMM tables; 0 forbidden tables; `ccmm:validate-schema` → `passed: true`.

**Validation:** Gate 3 — see `docs/PH065_GATE_SUMMARY.md`.

**Rollback trigger:** Migrate failure → `migrate:rollback` if safe; else drop `esb_cloud` and reprovision R2. See `docs/PH065_ROLLBACK_RUNBOOK.md`.

---

## Phase R4 — Reference Data Seeding

| Field | Detail |
|-------|--------|
| **Objective** | Seed reference catalogues required for Studio operation |
| **Prerequisites** | Gate 3 pass |
| **Commands** | |

```bash
# Same DB env as R3
php artisan db:seed --class=InstrumentCatalog
php artisan db:seed --class=SongMetadataReferenceSeeder
# Default band seed — operator-approved only
# Effects catalogue (CCMM-12) — EffectsAlgorithmReferenceSeeder when implemented
```

**Expected outputs:** `instrument_reference` ≥17 rows; `song_moods`, `time_signatures`, `musical_keys` ≥1 each; optional default `bands` row.

**Validation:** SQL row counts match PH061 §8.2 reference seed criteria.

**Rollback trigger:** Bad seed → truncate seeded tables; re-seed. Do not proceed to R5.

---

## Phase R5 — Data Migration

| Field | Detail |
|-------|--------|
| **Objective** | Import shared entity rows from Live Stage (or accepted source) |
| **Prerequisites** | Gate 3 pass; `cloud_recovery_entity_map` table exists |
| **Checklist** | `docs/PH065_DATA_MIGRATION_CHECKLIST.md` |

**Commands:** Governed import tooling (PH066+ when implemented). **No ad hoc SQL.**

```bash
# Placeholder — implement before execution
# php artisan recovery:import-domain bands --batch=<UUID>
# php artisan recovery:import-domain songs --batch=<UUID>
```

**Expected outputs:** `cloud_recovery_entity_map` populated; row counts match source manifest.

**Validation:** Gate 4 data checks — row counts, `public_id` completeness, FK integrity.

**Rollback trigger:** Import failure → delete batch via `batch_id` in reverse dependency order. See rollback runbook.

---

## Phase R6 — File Migration

| Field | Detail |
|-------|--------|
| **Objective** | Upload production assets to Cloud-canonical storage (Spaces) |
| **Prerequisites** | R5 complete for file-linked entities |
| **Checklist** | `docs/PH065_FILE_MIGRATION_CHECKLIST.md` |

**Commands:** Governed upload scripts with checksum manifest (PH066+).

**Expected outputs:** 100% checksum match; `storage_reference` resolvable for all migrated rows.

**Validation:** Gate 4 file acceptance.

**Rollback trigger:** Checksum mismatch → pause R7; do not cut over.

---

## Phase R7 — Application Validation

| Field | Detail |
|-------|--------|
| **Objective** | Validate Cloud Studio (+ Website) against new DB **before** public cutover |
| **Prerequisites** | R3–R6 complete; temporary `.env` pointing at new cluster on staging or local tunnel |
| **Checklist** | `docs/PH065_APPLICATION_VALIDATION_CHECKLIST.md` |

**Commands**

```bash
curl -sS -o /dev/null -w "%{http_code}" https://<staging-or-tunnel>/up
# Manual smoke: login page, Studio library read paths
```

**Expected outputs:** HTTP 200 `/up`; no 500 on auth routes; charts readable if data migrated.

**Validation:** Gate 5 partial (pre-cutover). Onboarding remains **disabled** until PH048B + CCMM-11 sign-off.

**Rollback trigger:** Application 500 with new DB → do not proceed to R8.

---

## Phase R8 — Cutover

| Field | Detail |
|-------|--------|
| **Objective** | Point Band Portal Forge site to new Cloud Database |
| **Prerequisites** | Gate 3 + Gate 4 + Gate 5 pre-cutover pass |
| **Commands** | |

1. Enable maintenance mode (optional).
2. Update Forge `.env` `DB_*` to new cluster credentials.
3. Deploy governed release via `remote-deploy.sh` (migrate **already applied** — deploy must not re-run on wrong DB).
4. Verify deploy hook does not target forensic `defaultdb`.

**Expected outputs:** Production site live on new cluster; forensic cluster read-only retained.

**Rollback trigger:** Post-cutover failure → R8 rollback path in `docs/PH065_ROLLBACK_RUNBOOK.md`.

---

## Phase R9 — Post-Cutover Validation

| Field | Detail |
|-------|--------|
| **Objective** | Confirm production health after cutover |
| **Prerequisites** | R8 complete |
| **Validation** | Gate 5 full — `docs/PH065_APPLICATION_VALIDATION_CHECKLIST.md` |

**Expected outputs:** Health check pass; operator sign-off on production smoke tests.

**Rollback trigger:** Critical failure within rollback window → revert `.env` to maintenance + forensic read-only.

---

## Phase R10 — Incident Closure

| Field | Detail |
|-------|--------|
| **Objective** | Close production incident; schedule forensic DB decommission |
| **Prerequisites** | Gate 5 pass; stable operation agreed |
| **Actions** | |

1. Record Gate 6 in `docs/DECISION_LOG.md`.
2. Schedule Live Stage realignment (PH061 §11) — separate window.
3. Plan forensic `defaultdb` decommission date (operator decision).
4. Re-enable governed deploy pipeline with CCMM-only migrate path.

**Validation:** Gate 6 — incident closure documented.

---

## Related documents

| Document | Purpose |
|----------|---------|
| `PH065_GATE2_SIGNOFF_PACKAGE.md` | Operator approval |
| `PH065_FORENSIC_EXPORT_CHECKLIST.md` | R1 |
| `PH065_CLOUD_PROVISIONING_CHECKLIST.md` | R2 |
| `PH065_MIGRATION_EXECUTION_CHECKLIST.md` | R3 |
| `PH065_DATA_MIGRATION_CHECKLIST.md` | R5 |
| `PH065_FILE_MIGRATION_CHECKLIST.md` | R6 |
| `PH065_APPLICATION_VALIDATION_CHECKLIST.md` | R7/R9 |
| `PH065_ROLLBACK_RUNBOOK.md` | All phases |
| `PH065_GATE_SUMMARY.md` | Gates 1–6 |

---

End of PH065 Cloud Recovery Runbook — manual only
