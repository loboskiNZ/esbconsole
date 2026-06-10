# ESB Foundation (Laravel) — PH009

Parallel Laravel foundation slice for the Live Performance Orchestration System. Does not replace the existing Node/React application.

## Prerequisites

- PHP 8.2+
- Composer
- Docker (for PostgreSQL 16 + Valkey 7)

## Local setup

```bash
# Start PostgreSQL and Valkey
docker compose -f compose.foundation.yaml up -d

# Configure environment
cp .env.example .env
php artisan key:generate

# Migrate and seed roles + band + local demo playlist data
php artisan migrate --seed

# Create a Director login (prompts for credentials — not committed)
php artisan esb:create-director

# Run the app
php artisan serve
```

Open http://localhost:8000 — login → Shows → select active Show → Playlist.

## Local-only data

- `BandSeeder` creates the band scope root.
- `LocalDemoSeeder` creates clearly labelled local demo shows/songs (local/testing only).
- Director users are created via `esb:create-director`, not hard-coded in seeders.

## Tests

```bash
php artisan test
```

Tests use SQLite in-memory by default. For PostgreSQL parity:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=esb_dev DB_USERNAME=esb DB_PASSWORD=esb_secret php artisan test
```
