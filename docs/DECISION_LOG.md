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
| 019 | Snippet is a Chart portion associated with a Cue. | Separates chart navigation content from Cue boundary events. |
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

---

End of Decision Log — PH008
