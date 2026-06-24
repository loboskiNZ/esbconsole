# Implementation Backlog

Status: Planning queue — governance and domain phases only until operator authorises implementation  
Authority: `docs/DECISION_LOG.md`, `AGENTS.md`

Items are listed in priority order unless noted. Implementation phases require explicit operator authorisation and applicable gate sign-off.

---

## Queued

| ID | Title | Status | Depends on | Notes |
|----|-------|--------|------------|-------|
| **PH065-exec** | Cloud Recovery Execution | Blocked | Gate 2 sign-off | R1–R10 per runbook; no execution until signed. |
| **PH067A** | Recovery Tooling Implementation | **Complete** | PH066 plan | Artisan `recovery:*` commands in `/server/` |
| **PH067B** | Local Dry-Run Rehearsal | **Complete** | PH067A | `esb_dev` → `esb_recovery_validation`; Gate 4 CONDITIONAL FAIL |
| **PH068** | Recovery Defect Resolution Plan | **Complete** | PH067B findings | Planning only — `PH068_RECOVERY_DEFECT_RESOLUTION_PLAN.md` |
| **PH069** | Recovery Tooling Corrections | Queued | PH068 plan | Deferred FK, effect transform, file roots |
| **PH067C** | Second Local Recovery Rehearsal | Queued | PH069 | `esb_dev` → `esb_recovery_validation`; Gate 4 re-test |
| **PH061B** | Console Templates Domain | Queued | PH061A | Discover, classify, and define reusable console templates — relationship to `show_console_baselines`, `effect_packages`, and CCMM-12. Resolves PH061A operator decision: merge with baselines vs separate entity. |

---

## Completed (reference)

| ID | Title | Document |
|----|-------|----------|
| PH066 | Data Migration & Verification Tooling Plan | `docs/PH066_DATA_MIGRATION_AND_VERIFICATION_TOOLING_PLAN.md` |
| PH065 | Cloud Recovery Runbook & Gate Package | `docs/PH065_CLOUD_RECOVERY_RUNBOOK.md` |
| PH064 | CCMM Migration Loader & Local Validation | `docs/PH064_CCMM_LOCAL_VALIDATION_REPORT.md` |
| PH063 | CCMM Migration Package Authoring | `database/migrations/ccmm/` |
| PH062 | CCMM Migration Authoring Plan | `docs/PH062_CCMM_MIGRATION_AUTHORING_PLAN.md` |
| PH061A | X32 Console Domain Discovery and Classification | `docs/PH061A_X32_CONSOLE_DOMAIN_DISCOVERY.md` |
| PH061 | Cloud Recovery Execution Plan | `docs/PH061_CLOUD_RECOVERY_EXECUTION_PLAN.md` |
| PH060 | CCMM Implementation Gap Analysis | `docs/PH060_CCMM_IMPLEMENTATION_GAP_ANALYSIS.md` |
| PH059 | Cloud Canonical Migration Manifest | `docs/PH059_CLOUD_CANONICAL_MIGRATION_MANIFEST.md` |
