# Implementation Backlog

Status: Planning queue — governance and domain phases only until operator authorises implementation  
Authority: `docs/DECISION_LOG.md`, `AGENTS.md`

Items are listed in priority order unless noted. Implementation phases require explicit operator authorisation and applicable gate sign-off.

---

## Queued

| ID | Title | Status | Depends on | Notes |
|----|-------|--------|------------|-------|
| **PH063-runbook** | Cloud Recovery Execution Runbook | Queued | PH064 pass, Gate 2 | Operator F0–F6; Gates 3–6. |
| **PH061B** | Console Templates Domain | Queued | PH061A | Discover, classify, and define reusable console templates — relationship to `show_console_baselines`, `effect_packages`, and CCMM-12. Resolves PH061A operator decision: merge with baselines vs separate entity. |

---

## Completed (reference)

| ID | Title | Document |
|----|-------|----------|
| PH064 | CCMM Migration Loader & Local Validation | `docs/PH064_CCMM_LOCAL_VALIDATION_REPORT.md` |
| PH063 | CCMM Migration Package Authoring | `database/migrations/ccmm/` |
| PH062 | CCMM Migration Authoring Plan | `docs/PH062_CCMM_MIGRATION_AUTHORING_PLAN.md` |
| PH061A | X32 Console Domain Discovery and Classification | `docs/PH061A_X32_CONSOLE_DOMAIN_DISCOVERY.md` |
| PH061 | Cloud Recovery Execution Plan | `docs/PH061_CLOUD_RECOVERY_EXECUTION_PLAN.md` |
| PH060 | CCMM Implementation Gap Analysis | `docs/PH060_CCMM_IMPLEMENTATION_GAP_ANALYSIS.md` |
| PH059 | Cloud Canonical Migration Manifest | `docs/PH059_CLOUD_CANONICAL_MIGRATION_MANIFEST.md` |
