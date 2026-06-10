# Database Architecture & Logical Schema Design

Status: PH007 Finalised  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: Canonical database architecture and logical schema design before physical database implementation

Related documents:

- Data ownership and persistence rules: `docs/DATA_ARCHITECTURE.md`
- Physical database and migration plan: `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`
- Entity definitions: `docs/DOMAIN_MODEL.md`
- Runtime behaviour: `docs/RUNTIME_MODEL.md`
- Infrastructure: `docs/ARCHITECTURE.md`

This document defines **logical domains**, **aggregate boundaries**, **relationship models**, and **cloud/local database responsibilities** — not physical table names, column types, migrations, or ORM implementation.

**Out of scope for PH005:** migrations, Eloquent models, seeders, API routes, frontend, runtime code changes.

---

## 1. Purpose

PH005 establishes the canonical database architecture for the Live Performance Orchestration System.

Goals:

- Translate PH004 data authority into logical database domains and aggregates
- Define cloud vs local runtime database boundaries
- Specify the Published Show Package as the sync unit
- Govern file asset persistence (Spaces + local cache; not Git)
- Define lifecycle, audit, deletion, and migration principles
- Provide the schema-design gate before physical implementation

Database design **must follow** `docs/DATA_ARCHITECTURE.md`. Physical implementation **must follow** this document, `docs/DATA_ARCHITECTURE.md`, and `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`.

---

## 2. Database Architecture Principles

| # | Principle |
|---|-----------|
| 1 | **Follow data architecture.** Logical schema reflects PH004 ownership, sync, and lifecycle rules. |
| 2 | **Cloud stores canonical collaboration/preparation data.** Published master library and operational records live in the cloud database after publish. |
| 3 | **Local runtime database supports offline show execution.** Local Show Runtime holds a performance-ready replica plus runtime state. |
| 4 | **Live performance must not depend on cloud database availability.** All required records and file cache must exist locally before Soundcheck. |
| 5 | **Runtime cue state is local-runtime-authoritative during performance.** PGM/CC16 and timeline-derived state are not cloud-canonical while live. |
| 6 | **One canonical owner per reusable asset.** Songs, Charts, Mix Moves, Light Modes, etc. are referenced by ID — not duplicated per Show. |
| 7 | **Shows and Performances reference assets.** Playlist, Assignments, and Actions use foreign references to canonical entities. |
| 8 | **Production assets are not Git assets.** Charts, uploads, resources, and songs on disk are runtime/managed assets — not version-controlled in Git. |
| 9 | **File records store managed object references and metadata.** Canonical identity is Spaces object reference + checksum — not ad hoc local paths. |
| 10 | **All future schema changes use migrations.** No ad hoc DDL; no manual production schema edits outside migration workflow. |
| 11 | **Phase-aware authority.** Draft, published, synced, live, and archived states determine read/write authority per environment. |
| 12 | **The show must go on.** Schema design must not introduce performance-blocking cloud dependencies. |

---

## 3. Persistence Boundaries

Three logical database contexts align with PH004 persistence environments:

| Context | Database role | Authority phase |
|---------|---------------|-----------------|
| **Director Local Database** | Draft creation, local editing, publish staging | Director-local-canonical until publish |
| **Cloud Database** | Published canonical records, collaboration, sync package registry, audit | Cloud-canonical after publish |
| **Local Show Runtime Database** | Performance-ready replica, runtime state, Soundcheck/Readiness, execution logs | Local-runtime-authoritative during show day |

### Boundary rules

| Rule | Statement |
|------|-----------|
| **Publish boundary** | Draft records in Director Local are not visible in cloud until publish transaction completes. |
| **Pull boundary** | Local Show Runtime receives data only via explicit Published Package pull — not live cloud queries during performance. |
| **Live boundary** | Performance in `live` state blocks inbound cloud sync to show-critical tables. |
| **Runtime boundary** | Ableton Protocol State and live Runtime State exist only in Local Show Runtime — not cloud-canonical during performance. |
| **File boundary** | Database stores file metadata and object references; binary content lives in Spaces (remote) or local cache (runtime). |

Director Local and Cloud may share the same logical schema with different data visibility; Local Show Runtime uses a subset schema optimised for show-day execution plus runtime tables.

---

## 4. Logical Data Domains

Each domain defines a bounded area of persistence responsibility.

### Identity Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Authentication identities, roles, permissions, and User–Musician linkage. |
| **Entities** | User, role/permission records, session references, User↔Musician link |
| **Primary relationships** | User may link to zero or one Musician; Users hold roles (Director, Musician, Tech, Administrator) |
| **Cloud** | Authoritative for Users, roles, credentials (Laravel auth) |
| **Local runtime** | Auth cache/session only; no canonical User creation at show time |
| **Notes** | User ≠ Musician. See §13. |

### Band / Organisation Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Top-level scope for all master library and operational data. |
| **Entities** | Band |
| **Primary relationships** | Band owns Songs, Shows, Musicians, Devices, Instrument Parts, Mix Moves, Light Modes, Production Configurations |
| **Cloud** | Canonical after publish |
| **Local runtime** | Cached Active Band context |
| **Notes** | Rarely changes; all queries scoped by Band |

### Music Library Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Reusable musical content and cue structure. |
| **Entities** | Song, Cue, Chart, Snippet, Action (authored definitions) |
| **Primary relationships** | Song owns Cues; Song references Charts; Cue owns Actions; Chart contains Snippets; Actions reference Mix Moves, Light Modes, instructions |
| **Cloud** | Canonical after publish |
| **Local runtime** | Cached subset required by Active Show playlist |
| **Notes** | Song is primary aggregate root for music/cue/chart structure |

### Production Asset Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Reusable production definitions not tied to a single Song. |
| **Entities** | Mix Move, Light Mode, Production Configuration, Instrument Part, Capability |
| **Primary relationships** | Instrument Part referenced by Capability and Assignment; Mix Move/Light Mode referenced by Actions; Production Configuration referenced by Show |
| **Cloud** | Canonical after publish |
| **Local runtime** | Cached referenced assets only |
| **Notes** | Production Configuration is aggregate for wiring templates |

### Show Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Rehearsed production variant definition. |
| **Entities** | Show, Playlist (ordered Song references), Ableton Show File reference, Stage Plot reference, Tech Rider reference |
| **Primary relationships** | Show belongs to Band; requires one Ableton Show File; references Songs via Playlist; references Production Configuration |
| **Cloud** | Canonical after publish |
| **Local runtime** | Active Show cached with Performance |
| **Notes** | Show without Ableton Show File reference is invalid |

### Performance Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Executable occurrence of a Show at venue/date. |
| **Entities** | Performance, Availability (musician presence), venue/date metadata |
| **Primary relationships** | Performance references one Show; owns Assignments, Monitor Assignments, Soundcheck, Readiness |
| **Cloud** | Hybrid — published preparation data; local authoritative during show day |
| **Local runtime** | Active Performance is execution root |
| **Notes** | Operator runs Performance, not Show |

### Assignment Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Operational Musician ↔ Instrument Part mapping. |
| **Entities** | Assignment, Monitor Assignment |
| **Primary relationships** | Assignment belongs to Performance; scoped by Song and/or Cue; Monitor Assignment binds monitor routing per Performance |
| **Cloud** | Published copies canonical |
| **Local runtime** | Cached; locked at performance start |
| **Notes** | Distinct from Capability (eligibility) and Availability (presence) |

### Soundcheck / Readiness Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Pre-performance validation and gate state. |
| **Entities** | Soundcheck, Readiness (aggregate dimensions: system, production, musician) |
| **Primary relationships** | Both belong to Performance; Readiness follows Soundcheck |
| **Cloud** | Optional post-performance history |
| **Local runtime** | Authoritative during show day |
| **Notes** | Warnings do not necessarily block performance |

### Runtime State Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Ephemeral and derived execution state. |
| **Entities** | Runtime State, Ableton Protocol State (PGM/CC16 mapping), Connection State, Action execution log entries |
| **Primary relationships** | Scoped to Active Performance; timeline state derived from Ableton input |
| **Cloud** | Not canonical during live performance |
| **Local runtime** | Authoritative |
| **Notes** | See §11 |

### Sync Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Replication metadata and package tracking. |
| **Entities** | Sync State, Published Package / Sync Package records, publish/sync audit entries |
| **Primary relationships** | Package links Show + Performance + asset manifest; Sync State tracks pull/push outcomes |
| **Cloud** | Package registry canonical |
| **Local runtime** | Local Sync State + pulled package version |
| **Notes** | Sync State is observability — not show content |

### File Asset Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Managed file metadata and object references. |
| **Entities** | File Asset records (Chart files, Tech Rider, Stage Plot, Ableton Show File, uploads) |
| **Primary relationships** | Linked to owning entity (Song, Chart, Show, etc.); manifest entries in Published Package |
| **Cloud** | Metadata canonical; binary in Spaces |
| **Local runtime** | Metadata + local cache path; checksum verification |
| **Notes** | See §12. Not Git-tracked. |

### Audit / History Domain

| Attribute | Value |
|-----------|-------|
| **Purpose** | Observable trail of publish, sync, assignment, readiness, and runtime events. |
| **Entities** | Audit log entries, history snapshots, conflict records |
| **Primary relationships** | Linked to entity, Performance, Package, or User as applicable |
| **Cloud** | Long-term retention |
| **Local runtime** | Show-day logs minimum until post-sync |
| **Notes** | See §14 |

---

## 5. Entity-to-Domain Mapping

| Entity | Logical Domain | Aggregate root | Cloud | Local runtime |
|--------|----------------|----------------|-------|---------------|
| **Band** | Band / Organisation | Band | ✅ canonical | ✅ cached |
| **User** | Identity | — (flat) | ✅ canonical | auth cache |
| **Musician** | Band / Organisation | Musician* | ✅ canonical | ✅ cached |
| **Device** | Band / Organisation | Musician* | ✅ hybrid | ✅ cached |
| **Instrument Part** | Production Asset | Band (catalog) | ✅ canonical | ✅ cached |
| **Capability** | Production Asset | Musician* | ✅ canonical | ✅ cached |
| **Song** | Music Library | Song | ✅ canonical | ✅ cached |
| **Chart** | Music Library | Song | ✅ canonical | ✅ cached + file |
| **Snippet** | Music Library | Song / Chart | ✅ canonical | ✅ cached |
| **Cue** | Music Library | Song | ✅ canonical | ✅ cached |
| **Action** | Music Library | Cue | ✅ canonical | ✅ cached |
| **Mix Move** | Production Asset | Band (catalog) | ✅ canonical | ✅ cached |
| **Light Mode** | Production Asset | Band (catalog) | ✅ canonical | ✅ cached |
| **Production Configuration** | Production Asset | Production Configuration | ✅ canonical | ✅ cached |
| **Show** | Show | Show | ✅ canonical | ✅ cached |
| **Performance** | Performance | Performance | ✅ hybrid | ✅ authoritative (show day) |
| **Assignment** | Assignment | Performance | ✅ hybrid | ✅ cached |
| **Stage Plot** | Show (informational) | Show | ✅ canonical | ✅ cached + file |
| **Tech Rider** | Show (informational) | Show | ✅ canonical | ✅ cached + file |
| **Soundcheck** | Soundcheck / Readiness | Performance | optional post-sync | ✅ authoritative |
| **Readiness** | Soundcheck / Readiness | Performance | optional post-sync | ✅ authoritative |
| **Monitor Assignment** | Assignment | Performance | ✅ hybrid | ✅ cached |
| **Ableton Protocol State** | Runtime State | Runtime State | ❌ live | ✅ authoritative |
| **Runtime State** | Runtime State | Runtime State | ❌ live | ✅ authoritative |
| **Sync State** | Sync | Published Package | ✅ hybrid | ✅ local copy |

\*Musician aggregate includes Devices and Capabilities as child collections.

---

## 6. Aggregate Boundaries

Aggregates define consistency boundaries — changes within an aggregate are atomic; cross-aggregate references use IDs only.

### Band (master library container)

- **Root:** Band
- **Contains (by reference or collection):** catalog pointers to Songs, Shows, Musicians, Instrument Parts, Mix Moves, Light Modes, Production Configurations
- **Invariant:** All child entities scoped to one Band; no cross-Band references
- **Publish unit:** Individual entities or batch publish — not whole Band at once

### Song (music / cue / chart aggregate)

- **Root:** Song
- **Contains:** Cues (ordered), Chart references, Snippet definitions linked to Cues
- **References:** Actions on Cues reference Mix Moves, Light Modes (by ID)
- **Invariant:** Cue order stable within Song; Cue 0 defined as preparation cue
- **Does not contain:** Assignment or Performance data

### Show (Ableton + playlist + production configuration aggregate)

- **Root:** Show
- **Contains:** Playlist (ordered Song IDs), Ableton Show File reference, Production Configuration reference, optional Stage Plot / Tech Rider references
- **Invariant:** Exactly one Ableton Show File reference required; Playlist imported from Ableton — not authored independently in platform
- **References:** Songs, Production Configuration — does not embed Song content

### Performance (executable occurrence aggregate)

- **Root:** Performance
- **Contains:** Availability list, Assignments, Monitor Assignments, Soundcheck, Readiness
- **References:** one Show (required)
- **Invariant:** Assignments reference Musicians available for this Performance; locked during `live` state
- **Lifecycle gate:** Soundcheck → Readiness → live execution

### Musician (identity / person / device / preference aggregate)

- **Root:** Musician
- **Contains:** Devices, Capabilities (Musician ↔ Instrument Part eligibility)
- **References:** User link (optional)
- **Invariant:** Device belongs to one Musician; Capability declares eligibility only — not operational assignment

### Production Configuration (reusable production setup aggregate)

- **Root:** Production Configuration
- **Contains:** Wiring/routing template definitions for a production variant
- **Referenced by:** Show
- **Invariant:** Reusable across Shows; not Performance-specific

### Runtime State (local execution aggregate)

- **Root:** Runtime State (per Active Performance session)
- **Contains:** Timeline context (Current/Previous/Next Song/Cue), Connection State, Action execution log buffer, Ableton Protocol State snapshot
- **Invariant:** Timeline updates only from Ableton signals during performance; not mutated by cloud sync
- **Lifetime:** Created at Soundcheck/sync; archived post-performance optionally

---

## 7. Relationship Model

### Band relationships

```
Band
├── owns → Musicians, Devices (via Musician), Instrument Parts
├── owns → Songs, Mix Moves, Light Modes, Production Configurations
└── owns → Shows → referenced by Performances
```

### Song relationships

```
Song
├── owns → Cues (ordered; Cue 0 = preparation)
├── references → Charts
├── contains → Snippets (portions of Charts linked to Cues)
└── Cue
    └── owns → Actions
        ├── references → Mix Move
        ├── references → Light Mode
        ├── may include → musician instructions
        ├── may include → chart navigation behaviour
        └── may include → MIDI/OSC or Ableton fallback/source behaviour
```

### Show relationships

```
Show
├── requires → one Ableton Show File (File Asset reference)
├── contains → Playlist (ordered Song references)
├── references → Production Configuration
├── optional → Stage Plot, Tech Rider (File Asset references)
└── referenced by → Performances
```

### Performance relationships

```
Performance
├── references → one Show
├── owns → Availability (Musician presence)
├── owns → Assignments (Musician ↔ Instrument Part; scoped by Song/Cue)
├── owns → Monitor Assignments
├── owns → Soundcheck
└── owns → Readiness
```

### Assignment relationships

```
Assignment
├── belongs to → Performance
├── maps → Musician ↔ Instrument Part
├── scoped by → Song (optional), Cue (optional)
├── informed by → Capability
└── constrained by → Availability
```

### Device and Capability

```
Device → belongs to → Musician
Capability → belongs to → Musician; references → Instrument Part
```

### Action execution references

Actions at Cue boundaries may reference:

| Reference type | Purpose |
|----------------|---------|
| Mix Move | X32 grouped parameter changes |
| Light Mode | Lighting state activation |
| Musician instructions | Role-specific guidance (including Cue 0) |
| Chart navigation | Snippet/display behaviour |
| MIDI/OSC | External integration triggers |
| Ableton fallback/source | Protocol behaviour when platform follows Ableton |

---

## 8. Cloud Database Model

The cloud database (PostgreSQL or MySQL on DigitalOcean, Laravel-managed) stores:

### Canonical master library (post-publish)

- Band
- Musicians, Devices, Capabilities
- Instrument Parts
- Songs, Cues, Actions
- Charts metadata, Snippets metadata
- Mix Moves, Light Modes
- Production Configurations

### Operational / collaboration records

- Shows, Playlists
- Performances (preparation and scheduled state)
- Assignments, Monitor Assignments
- Stage Plot and Tech Rider metadata

### Identity and access

- Users, roles, permissions
- User↔Musician links
- Session/token metadata (Laravel auth)

### File and sync infrastructure

- File asset metadata (object key, checksum, size, type, owning entity)
- Published Package / Sync Package registry
- Sync State records
- Publish audit trail

### Audit and history

- Publish events
- Sync package creation and pull records
- Assignment change history (published versions)
- Chart/snippet change history
- Conflict records

### Not stored as live-canonical in cloud during performance

- Live PGM/CC16 values
- In-progress Runtime State
- Real-time device session state
- In-progress Action execution state

Post-performance archival of runtime history is optional.

---

## 9. Local Runtime Database Model

Local Show Runtime database must contain **before performance begins** (via Published Package pull):

### Active context

- Active Band
- Active Show
- Active Performance

### Show execution data

- Playlist (ordered Song references)
- Songs (required subset)
- Cues per Song
- Actions per Cue
- Assignments (all scopes)
- Monitor Assignments
- Mix Moves and Light Modes referenced by Actions
- Charts/Snippets metadata

### File readiness

- Required file asset manifest
- Local cached asset paths (implementation detail)
- Checksum verification status per file

### Runtime tables (populated at show day)

- Runtime State (timeline context, connections)
- Ableton Protocol State (live input mapping)
- Readiness State (aggregate dimensions)
- Soundcheck State
- Connection State
- Runtime logs (action failures, cue transitions, connection events)

### Authority during `live` Performance state

Local runtime database is authoritative for runtime, Soundcheck, Readiness, and execution logs. Inbound cloud sync to show-critical tables is **blocked**.

---

## 10. Sync Package Model

A **Published Show Package** is the atomic unit pulled from cloud to Local Show Runtime.

### Package contents

| Component | Description |
|-----------|-------------|
| Show metadata | Show ID, version, lifecycle state |
| Ableton Show File reference | Managed object reference + checksum |
| Playlist | Ordered Song IDs with PGM mapping metadata |
| Required Songs | Full Song records for playlist |
| Required Cues | Cue definitions per Song |
| Required Actions | Action definitions per Cue |
| Required Assignments | All Assignment records for Performance |
| Required Monitor Assignments | Monitor routing for Performance |
| Required Mix Moves | Referenced production assets |
| Required Light Modes | Referenced production assets |
| Required Charts/Snippets | Metadata + file manifest entries |
| File asset manifest | All required files with object references and checksums |
| Version/hash/checksum metadata | Package integrity verification |
| Sync status | pulled / stale / conflict |

### Package lifecycle states

Aligns with `docs/DATA_ARCHITECTURE.md` §14:

`draft` → `published` → `pulled` → (optional) `stale` | `conflict`

### Pull validation

Before marking Performance as `synced`:

- All referenced entity IDs resolve
- All file manifest entries cached locally with matching checksum
- Ableton Show File reference present and valid
- No unresolved conflicts

Failure surfaces at Soundcheck — operator may re-pull.

---

## 11. Runtime State Persistence

### Locality rule

Runtime cue state is **local-runtime-authoritative during performance**. Cloud does not store live timeline authority.

### Persisted runtime concepts (Local Show Runtime)

| Concept | Persistence | Notes |
|---------|-------------|-------|
| **Active context** | Band, Show, Performance IDs | Set at sync/Soundcheck |
| **Timeline state** | Current/Previous/Next Song/Cue | Updated from Ableton PGM/CC16 only |
| **Cue 0 / Preparation state** | Flag when CC16 = 0 | Display and preparation actions |
| **Connection State** | Ableton, X32, lighting, device links | System readiness input |
| **Action execution log** | Success/failure per Action at Cue entry | Failures do not halt show |
| **Readiness snapshot** | At performance start | Optional post-sync to cloud |

### Ephemeral vs persisted

| Data | Persisted locally | Synced to cloud post-show |
|------|-------------------|---------------------------|
| Live PGM/CC16 stream | Session + optional log | Optional archive |
| Action failures | Yes (log table) | Optional |
| Cue transitions | Yes (log table) | Optional |
| Operator notes | Yes | Optional |

### Ableton Protocol State

- **Not** a canonical authored entity — runtime input only
- Mapped to canonical Song/Cue records via PGM/CC16 rules
- Stored in runtime session tables — never cloud-canonical during live performance

---

## 12. File Asset Persistence

### Production asset folders (not Git-tracked)

These are **managed production/runtime asset folders** and **must not be Git-tracked**:

| Folder | Purpose |
|--------|---------|
| `/resources/` | Ableton project files, logos, sample media, production resources |
| `/songs/` | Runtime song-related assets |
| `/charts/` | Chart PDFs and snippet images |
| `/uploads/` | User-uploaded chart and document binaries |

Git stores **code, docs, schema, and tiny approved static UI assets only** (e.g. `client/public/manuals/`).

### Approved storage model

| Layer | Technology | Role |
|-------|------------|------|
| **Remote canonical** | DigitalOcean Spaces | Published file binaries |
| **Database** | Cloud + local metadata tables | Object reference, checksum, size, type, owning entity, version |
| **Local runtime cache** | Local filesystem or local object cache | Offline access; path is implementation detail |

### File record requirements

Each File Asset record stores:

- Unique identifier
- Asset type (chart, tech_rider, stage_plot, ableton_show_file, upload, export)
- Owning entity reference (Song, Chart, Show, etc.)
- Spaces bucket/key (canonical object reference)
- Checksum (hash)
- Size, mime type, version
- Lifecycle state

**Does not store:** ad hoc local-only path as canonical identity.

### Soundcheck / Readiness detection

- Required file manifest compared against local cache at Soundcheck
- Missing or checksum-mismatch files surface as production readiness warnings
- Performance does not fetch from cloud — missing files must be resolved by re-pull or manual cache before show

---

## 13. Identity and Access Persistence

### User vs Musician

| Concept | Persistence |
|---------|-------------|
| **User** | Laravel auth identity — credentials, roles, permissions |
| **Musician** | Domain person record — global, reusable |

Related but distinct:

- Musician may link to User (device login)
- Not every User is a Musician (Director, Tech, Administrator)
- Musician self-service edits limited to permitted fields

### Role persistence

| Role | Database authority |
|------|-------------------|
| **Director** | Full production structure read/write; publish authority |
| **Musician** | Read assigned Performance data; edit permitted self-service only |
| **Tech** | Read production/system; edit Console/Lights/Monitor as authorised |
| **Administrator** | User/Band admin; not live performance operation |

### Show-critical changes

Assignment, Song, Show, Action, Mix Move, Light Mode changes require Director authority and publish workflow — not Musician self-service.

---

## 14. Audit and History Persistence

| Event | Minimum audit fields | Primary store |
|-------|---------------------|---------------|
| **Show publish** | who, when, version, validation result | Cloud |
| **Sync package creation** | package ID, version, checksum, creator | Cloud |
| **Sync pull** | direction, timestamp, outcome, target Performance | Cloud + local |
| **Assignment changes** | before/after, scope, publisher | Cloud (versioned) |
| **Chart/snippet changes** | entity ID, version, publisher | Cloud |
| **Readiness changes** | dimension, state, timestamp | Local (authoritative); optional cloud post-show |
| **Runtime action failures** | Action ID, Cue, timestamp, error | Local; optional cloud post-show |
| **Soundcheck events** | milestone, participant, timestamp | Local; optional cloud post-show |
| **Performance completion** | start/end, final readiness snapshot | Local; optional cloud post-show |
| **File asset availability** | manifest entry, cache status, checksum result | Local at Soundcheck; cloud on publish |

Audit supports rollback analysis — not real-time performance control.

---

## 15. Lifecycle State Persistence

Lifecycle states are persisted on entity records. Authority to transition depends on environment and role.

| Entity | States | Primary store |
|--------|--------|---------------|
| **Song** | `draft`, `in_progress`, `complete`, `archived` | Cloud (canonical) |
| **Chart** | `draft`, `complete`, `archived` | Cloud |
| **Show** | `draft`, `published`, `archived` | Cloud |
| **Performance** | `draft`, `scheduled`, `synced`, `soundcheck`, `live`, `completed`, `archived` | Hybrid |
| **Assignment** | `draft`, `published`, `locked` | Hybrid |
| **Soundcheck** | `not_started`, `in_progress`, `complete` | Local runtime (show day) |
| **Readiness** | `not_ready`, `warning`, `ready` | Local runtime (show day) |
| **Sync Package** | `draft`, `published`, `pulled`, `stale`, `conflict` | Cloud registry + local copy |
| **File Asset** | `draft`, `uploaded`, `published`, `cached`, `missing`, `archived` | Cloud metadata; local cache status on runtime |

Performance in `live` state triggers Assignment `locked` and blocks inbound cloud sync.

---

## 16. Deletion and Archive Rules

| Rule | Statement |
|------|-----------|
| **Prefer archive over delete** | Master library entities use `archived` state — not hard delete — when retired. |
| **Reference protection** | Cannot archive Song referenced by published Show playlist without operator override/warning. |
| **Cascade policy** | Deleting/archive of parent aggregate requires dependency check (Show → Performance, Song → Cue/Action). |
| **Performance history** | Completed Performances archived — not deleted — for audit trail. |
| **File assets** | Archive metadata in cloud; local cache may be purged post-performance per retention policy. |
| **Runtime logs** | Retained locally until post-sync; cloud retention configurable. |
| **User deletion** | Soft-delete Users; preserve audit trail; unlink Musician without deleting Musician record. |
| **No silent purge** | Bulk deletion of show-critical data requires Director authority and audit entry. |

Hard delete permitted only for: draft entities never published, failed upload stubs, and explicit administrator cleanup with audit.

---

## 17. Schema Design Constraints

| Constraint | Requirement |
|------------|-------------|
| **Normalisation** | Master library normalised; Performances reference by ID. |
| **No embedded duplicates** | Song content not copied into Show or Performance tables. |
| **Band scoping** | All tenant data scoped by Band ID. |
| **Publish versioning** | Published entities carry version or publish timestamp for sync comparison. |
| **Package integrity** | Published Package includes aggregate checksum for pull verification. |
| **Performance lock** | Schema supports `live` state flag blocking inbound sync. |
| **File indirection** | Binary content never inline in entity tables — File Asset reference only. |
| **Runtime isolation** | Runtime State tables separate from canonical master tables. |
| **Auth separation** | User/auth tables separate from Musician domain tables with explicit link. |
| **Index strategy** | Performance pull queries optimised for package assembly (deferred to implementation). |

No physical table or column names are finalised in PH005 — logical design only.

---

## 18. Future Migration Principles

| Principle | Statement |
|-------------|-----------|
| **Migrations only** | All schema changes via versioned migration files — Laravel migrations approved. |
| **No ad hoc DDL** | No manual production schema edits outside migration workflow. |
| **Backward-compatible pulls** | Schema changes must not break existing Published Packages without version bump. |
| **Dual-environment coordination** | Cloud and Local Show Runtime migrations may diverge in subset tables but share core entity schema. |
| **Data migration scripts** | Separate from schema migrations when transforming published data. |
| **Rollback plan** | Every migration documents rollback strategy in completion report. |
| **Governance gate** | Migrations must align with `docs/DATABASE_ARCHITECTURE.md` and `docs/DATA_ARCHITECTURE.md`. |
| **Review before apply** | Show-critical schema changes reviewed against domain and runtime models. |

---

## 19. Open Questions / Deferred Decisions

| ID | Question | Deferred to |
|----|----------|-------------|
| OQ-001 | Physical table naming convention (singular vs plural; prefix strategy) | PH005.01 or implementation kickoff |
| OQ-002 | PostgreSQL vs MySQL final selection | Infrastructure decision (both approved in ARCHITECTURE) |
| OQ-003 | Director Local DB: separate instance vs cloud replica with draft flag | Implementation architecture |
| OQ-004 | Musician without User account — guest/sub policy | Product policy / PH006 |
| OQ-005 | Post-performance runtime log retention duration | Operations policy |
| OQ-006 | Published Package binary format (SQL dump vs JSON manifest vs hybrid) | Implementation |
| OQ-007 | Monitor Assignment detailed schema (bus routing vs send levels) | Console integration phase |
| OQ-008 | Soft-delete vs archive-only for Assignments mid-tour | Product policy |
| OQ-009 | Multi-Band User access (User spans Bands) | Future scope |
| OQ-010 | Realtime sync transport (WebSockets vs polling) first implementation | ARCHITECTURE approved polling first |

None of these block PH005 logical design completion.

---

## 20. Glossary

| Term | Definition |
|------|------------|
| **Aggregate** | Consistency boundary grouping entities under one root. |
| **Cloud database** | PostgreSQL/MySQL canonical store on DigitalOcean. |
| **File Asset record** | Database row storing metadata and Spaces object reference for a managed file. |
| **Local runtime database** | Docker-local database holding performance-ready replica and runtime state. |
| **Logical domain** | Bounded persistence area (e.g. Music Library Domain). |
| **Migration** | Versioned schema change script — only approved schema change mechanism. |
| **Monitor Assignment** | Operational monitor routing binding for a Performance. |
| **Published Show Package** | Validated sync unit pulled to Local Show Runtime before performance. |
| **Runtime State aggregate** | Local-only execution state root for Active Performance session. |
| **Schema** | Logical structure of tables/relationships — physical implementation follows PH005+. |

---

End of Database Architecture — PH007
