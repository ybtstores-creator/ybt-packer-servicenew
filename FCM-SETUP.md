# FCM Push Notifications — Setup Guide

Instant message alerts (instead of the 15-minute WorkManager poll) via
Firebase Cloud Messaging. Both sides of the pipeline already exist in this
repo — this guide covers the **configuration** you must do once.

## How it fits together

```
WordPress (YBT_Notifications::notify())
  ├─ writes inbox row  → ybt_notifications   (app badge + in-app popup)
  └─ sends FCM data message → device        (instant tray alert / banner)
        ↓
PackTracker app (PackTrackerMessagingService)
  ├─ app in foreground → in-app heads-up banner
  └─ app backgrounded  → system tray notification → tap → Notifications screen

Fallback: NotificationPollWorker still polls every 15 min in case FCM is
misconfigured or a push is dropped (with shared dedupe state, so no doubles).
```

## Part 1 — Firebase project (once)

1. Go to [console.firebase.google.com](https://console.firebase.google.com) → **Add project** (or reuse an existing one).
2. Add an **Android app** to the project:
   - Package name: `com.packtracker` (must match exactly)
   - Nickname: anything
   - SHA-1: optional (only needed for Google Sign-In / Dynamic Links)
3. Download **`google-services.json`**.
4. Place it at: `PackTracker/app/google-services.json`

> The Gradle build auto-detects this file: when present it applies the
> `google-services` plugin; when absent the project still builds (push is
> simply inactive). So this file is what "switches on" FCM for the app.

5. In Firebase console → **Project settings → Service accounts**:
   - Click **Generate new private key** → downloads a JSON file.
   - Keep it secret — this is what lets your server send pushes.

## Part 2 — WordPress sender credentials (once)

The plugin refuses to send until credentials exist. Choose ONE method
(checked in this order at runtime):

| Priority | Method | How |
|----------|--------|-----|
| 1 | Environment variable | `YBT_FCM_SERVICE_ACCOUNT_JSON` = raw JSON of the service-account file |
| 2 | wp-config.php constant | `define('YBT_FCM_SERVICE_ACCOUNT_JSON', '<raw JSON string>');` |
| 3 | Admin UI (encrypted in DB) | YBT admin → Settings → FCM section → paste JSON → save |

The admin UI is easiest on shared hosting. Method 1/2 are safer on dedicated
servers. Verify with the **Test Connection** button on the FCM settings page
(it mints an OAuth token and reports the Firebase project id).

Also confirm the admin setting **Notifications enabled**
(`ybt_email_notifications_enabled`) is on — `Automation` and invoice mails
gate on it.

## Part 3 — What was added in this repo

Server (`includes/`):
- `class-ybt-notifications.php` — new `insert_inbox()` (writes the app inbox
  row) and `notify()` (inbox row + push, one event). `send_tracking_update()`
  now routes through `notify()`. FCM payloads are **data-only** so the app
  controls display (no double tray notifications).
- `api/class-ybt-api-chat-handler.php` — admin→user chat now writes an inbox
  row and pushes (`type=chat_message`), instead of abusing
  `send_tracking_update`.

Android (`PackTracker/`):
- `app/build.gradle.kts` — Firebase BOM 33.1.2 + `firebase-messaging`;
  google-services plugin applied **only when** `google-services.json` exists.
- `utils/PackTrackerMessagingService.kt` — receives pushes. Foreground →
  in-app banner; background → tray notification deep-linking to the
  Notifications screen.
- `utils/FcmTokenManager.kt` — token persistence + server registration
  (`ybt_mobile_update_fcm`) with self-healing retry each app launch;
  unregisters on logout (`ybt_mobile_unregister_token`).
- `utils/NotifState.kt` — shared dedupe state (inbox-row id) across FCM,
  in-app banner, and the WorkManager fallback poll.
- Login and MainActivity now call `FcmTokenManager.fetchAndStore/syncToServer`;
  logout calls `unregisterAndClear`.

Existing server pieces already in place: `ybt_fcm_tokens` table, token
registration on mobile login, `ybt_mobile_unregister_token` route, invalid
token cleanup, daily stale-token cron.

## Part 4 — Testing

1. Build & install the app (`./gradlew :app:installDebug`).
2. Log in — watch logcat for `FcmTokenManager: FCM token registered with server`.
3. Check the DB: `SELECT COUNT(*) FROM wp_ybt_fcm_tokens WHERE user_id = <id>;` → ≥ 1.
4. With the app **closed**, trigger a notification: admin changes an order
   status, sends a chat message, or via WP-CLI:
   ```bash
   php -r "
   define('ABSPATH', '/var/www/html/');
   require 'wp-load.php';
   YBT_Notifications::notify(<user_id>, 'Test title', 'Test body', ['type' => 'test']);
   "
   ```
   Expect the tray notification within a couple of seconds.
5. Tap it → app opens straight to the Notifications screen.
6. Repeat with the app **open** → in-app banner instead, no tray icon.
7. Check dedupe: with the app open, receive a push (banner shows) — the next
   15-min WorkManager pass must not re-notify (see `NotifState`).

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| No push, logcat silent | `google-services.json` missing/mismatched package; check `ybt_fcm_tokens` has a row; server creds not configured (see `ybt_log_error` entries) |
| Push arrives but no banner in-app | `PopupHost.currentActivity` null? Push handled but banner suppressed over inbox — expected |
| Two notifications for one message | Old client build mixing `notification` payloads — reinstall; ensure server sends data-only (this repo's sender does) |
| `FCM send failed` server-side with 401 | Service account JSON stale/rotated — re-paste, re-test connection |
| Token registered but push not delivered | Check `ybt_log_info('FCM send results')` — invalid tokens are auto-removed; app must re-login/re-launch to re-register |
