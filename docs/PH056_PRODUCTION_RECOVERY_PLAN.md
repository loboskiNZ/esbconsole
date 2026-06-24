# PH056 — Production Recovery Plan

Status: Planning only — **no production commands authorised by this document**  
Authority: `docs/PROJECT_CHARTER.md`, `docs/DECISION_LOG.md` PH055, `AGENTS.md` Production Safety Rules  
Date: 2026-06-24  
Related: `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md` §21, `server/docs/FORGE_SETUP.md`

This document defines the **operator-approved recovery procedure** required before any production mutation resumes. It does not authorise execution — the operator must explicitly select a recovery path and sign off each phase.

**Out of scope for PH056:** application code, migrations, deploys, seeders, onboarding, feature work, or SSH commands.

---

## 1. Purpose

Recover production data integrity and database topology in alignment with PH055:

- **Two physical databases:** Cloud Database, Live Stage Database
- **Three workspaces:** Cloud Studio, Website, Live Stage
- **Cloud Studio + Website** → Cloud Database only
- **Live Stage** → Live Stage Database only — **must not** share Cloud Database

Close the production incident opened during Band Portal login/onboarding investigation (2026-06-23/24).

---

## 2. Forensic Summary (read-only investigation — 2026-06-24)

Documented state at time of investigation. Re-verify before executing any recovery path.

### 2.1 Infrastructure

| Item | Observed value |
|------|----------------|
| Cloud Database host | `pr-esbdata-68105.db.on-forge.com:25060` |
| Database name | `defaultdb` |
| Engine | PostgreSQL (managed DigitalOcean) |
| Band Portal site | `band.edandtheshadowboys.com` |
| Second Forge site on same DB | `edandtheshadows-dxdworhs.on-forge.com` (Website / journey) |
| Active Band Portal release | `71987819` (Jun 23) — later deploys failed |
| Failed deploy cause (partial) | Wrong repo reference (`bbos-website`); missing `server/.env` symlink in some attempts |

### 2.2 Schema / migration contamination

| Item | Observed value |
|------|----------------|
| `migrations` table row count | 66 |
| Migration sources | `server/`, `backend/`, and Website app migrations applied to shared `defaultdb` |
| Duplicate migration names | e.g. two `create_bands_table`, two `create_band_people_schema` |
| Manual interventions (governance violation) | `ALTER TABLE users` via tinker; manual `INSERT INTO migrations`; `php artisan migrate --force` during incident |

### 2.3 Data state

| Table | Row count | Notes |
|-------|-----------|-------|
| `users` | 0 | `users_id_seq.last_value = 3` → historical user IDs existed |
| `people` | 0 | `people_id_seq` never advanced — onboarding never persisted |
| `invite_links` | 1 | `used_count = 0` |
| `invite_link_acceptances` | 0 | |
| `bands` | 1 | Seeded Jun 24 |
| `instrument_reference` | 17 | Seeded Jun 24 |
| `songs`, `charts` | 0 | |

### 2.4 Assessment

1. Portal onboarding **never successfully completed** on production — empty `people` is not evidence of post-success deletion alone.
2. Historical `users` rows were **lost or belonged to another app's lifecycle** on the shared database.
3. Shared `defaultdb` across Cloud Studio, Website, and backend migration history is **non-compliant** with PH055 Decision 185.
4. Manual migration history is **untrustworthy** — cannot be reconciled by further manual edits.

---

## 3. Recovery Objectives

| # | Objective |
|---|-----------|
| R1 | Isolate Cloud Database for Cloud Studio (+ Website if co-tenanted) |
| R2 | Ensure Live Stage (`/backend/`) migrations never run against Cloud Database |
| R3 | Establish trustworthy `migrations` history on Cloud Database |
| R4 | Recover or accept loss of portal identity data with operator sign-off |
| R5 | Restore governed deploy path for Band Portal without auto-migrate on contaminated DB |
| R6 | Document incident closure in `docs/DECISION_LOG.md` |

---

## 4. Mandatory Preconditions (all paths)

No production action until **all** preconditions are satisfied:

| # | Precondition | Owner |
|---|--------------|-------|
| P1 | Operator selects recovery path (§5) | Operator |
| P2 | DigitalOcean backup/PITR availability confirmed for target dates (especially pre–Jun 23) | Operator |
| P3 | Forensic export of current `defaultdb` (`pg_dump` or DO snapshot) before any mutation | Operator |
| P4 | Forge site → repo → web directory → database matrix documented and verified | Operator |
| P5 | All agents/developers acknowledge PH055 Production Safety Rules | Governance |
| P6 | Deploy hook and migrate steps **disabled or gated** until DB target confirmed | Operator |

### 4.1 Forensic export (required first step for any path)

Document only — execute when operator approves:

1. Create DO manual snapshot of `pr-esbdata-68105` **or** `pg_dump` full logical backup to secure storage.
2. Export `migrations` table to CSV for audit.
3. Export row counts for: `users`, `people`, `bands`, `invite_links`, `sessions`.
4. Record Forge environment variables for both sites (`DB_*`, `APP_ENV`).
5. Store backup location and timestamp in incident log (DECISION_LOG PH056).

---

## 5. Recovery Paths

### Path A — Point-in-Time Recovery (PITR) to isolated Cloud Database

**When to choose:** DO PITR available to a point **before** manual DDL and migration table edits, and operator needs historical portal data.

| Step | Action | Notes |
|------|--------|-------|
| A1 | Provision **new** DO PostgreSQL cluster (Cloud Database) | Do not reuse contaminated `defaultdb` |
| A2 | Restore via PITR to selected timestamp | Operator chooses timestamp after reviewing DO backup window |
| A3 | **Do not** attach Website or backend apps until schema audit complete | |
| A4 | Audit restored schema — list tables, migration count, duplicate names | |
| A5 | If restored DB still contains backend-only tables, plan selective schema cleanup via **new governed migrations** on fresh DB — not manual DDL | |
| A6 | Point `band.edandtheshadowboys.com` `.env` to new cluster | Website: separate decision — same Cloud DB or own DB |
| A7 | Run `php artisan migrate:status` from `/server/` only | Compare to expected `server/database/migrations/` |
| A8 | Operator verifies login/onboarding state before re-enabling invites | |

**Risks:** PITR may restore shared contamination if timestamp predates backend migrations. May restore lost `users` or may not — verify sequences vs rows.

**Rollback:** Keep forensic export (§4.1); revert `.env` to previous cluster only if no mutations on new cluster.

---

### Path B — Provision Fresh Isolated Cloud Database (recommended default)

**When to choose:** Manual migration history is untrustworthy; portal identity data is effectively empty; clean governed start is acceptable.

| Step | Action | Notes |
|------|--------|-------|
| B1 | Provision **new** DO PostgreSQL cluster: `esb-cloud` (name example) | Dedicated to Cloud Studio + optionally Website |
| B2 | Point Band Portal Forge `.env` to new cluster | `DB_DATABASE`, `DB_HOST`, credentials |
| B3 | **Empty database** — run **only** `server/` migrations via governed deploy | `php artisan migrate --force` from `server/` release path only |
| B4 | Run **only** `server/` seeders explicitly approved by operator | e.g. `instrument_reference`, default `bands` — document each |
| B5 | Leave old `defaultdb` **read-only forensic** — do not drop until incident closed | |
| B6 | Repoint Website Forge site to new Cloud Database **or** keep on legacy DB temporarily with documented exception | PH055 allows co-tenancy; migration ownership must be declared |
| B7 | Ensure `/backend/` Forge sites (if any) use **Live Stage Database** or local Docker — **never** new Cloud Database | |
| B8 | Create new Person-first invitations after PH048B implementation | `invite_links` model non-compliant per PH055 Decision 187 |

**Risks:** All prior portal accounts lost unless recovered from Path A. Website data on old `defaultdb` may be orphaned until repointed.

**Benefits:** Clean migration history; PH055 compliant topology; fastest path to trustworthy schema.

---

### Path C — Forensic Hold (no mutation)

**When to choose:** Backup availability uncertain; operator needs time to assess legal/data retention requirements.

| Step | Action |
|------|------|
| C1 | Complete §4.1 forensic export |
| C2 | Disable Band Portal deploy hook (or leave on failed release) |
| C3 | Display maintenance notice if required |
| C4 | No migrate, seed, or DDL on any environment |
| C5 | Re-evaluate Path A or B within agreed timeframe |

---

## 6. Forge Configuration Correction Plan (documentation only)

Verify and correct before next deploy. Reference: `server/docs/FORGE_SETUP.md`.

| Site | Setting | Required value |
|------|---------|----------------|
| `band.edandtheshadowboys.com` | Repository | `loboskiNZ/esbconsole` (monorepo) |
| | Web directory | `server/public` |
| | Deploy script | `server/deploy/forge-deploy.sh` |
| | PHP | 8.4 |
| | Database | **New Cloud Database** (post-recovery) — not shared with `/backend/` |
| `edandtheshadows-*.on-forge.com` | Database | Cloud Database (if co-tenanted) **or** separate — operator decision |
| Any Live Stage host | Database | Live Stage Database (local/Docker) — **not** Forge `defaultdb` |

### Deploy gating

Until recovery path complete:

1. **Do not** run `./server/deploy/remote-deploy.sh` automatically on push.
2. If deploy required for verification, use **read-only** health check only after DB target confirmed.
3. `forge-deploy.sh` runs `php artisan migrate --force` — **blocked** until Cloud Database is isolated and empty/governed.

---

## 7. Migration Reconciliation Plan

### 7.1 Problem

`defaultdb` contains migrations from:

| Codebase | Path | Target database (PH055) |
|----------|------|-------------------------|
| Band Portal | `server/database/migrations/` | Cloud Database |
| Live Stage foundation | `backend/database/migrations/` | Live Stage Database |
| Website (journey) | separate repo migrations | Cloud Database (if co-tenanted) |

### 7.2 Rules (post-recovery)

| Rule | Statement |
|------|-----------|
| M1 | **Never** run `backend/artisan migrate` against Cloud Database |
| M2 | Cloud Database migration authority: **`server/`** (+ governed Website migrations if co-tenanted) |
| M3 | Live Stage Database migration authority: **`backend/`** only |
| M4 | Duplicate migration **filenames** across codebases prohibited on same database |
| M5 | No manual `INSERT INTO migrations` — ever |
| M6 | Migration idempotency fixes belong in Git and apply on **fresh** DB or via forward migrations — not production patches |

### 7.3 Duplicate migrations to resolve (implementation phase — blocked until recovery)

| Migration concern | `backend/` | `server/` | Action (future) |
|-------------------|------------|-----------|-----------------|
| Band People schema | `2026_06_23_110000_create_band_people_schema` | `2026_06_23_131000_create_band_people_schema` | Single canonical migration per database; remove duplicate from wrong codebase |
| Bands table | `2026_06_10_140000_m2_create_bands_table` | `2026_06_23_130000_create_bands_table` | Cloud DB uses `server/` version only |

---

## 8. Database Tenancy Matrix (target state)

| Workspace | App path | Forge site | Physical database |
|-----------|----------|------------|-------------------|
| Cloud Studio | `/server/` | `band.edandtheshadowboys.com` | Cloud Database (new cluster) |
| Website | separate app | `edandtheshadows-*.on-forge.com` | Cloud Database (co-tenant) **or** separate — operator signs off |
| Live Stage | `/backend/` | local / not Forge cloud DB | Live Stage Database (local Docker) |

**Prohibited:** `/backend/` migrations on Cloud Database. **Prohibited:** Live Stage app pointing at Forge `defaultdb`.

---

## 9. Verification Checklist (post-recovery)

Execute only after operator approves recovery path completion.

| # | Check | Expected |
|---|-------|----------|
| V1 | `curl https://band.edandtheshadowboys.com/up` | `200` — no 500 |
| V2 | `php artisan migrate:status` (server only) | All `server/` migrations Ran; no `backend/` migrations present |
| V3 | `users` table columns | `username`, `person_id`, `band_id`, `is_active` per PH047 |
| V4 | No duplicate migration names in `migrations` | Unique batch history |
| V5 | `backend` production `.env` (if any cloud) | **Not** pointing at Cloud Database |
| V6 | Forensic `defaultdb` | Read-only or decommissioned with export retained |
| V7 | DECISION_LOG PH056 incident closure entry | Operator sign-off recorded |

---

## 10. Explicit Prohibitions During Recovery

Until PH056 incident closure recorded:

- No ad hoc production DDL (`AGENTS.md`, PH055 Decision 186)
- No manual `migrations` table edits
- No `migrate:fresh`, `db:wipe`, truncate, or seed on production without operator sign-off
- No onboarding / PH048B work
- No deploy to Band Portal except operator-approved verification deploy
- No merging uncommitted `server/` onboarding fixes until recovery path complete
- No sharing Cloud Database with Live Stage migrations

---

## 11. Post-Recovery Gates

| Gate | Unblocks |
|------|----------|
| PH056 incident closure (operator sign-off) | Governed Band Portal deploy |
| Cloud Database isolated + migration audit pass | `server/` migrate on deploy |
| PH048B implementation decision | Person-first `person_invitations`; remove `invite_links` drift |
| New invitations created under PH047 | Production onboarding |

---

## 12. Operator Decision Record (to be completed)

| Decision | Options | Selected | Date | Signed |
|----------|---------|----------|------|--------|
| Recovery path | A (PITR) / B (Fresh) / C (Hold) | _pending_ | | |
| Website co-tenancy | Same Cloud DB / separate DB | _pending_ | | |
| Accept portal identity loss | Yes / No | _pending_ | | |
| PITR timestamp (if A) | | _pending_ | | |
| Old `defaultdb` disposition | forensic read-only / decommission | _pending_ | | |

---

## 13. Rollback Notes

| Scenario | Rollback |
|----------|----------|
| New Cloud DB migrate fails | Do not patch manually; fix forward in Git; restore empty DB and re-migrate |
| Wrong DB connected in `.env` | Revert `.env` to forensic cluster; no migrate on wrong target |
| Deploy runs migrate on contaminated DB | Stop deploy hook immediately; forensic export if not done |

---

End of PH056 Production Recovery Plan — planning only
