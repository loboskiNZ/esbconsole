# Foundation Implementation Plan

Status: PH008 Finalised  
Authority: `docs/PROJECT_CHARTER.md`  
Purpose: Define the first implementable foundation slice and implementation sequence before any code begins

Related documents:

- Physical database plan: `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`
- Logical schema: `docs/DATABASE_ARCHITECTURE.md`
- Data ownership: `docs/DATA_ARCHITECTURE.md`
- Infrastructure: `docs/ARCHITECTURE.md`
- Navigation target: `docs/INFORMATION_ARCHITECTURE.md`

This document defines **what to build first**, **in what order**, and **how to verify** — not Laravel scaffold, migrations, Docker files, or application code.

**Out of scope for PH008:** migrations, Laravel install, package install, Docker Compose files, models, routes, frontend implementation, `.env` changes, production infrastructure.

---

## 1. Purpose

PH008 establishes the foundation implementation plan — the governed path from governance-complete design (PH001–PH007) to the first working vertical slice (PH009).

Goals:

- Confirm target technical stack for local and cloud
- Define local development, cloud deployment, and Local Show Runtime topologies
- Plan Laravel scaffold, PostgreSQL, Docker Compose, auth, and roles
- Scope **M1 Identity & Access** migration planning only
- Define first vertical slice: Login → Band → Show list → Active Show → Playlist
- Specify test strategy, verification evidence, gates, and non-scope

PH009 implementation **must follow** this document.

---

## 2. Governing References

| Document | Role in PH008 |
|----------|---------------|
| `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md` | PostgreSQL, M1–M14 order, identifier strategy |
| `docs/DATABASE_ARCHITECTURE.md` | Logical domains; Show/Playlist aggregates |
| `docs/DATA_ARCHITECTURE.md` | Band scoping; no Git assets |
| `docs/INFORMATION_ARCHITECTURE.md` | Login → Shows → Active Show navigation |
| `docs/UX_MODEL.md` | Director-primary; Show-centric navigation |
| `docs/DECISION_LOG.md` | PH008 decisions 093–100 |

---

## 3. Foundation Implementation Principles

| # | Principle |
|---|-----------|
| 1 | **Governance first.** Every PH009 change references applicable authority docs. |
| 2 | **Vertical slice over horizontal layers.** Prove Login → Playlist read path before runtime integrations. |
| 3 | **PostgreSQL everywhere.** Same engine local and cloud per PH007 Decision 083. |
| 4 | **Migrations only.** No ad hoc schema; M1 first per PH007. |
| 5 | **Local development without cloud.** Director dev works offline from local Postgres. |
| 6 | **No production assets in Git.** `/resources/`, `/songs/`, `/charts/`, `/uploads/` remain ignored. |
| 7 | **No runtime integrations in first slice.** No MIDI, DMX, X32, bridges, or WebSocket implementation in PH009 foundation phase. |
| 8 | **Existing frontend evaluated, not rewritten.** Current client assessed for integration; minimal changes in PH009. |
| 9 | **Test before expand.** M1 gates must pass before M2/M8 migrations for Show/Playlist slice. |
| 10 | **The show must go on.** Foundation choices must not compromise future offline show execution. |

---

## 4. Target Technical Stack

| Layer | Technology | Status |
|-------|------------|--------|
| **Backend** | Laravel 11+ | Planned PH009 |
| **Database** | PostgreSQL 16+ | Planned PH009 |
| **Cache / queue** | **Valkey 7+** (Redis-compatible) | Planned PH009 |
| **Realtime** | Laravel Reverb / WebSockets | Planned post-foundation; **not PH009** |
| **Cloud file storage** | DigitalOcean Spaces | Cloud only; not required for local dev |
| **Local file cache** | Filesystem volumes | Runtime phase; not PH009 |
| **Authentication** | Laravel Breeze or Fortify + session | Planned PH009 M1 |
| **Frontend** | Existing React/Vite client (`client/`) | Evaluate and minimally integrate PH009 |
| **Cloud hosting** | DigitalOcean Droplet + Laravel Forge | Post-foundation deployment |
| **Containerisation** | Docker Compose (local dev + runtime) | Planned PH009 |

### Valkey vs Redis recommendation

**Recommend Valkey 7+** for cache/queue:

| Reason | Detail |
|--------|--------|
| Redis-compatible API | Laravel `redis` driver works unchanged |
| Open source | No licensing uncertainty |
| Docker image | Official `valkey/valkey` image |
| Operational parity | Same commands as Redis for cache, queue, pub/sub |

Redis 7 remains acceptable if Valkey image unavailable on a host — document swap in `.env` only.

---

## 5. Local Development Topology

Director workstation — **no cloud dependency**:

```
Developer machine (Host OS)
├── Git repo (code + docs + migrations only)
├── Docker Compose (PH009)
│   ├── app (Laravel + existing client served or proxied)
│   ├── postgres (PostgreSQL 16)
│   ├── valkey (Valkey 7)
│   └── (optional) mailpit for auth emails
├── Host: existing Node dev server for client/ (during transition)
└── Local .env → Docker postgres + valkey
```

| Service | Port (default) | Purpose |
|---------|----------------|---------|
| Laravel app | 8000 or 8080 | API + future web routes |
| PostgreSQL | 5432 | Director local database |
| Valkey | 6379 | Cache, queue, future pub/sub |
| Vite dev (client/) | 5173 | Existing frontend hot reload |

Ableton, MIDI Bridge, and lighting **not required** for PH009 foundation slice.

---

## 6. Cloud Deployment Topology

Post-foundation (not PH009 scope):

```
User domain (DNS)
    ↓
DigitalOcean Droplet (Forge-managed)
├── Laravel application
├── Nginx
├── PostgreSQL 16 (DO Managed Database — separate service)
├── DigitalOcean Spaces (file assets)
└── (optional) Valkey/Redis managed or on-droplet

Musicians / Director (preparation) → HTTPS → Cloud
Local Show Runtime (show day) → separate Docker stack — not cloud-dependent
```

Cloud deployment is **PH010+** — PH009 proves local foundation only.

---

## 7. Local Show Runtime Topology

Full runtime stack per `docs/INTEGRATION_RUNTIME_ARCHITECTURE.md` — **not built in PH009**:

```
Host OS: Ableton, MIDI Bridge, Lighting Bridge, optional X32 Bridge
Docker: Laravel, PostgreSQL, Valkey, Reverb (later), Local UI, file cache volumes
```

PH009 uses **Director local Docker Compose** only — same service pattern, reduced scope (no bridges, no runtime tables).

---

## 8. Laravel Scaffold Plan

PH009 scaffold steps (planned — not executed in PH008):

| Step | Action |
|------|--------|
| 1 | Create new Laravel 11 app in `backend/` **or** migrate existing `index.js` API incrementally — **evaluate in PH009 kickoff** (see OQ-001) |
| 2 | Configure `.env.example` for PostgreSQL + Valkey (no secrets committed) |
| 3 | Set `DB_CONNECTION=pgsql`, timezone UTC |
| 4 | Install Laravel Breeze (API + session) or Fortify — **Breeze recommended** for speed |
| 5 | Configure `config/database.php` for PostgreSQL |
| 6 | Configure `config/cache.php` and `config/queue.php` for Valkey |
| 7 | Add PHPUnit/Pest baseline |
| 8 | Verify `php artisan serve` boots |

**Constraint:** Do not remove existing production `index.js` runtime until Laravel parity reached — parallel operation during transition (future phase).

---

## 9. PostgreSQL Setup Plan

| Environment | PH009 action |
|-------------|--------------|
| **Local Docker** | `postgres:16-alpine` service; volume `postgres_data`; database `esb_dev` |
| **Director native** | Optional alternative — Docker preferred for parity |
| **Cloud** | Deferred; use same migration files when Forge provisions DO Managed PostgreSQL |

### Connection requirements

- UTF-8 encoding
- UTC timezone
- SSL off locally; SSL on in cloud
- Single database per environment; `band_id` tenant scoping in application layer

---

## 10. Docker Compose Planning

Planned `docker-compose.yml` structure (PH009 — not created in PH008):

```yaml
services:
  app:        # Laravel PHP-FPM or artisan serve container
  postgres:   # PostgreSQL 16
  valkey:     # Valkey 7
  # nginx:    # optional reverse proxy
```

| Volume | Purpose |
|--------|---------|
| `postgres_data` | Database persistence |
| `valkey_data` | Optional cache persistence |

**Not in PH009 Compose:** MIDI Bridge, lighting, Ableton, Reverb (added in later phases).

`.dockerignore` must exclude `/resources/`, `/songs/`, `/charts/`, `/uploads/`.

---

## 11. Authentication Foundation Plan

| Item | Plan |
|------|------|
| **Mechanism** | Laravel session authentication (Breeze) |
| **User model** | `users` table — auth identity per PH007 M1 |
| **Musician link** | `musician_user` pivot — optional link; not required for Director login in first slice |
| **Password** | Bcrypt/argon via Laravel defaults |
| **Roles** | Role checked after login — Director/Admin for first slice |
| **Local dev** | Mailpit or log driver for password reset emails |
| **Cloud** | Real SMTP via Forge when deployed |

First slice requires **Director or Administrator** can log in — Musician login deferred to later slice.

---

## 12. Role / Permission Foundation Plan

| Role | First slice access |
|------|-------------------|
| **Director** | Full read on Band, Shows, Playlist |
| **Administrator** | Same as Director for foundation + user management (future) |
| **Tech** | Deferred |
| **Musician** | Deferred |

### Implementation approach

| Option | Recommendation |
|--------|----------------|
| **Spatie Laravel Permission** | Recommended — aligns with PH007 OQ-001 |
| **Native roles enum** | Acceptable for M1 minimal if Spatie deferred |

M1 tables (conceptual):

- `roles` — name, guard
- `permissions` — optional in M1 minimal
- `role_user` or `model_has_roles` (Spatie)
- `users.role` fallback enum acceptable for absolute M1 minimum

Director role required for first vertical slice.

---

## 13. Initial Migration Scope

**PH008 plans M1 only.** Per `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md` §9.

| Domain | Migration group | PH008 | PH009 foundation slice |
|--------|-----------------|-------|------------------------|
| **M1 Identity / access** | users, roles, permissions | ✅ Planned here | ✅ Implement |
| M2 Band / organisation | bands | ⬜ PH009 after M1 gate | ✅ Required for slice |
| M8 Shows / playlists | shows, show_playlist_items | ⬜ PH009 after M1 | ✅ Required for slice |
| M6 Songs | songs | ⬜ Later | ✅ Playlist FK references (minimal) |

**PH009 sequence:**

1. Implement and verify **M1**
2. Implement **M2** (bands) + minimal **M8** (shows, show_playlist_items) + minimal **M6** (songs for playlist FK)
3. Build vertical slice UI/API read path

M1 alone does not deliver the Playlist slice — documented explicitly to prevent scope confusion.

---

## 14. M1 Identity & Access Plan

### Tables (conceptual — PH009 migration files)

| Table | Purpose |
|-------|---------|
| `users` | Auth identity: id, public_id (uuid), name, email, password, email_verified_at, timestamps |
| `roles` | Role catalog: id, name, guard_name |
| `permissions` | Optional M1 — may defer to Spatie default migration |
| `role_user` or Spatie pivots | User ↔ Role |
| `musician_user` | Optional M1 — user_id, musician_id (nullable FK; musician table in M3) |

**Musician linkage in M1:** Include `musician_user` pivot table structure only if Spatie/User model adopted — **musicians table deferred to M3**. Pivot may be empty until M3. Alternative: add musician link in M3 migration only (preferred — keeps M1 pure identity).

### Recommended M1 contents

| Include | Exclude (later migrations) |
|---------|---------------------------|
| users | musicians, devices |
| roles + role assignments | bands (M2) |
| password reset tokens (Laravel default) | shows, playlists |
| sessions (if database sessions) | songs, cues, actions |
| public_id uuid on users | file_assets, runtime_* |

### Identifier fields (per PH007 §12)

- `users.id` — bigint PK
- `users.public_id` — uuid unique, indexed
- No hard-coded user IDs or emails in migrations

---

## 15. Initial Band / Director Seed Plan

| Seed | Method | Content |
|------|--------|---------|
| **Band** | `DatabaseSeeder` | One band: "Ed and the Shadow Boys" |
| **Director role** | Role seeder | `director`, `administrator` roles |
| **Bootstrap user** | Artisan command `esb:create-director` | Prompts for name/email/password — **not hard-coded in seeder** |
| **Test data** | PHPUnit/Pest factories | Fake users for tests only |

### Show/Playlist test data (PH009 — post M2/M8)

- Factory or seeder creates 2–3 Shows with playlist items referencing test Songs
- Used for vertical slice verification — not committed production data

---

## 16. First Vertical Slice

### Navigation flow

```
Login
  ↓
Band context (Active Band — single band in foundation)
  ↓
Show list
  ↓
Select active Show
  ↓
Basic Playlist view (ordered Song names from show_playlist_items)
```

Aligns with `docs/INFORMATION_ARCHITECTURE.md` entry path (Shows before Active Show depth).

### What this slice proves

| Capability | Evidence |
|------------|----------|
| Laravel app foundation | App boots; artisan works |
| PostgreSQL connection | migrate succeeds |
| Authentication | Director can log in/out |
| Governed migration flow | M1 → M2 → M8 minimal via Laravel migrations |
| Band/Show/Playlist read path | API or web returns show list + playlist from DB |
| No production assets in Git | `.gitignore` unchanged; no asset commits |
| No cloud dependency | Full slice runs on local Docker Postgres |

### Out of scope for this slice

- Live Show View, Soundcheck, Performances
- MIDI, DMX, X32, bridges, Reverb
- File uploads, charts, Spaces
- Publish/sync packages
- Musician device login

---

## 17. Test Strategy

| Test | Type | When |
|------|------|------|
| App boots locally | Manual + Feature | PH009 |
| PostgreSQL connects | Feature (`DB::connection()->getPdo()`) | PH009 |
| M1 migrations run | `migrate:fresh` | PH009 |
| M1 migrations roll back | `migrate:rollback` | PH009 |
| Director can login | Feature (HTTP login) | PH009 |
| Band context resolves | Feature (authenticated request returns band) | PH009 |
| Show list loads | Feature (seeded shows returned) | PH009 after M2/M8 |
| Playlist renders DB data | Feature (ordered playlist items) | PH009 after M2/M8 |
| No ignored asset folders tracked | CI script (`git ls-files resources songs charts uploads`) | PH009 CI |
| `.env` not committed | `git check-ignore` / manual | PH009 |

Test framework: **Pest** (preferred) or PHPUnit — Laravel default.

---

## 18. Verification Evidence Required

Before marking PH009 foundation complete, collect:

| # | Evidence |
|---|----------|
| 1 | Screenshot or CLI output: `php artisan migrate:status` all green |
| 2 | Screenshot: successful login as Director |
| 3 | API/HTTP response: show list JSON with ≥1 show |
| 4 | API/HTTP response: playlist ordered items for selected show |
| 5 | `git ls-files` confirms zero files under resources/songs/charts/uploads |
| 6 | Test suite green (`php artisan test`) |
| 7 | Rollback test log: migrate down/up cycle succeeds |
| 8 | Completion report per AGENTS.md |

---

## 19. Rollback Strategy

| Layer | Rollback |
|-------|----------|
| **M1 migration** | `php artisan migrate:rollback --step=N` |
| **Docker environment** | `docker compose down -v` (destroys local DB — dev only) |
| **Laravel scaffold** | Revert PH009 git commits |
| **Partial PH009** | Do not merge incomplete migration groups |

Never rollback migrations against production cloud DB without backup (PH007 §20).

---

## 20. Implementation Readiness Checklist

### PH008 gates (this document)

| # | Gate | Status |
|---|------|--------|
| 1 | PH001–PH007 governance complete | ✅ |
| 2 | PH008 foundation plan complete | ✅ |
| 3 | First vertical slice defined | ✅ |
| 4 | M1 scope documented | ✅ |
| 5 | Stack and topology defined | ✅ |

### PH009 gates (before writing code)

| # | Gate | Required |
|---|------|----------|
| 1 | Governance reference check — cite FOUNDATION_IMPLEMENTATION_PLAN + PHYSICAL_DATABASE_AND_MIGRATION_PLAN | ✅ |
| 2 | Clean working tree | ✅ |
| 3 | Database engine confirmed PostgreSQL 16+ | ✅ PH007 |
| 4 | Docker/local Postgres approach confirmed | ✅ §9–§10 |
| 5 | Migration order confirmed M1 → M2 → M8 minimal | ✅ §13 |
| 6 | Rollback strategy confirmed | ✅ §19 |
| 7 | Test plan confirmed | ✅ §17 |

**PH009 may begin when all PH009 gates are explicitly acknowledged in the PH009 kickoff prompt.**

---

## 21. Explicit Non-Scope

PH008 and PH009 foundation phase exclude:

- Migration file creation (PH008)
- Laravel install / composer require (PH008)
- Docker Compose file creation (PH008)
- M6+ full Song/Cue/Action schema
- Performances, Assignments, Soundcheck, Readiness
- Runtime state tables, sync packages, audit (beyond M1 users)
- MIDI Bridge, Lighting Bridge, X32 Bridge
- Laravel Reverb / WebSocket implementation
- DigitalOcean Spaces integration
- Cloud Forge deployment
- Musician device UX
- Live Show View
- Replacing existing `index.js` production server
- Frontend rewrite

---

## 22. Open Questions

| ID | Question | Deferred to |
|----|----------|-------------|
| OQ-001 | New Laravel in `backend/` vs incremental wrap of `index.js` | PH009 kickoff |
| OQ-002 | Breeze vs Fortify for auth | PH009 (default: Breeze) |
| OQ-003 | Spatie Permission vs simple role enum | PH009 (default: Spatie) |
| OQ-004 | API-first vs Blade web for first slice | PH009 |
| OQ-005 | Existing React client integration pattern (proxy vs API-only) | PH009 |
| OQ-006 | Single repo monorepo layout final structure | PH009 |
| OQ-007 | Database sessions vs Valkey sessions | PH009 |

---

## 23. Glossary

| Term | Definition |
|------|------------|
| **Foundation slice** | Login → Band → Show list → Active Show → Playlist read path. |
| **M1** | First migration domain: Identity & Access only. |
| **Vertical slice** | End-to-end proof across stack — not a single layer. |
| **Valkey** | Redis-compatible open-source cache/queue server. |
| **Director local** | Developer/preparation environment on Ed's workstation. |
| **PH009** | Next phase: actual Laravel scaffold + M1 implementation + slice. |

---

End of Foundation Implementation Plan — PH008
