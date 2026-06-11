# PH029 — Legacy Migration Design

Status: Complete (design only)  
Authority: `docs/PROJECT_CHARTER.md`, `docs/DOMAIN_MODEL.md`, `docs/PH027_SNIPPET_DOMAIN_RECONCILIATION.md`  
Baseline: PH028 schema committed (`88fd5ea`); 228 tests passing  
Scope: **Design documentation only** — no import commands, no data import, no schema changes

---

## 1. Purpose

Design the controlled migration of legacy show data from the Node/JSON/filesystem system into the PH028 Laravel schema. This document defines scope, field mappings, identity strategy, asset handling, validation reports, rollback safety, and the recommended implementation phase sequence (PH030+).

Legacy system summary (PH026 assessment confirmed):

| Legacy component | Location | Role |
|------------------|----------|------|
| Setlists / songs / cues | `setlists.json` | Show playlist, song metadata, cue array, chart assignments, snippet metadata |
| Musicians | `musicians.json` | Person records, default role labels, X32 bus/channel hints |
| Full charts (new system) | `charts/<legacySongId>/<role>.pdf` | Role-scoped chart PDF per song |
| Snippet PNGs | `charts/snippets/<legacySongId>/<role>/` | Cropped cue snippets (`cue_N.png` or timestamped) |
| Uploaded charts (legacy) | `uploads/*.pdf` | Per-channel chart assignments via `chartAssignments[]` |
| Boot backups | `backups/boot_*/` | Point-in-time copies of JSON if primary sources corrupt |

Representative legacy scale (active setlist **Thieves Alley**): 1 setlist, 12 playlist songs, 12 total song records, 114 cues, 104 chart assignments, 94 snippet role entries across 3 snippet roles (`machines`, `singer`, `trumpet`).

---

## 2. Migration Scope

### 2.1 In scope (will migrate)

| Target entity | Legacy source | Notes |
|---------------|---------------|-------|
| **Show** | `setlists[activeSetlistId]` | One Show per import batch (initially active setlist only) |
| **ShowPlaylistItem** | `setlist.songOrder[]` | Ordered positions; `ableton_pgm` assigned sequentially at import |
| **Song** | `songs[legacySongId]` | New `song_code` assigned; legacy ID retained in manifest only |
| **Cue** | `song.cues[]` | Index → `cue_number` + `sequence_order`; synthetic Cue 000 recommended |
| **InstrumentPart** | Snippet role slugs, chart filenames, `musicians.json` role strings | Normalized global catalog per Band |
| **SongInstrumentPart** | Distinct roles per song with charts and/or snippets | One SIP per normalized Instrument Part per Song |
| **Chart** | `charts/<songId>/<role>.pdf`, deduplicated `uploads/` PDFs | Song-scoped asset; shared via `chart_id` when checksum matches |
| **SongInstrumentPart.chart_id** | Resolved chart file per role | One assignment per SIP |
| **Snippet** | `cues[].visualSnippets[role]` | `source_type = chart_crop`; one active snippet per SIP + Cue |
| **Musician** (optional/basic) | `musicians.json` | Name + email only if safe; **no** X32 routing import |

### 2.2 Out of scope (will NOT migrate)

| Legacy data | Reason |
|-------------|--------|
| X32 scenes / safes / channel state | Runtime/hardware; outside music library domain |
| DMX / lighting | Integration runtime scope |
| Stage plots / tech riders | Not present in legacy sources for this show |
| `chartAssignments` monitor/input routing | Operational Assignment / Monitor Assignment — separate phase |
| `musicianRouting`, `busNames` | X32 routing tables |
| `setlistManager.runtime` state | Ephemeral live state |
| Cue `type`, `data`, `bars` (automation) | Not part of PH028 music library schema; future Action domain |
| SharePoint config (`musicians.json`) | External integration config |
| `manualSafes` | X32 state |
| Production notes not tied to chart/snippet structure | Out of scope unless explicitly linked to Song.notes |
| Learn-mode auto-created songs/cues | Excluded unless present in committed JSON at import time |

---

## 3. Source-to-Target Mapping

### 3.1 Show and playlist

| Legacy field | Target model.field | Transform |
|--------------|-------------------|-----------|
| `setlists[id].name` | `shows.name` | Direct (e.g. "Thieves Alley") |
| `setlists[id].id` | import manifest `legacy_setlist_id` | Audit only — not primary key |
| `activeSetlistId` | import batch metadata | Determines which setlist is imported |
| `setlist.songOrder[i]` | `show_playlist_items.song_id` | Resolve via song_code lookup after Song creation |
| Array index `i` | `show_playlist_items.position` | 1-based position |
| Array index `i` | `show_playlist_items.ableton_pgm` | `i + 1` (PGM is 1-based per runtime model) |

### 3.2 Song

| Legacy field | Target model.field | Transform |
|--------------|-------------------|-----------|
| `songs[id].id` | import manifest `legacy_song_id` | **Never** used as PK or `song_code` |
| `songs[id].title` | `songs.name` | Direct |
| `songs[id].artist` | `songs.description` or `notes` | Prefix: `Artist: {artist}` in notes if no description field used |
| `songs[id].bpm` | `songs.bpm` | Direct (integer) |
| Playlist order index | `songs.song_code` | Assign `001`, `002`, … in playlist order within Band |
| — | `songs.band_id` | Target Band context for import |
| — | `songs.status` | `in_progress` until validation pass; `ready` after PH031 approval |

### 3.3 Cue

| Legacy field | Target model.field | Transform |
|--------------|-------------------|-----------|
| `cues[i].name` | `cues.name` | Direct |
| `cues[i]` array index `i` | `cues.cue_number` | **`str_pad(i + 1, 3, '0')`** → `001`, `002`, … |
| `cues[i]` array index `i` | `cues.sequence_order` | **`i + 1`** (matches legacy performance order initially) |
| Synthetic preparation | `cues` row with `cue_number = 000` | **Recommended insert** per Song (see §4) |
| `cues[i].bars` | import manifest only | Not stored in Cue schema; available for future Action/timing |
| `cues[i].type`, `cues[i].data` | import manifest only | Automation — out of scope for library import |
| Legacy index `i` | import manifest `legacy_cue_index` | Audit / CC16 bridge |

**Runtime identity:** After import, canonical identity is `{song_code}.{cue_number}` (e.g. `003.002` for song 003, second legacy cue).

### 3.4 Instrument Part and SongInstrumentPart

| Legacy source | Target | Transform |
|---------------|--------|-----------|
| Snippet role slug `visualSnippets` key | `instrument_parts.name` | Normalized display name (see §5) |
| Chart filename prefix `charts/<songId>/<role>.pdf` | `instrument_parts.name` | Same normalization as snippet role |
| `musicians.json` `role` string | `instrument_parts.name` | Catalog enrichment; not 1:1 with SIP |
| `(song_id, instrument_part_id)` | `song_instrument_parts` | One row per distinct role used in song (charts and/or snippets) |
| Resolved chart record | `song_instrument_parts.chart_id` | FK to shared Chart asset |

Role discovery order per song:

1. Collect all keys from `cues[].visualSnippets` across the song.
2. Collect role prefixes from `charts/<legacySongId>/*.pdf` on disk.
3. Merge and deduplicate after normalization.
4. Create SIP for each distinct normalized Instrument Part.

### 3.5 Chart

| Legacy source | Target model.field | Transform |
|---------------|-------------------|-----------|
| `charts/<legacySongId>/<role>.pdf` | `charts.storage_reference` | Future path: `migrated/charts/{band}/{song_code}/{slug}.pdf` |
| `uploads/<hash>.pdf` via `chartAssignments[].file.path` | `charts.storage_reference` | Same; dedupe by content checksum |
| File SHA256 | `charts.checksum` | Computed at import / dry-run |
| `originalname` or derived title | `charts.title` | e.g. "Callejero (Trumpet in Bb)" |
| Parent Song | `charts.song_id` | FK to migrated Song |
| Same checksum within Song | Single Chart row | Multiple SIPs reference same `chart_id` |
| `noChart.txt` assignments | — | **Skip** — not a chart asset |

### 3.6 Snippet

| Legacy field | Target model.field | Transform |
|--------------|-------------------|-----------|
| `visualSnippets[role]` at `cues[i]` | `snippets` row | One per (SIP, Cue) |
| Role slug | `snippets.song_instrument_part_id` | Via normalized Instrument Part + SIP |
| Cue index `i` | `snippets.cue_id` | Via `cue_number = i + 1` lookup |
| PNG file on disk | `snippets.storage_reference` | Future: `migrated/snippets/{band}/{song_code}/{slug}/{cue_number}.png` |
| File SHA256 | `snippets.checksum` | Computed at copy |
| — | `snippets.source_type` | **`chart_crop`** (all legacy PNG snippets) |
| — | `snippets.source_chart_id` | Chart assigned to SIP for that role, if known |
| — | `snippets.chart_revision_at_creation` | Chart checksum at import time |
| — | `snippets.freshness_state` | **`current`** on import (no regeneration) |
| — | `snippets.is_active` | **`true`** |
| `path`, `timestamp`, `i`, `role` | `snippets.source_metadata` | JSON (see §7.3) |
| `visualSnippet` (singular fallback) | import manifest warning | Legacy fallback only; **do not migrate** if `visualSnippets[role]` exists |
| Cloned files (`{songId}_{role}_{index}_{ts}.png`) | `source_type = clone` optional | If detectable via filename pattern; else `chart_crop` with metadata note |

### 3.7 Musician (optional basic)

| Legacy field | Target | Transform |
|--------------|--------|-----------|
| `musicians[].name` | `musicians.name` | Direct |
| `musicians[].email` | user link / notes | If user model linked; else notes |
| `musicians[].role` | import manifest | Inform Instrument Part normalization lexicon only |
| `mixBusId`, `linkedChannels` | **Not imported** | X32 routing — out of scope |

Import musicians only when `--include-musicians` explicitly approved; default import is music library only.

---

## 4. ID and Identity Strategy

### 4.1 Principles

| Rule | Statement |
|------|-----------|
| No legacy timestamp IDs as PKs | Legacy song IDs (e.g. `1768048124047`) must **never** become `songs.id`, `song_code`, or runtime identity |
| song_code assignment | Sequential `001`–`999` in **playlist order** for the imported Show, Band-scoped unique |
| cue_number assignment | Legacy index `i` → `cue_number = i + 1` zero-padded (`001`, `002`, …) |
| sequence_order assignment | Initially equal to numeric cue index + 1; may diverge later without changing cue_number |
| Legacy ID retention | Stored in **import manifest JSON** and optional `import_entity_mappings` audit table (PH030) |
| public_id | Generated fresh UUID per new row — no legacy mapping |

### 4.2 Import manifest strategy (PH030+)

Each import batch produces a versioned manifest:

```json
{
  "import_batch_id": "uuid",
  "legacy_setlist_id": "default",
  "show_name": "Thieves Alley",
  "started_at": "ISO8601",
  "mappings": {
    "songs": { "1768048124047": { "song_code": "001", "song_id": 42, "public_id": "..." } },
    "cues": { "1768048124047:0": { "legacy_index": 0, "cue_number": "001", "cue_id": 101 } },
    "roles": { "trumpet": { "instrument_part_id": 5, "normalized_name": "Trumpet" } },
    "charts": { "/abs/path/trumpet.pdf": { "chart_id": 7, "checksum": "sha256:..." } },
    "snippets": { "/api/charts/snippets/.../cue_1.png": { "snippet_id": 12 } }
  }
}
```

Manifest is the rollback map and audit trail — not a runtime authority.

### 4.3 song_code exhaustion

12 songs in current legacy setlist → `001`–`012`. Import must fail gracefully if playlist exceeds 999 songs.

---

## 5. Cue 000 / Preparation Cue Policy

### 5.1 Recommendation: insert synthetic Cue 000

| Decision | Rationale |
|----------|-----------|
| **Insert Cue 000** per imported Song | PH010.01 requires Preparation Cue; legacy has no equivalent |
| Name | `"Preparation"` |
| `sequence_order` | **`0`** (before all musical sections) |
| Snippets | None initially |
| Actions | None initially |

### 5.2 Legacy cue index → cue_number mapping

| Legacy | PH028 | CC16 (target runtime) | Notes |
|--------|-------|----------------------|-------|
| *(none)* | `cue_number = 000` | CC16 = 0 | Synthetic preparation |
| `cues[0]` | `cue_number = 001` | CC16 = 1 | First musical section |
| `cues[i]` | `cue_number = i + 1` (padded) | CC16 = i + 1 | Direct alignment when Ableton uses 1-based section numbers |
| `sequence_order` | `i + 1` for musical cues; `0` for prep | — | Initially mirrors legacy order |

**Critical:** Legacy `partIndex` is 0-based array index into `cues[]`. New platform CC16 maps to `cue_number`, not array index. Import manifest must record `{ legacy_part_index, cue_number, cc16_equivalent }` for operator verification against Ableton Show File.

### 5.3 Ableton SSS.CCC preservation

After import, runtime identity is `{song_code}.{cue_number}`:

- Song "Callejero" at playlist position 1 → `song_code = 001`
- Legacy first cue (index 0, "Intro") → `001.001`
- Preparation → `001.000`

Operator must reconcile imported `song_code` assignments with existing Ableton Show File PGM/CC16 mapping in PH031 validation report.

---

## 6. Instrument Normalization

### 6.1 Normalization pipeline

```
legacy role string / slug
  → lowercase trim
  → alias lookup (typo table)
  → title case display name
  → instrument_parts.name (Band-scoped catalog)
  → SIP per (Song, Instrument Part)
```

### 6.2 Known alias table (initial)

| Legacy slug / string | Normalized Instrument Part |
|----------------------|---------------------------|
| `machines` | Machines |
| `singer` | Singer |
| `trumpet` | Trumpet |
| `guitarrist` | Guitar |
| `guitar` | Guitar |
| `keyboard` | Keyboard |
| `keys` | Keyboard |
| `drummer` | Drums |
| `drums` | Drums |
| `sous`, `sousaphone` | Sousaphone |
| `alto sax`, `alto_sax` | Alto Sax |
| `bari sax`, `baritone sax` | Baritone Sax |
| `cuatro` | Cuatro |
| `trombone` | Trombone |
| `bass` | Bass |
| `bari` | Baritone Sax |

### 6.3 Shared chart roles

When multiple Instrument Parts share one physical PDF (same checksum):

- Create **one Chart** row for the Song.
- Assign the same `chart_id` to each SIP (e.g. Bass + Sousaphone + Bari Sax sharing a concert pitch chart).
- Snippets remain **per SIP + Cue** — not shared.

Detection: group resolved chart file paths by normalized absolute path + SHA256 within each Song.

### 6.4 Unresolved role review report

PH031 dry-run must list:

| Category | Action |
|----------|--------|
| Role slug with no chart file and no snippets | Flag for director review |
| `chartAssignments` with only `noChart.txt` | Map to SIP but no `chart_id` |
| Role in chart filename but no snippets | Import chart only |
| Snippet role with no matching chart | Import snippet; `source_chart_id = null`; freshness `needs_review` |
| Ambiguous monitorBus-only assignment with no role slug | Manual mapping required |

---

## 7. Chart Migration Design

### 7.1 Chart file resolution order

For each `(legacySongId, normalizedRole)` pair:

1. **Primary:** `{projectRoot}/charts/{legacySongId}/{roleSlug}.pdf`
2. **Secondary:** Latest non-`noChart.txt` entry in `chartAssignments[]` whose resolved path exists on disk and matches role via monitorBus → musician role inference
3. **Tertiary:** Search `uploads/` for PDF referenced in assignments (newest `timestamp` or last array entry wins)
4. **Fallback:** `backups/boot_*/` JSON + file path if primary missing (PH033 asset validation)

### 7.2 Shared chart detection

```
FOR each candidate chart file path:
  normalize to absolute path
  compute SHA256
  IF checksum seen for this song:
    reuse existing charts.id
  ELSE:
    create new Chart row with charts.song_id = migrated song
  SET song_instrument_parts.chart_id for each SIP using this file
```

Observed legacy duplicates (same path, multiple assignment rows): e.g. `machines.pdf` referenced 4× — must produce **one Chart**, one checksum.

### 7.3 Missing chart files

| Condition | Dry-run report | Import behaviour |
|-----------|---------------|------------------|
| File not found | `missing_chart_files[]` | Skip `chart_id` assignment; SIP created without chart |
| `noChart.txt` placeholder | `placeholder_charts_skipped[]` | No Chart row |
| Zero-byte file | `invalid_chart_files[]` | Skip; flag blocker if SIP has snippets requiring chart linkage |

### 7.4 Checksum usage

- **Dedup:** Same SHA256 within a Song → one Chart record, many SIP assignments.
- **Snippet linkage:** Store chart checksum in `snippets.chart_revision_at_creation` at import.
- **Post-import freshness:** If chart file replaced later, `ChartSnippetFreshnessService` marks snippets `out_of_date` — not during initial import.

---

## 8. Snippet Migration Design

### 8.1 Legacy visualSnippets → Snippet

For each `song.cues[i].visualSnippets[roleSlug]`:

1. Resolve SIP: `(migrated Song, normalized Instrument Part from roleSlug)`.
2. Resolve Cue: `cue_number = str_pad(i + 1, 3, '0')`.
3. Resolve PNG path: `/api/charts/snippets/...` → `{projectRoot}/charts/snippets/{legacySongId}/{roleSlug}/{filename}`.
4. Verify file exists; compute checksum.
5. Insert Snippet with `source_type = chart_crop`, `is_active = true`, `freshness_state = current`.
6. Set `source_chart_id` if SIP has chart assignment.
7. Skip `visualSnippet` singular when role-specific entry exists.

### 8.2 source_metadata JSON structure

```json
{
  "legacy_song_id": "1768048124047",
  "legacy_cue_index": 1,
  "legacy_role_slug": "trumpet",
  "legacy_path": "/api/charts/snippets/1768048124047/trumpet/cue_1.png",
  "legacy_timestamp": 1768521219821,
  "legacy_filename": "cue_1.png",
  "import_batch_id": "uuid",
  "crop": {
    "page": null,
    "x": null,
    "y": null,
    "width": null,
    "height": null
  }
}
```

Legacy system did not persist crop coordinates — fields remain null unless inferrable later.

### 8.3 Duplicate snippet handling

| Scenario | Rule |
|----------|------|
| Same SIP + Cue, multiple role entries | Should not occur — one role per SIP |
| Same PNG path referenced twice | Import once; manifest dedup |
| `cue_N.png` overwritten on disk but JSON stale | Checksum mismatch → `freshness_state = needs_review` |
| Timestamped clone filenames | Import as separate Snippet; detect clone via filename pattern → optional `source_type = clone` |

### 8.4 Missing snippet files

- Record in `missing_snippet_files[]` in dry-run report.
- Do not create Snippet row if file missing (metadata-only orphan prevention).
- Snippet with missing chart but present PNG → import snippet with `source_chart_id = null`, `freshness_state = needs_review`.

### 8.5 Out-of-date default

All successfully imported legacy snippets: **`freshness_state = current`**. Chart updates after import trigger out-of-date via domain service — not during migration.

---

## 9. Asset Storage Design (future PH030–PH033)

No file copying in PH029. Recommended target layout under production asset root:

| Asset type | Proposed storage_reference pattern |
|------------|-----------------------------------|
| Full chart PDF | `migrated/charts/{band_slug}/{song_code}/{instrument_slug}.pdf` |
| Snippet PNG | `migrated/snippets/{band_slug}/{song_code}/{instrument_slug}/{cue_number}.png` |
| Cloned snippet | `migrated/snippets/{band_slug}/{song_code}/{instrument_slug}/{cue_number}_{import_ts}.png` |
| Annotation (future) | `migrated/snippets/{band_slug}/{song_code}/{instrument_slug}/{cue_number}_annotation.json` |
| Markup layer (future) | `migrated/snippets/{band_slug}/{song_code}/{instrument_slug}/{cue_number}_markup.png` |

Checksums stored in DB; manifest records `{legacy_path, storage_reference, checksum}` for PH033 asset copy validation.

---

## 10. Validation / Dry-Run Report Design (PH031)

PH031 dry-run produces a structured report (JSON + human-readable summary) without database writes:

### 10.1 Counts

| Metric | Source |
|--------|--------|
| `shows` | 1 per batch |
| `playlist_items` | `songOrder.length` |
| `songs` | Unique IDs in playlist |
| `cues` | Sum of `cues.length` + synthetic Cue 000 per song |
| `instrument_parts` | Distinct normalized roles across all songs |
| `song_instrument_parts` | Per (song, role) with chart and/or snippet |
| `charts` | Deduped chart files |
| `snippets` | Deduped visualSnippets entries with files present |

### 10.2 Issue categories

| Report section | Content |
|----------------|---------|
| `missing_chart_files[]` | `{legacySongId, role, expectedPath}` |
| `missing_snippet_files[]` | `{legacySongId, cueIndex, role, legacyPath}` |
| `duplicate_chart_checksums[]` | Checksums with multiple paths — confirm merge intent |
| `duplicate_mappings[]` | Conflicting cue_number assignments |
| `unresolved_roles[]` | Roles failing normalization |
| `zero_cue_songs[]` | Songs in playlist with empty `cues[]` |
| `orphan_snippets[]` | Snippet metadata with no matching cue index |
| `placeholder_charts_skipped[]` | noChart.txt assignments |
| `checksum_mismatches[]` | JSON path vs disk content hash |
| `migration_blockers[]` | Any condition that must halt import |
| `warnings[]` | Non-blocking issues (missing chart but snippet present, etc.) |
| `cue_mapping_table[]` | Per song: `{legacy_index, cue_number, sequence_order, cc16_equiv, name}` |
| `song_code_assignments[]` | `{legacySongId, title, song_code, playlist_position}` |

### 10.3 Blocker conditions (import must not proceed)

- Playlist song ID not found in `songs{}`
- song_code overflow (>999)
- Duplicate song_code within Band after assignment
- Zero songs in playlist
- Target Band not found / not specified
- Existing Show with same name and overlapping song_codes when `--no-overwrite` (default)

---

## 11. Rollback / Safety Design

### 11.1 Principles

| Rule | Statement |
|------|-----------|
| Dry-run first | PH031 must pass with zero blockers before PH032 |
| No silent overwrite | Default: abort if target Show or song_codes exist |
| Batch isolation | All writes tagged with `import_batch_id` |
| Transaction boundaries | One transaction per Song (Cue + SIP + Chart + Snippet) — partial song rollback possible |
| Asset copy after DB | PH032 creates DB rows; PH033 validates/copies files (or PH032 copies in same batch with staging) |
| Audit trail | `import_batches` + `import_entity_mappings` tables (PH030 schema) |

### 11.2 Proposed audit tables (PH030 — design only)

**import_batches**

| Column | Purpose |
|--------|---------|
| `id`, `public_id` | PK / sync |
| `band_id` | Target band |
| `legacy_setlist_id` | Source setlist key |
| `status` | `dry_run`, `staged`, `committed`, `rolled_back`, `failed` |
| `manifest_json` | Full mapping snapshot |
| `report_json` | PH031 validation output |
| `started_at`, `completed_at` | Timestamps |
| `initiated_by_user_id` | Operator |

**import_entity_mappings**

| Column | Purpose |
|--------|---------|
| `import_batch_id` | FK |
| `entity_type` | song, cue, chart, snippet, etc. |
| `legacy_key` | e.g. `1768048124047` or `1768048124047:2:trumpet` |
| `entity_id` | New DB id |
| `public_id` | New UUID |

### 11.3 Rollback strategy (PH032)

1. Locate `import_batch_id`.
2. Delete Snippets, Charts (if no other SIP references), SongInstrumentParts, Cues, ShowPlaylistItems, Songs created in batch — **reverse dependency order**.
3. Delete Show if created by batch.
4. Remove copied asset files listed in manifest (PH033).
5. Mark batch `rolled_back`; retain audit row.

**No destructive overwrite** of pre-existing canonical data unless `--force-overwrite` explicitly passed and logged.

---

## 12. Schema Compatibility Assessment

PH028 schema supports all PH029 migration targets. **No schema changes required.**

| PH029 requirement | PH028 support |
|-------------------|---------------|
| Shared Chart asset | ✅ `charts.song_id` + `song_instrument_parts.chart_id` |
| Snippet source type | ✅ `snippets.source_type` |
| Clone provenance | ✅ `source_snippet_id` |
| Freshness | ✅ `freshness_state` |
| Crop metadata | ✅ `source_metadata` JSON |
| One active snippet per SIP + Cue | ✅ partial unique index |
| Cue identity vs sequence | ✅ `cue_number` + `sequence_order` |
| Import audit | ⬜ PH030 — new tables, not PH029 |

---

## 13. Recommended Future Phase Plan

| Phase | Scope |
|-------|-------|
| **PH030** | Legacy Import Parser Foundation — read `setlists.json` / `musicians.json`, normalize roles, resolve paths, build in-memory migration plan; `import_batches` / `import_entity_mappings` schema; **no writes** |
| **PH031** | Dry-Run Migration Validation — emit full report (§10); operator review gate |
| **PH032** | Controlled Legacy Show Import — transactional DB writes per Song; manifest persistence; `--dry-run` flag honoured |
| **PH033** | Legacy Asset Copy Validation — copy PDF/PNG to `migrated/` paths; verify checksums; reconcile storage_reference values |

---

## 14. Governance Compliance

| Constraint | Status |
|------------|--------|
| Design only | ✅ |
| No import commands | ✅ |
| No data import | ✅ |
| No schema changes | ✅ |
| No frontend changes | ✅ |
| No runtime / X32 changes | ✅ |
| Song Code + Cue Number identity | ✅ Addressed in §4–5 |

---

End of PH029 — Legacy Migration Design
