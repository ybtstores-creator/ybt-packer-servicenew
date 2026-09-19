#!/usr/bin/env node
/**
 * YBT Packer Service — full feature/connectivity test.
 *
 * Exercises every API endpoint the three apps (admin Android, customer
 * Android, desktop) call, against a running WordPress stack. Defaults to the
 * local stack (docker compose up -d in local-wp/, host port 8080) because it
 * runs the current plugin code with known credentials. Pass BASE + admin
 * credentials to point it anywhere else:
 *
 *   node test-all-features.js
 *   BASE=http://127.0.0.1:8080 ADMIN_USER=admin ADMIN_PASS='Admin@ybt2026!' node test-all-features.js
 *
 * Destructive operations are only exercised in safe windows (older_than_days
 * far in the future → 0 rows) or dry-run mode.
 */
'use strict';

const http = require('http');
const BASE = (process.env.BASE || 'http://127.0.0.1:8080/').replace(/\/$/, '') + '/';
const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'Admin@ybt2026!';

// 1x1 JPEG used for multipart uploads (same fixture as the Android smoke tests).
const TINY_JPEG = Buffer.from(
  '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z',
  'base64'
);

const results = [];
function record(name, ok, detail) {
  results.push({ name, ok, detail });
  const mark = ok ? 'PASS' : 'FAIL';
  console.log(`[${mark}] ${name}${detail ? '  — ' + detail : ''}`);
}
function assert(name, cond, detail) {
  record(name, !!cond, detail || '');
  if (!cond) process.exitCode = 1;
}

function request(method, path, opts = {}) {
  return new Promise((resolve) => {
    const { form, json, headers = {}, files, uploads } = opts;
    let body = null;
    let contentType = null;
    const boundary = '----YBTTEST' + Date.now();

    if (files || uploads) {
      const chunks = [];
      const addField = (k, v) =>
        chunks.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${k}"\r\n\r\n${v}\r\n`));
      (files || []).forEach((f) => addField(f.name, f.value));
      if (json) Object.entries(json).forEach(([k, v]) => addField(k, String(v)));
      (uploads || []).forEach((f) => {
        chunks.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${f.field}"; filename="${f.filename}"\r\nContent-Type: ${f.mime}\r\n\r\n`));
        chunks.push(f.data);
        chunks.push(Buffer.from('\r\n'));
      });
      chunks.push(Buffer.from(`--${boundary}--\r\n`));
      body = Buffer.concat(chunks);
      contentType = `multipart/form-data; boundary=${boundary}`;
    } else if (form) {
      body = new URLSearchParams(form).toString();
      contentType = 'application/x-www-form-urlencoded';
    } else if (json) {
      body = JSON.stringify(json);
      contentType = 'application/json';
    }

    const u = new URL(path, BASE);
    const req = http.request(
      {
        hostname: u.hostname,
        port: u.port,
        path: u.pathname + u.search,
        method,
        headers: {
          ...(contentType ? { 'Content-Type': contentType } : {}),
          ...(body ? { 'Content-Length': Buffer.byteLength(body) } : {}),
          ...headers,
        },
        timeout: 30000,
      },
      (res) => {
        let data = '';
        res.setEncoding('utf8');
        res.on('data', (c) => (data += c));
        res.on('end', () => {
          let parsed = null;
          try { parsed = JSON.parse(data); } catch { /* non-JSON */ }
          resolve({ status: res.statusCode, body: data, json: parsed });
        });
      }
    );
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', (e) => resolve({ status: 0, body: '', json: null, error: e.message }));
    if (body) req.write(body);
    req.end();
  });
}

const bearer = (t) => ({ Authorization: `Bearer ${t}` });

async function main() {
  console.log(`Target: ${BASE}  (admin: ${ADMIN_USER})`);
  console.log('='.repeat(70));

  // ── 0. unauthenticated smoke ────────────────────────────────────────────────
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_test_connection');
    assert('test_connection (no auth)', r.status === 200 && r.json && r.json.success === true, `HTTP ${r.status}`);
  }
  {
    const r = await request('GET', 'wp-json/ybt/v1/app-version?current_version=1.0.0');
    assert('app-version (no auth)', r.status === 200 && r.json && 'latest_version' in r.json, `HTTP ${r.status} v=${r.json && r.json.latest_version}`);
  }

  // ── 1. customer: register + login ───────────────────────────────────────────
  const suffix = Date.now().toString().slice(-8);
  const custUser = 'fctest' + suffix;
  const custPass = 'Pass@' + suffix + '!';
  let custToken = null;
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_register', {
      form: { username: custUser, email: `${custUser}@example.com`, password: custPass, mobile_number: '1380000' + suffix },
    });
    assert('customer register', r.json && r.json.success === true && r.json.data && r.json.data.custom_user_id, JSON.stringify(r.json && r.json.data));
  }
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_login', {
      form: { username: custUser, password: custPass },
    });
    assert('customer login', r.json && r.json.success === true && r.json.data && r.json.data.token, r.body.slice(0, 120));
    if (r.json && r.json.data) custToken = r.json.data.token;
  }
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_login', {
      form: { username: custUser, password: 'wrong-pass-123' },
    });
    assert('customer login (bad creds rejected)', r.json && r.json.success === false, r.body.slice(0, 100));
  }

  // ── 2. customer dashboard ───────────────────────────────────────────────────
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_dashboard', { headers: bearer(custToken) });
    assert('customer dashboard stats', r.json && r.json.success === true && r.json.data && r.json.data.stats, `HTTP ${r.status}`);
  }
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_dashboard_info', { headers: bearer(custToken) });
    assert('customer dashboard info', r.json && r.json.success === true && r.json.data, `HTTP ${r.status}`);
  }

  // ── 3. customer: add 6 tracking numbers (4 sea, 2 air) ──────────────────────
  const tn = (p) => `${p}-${suffix}`;
  let addOk = 0;
  for (let i = 1; i <= 4; i++) {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_add_tracking', {
      form: { tracking_number: tn('SEA' + i), description: 'feature test sea parcel', shipping_method: 'sea', item_count: '1' },
      headers: bearer(custToken),
    });
    if (r.json && r.json.success === true) addOk++;
  }
  for (let i = 1; i <= 2; i++) {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_add_tracking', {
      form: { tracking_number: tn('AIR' + i), description: 'feature test air parcel', shipping_method: 'air', item_count: '1' },
      headers: bearer(custToken),
    });
    if (r.json && r.json.success === true) addOk++;
  }
  assert('customer add tracking (6 items)', addOk === 6, `${addOk}/6 added`);

  const seaIds = [], airIds = [], allIds = [];
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_tracking&status=all&limit=100', { headers: bearer(custToken) });
    const items = (r.json && r.json.data && r.json.data.items) || [];
    for (const it of items) {
      if (String(it.tracking_number).startsWith('SEA')) seaIds.push(it.id);
      if (String(it.tracking_number).startsWith('AIR')) airIds.push(it.id);
    }
    assert('customer tracking list (6 items fetched)', items.length >= 6 && seaIds.length === 4 && airIds.length === 2, `got ${items.length} items, sea=${seaIds.length} air=${airIds.length}`);
    allIds.push(...seaIds, ...airIds);
  }
  {
    const r = await request('GET', `wp-admin/admin-ajax.php?action=ybt_mobile_search_tracking&query=${encodeURIComponent('SEA')}`, { headers: bearer(custToken) });
    const found = (r.json && r.json.data && r.json.data.items) || [];
    assert('customer search tracking', r.json && r.json.success === true && found.length >= 4, `found ${found.length}`);
  }

  // ── 4. admin: login + mark items received ───────────────────────────────────
  let adminToken = null;
  {
    const r = await request('POST', 'wp-json/ybt-admin/v1/auth/login', { json: { username: ADMIN_USER, password: ADMIN_PASS } });
    const t = r.json && (r.json.token || (r.json.data && r.json.data.token));
    assert('admin REST login', !!t, r.body.slice(0, 120));
    adminToken = t;
  }
  let marked = 0;
  for (const id of allIds) {
    const r = await request('PUT', `wp-json/ybt-admin/v1/tracking/${id}`, { json: { status: 'received' }, headers: bearer(adminToken) });
    if (r.json && r.json.success === true) marked++;
  }
  assert('admin mark 6 items received (REST PUT)', marked === 6, `${marked}/6`);

  // ── 5. admin-ajax admin endpoints (Android admin app paths) ─────────────────
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_dashboard', { headers: bearer(adminToken) });
    assert('admin dashboard stats (ajax)', r.json && r.json.success === true && r.json.data && r.json.data.order_stats, `HTTP ${r.status}`);
  }
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_orders&status=received', { headers: bearer(adminToken) });
    assert('admin orders list (ajax)', r.json && r.json.success === true && Array.isArray(r.json.data && r.json.data.orders), `HTTP ${r.status}`);
  }
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_update_status', {
      form: { tracking_id: String(seaIds[0]), status: 'received' },
      headers: bearer(adminToken),
    });
    assert('admin update status (ajax)', r.json && r.json.success === true, r.body.slice(0, 100));
  }
  {
    // The admin Android app calls this with tracking_id + weight/description/
    // shipping_method (see YbtAdminApiService.updateItem) — test the app's
    // real parameter names to expose any server-side mismatch.
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_update_item', {
      form: { tracking_id: String(seaIds[0]), weight_kg: '2.5', description: 'updated by feature test', shipping_method: 'sea' },
      headers: bearer(adminToken),
    });
    assert('admin update item (ajax, app param names)', r.json && r.json.success === true, r.body.slice(0, 120));
  }

  // ── 6. scan barcode (creates anonymous parcel) + assign ─────────────────────
  let anonId = null;
  {
    const bc = 'SCAN-' + suffix;
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_scan_barcode', { form: { barcode: bc }, headers: bearer(adminToken) });
    assert('admin scan barcode (ajax)', r.json && r.json.success === true, r.body.slice(0, 120));
  }
  {
    const r = await request('GET', `wp-admin/admin-ajax.php?action=ybt_mobile_admin_orders&search=${encodeURIComponent('SCAN-' + suffix)}`, { headers: bearer(adminToken) });
    const orders = (r.json && r.json.data && (r.json.data.orders || r.json.data.items)) || [];
    anonId = orders.length ? orders[0].id : null;
    assert('admin scan created anonymous parcel', anonId != null, r.body.slice(0, 120));
  }
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_assign_parcel', {
      form: { tracking_id: String(anonId), target_custom_id: custUser, user_id: custUser, custom_id: custUser },
      headers: bearer(adminToken),
    });
    assert('admin assign parcel (ajax)', r.json && r.json.success === true, r.body.slice(0, 120));
  }

  // ── 7. claim parcel (customer path) ─────────────────────────────────────────
  {
    const r1 = await request('POST', 'wp-json/ybt-admin/v1/tracking', {
      json: { tracking_numbers: 'CLAIM-' + suffix, description: 'unclaimed for claim test' },
      headers: bearer(adminToken),
    });
    assert('desktop add tracking (REST)', r1.json && r1.json.success === true, r1.body.slice(0, 120));
    const r2 = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_claim_parcel', {
      form: { tracking_number: 'CLAIM-' + suffix },
      headers: bearer(custToken),
    });
    assert('customer claim parcel', r2.json && r2.json.success === true, r2.body.slice(0, 120));
  }

  // ── 8. MERGE TEST: two sea requests merge, air request is separate ──────────
  const ship = {
    shipping_company_name: 'Merge Test Logistics',
    shipping_company_address: '88 Harbor Road, Guangzhou',
    shipping_company_phone: '+8613900000000',
    shipping_mark: 'MT-' + suffix,
  };
  let batchA = null, batchB = null;
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_request_packing', {
      form: { tracking_ids: [seaIds[0], seaIds[1]].join(','), ...ship },
      headers: bearer(custToken),
    });
    assert('request packing #1 (sea x2, new batch)', r.json && r.json.success === true && r.json.data && r.json.data.merged === false, r.body.slice(0, 150));
    batchA = r.json && r.json.data && r.json.data.batch_id;
  }
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_request_packing', {
      form: { tracking_ids: [seaIds[2], seaIds[3]].join(','), ...ship },
      headers: bearer(custToken),
    });
    assert('request packing #2 (sea x2, MERGED into #1)', r.json && r.json.data && r.json.data.merged === true && r.json.data.batch_id === batchA,
      `merged=${r.json && r.json.data && r.json.data.merged} batch=${r.json && r.json.data && r.json.data.batch_id}`);
  }
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_request_packing', {
      form: { tracking_ids: [airIds[0], airIds[1]].join(','), ...ship },
      headers: bearer(custToken),
    });
    assert('request packing #3 (air x2, NEW batch — different mode)', r.json && r.json.data && r.json.data.merged === false && r.json.data.batch_id !== batchA,
      `merged=${r.json && r.json.data && r.json.data.merged} batch=${r.json && r.json.data && r.json.data.batch_id}`);
    batchB = r.json && r.json.data && r.json.data.batch_id;
  }
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_packing_requests', { headers: bearer(adminToken) });
    const batches = (r.json && r.json.data && r.json.data.batches) || [];
    const a = batches.find((b) => b.batch_id === batchA);
    const b = batches.find((x) => x.batch_id === batchB);
    assert('admin sees ONE merged batch (4 items) + one air batch (2 items)',
      r.json && r.json.success === true && a && b && Number(a.item_count) === 4 && Number(b.item_count) === 2,
      `batchA=${a && a.item_count} batchB=${b && b.item_count}`);
  }
  {
    const r = await request('GET', `wp-admin/admin-ajax.php?action=ybt_mobile_admin_batch_details&batch_id=${encodeURIComponent(batchA)}`, { headers: bearer(adminToken) });
    assert('admin batch details (4 items, single batch)', r.json && r.json.success === true && (r.json.data.items || []).length === 4, `items=${r.json && r.json.data && r.json.data.items.length}`);
  }
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_packed_batches', { headers: bearer(custToken) });
    const batches = (r.json && r.json.data && r.json.data.batches) || [];
    const a = batches.find((b) => b.batch_id === batchA);
    assert('customer packed batches shows merged batch', !!a && Number(a.item_count) === 4, `item_count=${a && a.item_count}`);
  }

  // ── 9. payment evidence upload + cartons (packing completion) ───────────────
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_upload_payment', {
      files: [{ name: 'note', value: 'x' }],
      json: { batch_id: batchA },
      headers: bearer(custToken),
      uploads: [{ field: 'payment_evidence', filename: 'receipt.jpg', mime: 'image/jpeg', data: TINY_JPEG }],
    });
    assert('customer upload payment evidence (multipart)', r.json && r.json.success === true, r.body.slice(0, 150));
  }
  {
    const cartons = JSON.stringify([
      { weight: 10.5, payment: 220, currency: 'CNY' },
      { weight: 8.2, payment: 180, currency: 'CNY' },
    ]);
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_save_cartons', {
      form: { batch_id: batchA, carton_data: cartons },
      headers: bearer(adminToken),
    });
    assert('admin save cartons + complete packing', r.json && r.json.success === true, r.body.slice(0, 150));
  }

  // ── 10. admin: users, user details, image upload ────────────────────────────
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_users', { headers: bearer(adminToken) });
    assert('admin users list', r.json && r.json.success === true && Array.isArray(r.json.data && r.json.data.users), `HTTP ${r.status}`);
  }
  {
    const r1 = await request('GET', `wp-admin/admin-ajax.php?action=ybt_mobile_admin_users&search=${encodeURIComponent(custUser)}`, { headers: bearer(adminToken) });
    const users = (r1.json && r1.json.data && r1.json.data.users) || [];
    const uid = users.length ? users[0].id : null;
    const r2 = await request('GET', `wp-admin/admin-ajax.php?action=ybt_mobile_admin_user_details&target_user_id=${uid}`, { headers: bearer(adminToken) });
    assert('admin user details', r2.json && r2.json.success === true && r2.json.data && r2.json.data.profile, r2.body.slice(0, 120));
  }
  {
    const r = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_upload_image', {
      files: [{ name: 'note', value: 'x' }],
      json: { tracking_id: String(seaIds[0]) },
      headers: bearer(adminToken),
      uploads: [{ field: 'image', filename: 'parcel.jpg', mime: 'image/jpeg', data: TINY_JPEG }],
    });
    assert('admin upload image (multipart)', r.json && r.json.success === true, r.body.slice(0, 150));
  }

  // ── 11. chat (customer + admin) ─────────────────────────────────────────────
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_chat', { headers: bearer(custToken) });
    assert('customer chat get messages', r.json && r.json.success === true, `HTTP ${r.status}`);
    const r2 = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_send_chat', {
      form: { message: 'hello from feature test ' + suffix },
      headers: bearer(custToken),
    });
    assert('customer chat send message', r2.json && r2.json.success === true, r2.body.slice(0, 100));
    const r3 = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_send_chat', {
      files: [{ name: 'message', value: 'with attachment ' + suffix }],
      headers: bearer(custToken),
      uploads: [{ field: 'attachment', filename: 'chat.jpg', mime: 'image/jpeg', data: TINY_JPEG }],
    });
    assert('customer chat send with attachment (multipart)', r3.json && r3.json.success === true, r3.body.slice(0, 100));
  }
  {
    const r = await request('GET', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_get_chats', { headers: bearer(adminToken) });
    assert('admin chat get chats', r.json && r.json.success === true && Array.isArray(r.json.data && r.json.data.chats), `HTTP ${r.status}`);
    const chats = (r.json && r.json.data && r.json.data.chats) || [];
    const uid = chats.length ? chats[0].user_id : null;
    const r2 = await request('GET', `wp-admin/admin-ajax.php?action=ybt_mobile_admin_get_messages&target_user_id=${uid}`, { headers: bearer(adminToken) });
    assert('admin chat get messages', r2.json && r2.json.success === true, `HTTP ${r2.status}`);
    const r3 = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_send_chat', {
      form: { target_user_id: String(uid), message: 'reply from admin ' + suffix },
      headers: bearer(adminToken),
    });
    assert('admin chat send reply', r3.json && r3.json.success === true, r3.body.slice(0, 100));
  }

  // ── 12. data management + AI (safe windows only) ────────────────────────────
  {
    const r = await request('GET', 'wp-json/ybt-admin/v1/data/stats', { headers: bearer(adminToken) });
    assert('admin data/stats (REST)', r.json && r.json.success === true && r.json.data, `HTTP ${r.status}`);
    const r2 = await request('POST', 'wp-json/ybt-admin/v1/data/preview', {
      json: { type: 'chat', older_than_days: 9999 },
      headers: bearer(adminToken),
    });
    assert('admin data/preview (REST)', r2.json && r2.json.success === true && 'count' in r2.json.data, r2.body.slice(0, 120));
    const r3 = await request('POST', 'wp-json/ybt-admin/v1/data/delete', {
      json: { type: 'chat', older_than_days: 9999, confirm: true },
      headers: bearer(adminToken),
    });
    assert('admin data/delete (REST, safe window, 0 rows)', r3.json && r3.json.success === true, r3.body.slice(0, 120));
  }
  {
    const r = await request('GET', 'wp-json/ybt-admin/v1/ai/diagnostics', { headers: bearer(adminToken) });
    assert('admin ai/diagnostics (REST)', r.json && r.json.success === true && r.json.data && 'health_score' in r.json.data, `HTTP ${r.status}`);
    const r2 = await request('POST', 'wp-json/ybt-admin/v1/ai/prune', { json: { months: 5, dry_run: true }, headers: bearer(adminToken) });
    assert('admin ai/prune dry-run (REST)', r2.json && r2.json.success === true, r2.body.slice(0, 120));
  }

  // ── 13. settings + tools (GET reads only) ───────────────────────────────────
  const getSettings = async (path, label) => {
    const r = await request('GET', path, { headers: bearer(adminToken) });
    assert(`settings ${label}`, r.json && r.json.success === true, `HTTP ${r.status}`);
  };
  await getSettings('wp-json/ybt-admin/v1/settings/general', 'general');
  await getSettings('wp-json/ybt-admin/v1/warehouse', 'warehouse');
  await getSettings('wp-json/ybt-admin/v1/settings/fcm', 'fcm');
  await getSettings('wp-json/ybt-admin/v1/settings/payment', 'payment');
  await getSettings('wp-json/ybt-admin/v1/settings/email', 'email');
  await getSettings('wp-json/ybt-admin/v1/settings/security', 'security');
  await getSettings('wp-json/ybt-admin/v1/tools', 'tools');
  await getSettings('wp-json/ybt-admin/v1/app-update', 'app-update');
  await getSettings('wp-json/ybt-admin/v1/payment-evidence', 'payment-evidence');
  {
    const r = await request('POST', 'wp-json/ybt-admin/v1/export', { json: { type: 'tracking' }, headers: bearer(adminToken) });
    assert('admin export (REST)', r.status === 200 && r.json && r.json.success === true && 'count' in r.json.data, `HTTP ${r.status} ${r.body.slice(0, 80)}`);
  }

  // ── 14. desktop-app REST paths ──────────────────────────────────────────────
  {
    const r = await request('GET', 'wp-json/ybt-admin/v1/tracking?limit=10', { headers: bearer(adminToken) });
    assert('desktop tracking list (REST)', r.json && r.json.success === true, `HTTP ${r.status}`);
    const r2 = await request('GET', 'wp-json/ybt-admin/v1/users?limit=10', { headers: bearer(adminToken) });
    assert('desktop users list (REST)', r2.json && r2.json.success === true, `HTTP ${r2.status}`);
    const r3 = await request('GET', 'wp-json/ybt-admin/v1/dashboard/stats', { headers: bearer(adminToken) });
    assert('desktop dashboard stats (REST)', r3.json && r3.json.success === true, `HTTP ${r3.status}`);
    const r4 = await request('POST', 'wp-json/ybt-admin/v1/scan', {
      json: { barcode: 'DESKSCAN-' + suffix, status: 'received' },
      headers: bearer(adminToken),
    });
    assert('desktop scan (REST)', r4.json && r4.json.success === true, r4.body.slice(0, 120));
  }

  // ── 15. reset password (test user, last so it can't break earlier tests) ────
  {
    const r = await request('GET', `wp-admin/admin-ajax.php?action=ybt_mobile_admin_users&search=${encodeURIComponent(custUser)}`, { headers: bearer(adminToken) });
    const users = (r.json && r.json.data && r.json.data.users) || [];
    const uid = users.length ? users[0].id : null;
    const r2 = await request('POST', 'wp-admin/admin-ajax.php?action=ybt_mobile_admin_reset_password', {
      form: { target_user_id: String(uid), new_password: 'Reset@' + suffix + '!' },
      headers: bearer(adminToken),
    });
    assert('admin reset password (ajax)', r2.json && r2.json.success === true, r2.body.slice(0, 120));
  }

  // ── summary ─────────────────────────────────────────────────────────────────
  const pass = results.filter((r) => r.ok).length;
  const fail = results.length - pass;
  console.log('='.repeat(70));
  console.log(`SUMMARY: ${pass} passed, ${fail} failed (of ${results.length})`);
  if (fail) {
    console.log('FAILED:');
    results.filter((r) => !r.ok).forEach((r) => console.log(`  - ${r.name}: ${r.detail}`));
  }
  process.exit(fail ? 1 : 0);
}

main().catch((e) => {
  console.error('FATAL:', e);
  process.exit(1);
});
