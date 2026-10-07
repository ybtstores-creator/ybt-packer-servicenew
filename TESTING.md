# YBT System Testing Guide

Full-system verification setup: the **Android app runs on the MuMu emulator**, pointed at
a local WordPress. Two WordPress targets are supported and both are kept green:

| Target | Site URL | DB driver | Notes |
|--------|----------|-----------|-------|
| **XAMPP** (default for app testing) | `http://localhost/wordpress` | `xampp` | This checkout *is* the deployed plugin: `C:/xampp/htdocs/wordpress/wp-content/plugins/ybt-packer-servicenew`. DB `wordpress`, user `root`, empty password, prefix `wp_`. The emulator reaches it at `http://10.0.2.2/wordpress/`. |
| **Docker** | `http://localhost:8080` | `docker` | Stack `ybt-wp-docker` (wp + mariadb 10.4), site admin `admin` (password in the local credentials note). Regression target for the PHP-side suites. |

Pick one with env vars; nothing in the suites hard-codes a driver (see §3).

> **No credentials live in this file.** Local test logins and passwords are kept outside
> the plugin checkout in `C:/Users/HP/ybt-wp-docker/TESTING-credentials.local.md`, with the
> machine-readable form `C:/Users/HP/ybt-wp-docker/ybt-test-env.sh` — the harnesses in
> `docker/` source that file (override its location with `YBT_TEST_ENV=/path/to/env.sh`) and
> exit with code 2 rather than fall back to an embedded password. Nothing secret can be
> committed or shipped in a deploy zip. If the note is
> missing, reset the account instead of guessing —
> `wp user update <login> --user_pass='<new>' --role=administrator --allow-root`.

## 1. Environment

| Piece       | Detail                                                        |
|-------------|---------------------------------------------------------------|
| WordPress   | XAMPP 8.2.12 at `C:/xampp` (site `http://localhost/wordpress`, WP 7.1.2) **or** Docker stack `ybt-wp-docker` at `http://localhost:8080` |
| Plugin      | Deployed at `wp-content/plugins/ybt-packer-servicenew` (this checkout) |
| DB access   | XAMPP: `C:/xampp/mysql/bin/mysql.exe -u root -N wordpress -e "…"` (root has no local password) · Docker: `docker exec ybt-wp-docker-db-1 sh -c 'mariadb -uroot -p"$DOCKER_DB_ROOT_PASS" wordpress …'` — the value is in the local credentials note |
| Local HTTP  | The site serves plain HTTP locally; the mobile API's HTTPS requirement is enforced by the `ybt_require_https` filter (opt-out only for local dev) |
| Android app | `com.packtracker` debug APK from `PackTracker/` (Gradle 8.9, versionName 2.4 / versionCode 7) |
| Emulator    | MuMu 15, adb at `emulator-5554` (also reachable as `127.0.0.1:16384` / `127.0.0.1:5555`) |
| Host bridge | The app's debug `BASE_URL` is `http://10.0.2.2/wordpress/` — MuMu's alias for the host, so the emulator hits XAMPP directly. Docker instead needs `adb reverse tcp:8080 tcp:8080` and a loopback `BASE_URL`. |
| Cleartext   | `network_security_config.xml` permits cleartext **only** to `10.0.2.2`, `localhost`, `127.0.0.1`; the manifest sets `usesCleartextTraffic="false"` |

## 2. One-time setup

```bash
cd docker && docker compose up -d          # start WordPress + MySQL (Docker target only)
# complete WP install + activate plugin via wp-cli if it's a fresh volume
export MSYS_NO_PATHCONV=1                  # required on Git Bash so adb paths survive
adb connect 127.0.0.1:16384
adb reverse tcp:8080 tcp:8080              # Docker target only
```

Test accounts (passwords are in the local credentials note, not here): `ybtqa` — QA
administrator on the XAMPP install, created so the local site never needs the real admin's
password; `admin` — site admin on the Docker install; `alice` and `bob` — normal users,
created on demand by the API suite; `testuser` — a seeded front-end user whose current
password is unknown (reset it rather than guessing). The suite-created accounts satisfy the
plugin's password policy (uppercase + special character, minimum 8); accounts made with
`wp_create_user` bypass `validate_password()` and can therefore fail web login.

## 3. API test suite (85 checks, 12 sections)

<!-- design-preview harnesses -->
### Static design previews (no WordPress needed)

The redesigned web templates can be rendered locally without WordPress:

```bash
C:/xampp/php/php.exe docker/render-homepage-harness.php   # -> docker/preview/homepage.html
C:/xampp/php/php.exe docker/render-dashboard-harness.php  # -> docker/preview/user-dashboard.html
C:/xampp/php/php.exe docker/render-status-harness.php     # -> status-received / status-not-received / add-tracking / packing-request
C:/xampp/php/php.exe docker/render-auth-harness.php       # -> auth-login / auth-register / auth-reset(+step2)
C:/xampp/php/php.exe docker/render-userpages-harness.php  # -> user-home(+member) / user-profile / user-anonymous / app-download / user-chat
C:/xampp/php/php.exe docker/render-admin-harness.php      # -> admin-dashboard / admin-orders
```

All previews exercise the real template PHP (with stubbed wpdb/WP functions) and embed
`assets/css/ybt-consignment.css` — the single source of truth for the consignment-note
design system (tokens, fonts, panels, chips, buttons, alerts). It also carries a
"legacy bridge" section (auth-split, nav-tabs, chat skeleton, Bootstrap-modal chrome)
for the few remaining surfaces, and is enqueued once in `class-ybt-shortcodes.php`
(frontend) and `class-ybt-admin.php` (admin). The four former theme sheets —
`ybt-premium-theme.css`, `user-dashboard-enhanced.css`, `user-dashboard-modern.css`,
`chat-style.css` — were audited and deleted: every rule still used by a live page was
merged into the shared sheet; the rest was orphaned by the redesigns. Production pages
get `ybt-combined.min.css` (style.css + ybt-enhanced — rebuild with
`php tools/build-combined-css.php` after editing either source) plus
`ybt-consignment.css`.

All six operations admin pages are on the consignment-note system too: add-tracking
(intake counter), barcode scanner (scan window), bulk import (manifest upload),
advanced search (records room — its AJAX handler now lives in
`includes/ajax-advanced-search-legacy.php`, loaded with the admin class and backed by
a real query; the template previously registered it only on render, so the endpoint
always 400'd), process packing (packing tables, scoped over `admin-packing.css`), and
manage cartons (carton register). Every page's JS contract was preserved; the cartons
template re-attaches its `YBT.escapeHtml` helpers on ready because
`ybt-admin-combined.min.js` overwrites `window.YBT` after the inline script runs. Layout regressions and JS wiring
(parcel modal, copy-to-clipboard, packing selection) can be reviewed in a browser before
touching the live site. The userpages harness covers the remaining surfaces — public
landing (guest + member variants), profile docket, unclaimed-parcels register, app
download, and the support desk (with an inline jQuery shim + ajax stub so the chat
submit path can be exercised). The admin harness covers the depot console and the
tracking register (filter panel, manifest table, bulk selection via localStorage,
status buttons, modals) with the same shim approach.
```bash
# XAMPP target (what the app talks to). Source the local credentials env first, then
# point the admin pair at the QA admin — XAMPP's uid-1 `admin` has no known password.
source /c/Users/HP/ybt-wp-docker/ybt-test-env.sh
cd docker && YBT_API_BASE="http://localhost/wordpress/wp-admin/admin-ajax.php" \
  YBT_DB_DRIVER=xampp YBT_ADMIN_USER="$YBT_QA_ADMIN_USER" YBT_ADMIN_PASS="$YBT_QA_ADMIN_PASS" \
  bash run-api-tests.sh

# Docker target (regression)
cd docker && bash run-api-tests.sh
```

Both runs must end with `0 failed` (exit 0; the script exits with the failure count).
The XAMPP run measured `92 passed, 0 failed` on 2026-10-07. Overridable vars:
`YBT_API_BASE`, `YBT_DB_DRIVER` (`docker` | `xampp`), `YBT_ADMIN_USER`/`YBT_ADMIN_PASS`,
`YBT_ALICE_USER`/`YBT_ALICE_PASS`, `YBT_BOB_USER`/`YBT_BOB_PASS`, `XAMPP_MYSQL`,
`XAMPP_PHP`, `XAMPP_DB_NAME`, `XAMPP_DB_USER`/`XAMPP_DB_PASS`, `XAMPP_WP_ROOT`, `DOCKER_WP_ROOT`.

Covers: test-connection, register, login (user + admin) + wrong password, dashboard(+info),
tracking list/add/claim, duplicate-claim rejection, admin scan/orders/users/warehouse,
5 security-boundary checks, packing request, packed batches, payment-evidence upload,
chat (user↔admin both directions), FCM register/unregister, web bell (unread/get/mark +
bad nonce), parcel timeline (+IDOR), announcements (web + mobile, send/broadcast/dismiss,
non-admin blocked) and admin bulk add (newline / comma / array payloads + non-admin
blocked).

> **Note:** repeated failed logins trigger the plugin's brute-force lockout (IP block +
> account lock). If logins suddenly fail with "Invalid username or password" despite
> correct credentials, clear it with:
> ```bash
> docker exec docker-wp-1 php -r 'require "/var/www/html/wp-load.php"; delete_option("ybt_blocked_ips");'
> # or, on XAMPP:
> C:/xampp/php/php.exe -r 'require "C:/xampp/htdocs/wordpress/wp-load.php"; delete_option("ybt_blocked_ips");'
> ```

## 4. Android UI walk

Helpers: `docker/ui.sh` (`dump` / `ids` / `tapid` / `taptext` / `type` / `crash` / `shot`),
`docker/ui-login.sh <serial> <site-url> <user> <pass>` (fills the login form and signs in).

Build (JDK 21 — a Temurin **25** `JAVA_HOME` makes Gradle 8.9's embedded Kotlin throw
`IllegalArgumentException: 25.0.4.1` in `JavaVersion.parse`):

```bash
export JAVA_HOME="/c/Program Files/Eclipse Adoptium/jdk-21.0.12.101-hotspot"
export PATH="$JAVA_HOME/bin:$PATH"
cd PackTracker && ./gradlew assembleDebug
adb -s emulator-5554 install -r app/build/outputs/apk/debug/app-debug.apk
```

```bash
export MSYS_NO_PATHCONV=1
export SER=emulator-5554
bash docker/ui.sh $SER dump                  # every non-empty text on screen
bash docker/ui.sh $SER taptext Chat          # …walk each tab
bash docker/ui.sh $SER crash                 # must stay at 0 crash-buffer lines
bash docker/ui.sh $SER shot qa-01-home      # -> docker/qa-01-home.png
```

Gotchas: `adb shell input text` cannot send newlines — use
`adb shell input keyevent KEYCODE_ENTER` between lines; the MuMu text field is
multiline, so ENTER inserts a newline instead of submitting (chat has a real send button,
`btnSend`). Text fields are not cleared after a successful submit (Intake keeps its User
ID), so re-entering a value can append to the old one — leave and re-enter the tab to get
a clean form.

Walkthrough screenshots from the last full pass: `docker/qa-01-home.png` … `docker/qa-12-user-chat-admin-reply.png`.

Verified flows: admin dashboard/orders/order-detail (with *Last updated by* attribution)/
scan (camera + manual lookup → "Marked as Received")/users/profile; user dashboard
(stats + warehouse address + announcement + bank details cards)/parcels
(add + claim)/parcel detail/packing request/packed batches/chat/profile/logout;
role separation (no Scan tab for users).

Screen-text quirks: if `uiautomator dump` shows an empty screen, confirm the hierarchy
with `adb shell dumpsys activity top | grep -E "cardAnnouncement|cardBank|cardWarehouse"` —
the accessibility dump is unreliable inside the dashboard's scroll container.

#### UI redesign to match the web admin (v1.1, versionCode 2)

The app was restyled to mirror the web admin's current design language
(`assets/css/admin-modern.css`: Modern Indigo/Violet palette, dark slate chrome,
subtle-tinted status badges). Build: `cd android-app && ./gradlew assembleDebug` (the
Gradle wrapper jar/scripts were restored into the repo; `local.properties` points at the
local SDK).

- **Palette** (`values/colors.xml`): primary is now web indigo `#6366F1` (was emerald);
  the full web token map is in the file header (primary/primary-dark/primary-subtle,
  bg-main/card/sidebar/hover, text tiers, border, radius equivalents). Semantic badge
  pairs (info/warning/danger/success subtle+dark) were added; the old emerald remains as
  the success color and gold stays the secondary accent. Legacy `@color/primary` etc.
  are aliases, so all 30+ layouts kept compiling without mass edits.
- **Theme** (`values/themes.xml`): fuller Material 3 role mapping — primary/secondary/
  tertiary containers, error colors, surface variants, outline; status bar is dark slate
  with light icons; nav bar white.
- **Status chips** (`StatusChipHelper.kt`): now follow the web's exact status→semantic
  mapping (`ybt_get_status_badge_color()`): not_received=slate, received=cyan info,
  packing_in_progress=amber warning, packed=indigo primary, completed=emerald success —
  rendered as fully-rounded pills with the web's subtle-bg + dark-text badge style
  (replaces the old mixed solid/gold/green chips).
- **Components** (`values/styles.xml` + new drawables): `bg_header_dark` (dark slate
  `#0F172A` screen-header band with rounded bottom corners — the web's sidebar chrome)
  applied to all 5 activity toolbars and 4 dashboard/profile headers; `YbtInput` (web
  bg-input card inputs) replacing `bg_card`-as-input on login/register; `YbtButton`/
  `YbtButton.Outlined` (M3, 12dp radius, no ALL-CAPS) on login; `bg_stat_box` for
  dashboard stats; nav active state switched to indigo; incoming/outgoing chat bubbles
  are now neutral grey / indigo-subtle; profile/dashboard role text switched from green
  to slate-300; the Users-screen gold FAB is now indigo.
- Verified: `assembleDebug` builds clean (v1.1 / versionCode 2 APK produced). Visual
  re-walk of the flows above still pending on the MuMu emulator (was offline at the
  time); no behavior or endpoint changes were made, only presentation.

#### One brand: the Android app follows the WEB SITE (not vice versa)

Direction settled after an earlier experiment: **the web site keeps its original
Emerald/Royal-Gold storefront brand untouched** (a trial that repainted the web to the
admin's indigo was fully reverted — all CSS files, both min bundles, and the plugin
version back to 3.3; verified `emerald:#10b981` served and 37/37 API checks pass).
Instead, **the Android app was re-aimed at the web site's brand**:

- `colors.xml`: primary = site emerald `#10B981`, accent = royal gold `#D4AF37`, ink
  text `#0F1F17` (the consignment system's ink), kraft-paper neutral chip pair; M3 theme
  maps emerald primary / gold secondary+tertiary.
- `StatusChipHelper`: now mirrors `ybt-consignment.css .ybd-chip-*` exactly —
  not_received = kraft `#FBF8F1`/`#5C5543`, received = emerald-tint `#ECFDF5` + deep
  green text, packing_in_progress = gold-tint `#FAF3DC` + gold-deep text, packed = solid
  emerald + white, completed = deep green + white — with matching 1dp borders.
- Indigo leftovers from the admin-matching pass were removed: nav active state back to
  emerald, Users-screen FAB back to gold, outgoing chat bubble back to emerald tint.
- Kept from the earlier redesign: Material 3 component styling, card/radius/spacing
  tokens, `SectionCard` vertical orientation fix (dashboard overlap bug).
- **Kraft header pass:** screen headers now use the site's kraft-paper treatment —
  `#F1EAD9` band with the subtle vertical-hairline texture (generated `bg_kraft_tile`),
  hairline `#D9CCB0` edge, **Archivo Black** display titles in ink (font bundled with its
  OFL license in `android-app/FONT-LICENSE.txt`), kraft status bar with dark icons. All
  13 header surfaces covered: 4 dashboard/profile bands, 5 toolbar activities, and 8
  detail fragments that previously had no header at all (injected `ScreenHeader` +
  `DisplayTitle` wrapper). Pixel-verified on MuMu: kraft band + texture lines + hairline
  present, ink titles, zero regressions/crashes. Details in `BRAND-TOKENS.md` (kraft
  tokens row + new Typography and Header-anatomy sections).

**Brand contract:** the full web→Android token mapping (CSS variables ⇄ `@color/*`
resources, the `.ybd-chip-*` ⇄ `StatusChipHelper` scheme, sync rules and change log)
lives in `BRAND-TOKENS.md` at the repo root — treat it as the contract when touching
either palette. Enforce it with `python tools/check_brand_tokens.py` (exit 0 = in sync;
checks doc values against live CSS/colors.xml and chip bindings against
StatusChipHelper.kt, negative-tested). Writing it surfaced one real drift: the app's `received` chip used the
navbar-era `#D1FAE5` instead of the shared system's emerald-tint; corrected to
`brand_green_tint` `#ECFDF5` and pixel-verified on-device (`docker/ui-cap.sh`, the
binary-safe capture helper).

Verified on the MuMu emulator (v1.1/versionCode 2 APK, pixel-sampled from screenshots):
login/dashboard/orders/users/profile/chat all render emerald accents (`#10B981` exact),
zero indigo pixels, gold FAB `#D4AF37` restored, chips show the web's tint+solid scheme
(solid-emerald and tint-emerald chip regions both present, gold-tint packing chip at
`#FAF3DC`), headers dark-ink `#0F1F17`. Zero crashes across the walk; alice's password
was restored to its canonical value (local credentials note) after a manual web login check, and
37/37 API checks pass.

**Android UI-walk fixes (walked live on MuMu via `docker/ui.sh`):**

- Real overlap bug found and fixed: `SectionCard` never set `orientation`, so cards
  whose callers forgot `android:orientation="vertical"` (dashboard Operations,
  Recent Activity) rendered their title inline with/overlapped by content. The style
  now pins `vertical`; `StatBox` got explicit `vertical` + `center` too. All 14 usages
  benefit.
- Walk covered: login (fresh), admin dashboard/orders/users/scan/profile, chat list +
  admin chat thread. Zero crashes (`docker/ui.sh crash`); camera-permission dialog
  granted via `pm grant` mid-walk; ScanActivity is intentionally full-screen (no nav
  bar) and its empty preview is just the emulated camera having no feed.
- Emulator workflow notes: MuMu 6.5 lives at `D:/Program Files/Netease/MuMuPlayer`
  (launch via `MuMuManager.exe control -v 0 launch`, adb port 16384); app relaunch needs
  `adb shell monkey -p com.ybt.packing -c android.intent.category.LAUNCHER 1` because
  MainActivity is not exported; screenshot binary transfer on Windows corrupts via
  stdout, use `screencap -p /sdcard/s.png && base64 /sdcard/s.png` + `base64 -d`.

### PackTracker app redesign (v2.2 / versionCode 5) — presentation only

The deployed app codebase lives at the live plugin's `PackTracker/` directory
(`com.packtracker`). Its redesign mirrors the web site's look with **zero logic
changes** — no API, session, worker, or model code touched:

- Kraft-paper canvas: light-mode window/canvas is now the site's kraft
  `#F1EAD9` with the vertical-hairline texture (`bg_kraft_tile`, `bg_kraft_canvas`);
  night mode keeps its dark canvas via `drawable-night` + `values-night` kraft
  role aliases (kraft_line/kraft_deep/ink/header_subtitle map to slate).
- Archivo Black bundled (`res/font`, OFL license in `PackTracker/FONT-LICENSE.txt`):
  toolbar titles (`Widget.PackTracker.Toolbar` -> `ToolbarTitle` appearance), and the
  "YBT PACKING" wordmark on login + dashboard via `DisplayFace.OnDark` (fixed white
  on the fixed emerald banner).
- Status chips now render like the web storefront (`.ybd-chip-*`): tinted bg + dark
  text for pending states, solid fill + white for packed/completed, hairline border —
  via a new presentation-only `utils/StatusChipStyle.kt`. The four visual binding
  sites (TrackingFragment colored text, ParcelDetailSheet colored text,
  AdminOrdersFragment solid fill + white, AdminPackingFragment chip text) were swapped
  to it; AdminPacking's status dot fill and all data/logic stay as they were.
- Verified on MuMu (light mode): kraft canvas `#F1EAD9` replaces the old grey,
  all four chip styles pixel-present on the orders list, night mode unchanged,
  0 crashes; admin login exercised end-to-end.

## 5. Admin shell (full-screen console)

The admin pages render **without the WordPress chrome** — no top admin bar, no left
sidebar menu, no footer (`includes/class-ybt-admin-shell.php`, active only on
`ybt-*` screens; posts/plugins/etc. keep the normal WP shell). In its place a slim
brand header provides page-group navigation (Console / Intake / Register / Scanner /
Packing / Cartons / Import / Search / Support / Settings) — the current group marked
by a gold underline tab fused to the header's bottom rule, consignment-note style —
plus a **quick search box** (tracking no. or username) that submits straight to the
tracking register's `?search=` filter — with **live typeahead**: from 2 characters it
debounced-queries `ybt_shell_lookup` and offers up to 8 matching parcels (tracking,
owner, status); clicking one — or ArrowDown/ArrowUp + Enter — jumps straight to that
parcel's Full-Edit detail page, while Enter on an unselected query keeps the register
filter fallback. Closes on Escape/outside click; full combobox ARIA. Focus the box
from any admin page with **`/`** or **Ctrl/Cmd+K** (visible `/` chip on the field;
`/` is ignored while typing in inputs, Escape blurs).
back to `wp-admin/index.php`, where the normal WP chrome is intact. The nav is a
single row that scrolls sideways rather than wrapping; when the ten groups don't
fit, a gold overflow toggle (with group count) opens a dropdown listing all ten —
closes on outside click or Escape, and always shows the active group (auto-scrolled
into view). The scroller's edges show 18px ink-fade masks whenever groups are hidden
off-screen on either side (state follows scroll position), so hidden groups are
always visually hinted. Below 780px the header reflows to two rows:
brand/search/user on top, nav + toggle beneath.
WP chrome is intact.

The shell also neutralizes legacy offset hacks in `ybt-admin-combined.min.css`
(negative `margin-top` pulls that tucked pages under the old admin bar) — that is
why page titles sit below the sticky shell header instead of underneath it.

## 6. Performance hardening: Process Packing page

The legacy admin packing interface "took forever" because it grew unbounded with the
table: `get_filtered_requests()` ran `SELECT *` with **no LIMIT**, and the template
rendered every `packing_in_progress` row (~3.8KB of markup each) with all group bodies
in the DOM (collapsed groups included) plus eager `<img>` thumbnails. Measured with 622
packing rows (22 real + 600 seeded `PERF-%`): **2.4MB of HTML, 630 `<tr>`, 30.8k lines**.

Fixes (both deployed to the live copy):

- `includes/class-ybt-packing.php` — `get_filtered_requests()` now selects an explicit
  column list (drops `shipping_address`, `admin_notes`, payment/phone/audit columns),
  applies `LIMIT 200` (filterable via `ybt_packing_requests_limit`), and runs a companion
  `SELECT COUNT(*)` so the UI can disclose truncation. Returns
  `{items, total, limit, truncated}` instead of a bare list.
- `templates/admin-process-packing.php` — renders a gold truncation notice when cut
  ("Showing the 200 most recent of N…" with a "How to load more" button that scrolls to
  the filters); thumbnails get `loading="lazy" decoding="async"`; panel header shows the
  **true total** so the count doesn't silently cap at 200.

Measured after (same 622-row dataset): **813KB HTML, 200 rows** — a 66% payload cut with
a hard ceiling; page size now stays flat as the table grows. Unfiltered latency on local
Docker stayed ~0.25–0.3s before and after (local disk hides the legacy pain — the win is
the bounded payload/DOM). Verified: truncation notice appears at 622→200, hides when a
user filter is under the limit (alice: 1 row, no notice); browser console clean;
real-data page back to 207KB/22 rows with no notice; full 37-check API suite passes.
Perf seed/cleanup: `INSERT ... SELECT` with `PERF-%` tracking numbers, then
`DELETE FROM wp_ybt_tracking WHERE tracking_number LIKE 'PERF-%'`.

### Admin-wide unbounded-query audit

Every admin page's data source was audited for the same pattern. Already bounded (no
change needed): manage-tracking (25/page + count), manage-cartons (per_page/OFFSET +
count), activity-log (50/page ×2 lists), security (LIMIT 50), dashboard (cached LIMIT
10), invoices AJAX (20/page + count), warehouse/payment-settings (LIMIT 1), and all
user-facing list pages (paginated). Four offenders found and fixed (measured with a
10,000-row `PERF-%` seed for democustomer):

- **User Summaries** (`templates/admin-user-summary.php`) — the worst: `SELECT *` over
  the *entire* tracking table, rendered in full. At 10k parcels: **12.06MB HTML**.
  Now: per-user/status aggregate (`GROUP BY display_name, status`, counts scale with
  users × statuses, not rows), explicit-column item list capped at 500 (2000 when a
  single user is filtered), SQL-side `CASE` display-name mapping (keeps Anonymous
  grouping exact), accurate totals from the aggregate, and a truncation notice.
  After: **706KB**, notice reads "500 most recent of 10,091 parcels".
- **Admin Messages** (`templates/admin-messages.php`) — unbounded `SELECT *` over
  `ybt_admin_messages` (grows forever with every broadcast). Now: explicit columns +
  newest 100 + companion COUNT; header shows "(100 of N — showing newest)".
- **Payment Proofs** (`templates/admin-payment-evidence.php`) — unbounded user list +
  unbounded per-user proof queries + one cartons query *per proof* (N+1). Now: newest
  50 users, newest 20 proofs each, cartons fetched for all of a user's proofs in ONE
  query; carton thumbs get `loading="lazy"`. Carton toggle DOM ids now keyed
  `user-batch` (old bare `evidence->id` risked cross-user collisions).
- **Chat history** (`includes/chat.php`) — both `ybt_get_user_chat` (admin console,
  re-fetched on every send) and `ybt_get_chat_messages` (user support widget) returned
  the whole conversation forever. Now both return the latest 500 messages, re-sorted
  ascending (filterable via `ybt_chat_history_limit`). Verified: 1000 seeded messages →
  exactly 500 returned, oldest-at-top order intact.

### Process Packing: lazy group rows (expand-on-demand)

After the bounded query (200-row cap), the page still rendered every shown row into the
initial DOM even though all groups start collapsed. Now the initial render ships **group
headers only** — a single cheap `COUNT ... GROUP BY username` (`YBT_Packing::-
get_group_counts`) — and each group's rows are fetched via AJAX the first time the group
is expanded:

- `ybt_get_packing_group_rows` AJAX handler (`includes/tracking.php`): `ybt_admin_nonce`
  + capability check, per-group `get_filtered_requests($user, $search, 200)` (filterable
  via `ybt_packing_group_rows_limit`), renders rows through the shared component
  `templates/components/admin-packing-item-rows.php` so AJAX-served markup can never
  drift from the canonical rows. Returns `{html, shown, total, truncated}`.
- `admin-packing.js` group toggle: on first expand inserts the table skeleton, injects
  the rows, appends a "Showing 200 of N" row when the group itself was truncated, and
  dispatches `ybt_group_rows_loaded` so the persisted-selection restore (which no longer
  finds checkboxes at page load) re-runs on freshly inserted rows. Failures reset the
  loaded flag so a later expand retries. Collapse/re-expand never re-fetches.
- Header cap: groups are themselves bounded (sublinear cap, filterable via
  `ybt_packing_max_visible_groups`) with a "most active of N users" truncation notice.
- `get_filtered_requests($user, $search, 0)` fetches metadata only (no item query);
  `get_group_counts()` powers the headers. Plugin version bumped 3.2 → 3.3 to
  cache-bust the changed `admin-packing.js` (same enqueue URL).

Measured: initial page ~116KB with **zero item rows in the DOM** (was 207KB/22 rows on
real data; the 600-row seed that previously produced 813KB now changes nothing —
payload scales with group count, not queue size). Verified in browser: expand democustomer
with 1002 queued items → exactly 200 rows injected + truncation notice; selection restore
fires on loaded rows; collapse/re-expand doesn't re-fetch; 37/37 API checks still pass.
Perf seed: `INSERT ... SELECT 'PERF-'` rows (status `packing_in_progress`), cleanup
`DELETE ... WHERE tracking_number LIKE 'PERF-%'`.

### Shared file-deletion helper (no more orphaned uploads)

The disk-space audit found deletion coverage was inconsistent: single-image deletes
unlinked files, but **bulk delete, all_parcels/all_chat wipes, dashboard-message delete,
and popup-image replace/remove leaked their files forever** — and each delete path had
its own URL→path logic (one basename-only, one `wp_make_link_relative` that breaks on
subdirectory installs). Everything now goes through one primitive in
`YBT_File_Handler`:

- `parse_images_field($raw)` — parses the tracking `images` / carton `carton_image`
  columns regardless of format: comma-separated URLs, JSON arrays, or the legacy fused
  hybrid (`["img1.jpg"],http://…`) found in live data.
- `url_to_local_path($ref)` — resolves full uploads URLs, `/wp-content/uploads/…`
  fragments, dated subdirs, and bare filenames (searched in uploads root + YBT storage
  subdirs, filterable via `ybt_image_storage_dirs`). Remote URLs and anything containing
  `..` return false.
- `delete_urls($refs)` — the only delete primitive: `realpath` containment check
  (refuses anything outside the uploads dir), returns `{deleted, freed_bytes}`.

Converted call sites: admin-ajax `ybt_delete_image` + REST `/tracking/{id}/image`
(basename-collision bug fixed), `data_delete` tracking/chat/cartons + `all_parcels` /
`all_chat` (now unlink files before rows vanish), dashboard-message delete, and
popup-image replace/remove. **Bulk delete and the AI prune have since been moved onto
the unified engine** (see below). Verified live: bulk delete of two seeded parcels
removed both files (full URL with dated subdir + bare filename) while ignoring a remote
URL and leaving unrelated uploads untouched; single-image delete with two same-named
files in different month folders removed only the referenced one and preserved the rest
of the legacy fused images string; 37/37 API checks pass.

#### Uploads cleanup defused + unified cleanup engine in YBT_File_Handler

The `/data/preview` + `/data/delete` `uploads` case previously enumerated the **entire
uploads directory** and — with `older_than_days=0` — deleted every file in it (Media
Library and all other plugins' files included). Rebuilt, then unified:

- The whole inventory + accounting now lives in `YBT_File_Handler`
  (`class-ybt-file-handler.php`) as a plan/execute engine, and `collect_ybt_file_refs()`
  was **removed** from `admin-rest-api.php`:
  - `$file_columns` — the single source of truth for which columns hold file refs
    (`ybt_tracking.images`, `ybt_cartons.carton_image`, `ybt_chat.attachment`,
    `ybt_admin_messages.image_url`).
  - `$cleanup_type_map` — cleanup type → `[table, date expression]`; the age column
    matches the SQL DELETE wheres (`tracking` ages by `date_added`, `uploads`/`all_*`
    by `COALESCE(date_completed, date_received, date_added)` / `date_created`), and
    `uploads`/`all_parcels` span multiple tables.
  - `get_cleanup_rows($type, $f)` — one bounded SELECT per table (cap filter
    `ybt_cleanup_rows_cap`, default 50000) with status/user/age filters applied in PHP.
  - `plan_data_cleanup($type, $f)` — the authoritative dry run: parses refs (shared
    `parse_images_field`), resolves each once via `url_to_local_path`, dedupes,
    verifies existence + size, and returns `{files:[{ref,path,size,owner}], rows,
    count, size_bytes}`.
  - `execute_data_cleanup($plan, $clear_fields)` — deletes via the containment-checked
    `delete_urls()` primitive, then blanks file columns only on rows whose files were
    actually local/deleted. Pass `$clear_fields = false` when the rows themselves are
    about to be deleted (tracking/chat/cartons/all_parcels/all_chat).
  - `run_data_cleanup($type, $f, $clear_fields)` — plan + execute convenience wrapper.
  - `plan_from_rows()` (private core) + `plan_tracking_rows($rows)` — plan from an
    explicit row set; normalizes ARRAY_A arrays or row objects (id + images).
  - `get_tracking_rows_for_cleanup($f)` — row selectors for the extended flows:
    `ids` (chunked IN() queries, bulk delete) or `months` (COALESCE completion date
    older than N months, AI prune); no filter = every row carrying images.
  - `plan_tracking_images($f)` — one planner for all tracking-image flows with
    `rows` / `ids` / `months` selectors; returns the plan plus `rows_matched` and
    `refs_found` (references parsed, resolvable or not).

**Bulk delete + AI prune migrated onto the engine (single accounting implementation):**

- `ybt_bulk_delete_items_handler` (`bulk-operations.php`) now plans via
  `plan_tracking_images(array('rows' => $deleted_items))` and executes with
  `$clear_fields = false` (rows are deleted anyway). The success message now reports
  freed space through the engine: `"N items deleted. Freed ~X KB of images."` The audit
  logging, activity log, and response shape are unchanged (the UI alerts `res.data`).
- `YBT_AI_Operator::prune_images_older_than_months($months, $dry_run)` now plans via
  `plan_tracking_images(array('months' => $months))` and executes via
  `execute_data_cleanup($plan, true)` when not a dry run. Its return contract is
  identical — `total_records_affected` = rows carrying images, `total_images_found` =
  refs parsed (resolvable or not), `deleted_files_count`/`freed_mb` from the engine —
  so `ai_prune` REST and `run_full_diagnostics()` keep working unchanged.
- Verified live: 5-month prune dry-run reported `{records:1, images:1, deleted:0}`;
  the real run deleted exactly the 200-day-old fixture's file, cleared its `images`
  field, kept the row, and left the fresh parcel untouched. Bulk delete of two rows
  (full-URL ref + `ybt-cartons/` subdirectory ref) removed both referenced files,
  replied `"2 items deleted. Freed ~4.9 KB of images."`, and left the unreferenced
  source copy of a same-named file alone. 37/37 API checks pass.
- `/data/preview` `uploads` = `plan_data_cleanup()` numbers verbatim (count, `size_kb`,
  sample with per-file `size_bytes` + owner, `scope` explanation) and now also refuses
  `older_than_days=0` with `age_required` — previously only delete did.
- `/data/delete` `uploads` = the **same plan executed** (`deleted`/`freed_kb` come from
  `execute_data_cleanup`), still with the mandatory age filter; reference columns are
  cleared on affected rows.
- `tracking` / `chat` / `cartons` previews now also report `file_count` + `size_kb`
  from the same plans, so every file-bearing type previews sizes; their deletes fold
  `freed_kb` from the identical engine. Preview and delete share one code path for all
  data types — the numbers cannot drift.
- `all_parcels` deletes tracking + cartons files before truncating both tables;
  `all_chat` does the same for chat.
- Verified live: seeded an old parcel (400 days, 5000-byte file) + new parcel (7000
  bytes); `uploads` preview returned exactly `{count:1, size_kb:4.9, owner:
  ybt_tracking#<id>}`, delete returned `{deleted:1, freed_kb:4.9}` — identical numbers —
  the new file and row survived, the old row's `images` field was cleared, and
  `older_than_days=0` was refused on preview AND delete. Tracking preview aggregates
  `file_count`/`size_kb` for filtered subsets; chat/cartons previews report their file
  sizes too. 37/37 API checks pass.
- The Data Cleanup page's "Delete Old Uploads" card was relabeled "Delete Old YBT
  Uploads" to make the scoped behavior explicit.

#### Orphaned-file scan & purge (files no YBT record references)

New "Orphaned Files" tool on the Data Cleanup page — the inverse of the cleanup engine:
it finds files living in YBT upload locations that **no YBT record references** (leftover
test uploads, files whose rows were deleted before the shared helper existed), previews
them with sizes, and purges them behind the same previewed/age-filtered/confirm flow as
every other cleanup type.

- Engine (`YBT_File_Handler`): `get_orphan_scan_dirs()` (uploads root + `ybt-cartons`,
  extendable via `ybt_orphan_scan_dirs`), `build_referenced_ref_set()` (every ref in the
  four file columns, normalized + basename sets, plus Media-Library attachment
  basenames from `_wp_attached_file`/guid — only entries whose files exist),
  `plan_orphan_cleanup()` (recursive scan with containment check, dotfile skip,
  referenced/attachment exclusion, **mandatory mtime age filter**, per-file sizes), and
  `execute_orphan_cleanup()` (deletes through the containment-checked `delete_urls()`).
- REST: `POST /data/preview` and `/data/delete` with `type: "orphans"`. The age filter is
  **mandatory on both** (`age_required` on `older_than_days=0`) — a fresh orphan may be
  mid-upload or referenced by a record type the scan doesn't know. Preview returns
  `count`/`size_kb`/`sample` plus `scanned` and `excluded` counts and the `scope`
  explanation; delete returns `deleted`/`freed_kb` from the same plan.
- UI: "🧹 Orphaned Files" card with its own age selector (default 60+ days) and
  Preview/Delete buttons wired through the existing `dcPreview`/`dcDelete` helpers.
- Verified live: with one genuine pre-existing orphan (July image, 104.8KB) + fixtures
  (backdated 4.4KB orphan, fresh 3.2KB orphan, 2.5KB file referenced by a seeded parcel)
  the preview listed exactly the two old orphans — `excluded: 12` covered the referenced
  file, and `test_compress.jpg` (13 days old, no DB ref) was age-skipped, confirming the
  exclusion vs age distinction. `days=0` was refused on preview and delete. For the
  execution test the genuine orphan was temporarily stashed inside the project tree and
  restored bit-identical afterwards; the purge deleted only `orphan-old.jpg`
  (`deleted:1, freed_kb:4.3`, matching preview's 4.3), left the fresh and referenced
  files in place, and 37/37 API checks pass.

#### Load test: the unified engine adds no measurable latency

Measured after the engine, bulk/prune migration, and orphan tool were all live
(`http://localhost:8080`, N=20 sequential samples per endpoint, plus 10-way concurrency
bursts and an in-container CLI micro-benchmark on the real DB + uploads):

- Pages: Process Packing median 0.207s (119,244 bytes — matches the historical
  ~116–119KB zero-rows baseline), Data Cleanup 0.195s (113,183 bytes). Lazy group-rows
  AJAX for the heaviest user: 0.137s median.
- Engine endpoints vs a no-engine control (`/ai/report`, 0.119s median):
  `preview uploads(150d)` 0.120s — indistinguishable; `preview tracking` 0.147s,
  `preview cartons` 0.131s, `data_stats` 0.120s. The heaviest engine call,
  `preview orphans(60d)` (full directory scan + 5 exclusion queries), measured 0.170s —
  a ~50ms delta that matches the micro-benchmark's 34ms pure scan cost.
- Concurrency: 50 requests / 10 parallel on Process Packing → 50/50 HTTP 200, mean
  0.289s, max 0.392s; 30 / 10 on the orphans preview → 30/30 HTTP 200, mean 0.246s,
  max 0.360s. No timeouts or failures.
- CLI micro-benchmark (pure engine cost, real data): DB row selection 0.1ms;
  `plan_data_cleanup(tracking)` 19.2ms/op (dominated by per-ref filesystem resolution
  for ~100 rows with distinct bare filenames), `plan_orphan_cleanup` 34.1ms/op,
  age-filtered `uploads(150d)` 0.53ms, empty-result `months=5` prune plan 0.25ms.

Conclusion: the engine costs 0.3–34ms depending on type (file resolution dominates,
DB queries are trivial), which is ≤ ~2% of a typical ~200ms admin request and invisible
in the burst tests. Page sizes and medians match the pre-unification baseline.

## 7. Bugs found & fixed during testing

- **Bulk add collapsed every number into one row (full-system pass, 2026-10-05).** The
  admin Intake tab says "Tracking Numbers (one per line)", and the app posts the textarea
  verbatim as one `tracking_numbers` field — but
  `YBT_API_Admin_Handler::add_tracking_bulk()` split on commas only
  (`explode(',', …)`). Worse, `sanitize_text_field()` rewrites whitespace runs (newlines
  included) to a single space *before* any splitting, so two lines arrived as
  `NUMBER_A NUMBER_B` and were stored as **one** tracking number. Fix: a new
  `YBT_API_Admin_Handler::normalize_tracking_numbers()` reads the raw `$_POST` value
  (never pre-sanitized) and splits on `/[\r\n,;|]+/`, accepting a PHP array too — which is
  what the web admin already posts as `tracking_numbers[]`. The REST twin
  (`includes/admin-rest-api.php`) delegates to the same helper. Blank lines and duplicates
  collapse. Covered by suite section 12 (7 checks) and verified on MuMu: two rows, ids
  112/113, both owned by alice.
- PackTracker (v2.2): tapping the **Dashboard** tab after opening Orders (via the
  dashboard's Admin Orders card, or after any tab switch) silently did nothing —
  the app stayed on Admin Orders and the nav bar kept Orders highlighted.
  Instrumentation proved the click fired with the right item id
  (`tab click title=Dashboard id=dashboardFragment`) yet
  `NavigationUI.onNavDestinationSelected` still resolved it to Admin Orders; a
  direct `navController.navigate(R.id.dashboardFragment)` worked. Fix:
  bottom-nav selection is now wired manually in `MainActivity` with plain
  top-level NavOptions (launchSingleTop + popUpTo start destination, non-inclusive)
  instead of NavigationUI's default selection handler. Verified on MuMu: all 5
  admin tabs navigate correctly in any order, Back returns through tabs to the
  start tab, 0 crashes.
- `ybt_mobile_admin_update_warehouse` was registered admin-only → app requests returned
  dead `0`; now registered `nopriv` (token-authenticated like the rest of the mobile API).
- `StatusChipHelper` crashed the Orders list for any status except "received" (passed
  literal colors into `getColor()`); now uses real color resources.
- Activation capability check blocked `wp-cli` table creation; handled during setup.
- Admin chat page: `user_nonce` was only set by a `typeof ybt_admin === 'undefined'
  guard, and the localized `ybt_admin` was re-declared in the footer — overwriting the
  template's earlier merge, so `ybt_admin.user_nonce` was undefined and every chat
  load/send 403'd. Fixed by re-merging nonces inside the page's `document.ready`
  (which runs after the footer localization).

### Brand name corrected to "YBT Packing Service" (2026-09-25)

The proper product name is **YBT PACKING SERVICE**, not "YBT Packer". All user-visible
display strings were corrected across both platforms (56 replacements, 26 files):

- **Web**: page titles ("YBT Packing Service — Dashboard" etc. in `class-ybt-admin.php`),
  the admin-shell wordmark ("PACKING SERVICE"), auth pages, meta descriptions, email
  bodies, app-download page, report headers, and the plugin `Name:` header.
- **PackTracker app (v2.3 / versionCode 6)**: launcher name (`app_name` →
  "YBT Packing Service"), "New to YBT Packing Service?" (login), chat header, and the
  dashboard toolbar label (nav graph `label="Dashboard"` instead of the old app-name
  fallback). Wordmarks already read "YBT PACKING".
- **In-repo android-app**: `app_name` and login wordmark corrected.

Deliberately **not** changed (functional, not display): the `ybt-packer` text domain and
URL slugs (renaming them breaks WordPress menu routing), the live app's package
(`com.packtracker`; the in-repo app's package was later renamed `com.ybt.packer` ->
`com.ybt.packing` as its own task), the `'YBT PACKER'` User-Agent detection string in
`ybt-offline-sync.php` (it matches the app's actual UA header), and the "Packer
Signature" field label on the packing print sheet. Verified: all 27 edited PHP files pass
`php -l`; APK reinstalled on MuMu, launcher label confirmed, 0 crashes.

Browser sweep of the live site (login, register, dashboard, admin shell, app-download
preview) caught two gaps the file-level grep could not see, both fixed:

- `templates/components/dashboard-navbar-modern.php` had been corrected in the repo but
  missed from the deploy list — the live navbar still rendered "YBT PACKER SERVICE".
  Deployed and re-verified in-browser.
- The dashboard notice board renders a **database-stored** announcement
  (`wp_ybt_admin_messages`), not template text: the seeded row still carried the old name,
  and `ybt_cache()` stores it under **hashed** transient keys (`wp_hash($key)`), so the
  stale entry can't be found by cache-key name. Row updated in `wp_ybt_admin_messages`
  and the two hashed transients (`_transient_ybt_v2_dashboard_*` / `_content_*` containing
  the old text) deleted; re-verified live. Remember this for any future copy edit to
  seeded/DB-stored content: fix the row *and* flush by content match, not key name.
- All `docker/preview/*.html` harness outputs regenerated from the corrected templates
  (grep-clean); app-download preview re-rendered visually.

### App-download page metadata refreshed (2026-09-25)

The download page was advertising a **dead link** (`ybt-packer-v1.0.apk` — the file never
existed in either downloads dir) plus v1.0/Android 5.0 badges. Updated
`templates/app-download.php` and wired the real artifact:

- `downloads/ybt-packing-service-v2.3.apk` (repo + live plugin): the current PackTracker
  **v2.3 / versionCode 6** debug build (the one emulator-verified), 28.9 MB,
  SHA-256 `15a9848092edbdbe0e473c6b94c3a2c6d714dfc2f4aa4d9d19d68548503bf76f`.
- Page now shows: link + button v2.3, Version 2.3 badge, **Android 7.0+** (minSdk 24),
  file size, install doc filename, and the SHA-256 in the "official APK guarantee" note
  (package com.packtracker — same binary the site's admin app runs).
- Verified end-to-end: HTTP 200 from the live URL, full download hash matches byte-for-byte.
- Note: PackTracker has **no release signing config** (no keystore in the project), so the
  publishable artifact is the debug-signed build; a proper release build needs a keystore
  created and a `signingConfigs` block added first. The orphaned
  `downloads/app-production-release.apk` (old v2.1 under a third id, `com.ybt.packerservice`,
  label "YBT Packer") was **deleted from both downloads dirs** (2026-09-25) after a final
  reference check confirmed nothing links to it — the old URL now 404s, `ybt-packing-service-v2.3.apk`
  still serves (HTTP 206 on range request).

### Database-wide old-name sweep (2026-09-25)

Column-level scan of all 28 tables (wp_* + every `wp_ybt_*`, real column names from
DESCRIBE) for 'Packer' display-name residue, after the notice-board fix. Result:
plugin tables, meta, users, terms, comments, chat/notifications/logs were already **zero**.
Two finds, both fixed:

- The active theme's `style.css` header ("Theme Name: YBT Packer Theme" — visible in
  Appearance) corrected to "YBT Packing Service Theme"; `current_theme` option cache
  deleted so WP recomputes it from the header. Theme **folder** `ybt-theme` untouched
  (functional: `stylesheet`/`template` options point at it).
- `_site_transient_update_plugins` held the old plugin folder path in its serialized
  update-check cache — functional, not display; deleted and let WP regenerate.

Deliberately preserved (functional identifiers, breaking if changed): `active_plugins`/
`uninstall_plugins`/`recently_activated` values carrying the `ybt-packer-servicenew` (and
legacy `ybt packer/`) plugin folder paths, `current_theme` value itself, and the
lowercase `ybt-packer` text domain/slug strings. Final sweep: 0 display hits across all
scanned table.column sets including hashed transients; dashboard re-verified in browser.

### Force-close audit (2026-09-25)

Static crash-pattern audit of both Android apps (JSON parsing, `!!`, indexing,
`first()`/`[0]`, number parsing, permission flows, coroutine scope/catch shapes),
followed by live probes on MuMu.

**Confirmed and fixed (in-repo `com.ybt.packing` app, v1.2 build):** a systemic
CancellationException crash class. All fragment async work correctly used
`viewLifecycleOwner.lifecycleScope` (cancels on leaving the screen), but 24
`catch (e: Exception)` blocks also caught the cancellation and then touched
`binding`/`requireContext()` — back-press mid-request destroyed the view (`_binding =
null`) and the catch/finally still ran, throwing NPE/IllegalStateException. The two
unconditional `finally { binding.… }` blocks were deterministic triggers. Fix: rethrow
`kotlinx.coroutines.CancellationException` as the first line of every fragment catch,
and null-safe the finally bodies (`_binding?.`). Activities were safe (bindings never
nulled); PackTracker was already hardened (`isAdded` guards, callback-based Retrofit,
try/catch on every view touch, defensive Gson normalization in OfflineScanStorage).

**Verified clean / false positives:** all date parsing wrapped in try/catch with raw
fallbacks; `parseImages` has JSON-then-comma fallbacks; `barcodes.first()` behind
`isNotEmpty()`; `paymentFile!!` null-checked; camera permission denial handled in both
apps (toast + finish); ScanSyncWorker catches-all to RETRYABLE; `callApi` helper (which
already rethrows CancellationException correctly) is dead code — fragments use raw
catches, hence the fix.

**Live probes (v1.2 on MuMu, fresh install):** fresh login → rapid tab-switch +
immediate-back across Dashboard/Orders/Packing (mid-load cancellation) → server stopped
(docker stop) → error paths + toasts + back-presses → server restored → tabs load again.
Crash buffer clean throughout.

**New MuMu quirk learned:** `cmd connectivity airplane-mode enable/disable` **persists
across guest restarts and kills MuMu's adb bridge** (adbd rides the virtual NIC), leaving
adb wedged at `device offline` even after VM restart/reboot. Recovery: disable airplane
mode via MuMuManager's RPC shell (bypasses adb):
`MuMuManager.exe sh -v 0 "cmd connectivity airplane-mode disable"` — adb reconnects
within seconds. (Also: `control -v 0 restart` does not bounce the Android guest;
use `shutdown` + `launch`.)

### Field crash reporting (2026-09-25)

Both Android apps now report uncaught exceptions to the site so field crashes are
diagnosable:

- **Server**: `includes/mobile-crash-log.php` (repo + live) registers the
  `ybt_mobile_crash_log` admin-ajax action and lazily creates `wp_ybt_crash_logs`
  (app, app_version, android_ver, device, user_id/username/is_admin when a bearer token
  is present, stack_trace ≤16k, custom_data, ip; keyed + dated). Auth is **optional**
  (a crashing app may lack a session); per-IP limit 10 reports / 5 min; insert failure
  = HTTP 500, missing stack = 400.
- **Clients**: `utils/CrashReporter.kt` in both apps (packtracker / ybt) chains the
  default uncaught-exception handler (process still dies normally), persists the crash
  to `files/crash-reports/` (ring of 5) on the dying thread, then flushes pending
  reports to the endpoint on next launch (raw OkHttp, best-effort, stops on first
  unreachable). Installed FIRST in both `Application.onCreate`s via `AppContextHolder`
  (early context, immune to later init failures).
- **Debug-only verification hook**: `src/debug` TestCrashReceiver (absent from release
  builds): `adb shell am broadcast -n com.packtracker/.utils.TestCrashReceiver --es note x`
  persists a synthetic report; next app launch flushes it.
- **Verified end-to-end on MuMu (PackTracker v2.3)**: empty table → synthetic crash
  broadcast → app relaunch → row 1 lands in `wp_ybt_crash_logs` with
  `app=packtracker, app_version=2.3 (6), android_ver=15, username=admin`, correct stack;
  app healthy afterwards, crash buffer clean. In-repo app build with the same
  reporter installed; note `buildConfig = true` was added to PackTracker's gradle and
  `ApiClient.BASE_URL` made public for the reporter.
- **To read field crashes**: `SELECT * FROM wp_ybt_crash_logs ORDER BY id DESC;`
  (consider an admin-viewer page later).

### Midnight-emerald dark theme (PackTracker v2.4 / versionCode 7, 2026-09-25)

The dark palette was generic navy slate ("legacy-looking" at night). Re-themed to echo
the emerald/kraft brand while keeping system-following behavior:

- **`values-night/colors.xml` rewritten** — deep ink-green surfaces: canvas `#0B1511`,
  "dark kraft" cards `#14251D`, elevated surfaces `#1B3026`/`#223A2E`, green-tinted text
  (`#E7F2EA` primary, `#9DB8A8` secondary), green outlines `#2E463A`. Bright emerald/
  gold accents unchanged per M3 dark conventions. All navy hexes removed; the only file
  touched besides it is `bg_kraft_canvas` (already theme-aware).
- **Hardcoded-white sweep**: `activity_main.xml` root + bottom nav,
  `item_scan_history.xml`, `dialog_user_picker.xml` backgrounds swapped `@color/white`
  → `@color/surface` (day-identical: day `surface` is `#FFFFFFFF`). Whites on the fixed
  emerald banners (toolbar titles, banner text, scanner icons) deliberately untouched.
- **Pixel-verified on MuMu (night)**: cards `#14251D` (91.8k sampled px), canvas
  `#0B1511` (50.7k), `#1B3026` (75.4k), **0 navy pixels**, emerald 29.5k, gold present;
  dark bottom nav renders. **Light regression**: kraft `#F1EAD9` (86.9k), zero dark
  bleed; a stats-zero capture was just mid-refresh (settles to live values). 0 crashes.
- **Shipped**: downloads now serve `ybt-packing-service-v2.4.apk` (28.9 MB, SHA-256
  `cb98109f…328efb`); download page + preview updated; v2.3 APK removed.
  Re-published 2026-09-25 from a **clean rebuild** of current source
  (`gradlew clean assembleDebug`): CrashReporter + `ybt_mobile_crash_log` endpoint
  string verified inside the artifact via dex grep (debug-only TestCrashReceiver
  present, as expected for a debug build); full-download hash matches the page
  checksum byte-for-byte; installed on MuMu (v2.4, dashboard loads).
  **Lesson**: Gradle APK hashes are NOT stable across builds even from identical
  source (zip entry timestamps/ordering) — `f0ec837b…` → `cb98109f…`. Always
  re-hash the exact published file after any rebuild and update the page; never
  assume the old checksum still applies.
