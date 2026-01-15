# Changelog

## [2.13.3] - 2026-01-15 (BETA START)
### Added
- **Chart Snippets (Beta)**:
    - Initialized beta phase for "Digital Scissors" feature.
    - Preparing to implement PDF rendering and cropping tools.

## [2.13.2] - 2026-01-15
### Added
- **Musician Proactive Cues**:
    - **Visual Count-in**: Implemented a beat-synced countdown (e.g. 8...7...6...) that appears exactly 2 bars before a cue ends.
    - **Rhythmic Pulse**: The "Next Cue" now pulses Amber in time with Ableton's heartbeat to provide a peripheral visual warning.
    - **Dynamic Calculation**: The count-in automatically adapts to the song's Time Signature (4/4, 6/8, etc.) received from Ableton.

## [2.13.1] - 2026-01-15
### Added
- **Browser Memory Optimization**:
    - **View Offloading**: The heavy 32-channel mixer console now completely unmounts when management overlays (Files, Setlist) are active, reclaiming significant memory.
    - **Meter Suspension**: Throttled high-frequency VU meter updates while overlays are open to reduce CPU pressure.
    - **View Persistence**: The app now remembers the active view across refreshes, avoiding mixer reloads.
- **Bugfixes**:
    - **Musician Chart Viewer**: Resolved a bug where the inline chart viewer in the setlist failed to resolve roles or upload new files correctly.

## [2.13.0] - 2026-01-15
### Added
- **Autonomous Shadow Learning (Final)**:
    - **Buffered Measurement Engine**: Cue durations are now collected in a memory buffer during performance, ensuring mathematical precision regardless of network latency.
    - **Silent Intelligence**: Implemented a "Duplicate Guard" that silently ignores redundant MIDI triggers (Ableton chatter) without resetting the measurement clock.
    - **Commit Workflow**: Added a visible "LEARNED CUES" status badge and a manual **[COMMIT ALL]** button to the Admin UI header.
    - **Final Cue Capture**: The server now automatically records the duration of the very last cue when LEARN MODE is toggled OFF.
- **UI Refinement**:
    - **Collapsible Assignments**: The "BAND ASSIGNMENTS" section can now be collapsed horizontally, giving the Cues and Setlist full screen width for complex shows.

## [2.12.2] - 2026-01-15
### Added
- **UI-Sync Engine**:
    - **Relative Reset**: Every cue/song trigger (MIDI or UI) now perfectly resets the counter to Bar 1.
    - **Metadata-Driven**: Added "Bars" input to the Admin UI to manually configure song part lengths (Y in 'Bar X of Y').
    - **SYNC Button**: Added a manual sync button to the Admin title for mid-song realignment.
- **Learn Mode (The Shadows)**:
    - **Auto-Registration**: Added a "LEARN" toggle. When ON, the server automatically creates placeholder songs/cues for any unknown MIDI or OSC triggers.
    - **Workflow**: Allows "The Shadows" to learn the song structure in real-time during rehearsal.

## [2.12.1] - 2026-01-14
### Improved
- **Musician Experience**:
    - **Header**: Added Musician's Name to the Setlist header for better personalization.
    - **Navigation**: Reordered menu to **Monitors | Setlist | Charts | Save | Logout** for better ergonomics.
    - **Persistence**: The app now remembers your active view (Monitors/Setlist/etc) and Group Mode toggle across page refreshes and logins.


## [2.12.0] - 2026-01-14
### Added
- **Ableton Auto-Setlist**:
    - **Automation**: Fully implemented MIDI Control for Setlist Navigation.
    - **Song Select**: Via standard MIDI Program Change messages.
    - **Cue Select**: Via MIDI CC 16 (Envelope) messages.
    - **Backwards Compatibility**: Supports both OSC and MIDI protocols.
- **Musician UI**:
    - **"Up Next" Display**: Added a context-aware header indicator showing the **Next Cue** (Green) or **Next Song** (Grey).
    - **Visual Feedback**: Active song/cue is highlighted in yellow when triggered by automation.

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
