# Local WordPress + MySQL test environment (YBT Packer Service)

Runs WordPress + MySQL in Docker with the YBT plugin bind-mounted from the repo,
so edits to `ybt-packer-service.php`, `includes/`, `assets/`, `templates/` are
live — no copying or rebuilding needed.

## Quick start

```bash
cd local-wp
docker compose up -d          # start WordPress on http://localhost:8080
```

Wait for MySQL to become healthy, then install WordPress and activate the plugin:

```bash
# Install WordPress (one-time)
docker compose run --rm wp-cli core install \
  --url="http://localhost:8080" \
  --title="YBT Local Test" \
  --admin_user=admin \
  --admin_password="Admin@ybt2026!" \
  --admin_email="admin@local.test" \
  --skip-email

# Pretty permalinks (REST + assetlinks need these flushed AFTER plugin activation)
docker compose run --rm wp-cli rewrite structure '/%postname%/' --hard
docker compose run --rm wp-cli rewrite flush --hard

# Activate the plugin
docker compose run --rm wp-cli plugin activate ybt-packer-service
```

## Important gotchas learned the hard way

1. **Activate / create tables with an admin user context.** `YBT_Database::create_tables()`
   refuses to run without `current_user_can('manage_options')`, and WP-CLI has no
   user by default, so table creation is silently skipped. Always pass `--user=admin`:
   ```bash
   docker compose run --rm wp-cli --user=admin eval 'var_dump(YBT_Database::create_tables());'
   ```
2. **The `wp-cli` image ships a MariaDB client that can't do MySQL 8's default
   `caching_sha2_password` auth** (`wp db query` fails). The setup flips the
   `wordpress` user to `mysql_native_password`; if you wipe the DB volume
   (`docker compose down -v`), re-run:
   ```bash
   docker compose exec -T db mysql -uroot -prootpass \
     -e "ALTER USER 'wordpress'@'%' IDENTIFIED WITH mysql_native_password BY 'wordpress'; FLUSH PRIVILEGES;"
   ```
3. **Plugin directories the activator expects** (created by setup):
   `wp-content/ybt-logs/` (logs + .htaccess) and `wp-content/uploads/` (media).
   If missing, create them owned by `www-data`:
   ```bash
   docker compose exec -T wordpress sh -c 'mkdir -p /var/www/html/wp-content/ybt-logs /var/www/html/wp-content/uploads && chown -R www-data:www-data /var/www/html/wp-content/ybt-logs /var/www/html/wp-content/uploads'
   ```
4. **Firebase/FCM is not configured locally** — you'll see
   `YBT CRITICAL: Firebase credentials not configured securely. Notifications disabled.`
   That's expected. To test FCM, paste a service-account JSON in
   Admin → Settings → FCM (it's stored AES-encrypted).

## Smoke-testing the API

### Customer-facing endpoints (mobile app + REST) — full automated run

```bash
cd local-wp
bash smoke-test-customer.sh
```

Runs **32 checks** against the customer-facing surface (stack must be up):

- **Mobile auth (AJAX):** `ybt_test_connection`, register (incl. weak-password
  rejection), login (JWT issued, wrong-password rejected)
- **Customer flow (AJAX + Bearer JWT):** dashboard stats + info, add tracking,
  list/search, claim an anonymous parcel, request packing (batch created),
  chat get/send, 401 without a token
- **REST `/wp-json/ybt/v1/*` (X-API-Key):** tracking list/get/create/PUT/DELETE,
  stats, customer-lookup (found + 404), webhook (valid + forged signature)

The script is **idempotent and re-runnable**: its preflight rotates a fresh API
key, resets all rate-limit transients, and clears the login-lockout IP block.

### Manual one-offs

```bash
BASE=http://localhost:8080

# Public
curl "$BASE/wp-json/ybt/v1/app-version"
curl "$BASE/.well-known/assetlinks.json"          # Android App Links verification

# Admin login → token (desktop console)
TOKEN=$(curl -s -X POST "$BASE/wp-json/ybt-admin/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Admin@ybt2026!"}' | grep -oP '"token":"\K[^"]+')
curl "$BASE/wp-json/ybt-admin/v1/dashboard/stats" -H "Authorization: Bearer $TOKEN"

# Mobile login → JWT (customer app)
MOBILE_TOKEN=$(curl -s -X POST "$BASE/wp-admin/admin-ajax.php" \
  -d 'action=ybt_mobile_login&username=<user>&password=<pass>' | grep -oP '"token":"\K[^"]+')
curl "$BASE/wp-admin/admin-ajax.php?action=ybt_mobile_tracking" \
  -H "Authorization: Bearer $MOBILE_TOKEN"
```

### Known gotchas when writing tests here

- **Login lockout:** 5 failed logins from one IP blocks that IP for 15 min
  (`YBT_Security`), and the mobile login handler masks the resulting error as
  "Invalid username or password". `smoke-test-customer.sh` clears the block in
  preflight; manual repeat testing may hit it.
- **Rate limits:** register 3/15min, mobile login 5/15min, unauthenticated REST
  30/60s. The smoke test's preflight resets all of them.
- **`packing_requests` route removed:** the plugin's schema never created the
  table, and `rest-api.php`'s `/ybt/v1/packing-requests/{id}/tracking-numbers`
  (the only reader) could never succeed on a real install. No shipped client
  called it — packing requests are modeled as `batch_id` on `ybt_tracking` — so
  the route was removed in favor of that model.
- **Bash + `wp eval` quoting:** the smoke script passes PHP to wp-cli inside a
  single-quoted heredoc, so PHP single quotes (e.g. SQL `LIKE '…'`) get eaten
  by bash and fail silently. Use `\"` for string literals inside `wp eval`.

## Other useful commands

```bash
docker compose run --rm wp-cli plugin list
docker compose run --rm wp-cli eval 'var_dump(ybt_check_database_health());'
docker compose run --rm wp-cli db query "SHOW TABLES LIKE 'wp_ybt%';"
docker compose logs -f wordpress               # PHP errors / deprecations (WP_DEBUG on)
docker compose down                            # stop (data persists)
docker compose down -v                         # stop + wipe DB (fresh start)
```

Admin login for the WordPress dashboard: `admin` / `Admin@ybt2026!`
(local dev only — change before pointing anywhere public).

## Config

- WordPress: http://localhost:8080 (Apache + PHP 8.3, `WP_DEBUG` on)
- MySQL: internal only (service `db`), not published to the host (port 3306 was already taken)
- DB credentials: `wordpress` / `wordpress` / db `wordpress`, root `rootpass`
- The plugin is mounted at `wp-content/plugins/ybt-packer-service/`
