# PH071A — CCMM Domain Coverage Audit

Status: **Assessment only** — no production mutation, migrations, schema changes, or recovery execution  
Date: 2026-06-25  
Authority: PH059 CCMM, PH060, PH061A, PH070, governance corpus  
Overall verdict: **CCMM Sufficient With Defined Gaps**

---

## 1. Executive summary

The Cloud Canonical Migration Manifest (CCMM) plus implemented migration packages (PH063) and recovery extensions provide **strong coverage** of the ESB production platform's core capabilities: band roster, music library, show/performance lifecycle, venue/festival operations, X32 console preparation assets, and governed recovery audit.

Gaps are **governed and mostly intentional deferrals**, not silent omissions:

| Gap class | Examples |
|-----------|----------|
| **Future Phase (PH054)** | Checkout, versioning, Published Package registry, sync conflict audit |
| **M5 assets (planned)** | `mix_moves`, `light_modes`, `production_configuration` |
| **Live Stage superset** | Soundcheck, readiness, integration devices, learning snapshots |
| **Operational / implementation** | `person_invitations` table exists; PH048B onboarding not live |
| **Artifact-not-entity** | Stage plots, tech riders, festival packs — generated outputs |

**Recommended disposition:** **CCMM Sufficient With Defined Gaps**

Cloud recovery (PH061) can proceed on current CCMM **after Gates 1–2** (PH070). Full platform capability — especially PH054 peer authoring and M5 reusable assets — requires **PH071B remediation planning**, not emergency CCMM rewrite.

---

## 2. Audit method

For each platform capability area:

1. Governing authority document identified  
2. Required entities extracted from `DOMAIN_MODEL.md`, `DATA_ARCHITECTURE.md`, `DATABASE_ARCHITECTURE.md`  
3. Mapped to PH059 Part A/B/C tables and PH063 migration packages  
4. Classified: **Covered** | **Partially Covered** | **Missing** | **Future Phase**  
5. Gaps logged with severity and recommended action  

**Not table-driven:** coverage judged against business capability, not raw table count.

---

## A. Band & People Domain

**Governing authority:** `DOMAIN_MODEL.md` (Person, Musician, Band), `DATA_ARCHITECTURE.md` Band People matrix, PH047/PH048A identity governance.

### Coverage matrix

| Capability | Required entities | CCMM mapping | Status |
|------------|-------------------|--------------|--------|
| Bands | `bands` | A1 / CCMM-01 | **Covered** |
| People (production personnel) | `people` | A3 / CCMM-03 | **Covered** |
| Musicians (roster) | `musicians` | A7 / CCMM-04 | **Covered** |
| Musician roles | `musician_band_roles` | A8 / CCMM-04 | **Covered** |
| Directors | `bands.primary_director_musician_id` | A1 + A7 / CCMM-04 | **Covered** |
| Invitations (governed) | `person_invitations` | Part C / CCMM-11 | **Partially Covered** — schema authored; Cloud-only; PH048B not operational |
| Profile files | `person_files`, `people.profile_photo_*` | A5 / CCMM-03 | **Covered** |
| Secure fields | `person_secure_fields` | A4 / CCMM-03 | **Covered** |
| Instruments | `instrument_reference`, `person_instruments` | A9–A10 / CCMM-03 | **Covered** |
| IEM preferences | `person_iem_settings` | A6 / CCMM-03 | **Covered** |
| Legacy invite drift | `invite_links*` | Quarantined Part C | **Missing** (intentional — not canonical) |

**Domain coverage:** **92%** — invitation capability awaits PH048B implementation, not schema absence.

---

## B. Music Library Domain

**Governing authority:** `DOMAIN_MODEL.md` (Song, Chart, Snippet, Cue, Action), PH027/PH053 metadata governance.

### Coverage matrix

| Capability | Required entities | CCMM mapping | Status |
|------------|-------------------|--------------|--------|
| Songs | `songs` | A12 / CCMM-05 | **Covered** |
| Song metadata (mood, key, time) | `song_moods`, `musical_keys`, `time_signatures` | A13–A15 / CCMM-02 | **Covered** |
| Instrument parts | `instrument_parts` | A11 / CCMM-05 | **Covered** |
| Song ↔ part linkage | `song_instrument_parts` | A17 / CCMM-06 | **Covered** |
| Charts | `charts` | A16 / CCMM-06 | **Covered** |
| Snippets | `snippets` | A33 / CCMM-06 | **Covered** |
| Cues | `cues` | A28 / CCMM-07 | **Covered** |
| Cue actions | `cue_actions`, `action_definitions`, `action_parameters`, `action_types` | A29–A32 / CCMM-07 | **Covered** |
| Import audit | `import_batches`, `import_entity_mappings` | A18–A19 / CCMM-06 | **Covered** |
| PH054 checkout / versioning | version columns, checkout records | Not in CCMM v1 | **Future Phase** |

**Authoring requirements:** Structurally represented. Peer-authoring conflict model (PH054) deferred — does not block library CRUD or recovery.

**Domain coverage:** **90%** (95% excluding explicit PH054 future work).

---

## C. Show & Performance Domain

**Governing authority:** `DOMAIN_MODEL.md` (Show, Performance, Assignment, Device), `RUNTIME_MODEL.md`.

### Coverage matrix

| Capability | Required entities | CCMM mapping | Status |
|------------|-------------------|--------------|--------|
| Ableton show files | `ableton_show_files` | A20 / CCMM-08 | **Covered** |
| Shows | `shows` | A21 / CCMM-08 | **Covered** |
| Playlists | `show_playlist_items` | A22 / CCMM-08 | **Covered** |
| Performances | `performances` | A23 / CCMM-08 | **Covered** |
| Performance assignments | `performance_assignments` | A24 / CCMM-08 | **Covered** |
| Devices | `devices` | A25 / CCMM-09 | **Covered** |
| Capabilities | `capabilities` | A26 / CCMM-09 | **Covered** |
| Roster assignments | `assignments` | A27 / CCMM-09 | **Covered** |
| Soundcheck | `soundchecks` | Part B superset | **Future Phase** / Live Stage Extension |
| Readiness | `readiness_records` | Part B superset | **Future Phase** / Live Stage Extension |
| Musician availability | implicit on Performance | Partial via `performance_assignments` | **Partially Covered** — no dedicated availability table |
| Production Configuration | aggregate in DATA_ARCHITECTURE | No CCMM table | **Missing** — M5 / Future Phase |

**Rehearsal/performance lifecycle:** Show → Performance → Assignment chain **Covered**. Operational gates (Soundcheck, Readiness) correctly **Live Stage superset**.

**Domain coverage:** **85%**

---

## D. Venue & Festival Domain

**Governing authority:** `DATA_ARCHITECTURE.md`, backend venue/festival migrations referenced in PH059 A34–A35.

### Coverage matrix

| Capability | Required entities | CCMM mapping | Status |
|------------|-------------------|--------------|--------|
| Venues | `venues` | A34 / CCMM-10 | **Covered** |
| Festivals | `festivals` | A35 / CCMM-10 | **Covered** |
| Festival applications | workflow state | `festivals.application_status`, `application_url`, `application_deadline` | **Partially Covered** — single-row status, no history |
| Submissions | submission records | — | **Missing** |
| Contact history | CRM-style trail | inline `contact_*` on venue/festival | **Partially Covered** |

**Domain coverage:** **70%** — core entities present; workflow depth is application-layer or future extension.

---

## E. Cloud Studio Domain

**Governing authority:** `INFORMATION_ARCHITECTURE.md`, PH054 Cloud Studio definition, `/server/` Band Portal scope.

| Studio function | CCMM support | Status |
|-----------------|--------------|--------|
| Song authoring | songs + metadata + import audit | **Covered** |
| Chart management | charts + storage_reference | **Covered** |
| Festival management | festivals + application fields | **Partially Covered** |
| Venue management | venues | **Covered** |
| Musician administration | people, musicians, roles, devices | **Covered** |
| Onboarding | person_invitations (schema) | **Partially Covered** — PH048B blocked |
| PH054 peer edit | checkout/version | **Future Phase** |

**Domain coverage:** **88%**

---

## F. Website Domain

**Governing authority:** PH055 (Website as third workspace), `ARCHITECTURE.md` §3, `DATA_ARCHITECTURE.md` Band People rows.

| Website capability | CCMM representation | Status |
|--------------------|----------------------|--------|
| Band website content | reads `bands`, shared entities | **Partially Covered** — no CMS tables (intentional) |
| Public profiles | `people` + `person_files.is_public` | **Partially Covered** |
| Public songs | `songs` (publication workflow TBD) | **Partially Covered** |
| Public events | `performances` + `venues` | **Partially Covered** |
| Public contact flows | venue/festival contact fields | **Partially Covered** |

**Finding:** Website is a **consumer workspace** on Cloud Database — not a parallel schema fork. Missing website-specific tables are **intentional** per PH055; publication flags and routing belong in application layer or future CCMM extension.

**Domain coverage:** **65%** (acceptable by architecture — not a CCMM completeness failure).

---

## G. X32 Console Domain

**Governing authority:** PH061A, PH043–PH044, CCMM-12 migrations (PH063 supersedes PH059 Part B listing for effects/baselines).

### Coverage matrix

| Capability | CCMM / implementation | Status |
|------------|----------------------|--------|
| Effects algorithm catalogue | `effects`, `effect_parameters`, `effect_package_types` | **Covered** |
| Effect packages | `effect_packages`, `effect_package_items`, parameters, target sections | **Covered** |
| Song effect assignments | `song_effect_assignments` | **Covered** |
| Show console baselines | `show_console_baselines` | **Covered** (CCMM-12 — shared parity) |
| Routing / channel config | `baseline_json` document | **Partially Covered** — JSON-in-row (PH061A decision) |
| Mix moves | `mix_moves` | **Missing** — Future Phase / M5 |
| Console templates | conceptual | **Partially Covered** — overlaps baselines + packages; operator decision open |
| Console registry | `integration_devices`, `consoles` | **Missing** — Live Stage Extension |
| Learning snapshots | `console_learning_snapshots` | **Future Phase** — Live Stage only |
| Runtime OSC state | ephemeral | **Future Phase** — runtime only |

**CCMM-12 sufficiency:** **Sufficient** for show prep backup/restore and recovery rehearsal (PH067C imported effects + baselines). Normalized routing tables correctly **excluded**.

**Domain coverage:** **78%**

---

## H. Ableton Domain

**Governing authority:** `DOMAIN_MODEL.md` (Ableton Show File, Cue), `INTEGRATION_RUNTIME_ARCHITECTURE.md` — Ableton owns timeline.

| Capability | CCMM mapping | Status |
|------------|--------------|--------|
| Show files | `ableton_show_files` | **Covered** |
| Cues | `cues` | **Covered** |
| Actions | `cue_actions` + action domain | **Covered** |
| Playlist integration | `show_playlist_items` | **Covered** |
| Runtime references / protocol state | runtime tables | **Future Phase** — Live Stage superset |
| Mix move execution | requires `mix_moves` | **Missing** — blocks full Action taxonomy |

**Domain coverage:** **80%** for authoring; runtime intentionally outside CCMM.

---

## I. Synchronisation Domain

**Governing authority:** PH054, ADR-001, `DATABASE_ARCHITECTURE.md` §Published Package / Sync State.

| PH054 requirement | CCMM presence | Status |
|-------------------|---------------|--------|
| Checkout before edit | checkout records | **Missing** — Future Phase |
| Version columns (base/cloud/local) | per-entity version fields | **Missing** — Future Phase |
| Published Package registry | sync package tables | **Missing** — Future Phase |
| Conflict resolution audit | conflict log | **Missing** — Future Phase |
| Sync-before-show pull | uses package manifest (app layer) | **Partially Covered** — process in runbooks, not schema |
| Schema parity Cloud ↔ Live Stage | CCMM Part A | **Covered** |

**Finding:** PH054 explicitly deferred from CCMM v1 (PH059 scope footnote; PH061 §11.4). **Not a recovery blocker**; **is a platform roadmap blocker** for bidirectional song authoring.

**Domain coverage:** **35%** — low by design until PH071B+ sync package.

---

## J. Recovery Domain

**Governing authority:** PH061 §5.2, PH066, PH067A–PH070.

| Capability | Representation | Status |
|------------|----------------|--------|
| Recovery entity map | `cloud_recovery_entity_map` | **Covered** |
| Batch manifests / entity maps | filesystem `storage/recovery/{batch}/` | **Covered** (tooling) |
| Forensic tracking | Gate 1 export + batch JSON | **Partially Covered** — minimal DB footprint |
| Rollback support | `recovery:rollback-batch` dry-run + `PH065_ROLLBACK_RUNBOOK.md` | **Partially Covered** — rehearsed dry-run only |
| Deferred FK / transform audit | `deferred_fk.json`, `effect_transform_report.json` | **Covered** (tooling) |

**Domain coverage:** **80%** — sufficient for PH061 Path B recovery; Cloud rollback execution unproven.

---

## 3. Gap classification register

| Gap | Severity | Action |
|-----|----------|--------|
| PH054 checkout / version / conflict entities absent | **Critical** (platform) | **Future Phase** — PH071B sync package |
| Published Package / Sync Package registry tables absent | **Critical** (platform) | **Future Phase** — PH071B |
| `mix_moves` not in CCMM | **High** | **Add to CCMM** — M5 package |
| `light_modes` not in CCMM | **High** | **Add to CCMM** — M5 package |
| `production_configuration` not in CCMM | **High** | **Add to CCMM** — M5 package |
| `person_invitations` not operational (PH048B) | **High** | **No Action** (CCMM) — implementation phase |
| Festival submission / contact history tables | **Medium** | **Future Phase** or **Cloud Extension** |
| Stage plot / tech rider as stored entities | **Medium** | **No Action** — generated artifacts per DOMAIN_MODEL |
| `integration_devices` / performance device binding | **Medium** | **Live Stage Extension** |
| `console_learning_snapshots` | **Low** | **Live Stage Extension** |
| Website CMS / publication workflow tables | **Low** | **Future Phase** or application-layer flags |
| PH059 Part B vs CCMM-12 drift (effects/baselines) | **Low** | **No Action** — PH063 implementation supersedes PH059 Part B text |
| Musician availability entity | **Low** | **Future Phase** — may use `performance_assignments` |

---

## 4. Coverage score

| Domain | Covered % | Notes |
|--------|----------:|-------|
| A. Band & People | **92%** | Invitations partial (implementation) |
| B. Music Library | **90%** | PH054 versioning deferred |
| C. Show & Performance | **85%** | Soundcheck/Readiness LS-only |
| D. Venue & Festival | **70%** | Workflow depth partial |
| E. Cloud Studio | **88%** | Onboarding + PH054 gaps |
| F. Website | **65%** | Intentional consumer model |
| G. X32 Console | **78%** | mix_moves missing; JSON routing OK |
| H. Ableton | **80%** | Runtime out of scope |
| I. Synchronisation | **35%** | PH054 deferred by governance |
| J. Recovery | **80%** | Cloud rollback unproven |

**Weighted platform core (A–C, E, G, J):** ~**86%**  
**Full capability incl. PH054 + M5:** ~**72%**

---

## 5. Critical missing items

1. **PH054 synchronisation schema** — checkout records, version fields, Published Package registry, conflict audit (governed Future Phase; blocks peer authoring, not Cloud recovery).  
2. **M5 reusable assets** — `mix_moves`, `light_modes`, `production_configuration` referenced in `DATA_ARCHITECTURE.md` and `ARCHITECTURE.md` Master Library but absent from CCMM.  
3. **No Critical gap blocks PH061 Cloud recovery** on current CCMM — evidenced by PH067C/D local rehearsals.

---

## 6. Recommended PH071B scope

**PH071B — CCMM Coverage Remediation Plan** (recommended)

| Track | Scope |
|-------|-------|
| **B1 — PH054 sync package** | Define CCMM-15+ tables: asset checkout, version vectors, published package manifest, sync audit |
| **B2 — M5 master library** | `mix_moves`, `light_modes`, `production_configuration` entity definitions + migration order |
| **B3 — Venue/festival workflow** | Decide: inline festival fields sufficient vs `festival_submissions` / `contact_history` tables |
| **B4 — X32 registry decision** | Close PH061A operator question: `integration_devices` vs `consoles` registry |
| **B5 — PH059 document reconcile** | Update Part B text to reflect CCMM-12 shared effects/baselines (implementation truth) |
| **B6 — Website publication model** | Publication flags or views — application vs schema decision |

**Out of PH071B scope:** PH048B onboarding implementation, Cloud recovery execution (PH065-exec), PH054 sync engine code.

---

## 7. Overall CCMM assessment

| Verdict | Applies when |
|---------|--------------|
| CCMM Complete | ❌ — PH054 + M5 gaps remain |
| **CCMM Sufficient With Defined Gaps** | ✅ **Selected** — recovery-ready; governed deferrals documented |
| CCMM Incomplete | ❌ — core production domains are represented |

**Summary:** CCMM contains what the platform needs for **Cloud recovery, show preparation, music library, roster, and X32 console prep**. It does **not yet** contain everything for **full PH054 peer authoring** or **complete M5 Action asset library**. Those gaps are known, classified, and sequenced — not discovery surprises.

---

## 8. Governance confirmation

| Statement | Confirmed |
|-----------|:---------:|
| No production mutation during PH071A | **Yes** |
| Assessment and documentation only | **Yes** |
| No infrastructure changes | **Yes** |
| No schema changes | **Yes** |

---

## 9. Evidence references

| Document | Role |
|----------|------|
| `docs/PH059_CLOUD_CANONICAL_MIGRATION_MANIFEST.md` | Schema authority |
| `docs/PH060_CCMM_IMPLEMENTATION_GAP_ANALYSIS.md` | Pre-PH063 gap baseline |
| `docs/PH061A_X32_CONSOLE_DOMAIN_DISCOVERY.md` | Console domain classification |
| `docs/PH064_CCMM_LOCAL_VALIDATION_REPORT.md` | 48/48 table validation |
| `docs/PH067C_SECOND_LOCAL_RECOVERY_REHEARSAL_REPORT.md` | Data recovery evidence |
| `docs/PH067D_LOCAL_FILE_RECOVERY_REHEARSAL_REPORT.md` | File recovery evidence |
| `docs/PH070_RECOVERY_READINESS_ASSESSMENT.md` | READY WITH CONDITIONS |
| `database/migrations/ccmm/` | Implemented packages CCMM-00–12 + CCMM-11 |

---

End of PH071A audit
