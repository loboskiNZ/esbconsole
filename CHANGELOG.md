# Changelog

## [2.11.2] - 2026-01-14
- **Performance Optimizations**:
    - **MIDI Clock**: Drastically reduced CPU usage by only listening to the primary MIDI Input (IAC/Ableton) instead of all available ports.
    - **Meter Polling**: Relaxed polling rate from 50ms to 150ms to reduce event loop load alongside Ableton.
    - **Cleanup**: Removed redundant RTA data broadcasting.
- **Features**:
    - **Mute-Masking**: Muted channels now display as 0 (-inf) on all meters (Main/Overview/Monitor) for immediate visual feedback.

## [2.11.1] - 2026-01-14
- **Resilience & Backups**:
    - Added **Automated Startup Backup**: Creating a timestamped snapshot of `presets.json`, `scenes/`, and config files to `backups/` on every boot.
    - Added `backup_system.js` module.
    - Created `DATA_PERSISTENCE.md` guide for architecture reference.
- **Fixes**:
    - **Scene Loading**: Fixed crash when loading scenes with legacy numeric color IDs (e.g. `5` vs `#FF00FF`).
    - **Presets**: Restored original "Pro Tips" (descriptions) to `presets.json`.
    - **FX Injection**: Patched `thieves_alley.json` to restore correct FX Types (Vintage Room, Plate, Delay, Combinator).

## [2.11.0] - 2026-01-13
### Added
- **Dynamic Channel Presets**: 
    - Replaced hardcoded presets with a server-managed `presets.json` file.
    - Added API endpoints (`GET`, `POST`, `DELETE`) for managing presets.
    - Added **"+ SAVE"** button to the Quick Presets UI to capture current channel settings (EQ, Gate, Dynamics, Preamp).
    - Added Overwrite protection with a dropdown to easily update existing presets.
- **Migration**: Existing hardcoded presets are automatically migrated to `presets.json` on first startup.

## [2.10.1] - 2026-01-13
### Fixed
- **Channel Safes**: Fixed an issue where the Admin UI "Ghost State" (internal representation) was overwriting "Safe" channels during scene load. The server now correctly filters out safe channels from the loaded scene data before applying check-updates, ensuring both the console and the UI reflect the valid hardware state.
- **Scene Loading**: Names and Colors (Step 1 of restore) now respect Channel Safe settings.
- **Startup**: Fixed a duplicate network binding issue that prevented the server from receiving initial configuration (like Safe Bitmask) from the X32 console on startup.

### Added
- **Manual Safes UI**: Added a "SAFES 🛡️" button in the Admin Status Bar. This opens an overlay to manually toggle Safe status for Channels 1-32, which is persisted to `musicians.json`. This overrides/extends the hardware safe settings.

## [2.10.0] - 2026-01-08
### Added
- **Pan Restoration**: Fixed issue where Pan settings were not saved or restored with Scenes.
- **Mix Restoration**: Improved reliability of Mix Send restoration during scene changes.

### Changed
- **Setlist Manager**: Updates to use relative paths for SharePoint integration.
