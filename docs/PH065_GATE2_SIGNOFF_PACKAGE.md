# PH065 — Gate 2 Operator Sign-off Package

Status: **Awaiting operator signature** — production execution blocked until signed  
Date: 2026-06-24  
Authority: PH061, PH064, PH065

---

## Current status

| Item | Status |
|------|--------|
| PH056 Path B (fresh Cloud DB) | **Approved for planning** |
| PH059 CCMM | **Authoritative** |
| PH062 authoring blueprint | **Complete** |
| PH063 migration packages | **Authored** (`database/migrations/ccmm/`) |
| PH064 local PostgreSQL validation | **PASS** (48/48 CCMM tables) |
| Production Cloud recovery execution | **BLOCKED** — pending Gate 2 |
| Forensic `defaultdb` | **Contaminated** — export required before cutover |
| Data import tooling | **Not implemented** — R5 blocked until PH066+ |
| PH048B onboarding | **Not enabled** post-recovery until Gate 5 + CCMM-11 |

---

## Risks

| Risk | Level | Mitigation |
|------|-------|------------|
| Migrate against wrong database | **Critical** | Explicit env vars; checklist; no Forge auto-migrate until R3 verified |
| Data loss from forensic DB | **High** | R1 exports before any mutation |
| Incomplete data import tooling | **High** | R5 deferred until tooling exists; empty Cloud acceptable initially |
| Website co-tenancy misconfiguration | **Medium** | Document both Forge sites in R2 checklist |
| Live Stage accidental Cloud connection | **Critical** | Verify `backend/` `.env` isolation |
| Onboarding enabled too early | **Medium** | Gate 5 criteria; PH048B separate sign-off |
| `mix_moves` placeholder only | **Low** | No behavioural impact until M5 |

---

## Assumptions

1. New DigitalOcean PostgreSQL 16 cluster will be provisioned — forensic `defaultdb` is **not** migrated in place.
2. CCMM migrations authored in PH063 are the **only** shared-schema DDL path on Cloud.
3. Live Stage `esb_dev` is authoritative source for optional data migration (if operator chooses migrate vs empty start).
4. Production assets (charts, snippets) migrate to DigitalOcean Spaces.
5. `invite_links` quarantined — no data migration from quarantined tables.
6. PH054 sync engine is **out of scope** for this recovery.

---

## Operator decisions already approved

| # | Decision |
|---|----------|
| 1 | Repo-root `database/migrations/ccmm/` |
| 2 | Include CCMM-12 in initial Cloud build |
| 3 | Merge `effect_library_*` into `effects` catalogue |
| 4 | Keep Laravel `0001_*` separate from CCMM-00 |
| 5 | Spatie permissions remain LS-EXT |
| 6 | B12 (X32) before B11 (invitations) |
| 7 | Authorise PH063 migration authoring |
| 8 | PH056 Path B fresh Cloud Database |

---

## Operator decisions still required

| # | Decision | Default |
|---|----------|---------|
| 1 | New cluster name and database name | `esb-cloud-prod` / `esb_cloud` |
| 2 | Recovery window date/time | Operator |
| 3 | Migrate Live Stage data vs empty Cloud | Operator |
| 4 | Website co-tenant on new cluster | Yes (per PH056) |
| 5 | Forensic `defaultdb` decommission date | After Gate 6 |
| 6 | Authorise R3 migrate on production new cluster | **This sign-off** |
| 7 | Maintenance mode during R8 cutover | Recommended |

---

## Execution authority statement

By signing below, the operator authorises execution of **PH065 Cloud Recovery Runbook** phases **R1–R10** against the **new isolated Cloud Database** only, subject to:

- Completion of Gate 1 forensic exports before R2 mutation on production infrastructure
- Per-phase checklists and gate pass criteria in `docs/PH065_GATE_SUMMARY.md`
- **No** manual `INSERT INTO migrations` or ad hoc DDL
- **No** `backend/` migrate against Cloud Database
- Immediate STOP if rollback triggers in `docs/PH065_ROLLBACK_RUNBOOK.md` fire

**This signature does not authorise:**

- Feature development on production
- Live Stage database mutation during Cloud recovery
- PH048B public onboarding until Gate 5 criteria met
- Decommission of forensic database until Gate 6

---

## Operator approval

| Field | Value |
|-------|-------|
| **Operator name** | _________________________________ |
| **Operator Approval** | ☐ I approve Gate 2 and authorise Cloud recovery per PH065 |
| **Date** | _________________________________ |
| **Recovery window** | From: ______________ To: ______________ |
| **Rollback window** | Until: ______________ (must cover R8 + R9) |
| **Notes** | _________________________________ |

**Incident log reference:** _________________________________

---

End of Gate 2 Sign-off Package
