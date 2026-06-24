# Decision Log

## Foundational Decisions

| ID | Decision |
|----|----------|
| 001 | Ableton is authoritative. |
| 002 | Cue = Song Section Boundary. |
| 003 | Cue 0 = Preparation Cue. |
| 004 | Live Show View is Priority #1. |
| 005 | The platform must operate offline. |
| 006 | Songs are reusable assets. |
| 007 | Shows reference Songs. |
| 008 | Performances execute Shows. |
| 009 | Musician experience is musician-centric. |
| 010 | Mix Moves are reusable assets. |
| 011 | Light Modes are reusable assets. |
| 012 | The show must go on. |

## PH001 — Domain Model Finalisation

| ID | Decision | Rationale |
|----|----------|-----------|
| 013 | Band is the top-level domain container. | All master assets and Shows belong to a Band context. |
| 014 | Instrument Parts are global role definitions. | Voice roles (Lead Vocal, etc.) are Instrument Parts, not separate entities. |
| 015 | Capability links Musician to Instrument Part (eligibility only). | Assignment is operational per Performance; Capability declares what a Musician *can* do. |
| 016 | Playlist is imported from Ableton Show File, not authored in platform. | Ableton owns playlist and cue progression. |
| 017 | Actions are attached to Cues; Cues are not Actions. | Light flash, mix change, etc. are Actions triggered at Cue boundaries. |
| 018 | Mix Moves are grouped parameter changes, not X32 scene recalls. | Explicit parameter groups for reliability and clarity. |
| 019 | Snippet is a Chart portion associated with a Cue. | Separates chart navigation content from Cue boundary events. **Amended by PH027** — Snippet is a cue-specific visual reference asset for SongInstrumentPart + Cue; not exclusively a Chart child. |
| 020 | Stage Plot and Tech Rider are informational production documents. | Spatial layout and technical requirements; they do not drive runtime automation. |
| 021 | Readiness is a distinct gate state after Soundcheck. | Soundcheck is the process; Readiness is the cleared-to-perform state. |
| 022 | Cloud is canonical; Local Runtime is authoritative during live performance. | Sync supports collaboration; performance does not depend on cloud. |
| 023 | Ableton Protocol State (PGM/CC16) is runtime-only, separate from authored Cue definitions. | Domain Cues are authored; Ableton signals are consumed at runtime. |
| 024 | Operator runs Performance, not Show. | Show is the template; Performance is the executable unit. |
| 025 | Assignments may vary by Song and Cue within a Performance. | Musicians may change instruments/roles during a show. |

## PH001.01 — Assignment Entity Formalisation

| ID | Decision | Rationale |
|----|----------|-----------|
| 026 | Assignment is a standalone domain entity. | Operational Musician ↔ Instrument Part mapping is distinct from Capability, Availability, Instrument Part, Device, and Chart. |

## PH002 — Runtime & Operational Model

| ID | Decision | Rationale |
|----|----------|-----------|
| 027 | Ableton is the runtime timeline authority; the platform follows and never overrides cue progression during performance. | Timeline ownership is external; platform is orchestration layer. |
| 028 | PGM is show-scoped; CC16 identifies cue within active song; CC16 0 is preparation, 1+ are sections. | Protocol mapping rules for canonical Show/Song/Cue resolution. |
| 029 | Ableton Show File owns playlist, song order, PGM numbers, cue timing, and cue progression. | Platform imports; does not author runtime timeline. |
| 030 | Cue 0 prepares the next song before Cue 1; automatic by default; musician chart override is display-only. | Preparation window without timeline authority change. |
| 031 | Local Show Runtime is authoritative during performance; cloud is not required once performance starts. | Local-first live execution. |
| 032 | Remote canonical data must be synced to Local Show Runtime before performance (sync-before-show). | Preparation requirement, not runtime dependency. |
| 033 | Live performance assumes no internet; all required assets must be present locally before show start. | Offline operation is mandatory, not optional. |
| 034 | Failed actions must not stop remaining actions or performance; failures are logged and surfaced. | The show must go on. |
| 035 | Readiness is collaborative (system, production, musician); warnings do not necessarily block performance. | Operator judgment; degraded guidance beats halted execution. |
| 036 | Missing assignment coverage may fall back to Ableton; platform logs gaps but does not halt show. | Fallback when musicians/devices/charts unavailable. |
| 037 | Musician device view is musician-centric; automatic chart navigation default; manual override allowed without changing timeline authority. | Guidance over restriction. |
| 038 | Runtime maintains Previous, Current, and Next Cue as minimum context; deeper lookahead is future scope. | Baseline cue context for operator and musician displays. |

## PH003 — Information Architecture & UX Model

| ID | Decision | Rationale |
|----|----------|-----------|
| 039 | Director is the primary user of the system. | All UX serves show preparation and live execution. |
| 040 | Application navigation is Show-centric; everything operates within Active Show context. | Production variants are organised by Show. |
| 041 | Live Show View is the most important screen; all UX decisions must be justified against it. | Live performance is highest priority. |
| 042 | Musician device navigation is Performance-centric (My Performances → Current Performance). | Musicians execute Performances, not Shows. |
| 043 | Playlist is the centre of gravity of Show Preparation activity. | Ableton Show File drives song order and downstream preparation. |
| 044 | Master Library supports Shows but is not the primary user workflow. | Director's journey is Show-centric. |
| 045 | Live Show View is read-first, show-safe, non-blocking, and operable under pressure. | Operator awareness without timeline override or cloud dependency. |
| 046 | Musician device view is musician-centric with automatic chart navigation and manual override. | Guidance over restriction. |
| 047 | Soundcheck uses collaborative readiness (system, production, musician) with Ready/Warning/Not Ready states. | Aligns UX with runtime readiness model. |
| 048 | Readiness warnings do not necessarily block entry to Live Show View. | Operator judgment; the show must go on. |
| 049 | Show Preparation must not compromise Live Show View or Soundcheck UX quality. | Priority 3 serves Priority 1 and 2. |
| 050 | UX defines behaviour only — not implementation, frameworks, or wireframes. | PH003 is behavioural architecture. |

## PH004 — Data Architecture & Persistence Model

| ID | Decision | Rationale |
|----|----------|-----------|
| 051 | Data authority is phase-aware: Director Local (draft) → Cloud (published) → Local Runtime (performance). | Matches creation/collaboration/performance lifecycle. |
| 052 | Director Local is primary preparation authority; draft data is not visible until published. | Local-first design; explicit publish boundary. |
| 053 | Cloud is canonical for published master library and operational records. | Collaboration, backup, musician access. |
| 054 | Local Show Runtime is authoritative during performance; inbound cloud sync blocked while Performance is live. | Protect active show; no cloud disruption. |
| 055 | Sync-before-show is required via explicit Published Package pull. | All show data and files local before Soundcheck. |
| 056 | Publish validates required dependencies before making data cloud-visible. | Prevent incomplete show packages reaching runtime. |
| 057 | Files are managed assets in DigitalOcean Spaces with metadata and object references. | No canonical ad hoc local paths. |
| 058 | Local runtime must cache required show files before performance; missing files detected at Soundcheck. | Offline-safe file access. |
| 059 | User (auth identity) and Musician (domain entity) are related but distinct. | Laravel auth separate from performance person. |
| 060 | Musician edits are limited to permitted self-service data; Director owns production structure. | Role-boundary data governance. |
| 061 | Runtime cue state (PGM/CC16) is local/runtime-only; cloud does not control live timeline. | Ableton authority preserved in persistence layer. |
| 062 | Conflicts are surfaced for operator review; no silent overwrite of show-critical data. | Show safety over convenience. |
| 063 | Monitor Assignment is operational hybrid data synced with Performance package. | Monitor routing per Performance. |
| 064 | Database/schema work must not proceed unless aligned to DATA_ARCHITECTURE.md. | Governance gate before implementation. |

## PH005 — Database Architecture & Logical Schema Design

| ID | Decision | Rationale |
|----|----------|-----------|
| 065 | `docs/DATABASE_ARCHITECTURE.md` is the canonical database architecture authority. | Separates logical schema design from PH004 data ownership rules. |
| 066 | Persistence is organised into twelve logical domains with explicit aggregate boundaries. | Band, Song, Show, Performance, Musician, Production Configuration, and Runtime State are aggregate roots. |
| 067 | Cloud database stores published canonical collaboration and preparation data. | Master library, operational records, sync package registry, and audit after publish. |
| 068 | Local Show Runtime database is authoritative during performance for runtime, Soundcheck, and Readiness. | Live performance must not depend on cloud database availability. |
| 069 | Published Show Package is the atomic sync unit pulled to Local Show Runtime before performance. | Explicit package with manifest, checksums, and sync status — not ad hoc queries. |
| 070 | File assets persist as managed object references in database; binaries in Spaces; local cache for runtime. Production folders `/resources/`, `/songs/`, `/charts/`, `/uploads/` must not be Git-tracked. | Git stores code and docs only; production assets are managed runtime assets. |
| 071 | User (auth identity) and Musician (domain entity) are persisted separately with explicit link. | Laravel authentication approved; role-boundary data governance. |
| 072 | All future schema changes must use versioned migrations only — no ad hoc DDL. | Governed schema evolution aligned to DATABASE_ARCHITECTURE and DATA_ARCHITECTURE. |
| 073 | Runtime cue state (PGM/CC16, timeline context) is local-runtime-only during performance — not cloud-canonical. | Preserves Ableton authority in persistence layer. |
| 074 | Audit history is required for publish, sync, assignment changes, chart changes, readiness, action failures, Soundcheck, performance completion, and file availability. | Observable trail for rollback analysis; no silent mutation of show-critical data. |

## PH006 — Integration & Runtime Architecture

| ID | Decision | Rationale |
|----|----------|-----------|
| 075 | `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` is the canonical integration architecture authority. | Separates bridge topology and event flow from runtime behaviour (PH002) and infrastructure (ARCHITECTURE). |
| 076 | Docker hosts app/runtime infrastructure; MIDI and USB-DMX hardware access runs on host OS via bridge services. | Containers must not be assumed to have direct hardware access. |
| 077 | MIDI Bridge runs on host OS; decodes Ableton PGM/CC16; publishes TimelineEvents to Local Show Runtime; does not own show state. | Preserves Ableton as timeline master; single ingress path. |
| 078 | Lighting Bridge runs on host for USB-DMX; translates Light Mode Actions to DMX/Art-Net/sACN/lighting software; network protocols may run host or Docker. | Light Modes are authoring unit; raw DMX is translation layer. |
| 079 | X32 Bridge translates Mix Move Actions to X32 OSC; failures are logged and non-blocking. | Mix Moves are grouped parameter changes, not primarily scene recalls. |
| 080 | Canonical runtime event flow: Ableton → MIDI Bridge → Runtime Event → Local Runtime → Resolve Song/Cue → Execute Actions → Devices/X32/Lighting/Logs. | Single documented pipeline for integration implementation. |
| 081 | Musician devices connect to Local Show Runtime on local network; no cloud required; manual chart browsing is display-only. | Musician-centric local-first device experience. |
| 082 | Integration failures (X32, lighting, devices, bridges) are logged and surfaced but must not stop performance or remaining cue Actions; cloud unavailable has no performance impact. | The show must go on. |

## PH007 — Physical Database & Migration Planning

| ID | Decision | Rationale |
|----|----------|-----------|
| 083 | PostgreSQL 16+ is the selected database engine for cloud, Director local, and Local Show Runtime. | JSONB manifest support, Laravel/DigitalOcean/Docker parity, single-engine operational simplicity; aligns with Ed's preference. |
| 084 | Three distinct PostgreSQL databases: cloud (DO managed), Director local, Local Show Runtime (Docker). | Phase-aware authority; runtime DB required during performance; cloud not required during performance. **PH055 amends physical topology:** two physical databases (Cloud Database, Live Stage Database); three workspaces (Cloud Studio, Website, Live Stage). Decision 084 deployment contexts preserved; see PH055 Decisions 183–184. |
| 085 | Laravel migrations are the sole approved schema change mechanism; no manual DDL or untracked schema edits. | Version-controlled governed schema evolution. |
| 086 | Initial migrations follow dependency order M1–M14 (identity → band → assets → songs → shows → performances → sync → runtime → audit). | FK integrity and aggregate hierarchy respected. |
| 087 | Internal bigint PKs plus UUID public_id for API/sync; PGM/CC16 as external mapping fields — not primary keys. | Sync-safe cross-environment references. |
| 088 | File asset records store Spaces bucket/key and checksum as canonical reference; local cache path runtime-only; production folders not Git-tracked. | Aligns PH004/PH005 file governance. |
| 089 | Runtime state (timeline, connections, action logs, bridge health) persisted in Local Show Runtime DB only — not cloud-authoritative during performance. | Preserves Ableton authority and offline operation. |
| 090 | Sync package persistence uses published_packages, manifests (JSONB), entity/file checksums, and sync_states. | Explicit package model for sync-before-show. |
| 091 | Audit tables required for publish, sync, assignment/chart/file changes, Soundcheck, readiness, action failures, and performance events. | Observable trail; no silent mutation. |
| 092 | Prohibited: production assets in Git, manual DB edits, localStorage authority, hard-coded IDs/emails, silent drops of show-critical data, cloud-authoritative runtime cue state. | Show safety and governance enforcement. |

## PH008 — Foundation Implementation Planning

| ID | Decision | Rationale |
|----|----------|-----------|
| 093 | First implementation slice is Login → Band → Show list → Active Show → Basic Playlist view. | Proves Laravel, PostgreSQL, auth, migrations, and Show/Playlist read path before runtime complexity. |
| 094 | Foundation stack is Laravel 11+, PostgreSQL 16+, Docker Compose, Valkey (Redis-compatible), Laravel session auth. | Aligns PH007 engine choice with local-dev parity; no cloud required for foundation. |
| 095 | Local development uses Docker Compose (app, postgres, valkey) on Director workstation without cloud dependency. | Local-first preparation; same service pattern as future runtime stack. |
| 096 | M1 Identity & Access is the first migration group; M2/M8 minimal follow for vertical slice after M1 gates pass. | Respects PH007 dependency order; M1 scoped to users/roles only. |
| 097 | Authentication foundation uses Laravel Breeze (session) with Director/Admin roles; bootstrap user via artisan command — not hard-coded emails. | Governed auth without production identity leakage. |
| 098 | Initial seed: one Band ("Ed and the Shadow Boys") + roles; Director created via `esb:create-director` command. | Minimal seed; no sample production data without approval. |
| 099 | PH009 implementation requires explicit gates: governance check, clean tree, engine confirmed, Docker approach, migration order, rollback, test plan. | No code until foundation plan acknowledged. |
| 100 | Foundation slice excludes runtime integrations: no MIDI, DMX, X32, bridges, Reverb, Performances, sync packages, or cloud Spaces. | Vertical slice proves read path only; integrations deferred. |

## PH010.01 — Song/Cue Identity Governance

| ID | Decision | Rationale |
|----|----------|-----------|
| 101 | **Song Code** (`song_code`, format `NNN`, range `001`–`999`) is the canonical Song business identity; unique across the Song Library. | Human-meaningful stable identifier for runtime, Ableton naming, and validation; distinct from relational `id`. |
| 102 | **Cue Number** (`cue_number`, format `NNN`, range `000`–`999`) is the canonical Cue identity within a Song; constraint `unique(song_id, cue_number)`. | Cue identity is Song-scoped; supports preparation and section numbering without global cue IDs. |
| 103 | Canonical Runtime Identity format is **`SSS.CCC`** (Song Code + Cue Number), derived at runtime. | Single cross-subsystem identity for logging, guidance, snippets, charts, and validation. |
| 104 | **Cue 000** is the Preparation Cue — exists before the first musical section. | Aligns with Cue 0 / CC16 = 0 preparation model; explicit three-digit convention. |
| 105 | Approved Ableton naming convention is **`SSS.CCC.Song Name.Cue Name`**. | Parseable bridge between Ableton Show File identifiers and platform canonical definitions. |
| 106 | Database `id` and `public_id` are relational/sync identifiers only; must not be used as runtime identities. | Preserves PH007 identifier strategy; runtime uses Song Code + Cue Number. |

## PH027 — Snippet Domain Reconciliation

| ID | Decision | Rationale |
|----|----------|-----------|
| 107 | **Snippet** is a cue-specific visual reference asset for an Instrument Part within a Song — not merely a chart fragment or cue note. | Supports multiple origin types (crop, photo, upload, clone, drawing); separates visual assets from Actions and instructions. |
| 108 | Snippet belongs to **SongInstrumentPart + Cue** context — not to Chart as a child entity. | Aligns with assignment resolution path; Chart is whole document; Snippet is independent copied asset. |
| 109 | One active Snippet per **SongInstrumentPart + Cue**; constraint `unique(song_instrument_part_id, cue_id)`. | One visual reference per part per cue section; matches legacy behaviour and foundation schema. |
| 110 | Snippets are **copied, not shared** between cues; cloning creates an independent asset. | Reuse from another cue must not mutate source; each cue owns its snippet binary. |
| 111 | A **SongInstrumentPart** uses exactly one Chart for that Song; a Chart may be shared by many SongInstrumentParts. | Separates reusable chart file assets from per-cue snippet copies. |
| 112 | Chart updates do **not** auto-regenerate Snippets; affected snippets are flagged **out-of-date**. | Preserves musician annotations and explicit authoring; avoids silent content changes mid-preparation. |
| 113 | Snippet **source type** distinguishes origin: `chart_crop`, `photo`, `image_upload`, `cloned_snippet`, `freehand_drawing`. | Provenance for UI, migration, and freshness rules. |
| 114 | Musicians may **annotate / mark up** Snippets; markup is per-Snippet. | Legacy Cue View behaviour; does not mutate parent Chart. |
| 115 | **Cue identity** (`cue_number`) is stable and distinct from **cue sequence** (display/performance order). | Supports special arrangements without reassigning snippet bindings. |
| 116 | Live musician view minimum: current snippet, next snippet, next +1 snippet, optional full chart mode. | Lookahead guidance for performance; full chart is display override only. |
| 117 | Chart Mode workflow: crop → select cue (empty cues only for that SongInstrumentPart) → save independent Snippet. | Captures legacy Digital Scissors / SnippetMaker behaviour. |
| 118 | PH019 retained in history but **amended** — Snippet separates navigation content from Cue boundaries; definition expanded per PH027. | Preserves phase history while correcting scope. |

## PH028 — Snippet Schema Design

| ID | Decision | Rationale |
|----|----------|-----------|
| 119 | Chart is a **Song-scoped file asset** (`charts.song_id`); SongInstrumentPart holds `chart_id` for one chart assignment. | Supports shared chart assets across multiple SongInstrumentParts without duplicating file metadata. |
| 120 | Snippet partial unique index on `(song_instrument_part_id, cue_id) WHERE is_active = 1`. | One active Snippet per context; inactive historical rows preserved. |
| 121 | Snippet `source_type` values: `chart_crop`, `photo`, `upload`, `clone`, `drawing`. | Covers all confirmed origin paths; distinct from PH027 proposed names where simplified (`upload` vs `image_upload`, `drawing` vs `freehand_drawing`). |
| 122 | Snippet freshness states: `current`, `out_of_date`, `needs_review`. | Chart updates flag affected snippets; no auto-regeneration. |
| 123 | Snippet annotation/markup via storage reference columns + `source_metadata` JSON. | Defers frontend rendering; supports layered assets without prescribing UI. |
| 124 | Cue `sequence_order` column separate from `cue_number`. | Performance/display order may differ from stable cue identity. |
| 125 | `ChartSnippetFreshnessService` marks chart-crop snippets out-of-date on chart update. | Domain service only — no runtime execution or auto-regeneration. |

## PH029 — Legacy Migration Design

| ID | Decision | Rationale |
|----|----------|-----------|
| 126 | Legacy timestamp song IDs (e.g. `1768048124047`) are **audit metadata only** — never `song_code`, PK, or runtime identity. | Preserves PH010.01; new `song_code` assigned sequentially in playlist order (`001`–`999`). |
| 127 | Legacy cue array index `i` maps to `cue_number = str_pad(i + 1, 3, '0')` and `sequence_order = i + 1`. | Aligns legacy 0-based index with 1-based CC16 section numbering; identity stable after import. |
| 128 | Import **inserts synthetic Cue 000** (Preparation) per Song. | Legacy has no preparation cue; PH010.01 requires Cue 000 before first musical section. |
| 129 | Legacy `visualSnippets[role]` maps to Snippet via normalized Instrument Part + SIP + Cue; `source_type = chart_crop`. | All legacy PNG snippets are chart crops; role slug drives SIP resolution. |
| 130 | Chart files deduplicated by **SHA256 checksum** within a Song; shared Chart referenced by multiple `song_instrument_parts.chart_id`. | Observed legacy duplicate assignment rows pointing to same PDF; matches PH028 sharing model. |
| 131 | Chart resolution order: `charts/{legacySongId}/{role}.pdf` → assignment uploads → boot backup fallback. | Matches Node server lookup behaviour in `index.js`. |
| 132 | `noChart.txt` placeholder assignments are **skipped** — no Chart row created. | Not a chart asset; monitor-only musicians. |
| 133 | Import manifest + `import_batches` / `import_entity_mappings` audit tables carry legacy→canonical mappings. | Enables rollback and operator verification; implementation in PH030. |
| 134 | Dry-run (PH031) must pass with **zero blockers** before controlled import (PH032). | Safety gate; no silent overwrite of existing canonical data. |
| 135 | Musician import is **optional and basic** (name/email only); X32 routing from `musicians.json` is out of scope. | Routing belongs to Assignment/Monitor Assignment domain. |
| 136 | Legacy `partIndex` / CC16 bridge recorded in manifest; operator reconciles with Ableton Show File. | Legacy 0-based index vs canonical `cue_number` requires explicit mapping table. |
| 137 | Imported snippets default to `freshness_state = current`; no auto-regeneration during import. | PH028 freshness service applies only to post-import chart updates. |

## PH030 — Legacy Import Parser Foundation

| ID | Decision | Rationale |
|----|----------|-----------|
| 138 | Legacy import parser is **read-only** — builds `LegacyMigrationPlan` in memory; no canonical entity writes. | PH030 foundation only; controlled import deferred to PH032. |
| 139 | `LegacyMigrationPlanService::buildPlan()` is the programmatic entry point. | Returns normalized plan + manifest; CLI deferred to PH031+. |
| 140 | Import audit schema (`import_batches`, `import_entity_mappings`) added for future rollback; parser does not populate them yet. | PH029 rollback design; PH032 writes batch records on commit. |
| 141 | Legacy role normalization via `LegacyRoleNormalizer` alias table (e.g. `guitarrist` → Guitar). | Consistent Instrument Part catalog from heterogeneous legacy strings. |
| 142 | Chart deduplication in parser uses SHA256 within Song scope; shared charts expose multiple `assignedRoleSlugs`. | Matches PH028 sharing model at plan stage. |
| 143 | Missing assets collected in `LegacyMigrationIssues` — never silently ignored. | PH031 dry-run report consumes same structure. |

## PH031 — Dry-Run Migration Validation

| ID | Decision | Rationale |
|----|----------|-----------|
| 144 | Dry-run validation is **read-only** — produces `LegacyDryRunValidationReport`; no canonical writes, no asset copies, no `import_batches` persistence. | PH031 is the operator safety gate before PH032 controlled import. |
| 145 | Validation status classified as `PASS`, `PASS_WITH_WARNINGS`, or `BLOCKED`. | BLOCKED only when parser blockers present; missing assets are warnings. |
| 146 | `legacy:import-dry-run` Artisan command accepts `--root` plus optional path overrides for real legacy show directories. | Legacy assets may not be in git; paths must be operator-supplied. |
| 147 | JSON report is primary output; human summary optional via `--summary` or when `--output` writes file. | Machine-readable report for PH032 tooling; stdout remains valid JSON when piping. |
| 148 | Report builder enriches PH030 plan with shared chart detection, duplicate mapping detection, and unresolved role analysis. | Full PH029 §10 dry-run report requirements without duplicating parser logic. |

## PH045 — Band People Canonical Schema Reconciliation

| ID | Decision | Rationale |
|----|----------|-----------|
| 149 | Band People / Production Personnel is **canonical shared schema** — one PostgreSQL structure for local app and website; no local-only or website-only personnel tables. | Single source of truth for onboarding, travel, festival, and export workflows. |
| 150 | Physical tables: `people`, `person_secure_fields`, `person_files`, `instrument_reference`, `person_instruments`, `person_iem_settings`. | Implemented in migration `2026_06_23_110000_create_band_people_schema.php` (commit `2d53043`). |
| 151 | Bank account, passport number, and Air New Zealand points stored **encrypted at rest** in `person_secure_fields` via Laravel application encryption with `encryption_key_context`. | Sensitive values must not be plain text; default serialization hides ciphertext. |
| 152 | `person_files.is_public` defaults **false**; passport photos and travel documents require access control. | Private-by-default document governance. |
| 153 | `person_iem_settings` are **preference templates only** — not live console bus settings. Selected templates may be copied/applied to performance bus settings later. | Separates onboarding preferences from runtime monitor state. |
| 154 | **`musicians` remains the operational domain** for Performances, Assignments, Devices, and Capabilities. Musician ↔ Person mapping is a **follow-up phase** — not implemented in PH045. | Avoids breaking operational runtime paths; Person is additive personnel layer. |
| 155 | **`instrument_reference` and `instrument_parts` are separate catalogs.** Person Instruments use Instrument Reference; Capabilities use Instrument Part. Mapping may be required later. | Onboarding catalog vs operational role catalog serve different workflows. |
| 156 | Stage plots, tech riders, input lists, monitor plans, and festival packs are **generated artifacts** from canonical production data — not parallel personnel schemas. | Artifacts are outputs; canonical data lives in Person, Musician, Assignment, and related entities. |
| 157 | Existing Band People UI routes (`/people`) currently operate on **`musicians`** table — UI reconciliation to `people` is follow-up. | Schema precedes UI migration; documented to prevent architecture drift assumptions. |
| 158 | `instrument_reference` is **not band-scoped** in current implementation; `people` is band-scoped. | Global personnel instrument catalog; band scoping on Person records. |
| 159 | Person Files use `file_path` metadata in M3a; full Spaces bucket/key alignment with `file_assets` governance is follow-up. | Initial schema stores managed path reference; canonical object storage integration deferred. |

## PH046 — PHP 8.4 Runtime Baseline

| ID | Decision | Rationale |
|----|----------|-----------|
| 160 | Project PHP runtime baseline is **8.4** for `/backend` (local foundation) and `/server` (Forge Band Portal). Composer constraints use `^8.4` with platform `8.4.0`. | Unified deploy and dev target; Forge PHP 8.4; avoids documenting or requiring PHP 8.5. |
| 161 | Laravel **13.8+** remains the framework baseline on PHP 8.4 for both Laravel apps. | No framework drift between local and cloud apps. |
| 162 | `/client/` has no Composer PHP constraint; Node 20 runtime is unchanged. | Client is not a PHP application. |

---

## PH047 — Band Portal Authentication & Canonical Identity

| ID | Decision | Rationale |
|----|----------|-----------|
| 163 | **Person** is the canonical human/profile record — legal name, artistic name, contact, travel, dietary, passport (secure), banking (secure), instruments, files, IEM templates. Person is **not** an authentication record. | Separates production personnel data from login; aligns with PH045 Band People schema. |
| 164 | **User** is the authentication identity — portal login credentials and access state. User **must link to Person** but must **not** store travel, passport, banking, instrument, or IEM data. | Clear data boundary; prevents credential leakage into personnel exports. |
| 165 | **Username/password login** is approved for Band Portal. Login identifier is `username`. Email remains profile/contact data on Person unless a future decision approves email login. | Operator preference; staged login UX (PH046.01A) aligns with username-first flow. |
| 166 | Passwords are **hashed** with Laravel password hashing (`Hash` facade / `bcrypt`/`argon`) — **not encrypted**. No custom reversible password encryption. `APP_KEY` is not a password storage strategy. Passwords must never be decrypted or displayed. | Industry standard; prevents recoverable credential storage. |
| 167 | Sensitive Person data (passport number, bank account, Air New Zealand points) remains in **`person_secure_fields`** with application encryption. Dedicated environment encryption keys may be considered for secure fields only — not login passwords. | PH045 encryption model preserved; auth and PII encryption concerns separated. |
| 168 | **Invitation flow** is the approved account-creation path: Admin creates/selects Person → sends time-limited invitation → invitee opens token link → invitee creates username and password → User created and linked to Person → progressive Person onboarding. Self-registration without invitation is not approved. | Controlled onboarding; Person exists before portal access. |
| 169 | **Login details must never be stored on Person** — no username, password, hash, or invitation token on `people` or Person child tables. | Enforces Person/User separation at persistence layer. |
| 170 | **Forgot password** remains unavailable in UX until the staged login implementation reaches an approved phase. PH046.01A scaffold may show the affordance; it must stay non-functional until then. | Avoids half-implemented recovery paths. |
| 171 | Invitation tokens must be cryptographically random, time-limited, single-use, and revocable. Store token hash at rest — not plaintext in logs. | Standard secure invitation practice. |
| 172 | Proposed future tables (document only): `users` (with `username`, `person_id`), optional `person_user_links` if many-to-many later approved, `person_invitations`, optional `onboarding_progress`, `password_reset_tokens` (Laravel conventions). | Migration planning without premature implementation. |
| 173 | **Band Portal deployment:** operator should not manually deploy `band.edandtheshadowboys.com` except emergency recovery or explicit infrastructure intervention. Agent/process must trigger or verify Forge deployment via `./server/deploy/remote-deploy.sh` after push. | Reduces manual deploy drift; automation is default path. |
| 174 | Decision **071** (User ≠ Musician) remains valid. PH047 adds **User ≠ Person** with explicit User→Person link for Band Portal. Musician↔User and Person↔Musician mappings remain separate follow-up concerns. | No charter conflict; extends identity governance. |
| 175 | `/server/` Laravel skeleton `users` table (email-based) is **provisional** until M1 migration reconciles PH047 `username` + `person_id` model on shared PostgreSQL. | Documents gap between scaffold and governed schema. |

### PH047 — Relationship to prior decisions

| Prior | PH047 position |
|-------|----------------|
| PH007 / 071 User vs Musician | Unchanged — orthogonal linkage |
| PH045 Band People schema | Reinforced — no auth columns on `people` |
| PH008 / FOUNDATION M1 Breeze email auth | Band Portal uses username login per 165; Director local app may retain Breeze email baseline until aligned |
| PH046.01A landing scaffold | Staged login UX only — no credential enforcement yet |

---

## PH047A — Authentication Policy Finalisation

| ID | Decision | Rationale |
|----|----------|-----------|
| 176 | **Username Policy:** Username is the canonical Band Portal authentication identifier. Email is contact information only. Length 3–32. Characters `a-z`, `A-Z`, `0-9` only. Disallowed: spaces, hyphens, underscores, dots, email addresses, symbols, punctuation. Usernames are case-insensitive; stored lowercase; unique indexed with case-insensitive uniqueness. | Removes ambiguity before PH048; aligns with PH046.01A username-first login scaffold. |
| 177 | **Password Policy:** Length 8–50. Requires uppercase, lowercase, number, and symbol. Hashed only via Laravel `Hash` (Argon2id / Laravel defaults). Never encrypted, reversible, displayed, or recoverable. Custom password encryption prohibited. `APP_KEY` is not a password encryption strategy. | Finalises PH047 password rules for implementation validators and persistence. |

### PH047A — Governance clarification

| Identity | Role |
|----------|------|
| **User** | Authentication identity — username, password hash, access state |
| **Person** | Human/profile identity — contact, travel, passport, banking, instruments, files, IEM |

Login credentials must never be stored on Person. Passport, banking, travel, onboarding, instruments, files, and IEM data must never be stored on User.

### PH047A — Validation (no conflicts)

| Prior | PH047A position |
|-------|-----------------|
| **PH045** Band People schema | No conflict — `people` has no auth columns; username/password belong on `users` only |
| **PH046** PHP 8.4 runtime | No conflict — runtime baseline unrelated to credential policy |
| **PH046.01A** landing scaffold | No conflict — staged username→password UX matches Decision 176; validation enforced at PH048 |
| **PH047** Person/User separation | Reinforced — 176/177 apply to User only; Person email remains contact data |
| **Laravel auth foundations** | No conflict — Laravel `Hash`, session auth, and validation rules align; skeleton `users.email` reconciled at M1 per 175 |

**PH048 Authentication Implementation may proceed** under Decisions 163–177.

---

## PH048A — ESB Band Portal Narrative Onboarding Experience Governance

| ID | Decision | Rationale |
|----|----------|-----------|
| 178 | **Narrative Onboarding:** The ESB Band Portal onboarding experience is a guided narrative journey — not a traditional registration form. Users must feel welcomed, curious, excited, valued, and invited into something special. Artistic, immersive, cinematic, memorable. Progressive storytelling; one field at a time; avoid large forms, corporate language, and generic "sign up" patterns. | Establishes experience-first initiation before implementation; differentiates ESB Band Portal from enterprise SaaS onboarding. |
| 179 | **Chapter-Based Onboarding Structure:** Onboarding is divided into eight chapters accessed via `/invite/{token}`: (1) Welcome to the Shadows, (2) Claim Your Identity, (3) Your True Name, (4) Choose Your Persona, (5) Choose Your Weapon, (6) Find Your Way Home, (7) The Road Ahead, (8) Enter the Studio. Each chapter uses cinematic transitions, alpha fades, and progressive reveals. | Storyboard structure for PH048A scaffold and future implementation. |
| 180 | **ESB Studio As Post-Onboarding Destination:** ESB Studio is the onboarding arrival surface, member home page, future login destination, and primary authenticated portal landing page. Not a separate domain entity — a UX destination surfacing Person-linked member content. | Replaces generic "dashboard" framing; gives members a named creative home. |
| 181 | **Email Verification Required For Studio Access:** `bookings@edandtheshadowboys.com` sends verification behind the scenes. Status: Email Pending Verification. If incomplete after 24 hours, user may authenticate but cannot access ESB Studio — show Verify Your Email with resend, instructions, and support. Verification required before Studio access. Email remains Person contact data (PH047) — not login identifier. | Balances early onboarding completion with contact verification before full portal access. |

### PH048A — Invitation architecture (documented)

| Requirement | Statement |
|-------------|-----------|
| Linked to Person | `person_id` FK |
| Expiry | `expiry_date` / expires_at |
| Single use | `accepted_at` on completion |
| Revocable | Administrator revocation |
| Provenance | `created_by` tracked |
| Status lifecycle | Draft → Sent → Accepted / Expired / Revoked |
| Management UI | Planned; implementation later |

### PH048A — Out of scope

Authentication implementation, User creation, invitation validation, database writes, password storage, email sending, session handling, profile persistence.

PH048A establishes the **experience scaffold** only.

### PH048A — Validation (no conflicts)

| Prior | PH048A position |
|-------|-----------------|
| **PH045** Band People schema | No conflict — Chapters 3–6 map to existing Person / `instrument_reference` / `person_instruments` fields |
| **PH046 / PH046.01A** landing scaffold | No conflict — public landing remains for returning members; invite flow uses `/invite/{token}` narrative journey |
| **PH047** Person/User separation | Reinforced — Ch 2 → User; Ch 3–6 → Person |
| **PH047A** username/password rules | Reinforced — Ch 2 enforces Decisions 176–177 in progressive UX |
| **Charter** Live Show View Priority #1 | No conflict — Band Portal is cloud personnel surface; ESB Studio ≠ Live Show View |
| **Laravel foundations** | No conflict — PH048A is documentation/scaffold only |

---

## PH049 — ESB Studio Musician Evaluation Prohibition

| ID | Decision | Rationale |
|----|----------|-----------|
| 178A | **Musician Evaluation Prohibition:** The ESB Studio is a facilitation and preparation platform. The Studio shall not calculate, display, infer, score, rank, evaluate, grade, review, or estimate: musician readiness; performance readiness; rehearsal readiness; musician quality; musician engagement; musician productivity; musician participation scores; practice scores; performance ratings; or league tables / rankings. The Studio may present factual information including: songs assigned to a performance; charts available; charts updated; notes added; running plans; rehearsal materials; upcoming performances; administrative requirements; and messages and activity. Interpretation of whether a musician is ready for rehearsal or performance is exclusively a human responsibility — musical director, band leadership, rehearsal process, and professional judgement. The platform exists to facilitate preparation, collaboration, communication, and access to resources. The platform shall not attempt to measure artistic capability, artistic readiness, or artistic performance. | Prevents gamification, surveillance, or algorithmic judgement of musicians in the Band Portal; preserves human artistic authority in rehearsal and performance decisions. |

### PH049 — Prohibited in ESB Studio

| Category | Prohibited |
|----------|------------|
| Readiness inference | Musician, performance, or rehearsal readiness scores or indicators |
| Quality judgement | Musician quality, engagement, or productivity ratings |
| Participation metrics | Participation scores, practice scores, performance ratings |
| Comparative ranking | League tables, leaderboards, rankings |

### PH049 — Permitted factual presentation

| Category | Examples |
|----------|----------|
| Assignment facts | Songs assigned to a performance |
| Chart facts | Charts available; charts updated |
| Collaboration | Notes added; messages and activity |
| Planning | Running plans; rehearsal materials |
| Calendar | Upcoming performances |
| Administration | Administrative requirements |

### PH049 — Human interpretation boundary

Whether a musician is ready for rehearsal or performance is determined by musical director, band leadership, rehearsal process, and professional judgement — not by ESB Studio.

### PH049 — Validation (no conflicts)

| Prior | PH049 position |
|-------|-----------------|
| **Decision 180** ESB Studio destination | Reinforced — Studio is a facilitation and preparation surface, not an evaluation engine |
| **Decisions 035, 047, 048** collaborative Readiness | No conflict — Soundcheck/Readiness is a collaborative, human-gated process on the Performance execution layer (Director/Soundcheck), not automated musician scoring in ESB Studio |
| **Decisions 021, 022** Readiness gate | No conflict — operational Performance readiness ≠ Studio musician evaluation |
| **Charter** Live Show View Priority #1 | No conflict — ESB Studio is Band Portal cloud surface; Live Show View remains separate |
| **PH048A** narrative onboarding | No conflict — onboarding captures identity; Studio presents factual preparation content only |

---

End of Decision Log — PH049

---

## PH054 — Cloud Studio ↔ Live Stage Synchronisation Model

| ID | Decision | Rationale |
|----|----------|-----------|
| 182 | **Cloud Studio and Live Stage are peer authoring environments.** Song, chart, metadata, brief, and related asset edits require checkout before modification. Synchronisation is version-aware (base version, current cloud version, current Live Stage version), initiated by Live Stage, produces diffs, and resolves conflicts through operator decision — no last-write-wins. Live Stage must operate offline (~50% connectivity assumption); no critical rehearsal or performance workflow may depend on cloud connectivity. Neither environment is permanent overwrite authority. | Prevents silent overwrites between cloud and local authoring; preserves offline-first rehearsal and performance while enabling safe bidirectional song management. |

### PH054 — Key principles

| Principle | Requirement |
|-----------|-------------|
| **Offline-capable Live Stage** | Live Stage operates without internet; connectivity not guaranteed during rehearsal or performance |
| **Explicit synchronisation** | Sync is a deliberate operator-initiated process — not background silent merge |
| **Checkout-aware editing** | Songs and related assets must be checked out before edit; checkout records environment, user, timestamp, version |
| **Version-based conflict detection** | Compare base, cloud, and Live Stage versions — timestamp alone is insufficient |
| **Operator-controlled conflict resolution** | Diff presented; operator chooses resolution; field-level review where practical |
| **No last-write-wins** | Automatic overwrite of concurrent edits is prohibited |

### PH054 — Terminology

| Term | Definition |
|------|------------|
| **Cloud Studio** | Server environment — musician portal, cloud-hosted collaboration, song/chart/performance management |
| **Live Stage** | Local performance environment — rehearsal/performance runtime, offline-capable |

Use these terms consistently. Do not introduce alternative environment names.

### PH054 — Formal ADR

Full decision record: `docs/adr/ADR-001-cloud-studio-live-stage-synchronisation.md`

### PH054 — Validation (no conflicts)

| Prior | PH054 position |
|-------|----------------|
| **Decisions 022, 051, 053** (cloud canonical) | Amended for **song asset authoring** — peer checkout/version model; publish and sync-before-show for operational deployment unchanged |
| **Decision 178A** (musician evaluation prohibition) | No conflict — sync/conflict review is operator preparation workflow, not musician scoring |
| **Charter** offline / show must go on | Reinforced — Live Stage offline-first |
| **Ableton runtime authority** | No conflict — timeline authority unchanged |
| **PH053 song metadata** | Compatible — metadata fields subject to checkout/version sync when implemented |

---

## PH055 — Governance Recovery and Architecture Alignment

| ID | Decision | Rationale |
|----|----------|-----------|
| 183 | **ESB data architecture:** one logical data architecture; **two physical PostgreSQL databases** (Cloud Database, Live Stage Database); **three workspaces** (Cloud Studio, Website, Live Stage). Cloud Studio and Website use Cloud Database. Live Stage uses Live Stage Database. | Resolves PH007 Decision 084 drift — three deployment contexts were incorrectly implemented as permission to co-locate unrelated apps on one production database; clarifies Website as first-class workspace. |
| 184 | **Schema parity** is mandatory between Cloud Database and Live Stage Database for all shared ESB entity tables. Offline operation creates **data-state divergence** (row values, versions, checkout state), **not schema divergence** (missing tables, divergent columns, workspace forks). Live Stage Database may add runtime-only superset tables. | Preserves sync-before-show and PH054 checkout model; prevents production schema collision across workspaces. |
| 185 | **Production co-tenancy rule:** Cloud Studio and Website may share Cloud Database. **Live Stage must not share a physical database instance with Cloud Database.** Multiple Laravel apps on Cloud Database require governed migration namespace ownership per app. | Direct response to production incident — `/server/` and unrelated Forge sites must not apply `/backend/` migrations to Cloud Database. |
| 186 | **Production safety (mandatory):** No ad hoc production DDL; no manual `INSERT` into `migrations`; no marking migrations as run manually; no feature work during production data-integrity investigations; no production mutation without operator-approved incident procedure. | Codified in `AGENTS.md`; closes governance gap exposed when incident response used SSH tinker and manual migration table edits. |
| 187 | **Person-first invitation remains authoritative.** PH047 Decision 168 and PH048A invitation architecture (`person_id` FK, Person exists before User creation) are **not amended**. The implemented `invite_links` table (shared links without `person_id`, Person created at registration completion) is **non-compliant provisional drift**. **Implementation must change** to governed `person_invitations` model before production onboarding resumes. PH047 is not amended. | Resolves invite model conflict in favour of identity governance; documents drift without retroactive approval of shared invite links. |
| 188 | **PH048A scope clarification:** PH048A governs narrative onboarding UX scaffold. Persistence implementation (User creation, Person updates, invitation validation, database writes) is **PH048B** — blocked until PH055 infrastructure recovery (database isolation, migration reconciliation) is operator-approved. | Resolves PH048A "scaffold only" vs implemented registration conflict without retroactively approving out-of-scope writes. |

### PH055 — Original authority located

| Concept | Original source | PH055 reconciliation |
|---------|-----------------|----------------------|
| Cloud Database | PH005 Decision 067; `DATABASE_ARCHITECTURE.md` §8; `PHYSICAL_DATABASE` §4 | Cloud Studio + Website workspaces |
| Live Stage Database | PH005 Decision 068; `DATABASE_ARCHITECTURE.md` §9; `PHYSICAL_DATABASE` §5 | Director + Local Show Runtime consolidated |
| Cloud Studio | PH054 Decision 182; ADR-001 | `/server/` Band Portal |
| Website | `DATA_ARCHITECTURE.md` Band People rows ("Director Local / Website"); production Forge topology | Public cloud app; Cloud Database co-tenant |
| Live Stage | PH054 Decision 182; ADR-001 mapping note | `/backend/` + Local Show Runtime |
| Two physical DBs | **Not explicitly stated before PH055** — implied by cloud vs local authority split (PH004–PH007) but obscured by PH007 Decision 084 "three databases" wording | PH055 makes two physical databases explicit |
| Three workspaces | **Not explicitly stated before PH055** — PH054 named two authoring environments; Website was implicit in DATA_ARCHITECTURE | PH055 names Website as third workspace |

### PH055 — Production incident record (governance only)

Read-only investigation (2026-06-24) identified: shared `defaultdb` across Band Portal and unrelated Forge site; 66 migrations from multiple codebases; manual production DDL and `migrations` table inserts during incident response; empty `users`/`people` tables with historical sequence values. **No further production mutation** until operator selects recovery path (PITR, isolated Cloud Database provisioning, or forensic hold).

### PH055 — Validation

| Prior | PH055 position |
|-------|----------------|
| PH007 Decision 084 | **Amended interpretation** — two physical DBs, three workspaces; authority phases unchanged |
| PH047 Decision 168 | **Reinforced** — implementation must align; PH047 not amended |
| PH048A | **Clarified** — UX scaffold only; persistence is PH048B |
| PH054 / ADR-001 | **Compatible** — peer authoring and sync model unchanged |
| Charter offline-first | **Reinforced** — Live Stage Database isolation |

---

## PH056 — Production Recovery Planning

| ID | Decision | Rationale |
|----|----------|-----------|
| 189 | **Production recovery requires an operator-selected path** before any mutation: Path A (PITR to isolated Cloud Database), Path B (fresh isolated Cloud Database — **recommended default**), or Path C (forensic hold). Full procedure: `docs/PH056_PRODUCTION_RECOVERY_PLAN.md`. | PH055 blocked production work without recovery plan; manual incident response made `defaultdb` migration history untrustworthy. |
| 190 | **Forensic export is mandatory** before any recovery-path execution: DO snapshot or `pg_dump`, `migrations` export, row-count audit, Forge `DB_*` documentation. | Preserves evidence; enables rollback analysis; satisfies PH007 backup governance. |
| 191 | **Fresh isolated Cloud Database (Path B)** is the recommended default when portal `users`/`people` are empty and manual migration edits occurred — governed `server/` migrations only on empty cluster. | Faster trustworthy schema than reconciling 66 contaminated migrations; aligns with PH055 Decision 185. |
| 192 | **Deploy and migrate are gated** until PH056 incident closure: no `remote-deploy.sh`, no `forge-deploy.sh` migrate step on production, until Cloud Database target is isolated and operator signs verification checklist (PH056 §9). | `forge-deploy.sh` runs `migrate --force`; deploying to contaminated DB would worsen drift. |
| 193 | **Website database co-tenancy** with Cloud Studio remains permitted per PH055 Decision 185 but requires operator sign-off and declared migration ownership before repointing `edandtheshadows` Forge site. | Co-tenancy is allowed; blind repointing without audit is not. |
| 194 | **Old shared `defaultdb`** must remain forensic read-only or be decommissioned only after export — never dropped without operator approval. | May contain Website data and pre-incident evidence. |
| 195 | **PH048B remains blocked** until PH056 incident closure recorded with operator sign-off on verification checklist. | Onboarding persistence must not resume on non-compliant schema or invite model. |

### PH056 — Incident status

| Field | Value |
|-------|-------|
| **Status** | Open — planning complete; execution **not authorised** |
| **Forensic summary** | PH056 §2 / PH055 incident record |
| **Recovery plan** | `docs/PH056_PRODUCTION_RECOVERY_PLAN.md` |
| **Operator decisions pending** | Recovery path; Website co-tenancy; identity loss acceptance |

### PH056 — Validation

| Prior | PH056 position |
|-------|----------------|
| PH055 Decision 185 (DB isolation) | Operationalised via recovery paths and tenancy matrix |
| PH055 Decision 186 (production safety) | Reinforced — forensic export before any mutation |
| PH047 Decision 173 (automated deploy) | Suspended until incident closure — deploy is not default during recovery |
| PH055 Decision 187 (person-first invite) | PH048B blocked until recovery complete |

---

## PH058 — Cloud-First Canonical Schema Stabilisation

| ID | Decision | Rationale |
|----|----------|-----------|
| 196 | **Cloud Database is canonical schema authority** for all shared ESB entities. Shared-entity DDL is authored for Cloud first and applied identically to Live Stage. | Resolves PH057 parity failure; one migration-defined structure enables backup, restore, and rebuild. |
| 197 | **Cloud-first canonical schema is a reliability decision** — not a hierarchy of workspace importance. Cloud owns durable system-of-record: backup, restore, replication, reference data, long-term history, rebuild source. Live Stage owns operational continuity: rehearsal, performance, offline operation, console/Ableton runtime execution, pending local changes until synchronised. | Preserves Charter offline-first and Live Show View priority while making Cloud the predictable rebuild anchor. |
| 198 | **Cloud can rebuild Live Stage; Live Stage can operate without Cloud.** Schema parity is mandatory for shared entities; data-state divergence when offline is permitted — schema divergence is not. | PH055 schema parity rule operationalised; sync-before-show enables offline performance. |
| 199 | **`server/` duplicate shared-entity migrations are deprecated.** Live Stage definitions for missing Cloud tables are merged into Cloud Canonical Migration Manifest (CCMM). `invite_links` remains quarantined — `person_invitations` per PH055-187. | Ends parallel migration forks; PH058 plan: `docs/PH058_CLOUD_FIRST_SCHEMA_STABILISATION_PLAN.md`. |
| 200 | **PH056 recovery and data migration remain blocked** until CCMM is published and operator approves. No production mutation until PH056 incident closure. | Schema stabilisation precedes recovery execution. |

### PH058 — Validation

| Prior | PH058 position |
|-------|----------------|
| PH054 peer authoring | Compatible — schema canonicality ≠ overwrite authority |
| PH055 schema parity | Reinforced — Cloud-first DDL, identical Live Stage structure |
| PH055-187 person-first invite | Unchanged — `invite_links` quarantined |
| Charter offline / show must go on | Reinforced — Live Stage operates without Cloud |
| PH057 backend-owned DDL | Amended — Cloud-first canonical authority |

### PH058 — Status

| Field | Value |
|-------|-------|
| **Status** | Planning complete — implementation **not authorised** |
| **Plan** | `docs/PH058_CLOUD_FIRST_SCHEMA_STABILISATION_PLAN.md` |
| **Next** | Publish CCMM; PH056 recovery on stabilised schema |

---

## PH059 — Cloud Canonical Migration Manifest (CCMM)

| ID | Decision | Rationale |
|----|----------|-----------|
| 201 | **CCMM accepted as shared schema authority.** `docs/PH059_CLOUD_CANONICAL_MIGRATION_MANIFEST.md` defines 35 shared ESB tables both Cloud Database and Live Stage Database must implement identically before recovery migrations are written. | Formalises PH058; single reference for PH060 migration authoring. |
| 202 | **Cloud Database canonical schema reliability role** reinforced: backup, restore, replication, audit, rebuild source — not workspace hierarchy. | PH058 framing codified in manifest. |
| 203 | **Live Stage schema parity requirement:** Live Stage applies CCMM groups CCMM-1–13 identically, then Live Stage superset (Part B) only. | PH055 schema parity operationalised. |
| 204 | **`server/` duplicate shared migrations deprecated** — listed in CCMM Part D; must not be used for recovery or new environments. | Ends parallel forks from PH057/PH058. |
| 205 | **Runtime-only Live Stage tables excluded from Cloud** — Part B lists runtime, soundcheck, readiness, X32 effects, integration, `performance_device_assignments`. | Cloud rebuilds shared entities; runtime state remains local. |
| 206 | **`invite_links` / `invite_link_acceptances` quarantined;** canonical future model is `person_invitations` with `person_id` (PH047). Not in shared CCMM. | PH055-187 enforced in manifest. |
| 207 | **`venues` and `festivals` classified as shared canonical entities** (CCMM A34–A35). | Tour collaboration on Cloud; operator may defer seeding only. |
| 208 | **PH056 recovery unblocked for planning next phase only** — operator must approve CCMM before migration files (PH060) or production execution. | CCMM publication satisfies PH058-200 gate for migration authoring. |

### PH059 — Specific resolutions

| Topic | Resolution |
|-------|------------|
| **users** | Unified merge: `public_id`, `username`, `person_id`, `band_id`, `is_active`, nullable `name`/`email`, case-insensitive unique `username` |
| **people** | PH045 + `bio`, `profile_photo_path`, `profile_photo_display_path` |
| **instrument_reference** | `slug` canonical NOT NULL unique |
| **songs** | `song_code`, `status` (not `lifecycle_state`), full metadata + authoring columns |
| **charts** | Full file metadata + `import_batch_id`; parent `import_batches` required on Cloud |
| **performance_device_assignments** | Live Stage superset only (FK to `integration_devices`) |

### PH059 — Status

| Field | Value |
|-------|-------|
| **Status** | CCMM published — migration implementation **not authorised** (PH060) |
| **Manifest** | `docs/PH059_CLOUD_CANONICAL_MIGRATION_MANIFEST.md` |
| **Next** | Operator CCMM approval; PH060 governed migration files |

---

## PH060 — CCMM Implementation Gap Analysis

| ID | Decision | Rationale |
|----|----------|-----------|
| 209 | **CCMM gap-analysis is the implementation planning authority** before PH061 migration files. Comparison target: `server/` Cloud implementation vs PH059 CCMM. Document: `docs/PH060_CCMM_IMPLEMENTATION_GAP_ANALYSIS.md`. | Makes explicit what must change; no implementation until gaps closed. |
| 210 | **Cloud completion process:** fresh isolated Cloud Database (PH056 Path B) → apply PH061 CCMM migrations in dependency order (PH060 §9) → verify → then data migration. Contaminated `defaultdb` is not a migration target. | Production forensic DB cannot be reconciled in place. |
| 211 | **Migration retirement approach:** all `server/` duplicate shared-entity migrations (PH059 Part D) are **archived, not extended**. Shared entities move to Cloud-first CCMM migration package (PH061). `backend/` duplicates of same entities are also retired from Cloud path. | Ends fork that caused PH057 collision. |
| 212 | **Quarantine handling:** `invite_links` and `invite_link_acceptances` remain on forensic production only; **excluded** from fresh Cloud migrate. Replace with `person_invitations` in PH048B/PH061 C18. No data migration from quarantined tables. | PH055-187 / PH059 Part C. |
| 213 | **PH061 blocked until operator approves PH060 findings.** Current Cloud code readiness: **NOT READY** — 20 MISSING, 1 DRIFTED, 5 PARTIAL shared entities vs CCMM. | Quantified gate before migration authoring. |

### PH060 — Gap summary

| Metric | Count |
|--------|-------|
| CCMM shared entities | 35 |
| ALIGNED | 9 |
| PARTIAL | 5 |
| DRIFTED | 1 (`users`) |
| MISSING | 20 |
| QUARANTINED | 2 |

### PH060 — Status

| Field | Value |
|-------|-------|
| **Status** | Gap analysis complete — PH061 **not authorised** until operator approval |
| **Document** | `docs/PH060_CCMM_IMPLEMENTATION_GAP_ANALYSIS.md` |
| **Next** | Operator sign-off → PH061 CCMM migration implementation planning |

---

## PH061 — Cloud Recovery Execution Plan

| ID | Decision | Rationale |
|----|----------|-----------|
| 214 | **PH061 is the execution plan authority** for Cloud recovery. Defines build sequence (F0–F6), data/file migration, verification, rollback, and Live Stage realignment. Document: `docs/PH061_CLOUD_RECOVERY_EXECUTION_PLAN.md`. | Operator approved PH060; Path B assumed; no execution in PH061. |
| 215 | **Migration package strategy:** eleven CCMM packages (CCMM-00 through CCMM-11) replace deprecated `server/`/`backend/` duplicate shared migrations. Historical migration files **preserved in Git for audit only** — not run on fresh Cloud. | PH060-211 operationalised; single Cloud-first path. |
| 216 | **Verification authority:** Gates 1–6 (forensic export → operator sign-off → schema → data/files → application → incident closure) are mandatory before each phase advance. §8 acceptance criteria are binding. | Production Safety Rules; no skip. |
| 217 | **Rollback requirements:** per-phase rollback in PH061 §9; pre-F4 Cloud snapshot; `cloud_recovery_entity_map` for data batch rollback; Live Stage never mutated during Cloud recovery; no manual migrations table edits. | Recoverable recovery; forensic evidence preserved. |
| 218 | **PH062 authorised for migration file authoring only** after operator Gate 2 sign-off on PH061. PH062 does not execute production recovery. | Separates plan from implementation. |
| 219 | **Live Stage realignment** (PH061 §11) follows Cloud Gate 4 pass — CCMM parity apply, superset migrations, governed pull, PH054 sync engine deferred. | Schema before sync; offline-first preserved. |

### PH061 — Approved operator assumptions (planning)

| Assumption | Status |
|------------|--------|
| PH056 Path B fresh Cloud Database | Assumed for plan |
| Cloud-first canonical authority | Active |
| CCMM schema authority | PH059 |
| Duplicate migration ownership retired | PH060-211 |
| invite_links quarantined | PH060-212 |

### PH061 — Status

| Field | Value |
|-------|-------|
| **Status** | Execution plan complete — **production execution not authorised** |
| **Document** | `docs/PH061_CLOUD_RECOVERY_EXECUTION_PLAN.md` |
| **Next** | PH062 authoring plan → PH062-impl migration files → Operator Gate 2 |

---

## PH061A — X32 Console Domain Discovery and Classification

| ID | Decision | Rationale |
|----|----------|-----------|
| 220 | **X32 console domain classified** at business level in `docs/PH061A_X32_CONSOLE_DOMAIN_DISCOVERY.md`. Configuration channels/buses/routing remain **JSON inside `show_console_baselines`** — not normalized CCMM tables. | PH043 learn model; avoids 32×16 relational explosion |
| 221 | **CCMM expansion (Track B):** `effect_definitions`, `effect_packages`, `effect_package_items`, `song_effect_assignments`, effects reference catalogue, and `show_console_baselines` become CCMM shared entities (proposed CCMM-12a–12b). | Must survive backup/restore for show prep and song FX intent |
| 222 | **Live Stage only:** `console_learning_snapshots`, `integration_devices`, `integration_connection_profiles`, `performance_device_assignments`, all `live_*` OSC state, X32 scene/snippet recall operations. | Runtime continuity; PH059 Part B reinforced |
| 223 | **Runtime-only exclusions** confirmed: fader/meter/mute/connection/heartbeat/transport live state must not become Cloud entities. | Cloud rebuilds prep assets not desk telemetry |
| 224 | **Music `snippets` (PH027) ≠ X32 console snippets.** CCMM `snippets` table remains music domain; X32 recall snippets stay operational/fallback fields only. | Prevents domain collision |
| 225 | **PH061 core recovery (Track A) not blocked** by PH061A; CCMM-12 X32 domain is **Track B** after core Gate 4 or parallel planning. `mix_moves` CCMM-12c blocked until M5 schema exists. | Sequencing risk control |

### PH061A — CCMM additions summary

| Package | Entities |
|---------|----------|
| CCMM-12a | effect_* tables + song_effect_assignments |
| CCMM-12b | show_console_baselines |
| CCMM-12c | mix_moves (future) |

### PH061A — Status

| Field | Value |
|-------|-------|
| **Status** | Domain classification complete — no implementation |
| **Document** | `docs/PH061A_X32_CONSOLE_DOMAIN_DISCOVERY.md` |
| **Next** | Operator decisions §8; PH062 CCMM-12 authoring when Track B approved |

---

## PH062 — CCMM Migration Authoring Plan

| ID | Decision | Rationale |
|----|----------|-----------|
| 226 | **PH062 is the migration authoring blueprint.** CCMM (PH059) is the **sole schema authority** — no migration folder, application code, or production DB is authoritative for shared entities. Document: `docs/PH062_CCMM_MIGRATION_AUTHORING_PLAN.md`. | Ends PH060 duplicate-fork authority conflict |
| 227 | **Canonical DDL path:** repo-root `database/migrations/ccmm/` (PH063). Historical `server/` and `backend/` shared forks → **Retired Ownership** archived, not deleted. | Single migration authority chain |
| 228 | **Package authority:** CCMM-00–12 + RECOVERY + LS-EXT. CCMM-12 unified X32 console package (effects + baselines + `mix_moves` placeholder). PH061A supersedes PH059 Part B for effect/baseline tables. | Consolidates Track B into CCMM-12 |
| 229 | **PH062 is blueprint only** — no migration PHP files, production DDL, or data migration in this phase. PH063 authors implementation files. | Production safety |
| 230 | **Retirement strategy:** retire ownership not history; classify every `server/` and `backend/` migration per retirement matrix §4. | Forensic audit preserved |
| 231 | **Naming standard:** `{timestamp}_ccmm{NN}_{slug}.php`. Every CCMM migration **must** declare **CCMM Package**, **Decision Reference**, and **PH Reference** in file header (PH062 §8.4). | Prevents future drift; traceable governance chain |
| 235 | **Mandatory CCMM migration declarations** are non-negotiable: `CCMM Package`, `Decision Reference` (`DECISION_LOG {id}`), `PH Reference` (e.g. `PH059 A12`). Enforced in `AGENTS.md` and `.cursor/rules/ccmm-migrations.mdc`. | Every migration traceable to authority |
| 232 | **CCMM change process:** Proposal → governance → CCMM update → PH062 amend → PH063 migration → Cloud → Live Stage parity. No direct migration without CCMM change. | Governed schema evolution |
| 233 | **Cloud build order:** B0–B10 core → B12 X32 → BR recovery → B11 invitations. JSON baseline strategy for channel/bus/routing inside `show_console_baselines`. | PH061A document model |
| 234 | **PH063 readiness: Ready with Conditions** — blueprint complete; blocked on Gate 2, migration file authoring, `effect_library_*` decision, `mix_moves` M5 schema. | Honest handoff gate |

### PH062 — Ownership summary

| Ownership | Count (approx.) |
|-----------|-----------------|
| Shared CCMM (incl. CCMM-12) | 46 tables + JSON document fields |
| Cloud Extension | Laravel infra, `person_invitations`, `cloud_recovery_entity_map` |
| Live Stage Extension | integration, learning snapshots, soundchecks, permissions |
| Runtime Only | `runtime_*`, `live_*` |
| Quarantined | `invite_links`, `invite_link_acceptances` |

### PH062 — Status

| Field | Value |
|-------|-------|
| **Status** | Blueprint complete — **no migration files authored** |
| **Document** | `docs/PH062_CCMM_MIGRATION_AUTHORING_PLAN.md` |
| **Next** | Operator decisions §11 → PH063 migration file implementation |

---

End of Decision Log — PH062

---

## PH063 — CCMM Migration Package Authoring

| ID | Decision | Rationale |
|----|----------|-----------|
| 236 | **CCMM migration PHP files authored** at repo-root `database/migrations/ccmm/` and `database/migrations/recovery/` per PH062 blueprint. **Not executed** against any production database. | PH063 scope: files only |
| 237 | **22 migration files** cover CCMM-00–12 + RECOVERY. CCMM-12 includes merged `effects` catalogue (no `effect_library_*`). `mix_moves` placeholder only. | Operator decisions applied |
| 238 | **Migration order:** B12 (`001200–001220`) before B11 (`001300`); RECOVERY at `001250`. | Operator B12-before-B11 |
| 239 | **`show_console_baselines.source_snapshot_id`** nullable without FK — `console_learning_snapshots` remains LS-EXT. | PH061A boundary |
| 240 | **PH063 execution blocked** until PH061 Gate 2 + isolated Cloud cluster. Files ready for local fresh-migrate validation. | Production safety |

### PH063 — Status

| Field | Value |
|-------|-------|
| **Status** | Migration files authored — **not executed** |
| **Path** | `database/migrations/ccmm/`, `database/migrations/recovery/` |
| **Next** | Wire loader in server/backend; local fresh-migrate test; PH061 Gate 2 for production |

---

End of Decision Log — PH063

---

## PH064 — CCMM Migration Loader and Local PostgreSQL Validation

| ID | Decision | Rationale |
|----|----------|-----------|
| 241 | **CCMM loader wired** in `server/` and `backend/` via `database/ccmm_migration_paths.php` + `loadMigrationsFrom()`. | Governed single path; no duplication |
| 242 | **11 server fork migrations archived** to `_archived_ccmm_forks/` — not deleted. `0001_*` retained; `users` DDL removed from `0001` (CCMM-04 owns users). | PH062 retirement strategy |
| 243 | **Local PostgreSQL validation PASS** on isolated `esb_ccmm_validation` (Docker `backend-postgres-1`). 48/48 CCMM tables; 0 forbidden; 0 FK orphans. | PH064 gate before production |
| 244 | **`ccmm:validate-schema` command** added for repeatable local/CI schema checks. | PH062 §8.5 drift prevention |
| 245 | **Production migrate remains blocked** — PH061 Gate 2 required. | Production safety |

### PH064 — Status

| Field | Value |
|-------|-------|
| **Status** | Loader wired; local validation PASS |
| **Report** | `docs/PH064_CCMM_LOCAL_VALIDATION_REPORT.md` |
| **Next** | PH063-runbook / PH061 Gate 2 for Cloud cluster |

---

End of Decision Log — PH064

---

## PH065 — Cloud Recovery Runbook and Gate 2 Sign-off Package

| ID | Decision | Rationale |
|----|----------|-----------|
| 246 | **PH065 operational runbook authored** — R0–R10 phases with checklists, gates, rollback. Master: `docs/PH065_CLOUD_RECOVERY_RUNBOOK.md`. | Operator step-by-step manual before any Cloud execution |
| 247 | **Gate 2 sign-off package** requires explicit operator signature, recovery window, rollback window. `docs/PH065_GATE2_SIGNOFF_PACKAGE.md`. | Production execution blocked until signed |
| 248 | **Eight execution checklists** + rollback runbook + gate summary created under `docs/PH065_*`. | Completeness for Path B recovery |
| 249 | **R5/R6 blocked on PH066** import/upload tooling — runbook documents placeholders; empty Cloud start valid after R4. | Honest execution boundary |
| 250 | **Gate 3 criteria updated** to PH064 authority: **48 CCMM tables** (not legacy 35-only count). | Aligns with CCMM-12 inclusion |
| 251 | **PH065 produces documentation only** — no production commands, migrate, deploy, or data migration in this phase. | Production safety |

### PH065 — Document index

| Document | Purpose |
|----------|---------|
| `PH065_CLOUD_RECOVERY_RUNBOOK.md` | Master R0–R10 |
| `PH065_GATE2_SIGNOFF_PACKAGE.md` | Operator approval |
| `PH065_FORENSIC_EXPORT_CHECKLIST.md` | Gate 1 |
| `PH065_CLOUD_PROVISIONING_CHECKLIST.md` | R2 |
| `PH065_MIGRATION_EXECUTION_CHECKLIST.md` | R3 |
| `PH065_DATA_MIGRATION_CHECKLIST.md` | R5 |
| `PH065_FILE_MIGRATION_CHECKLIST.md` | R6 |
| `PH065_APPLICATION_VALIDATION_CHECKLIST.md` | Gate 5 |
| `PH065_ROLLBACK_RUNBOOK.md` | Rollback paths |
| `PH065_GATE_SUMMARY.md` | Gates 1–6 |

### PH065 — Status

| Field | Value |
|-------|-------|
| **Status** | Runbook package complete — **execution blocked pending Gate 2** |
| **Next** | Operator signs Gate 2 → R1 forensic export → PH066 import tooling (optional before R5) |

---

## PH066 — Data Migration and Verification Tooling Plan

| ID | Decision | Rationale |
|----|----------|-----------|
| 252 | **PH066 defines recovery data/file tooling architecture** — export, transform, import, verify, rollback via `recovery:*` Artisan commands. Document: `docs/PH066_DATA_MIGRATION_AND_VERIFICATION_TOOLING_PLAN.md`. | Unblocks PH065 Gate 4 design |
| 253 | **Identity strategy:** preserve `public_id`; remap bigint FKs via `cloud_recovery_entity_map`; Live Stage never mutated. | PH061 §5.1 |
| 254 | **21-domain import order** defined incl. effects (CCMM-12) and `show_console_baselines`; runtime/integration excluded. | PH061A supersedes PH061 omit list for effects |
| 255 | **Machine-readable reports** — export/import/file/verification/rollback JSON schemas v1. | Audit and Gate 4 evidence |
| 256 | **Mandatory dry-run** before any write; two consecutive dry-run PASS per phase. | Production safety |
| 257 | **PH067 = implementation + local dry-run rehearsal** — not production Cloud execution. | Sequencing |
| 258 | **PH067A implements local-only `recovery:*` tooling** in `/server/` — dry-run default, production host/env guards, batch workspace under `storage/recovery/{batch_id}/`. No Spaces, no production DB, no destructive rollback execution. | PH066 §1, §8 |
| 259 | **PH067A write execution gated** by `RECOVERY_LOCAL_ACKNOWLEDGED=true` plus non-production host allowlist; `--execute` on upload/rollback blocked in PH067A. | Safety |
| 260 | **Report contracts** include `version: 1` and `schema: esb.recovery.*/v1` per PH066 with PH067A filenames (`entity_map.json`, `file_manifest.json`). | Audit |

### PH066 — Status

| Field | Value |
|-------|-------|
| **Status** | Tooling plan complete — PH067A implements command framework |
| **Document** | `docs/PH066_DATA_MIGRATION_AND_VERIFICATION_TOOLING_PLAN.md` |
| **Next** | PH067B local dry-run rehearsal |

---

## PH067A — Recovery Tooling Implementation (Local Framework)

| Field | Value |
|-------|-------|
| **Status** | Complete — local recovery tooling framework in `/server/` |
| **Scope** | 8 `recovery:*` Artisan commands, batch storage, production guards, unit/feature tests |
| **Out of scope** | Production DB, Spaces uploads, destructive rollback, Cloud R5/R6 execution |
| **Next** | PH067B local dry-run against `esb_dev` → `esb_recovery_validation` |

---

## PH067B — Local Recovery Rehearsal

| ID | Decision | Rationale |
|----|----------|-----------|
| 261 | **PH067B rehearsal executed** — `esb_dev` → `esb_recovery_validation` on local Docker PostgreSQL. Batch `a0168483-1e88-4c6b-bb34-8e6b5a3314bb`. | Gate 4 evidence |
| 262 | **Rehearsal import path enabled** via `RECOVERY_REHEARSAL_MODE=true` + `RecoveryImportExecutor` (local only). | PH067B execute requirement |
| 263 | **Gate 4 rehearsal: CONDITIONAL FAIL** — tooling pipeline PASS; row parity and file migration FAIL. | Documented in `PH067B_LOCAL_RECOVERY_REHEARSAL_REPORT.md` |
| 264 | **Root causes identified (no PH067B fixes):** (A) circular bands↔musicians FK ordering, (B) `esb_dev` legacy effect columns vs CCMM-12, (C) chart file paths missing locally. | PH068 queued |

### PH067B — Status

| Field | Value |
|-------|-------|
| **Status** | Rehearsal complete — Gate 4 not eligible |
| **Document** | `docs/PH067B_LOCAL_RECOVERY_REHEARSAL_REPORT.md` |
| **Next** | PH068 recovery import hardening |

---

## PH068 — Recovery Defect Resolution Plan

| ID | Decision | Rationale |
|----|----------|-----------|
| 265 | **PH068 plans correction for PH067B defects RD-01–RD-06** — no implementation, migrations, or production execution. | Governed sequencing before PH069/PH067C |
| 266 | **Deferred FK three-pass import** adopted for `bands.primary_director_musician_id` ↔ `musicians.band_id`. | Mirrors CCMM-01/CCMM-04 intent |
| 267 | **Effect recovery uses transform-layer mapping** from `effect_library_*` to CCMM-12 `effects` / `effect_parameters` — no CCMM schema change. | PH061A merge decision |
| 268 | **File resolution requires configurable `RECOVERY_*_ROOT` paths** and missing-file classification before PH067C. | PH067B 262/262 missing |
| 269 | **Next sequence: PH069 tooling corrections → PH067C second rehearsal** — Cloud R5/R6 remains blocked. | Gate 4 + Gate 2 |

### PH068 — Status

| Field | Value |
|-------|-------|
| **Status** | Plan complete — no tooling changes in PH068 |
| **Document** | `docs/PH068_RECOVERY_DEFECT_RESOLUTION_PLAN.md` |
| **Next** | PH069 recovery tooling corrections |

---

## PH069 — Recovery Tooling Corrections

| ID | Decision | Rationale |
|----|----------|-----------|
| 270 | **PH069 implements RD-01–RD-06 corrections** in `/server/` recovery services — no CCMM or production changes. | PH068 plan |
| 271 | **Deferred FK three-pass import** via `RecoveryDeferredForeignKeyService` + `deferred_fk.json` / `deferred_fk_report.json`. | RD-01 |
| 272 | **Effect transform at recovery layer** — drop `effect_library_*` columns; map via x32 algorithm keys. | RD-03 / PH061A |
| 273 | **File resolution** via `RECOVERY_*_ROOT` env vars and `missing_files_report.json` classification. | RD-04 |
| 274 | **Verification report v2** with `deferred_fk`, `effect_transform`, `file_resolution`, `gate4_readiness`. | RD-06 |
| 275 | **Next: PH067C** second local rehearsal before Cloud R5/R6. | Gate 4 |

### PH069 — Status

| Field | Value |
|-------|-------|
| **Status** | Tooling corrections complete — 23 Recovery tests PASS |
| **Document** | `docs/PH069_RECOVERY_TOOLING_CORRECTIONS_REPORT.md` |
| **Next** | PH067C second local recovery rehearsal |

---

## PH067C — Second Local Recovery Rehearsal

| ID | Decision | Rationale |
|----|----------|-----------|
| 276 | **PH067C second rehearsal PASS for data recovery** — batch `62d2bef1-f1d2-4dd3-89b3-a2513c6a3094`; all domain row counts match; 0 import errors. | PH069 corrections validated |
| 277 | **Deferred FK replay complete** — 1/1 band director FK applied; RD-01 closed. | G4-13 PASS |
| 278 | **No band cascade failure** — `dependency_block_report.bands_blocked=false`; RD-02 closed. | G4-2/G4-3 PASS |
| 279 | **Effect domain 520/520 imported** with 0 column drift errors; RD-03 closed. | G4-14 PASS |
| 280 | **Gate 4 rehearsal: CONDITIONAL PASS** — file phase blocked on 258 `required_missing` charts (correctly classified); not tooling failure. | RD-04 operator content gap |
| 281 | **Cloud R5/R6 remains blocked** pending Gate 2 + chart file sync to local root. | PH065 |

### PH067C — Status

| Field | Value |
|-------|-------|
| **Status** | Rehearsal complete — CONDITIONAL PASS |
| **Document** | `docs/PH067C_SECOND_LOCAL_RECOVERY_REHEARSAL_REPORT.md` |
| **Next** | Operator Gate 2 + chart library sync before Cloud recovery |

---

End of Decision Log — PH067C
