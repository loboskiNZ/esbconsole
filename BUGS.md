# Bug Tracking Log

This document tracks bugs reported during development.
**Status Definitions:**
- **OPEN**: Bug reported, pending investigation/fix.
- **IN PROGRESS**: Currently being worked on.
- **FIXED**: Fix implemented and verified.
- **REGRESSION**: A previously fixed bug that has reappeared.

---

## Active Bugs

### Bug #007: DMX Lights Unresponsive
- **Status:** FIXED
- **Reported:** 2026-01-23
- **Description:** DMX lights are not responding when buttons are pressed in the Light Overlay.
- **Resolution:** Re-enabled `dmx_update` broadcasting in `index.js`, which was previously commented out. Added 50ms throttling.
- **Verified:** 2026-01-23 by User.

### Bug #006: Mobile Chart Upload (Photo/Library) Unresponsive
- **Status:** FIXED
- **Reported:** 2026-01-23
- **Description:** On mobile devices, selecting "Take Photo" or "Photo Library" when uploading a chart does nothing.
- **Resolution:** Added server-side HEIC conversion using `sips` in `index.js`. Now detects `.heic` files, converts them to JPEG, and then wraps them in PDF for the viewer.
- **Verified:** 2026-01-23 by User (Nathan Hill test).

### Bug #005: SharePoint Sign-In Box Disappears
- **Status:** IN PROGRESS (Monitoring)
- **Reported:** 2026-01-23
- **Description:** The SharePoint sign-in popup in the Files Overlay disappears immediately.
- **Update:** User reports it "seems to be connected somehow" (2026-01-23). Potentially a cached token issue or transient network glitch. verifying flow.

## Closed Bugs

### Bug #004: Setlist Export Name "Unspecified"
- **Status:** FIXED
- **Reported:** 2026-01-23
- **Description:** Setlist name missing in DOCX export.
- **Resolution:** Updated `index.js` to inject `title`, `name`, and `setlistName` tags into the docxtemplater context.
- **Verified:** 2026-01-23 by User.

### Bug #003: Master EQ Display Issues
- **Status:** FIXED
- **Reported:** 2026-01-23
- **Resolution:** Updated `App.jsx`Log frequency scaling, 6-band support, server-side fetch.
- **Verified:** 2026-01-23 by User.
