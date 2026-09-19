import json, subprocess, sys

BASE_URL = "http://localhost/wordpress/wp-admin/admin-ajax.php"
REST_URL = "http://localhost/wordpress/wp-json"

results = []

def curl_cmd(method, url, headers=None, data=None):
    cmd = ["curl", "-s", "-X", method]
    if headers:
        for k, v in headers.items():
            cmd.extend(["-H", k + ": " + v])
    if data:
        cmd.extend(["-d", "&".join(k + "=" + v for k, v in data.items())])
    cmd.append(url)
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        return json.loads(r.stdout)
    except:
        return {"error": "Failed to parse response"}

def api_post(action, data=None, headers=None):
    """POST to admin-ajax.php with action"""
    post_data = {"action": action}
    if data:
        post_data.update(data)
    return curl_cmd("POST", BASE_URL, headers, post_data)

def api_get(action, headers=None, extra_params=""):
    """GET from admin-ajax.php with action"""
    url = BASE_URL + "?action=" + action
    if extra_params:
        url += "&" + extra_params
    return curl_cmd("GET", url, headers)

def rest_get(path, headers=None):
    """GET from REST API"""
    return curl_cmd("GET", REST_URL + path, headers)

def test(name, success, detail=""):
    status = "PASS" if success else "FAIL"
    results.append((name, success, detail))
    print("  " + status + " | " + name + " -- " + detail)

print("=" * 70)
print("  COMPREHENSIVE SYSTEM TEST -- Website & Android App")
print("=" * 70)

# ══════════════════════════════════════════════════════
# PART 1: AUTHENTICATION
# ══════════════════════════════════════════════════════
print("\n-- 1. AUTHENTICATION --")

r = api_post("ybt_mobile_login", {"username": "admin", "password": "admin123"})
admin_token = r.get("data", {}).get("token", "")
test("Admin Login", r.get("success") == True and len(admin_token) > 10, "Token: " + admin_token[:30] + "...")
AH = {"Authorization": "Bearer " + admin_token}

r = api_post("ybt_mobile_login", {"username": "testuser2", "password": "admin123"})
user_token = r.get("data", {}).get("token", "")
test("User Login", r.get("success") == True and len(user_token) > 10, "Role: " + str(r.get("data", {}).get("role", "?")))
UH = {"Authorization": "Bearer " + user_token}

r = api_post("ybt_mobile_login", {"username": "admin", "password": "wrong"})
test("Wrong Password Reject", r.get("success") == False, r.get("message", ""))

r = api_post("ybt_mobile_login", {"username": "nonexistent", "password": "admin123"})
test("Invalid User Reject", r.get("success") == False, r.get("message", ""))

# ══════════════════════════════════════════════════════
# PART 2: ADMIN DASHBOARD
# ══════════════════════════════════════════════════════
print("\n-- 2. ADMIN DASHBOARD --")

r = api_get("ybt_mobile_dashboard", AH)
test("Admin Dashboard Stats", r.get("success") == True, "Stats: " + str(r.get("data", {}).get("stats", {})))

r = api_get("ybt_mobile_dashboard_info", AH)
d = r.get("data", {})
test("Dashboard Info (Warehouse+Bank+Announcements)", r.get("success") == True,
     "Warehouse: " + str(d.get("warehouse_address", {}).get("address", "N/A"))[:40] + " | " +
     "Bank: " + str(d.get("payment_settings", {}).get("bank_name", "N/A")))

# ══════════════════════════════════════════════════════
# PART 3: ADMIN ORDERS
# ══════════════════════════════════════════════════════
print("\n-- 3. ADMIN ORDERS --")

r = api_get("ybt_mobile_admin_orders", AH)
orders = r.get("data", {}).get("orders", [])
test("Admin Orders List", r.get("success") == True and len(orders) > 0, "Count: " + str(len(orders)))
if orders:
    o = orders[0]
    test("Order Has Tracking #", o.get("tracking_number") is not None, "TN: " + str(o.get("tracking_number", "N/A")))
    test("Order Has Status", o.get("status") is not None, "Status: " + str(o.get("status", "N/A")))

# ══════════════════════════════════════════════════════
# PART 4: ADMIN TRACKING
# ══════════════════════════════════════════════════════
print("\n-- 4. ADMIN TRACKING --")

r = api_get("ybt_mobile_tracking", AH)
tracking = r.get("data", {}).get("tracking", [])
test("Admin Tracking List", r.get("success") == True, "Count: " + str(len(tracking)))

r = api_get("ybt_mobile_search_tracking", AH, "query=YBTTEST")
results_data = r.get("data", {}).get("results", r.get("data", {}).get("tracking", []))
test("Search Tracking", r.get("success") == True, "Results: " + str(len(results_data)))

# ══════════════════════════════════════════════════════
# PART 5: ADMIN USERS
# ══════════════════════════════════════════════════════
print("\n-- 5. ADMIN USERS --")

r = api_get("ybt_mobile_admin_users", AH)
users = r.get("data", {}).get("users", [])
test("Admin Users List", r.get("success") == True and len(users) > 0, "Count: " + str(len(users)))
if users:
    uid = users[0].get("id", users[0].get("user_id", 0))
    r2 = api_get("ybt_mobile_admin_user_details", AH, "target_user_id=" + str(uid))
    data2 = r2.get("data", {})
    profile = data2.get("profile", {}) or data2.get("user", {})
    uname = profile.get("username", "?")
    test("User Details", r2.get("success") == True and uname != "?", "User: " + str(uname))

# ══════════════════════════════════════════════════════
# PART 6: ADMIN PACKING
# ══════════════════════════════════════════════════════
print("\n-- 6. ADMIN PACKING --")

r = api_get("ybt_mobile_admin_packing_requests", AH)
batches = r.get("data", {}).get("batches", [])
test("Admin Packing Requests", r.get("success") == True, "Count: " + str(len(batches)))
if batches:
    bid = batches[0].get("batch_id", batches[0].get("id", ""))
    r2 = api_get("ybt_mobile_admin_batch_details", AH, "batch_id=" + str(bid))
    items = r2.get("data", {}).get("items", [])
    test("Batch Details", r2.get("success") == True, "Items: " + str(len(items)))

# ══════════════════════════════════════════════════════
# PART 7: ADMIN CHAT
# ══════════════════════════════════════════════════════
print("\n-- 7. ADMIN CHAT --")

r = api_get("ybt_mobile_admin_get_chats", AH)
chats = r.get("data", {}).get("chats", [])
test("Admin Chat List", r.get("success") == True and len(chats) > 0, "Conversations: " + str(len(chats)))
if chats:
    uid = chats[0].get("user_id", 0)
    r2 = api_get("ybt_mobile_admin_get_messages", AH, "target_user_id=" + str(uid))
    msgs = r2.get("data", {}).get("messages", [])
    test("Chat Messages", r2.get("success") == True and len(msgs) > 0, "Messages: " + str(len(msgs)))

    r3 = api_post("ybt_mobile_admin_send_chat", {"target_user_id": str(uid), "message": "Test from API"}, AH)
    test("Send Chat Message", r3.get("success") == True, r3.get("message", ""))

# ══════════════════════════════════════════════════════
# PART 8: ADMIN NOTIFICATIONS
# ══════════════════════════════════════════════════════
print("\n-- 8. ADMIN NOTIFICATIONS --")

r = api_get("ybt_mobile_notifications_v2", AH)
test("Admin Notifications", r.get("success") == True, "Count: " + str(len(r.get("data", []))))

# ══════════════════════════════════════════════════════
# PART 9: USER FEATURES
# ══════════════════════════════════════════════════════
print("\n-- 9. USER FEATURES --")

r = api_get("ybt_mobile_dashboard", UH)
test("User Dashboard", r.get("success") == True, "Stats: " + str(r.get("data", {}).get("stats", {})))

r = api_get("ybt_mobile_tracking", UH)
tracking = r.get("data", {}).get("tracking", [])
test("User Tracking List", r.get("success") == True, "Count: " + str(len(tracking)))

r = api_get("ybt_mobile_chat", UH)
test("User Chat", r.get("success") == True, "Messages: " + str(len(r.get("data", {}).get("messages", []))))

r = api_get("ybt_mobile_notifications_v2", UH)
test("User Notifications", r.get("success") == True, "")

# ══════════════════════════════════════════════════════
# PART 10: REST API (Website)
# ══════════════════════════════════════════════════════
print("\n-- 10. REST API (Website) --")

r = rest_get("/ybt/v1/tracking", AH)
test("REST Tracking", r.get("code") != "rest_no_route", "")

r = rest_get("/ybt/v1/stats", AH)
test("REST Stats", r.get("code") != "rest_no_route", "")

r = rest_get("/ybt/v1/app-version")
has_version = "version" in str(r) or "data" in str(r)
test("REST App Version", has_version, str(r)[:80])

r = rest_get("/ybt-admin/v1/users", AH)
test("REST Admin Users", r.get("code") != "rest_no_route", "")

r = rest_get("/ybt-admin/v1/dashboard/stats", AH)
test("REST Admin Dashboard Stats", r.get("code") != "rest_no_route", "")

r = rest_get("/ybt-admin/v1/chat/users", AH)
test("REST Admin Chat Users", r.get("code") != "rest_no_route", "")

r = rest_get("/ybt-admin/v1/packing-requests", AH)
test("REST Packing Requests", r.get("code") != "rest_no_route", "")

r = rest_get("/ybt-admin/v1/warehouse", AH)
test("REST Warehouse", r.get("code") != "rest_no_route", "")

r = rest_get("/ybt-admin/v1/tracking", AH)
test("REST Admin Tracking", r.get("code") != "rest_no_route", "")

# ══════════════════════════════════════════════════════
# PART 11: WEBSITE PAGES
# ══════════════════════════════════════════════════════
print("\n-- 11. WEBSITE PAGES --")

pages = [
    ("Home", "http://localhost/wordpress/"),
    ("Login", "http://localhost/wordpress/wp-login.php"),
    ("Dashboard", "http://localhost/wordpress/wp-admin/"),
    ("Tracking Page", "http://localhost/wordpress/wp-admin/admin.php?page=ybt-packer"),
]
for page_name, url in pages:
    try:
        r = subprocess.run(["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}", url], capture_output=True, text=True, timeout=10)
        code = r.stdout.strip()
        test("Page: " + page_name, code in ["200", "302"], "HTTP " + code)
    except:
        test("Page: " + page_name, False, "Timeout")

# ══════════════════════════════════════════════════════
# PART 12: IMAGE COMPRESSION
# ══════════════════════════════════════════════════════
print("\n-- 12. IMAGE COMPRESSION --")

# Test server-side compress_image function
php_code = (
    "require_once 'C:/xampp/htdocs/wordpress/wp-load.php';"
    "require_once 'C:/xampp/htdocs/wordpress/wp-content/plugins/ybt-packer-servicenew/includes/class-ybt-file-handler.php';"
    "$img=imagecreatetruecolor(2000,1500);"
    "imagefill($img,0,0,imagecolorallocate($img,255,0,0));"
    "$path='C:/xampp/htdocs/wordpress/wp-content/uploads/test_compress.jpg';"
    "imagejpeg($img,$path,95);"
    "$before=filesize($path);"
    "YBT_File_Handler::compress_image($path);"
    "clearstatcache(true,$path);"
    "$after=filesize($path);"
    "echo json_encode(array('before'=>$before,'after'=>$after));"
    "imagedestroy($img);"
)
r = subprocess.run([
    "C:/xampp/php/php.exe", "-r", php_code
], capture_output=True, text=True, timeout=30)

try:
    compress_data = json.loads(r.stdout)
    before_val = compress_data.get("before", 0)
    after_val = compress_data.get("after", 0)
    savings = round((1 - after_val / before_val) * 100) if before_val > 0 else 0
    test("Server-side Image Compression", after_val < before_val, str(before_val) + " bytes -> " + str(after_val) + " bytes (" + str(savings) + "% saved)")
except:
    test("Server-side Image Compression", False, r.stdout[:200] if r.stdout else r.stderr[:200])

# ══════════════════════════════════════════════════════
# PART 13: DATABASE INTEGRITY
# ══════════════════════════════════════════════════════
print("\n-- 13. DATABASE INTEGRITY --")

r = subprocess.run([
    "C:/xampp/mysql/bin/mysql.exe", "-u", "root", "wordpress", "-e",
    "SELECT COUNT(*) as cnt FROM wp_ybt_tracking;"
], capture_output=True, text=True, timeout=10)
cnt = r.stdout.strip().split("\n")[-1].strip()
test("Tracking Table Has Data", cnt.isdigit() and int(cnt) > 0, "Rows: " + cnt)

r = subprocess.run([
    "C:/xampp/mysql/bin/mysql.exe", "-u", "root", "wordpress", "-e",
    "SELECT COUNT(*) as cnt FROM wp_users WHERE ID IN (SELECT user_id FROM wp_usermeta WHERE meta_key='wp_capabilities');"
], capture_output=True, text=True, timeout=10)
cnt = r.stdout.strip().split("\n")[-1].strip()
test("Active Users in DB", cnt.isdigit() and int(cnt) > 0, "Users: " + cnt)

# ══════════════════════════════════════════════════════
# SUMMARY
# ══════════════════════════════════════════════════════
print("\n" + "=" * 70)
passed = sum(1 for _, s, _ in results if s)
failed = sum(1 for _, s, _ in results if not s)
total = len(results)
print("  TOTAL: " + str(total) + " tests | " + str(passed) + " passed | " + str(failed) + " failed")
print("=" * 70)

if failed > 0:
    print("\nFailed tests:")
    for name, s, detail in results:
        if not s:
            print("  FAIL: " + name + ": " + detail)
