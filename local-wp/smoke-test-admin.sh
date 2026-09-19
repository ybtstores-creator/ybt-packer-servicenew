#!/usr/bin/env bash
# YBT admin-facing smoke test against the local-wp Docker stack.
#
# Covers the ybt-admin/v1 REST API (what the Android admin + desktop apps
# consume) and the plugin's wp-admin menu pages:
#   A. Auth          - login (JWT), wrong-password rejection, verify, capabilities, 401 gating
#   B. Dashboard     - stats
#   C. Users         - list, search, details, reset-password (weak + strong)
#   D. Tracking      - bulk add, list, detail, update, invalid status, assign,
#                      scan (new + existing), image upload, delete
#   E. Packing       - request packing, packing-requests list, cartons save/list
#   F. Chat          - chat users, messages, admin reply
#   G. Warehouse     - get + update address
#   H. Export        - tracking, users, invalid type
#   I. wp-admin pages- every plugin admin page renders 200 without a fatal error
#
# Usage: bash local-wp/smoke-test-admin.sh   (stack must be up)

set -u
cd "$(dirname "$0")"

BASE="http://localhost:8080"
AJAX="$BASE/wp-admin/admin-ajax.php"
ADMIN_REST="$BASE/wp-json/ybt-admin/v1"
TS="$(date +%s)"
USERNAME="adminsmoke${TS: -6}"
EMAIL="$USERNAME@example.com"
PASSWORD='Adm1nSm0ke#!'

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

say "Preflight: clear rate-limit transients + blocked IPs; fetch API key"
docker compose exec -T db mysql -uroot -prootpass wordpress \
  -e "DELETE FROM wp_options WHERE option_name LIKE '_transient_ybt_%' OR option_name LIKE '_transient_timeout_ybt_%';" 2>/dev/null
API_KEY=$(docker compose run --rm -T wp-cli eval '
  if (!class_exists("YBT_Encryption")) {
    require_once ABSPATH . "wp-content/plugins/ybt-packer-service/includes/class-ybt-encryption.php";
  }
  $e = YBT_Encryption::getInstance();
  $k = $e->generate_api_key();
  update_option("ybt_api_key_hash", $e->hash_api_key($k));
  update_option("ybt_api_key_display", substr($k, 0, 8) . "****");
  update_option("ybt_blocked_ips", array());
  echo $k;
' 2>/dev/null | tail -1)
[ -n "$API_KEY" ] && echo "  api key: ${API_KEY:0:8}****" || echo "  WARN: api key fetch failed"

# ---------------------------------------------------------------- A. Auth
say "A. Auth (ybt-admin/v1)"
OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Admin@ybt2026!"}')
chk "admin login returns token" '"success":true' "$OUT"
TOKEN=$(printf '%s' "$OUT" | jpath data.token)
[ -n "$TOKEN" ] && ok "token extracted" || bad "token extracted"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X POST "$ADMIN_REST/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"WrongPass99"}')
chk "admin login rejects wrong password (401)" "401" "$OUT"

OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/auth/verify" \
  -H "Content-Type: application/json" \
  -d "{\"api_key\":\"$API_KEY\"}")
chk "auth verify with valid api key" '"verified":true' "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X POST "$ADMIN_REST/auth/verify" \
  -H "Content-Type: application/json" -d '{}')
chk "auth verify rejects missing key (400)" "400" "$OUT"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/auth/capabilities" \
  -H "Authorization: Bearer $TOKEN")
chk "capabilities endpoint" 'feature_export_csv' "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' "$ADMIN_REST/dashboard/stats")
chk "dashboard/stats without auth rejected (401)" "401" "$OUT"

# ------------------------------------------------------------ B. Dashboard
say "B. Dashboard stats"
OUT=$(curl -s --max-time 30 "$ADMIN_REST/dashboard/stats" \
  -H "Authorization: Bearer $TOKEN")
chk "dashboard stats return totals" '"total_tracking"' "$OUT"

# ---------------------------------------------------------------- C. Users
say "C. Users"
OUT=$(curl -s --max-time 30 -X POST "$AJAX" \
  --data "action=ybt_mobile_register&username=$USERNAME&email=$EMAIL&password=$PASSWORD")
chk "register fresh customer for admin tests" '"success":true' "$OUT"

OUT=$(curl -s --max-time 30 -X POST "$AJAX" \
  --data "action=ybt_mobile_login&username=$USERNAME&password=$PASSWORD")
chk "customer login (JWT for packing flow)" '"token"' "$OUT"
CUST_TOKEN=$(printf '%s' "$OUT" | jpath data.token)

OUT=$(curl -s --max-time 30 "$ADMIN_REST/users?search=$USERNAME" \
  -H "Authorization: Bearer $TOKEN")
chk "users search finds the new customer" "$USERNAME" "$OUT"
USER_ID=$(printf '%s' "$OUT" | jpath data.users.0.id)
[ -n "$USER_ID" ] && ok "user id = $USER_ID" || bad "user id"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/users?limit=3" \
  -H "Authorization: Bearer $TOKEN")
chk "users list returns array" '"users"' "$OUT"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/users/$USER_ID" \
  -H "Authorization: Bearer $TOKEN")
chk "user details return profile" '"profile"' "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X POST \
  "$ADMIN_REST/users/$USER_ID/reset-password" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"new_password":"short"}')
chk "reset-password rejects weak password (400)" "400" "$OUT"

OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/users/$USER_ID/reset-password" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"new_password":"Str0ng#Reset1"}')
chk "reset-password succeeds with strong password" '"Password reset successfully"' "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X POST \
  "$ADMIN_REST/users/999999/reset-password" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"new_password":"Str0ng#Reset1"}')
chk "reset-password unknown user (404)" "404" "$OUT"

# ------------------------------------------------------------- D. Tracking
say "D. Tracking"
TRACK1="ADMIN-T1-$TS"
TRACK2="ADMIN-T2-$TS"
OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/tracking" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d "{\"tracking_numbers\":\"$TRACK1,$TRACK2\",\"description\":\"admin smoke parcel\",\"target_user_id\":$USER_ID}")
chk "bulk add two tracking numbers" 'Successfully processed 2 parcel' "$OUT"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/tracking?search=$TRACK1" \
  -H "Authorization: Bearer $TOKEN")
chk "tracking list finds added parcel" "$TRACK1" "$OUT"
ITEM_ID=$(printf '%s' "$OUT" | jpath data.items.0.id)
OUT=$(curl -s --max-time 30 "$ADMIN_REST/tracking?search=$TRACK2" \
  -H "Authorization: Bearer $TOKEN")
TRACK2_ID=$(printf '%s' "$OUT" | jpath data.items.0.id)

OUT=$(curl -s --max-time 30 "$ADMIN_REST/tracking/$ITEM_ID" \
  -H "Authorization: Bearer $TOKEN")
chk "tracking detail by id" '"success":true' "$OUT"

OUT=$(curl -s --max-time 30 -X PUT "$ADMIN_REST/tracking/$ITEM_ID" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"completed","description":"updated via admin","admin_notes":"smoke note"}')
chk "tracking update status/notes" '"Tracking updated"' "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X PUT \
  "$ADMIN_REST/tracking/$ITEM_ID" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"bogus_status"}')
chk "tracking update rejects invalid status (400)" "400" "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X PUT \
  "$ADMIN_REST/tracking/999999" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"received"}')
chk "tracking update unknown id (404)" "404" "$OUT"

# A second received parcel for the packing flow (reuse TRACK2)
OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/tracking/$ITEM_ID/assign" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d "{\"user_id\":$USER_ID}")
chk "assign parcel to customer" '"Parcel assigned"' "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X POST \
  "$ADMIN_REST/tracking/$ITEM_ID/assign" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"user_id":999999}')
chk "assign to unknown user (404)" "404" "$OUT"

# Scan: new barcode -> anonymous received; then not_received -> received
SCAN_NEW="ADMIN-SCAN-$TS"
OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/scan" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d "{\"barcode\":\"$SCAN_NEW\"}")
chk "scan new barcode creates received parcel" '"is_new":true' "$OUT"

SCAN_OLD="ADMIN-SCANOLD-$TS"
OUT=$(curl -s --max-time 30 -X POST -H "Authorization: Bearer $CUST_TOKEN" "$AJAX" \
  --data "action=ybt_mobile_add_tracking&tracking_number=$SCAN_OLD&description=to+be+received")
chk "customer adds not_received parcel (prereq)" '"success":true' "$OUT"
OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/scan" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d "{\"barcode\":\"$SCAN_OLD\"}")
chk "scan existing not_received flips to received" '"status":"received"' "$OUT"

# Image upload (uses tracking/{id}/image) - a real image
# NOTE: keep the file in the cwd - Windows curl cannot read Git Bash's
# virtual /tmp paths (mktemp), which previously made every upload silently
# fail with curl exit 26.
IMG="_img_upload_$$.jpg"
printf '\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xff\xdb\x00C\x00\xff\xc0\x00\x0b\x08\x00\x01\x00\x01\x01\x01\x11\x00\xff\xc4\x00\x1f\x00\xff\xda\x00\x08\x01\x01\x00\x00?\x00\xff\xd9' > "$IMG"
OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/tracking/$ITEM_ID/image" \
  -H "Authorization: Bearer $TOKEN" -F "tracking_id=$ITEM_ID" -F "image=@$IMG;type=image/jpeg")
chk "tracking image upload" '"success":true' "$OUT"
rm -f "$IMG"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X DELETE \
  "$ADMIN_REST/tracking/$ITEM_ID" \
  -H "Authorization: Bearer $TOKEN")
chk "tracking delete" "200" "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X DELETE \
  "$ADMIN_REST/tracking/$ITEM_ID" \
  -H "Authorization: Bearer $TOKEN")
chk "tracking delete unknown id (404)" "404" "$OUT"

# ---------------------------------------------------------------- E. Packing
say "E. Packing"
OUT=$(curl -s --max-time 30 -X POST -H "Authorization: Bearer $CUST_TOKEN" "$AJAX" \
  --data "action=ybt_mobile_request_packing&tracking_ids=$TRACK2_ID&shipping_company_name=Admin+Logistics&shipping_company_address=456+Admin+St&shipping_company_phone=%2B8613900000000&shipping_mark=ADMIN")
chk "request packing via customer flow" "Packing request submitted" "$OUT"
BATCH_ID=$(printf '%s' "$OUT" | jpath data.batch_id)
[ -n "$BATCH_ID" ] && ok "batch created ($BATCH_ID)" || bad "batch created"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/packing-requests" \
  -H "Authorization: Bearer $TOKEN")
chk "packing requests list contains batch" "$BATCH_ID" "$OUT"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/packing-requests/$BATCH_ID/cartons" \
  -H "Authorization: Bearer $TOKEN")
chk "batch cartons GET" '"cartons"' "$OUT"

OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/packing-requests/$BATCH_ID/cartons" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"cartons":[{"weight":2.5,"amount":120,"currency":"CNY"},{"weight":3.1,"amount":95,"currency":"CNY"}]}')
chk "batch cartons save" '2 cartons saved' "$OUT"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/packing-requests/$BATCH_ID/cartons" \
  -H "Authorization: Bearer $TOKEN")
chk "batch cartons reflect saved data" '"weight_kg":"2.50"' "$OUT"

# ------------------------------------------------------------------- F. Chat
say "F. Chat"
OUT=$(curl -s --max-time 30 "$ADMIN_REST/chat/users" \
  -H "Authorization: Bearer $TOKEN")
chk "chat users list" '"users"' "$OUT"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/chat/$USER_ID/messages" \
  -H "Authorization: Bearer $TOKEN")
chk "chat messages GET" '"messages"' "$OUT"

MSG="Admin reply from smoke test $TS"
OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/chat/$USER_ID/messages" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d "{\"message\":\"$MSG\"}")
chk "admin sends chat message" '"Message sent"' "$OUT"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/chat/$USER_ID/messages" \
  -H "Authorization: Bearer $TOKEN")
chk "chat messages include admin reply" "$MSG" "$OUT"

# -------------------------------------------------------------- G. Warehouse
say "G. Warehouse"
OUT=$(curl -s --max-time 30 "$ADMIN_REST/warehouse" \
  -H "Authorization: Bearer $TOKEN")
chk "warehouse settings GET" '"success":true' "$OUT"

NEW_ADDR="123 Warehouse Road, Guangzhou (smoke $TS)"
OUT=$(curl -s --max-time 30 -X PUT "$ADMIN_REST/warehouse" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d "{\"address\":\"$NEW_ADDR\"}")
chk "warehouse settings update" '"Warehouse updated"' "$OUT"

OUT=$(curl -s --max-time 30 "$ADMIN_REST/warehouse" \
  -H "Authorization: Bearer $TOKEN")
chk "warehouse settings reflect update" "smoke $TS" "$OUT"

# ----------------------------------------------------------------- H. Export
say "H. Export"
OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/export" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"type":"tracking","status":"all"}')
chk "export tracking" '"count"' "$OUT"

OUT=$(curl -s --max-time 30 -X POST "$ADMIN_REST/export" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"type":"users"}')
chk "export users" '"type":"users"' "$OUT"

OUT=$(curl -s --max-time 30 -o /dev/null -w '%{http_code}' -X POST \
  "$ADMIN_REST/export" \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"type":"bogus"}')
chk "export invalid type (400)" "400" "$OUT"

# ---------------------------------------------------------- I. wp-admin pages
say "I. wp-admin menu pages"
COOKIE_JAR=$(mktemp)
curl -s --max-time 30 -c "$COOKIE_JAR" "$BASE/wp-login.php" -o /dev/null
# NOTE: the stock WP login form has no _wpnonce field (nonce is applied on
# submit via JS), so a cookie-based POST login is the correct contract.
curl -s --max-time 30 -b "$COOKIE_JAR" -c "$COOKIE_JAR" -L -o /dev/null \
  -d "log=admin&pwd=Admin@ybt2026!&wp-submit=Log+In&redirect_to=http%3A%2F%2Flocalhost%3A8080%2Fwp-admin%2F&testcookie=1" \
  "$BASE/wp-login.php"
IS_ADMIN=$(curl -s --max-time 30 -b "$COOKIE_JAR" "$BASE/wp-admin/" | grep -c "Dashboard")
if [ "$IS_ADMIN" -gt 0 ]; then
  ok "wp-admin login accepted"
else
  bad "wp-admin login accepted"
fi

if [ "$IS_ADMIN" -gt 0 ]; then
  PAGES="ybt-packer ybt-parcels ybt-add-tracking ybt-manage-tracking ybt-process-packing ybt-manage-cartons ybt-barcode-scanner ybt-bulk-import ybt-advanced-search ybt-reports ybt-analytics ybt-smart-dashboard ybt-user-summary ybt-activity-log ybt-settings-group ybt-settings ybt-fcm-settings ybt-warehouse-settings ybt-payment-settings ybt-payment-evidence ybt-email-settings ybt-security ybt-tools ybt-site-export ybt-site-import ybt-app-updates ybt-support ybt-chat ybt-messages ybt-invoices"
  for page in $PAGES; do
    BODY=$(curl -s --max-time 30 -b "$COOKIE_JAR" -w '\n__CODE__%{http_code}' \
      "$BASE/wp-admin/admin.php?page=$page")
    CODE=$(printf '%s' "$BODY" | grep -oE '__CODE__[0-9]+$' | grep -oE '[0-9]+$')
    FATAL=$(printf '%s' "$BODY" | grep -cE "Fatal error|critical error|Call to (undefined|member) function")
    if [ "$CODE" = "200" ] && [ "$FATAL" -eq 0 ]; then
      ok "admin page renders: $page"
    else
      bad "admin page $page (code=$CODE fatal=$FATAL)"
    fi
  done
  # Cleanup: reset the warehouse address to a clean value
  curl -s --max-time 30 -X PUT "$ADMIN_REST/warehouse" \
    -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
    -d '{"address":"123 Warehouse Road, Guangzhou"}' > /dev/null
fi
rm -f "$COOKIE_JAR"

echo
echo "=============================================="
echo "Admin smoke test: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ] && echo "ALL GREEN" || echo "SEE FAILURES ABOVE"
exit "$FAIL"
