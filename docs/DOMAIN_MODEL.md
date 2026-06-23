# Domain Model

Status: PH047 Amended (Band Portal Authentication & Canonical Identity)  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: Canonical entity definitions for the Live Performance Orchestration System

## Entity Hierarchy

```
Band
├── Users (authentication — Band Portal)
├── Musicians
├── People (Production Personnel)
├── Devices
├── Instrument Parts
├── Songs
│   ├── SongInstrumentParts
│   │   ├── Chart (one per SongInstrumentPart)
│   │   └── Snippets (per Cue)
│   ├── Cues
│   └── Actions
├── Light Modes
├── Mix Moves
├── Production Configurations
├── Shows
│   ├── Ableton Show File
│   ├── Playlist
│   └── Production Configuration
└── Performances
    ├── Show
    ├── Venue
    ├── Date
    ├── Available Musicians
    ├── Assignments
    └── Soundcheck
```

## Master Library vs Operational Layer

| Layer | Entities |
|-------|----------|
| Master Library (global, reusable) | Band, User, Musician, Person, Device, Instrument Part, Instrument Reference, Capability, Song, SongInstrumentPart, Chart, Snippet, Cue, Action, Mix Move, Light Mode, Production Configuration, Stage Plot, Tech Rider |
| Operational (show execution) | Show, Performance, Soundcheck, Readiness, Assignment, Local Runtime, Sync State, Ableton Protocol State |

Shows reference master assets. Assets are not duplicated per Show.

---

## Band

### Definition

The top-level organisational container for a production act. Represents the band or ensemble whose assets, shows, and performances are managed by the platform.

Example: Ed and the Shadow Boys.

### Ownership / Source of Truth

- **Authoritative:** Band record in master library (cloud canonical, replicated locally).
- **Runtime:** Local runtime holds a synced copy for offline operation.

### Key Relationships

- Owns: Musicians, Devices, Instrument Parts, Songs, Light Modes, Mix Moves, Production Configurations, Shows.
- Shows and Performances belong to a Band context.

### Lifecycle Notes

- Created once per act/organisation.
- Rarely deleted; archival preferred.
- All other master assets are scoped to a Band.

### Must Not Be Confused With

- **Show** — a rehearsed production variant, not the organisation.
- **Performance** — a single dated occurrence, not the band entity.

---

## Person (Production Personnel)

### Definition

A band-scoped production personnel record holding legal identity, contact, location, dietary, onboarding documents, instrument preferences, and IEM preference templates. Supports travel, onboarding, festival applications, website/private exports, and generation of production artifacts (stage plots, tech riders, input lists, monitor plans, festival packs).

Person is the canonical **Band People / Production Personnel** domain — shared by the local app and website from one database structure.

### Ownership / Source of Truth

- **Authoritative:** Master library (Person record and child collections).
- **Scope:** Band-scoped; same schema in cloud, Director local, and Local Show Runtime (cached replica).
- **Sync:** Cloud-canonical after publish; no local-only or website-only schema fork.

### Key Relationships

- Belongs to: Band.
- Has many: Person Secure Fields, Person Files, Person Instruments, Person IEM Settings.
- Belongs to many: Instrument Reference (via Person Instruments).
- **Not yet linked to Musician** — mapping is a follow-up phase.

### Child Entities

| Entity | Purpose |
|--------|---------|
| **Person Secure Field** | Encrypted at-rest storage for bank account, passport number, Air New Zealand points |
| **Person File** | Access-controlled document storage (passport photo, visa, contract, invoice, other); private by default |
| **Person Instrument** | Person ↔ Instrument Reference association with role label and primary flag |
| **Person IEM Setting** | Named IEM preference template — not live console bus settings |

### Lifecycle Notes

- Created and maintained during onboarding and ongoing production administration.
- Persists across Shows and Performances as master personnel data.
- Sensitive values never stored in plain text on the Person record itself.
- **Login credentials must never be stored on Person** — no username, password, password hash, invitation token, or access-state columns on `people` or Person child tables.
- Production artifacts (Stage Plot, Tech Rider, input lists, monitor plans, festival packs) are **generated from** canonical Person and related production data — not stored as duplicate personnel schemas.

### Must Not Be Confused With

- **User** — authentication identity for Band Portal login; links to Person but does not replace Person.
- **Musician** — operational runtime roster entity for Performances, Assignments, Devices, and Capabilities.
- **Instrument Part** — operational performance role catalog for Assignments and Capabilities.
- **Instrument Reference** — personnel/onboarding instrument catalog; separate from Instrument Part for now.
- **Person IEM Setting** — preference template only; live console bus settings are performance/runtime scoped and may copy from a selected template when applied.

---

## Person Secure Field

### Definition

An encrypted value associated with a Person for a specific sensitive field type: bank account, passport number, or Air New Zealand points.

### Ownership / Source of Truth

- **Authoritative:** Master library; stored encrypted at rest in `person_secure_fields`.
- **Encryption:** Laravel application encryption with recorded `encryption_key_context`; plaintext must not appear in default serialization.

### Key Relationships

- Belongs to: Person (one row per person per field type).

### Must Not Be Confused With

- **Person File** — binary document storage; secure fields are structured encrypted values.
- **Person profile columns** — legal name, email, phone remain on Person; only designated sensitive types use secure fields.

---

## Person File

### Definition

A managed file record associated with a Person: passport photo, visa, contract, invoice document, or other onboarding/travel document.

### Ownership / Source of Truth

- **Authoritative:** Master library metadata; binary content in managed object storage (not Git).
- **Access:** Private by default (`is_public = false`); not publicly accessible without explicit access control.

### Key Relationships

- Belongs to: Person.

### Must Not Be Confused With

- **Chart / Stage Plot / Tech Rider file assets** — show production documents, not person onboarding files.
- **Public website assets** — Person Files require access control; exports are generated artifacts, not default-public storage.

---

## Instrument Reference

### Definition

A shared catalog entry describing an instrument name and optional family (e.g. Electric Guitar, strings). Used for personnel instrument associations during onboarding and production administration.

### Ownership / Source of Truth

- **Authoritative:** Master library (Instrument Reference catalog).

### Key Relationships

- Has many: Person Instruments.
- **Separate from Instrument Part** — mapping between catalogs may be required in a follow-up phase.

### Must Not Be Confused With

- **Instrument Part** — operational performance role definition (Lead Vocal, Drums, etc.) scoped to Band and used by Capability and Assignment.
- **Person Instrument** — the link between a Person and an Instrument Reference.

---

## Person Instrument

### Definition

The association of a Person with an Instrument Reference, optionally labelled with a role and primary flag.

### Ownership / Source of Truth

- **Authoritative:** Master library (Person ↔ Instrument Reference).

### Key Relationships

- Belongs to: Person, Instrument Reference.

### Must Not Be Confused With

- **Capability** — Musician ↔ Instrument Part eligibility for operational assignments.
- **Assignment** — operational Musician ↔ Instrument Part mapping for a Performance.

---

## Person IEM Setting

### Definition

A named in-ear monitor preference template for a Person: level presets for vocal, own instrument, band, click, tracks, reverb, and ambient mixes.

### Ownership / Source of Truth

- **Authoritative:** Master library (template only).

### Key Relationships

- Belongs to: Person.

### Lifecycle Notes

- Templates are preference defaults for onboarding and preparation.
- When a person is assigned to a performance console bus, a selected Person IEM Setting may be **copied/applied** into performance-specific bus settings — templates are not live console state.

### Must Not Be Confused With

- **Monitor Assignment / console bus settings** — live routing and mix state during Soundcheck and performance.
- **Mix Move** — reusable X32 parameter group asset for cue Actions.

---

## User (Band Portal Authentication)

### Definition

The **authentication identity** for Band Portal (`band.edandtheshadowboys.com` / `/server/`). Stores portal login credentials, access state, and session linkage — not production personnel profile data.

### Ownership / Source of Truth

- **Authoritative:** Cloud identity domain (shared PostgreSQL — same canonical database as Band People).
- **First consumer:** Band Portal (`/server/`). Director local app may share the identity domain in a later phase.

### Key Relationships

- **Must link to exactly one Person** for Band Portal member accounts (direct `person_id` FK preferred; `person_user_links` pivot only if many-to-many is later approved).
- **Must not link to Musician by default** — Musician ↔ User linkage remains a separate operational concern (PH007 M1 `musician_user` pivot).
- May hold roles/permissions for portal access (Administrator, invited member, etc.) — role model follows M1 identity domain.

### Stores (authentication only)

| Field class | Examples | Storage rule |
|-------------|----------|--------------|
| Login identifier | `username` | Unique; used for Band Portal login |
| Credential | `password` | **Hashed only** via Laravel `Hash` — never encrypted reversibly, never displayed |
| Access state | active, suspended, email_verified_at (if used) | On User record |
| Session / remember token | Laravel session conventions | Standard framework tables |

### Must Not Store on User

Travel, passport, banking, dietary, instrument preferences, IEM templates, person files, or any Person secure-field payloads. Those belong on **Person** and **Person Secure Fields**.

### Must Not Be Confused With

- **Person** — canonical human/profile record; not an authentication record.
- **Musician** — operational roster entity for live performance workflows.
- **Person Invitation** — time-limited account-creation artefact; not a User until accepted.

### Approved Account Creation Path (PH047)

1. Administrator creates or selects a **Person**.
2. Administrator sends a **Person Invitation** (time-limited token).
3. Invitee opens token link on Band Portal.
4. Invitee chooses **username** and **password**.
5. **User** record is created, linked to Person.
6. Person onboarding continues progressively (profile, travel, documents, instruments, IEM templates).

Self-registration without invitation is **not** approved for Band Portal in PH047.

### Login Model (PH047)

- **Approved:** Local username + password login on Band Portal.
- **Username** is the login identifier.
- **Email** on Person remains profile/contact data unless a future decision approves email login.

### Password Rules (PH047)

- Passwords are **hashed** with Laravel password hashing — not encrypted.
- No custom reversible password encryption key.
- `APP_KEY` is **not** a password storage strategy.
- Passwords must never be decrypted or displayed.

---

## Person Invitation (proposed — not implemented)

### Definition

A time-limited, single-use, revocable token authorising creation of a **User** account linked to a specific **Person**.

### Governance

| Rule | Statement |
|------|-----------|
| Token | Cryptographically random; not guessable |
| Lifetime | Time-limited; expires |
| Use | Single-use on acceptance |
| Revocation | Administrator may revoke before acceptance |
| Storage | Token hash only at rest — not plaintext in logs |

---

## Musician

### Definition

A person who performs with the band. A global, reusable asset representing identity, contact, and capabilities — not a specific role in a specific show.

### Ownership / Source of Truth

- **Authoritative:** Master library (Musician record).
- **Availability:** Declared per Performance (which musicians are present).
- **Assignment:** Role/instrument assignment is per Performance, per Song, and may change per Cue.

### Key Relationships

- Belongs to: Band.
- Owns: Devices (personal devices registered to the musician).
- Has: Capabilities (which Instrument Parts they can perform).
- Referenced by: Performance (Available Musicians, Assignments), Soundcheck, device sessions.

### Lifecycle Notes

- Created in master library; persists across Shows and Performances.
- May be marked unavailable for a specific Performance without deletion.
- Assignments are operational, not embedded in the Musician record.

### Must Not Be Confused With

- **Person** — production personnel profile, onboarding, travel, and document domain; Musician is the operational roster entity.
- **Instrument Part** — a role definition (e.g. Lead Vocal), not a person.
- **Assignment** — the operational binding of a Musician to an Instrument Part for a Performance/Song/Cue.
- **Device** — hardware used to connect; belongs to Musician but is not the Musician.

---

## Device

### Definition

A physical or logical client used by a Musician to connect to the platform during preparation, soundcheck, or live performance (tablet, phone, browser session).

### Ownership / Source of Truth

- **Authoritative:** Device record linked to owning Musician in master library.
- **Connection state:** Runtime / Performance context (which device is currently connected).

### Key Relationships

- Belongs to: Musician (not Instrument Part, not Show).
- Used during: Soundcheck, Live Show View (musician-centric device experience).
- Tracked in: Performance (Device Connections).

### Lifecycle Notes

- Registered to a Musician in master library.
- May connect/disconnect across Soundcheck and Performance.
- Device identity persists; connection state is ephemeral per session.

### Must Not Be Confused With

- **Instrument Part** — what the musician plays; Device is how they connect.
- **Channel / X32 input** — console routing, not a musician device.

---

## Instrument Part

### Definition

A global, reusable definition of a performance role or instrument position required by the production. Includes played instruments and voice roles.

Examples: Lead Vocal, Harmony Vocal, Backing Vocal, Electric Guitar, Acoustic Guitar, Keys, Bass, Drums.

### Ownership / Source of Truth

- **Authoritative:** Master library (Instrument Part catalog per Band).

### Key Relationships

- Belongs to: Band.
- Referenced by: SongInstrumentPart (instrument requirements per Song), Capability (musician eligibility), Assignment (operational use).

### Lifecycle Notes

- Defined once; reused across Songs, Shows, and Performances.
- Voice roles are Instrument Parts, not a separate entity type.
- Songs declare which Instrument Parts are required; Performances assign Musicians to them.

### Must Not Be Confused With

- **Instrument Reference** — personnel/onboarding instrument catalog; Instrument Part is the operational performance role catalog.
- **Musician** — a person, not a role slot.
- **Capability** — the link stating a Musician *can* perform an Instrument Part.
- **Assignment** — the operational decision that a Musician *will* perform an Instrument Part for a given context.

---

## Capability

### Definition

The declaration that a Musician is able to perform a specific Instrument Part. Links Musician to Instrument Part in the master library.

### Ownership / Source of Truth

- **Authoritative:** Master library (Musician ↔ Instrument Part association).

### Key Relationships

- Links: Musician ↔ Instrument Part.
- Informs: Assignment options during Performance preparation (eligible musicians for a part).

### Lifecycle Notes

- Maintained in master library as musicians join or expand roles.
- Does not imply assignment — only eligibility.
- May be added progressively as the band roster evolves.

### Must Not Be Confused With

- **Assignment** — operational, per Performance/Song/Cue; Capability is eligibility only.
- **Instrument Part** — the role definition itself, not the musician's ability to fill it.

---

## Song

### Definition

A global, reusable musical work. Contains charts, cues, actions, and instrument requirements. Exists independently of any Show.

### Canonical Business Identity

| Field | Format | Range | Scope |
|-------|--------|-------|-------|
| **song_code** | `NNN` (three-digit zero-padded) | `001`–`999` | Unique across the Song Library (Band-scoped master library) |

**Song Code** is the canonical business identifier for a Song. It is stable, human-meaningful, and used for runtime identity composition, Ableton naming, validation, and cross-system reference.

The database `id` (bigint) and `public_id` (uuid) are relational and sync identifiers only — **not** canonical Song business identity. See `docs/DECISION_LOG.md` PH010.01.

### Ownership / Source of Truth

- **Authoritative:** Master library (Song record and child assets).
- **Business identity:** `song_code` — canonical Song identifier in the master library.
- **Runtime mapping:** Ableton PGM number maps Song at performance time within an Active Show; PGM is show-scoped, not a substitute for Song Code.
- **Playlist presence:** Show references Song; playlist order comes from Ableton Show File.

### Key Relationships

- Belongs to: Band.
- Identified by: `song_code` (canonical business identity).
- Contains: SongInstrumentParts, Cues, Actions.
- Referenced by: Show (via Playlist import from Ableton Show File).
- Mapped at runtime via: Song Code + Cue Number (`SSS.CCC`) and Ableton Protocol State (PGM/CC16).

### Lifecycle Notes

- Authored and refined in master library; may be progressively completed.
- Appears once per Show playlist (no duplicate Song entries).
- Encore behaviour returns to existing Song/Cue rather than duplicating.

### Must Not Be Confused With

- **Show** — a production variant; Songs are referenced, not owned.
- **Playlist entry** — ordering context within a Show; the Song asset is global.
- **Cue** — a section within a Song, not the Song itself.

---

## SongInstrumentPart

### Definition

The declaration that a specific Instrument Part is required for a Song. Links a global Instrument Part to a Song and anchors that part's Chart and Snippets for that Song.

Examples: Song "001" requires Lead Vocal and Electric Guitar — each is a SongInstrumentPart row linking the Song to the Instrument Part catalog entry.

### Ownership / Source of Truth

- **Authoritative:** Master library (Song-scoped Instrument Part requirement).

### Key Relationships

- Belongs to: Song, Instrument Part.
- Uses exactly one: Chart (for that Song).
- Has: Snippets (one active Snippet per Cue for this SongInstrumentPart).
- Informs: Assignment options (which parts exist for the Song during Performance preparation).

### Lifecycle Notes

- Created when a Song declares an Instrument Part requirement.
- A Chart is associated with the SongInstrumentPart; the Chart file asset may be shared by multiple SongInstrumentParts (see Chart).
- Snippets are authored per Cue within this context — not embedded in the Chart document.

### Must Not Be Confused With

- **Instrument Part** — the global role definition; SongInstrumentPart is the Song-specific requirement instance.
- **Assignment** — operational Musician ↔ Instrument Part mapping for a Performance; SongInstrumentPart is authored master data.
- **Chart** — the whole notated document; SongInstrumentPart is the anchor for chart and snippet assets.

---

## Chart

### Definition

A notated or visual performance document used by a SongInstrumentPart within a Song. A Song may have multiple Charts because different Instrument Parts require different notation.

### Ownership / Source of Truth

- **Authoritative:** Master library (Chart file asset linked to Song context via SongInstrumentPart).

### Key Relationships

- Used by: SongInstrumentPart (exactly one Chart per SongInstrumentPart per Song).
- May be shared by: multiple SongInstrumentParts (same file asset referenced by more than one part when notation is identical).
- Referenced by: Snippets with `chart_crop` source type (provenance only — Snippets are not Chart children).

### Lifecycle Notes

- Created or linked when preparing a SongInstrumentPart.
- Updated independently of Show or Performance.
- Chart updates do **not** automatically regenerate Snippets; affected Snippets should be flagged out-of-date (see Snippet).
- Displayed on Musician devices based on Assignment + current Cue (full chart mode) or via Snippet (default).

### Must Not Be Confused With

- **Snippet** — a cue-specific visual reference asset; may originate from a chart crop but is an independent copied asset.
- **SongInstrumentPart** — the requirement anchor; Chart is the whole document for that part.
- **Song** — the musical work; Chart is instrument-specific material for it.

---

## Snippet

### Definition

A cue-specific visual reference asset for an Instrument Part within a Song. Displayed to musicians during live performance, Soundcheck, and Cue 0 preparation.

A Snippet is **not merely a chart fragment**. It may originate from:

- Chart crop (Chart Mode workflow)
- Photo capture
- Image upload
- Cloned snippet (independent copy from another cue)
- Freehand / drawing capture

### Ownership / Source of Truth

- **Authoritative:** Master library (Snippet linked to SongInstrumentPart and Cue).

### Key Relationships

- Belongs to: SongInstrumentPart + Cue (Instrument Part + Cue assignment context within a Song).
- One active Snippet per SongInstrumentPart + Cue (`unique(song_instrument_part_id, cue_id)`).
- May reference (provenance only): source Chart, source Snippet (when cloned).
- Displayed during: Live Show View, Soundcheck (chart validation), Cue 0 preparation.

### Lifecycle Notes

- Authored in Chart Mode (crop + cue selection) or Cue View (clone, photo, upload, drawing).
- Chart Mode: after cropping, user selects target Cue; normal list shows only empty cues for that SongInstrumentPart.
- Cloning from another cue creates an **independent copy** — Snippets are not shared between cues.
- Musicians may annotate / mark up a Snippet; markup is per-Snippet, not on the parent Chart.
- Chart updates do not auto-regenerate Snippets; chart-derived Snippets should be flagged out-of-date.
- Automatic navigation displays the Snippet for the current Cue by default.
- Manual override and full chart mode allowed — display only, not timeline authority.

### Must Not Be Confused With

- **Cue** — the section boundary event; Snippet is visual reference content for that section.
- **Chart** — the full document; Snippet is an independent visual asset, not a child row of Chart.
- **Action / Musician Instruction** — text guidance attached to Cues; not a Snippet.
- **Shared asset** — Charts may be shared across SongInstrumentParts; Snippet copies are never shared.

### PH027 Reference

Full reconciliation, superseded assumptions, and future schema design notes: `docs/PH027_SNIPPET_DOMAIN_RECONCILIATION.md`

---

## Cue

### Definition

The start of a musical section within a Song. Represents a structural boundary in the performance timeline.

Examples: Intro, Verse 1, Chorus, Bridge, Solo, Ending.

### Canonical Business Identity

| Field | Format | Range | Scope |
|-------|--------|-------|-------|
| **cue_number** | `NNN` (three-digit zero-padded) | `000`–`999` | Unique within a Song |

**Constraint:** `unique(song_id, cue_number)`

**Cue Number** is the canonical business identifier for a Cue within a Song. Combined with Song Code it forms the canonical runtime identity `SSS.CCC`.

The database `id` (bigint) and `public_id` (uuid) are relational identifiers only — **not** canonical Cue business identity.

**Cue 000** = **Preparation Cue** — exists before the first musical section (Cue 001 onward). Preparation Cue is used for chart loading, snippet preparation, musician instructions, and monitoring setup before musical sections begin.

### Ownership / Source of Truth

- **Authoritative:** Song definition in master library (Cue structure).
- **Business identity:** `cue_number` — canonical Cue identifier within a Song.
- **Runtime authority:** Ableton via CC16 — the platform follows Ableton, does not own the timeline. CC16 maps to `cue_number` at runtime.

### Key Relationships

- Belongs to: Song.
- Identified by: `cue_number` within parent Song (unique per Song).
- Has: Actions (Mix Moves, Light Modes, effects attached to this Cue).
- Associated with: Snippets (per SongInstrumentPart + Cue), Ableton Protocol State (CC16 maps to `cue_number`).
- Special case: **Cue 000** = Preparation Cue (before first musical section).

### Lifecycle Notes

- Defined during Song authoring in master library.
- At runtime, Ableton CC16 changes drive Cue transitions.
- **Cue identity** (`cue_number`) is stable; **cue sequence** (display/performance order) may differ for special arrangements — identity is not runtime sequence.
- Cue 0 is used for preparation: load charts, snippets, instructions, monitoring setup before Cue 1.

### Must Not Be Confused With

- **Action** — something triggered at a Cue (light flash, mix move); Cue is the section boundary.
- **Scene (X32)** — console scene recall; platform uses Mix Moves (parameter groups), not scene recalls as domain Cues.
- **Playlist position** — ordering within a Show; Cue is within-Song structure.

---

## Action

### Definition

An executable production event attached to a Cue. Triggers or references production changes when a Cue boundary is reached.

Examples: apply a Mix Move, activate a Light Mode, trigger an effect.

### Ownership / Source of Truth

- **Definition:** Master library (Action linked to Cue, referencing reusable assets).
- **Execution:** Local Runtime at Cue boundary, driven by Ableton Protocol State.

### Key Relationships

- Attached to: Cue.
- References: Mix Move, Light Mode, or other production effect.
- Executed by: Local Runtime (X32 integration, lighting integration).

### Lifecycle Notes

- Authored as part of Song/Cue definition.
- Fires when Ableton signals the corresponding Cue (CC16).
- Must not block or delay live performance if an Action fails — the show must go on.

### Must Not Be Confused With

- **Cue** — the section boundary; Action is what happens at/on that boundary.
- **Mix Move / Light Mode** — reusable asset definitions; Action is the Cue-level reference/trigger.

---

## Mix Move

### Definition

A reusable X32 production asset consisting of grouped parameter changes. Applied during performance via Actions at Cue boundaries.

Examples: Guitar Solo, Vocal Lift, Acoustic Section, Finale Boost.

### Ownership / Source of Truth

- **Authoritative:** Master library (Mix Move definition per Band).

### Key Relationships

- Belongs to: Band.
- Referenced by: Actions (on Cues), Production Configuration.
- Executed via: X32 Integration in Local Runtime.

### Lifecycle Notes

- Authored once; reused across Songs and Shows.
- Consists of grouped parameter changes — **not** X32 scene recalls.
- May be referenced by multiple Actions across different Songs.

### Must Not Be Confused With

- **X32 Scene** — hardware scene recall; Mix Moves are explicit parameter groups.
- **Action** — the Cue-level trigger; Mix Move is the reusable definition.
- **Production Configuration** — show-level wiring; Mix Move is the asset.

---

## Light Mode

### Definition

A reusable lighting production asset defining a lighting look or state. Referenced by Actions at Cue boundaries.

Examples: Verse, Chorus, Ballad, Solo, Finale.

### Ownership / Source of Truth

- **Authoritative:** Master library (Light Mode definition per Band).

### Key Relationships

- Belongs to: Band.
- Referenced by: Actions (on Cues), Production Configuration.
- Executed via: Lighting Integration in Local Runtime.

### Lifecycle Notes

- Authored once; reused across Songs and Shows.
- Activated when an Action referencing the Light Mode fires at a Cue boundary.

### Must Not Be Confused With

- **Action** — the trigger at a Cue; Light Mode is the reusable lighting definition.
- **Cue** — musical section; Light Mode is a production look applied during a section.

---

## Production Configuration

### Definition

A reusable or show-specific assembly of production settings wiring Songs, Mix Moves, Light Modes, console routing, and monitoring templates for a production variant.

### Ownership / Source of Truth

- **Authoritative:** Master library (Production Configuration per Band).
- **Applied to:** Show (each Show references one Production Configuration).

### Key Relationships

- Belongs to: Band.
- Referenced by: Show.
- Composes: Mix Moves, Light Modes, console/monitor templates, instrument routing context.

### Lifecycle Notes

- Different production variants (Full Band, Lo-Fi, Acoustic) use different Production Configurations.
- Referenced by Show — not duplicated per Performance.
- Updated in preparation; synced to Local Runtime before performance.

### Must Not Be Confused With

- **Show** — the rehearsed production; Production Configuration is the technical wiring.
- **Performance** — a dated occurrence; inherits configuration via Show.

---

## Show

### Definition

A rehearsed production variant. Defines the Ableton Show File, imported Playlist, and Production Configuration for a specific way the band performs (e.g. Callejero Tour - Full Band).

### Ownership / Source of Truth

- **Authoritative:** Master library / cloud canonical.
- **Ableton Show File:** External authoritative source for playlist and cue progression.
- **Runtime copy:** Local Runtime for offline execution.

### Key Relationships

- Belongs to: Band.
- Contains: Ableton Show File (required), Playlist (imported), Production Configuration.
- Referenced by: Performances.
- References: Songs (via Playlist), Production Configuration.

### Lifecycle Notes

- Must have exactly one Ableton Show File — a Show without one is invalid.
- Playlist is imported from Ableton; the platform does not author playlists.
- Songs appear once in playlist; encore returns to Song/Cue.

### Must Not Be Confused With

- **Performance** — a specific dated occurrence; Show is the template.
- **Song** — global asset referenced by Show playlist, not owned by Show.
- **Band** — the organisation; Show is a production variant.

---

## Performance

### Definition

A specific occurrence of a Show at a venue on a date. The unit of live execution — the operator runs a Performance, not a Show.

Examples: Dunedin, Wanaka, Tokyo, Denpasar.

### Ownership / Source of Truth

- **Authoritative:** Operational layer (Performance record).
- **Execution:** Local Runtime (offline-capable).

### Key Relationships

- Belongs to: Band.
- References: Show.
- Contains: Venue, Date, Available Musicians, Assignments, Soundcheck, Device Connections.
- Precedes: Readiness → Live execution.

### Lifecycle Notes

```
Soundcheck → Readiness → Run Performance
```

- Created for each venue/date.
- Assignments are per Performance (and may vary by Song/Cue).
- Must operate fully offline once synced to Local Runtime.

### Must Not Be Confused With

- **Show** — the reusable production template; Performance is one instance.
- **Soundcheck** — pre-performance validation; Performance is the overall occurrence.
- **Assignment** — operational role mapping within a Performance; Performance is the container.

---

## Assignment

### Definition

The operational mapping between a Musician and an Instrument Part within a specific Performance, Song, or Cue context. Assignment declares who *will* perform which role during live execution — not merely who *can* (Capability) or who is *present* (Availability).

An Assignment may be scoped to:

- the entire Performance (default role for a musician),
- a specific Song within the Performance, or
- a specific Cue within a Song (when musicians change instruments or roles mid-show).

### Ownership / Source of Truth

- **Authoritative:** Operational layer (Assignment records scoped to a Performance).
- **Prepared in:** Show Preparation (Director Local / Cloud).
- **Executed in:** Local Runtime (synced copy, offline-capable).

### Key Relationships

- Belongs to: Performance.
- Maps: Musician ↔ Instrument Part.
- Scoped by: Song and/or Cue (optional narrowing of context).
- Informed by: Capability (eligibility — which musicians *can* fill the part).
- Constrained by: Availability (musician must be listed as available for the Performance).
- Determines: which Chart/Snippet the Musician sees on their Device during Live Show View (via SongInstrumentPart resolution).
- Validated during: Soundcheck.
- Distinct from: Device (how the musician connects, not what they play).

### Lifecycle Notes

- Created and edited during Performance preparation.
- May vary by Song and by Cue within the same Performance.
- Must reference a Musician who is available for the Performance.
- Should reference an Instrument Part the Musician is capable of performing (Capability).
- Validated during Soundcheck (assignments, charts, monitoring).
- Active during Live Show View — drives musician-centric display and monitor routing context.
- Synced to Local Runtime before performance; must be available offline.

### Must Not Be Confused With

- **Capability** — eligibility (what a Musician *can* do); Assignment is the operational decision (what they *will* do).
- **Availability** — whether a Musician is present for a Performance; Assignment maps an available Musician to a role.
- **Instrument Part** — the role definition itself (e.g. Lead Vocal); Assignment is the Musician-to-role binding for a context.
- **Device** — the client used to connect; Assignment determines role and chart content, not connection hardware.
- **Chart** — the notated material for an Instrument Part; Assignment determines which Chart the Musician receives, but Chart is the asset, not the mapping.

---

## Stage Plot

### Definition

A production document describing physical stage layout: instrument positions, risers, sightlines, and spatial relationships for a Show or production variant.

**Artifact rule:** Stage plots are **generated artifacts** from canonical production data (including Person, Musician, Assignment, and Instrument Part context where applicable). The stored Stage Plot file asset is the published output — not a parallel personnel schema.

### Ownership / Source of Truth

- **Authoritative:** Master library (Stage Plot asset, associated with Show or Production Configuration).

### Key Relationships

- Associated with: Show / Production Configuration.
- Referenced during: Show Preparation, Soundcheck, venue coordination.

### Lifecycle Notes

- Authored during show preparation.
- May vary per Show variant (Full Band vs Acoustic layout).
- Informational for crew and musicians — does not drive runtime automation.

### Must Not Be Confused With

- **Tech Rider** — technical requirements document; Stage Plot is spatial layout.
- **Assignment** — who plays what; Stage Plot is where they stand.

---

## Tech Rider

### Definition

A production document describing technical requirements for a Show: backline, inputs, power, monitoring, lighting requirements, and venue needs.

**Artifact rule:** Tech riders, input lists, monitor plans, and festival packs are **generated artifacts** from canonical production data (Person, Musician, Assignments, Instrument Parts, and related configuration). Stored file assets are published outputs — not duplicate local-only or website-only personnel schemas.

### Ownership / Source of Truth

- **Authoritative:** Master library (Tech Rider asset, associated with Show or Production Configuration).

### Key Relationships

- Associated with: Show / Production Configuration.
- Referenced during: Show Preparation, venue advance, Soundcheck.

### Lifecycle Notes

- Authored during show preparation.
- Shared with venues and crew.
- Informational — supports preparation, not runtime timeline control.

### Must Not Be Confused With

- **Stage Plot** — spatial layout; Tech Rider is technical specification.
- **Production Configuration** — runtime wiring; Tech Rider is documentary.

---

## Soundcheck

### Definition

A first-class pre-performance process validating that all systems, people, and assignments are ready before live execution. Includes direct musician participation.

### Ownership / Source of Truth

- **Authoritative:** Operational layer (Soundcheck state on Performance).

### Key Relationships

- Belongs to: Performance.
- Validates: Ableton, X32, Lighting, Devices, Musicians, Assignments, Monitoring, Charts.
- Precedes: Readiness.

### Lifecycle Notes

- Conducted before each Performance.
- Musicians may: confirm readiness, check ears, adjust monitoring, validate charts.
- Soundcheck completion contributes to Readiness gate.

### Must Not Be Confused With

- **Readiness** — the gate state after Soundcheck; Soundcheck is the process.
- **Rehearsal** — informal practice; Soundcheck is structured pre-show validation.

---

## Readiness

### Definition

The operational gate state indicating a Performance has completed Soundcheck and is cleared to enter live execution.

### Ownership / Source of Truth

- **Authoritative:** Operational layer (Readiness state on Performance, set after Soundcheck).

### Key Relationships

- Follows: Soundcheck.
- Precedes: Live Show View / Performance execution.
- Aggregates: subsystem readiness (Ableton, X32, lighting, devices, musicians).

### Lifecycle Notes

```
Soundcheck → Readiness → Run Performance
```

- Must be achieved before operator starts live execution.
- May be revoked if critical systems fail pre-show (operator decision).

### Must Not Be Confused With

- **Soundcheck** — the validation process; Readiness is the resulting state.
- **Sync State** — data sync between environments; Readiness is show-day operational.

---

## Local Runtime

### Definition

The offline-capable execution environment running at show time. Docker-based local stack with local database, UI, and integrations.

### Ownership / Source of Truth

- **Authoritative at performance time:** Local Runtime (local database, local cache).
- **Synced from:** Cloud canonical environment before performance.

### Key Relationships

- Hosts: Live Show View, Soundcheck execution, Ableton/X32/Lighting integrations.
- Consumes: synced Show, Performance, Assignments, master assets.
- Tracks: Ableton Protocol State, Device Connections.

### Lifecycle Notes

- Provisioned before performance (Director Local or venue machine).
- Must operate with no internet connectivity.
- Receives sync from cloud; cloud is not required during performance.

### Must Not Be Confused With

- **Cloud Environment** — collaboration/sync; Local Runtime executes the show.
- **Director Local Environment** — primary design/preparation workstation; may also host Local Runtime.

---

## Sync State

### Definition

The tracked state of data synchronisation between the Cloud canonical environment and Local Runtime (or Director Local Environment).

### Ownership / Source of Truth

- **Authoritative direction:** Cloud is canonical for master library and operational records.
- **Runtime direction:** Local Runtime is authoritative during live performance execution.

### Key Relationships

- Between: Cloud Environment ↔ Director Local Environment ↔ Local Show Runtime.
- Affects: master assets, Show/Performance data, chart files, configuration.

### Lifecycle Notes

- Sync supports collaboration, backup, and distribution — not live performance dependency.
- Last sync timestamp and conflict state tracked per entity or bundle.
- Performance must not start with stale critical data (operator verification).

### Must Not Be Confused With

- **Ableton Protocol State** — runtime timeline from Ableton; Sync State is data replication.
- **Readiness** — show-day operational gate; Sync State is infrastructure.

---

## Ableton Protocol State

### Definition

The runtime representation of Ableton's authoritative timeline signals. The platform follows Ableton; it does not own the timeline.

### Ownership / Source of Truth

- **Authoritative:** Ableton Live (external runtime master).
- **Consumed by:** Local Runtime integrations.

### Key Relationships

- Maps: PGM → current Song, CC16 → current Cue.
- Drives: Cue transitions, Action execution, chart navigation, musician display updates.

### Protocol

```
PGM = Song
CC16 = Cue
```

Example:

```
PGM 12
CC16 1
→ Song 12 / Intro (Cue 1)
```

Cue 0 = Preparation Cue (before Cue 1 begins).

### Lifecycle Notes

- Ephemeral runtime state — changes continuously during performance.
- Platform reacts to Ableton; never overrides timeline authority.
- Cue 0 used for preparation before musical sections begin.

### Must Not Be Confused With

- **Cue (domain model)** — the authored section definition in a Song; Ableton Protocol State is the live signal.
- **Sync State** — data replication; Ableton Protocol State is live performance timeline.

---

## Runtime Authority Summary

| Concern | Authority |
|---------|-----------|
| Timeline (Song, Cue progression) | Ableton |
| Master assets (Songs, Charts, etc.) | Cloud canonical → synced locally |
| Live execution | Local Runtime |
| Performance operator context | Performance (not Show) |
| Role mapping (who plays what) | Assignment (per Performance/Song/Cue) |
| Musician display | Musician-centric (not instrument-centric) |
| Playlist order | Ableton Show File (imported, not authored) |

---

End of Domain Model — PH001.01
