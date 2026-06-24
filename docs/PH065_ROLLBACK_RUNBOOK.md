# PH065 — Rollback Runbook

Status: Operational — use when recovery failure conditions met  
Authority: PH061 §9, PH065  
Date: 2026-06-24

---

## Recovery failure conditions

STOP and initiate rollback when any of:

1. Migrate executed against **wrong** database (forensic or Live Stage)
2. `ccmm:validate-schema` fails after R3
3. Data import produces FK orphans or count mismatch beyond tolerance
4. File checksum mismatch > 0
5. Post-cutover application 500 on critical paths within rollback window
6. Manual DDL or `INSERT INTO migrations` was required (governance violation — STOP all work)

---

## Rollback paths

### Path A — Before migration (R2 only)

| Trigger | Wrong empty cluster provisioned |
| Action | Delete new cluster/database; reprovision R2 |
| Live Stage | Unchanged |
| Forensic | Unchanged |
| Evidence | R2 checklist |

### Path B — After migration, before data load (R3 complete)

| Trigger | Schema validation fail; unrecoverable migrate error |
| Action | Drop `esb_cloud` database or entire new cluster; return to R2 |
| Alternative | `migrate:rollback --step=N` only if safe and verified on **new** cluster |
| Live Stage | Unchanged |
| Forensic | Unchanged |
| **Never** | Manual migrations table edits |

### Path C — After data load, before cutover (R5/R6)

| Trigger | Import failure; checksum mismatch |
| Action | `recovery:rollback-batch --batch=<UUID>` (PH066+) or delete imported rows via map table in reverse FK order |
| Restore | Pre-R5 snapshot of new cluster if PITR available |
| Cutover | **Do not** proceed to R8 |
| Live Stage | Unchanged |

### Path D — After cutover (R8/R9)

| Trigger | Production 500; data corruption detected in rollback window |
| Action | |
| D1 | Enable maintenance mode | |
| D2 | Revert Forge `.env` `DB_*` to forensic read-only cluster OR last known good |
| D3 | Redeploy previous release if needed | |
| D4 | Document incident; do not mutate forensic DB | |
| D5 | Assess new cluster state — may drop and restart from R2 | |

---

## Forbidden rollback actions

- Mutating Live Stage database during Cloud recovery
- `INSERT INTO migrations` on any environment
- Ad hoc `ALTER TABLE` to “fix forward” in production
- Deleting forensic exports

---

## Evidence retention

| Item | Retain |
|------|--------|
| Forensic `pg_dump` | Until Gate 6 + operator decommission |
| `r3_schema_validation.json` | Permanent incident record |
| `cloud_recovery_entity_map` | Until batch rollback complete |
| Gate sign-off documents | Permanent |

---

## Escalation

If rollback path unclear → **STOP**; treat forensic export as restore of last resort; record in DECISION_LOG before further action.

---

End of Rollback Runbook
