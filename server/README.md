# Band Portal (`/server`)

Laravel cloud application for **https://band.edandtheshadowboys.com**.

This directory is the **Band Portal** in the monorepo:

- `/client/` — local X32/Ableton performance app
- `/server/` — this Laravel site (Forge deployment target)
- `/mockup/` — website design system source

## Stack (aligned with `/backend`)

| Component | Version |
|-----------|---------|
| PHP | `^8.4` (project runtime baseline; Forge and local Laravel apps) |
| Laravel | `^13.8` |

Minimum production dependencies: `laravel/framework`, `laravel/tinker` only.

## Quick start (local)

```bash
cd server
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

## Forge deployment

See **[docs/FORGE_SETUP.md](docs/FORGE_SETUP.md)** for:

- Web root: `/server/public`
- Environment variables
- SSL requirements
- SSH deploy key placement
- Deploy script notes

## Health checks

- `GET /` — Laravel welcome (baseline)
- `GET /up` — Laravel health endpoint
