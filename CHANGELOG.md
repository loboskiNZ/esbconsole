# Changelog

## v2.1.0 (Current)
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
