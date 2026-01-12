# Changelog

## v2.9.4 (Current)
**Date:** 2026-01-13

### New Features (Musician UI)
*   **Welcome Overlay**: Added splash screen with rotating logo and "Restoring Mix..." feedback on login.
*   **Mix Restoration**: Automatically sends saved monitor mix (levels/mutes) to the console upon login.
*   **Save Mix Button**: Added "Save" icon to bottom navigation. Persists current mix to local device for future restoration.

### Bug Fixes
*   **Master Mute**: Fixed Master Fader/Mute not syncing with console state.
*   **Fader Control**: Fixed an issue where Musician App faders would not control the console because of a strict "OSC Status" check.
*   **Splash Crash**: Removed `backdrop-filter` blur to prevent crashes on iPad/Safari.

## v2.8.2
**Date:** 2026-01-13

### Stability Fixes
*   **Spontaneous Channel Reset**: Disabled the server's "Force Sync" on startup (`syncConfigToConsole`). The server will no longer overwrite the console's Scribble Strips (Channel Names/Colors) with default values on connection/restart. This prevents the "Reset to Default/Spare" issue during live use.

## v2.8.1
**Date:** 2026-01-13

### Bug Fixes
*   **Setlist Editor**: Fixed a React Rendering bug where Cue Name inputs would not update when switching songs, displaying stale or empty data (falling back to "preset"). Forced component recreation on song selection.

## v2.8.0
**Date:** 2026-01-13

### Critical Fixes
*   **Cue Sync**: Refactored Cue Sync to use **Index** instead of Name. This resolves the issue where duplicate cue names (e.g. multiple "Chorus" sections) were all highlighted simultaneously.
*   **Admin UI**: Updated Admin UI buttons to highlight based on unique Index.
*   **Server Stability**: Fixed a crash in `triggerPart` caused by accessing properties on undefined objects.
*   **Song Selection**: Fixed ID type mismatch (String vs Number) that prevented song selection in the Musician App.

## v2.5.1
**Date:** 2026-01-11

### Features
*   **Server-Side PDF Export**: "EXPORT PDF" now uses server-side conversion (LibreOffice) to generate high-quality PDFs from the user's Word Template. This preserves all vector graphics, fonts, and layout.
*   **UI Polish**: Renamed "PRINT" to "EXPORT PDF" and removed deprecated help buttons (moved to Manual).

## v2.5.0 (Current) - Setlist Management Milestone
**Date:** 2026-01-11

### New Features
#### Setlist Management
*   **Print Setlist**: Added "PRINT" button for stage-ready setlists (Large font, Logo, Notes, Numbered).
*   **Word Export**: Export setlists to `.docx` using custom templates (`templates/setlist_template.docx`).
*   **Notes Field**: Added persistent "Notes" field for each song.
*   **Smart Bus Naming**: Monitor buses now auto-label based on assignments (e.g., "Vox Ed").

#### UI Enhancements
*   **Assignment Panel**: Improved "Select Input" dropdown with smart filtering and stereo-link awareness.
*   **Chartless Mode**: Support for assigning channels without uploading a chart.
*   **Dynamic Filenames**: Exports and Downloads use `Setlist - [Name]` format.

### Backend
*   **New Endpoints**: `/api/setlist/export-docx`, `/api/setlist/update`.
*   **Dependencies**: Added `docxtemplater`, `pizzip`.

## v2.4.2 (Current)
*   **UI Refinement**: Master Panel buttons (Auto FX, EQ, DYN) are now vertically stacked with uniform styling.
*   **Version Bump**: Updated to 2.4.2.

## v2.4.1
*   **Master Combinator**: Implemented full control suite (Mix, Ratio, Gain, SBC) for the Master Bus Combinator (Slot 8).
*   **FX UI Refinement**:
    *   Removed duplicate controls for Dub Echo.
    *   Added formatting for 'Ratio' and 'dB' values.
    *   Aligned Slot 2 (Drum Verb) controls with documentation.

## v2.4.0
*   **Automated FX Rack**: Added "AUTO FX" button to Master Strip. One-click configuration of X32 FX Slots 1-4 & 8 with professional "Dub" settings (Vintage Room, Hall, Stereo Delay, Vocal Doubler, Master Combinator).
*   **Documentation**: Added "Pro Tips" section to User Manual with detailed recipes for Dub FX and Instrument Processing.

## v2.3.1
*   **UI Optimization**: Switch Gain/Pan to Full-Width Horizontal Sliders.
    *   **Horizontal Layout**: Maximized width for Gain and Pan controls for finer adjustments.
    *   **Button Row**: Consolidated 48V, Phase, Link, and Center into a unified toolbar row.
    *   **Responsive Presets**: Presets grid now expands to fill available width.
*   **Version Bump**: Updated to 2.3.1.

## v2.3.0
*   **UI Redesign**: Complete overhaul of the Gain/Preamp Overlay.
    *   **Channel Strip Layout**: Grouped Gain and Pan into parallel vertical sliders (similar to a console strip).
    *   **Visual Grouping**: Separated controls into card-like containers for Preamp/Pan and Presets.
    *   **Presets Grid**: Moved presets into a structured grid for easier access.
*   **Version Bump**: Updated to 2.3.0.

## v2.2.0
*   **Pan Control**: Added Pan slider and Center reset button to the Gain/Preamp Overlay.
*   **Stereo Linking**: Added Channel Link (∞) toggle to link/unlink adjacent mono channels (1-2, 3-4, etc.) into stereo pairs.
*   **Version Bump**: Updated to 2.2.0.

## v2.1.2
*   **Version Bump**: Validated UI enhancements for Gate/Comp value displays.
*   **Maintenance**: Confirmed stable operation of preset system.

## v2.1.1
*   **UI Enhancements**:
    *   Added numerical value displays (0.00 - 1.00) to Gate and Compressor sliders (Attack, Hold, Release, Ratio).
    *   Improved layout of Envelope controls.
*   **Version Bump**: Updated to 2.1.1.

## v2.1.0
*   **Instrument Presets**:
    *   Added "Quick Presets" menu to the Gain/Preamp Overlay.
    *   Includes 15+ professionally tuned presets for Kick, Snare, Toms, Bass, Guitars, Vocals, Horns, etc.
    *   Instantly applies EQ (Freq, Gain, Q, Type), Gate, and Compressor settings.
*   **Version Bump**: Updated to 2.1.0 (Minor version increment).

## v2.0.4
*   **Phase Invert Control**: Added Phase/Polarity Invert (Ø) toggle to the Gain/Preamp overlay.
*   **Backend**: Added OSC support for `/ch/{id}/preamp/invert`.
*   **Version Bump**: Updated to 2.0.4.

## v2.0.3
*   **Bi-Directional EQ Sync**:
    *   Console changes to Freq, Gain, Q, and Type now update the UI immediately.
    *   Added Master Channel sync (Fader + EQ).
    *   Implemented `/xremote` heartbeat to maintain console subscriptions.
*   **Fixes**:
    *   Fixed Q-Factor scaling to match X32 logarithmic scale (0.3 - 10.0).
    *   Fixed Master EQ Type selector bug.
    *   Fixed server startup errors (zombie process killing).
*   **Version Bump**: Updated to 2.0.3.

## v2.02
*   **Enhanced Gain/Preamp Overlay**: 
    *   Added Channel Name to the Header (e.g., "CH 1 | Kick - GAIN").
    *   Implemented bi-directional sync for Preamp Gain sliders.
    *   Implemented bi-directional sync for 48V Phantom Power toggle.
*   **Version Bump**: Updated project metadata to 2.0.2.

## v2.01
*   **Force UI Config Override**:
    *   Centralized Channel Names and Colors in `config.js` as the single source of truth.
    *   Implemented `syncConfigToConsole()` to force-overwrite X32 scribble strips on server connection.
    *   Updated `App.jsx` to dynamically render Group buttons based on backend config.
*   **Bug Fixes**:
    *   Fixed regression where Group/Mute buttons disappeared from the UI.
    *   Fixed `soloContext` redeclaration lint error in `index.js`.

## v2.0
*   **UI Restoration**:
    *   Restored legacy Dark Mode aesthetics for Channel Strips.
    *   Fixed Header positioning (Sticky/Fixed) and removed duplicate UI sections.
    *   Restored Bottom-Right System Monitor.
*   **Group Solo v2.0**:
    *   Implemented "Exclusive Solo" logic with +5dB boost for soloed group and -2dB dim for others.
    *   Added "S" button to Group Controls in UI.
*   **Infrastructure**:
    *   Initialized Git Repository.
    *   Created standard `.gitignore`.
