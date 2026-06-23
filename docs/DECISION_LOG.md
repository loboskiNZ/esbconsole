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
| 084 | Three distinct PostgreSQL databases: cloud (DO managed), Director local, Local Show Runtime (Docker). | Phase-aware authority; runtime DB required during performance; cloud not required during performance. |
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

End of Decision Log — PH048A
