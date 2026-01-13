# Changelog

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
