# Changelog

## v2.02 (Current)
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
