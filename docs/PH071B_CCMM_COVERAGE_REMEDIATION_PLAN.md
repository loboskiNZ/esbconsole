# PH071B — CCMM Coverage Remediation Plan

Status: **Planning and sequencing only** — no production mutation, migrations, schema changes, or recovery execution  
Date: 2026-06-25  
Authority: PH071A audit, PH059 CCMM, PH070 readiness, PH054/ADR-001  
Input: `docs/PH071A_CCMM_DOMAIN_COVERAGE_AUDIT.md`

---

## 1. Purpose

PH071A concluded **CCMM Sufficient With Defined Gaps**. PH071B converts those gaps into a governed remediation programme with explicit sequencing, recovery-boundary classification, and operator decision points.

**This document does not authorise schema implementation.** Migration authoring for any track requires separate phase authorisation per `AGENTS.md` change process (CCMM proposal → governance → PH063-style migration package).

---

## 2. Recovery blocker assessment (PH071A confirmation)

PH071B **confirms** PH071A Decision 292:

> **No PH071A gap track blocks PH061 Cloud Recovery** on current CCMM v1, evidenced by PH064 schema validation and PH067C/D local rehearsals.

| Blocker type | Status |
|--------------|--------|
| CCMM schema gaps (B1–B6) | **Not recovery blocking** |
| PH065 Gate 1 (forensics) | **Recovery blocking** — operator pending |
| PH065 Gate 2 (sign-off) | **Recovery blocking** — operator pending |
| Cloud cluster provisioning | **Recovery blocking** — execution pending |
| PH048B onboarding (not a CCMM gap) | **Pre-Recovery Recommended** for public onboarding — not schema |

**Challenge review:** No track reclassified as Recovery Blocking after PH071B analysis. PH054 peer authoring absence would block **bidirectional song sync**, not **initial Cloud rebuild from Live Stage source**. M5 assets are referenced by cue Actions but recovery imports existing action domain without `mix_moves` rows in current source.

**Position:** Cloud Recovery may proceed after **Gate 1 and Gate 2** on **CCMM v1**. Remediation tracks B1–B4 target **CCMM v2+** post-recovery or parallel planning only.

---

## 3. Cloud recovery boundary

### Must be remediated before Cloud Recovery (R1+)

| Item | Track | Notes |
|------|-------|-------|
| Gate 1 forensic export | PH065 | Not CCMM |
| Gate 2 operator signature | PH065 | Not CCMM |
| **B5 documentation reconciliation** | B5 | **Documentation-only** — update PH059 Part B before PH071 authorisation so operators have accurate manifest text |

### May proceed without remediation (CCMM v1 sufficient)

All shared Part A entities, CCMM-12 effects/baselines, recovery entity map, venues/festivals core tables, action domain (without M5 asset tables).

### Post-recovery remediation (CCMM v2 extension)

| Track | Rationale |
|-------|-----------|
| **B1** PH054 sync package | Required for peer authoring; sync-before-show on recovered Cloud uses publish/pull process without checkout tables initially |
| **B2** M5 asset library | Required for full Action taxonomy execution; not in current Live Stage export volume for recovery |
| **B3** Festival workflow depth | Optional CRM depth; inline festival fields sufficient for cutover |
| **B4** X32 console registry | Live Stage show-day binding; baselines sufficient for show prep recovery |

### Application-level only (no CCMM change required for recovery)

| Item | Track |
|------|-------|
| Website routing and templates | B6 partial |
| Publication UI workflow | B6 partial |
| Demo seed row exclusion | Recovery operator config |
| `person_invitations` operational wiring | PH048B |

### CCMM v2 candidates

| Package | Tracks | Trigger |
|---------|--------|---------|
| **CCMM-15** PH054 Sync | B1 | Post Gate 4; before enabling peer authoring |
| **CCMM-16** M5 Master Library | B2 | Before Mix Move / Light Mode Action execution |
| **CCMM-17** Festival CRM (optional) | B3 | Operator requests submission history |
| **CCMM-18** Console Registry (optional) | B4 | Operator closes PH061A registry decision |
| **CCMM-19** Publication flags (optional) | B6 | Operator requires schema-backed public visibility |

---

## 4. Gap track detail

Classification key: **Recovery Blocking** | **Pre-Recovery Recommended** | **Post-Recovery Required** | **Future Capability** | **No Action**

---

### B1 — PH054 Synchronisation Package

| Field | Value |
|-------|-------|
| **Current coverage** | Schema parity for 48 shared CCMM tables; `public_id` on entities; sync-before-show as **process** in PH065/PH061 runbooks |
| **Gap description** | No checkout records, version vectors, Published Package registry, conflict audit, or Live Stage offline change log tables |
| **Severity** | Critical (platform capability) / High (governance) |
| **Recovery blocker?** | **No** — initial Cloud rebuild is one-directional Live Stage → Cloud |
| **Recommended action** | Author CCMM-15 design doc; define entities per ADR-001; defer migration until post Gate 4 |
| **Target future phase** | **PH072+** (post-recovery CCMM extension) or parallel design during PH071 authorisation |
| **Expected schema impact** | New tables: `asset_checkouts`, `entity_versions` (or per-entity version columns), `published_packages`, `published_package_items`, `sync_audit_log`, `offline_change_queue` (proposed names — design phase) |
| **Workspace impact** | CS + LS (shared); Website read-only on published outputs |
| **Operator decision required?** | Yes — confirm PH054 checkout scope (songs only vs charts/snippets/metadata bundle) |

**Classification:** **Post-Recovery Required** (before enabling peer authoring); **Future Capability** until PH054 engine implemented

**Proposed CCMM-15 scope:**

| Entity | Purpose |
|--------|---------|
| Checkout record | Environment, user, asset ref, base version, lock state |
| Version metadata | Base / cloud / local version triple per asset |
| Published package | Sync unit manifest + checksum aggregate |
| Package items | Entity + file entries in package |
| Sync audit | Operator-initiated sync events |
| Offline change log | LS pending changes awaiting publish |

---

### B2 — M5 / Action Asset Library

| Field | Value |
|-------|-------|
| **Current coverage** | `action_types`, `action_definitions`, `action_parameters`, `cue_actions` — Action **attachment** without reusable asset tables |
| **Gap description** | `mix_moves`, `light_modes`, `production_configuration` absent; Action metadata for X32/lighting bundles not normalized |
| **Severity** | High |
| **Recovery blocker?** | **No** — PH067C imported action domain; source has no mix_move rows requiring recovery |
| **Recommended action** | Author CCMM-16 entity definitions from DOMAIN_MODEL; sequence after Cloud Gate 4 |
| **Target future phase** | **PH073+** M5 implementation |
| **Expected schema impact** | New tables: `mix_moves`, `mix_move_steps` (TBD), `light_modes`, `light_mode_steps` (TBD), `production_configurations`, junction to shows/cues |
| **Workspace impact** | CS + LS shared (Master Library) |
| **Operator decision required?** | Yes — confirm Production Configuration scope vs show-scoped wiring only |

**Classification:** **Post-Recovery Required** (before live Mix Move / Light Mode execution); **Future Capability** for recovery cutover

---

### B3 — Festival / Venue Workflow Depth

| Field | Value |
|-------|-------|
| **Current coverage** | `venues`, `festivals` with contact fields, `application_status`, `application_url`, `application_deadline` |
| **Gap description** | No submission history, multi-step application records, or contact interaction log |
| **Severity** | Medium |
| **Recovery blocker?** | **No** |
| **Recommended action** | **No Action** for recovery; evaluate CCMM-17 only if Studio festival workflow requires history beyond single-row status |
| **Target future phase** | **PH074+** or application-layer audit trail |
| **Expected schema impact** | Optional: `festival_submissions`, `venue_contact_history` — or JSON audit on `festivals.notes` |
| **Workspace impact** | CS primary; Website read published festival listings |
| **Operator decision required?** | Yes — inline fields sufficient vs dedicated CRM tables |

**Classification:** **Future Capability**; **No Action** for Cloud Recovery

---

### B4 — X32 Console Registry

| Field | Value |
|-------|-------|
| **Current coverage** | CCMM-12: effects catalogue, packages, assignments, `show_console_baselines`; routing in `baseline_json`; LS: `integration_devices`, `performance_device_assignments` (Part B) |
| **Gap description** | No normalized console registry, console models reference, capabilities matrix, or console templates table; PH061A operator decision open |
| **Severity** | Medium |
| **Recovery blocker?** | **No** — baselines and effects recovered in PH067C |
| **Recommended action** | Defer registry tables; resolve PH061A merge-with-baselines vs separate entity in **PH061B** planning; keep `integration_devices` as Live Stage superset |
| **Target future phase** | **PH061B** (planning) → optional **CCMM-18** post-recovery |
| **Expected schema impact** | Optional reference: `console_models`, `console_capabilities`; optional `consoles` registry; templates likely remain baseline/package pattern |
| **Workspace impact** | LS for device binding; CS for baseline/effects authoring |
| **Operator decision required?** | **Yes** — registry model vs JSON-in-baseline (PH061A) |

**Classification:** **Future Capability**; **Live Stage Extension** for `integration_devices`

---

### B5 — CCMM Documentation Reconciliation

| Field | Value |
|-------|-------|
| **Current coverage** | PH063 CCMM-12 implements shared effects + baselines; PH059 Part B still lists them as Live Stage-only |
| **Gap description** | Manifest text drift; X32 snippet vs music snippet distinction must remain explicit in PH059 |
| **Severity** | Low (documentation) |
| **Recovery blocker?** | **No** — implementation is authoritative |
| **Recommended action** | Amend PH059 Part B table list; add CCMM-12 cross-reference; reinforce music `snippets` ≠ X32 recall snippets (PH061A) |
| **Target future phase** | **Before PH071 authorisation** (documentation-only) |
| **Expected schema impact** | **None** |
| **Workspace impact** | Governance docs only |
| **Operator decision required?** | No |

**Classification:** **Pre-Recovery Recommended** (documentation-only, no DDL)

---

### B6 — Website Publication Model

| Field | Value |
|-------|-------|
| **Current coverage** | Shared Cloud DB entities; `person_files.is_public`; people profile fields; performances + venues for events |
| **Gap description** | No schema-backed publication state for songs, events, media; no website CMS tables |
| **Severity** | Low–Medium (depends on cutover scope) |
| **Recovery blocker?** | **No** — Website can launch read-only or admin-curated without publication flags |
| **Recommended action** | Defer CCMM-19 unless operator requires public song/event pages at Cloud cutover; prefer application-layer visibility rules initially |
| **Target future phase** | **PH075+** or Website app config |
| **Expected schema impact** | Optional: `published_at`, `visibility` enums on songs/performances/people; or separate `website_publications` junction |
| **Workspace impact** | Website + CS |
| **Operator decision required?** | Yes — public site scope at cutover vs post-stabilisation |

**Classification:** **Future Capability**; **application-level** sufficient for recovery

**Publication elements:**

| Element | CCMM v1 | Remediation |
|---------|---------|-------------|
| Public visibility flags | Partial (`is_public` on files) | Optional columns or app rules |
| Website publishing state | Missing | CCMM-19 or app layer |
| Public profiles | `people` + files | Covered |
| Public events | `performances` + `venues` | App filtering |
| Public songs | `songs` | App filtering |
| Public media | `charts` / files via storage | App + Spaces ACL |
| Publication workflow | Missing | UX/process — not recovery blocker |

---

## 5. Gap track summary table

| Track | Classification | Recovery blocker? | CCMM v2? | Phase |
|-------|----------------|:-----------------:|:--------:|-------|
| B1 PH054 Sync | Post-Recovery Required | No | Yes (CCMM-15) | PH072+ |
| B2 M5 Assets | Post-Recovery Required | No | Yes (CCMM-16) | PH073+ |
| B3 Festival depth | Future Capability | No | Optional (CCMM-17) | PH074+ |
| B4 X32 registry | Future Capability | No | Optional (CCMM-18) | PH061B → post-recovery |
| B5 Doc reconcile | Pre-Recovery Recommended | No | No | Before PH071 |
| B6 Website publication | Future Capability | No | Optional (CCMM-19) | PH075+ / app layer |

---

## 6. Recommended remediation sequence

```text
NOW (pre-recovery, no DDL)
  └── B5 — PH059 Part B documentation reconcile

PARALLEL (planning only, no execution)
  └── PH071 — Cloud Recovery Execution Authorisation Package
  └── PH061B — Console templates / registry decision (feeds B4)

POST Gate 4 (Cloud stable)
  └── B1 — CCMM-15 PH054 sync design + migration authoring
  └── B2 — CCMM-16 M5 asset library design + migration authoring

OPERATOR-TRIGGERED (optional)
  └── B3 — CCMM-17 festival CRM if workflow requires
  └── B4 — CCMM-18 console registry if PH061B decides normalized registry
  └── B6 — CCMM-19 publication flags if Website cutover requires schema backing
```

**Recovery path unchanged:** Gate 1 → Gate 2 → R2 provision → R3 migrate (CCMM v1) → R5/R6 recovery → Gate 3/4 → R8 cutover.

---

## 7. CCMM v2 package candidates

| Package | Track | Priority post-recovery | Est. new tables |
|---------|-------|------------------------|-----------------|
| CCMM-15 PH054 Sync | B1 | **High** — ADR-001 binding | 5–7 |
| CCMM-16 M5 Library | B2 | **High** — Action execution | 3–6 |
| CCMM-17 Festival CRM | B3 | Low | 0–2 |
| CCMM-18 Console Registry | B4 | Medium (operator) | 0–3 |
| CCMM-19 Publication | B6 | Low (operator) | 0–4 |

CCMM v1 remains **frozen** for Cloud Recovery execution unless Gate 3 discovers drift — remediate via hotfix migration package, not v2 scope creep.

---

## 8. Operator decisions required

| # | Decision | Track | Default if deferred |
|---|----------|-------|---------------------|
| 1 | Proceed with Cloud Recovery on CCMM v1 after Gates 1–2 | Programme | Hold recovery |
| 2 | PH054 checkout asset scope (songs-only vs bundle) | B1 | Design-wide bundle |
| 3 | M5 Production Configuration scope | B2 | Show-scoped JSON until table authored |
| 4 | Festival workflow: inline vs CRM tables | B3 | Inline fields |
| 5 | Console registry: baseline JSON vs normalized tables | B4 | JSON-in-baseline |
| 6 | Website public scope at Cloud cutover | B6 | Admin-curated / read-only |
| 7 | Approve B5 PH059 doc amend before PH071 | B5 | Proceed with implementation-truth docs in PH071 |

---

## 9. Recommended next phase

**PH071 — Cloud Recovery Execution Authorisation Package**

PH071B completes gap sequencing. PH071 consolidates Gates 1–2 checklists, R0–R10 entry criteria, and explicit authorisation boundary incorporating:

- CCMM v1 authority for recovery
- B5 doc reconcile as pre-authorisation housekeeping
- B1–B6 as post-recovery roadmap (not blockers)

Alternative: **PH061B** (console templates) may run in parallel as planning — feeds B4 only.

---

## 10. Governance confirmation

| Statement | Confirmed |
|-----------|:---------:|
| No production mutation during PH071B | **Yes** |
| Planning and sequencing only | **Yes** |
| No migrations, schema changes, or recovery execution | **Yes** |
| PH071A gaps do not block Cloud Recovery (confirmed) | **Yes** |

---

End of PH071B plan
