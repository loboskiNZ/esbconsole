# Forge band portal — Git repository recovery

## Problem

Deploy hook for `band.edandtheshadowboys.com` (Forge site **3255691**, server **1209075**) creates releases that **lack `server/`** and fail with:

```text
ln: failed to create symbolic link '.../server/.env': No such file or directory
```

Deploy logs show:

```text
Cloning from git@github.com:loboskiNZ/bbos-website
```

The Band Portal requires the **esbconsole monorepo** (`loboskiNZ/esbconsole`, branch `main`), not Statamic/bbos-website.

While the wrong repository is configured, `current` stays on the last good release (e.g. `71987819`) and **does not pick up** onboarding fixes on `main`.

## Fix in Forge (required)

1. Open [Laravel Forge](https://forge.laravel.com) → Server **1209075** → Site **band.edandtheshadowboys.com**
2. **Meta** (or **Git Repository**)
3. Set:
   - **Repository:** `loboskiNZ/esbconsole`
   - **Branch:** `main`
   - **Web Directory:** `server/public` (unchanged)
4. **Deployments** → paste script from repo `server/deploy/forge-deploy.sh`
5. Save, then deploy

## Deploy from workstation

```bash
./server/deploy/remote-deploy.sh --push
./server/deploy/verify-active-release.sh 8750521
```

## Manual deploy (Forge Run Command)

If the hook still fails after fixing the repository, paste and run the contents of:

`server/deploy/forge-run-command-manual-deploy.sh`

in Forge → Site → **Commands** (runs as `forge`).

Then verify:

```bash
./server/deploy/verify-active-release.sh 8750521
```

## Expected verification output

- `active_release` changes from `71987819`
- `VERIFY OK`
- No `human_answer` / `OnboardingHumanCheck` in `StoreOnboardingRequest.php`
- `public_id` present in `OnboardingRegistrationService` user creation
