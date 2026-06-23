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
| **Web directory** | `server/public` |
| **Repository root** | Monorepo root (clone full `esbconsole` repo) |
| **Install Composer Dependencies** (initial repo hook) | **Uncheck** — `composer.json` is not at repo root |
| **PHP version** | **8.4** (project runtime baseline; match `composer.json` `^8.4` in `/server` and `/backend`) |
| **Laravel version** | **13.x** (match `laravel/framework`: `^13.8`) |

### Monorepo: `composer.json` is in `/server`

Forge’s default “Install Composer Dependencies” step looks for **`composer.json` at the repository root**. This monorepo has:

| Path | `composer.json`? |
|------|------------------|
| `/` (repo root) | **Yes** — monorepo shim (delegates install to `server/`) |
| `/server/` | **Yes** — Band Portal Laravel app |
| `/backend/` | **Yes** — local foundation app (not this Forge site) |

If you see:

> Your project does not contain a composer.json file.  
> When installing this repository, you should uncheck the Install Composer Dependencies option.

That is **expected**. Uncheck it, finish site setup, then use the deploy script below (runs Composer inside `/server`).

### Why `/server/public`

The Band Portal Laravel application lives under `/server/`. Nginx must point the site document root at **`/server/public`**, not `/public` at repo root.

### Deploy script (required — replace entire Forge script)

Forge’s default Laravel script runs `composer install` in **`$FORGE_RELEASE_PATH`** (release root).  
**Replace the whole deployment script** in Forge → site → **Deployments** with `server/deploy/forge-deploy.sh`:

```bash
cd $FORGE_RELEASE_PATH

# Forge links .env and storage at release root; Laravel app is in server/
ln -nfs $FORGE_SITE_PATH/.env $FORGE_RELEASE_PATH/server/.env
rm -rf $FORGE_RELEASE_PATH/server/storage
ln -nfs $FORGE_SITE_PATH/storage $FORGE_RELEASE_PATH/server/storage

# Root composer.json delegates to server/ via post-install-cmd
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

cd $FORGE_RELEASE_PATH/server

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
fi
```

Do **not** leave the default `$FORGE_COMPOSER install` + `artisan` block at release root without `cd server`.

Ensure `/server/.env` content is configured in Forge → **Environment** (writes shared `$FORGE_SITE_PATH/.env`, symlinked into `server/` on deploy).

Use `php artisan migrate --force` only after production database is provisioned and `.env` is configured.

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
| “Your project does not contain a composer.json file” | Forge ran Composer at **repo root** before shim existed | Pull latest `main` (root `composer.json` added); replace deploy script with `server/deploy/forge-deploy.sh` |
| “Composer could not find composer.json in …/releases/…” | Deploy script still runs Composer only at **release root** without root shim or `cd server` | **Replace entire** Forge deploy script; do not keep default Laravel script |
| 404 on all routes | Wrong web root | Set web directory to `server/public` |
| 500 after deploy, no APP_KEY | Missing `.env` | Create `.env`, run `php artisan key:generate` |
| Composer platform error | PHP version mismatch | Forge PHP 8.4 |
| DB connection refused | Wrong host/port/SSL | Use DO managed DB credentials; enable SSL if required |
| Git clone fails on Forge | Deploy key missing | Add `band-portal-forge-deploy.pub` to GitHub deploy keys |
