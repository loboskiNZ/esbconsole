# PH027 — Snippet Domain Reconciliation

Status: Complete (documentation / domain reconciliation only)  
Authority: `docs/PROJECT_CHARTER.md`, `docs/DOMAIN_MODEL.md`  
Baseline: PH026 Legacy Show Migration Assessment complete; PH001–PH025 implementation complete  
Scope: Domain documentation reconciliation — **no schema, import, runtime, frontend, or X32 changes**

---

## 1. Purpose

Reconcile the Snippet domain model with confirmed product rules and captured legacy behaviour. Prior documentation treated Snippets primarily as chart fragments owned by Chart. The reconciled model positions Snippet as a **cue-specific visual reference asset** for an Instrument Part within a Song, anchored by **SongInstrumentPart + Cue** assignment context.

This phase updates governance documents only. Implementation is deferred to PH028 onward.

---

## 2. Authoritative Snippet Definition

A **Snippet** is a cue-specific visual reference asset for an Instrument Part.

It may originate from:

| Source type (proposed) | Description |
|------------------------|-------------|
| `chart_crop` | Region cropped from the SongInstrumentPart's Chart in Chart Mode |
| `photo` | Camera capture (e.g. photo of a physical drawing) |
| `image_upload` | Direct image file upload |
| `cloned_snippet` | Independent copy created from an existing snippet in another cue |
| `freehand_drawing` | Freehand/drawing capture on device |

A Snippet is **not merely a chart fragment**. Chart crops are one origin path; the asset stands on its own once created.

---

## 3. Confirmed Domain Rules

| # | Rule |
|---|------|
| 1 | Song has Cues. |
| 2 | Song has SongInstrumentParts. |
| 3 | A SongInstrumentPart represents an Instrument Part required for that Song. |
| 4 | A SongInstrumentPart uses exactly one Chart for that Song. |
| 5 | A Chart may be shared by many SongInstrumentParts. |
| 6 | A Snippet belongs to an Instrument Part + Cue assignment context (SongInstrumentPart + Cue). |
| 7 | One SongInstrumentPart + Cue may have one active Snippet. |
| 8 | A Snippet is copied, not shared. |
| 9 | Reusing a snippet from another cue creates an independent copy. |
| 10 | A musician can mark up / annotate a snippet. |
| 11 | A chart update does not automatically regenerate snippets. |
| 12 | Chart updates should flag affected snippets as out-of-date. |
| 13 | Cues have stable identity (`cue_number` within Song). |
| 14 | Cue order may change for special arrangements. |
| 15 | Cue identity is not the same as runtime sequence. |
| 16 | Musicians may perform multiple Instrument Parts. |
| 17 | Live musician view supports: current snippet, next snippet, next +1 snippet, optional full chart mode. |

---

## 4. Superseded Assumptions

The following prior assumptions are **superseded** by PH027. Historical phase entries are preserved; active domain behaviour follows PH027.

| Prior assumption | Status | Reconciled position |
|------------------|--------|---------------------|
| Snippet is simply a freeform cue note | **Superseded** | Snippet is a visual reference asset; cue notes/instructions remain separate (Actions / musician instructions). |
| Snippet is exclusively a chart child | **Superseded** | Snippet may originate from chart crop but is not owned by Chart; it belongs to SongInstrumentPart + Cue. Non-chart sources (photo, upload, drawing) are valid. |
| Snippet is shared between cues | **Superseded** | One active Snippet per SongInstrumentPart + Cue; cloning creates an independent copy. |
| Chart is reusable, snippet copy is not | **Confirmed** | Chart (file asset) may be referenced by multiple SongInstrumentParts; each Snippet is an independent copied asset. |
| PH001 Decision 019: "Snippet is a Chart portion associated with a Cue" | **Amended** | Retained intent (separates navigation content from Cue boundary) but definition expanded — see PH027 decisions in `docs/DECISION_LOG.md`. |
| Chart "contains" Snippets | **Superseded** | Chart is the whole notated document; Snippets are sibling assets under SongInstrumentPart, not children of Chart. |
| Snippet uniqueness is Chart-scoped | **Superseded** | Uniqueness is `SongInstrumentPart + Cue`. |

---

## 5. Legacy Behaviour Captured

### Chart Mode (preparation / authoring)

- Musician or director opens Chart Mode against the SongInstrumentPart's Chart.
- User crops a region of the chart (PDF page render + crop workflow).
- After cropping, workflow asks: **"Select the cue you want this in."**
- Normal selection list shows **only empty cues** for that Instrument Part (SongInstrumentPart + Cue combinations without an active Snippet).
- Saving creates a new Snippet asset bound to the selected Cue.

### Cue View (live / review)

- Musician views current, next, and upcoming snippets in cue context.
- Musician may **clone** an existing snippet into another cue (creates independent copy).
- Musician may add **notes/markup** on existing snippets.

### Photo / drawing capture

- Musician may take a picture of a physical drawing and use it as a Snippet for a selected cue.
- Treated as `photo` or `freehand_drawing` source type — not a chart crop.

---

## 6. Proposed Future Implementation Concepts

*Design notes only — not implemented in PH027.*

### 6.1 Snippet source type

Enum or equivalent: `chart_crop`, `photo`, `image_upload`, `cloned_snippet`, `freehand_drawing`.

- Required for provenance, migration mapping, and UI labelling.
- `cloned_snippet` should reference `source_snippet_id` (provenance only — not a shared asset link).

### 6.2 Snippet copy semantics

- Each Snippet owns its own file asset (`storage_reference`, `checksum`).
- Clone operation: duplicate binary + new row; set `source_type = cloned_snippet` and optional `source_snippet_id`.
- No shared storage reference between cues.

### 6.3 Snippet annotation / markup

- Musician markup is layered on the Snippet display asset or stored as separate annotation data linked to Snippet.
- Markup is per-Snippet; does not mutate the parent Chart.
- Director may also author markup during preparation (role TBD in PH028 UX detail).

### 6.4 Snippet freshness / out-of-date state

| State (proposed) | Meaning |
|------------------|---------|
| `current` | Snippet asset matches known Chart revision or non-chart source. |
| `out_of_date` | Underlying Chart was updated after Snippet creation; Snippet not auto-regenerated. |
| `superseded` | Replaced by a newer active Snippet for same SongInstrumentPart + Cue (optional future). |

- Chart update sets affected `chart_crop` snippets to `out_of_date`.
- Musician/director must explicitly recreate or accept stale snippet.
- Non-chart sources unaffected by chart updates.

### 6.5 Cue identity vs cue sequence

- **Identity:** `cue_number` (`NNN`) within Song — stable, canonical in `SSS.CCC`.
- **Sequence:** display/performance order — may differ from numeric order for special arrangements.
- Snippets bind to Cue **identity** (`cue_id` / `cue_number`), not playlist or runtime sequence index.
- Runtime CC16 maps to `cue_number`; sequence reordering does not reassign Snippets.

### 6.6 Chart Mode vs Cue View workflows

| Mode | Purpose | Primary actions |
|------|---------|-----------------|
| **Chart Mode** | Authoring / crop-from-chart | View full Chart, crop region, assign to empty cue |
| **Cue View** | Live guidance / review | View current & lookahead snippets, clone, annotate, optional full chart |

Chart Mode operates on Chart + SongInstrumentPart context. Cue View operates on timeline context (Current / Next / Next+1 Cue) resolved via Assignment.

### 6.7 Live musician display mode

Minimum display surfaces (per Assignment + Cue resolution):

| Surface | Content |
|---------|---------|
| Current Snippet | Active cue's Snippet for assigned SongInstrumentPart |
| Next Snippet | Next cue in sequence (identity-based lookup) |
| Next +1 Snippet | Second lookahead cue |
| Full Chart Mode (optional) | Entire Chart for assigned SongInstrumentPart — display override, not timeline authority |

Automatic navigation follows Ableton cue transitions by default. Manual browse and full chart mode are display-only overrides.

---

## 7. Recommended Future Schema Implications

*Design notes for PH028 — not migration instructions.*

### 7.1 SongInstrumentPart (confirmed aggregate anchor)

Already present in foundation schema (`song_instrument_parts`). Documentation now aligns:

- `unique(song_id, instrument_part_id)`
- One Chart reference per SongInstrumentPart (PH028 may enforce `chart_id` FK or unique constraint)
- Snippets scoped via `song_instrument_part_id`

### 7.2 Snippets table extensions (proposed PH028)

| Column / concept | Purpose |
|------------------|---------|
| `source_type` | Origin enum (§6.1) |
| `source_snippet_id` | Nullable FK — clone provenance only |
| `source_chart_id` | Nullable FK — chart crop provenance |
| `freshness_state` | `current`, `out_of_date`, `superseded` |
| `chart_revision_at_creation` | Chart checksum or version stamp at snippet creation |
| `is_active` | Support soft-replace without breaking history |
| `annotation_data` or linked `snippet_annotations` | Markup storage |
| `crop_metadata` | JSON: page, bounding box — for chart_crop sources |

Existing foundation columns retained: `public_id`, `song_instrument_part_id`, `cue_id`, `title`, `storage_reference`, `checksum`, `notes`.

Existing constraint retained: `unique(song_instrument_part_id, cue_id)` for one active Snippet per context.

### 7.3 Chart sharing (PH028 implemented)

Implemented as Song-scoped **Chart** asset with **SongInstrumentPart.chart_id** assignment:

- `charts.song_id` — Chart file asset belongs to Song
- `song_instrument_parts.chart_id` — one chart assignment per SongInstrumentPart (nullable during preparation)
- Multiple SongInstrumentParts may reference the same `charts.id` (shared file asset)

M9 `charts.song_instrument_part_id` removed by migration `2026_06_17_100000_ph028_snippet_domain_schema`.

### 7.4 Snippets table (PH028 implemented)

Foundation columns retained plus:

| Column | Purpose |
|--------|---------|
| `source_type` | `chart_crop`, `photo`, `upload`, `clone`, `drawing` |
| `source_snippet_id` | Clone provenance (nullable FK) |
| `source_chart_id` | Chart crop provenance (nullable FK) |
| `freshness_state` | `current`, `out_of_date`, `needs_review` |
| `is_active` | Supports historical snippets; partial unique index on active rows only |
| `annotation_storage_reference` | Markup/annotation asset path |
| `markup_storage_reference` | Layered markup asset path |
| `rendered_storage_reference` | Pre-rendered display asset path |
| `source_metadata` | JSON — crop page, x/y/width/height |
| `chart_revision_at_creation` | Chart checksum at snippet creation |

Partial unique index: `snippets_active_sip_cue_unique` on `(song_instrument_part_id, cue_id) WHERE is_active = 1`.

Freshness updates: `App\Services\Snippet\ChartSnippetFreshnessService` marks affected chart-crop snippets out-of-date when chart checksum changes — no auto-regeneration.

### 7.5 Cue sequence (PH028 implemented)

`cues.sequence_order` added — performance/display order separate from stable `cue_number` identity.

### 7.6 File assets

- Snippet binaries remain production assets under `/charts/` (or dedicated `/snippets/` path — PH028 decision).
- Each Snippet copy = distinct file asset; no shared checksum requirement between clones.

### 7.7 Cue ordering

- Consider `sequence_order` or equivalent on Cues table (separate from `cue_number`) if not already present — supports rule 14 without changing identity.

---

## 8. Recommended Future Phase Order

| Phase | Scope |
|-------|-------|
| **PH028** | Snippet Schema Design — SongInstrumentPart/Chart sharing model, snippet source type, freshness, annotation storage, migration delta from foundation schema |
| **PH029** | Legacy Migration Design — map legacy `charts/snippets/` tree and API payloads to reconciled model |
| **PH030** | Import Command Foundation — CLI/service skeleton for legacy show import |
| **PH031** | Dry-Run Migration Validation — validate mapping without writes |
| **PH032** | Controlled Legacy Show Import — executed import with rollback |

---

## 9. Cross-Reference Updates

PH027 updates the following authority documents:

| Document | Change summary |
|----------|----------------|
| `docs/DOMAIN_MODEL.md` | SongInstrumentPart entity; Snippet, Chart, Song, Cue sections reconciled |
| `docs/DECISION_LOG.md` | PH027 decisions; PH019 amended |
| `docs/DATABASE_ARCHITECTURE.md` | Song aggregate and relationship model |
| `docs/DATA_ARCHITECTURE.md` | Snippet ownership note |
| `docs/UX_MODEL.md` | Chart Mode / Cue View workflows; live display surfaces |
| `docs/RUNTIME_MODEL.md` | Musician display minimum and resolution path |
| `docs/PROJECT_CHARTER.md` | Chart Model section aligned |

---

## 10. Governance Compliance

| Constraint | Status |
|------------|--------|
| Documentation only | ✅ |
| No schema migrations | ✅ |
| No import code | ✅ |
| No runtime changes | ✅ |
| No frontend changes | ✅ |
| No X32 changes | ✅ |
| Phase history preserved | ✅ |
| Song Code + Cue Number identity | ✅ Unchanged |

---

End of PH027 — Snippet Domain Reconciliation
