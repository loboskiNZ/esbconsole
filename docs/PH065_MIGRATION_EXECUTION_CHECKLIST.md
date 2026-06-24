# PH065 — Migration Execution Checklist

Status: R3 — CCMM migrate on **new Cloud Database only**  
Authority: PH063, PH064, PH065 R3  
Date: 2026-06-24

**Validated locally:** PH064 PASS on `esb_ccmm_validation`.

---

## Pre-flight

| # | Check | Done |
|---|-------|:----:|
| M0 | Gate 2 signed | ☐ |
| M1 | DB host is **new** cluster (not `pr-esbdata-68105`) | ☐ |
| M2 | `server/` release includes PH064 loader | ☐ |
| M3 | Explicit `DB_*` env vars set (not forensic `.env` copy-paste unchecked) | ☐ |
| M4 | `backend/` not connected to target | ☐ |

---

## Package execution

Execute via single `php artisan migrate --force` (packages run in timestamp order). Validate after full run.

| Package | Tables / action | Execute | Validate | Rollback point |
|---------|-----------------|:-------:|:--------:|----------------|
| **Laravel 0001** | cache, jobs, sessions, password_reset_tokens | ☐ | ☐ | Before CCMM-01 |
| **CCMM-00** | Infrastructure manifest (no-op) | ☐ | ☐ | — |
| **CCMM-01** | `bands` | ☐ | ☐ | Drop through CCMM-01 |
| **CCMM-02** | reference tables | ☐ | ☐ | Through CCMM-02 |
| **CCMM-03** | people domain | ☐ | ☐ | Through CCMM-03 |
| **CCMM-04** | users, musicians, bands FK | ☐ | `users.public_id` | Through CCMM-04 |
| **CCMM-05** | songs, cues, instrument_parts | ☐ | ☐ | Through CCMM-05 |
| **CCMM-06** | import, charts, snippets | ☐ | charts FK | Through CCMM-06 |
| **CCMM-07** | actions (+ action_types seed) | ☐ | ☐ | Through CCMM-07 |
| **CCMM-08** | shows, performances | ☐ | ☐ | Through CCMM-08 |
| **CCMM-09** | devices, assignments | ☐ | ☐ | Through CCMM-09 |
| **CCMM-10** | venues, festivals | ☐ | ☐ | Through CCMM-10 |
| **CCMM-12** | effects, baselines, mix_moves | ☐ | `baseline_json` | Through CCMM-12 |
| **RECOVERY** | `cloud_recovery_entity_map` | ☐ | ☐ | Independent |
| **CCMM-11** | `person_invitations` | ☐ | ☐ | CCMM-11 only |

---

## Post-migrate validation (Gate 3)

```bash
php artisan ccmm:validate-schema --json | tee recovery/r3_validation.json
```

| Check | Criterion | Pass |
|-------|-----------|:----:|
| CCMM tables | 48 / 48 | ☐ |
| Forbidden absent | no `invite_links`, `runtime_*`, `integration_*` | ☐ |
| FK orphans | 0 | ☐ |
| Index spot-check | per PH064 | ☐ |
| Migration count | 25 rows in `migrations` | ☐ |

---

## Rollback triggers

| Condition | Action |
|-----------|--------|
| Migrate fails mid-run | `migrate:rollback --step=N` if safe; else drop DB + R2 |
| `ccmm:validate-schema` fails | STOP — no R4; fix forward in Git |
| Wrong database targeted | STOP — assess forensic impact; rollback runbook |

---

End of Migration Execution Checklist
