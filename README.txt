NetMonitor Hotspot module - Phase 1
=====================================
Files:
- api/hotspot.php
- hotspot/index.php
- assets/css/hotspot.css
- assets/js/hotspot.js

Requirements:
- Existing NetMonitor config/mikrotik.php
- Existing NetMonitor library/routeros_api.class.php
- MikroTik API enabled

Live RouterOS resources used:
- /ip/hotspot/user/print
- /ip/hotspot/user/add
- /ip/hotspot/user/set
- /ip/hotspot/user/remove
- /ip/hotspot/user/profile/print
- /ip/hotspot/active/print
- /ip/hotspot/active/remove

No database changes are required for this phase.
No automatic suspension or WhatsApp is included.

Integration:
Add a sidebar menu pointing to ../hotspot/ with activeMenu = hotspot.

Phase 3: live ONLINE/OFFLINE status per Hotspot user and online-user counter. No database changes.

Phase 4: User Detail modal with live session information. No database changes.

Phase 5: User Detail is enforced in renderer and click handler; cache-busted assets.

Phase 6: live Hotspot traffic summary/table and 10-second refresh. No database changes.

Phase 7: per-session traffic search, sorting (RX/TX/total/name), and peak-session indicator. No database changes.

Phase 8 fix: single-connection snapshot endpoint to prevent concurrent RouterOS API connection failures; fixed undefined profile comment parameter; consolidated traffic controls.

Phase 9: reverted dashboard refresh from parallel/snapshot connection to sequential RouterOS API calls with per-endpoint tolerance and clearer API error handling. No database changes.

Phase 9 Fix: corrected JavaScript syntax corruption in get(), hardened POST JSON parsing, cache-busted JS. This fixes the page-level failure that caused all counters to remain zero.

Phase 11: read-only Hotspot-to-Billing username matching in User Detail. No database/schema changes and no automatic billing action.

Phase 11 Fix: completed and wired the read-only Billing lookup in User Detail; explicit async lookup and error display. No database changes.
