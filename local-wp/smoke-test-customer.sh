#!/usr/bin/env bash
# YBT customer-facing API smoke test against the local-wp Docker stack.
#
# Covers:
#   A. Public endpoints          - ybt_test_connection (AJAX), REST 401 gating
#   B. Mobile auth (AJAX)        - register, login (JWT), wrong-password rejection
#   C. Customer flow (AJAX+JWT)  - dashboard, tracking CRUD, search, claim,
#                                  request packing, chat
#   D. REST /wp-json/ybt/v1/*    - tracking CRUD, stats, customer-lookup, webhook
#                                  (X-API-Key auth)
#
# Usage: bash local-wp/smoke-test-customer.sh   (stack must be up)

set -u
cd "$(dirname "$0")"

BASE="http://localhost:8080"
AJAX="$BASE/wp-admin/admin-ajax.php"
REST="$BASE/wp-json/ybt/v1"
TS="$(date +%s)"
USERNAME="smokecust${TS: -6}"
EMAIL="$USERNAME@example.com"
PASSWORD='Sm0ke#Pass!'

PASS=0
FAIL=0

say() { printf '\n== %s\n' "$1"; }
ok()  { printf '  PASS  %s\n' "$1"; PASS=$((PASS + 1)); }
bad() { printf '  FAIL  %s\n' "$1"; FAIL=$((FAIL + 1)); }
chk() { # name expected_fragment output
  if printf '%s' "$3" | grep -q -- "$2"; then
    ok "$1"
  else
    bad "$1"
    printf '        got: %s\n' "$(printf '%s' "$3" | head -c 400)"
  fi
}
jpath() { # dotted path e.g. data.token  (reads JSON from stdin)
  php -r '
    $d = json_decode(stream_get_contents(STDIN), true);
    foreach (explode(".", $argv[1]) as $k) {
      if (!is_array($d) || !array_key_exists($k, $d)) { exit(1); }
      $d = $d[$k];
    }
    if (is_scalar($d) || $d === null) { echo (string) $d; }
  ' "$1" 2>/dev/null
}

say "Preflight: fetch API key + webhook secret from the stack"
CREDS=$(docker compose run --rm -T wp-cli eval '
  if (!class_exists("YBT_Encryption")) {
    require_once ABSPATH . "wp-content/plugins/ybt-packer-service/includes/class-ybt-encryption.php";
  }
  $e = YBT_Encryption::getInstance();
  $k = $e->generate_api_key();
  update_option("ybt_api_key_hash", $e->hash_api_key($k));
  update_option("ybt_api_key_display", substr($k, 0, 8) . "****");
  echo $k . "\n" . get_option("ybt_webhook_secret", "");
  global $wpdb;
  // Reset rate-limit transients so repeated runs are deterministic.
  // NOTE: use escaped double quotes for the LIKE literals - plain single
  // quotes would terminate this bash single-quoted heredoc and be silently
  // stripped, leaving MySQL with unquoted literals and a dead DELETE.
  $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"_transient_ybt_rate_%\" OR option_name LIKE \"_transient_ybt_rl_%\" OR option_name LIKE \"_transient_ybt_mobile_%\" OR option_name LIKE \"_transient_ybt_login_fails_%\"");
  // Unblock the test IP (wrong-password tests trip the login lockout)
  update_option("ybt_blocked_ips", array());
' 2>/dev/null | tail -2)
API_KEY=$(printf '%s\n' "$CREDS" | sed -n '1p')
WEBHOOK_SECRET=$(printf '%s\n' "$CREDS" | sed -n '2p')
if [ -z "$API_KEY" ]; then
  echo "ERROR: could not fetch API key from wp-cli (stack running?)"; exit 1
fi
echo "  api key: ${API_KEY:0:8}****   webhook secret: ${WEBHOOK_SECRET:0:8}****"

# ---------------------------------------------------------------- A. Public
say "A. Public endpoints"
OUT=$(curl -s --max-time 30 -X POST "$AJAX" --data "action=ybt_test_connection")
chk "ybt_test_connection returns plugin info" "mobile_api_loaded" "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' "$REST/tracking")
chk "REST /tracking rejects unauthenticated (401)" "401" "$OUT"

# ------------------------------------------------------- B. Mobile auth
say "B. Mobile auth (AJAX)"
OUT=$(curl -s --max-time 30 -X POST "$AJAX" --data "action=ybt_mobile_register&username=$USERNAME&email=$EMAIL&password=$PASSWORD")
chk "mobile register ($USERNAME)" "Registered successfully" "$OUT"

OUT=$(curl -s --max-time 30 -X POST "$AJAX" --data "action=ybt_mobile_register&username=${USERNAME}dup&email=${USERNAME}dup@example.com&password=short")
chk "mobile register rejects weak password" "at least 8" "$OUT"

OUT=$(curl -s --max-time 30 -X POST "$AJAX" --data "action=ybt_mobile_login&username=$USERNAME&password=WrongPass99")
chk "mobile login rejects wrong password" "Invalid username or password" "$OUT"

OUT=$(curl -s --max-time 30 -X POST "$AJAX" --data "action=ybt_mobile_login&username=$USERNAME&password=$PASSWORD")
chk "mobile login issues JWT" "\"token\"" "$OUT"
TOKEN=$(printf '%s' "$OUT" | jpath data.token)
[ -n "$TOKEN" ] && ok "JWT token non-empty" || { bad "JWT token non-empty"; echo "       got: ${OUT:0:200}"; }
AUTH="Authorization: Bearer $TOKEN"

# ------------------------------------------------- C. Customer flow
say "C. Customer flow (JWT Bearer)"

OUT=$(curl -s --max-time 30 -H "$AUTH" "$AJAX?action=ybt_mobile_dashboard_info")
chk "dashboard_info" '"success":true' "$OUT"

OUT=$(curl -s --max-time 30 -H "$AUTH" "$AJAX?action=ybt_mobile_dashboard")
chk "dashboard stats" '"success":true' "$OUT"

CUST_TRACKING="SMOKE-CUST-$TS"
OUT=$(curl -s --max-time 30 -X POST -H "$AUTH" "$AJAX" \
  --data "action=ybt_mobile_add_tracking&tracking_number=$CUST_TRACKING&description=smoke+test+parcel&item_count=2&shipping_method=sea")
chk "add tracking $CUST_TRACKING" '"success":true' "$OUT"

OUT=$(curl -s --max-time 30 -H "$AUTH" "$AJAX?action=ybt_mobile_tracking")
chk "tracking list shows new item" "$CUST_TRACKING" "$OUT"

OUT=$(curl -s --max-time 30 -H "$AUTH" "$AJAX?action=ybt_mobile_search_tracking&query=SMOKE-CUST")
chk "search finds it" "$CUST_TRACKING" "$OUT"

ITEM_ID=$(printf '%s' "$OUT" | php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["data"]["items"][0]["id"] ?? "";' 2>/dev/null)
[ -n "$ITEM_ID" ] && ok "item id = $ITEM_ID" || bad "item id"

# Create an anonymous parcel (as if admin scanned it), then claim it
ANON_TRACKING="SMOKE-ANON-$TS"
curl -s --max-time 30 -X POST "$REST/tracking" -H "X-API-Key: $API_KEY" \
  --data "tracking_number=$ANON_TRACKING&description=unclaimed" > /dev/null
OUT=$(curl -s --max-time 30 -X POST -H "$AUTH" "$AJAX" \
  --data "action=ybt_mobile_claim_parcel&tracking_number=$ANON_TRACKING")
chk "claim anonymous parcel" '"success":true' "$OUT"

OUT=$(curl -s --max-time 30 -H "$AUTH" "$AJAX?action=ybt_mobile_tracking")
chk "claimed parcel now owned by customer" "$ANON_TRACKING" "$OUT"

# Mark the customer's item 'received' via the REST API (admin side), then request packing
OUT=$(curl -s --max-time 30 -X PUT "$REST/tracking/$ITEM_ID" -H "X-API-Key: $API_KEY" \
  --data "status=received")
chk "REST marks item received (prereq for packing)" 'Tracking item updated successfully' "$OUT"

OUT=$(curl -s --max-time 30 -X POST -H "$AUTH" "$AJAX" \
  --data "action=ybt_mobile_request_packing&tracking_ids=$ITEM_ID&shipping_company_name=Smoke+Logistics&shipping_company_address=123+Test+St&shipping_company_phone=+8613800000000&shipping_mark=TEST")
chk "request packing" "Packing request submitted" "$OUT"
BATCH_ID=$(printf '%s' "$OUT" | jpath data.batch_id)
[ -n "$BATCH_ID" ] && ok "batch created ($BATCH_ID)" || bad "batch created"

OUT=$(curl -s --max-time 30 -H "$AUTH" "$AJAX?action=ybt_mobile_chat")
chk "chat: get messages (empty ok)" '"success":true' "$OUT"

OUT=$(curl -s --max-time 30 -X POST -H "$AUTH" "$AJAX" \
  --data-urlencode "action=ybt_mobile_send_chat" --data-urlencode "message=Hello support - smoke test")
chk "chat: send message" '"success":true' "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' "$AJAX?action=ybt_mobile_tracking")
chk "tracking without token rejected (401)" "401" "$OUT"

# ------------------------------------------------- D. REST /ybt/v1/*
say "D. REST /wp-json/ybt/v1/* (X-API-Key)"
OUT=$(curl -s --max-time 30 -H "X-API-Key: $API_KEY" "$REST/tracking?per_page=100")
chk "GET /tracking lists items" "$CUST_TRACKING" "$OUT"

OUT=$(curl -s --max-time 30 -H "X-API-Key: $API_KEY" "$REST/tracking/$ITEM_ID")
chk "GET /tracking/{id}" "$CUST_TRACKING" "$OUT"

OUT=$(curl -s --max-time 30 -H "X-API-Key: $API_KEY" "$REST/stats")
chk "GET /stats" '"total"' "$OUT"

OUT=$(curl -s --max-time 30 -H "X-API-Key: $API_KEY" "$REST/customer-lookup?username=$USERNAME")
chk "GET /customer-lookup finds $USERNAME" "$USERNAME" "$OUT"

OUT=$(curl -s --max-time 30 -H "X-API-Key: $API_KEY" "$REST/customer-lookup?username=nonexistentuserzzz")
chk "customer-lookup returns 404 for unknown" "404" "$OUT"

REST_TRACKING="SMOKE-REST-$TS"
OUT=$(curl -s --max-time 30 -X POST -H "X-API-Key: $API_KEY" "$REST/tracking" \
  --data "tracking_number=$REST_TRACKING&description=rest+create")
chk "POST /tracking creates" '"message"' "$OUT"
REST_ID=$(printf '%s' "$OUT" | jpath id)
[ -n "$REST_ID" ] && ok "created id=$REST_ID" || bad "created id"

OUT=$(curl -s --max-time 30 -X PUT -H "X-API-Key: $API_KEY" "$REST/tracking/$REST_ID" --data "status=packed")
chk "PUT /tracking/{id} updates status" 'Tracking item updated successfully' "$OUT"

OUT=$(curl -s --max-time 30 -X DELETE -H "X-API-Key: $API_KEY" "$REST/tracking/$REST_ID")
chk "DELETE /tracking/{id}" 'Tracking item deleted successfully' "$OUT"

# Webhook: valid signature -> accepted, bad signature -> 401
BODY='{"event":"test","ts":'$TS'}'
SIG=$(printf '%s' "$BODY" | php -r 'echo hash_hmac("sha256", stream_get_contents(STDIN), $argv[1]);' "$WEBHOOK_SECRET")
OUT=$(curl -s --max-time 30 -X POST "$REST/webhook" -H "Content-Type: application/json" \
  -H "X-YBT-Signature: $SIG" -H "X-YBT-Timestamp: $TS" --data "$BODY")
chk "webhook valid signature" "Webhook received" "$OUT"
OUT=$(curl -s --max-time 30 -X POST "$REST/webhook" -H "Content-Type: application/json" \
  -H "X-YBT-Signature: deadbeef" -H "X-YBT-Timestamp: $TS" --data "$BODY")
chk "webhook bad signature rejected (401)" "401" "$OUT"

# ----------------------------------------------------------- E. Web UI pages
say "E. Web UI (shortcode pages)"
# Public pages render 200 without a fatal error when logged out
for p in login register reset-password; do
  BODY=$(curl -s --max-time 30 -w '\n__CODE__%{http_code}' "$BASE/$p/")
  CODE=$(printf '%s' "$BODY" | grep -oE '__CODE__[0-9]+$' | grep -oE '[0-9]+$')
  FATAL=$(printf '%s' "$BODY" | grep -cE "Fatal error|critical error|Call to (undefined|member) function")
  if [ "$CODE" = "200" ] && [ "$FATAL" -eq 0 ]; then
    ok "public page renders: $p"
  else
    bad "public page $p (code=$CODE fatal=$FATAL)"
  fi
done

# Gated pages redirect to login when logged out
for p in dashboard add-tracking not-received received packing-in-progress packed completed anonymous profile support; do
  CODE=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' "$BASE/$p/")
  if [ "$CODE" = "302" ]; then
    ok "gated page redirects logged-out users: $p"
  else
    bad "gated page $p did not redirect (code=$CODE)"
  fi
done

# Web register via AJAX (ybt_register_user + nonce + mobile_number).
# Register auto-logs-in (sets the auth cookie), so the login step below uses a
# fresh cookie jar - real users log in from a clean session, and WP nonces are
# bound to the user + session anyway.
WEB_USER="webcust${TS: -6}"
WEB_EMAIL="$WEB_USER@example.com"
COOKIE_JAR="_cust_cookies_$$.txt"
LOGIN_HTML=$(curl -s --max-time 30 -c "$COOKIE_JAR" "$BASE/login/")
WEB_NONCE=$(printf '%s' "$LOGIN_HTML" | grep -oE "ybtNonce = \(typeof ybt_ajax[^:]*: '[a-f0-9]+'" | grep -oE "[a-f0-9]{10}" | head -1)
if [ -z "$WEB_NONCE" ]; then
  bad "web nonce extracted from login page"
else
  ok "web nonce extracted from login page"
fi
OUT=$(curl -s --max-time 30 -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$AJAX" \
  --data "action=ybt_register_user&nonce=$WEB_NONCE&username=$WEB_USER&email=$WEB_EMAIL&mobile_number=%2B8613900000000&password=$PASSWORD&confirm_password=$PASSWORD")
chk "web register via AJAX" '"success":true' "$OUT"

# Web login via AJAX (ybt_ajax_login) from a clean session - sets the auth cookie
rm -f "$COOKIE_JAR"
LOGIN_HTML=$(curl -s --max-time 30 -c "$COOKIE_JAR" "$BASE/login/")
WEB_NONCE=$(printf '%s' "$LOGIN_HTML" | grep -oE "ybtNonce = \(typeof ybt_ajax[^:]*: '[a-f0-9]+'" | grep -oE "[a-f0-9]{10}" | head -1)
OUT=$(curl -s --max-time 30 -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$AJAX" \
  --data "action=ybt_ajax_login&nonce=$WEB_NONCE&username=$WEB_USER&password=$PASSWORD&remember=on")
chk "web login via AJAX" '"success":true' "$OUT"

# Gated pages render 200 without a fatal error once logged in
for p in dashboard add-tracking not-received received packing-in-progress packed completed anonymous profile support; do
  BODY=$(curl -s --max-time 30 -b "$COOKIE_JAR" -w '\n__CODE__%{http_code}' "$BASE/$p/")
  CODE=$(printf '%s' "$BODY" | grep -oE '__CODE__[0-9]+$' | grep -oE '[0-9]+$')
  FATAL=$(printf '%s' "$BODY" | grep -cE "Fatal error|critical error|Call to (undefined|member) function")
  if [ "$CODE" = "200" ] && [ "$FATAL" -eq 0 ]; then
    ok "gated page renders logged in: $p"
  else
    bad "gated page $p (code=$CODE fatal=$FATAL)"
  fi
done

# Dashboard shows the logged-in user (profile data reaches the template)
DASH=$(curl -s --max-time 30 -b "$COOKIE_JAR" "$BASE/dashboard/")
chk "dashboard shows username" "$WEB_USER" "$DASH"
chk "dashboard shows YBT custom ID" "YBT" "$DASH"

# Profile page shows the user's email
PROF=$(curl -s --max-time 30 -b "$COOKIE_JAR" "$BASE/profile/")
chk "profile page shows email" "$WEB_EMAIL" "$PROF"

rm -f "$COOKIE_JAR"

echo
echo "=============================================="
echo "Customer-facing smoke test: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ] && echo "ALL GREEN" || echo "SEE FAILURES ABOVE"
exit "$FAIL"
