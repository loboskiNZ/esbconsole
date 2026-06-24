# PH069 — Recovery Tooling Corrections Report

Status: **Implementation complete** — local tooling only  
Date: 2026-06-25  
Authority: PH068 `docs/PH068_RECOVERY_DEFECT_RESOLUTION_PLAN.md`, PH067B rehearsal  
**No production mutation.** No CCMM schema changes.

---

## Defects addressed

| Defect | Status | Implementation |
|--------|--------|----------------|
| **RD-01** Circular FK import ordering | **Addressed** | `RecoveryDeferredForeignKeyService` — Pass 1 defer director FK; Pass 3 replay |
| **RD-02** Band cascade failure | **Addressed** | `RecoveryCascadeGuardService` — hard-stop dependent domains; `dependency_block_report.json` |
| **RD-03** Effect transform drift | **Addressed** | `RecoveryEffectTransformService` — legacy column drop + x32 mapping |
| **RD-04** File path resolution | **Addressed** | `RecoveryFileResolutionService` + configurable `RECOVERY_*_ROOT` |
| **RD-05** Dependency visibility | **Addressed** | `recovery:plan` v2 — dependency tree, critical path, deferred FK path |
| **RD-06** Verification noise | **Addressed** | `verification_report.json` v2 with structured sections |

---

## Implementation summary

### New services

| Service | Purpose |
|---------|---------|
| `RecoveryDeferredForeignKeyService` | `deferred_fk.json`, replay, `deferred_fk_report.json` |
| `RecoveryEffectTransformService` | Legacy effect_library mapping, `effect_transform_report.json` |
| `RecoveryFileResolutionService` | Multi-root path resolution + missing file classification |
| `RecoveryCascadeGuardService` | Band-fail cascade blocking |
| `RecoverySchemaColumnFilter` | Target-schema column allowlist on import |

### Updated services

| Service | Changes |
|---------|---------|
| `RecoveryImportExecutor` | Deferred FK passes, effect transform, column filter, cascade guard |
| `RecoveryTransformService` | Emits `deferred_fk.json`; warms effect maps |
| `RecoveryFileManifestService` | Uses file resolution; writes `missing_files_report.json` |
| `RecoveryVerifyService` | Report schema v2 |
| `RecoveryPlanService` | Plan schema v2 with dependency visibility |

### New batch artifacts

| File | Schema |
|------|--------|
| `deferred_fk.json` | `esb.recovery.deferred_fk/v1` |
| `deferred_fk_report.json` | `esb.recovery.deferred_fk_report/v1` |
| `effect_transform_report.json` | `esb.recovery.effect_transform_report/v1` |
| `missing_files_report.json` | `esb.recovery.missing_files_report/v1` |
| `dependency_block_report.json` | `esb.recovery.dependency_block_report/v1` |
| `verification_report.json` | `esb.recovery.verification_report/v2` |

### Config (`server/config/recovery.php`)

```text
RECOVERY_CHART_ROOT
RECOVERY_SNIPPET_ROOT
RECOVERY_PROFILE_ROOT
RECOVERY_ABLETON_ROOT
PORTAL_LIBRARY_STORAGE_ROOT (fallback)
```

---

## Tests

23 Recovery tests passing (13 → 23 added):

- `RecoveryDeferredForeignKeyServiceTest` — capture, replay, defer column
- `RecoveryEffectTransformServiceTest` — mapping, ambiguous report
- `RecoveryFileAndCascadeTest` — resolution, classification, cascade guard
- `RecoveryVerifyServiceV2Test` — v2 report sections
- Updated existing Recovery command/transform/report tests

```bash
cd server && php artisan test --filter=Recovery
# 23 passed
```

---

## Remaining known issues

1. **Full rehearsal not re-run in PH069** — corrections implemented; PH067C must validate against `esb_dev` → `esb_recovery_validation`.
2. **Chart files** — resolution logic present; operator must mount `RECOVERY_CHART_ROOT` for G4-7/G4-9.
3. **Ambiguous effect mappings** — reported in `effect_transform_report.json`; operator review required when multiple `effects` match x32 keys.
4. **Band partial failure** — cascade guard blocks downstream domains if any band row fails; operator may need source data fix for band id=1 director FK.
5. **Cloud recovery** — still blocked (PH065 Gate 2 + PH067C PASS).

---

## PH067C readiness assessment

| Prerequisite | Ready |
|--------------|-------|
| Deferred FK three-pass import | **Yes** |
| Effect column drift handling | **Yes** |
| File root configuration | **Yes** (operator env required) |
| Cascade hard-stop + reports | **Yes** |
| Verification v2 / Gate 4 readiness blockers | **Yes** |
| Local safety guards unchanged | **Yes** |

**Recommendation:** Proceed to **PH067C** — second local recovery rehearsal with:

- Fresh `esb_recovery_validation`
- `RECOVERY_CHART_ROOT` pointed at local chart library
- Full pipeline dry-run ×2 then execute per PH066 §8

---

End of PH069 report
