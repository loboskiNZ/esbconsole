# Changelog

## [2.15.0] - 2026-01-17
### Added
- **Email Charts Feature**: Musicians can now email themselves a PDF package of their charts for the current setlist directly from the Musician UI.
- **Microsoft Graph Email Integration**: Implemented backend logic to send emails using Azure App Permissions (Client Credentials Flow).
- **Chart Deduplication**: Email logic automatically filters out duplicate charts (same file assigned to multiple channels) and ignores placeholder files (`noChart.txt`) or empty files.

## [2.14.0] - 2026-01-17
### Added
- **SharePoint Chart Organization**: Admin feature to organize chart files into folders on SharePoint based on the active setlist.
- **Export Charts Button**: Added a dedicated button in the SharePoint Browser to trigger the organization process.
### Fixed
- **Folder Naming**: Fixed issue where setlist names with spaces caused duplicate folder creation by sanitizing names incorrectly.
- **Path Construction**: Fixed bug in `sharePointOrganizor.js` that caused "Resource not found" errors when `driveId` was missing.

## [2.13.3] - 2026-01-14
### Fixed
- **Admin Chart Loading**: Fixed sporadic network errors when loading charts in the Admin UI by adding a `timestamp` query parameter to bust cache and prevent race conditions.
- **Ableton Scene Bar Count**: Added logic to calculate and display the current bar count for Ableton Live scenes based on follow action length.

## [2.13.2] - 2026-01-13
### Fixed
- **Ableton MIDI**: Resolved issues with Ableton Live not sending MIDI messages to the controller by fixing MIDI port configuration and routing.

## [2.13.1] - 2026-01-11
### Added
- **SharePoint Setlist Folder Integration**: initial work on organizing files.
