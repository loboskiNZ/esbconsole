# Band Portal — Laravel Forge Setup

Status: Infrastructure baseline (pre-feature)  
Site: **https://band.edandtheshadowboys.com**  
Repository layout:

| Path | Role |
|------|------|
| `/client/` | Local X32/Ableton app |
| `/server/` | Laravel cloud Band Portal (this app) |
| `/mockup/` | Website template / design system source |

---

## 1. Forge site configuration

| Setting | Value |
|---------|-------|
| **Domain** | `band.edandtheshadowboys.com` |
| **Project type** | Laravel |
| **Web directory** | `/server/public` |
| **Repository root** | Monorepo root (clone full `esbconsole` repo) |
| **PHP version** | **8.4** (project runtime baseline; match `composer.json` `^8.4` in `/server` and `/backend`) |
| **Laravel version** | **13.x** (match `laravel/framework`: `^13.8`) |

### Why `/server/public`

The Band Portal Laravel application lives under `/server/`. Nginx must point the site document root at **`/server/public`**, not `/public` at repo root.

### Deploy script assumptions

Forge default Laravel deploy hooks should run **from `/server`**:

```bash
cd /home/forge/band.edandtheshadowboys.com/server

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
fi
```

Adjust `$FORGE_SITE_PATH` if Forge site path differs. Use `php artisan migrate --force` only after production database is provisioned and `.env` is configured.

---

## 2. Environment variables (`.env` on server)

Create `/server/.env` on the Forge server (**never commit**). Copy from `server/.env.example` and set:

| Variable | Required | Notes |
|----------|----------|-------|
| `APP_NAME` | Yes | `Band Portal` |
| `APP_ENV` | Yes | `production` |
| `APP_KEY` | Yes | Run `php artisan key:generate` once on server |
| `APP_DEBUG` | Yes | `false` in production |
| `APP_URL` | Yes | `https://band.edandtheshadowboys.com` |
| `DB_CONNECTION` | Yes | `pgsql` |
| `DB_HOST` | Yes | Managed PostgreSQL host from DO/Forge |
| `DB_PORT` | Yes | Usually `25060` (DO SSL) or `5432` |
| `DB_DATABASE` | Yes | Forge/DO database name |
| `DB_USERNAME` | Yes | Database user |
| `DB_PASSWORD` | Yes | Database password |
| `SESSION_DRIVER` | Yes | `database` (baseline migrations include sessions table) |
| `SESSION_SECURE_COOKIE` | Yes | `true` behind HTTPS |
| `CACHE_STORE` | Yes | `database` until Redis is provisioned |
| `QUEUE_CONNECTION` | Yes | `database` until Redis worker is configured |
| `MAIL_*` | When sending mail | Configure via Forge mail provider |

Optional later: `REDIS_*`, `AWS_*` (Spaces), queue workers.

---

## 3. SSL / HTTPS

1. In Forge → site → **SSL** → obtain Let's Encrypt certificate for `band.edandtheshadowboys.com`.
2. Enable **Force HTTPS**.
3. Confirm `APP_URL` uses `https://`.
4. Set `SESSION_SECURE_COOKIE=true`.

Health check: `https://band.edandtheshadowboys.com/up` (Laravel built-in).

---

## 4. Database

Per `docs/PHYSICAL_DATABASE_AND_MIGRATION_PLAN.md`:

- **Engine:** PostgreSQL 16+
- **Hosting:** DigitalOcean Managed PostgreSQL (provision via Forge)
- **SSL:** Required for managed DB connections from Droplet

Baseline Laravel migrations ship with the skeleton (`users`, `cache`, `jobs`, session tables). Band People and portal features are **not** in this infrastructure phase.

---

## 5. SSH deploy key

### Key material

| File | Committed? | Location |
|------|------------|----------|
| Private key | **No** | `server/deploy/keys/band-portal-forge` (gitignored) or operator `~/.ssh/` |
| Public key | **Yes** | `server/deploy/band-portal-forge-deploy.pub` |

Regenerate locally if needed (private key stays out of Git):

```bash
mkdir -p server/deploy/keys
ssh-keygen -t ed25519 -f server/deploy/keys/band-portal-forge \
  -C "forge-deploy-band.edandtheshadowboys.com" -N ""
cp server/deploy/keys/band-portal-forge.pub server/deploy/band-portal-forge-deploy.pub
```

### Where to add the public key

1. **GitHub (repository deploy key)**  
   Repository → Settings → Deploy keys → Add deploy key  
   Paste contents of `server/deploy/band-portal-forge-deploy.pub`  
   Read-only access is sufficient for Forge pull deploys.

2. **Laravel Forge (if using custom SSH key for git)**  
   Forge → Server → SSH Keys, or Site → Repository → use server default key or add this public key to GitHub as above.

3. **Never** commit `band-portal-forge` (private key) or `.env`.

---

## 6. Local verification (operator workstation)

```bash
cd server
composer validate
composer install
cp .env.example .env
php artisan key:generate
# optional: sqlite for local smoke test only
php artisan about
php artisan route:list
php artisan test
```

Production PostgreSQL is not required for `composer validate` / `about` / `route:list`.

---

## 7. Explicit non-scope (this phase)

- Band People onboarding UI
- Invitation system
- Website template integration from `/mockup/`
- Shared schema migrations from `/backend/` (follow-up sync phase)

---

## 8. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| 404 on all routes | Wrong web root | Set web directory to `/server/public` |
| 500 after deploy, no APP_KEY | Missing `.env` | Create `.env`, run `php artisan key:generate` |
| Composer platform error | PHP version mismatch | Forge PHP 8.4 |
| DB connection refused | Wrong host/port/SSL | Use DO managed DB credentials; enable SSL if required |
| Git clone fails on Forge | Deploy key missing | Add `band-portal-forge-deploy.pub` to GitHub deploy keys |
