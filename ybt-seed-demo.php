<?php
/**
 * YBT Demo Data Seeder
 *
 * Fills the database with realistic demo data so the app has content to show:
 * customers, tracking parcels across all statuses, packing batches with
 * cartons, WhatsApp-style chat threads, and edit-history audit entries.
 *
 * Usage: php ybt-seed-demo.php
 * Idempotent: skips rows that already exist (unique keys), safe to re-run.
 */

if (PHP_SAPI !== 'cli') { echo "CLI only\n"; exit(1); }

define('ABSPATH', 'C:/xampp/htdocs/wordpress/');
require ABSPATH . 'wp-load.php';

if (!current_user_can('manage_options') && php_sapi_name() !== 'cli') { exit; }

echo "=== YBT Demo Data Seeder ===\n";

// ---------------------------------------------------------------------------
// 1. Demo customers (subscriber role) — create if missing, set custom IDs.
// ---------------------------------------------------------------------------
$customers = array(
    array('login' => 'chioma',  'name' => 'Chioma Eze',      'phone' => '+2348031112233', 'custom' => 'YBT1001'),
    array('login' => 'emeka',   'name' => 'Emeka Obi',       'phone' => '+2348052223344', 'custom' => 'YBT1002'),
    array('login' => 'amara',   'name' => 'Amara Nwosu',     'phone' => '+2348073334455', 'custom' => 'YBT1003'),
    array('login' => 'tunde',   'name' => 'Tunde Adeyemi',   'phone' => '+2348094445566', 'custom' => 'YBT1004'),
    array('login' => 'fatima',  'name' => 'Fatima Bello',    'phone' => '+2348015556677', 'custom' => 'YBT1005'),
    array('login' => 'kemi',    'name' => 'Kemi Adebayo',    'phone' => '+2348026667788', 'custom' => 'YBT1006'),
    array('login' => 'uzoma',   'name' => 'Uzoma Kalu',      'phone' => '+2348037778899', 'custom' => 'YBT1007'),
    array('login' => 'ngozi',   'name' => 'Ngozi Okafor',    'phone' => '+2348068889900', 'custom' => 'YBT1008'),
);

$customer_ids = array();
foreach ($customers as $c) {
    $existing = get_user_by('login', $c['login']);
    if ($existing) {
        $customer_ids[$c['login']] = $existing->ID;
        echo "user exists: {$c['login']} (ID {$existing->ID})\n";
    } else {
        $uid = wp_insert_user(array(
            'user_login'   => $c['login'],
            'user_pass'    => 'Demo2026',
            'user_email'   => $c['login'] . '@demo.ybt',
            'display_name' => $c['name'],
            'role'         => 'subscriber',
        ));
        if (is_wp_error($uid)) { echo "FAIL user {$c['login']}: " . $uid->get_error_message() . "\n"; continue; }
        $customer_ids[$c['login']] = $uid;
        echo "created user: {$c['login']} (ID $uid, password Demo2026)\n";
    }
    $uid = $customer_ids[$c['login']];
    if (!get_user_meta($uid, 'ybt_custom_user_id', true)) update_user_meta($uid, 'ybt_custom_user_id', $c['custom']);
    if (!get_user_meta($uid, 'ybt_mobile_number', true)) update_user_meta($uid, 'ybt_mobile_number', $c['phone']);
}

// Ensure testuser has a display name so chat screens look right.
if (get_user_by('login', 'testuser')) {
    $tu = get_user_by('login', 'testuser');
    if (empty($tu->display_name)) wp_update_user(array('ID' => $tu->ID, 'display_name' => 'Test User'));
}

// ---------------------------------------------------------------------------
// 2. Tracking parcels across all statuses.
// ---------------------------------------------------------------------------
global $wpdb;
$tracking_table = $wpdb->prefix . 'ybt_tracking';
$edits_table    = $wpdb->prefix . 'ybt_tracking_edits';
$cartons_table  = $wpdb->prefix . 'ybt_cartons';
$chat_table     = $wpdb->prefix . 'ybt_chat';

$addresses = array(
    'No. 12 Adeola Odeku Street, Victoria Island, Lagos, Nigeria',
    '45 Ahmadu Bello Way, Kano, Nigeria',
    '8 Riverview Court, Lekki Phase 1, Lagos, Nigeria',
    'Plot 7, Trans-Ekulu, Enugu, Nigeria',
    '23 Bodija Estate, Ibadan, Oyo, Nigeria',
);

$descriptions = array(
    'Assorted women\u0027s clothing (bales)', 'iPhone 15 Pro Max + accessories', 'Hair extensions and wigs',
    'Kids\u0027 toys and school supplies', 'Kitchen appliances (blender, air fryer)', 'Sneakers — 30 pairs assorted sizes',
    'Laptop HP EliteBook + dock', 'Fabric: Ankara prints (6 rolls)', 'Phone cases and chargers wholesale',
    'Auto spare parts (filters, belts)', 'Cosmetics and skincare box', 'Generators spare parts',
);

$item_types  = array('unit', 'sets', 'packs');
$ship_methods = array('sea', 'air', 'express');
$status_flow = array('not_received', 'received', 'packing_in_progress', 'packed', 'completed');

$seeded_tracking = 0;
$batch_counters = array('sea' => 0, 'air' => 0, 'express' => 0);
$mk_batch = function ($method, $date) use (&$batch_counters) {
    $batch_counters[$method]++;
    return sprintf('BATCH_%s_%s%02d', strtoupper($method), date('Ymd', strtotime($date)), $batch_counters[$method]);
};

for ($i = 1; $i <= 40; $i++) {
    $tn = sprintf('YBT-DEMO-%04d', $i);
    if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $tracking_table WHERE tracking_number = %s", $tn))) {
        continue; // already seeded
    }
    $login     = $customers[array_rand($customers)]['login'];
    $uid       = $customer_ids[$login];
    $user      = get_userdata($uid);
    $status    = $status_flow[min(4, (int) floor($i / 8))]; // spread across statuses
    $desc      = $descriptions[($i - 1) % count($descriptions)];
    $addr      = $addresses[($i - 1) % count($addresses)];
    $method    = $ship_methods[($i - 1) % count($ship_methods)];
    $added     = date('Y-m-d H:i:s', strtotime("-" . (45 - $i) . " days -" . rand(0, 20) . " hours"));
    $received  = null; $packing = null; $packed = null; $completed = null;
    $batch_id  = null; $cartons_n = null; $weight = null; $pay_amt = null; $pay_cur = null;

    if (in_array($status, array('received', 'packing_in_progress', 'packed', 'completed'))) {
        $received = date('Y-m-d H:i:s', strtotime($added . ' +2 days'));
    }
    if (in_array($status, array('packing_in_progress', 'packed', 'completed'))) {
        $packing = date('Y-m-d H:i:s', strtotime($received . ' +1 day'));
        $batch_id = $mk_batch($method, $received);
    }
    if (in_array($status, array('packed', 'completed'))) {
        $packed = date('Y-m-d H:i:s', strtotime($packing . ' +2 days'));
        $cartons_n = rand(2, 9);
        $weight = rand(12, 120) + 0.5;
        $pay_amt = round($weight * ($method === 'air' ? 12.5 : ($method === 'express' ? 15 : 4.2)), 2);
        $pay_cur = 'CNY';
    }
    if ($status === 'completed') {
        $completed = date('Y-m-d H:i:s', strtotime($packed . ' +3 days'));
    }

    $inserted = $wpdb->insert($tracking_table, array(
        'tracking_number'    => $tn,
        'user_id'            => $uid,
        'username'           => $user ? $user->user_login : '',
        'phone'              => get_user_meta($uid, 'ybt_mobile_number', true),
        'description'        => $desc,
        'status'             => $status,
        'item_count'         => rand(1, 60),
        'item_type'          => $item_types[($i - 1) % count($item_types)],
        'is_complete'        => $status === 'completed' ? 1 : 0,
        'shipping_method'    => $method,
        'shipping_address'   => $addr,
        'shipping_mark'      => strtoupper($login) . '-' . $i,
        'cartons'            => $cartons_n,
        'weight_kg'          => $weight,
        'payment_amount'     => $pay_amt,
        'payment_currency'   => $pay_cur,
        'date_added'         => $added,
        'date_received'      => $received,
        'date_packing_requested' => $packing,
        'date_packed'        => $packed,
        'date_completed'     => $completed,
        'last_edited_by'     => 1,
        'last_edited_by_name'=> 'admin',
        'last_edited_at'     => $wpdb->get_var("SELECT NOW()"),
        'edit_count'         => rand(0, 6),
    ));

    if (!$inserted) { echo "FAIL tracking $tn: " . $wpdb->last_error . "\n"; continue; }
    $seeded_tracking++;
    $tracking_id = $wpdb->insert_id;

    // Audit entries for this parcel's journey.
    $edits = array();
    $edits[] = array('action' => 'Created',  'summary' => 'Parcel created and announced', 'field' => null, 'old' => null, 'new' => $status, 'date' => $added);
    if ($received)  $edits[] = array('action' => 'Status Change', 'summary' => 'Status: Not Received → Received', 'field' => 'status', 'old' => 'not_received', 'new' => 'received', 'date' => $received);
    if ($packing)   $edits[] = array('action' => 'Status Change', 'summary' => 'Status: Received → Packing In Progress', 'field' => 'status', 'old' => 'received', 'new' => 'packing_in_progress', 'date' => $packing);
    if ($packed) {
        $edits[] = array('action' => 'Status Change', 'summary' => 'Status: Packing In Progress → Packed', 'field' => 'status', 'old' => 'packing_in_progress', 'new' => 'packed', 'date' => $packed);
        $edits[] = array('action' => 'Edit', 'summary' => 'Edited: Cartons, Weight (kg)', 'field' => 'cartons', 'old' => null, 'new' => $cartons_n, 'date' => $packed);
        $edits[] = array('action' => 'Edit', 'summary' => 'Edited: Weight (kg)', 'field' => 'weight_kg', 'old' => null, 'new' => $weight, 'date' => $packed);
    }
    if ($completed) $edits[] = array('action' => 'Status Change', 'summary' => 'Status: Packed → Completed', 'field' => 'status', 'old' => 'packed', 'new' => 'completed', 'date' => $completed);

    foreach ($edits as $e) {
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $edits_table WHERE tracking_id = %d AND action = %s AND date_created = %s", $tracking_id, $e['action'], $e['date']))) {
            continue;
        }
        $wpdb->insert($edits_table, array(
            'tracking_id'   => $tracking_id,
            'tracking_number' => $tn,
            'admin_id'      => 1,
            'admin_name'    => 'admin',
            'action'        => $e['action'],
            'summary'       => $e['summary'],
            'changes'       => wp_json_encode($e['field'] ? array(array('field' => $e['field'], 'old' => $e['old'], 'new' => $e['new'])) : array()),
            'source'        => 'web',
            'ip_address'    => '127.0.0.1',
            'date_created'  => $e['date'],
        ));
    }
}
echo "tracking seeded: $seeded_tracking\n";

// ---------------------------------------------------------------------------
// 3. Cartons for packed parcels (batch_id set).
// ---------------------------------------------------------------------------
$packed = $wpdb->get_results("SELECT id, tracking_number, batch_id, cartons, weight_kg FROM $tracking_table WHERE batch_id IS NOT NULL AND tracking_number LIKE 'YBT-DEMO-%'");
$seeded_cartons = 0;
foreach ($packed as $p) {
    if (!$p->cartons) continue;
    $have = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $cartons_table WHERE batch_id = %s", $p->batch_id)));
    if ($have >= intval($p->cartons)) continue;
    for ($c = $have + 1; $c <= intval($p->cartons); $c++) {
        $wpdb->insert($cartons_table, array(
            'batch_id'      => $p->batch_id,
            'carton_number' => $c,
            'weight_kg'     => round(floatval($p->weight_kg) / intval($p->cartons), 2),
            'payment_amount'=> round(floatval($p->payment_amount) / intval($p->cartons), 2),
            'payment_currency' => 'CNY',
            'date_created'  => current_time('mysql'),
        ));
        $seeded_cartons++;
    }
}
echo "cartons seeded: $seeded_cartons\n";

// ---------------------------------------------------------------------------
// 4. Chat threads (one per customer, WhatsApp-flavoured).
// ---------------------------------------------------------------------------
$threads = array(
    'chioma' => array(
        array(0, 'Hi, has my package arrived at the warehouse?', '-6 days'),
        array(1, 'Yes Chioma! All 3 parcels arrived this morning. We are preparing them for packing.', '-6 days +5 minutes'),
        array(0, 'Great! Please pack the clothes together, they are fragile.', '-6 days +10 minutes'),
        array(1, 'Noted. We will send photos of the cartons once packed. 📦', '-6 days +15 minutes'),
        array(0, 'Thank you! 🙏', '-5 days'),
    ),
    'emeka' => array(
        array(0, 'What is the shipping cost for 45kg to Lagos?', '-4 days'),
        array(1, 'Sea freight is 4.2 CNY/kg → about 189 CNY. Air would be 12.5 CNY/kg.', '-4 days +8 minutes'),
        array(0, 'Sea is fine. Please proceed.', '-4 days +1 hour'),
    ),
    'amara' => array(
        array(1, 'Amara, your batch is packed: 6 cartons, 68.5kg. Invoice attached in your dashboard.', '-3 days'),
        array(0, 'Awesome, I will pay today and upload the receipt.', '-3 days +30 minutes'),
        array(1, 'Perfect, we ship within 2-3 business days after confirmation.', '-3 days +45 minutes'),
        array(0, 'Payment uploaded! Check when you can 📸', '-2 days'),
    ),
    'tunde' => array(
        array(0, 'Do you ship generators spare parts?', '-8 days'),
        array(1, 'Yes we do, via sea freight. Any restrictions depend on size — share the description when you create the parcel.', '-8 days +20 minutes'),
    ),
    'fatima' => array(
        array(0, 'My tracking YBT-DEMO-0021 shows packed. When does it ship?', '-1 days'),
        array(1, 'Ships tomorrow on the weekly container. You will get the shipping mark photo 🚚', '-1 days +12 minutes'),
        array(0, 'Perfect, thanks!', '-22 hours'),
    ),
    'kemi' => array(
        array(0, 'Hello, I want to send 30 pairs of sneakers next week.', '-12 hours'),
        array(1, 'Great! Create the parcel in the app with the app \u0022+ Add\u0022 tab, and mention shoe sizes in the description.', '-11 hours'),
    ),
);

$seeded_chat = 0;
foreach ($threads as $login => $msgs) {
    $uid = $customer_ids[$login] ?? 0;
    if (!$uid) continue;
    $user = get_userdata($uid);
    $have = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $chat_table WHERE user_id = %d AND message LIKE '%%[demo]%%'", $uid)));
    foreach ($msgs as $idx => $m) {
        if ($idx < $have) continue; // already seeded part of this thread
        $wpdb->insert($chat_table, array(
            'user_id'      => $uid,
            'username'     => $user->user_login,
            'message'      => $m[1] . ' [demo]',
            'attachment'   => '',
            'is_admin'     => $m[0],
            'date_created' => date('Y-m-d H:i:s', strtotime($m[2])),
            'is_read'      => $m[0] ? 1 : 1, // history is read; keeps badges clean
        ));
        $seeded_chat++;
    }
}
echo "chat messages seeded: $seeded_chat\n";

// ---------------------------------------------------------------------------
// 4b. BACKFILL: repair first-pass gaps for parcels that were seeded before
//      the fixes (missing batch_id, missing audit entries, missing cartons).
// ---------------------------------------------------------------------------
$backfill_parcels = $wpdb->get_results(
    "SELECT * FROM $tracking_table WHERE tracking_number LIKE 'YBT-DEMO-%' ORDER BY id ASC"
);
$batch_counters2 = array('sea' => 0, 'air' => 0, 'express' => 0);
$mk_batch2 = function ($method, $date) use (&$batch_counters2) {
    $batch_counters2[$method]++;
    return sprintf('BATCH_%s_%s%02d', strtoupper($method), date('Ymd', strtotime($date)), $batch_counters2[$method]);
};
$backfilled_batches = 0; $backfilled_edits = 0; $backfilled_cartons = 0;

foreach ($backfill_parcels as $p) {
    $status = $p->status;
    $needs_batch = in_array($status, array('packing_in_progress', 'packed', 'completed')) && empty($p->batch_id);
    $edit_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $edits_table WHERE tracking_id = %d", $p->id)));
    $needs_edits = $edit_count === 0;
    if (!$needs_batch && !$needs_edits && !empty($p->cartons)) continue;

    $added    = $p->date_added;
    $received = $p->date_received ?: date('Y-m-d H:i:s', strtotime($added . ' +2 days'));
    $packing  = $p->date_packing_requested ?: date('Y-m-d H:i:s', strtotime($received . ' +1 day'));
    $packed   = $p->date_packed ?: date('Y-m-d H:i:s', strtotime($packing . ' +2 days'));
    $completed= $p->date_completed ?: date('Y-m-d H:i:s', strtotime($packed . ' +3 days'));

    $batch_id = $p->batch_id;
    if ($needs_batch) {
        $batch_id = $mk_batch2($p->shipping_method, $received);
        $wpdb->update($tracking_table, array('batch_id' => $batch_id), array('id' => $p->id));
        $backfilled_batches++;
    }

    $cartons_n = $p->cartons;
    $weight    = $p->weight_kg;
    if (in_array($status, array('packed', 'completed')) && (empty($cartons_n) || empty($weight))) {
        $cartons_n = $cartons_n ?: rand(2, 9);
        $weight    = $weight ?: (rand(12, 120) + 0.5);
        $wpdb->update($tracking_table, array('cartons' => $cartons_n, 'weight_kg' => $weight), array('id' => $p->id));
    }

    if ($needs_edits) {
        $edits = array();
        $edits[] = array('action' => 'Created',  'summary' => 'Parcel created and announced', 'field' => null, 'old' => null, 'new' => null, 'date' => $added);
        if (!empty($p->date_received) || in_array($status, array('received', 'packing_in_progress', 'packed', 'completed')))
            $edits[] = array('action' => 'Status Change', 'summary' => 'Status: Not Received → Received', 'field' => 'status', 'old' => 'not_received', 'new' => 'received', 'date' => $received);
        if (in_array($status, array('packing_in_progress', 'packed', 'completed')))
            $edits[] = array('action' => 'Status Change', 'summary' => 'Status: Received → Packing In Progress', 'field' => 'status', 'old' => 'received', 'new' => 'packing_in_progress', 'date' => $packing);
        if (in_array($status, array('packed', 'completed'))) {
            $edits[] = array('action' => 'Status Change', 'summary' => 'Status: Packing In Progress → Packed', 'field' => 'status', 'old' => 'packing_in_progress', 'new' => 'packed', 'date' => $packed);
            $edits[] = array('action' => 'Edit', 'summary' => 'Edited: Cartons', 'field' => 'cartons', 'old' => null, 'new' => $cartons_n, 'date' => $packed);
            $edits[] = array('action' => 'Edit', 'summary' => 'Edited: Weight (kg)', 'field' => 'weight_kg', 'old' => null, 'new' => $weight, 'date' => $packed);
        }
        if ($status === 'completed')
            $edits[] = array('action' => 'Status Change', 'summary' => 'Status: Packed → Completed', 'field' => 'status', 'old' => 'packed', 'new' => 'completed', 'date' => $completed);

        foreach ($edits as $e) {
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $edits_table WHERE tracking_id = %d AND action = %s AND date_created = %s", $p->id, $e['action'], $e['date']))) {
                continue;
            }
            $wpdb->insert($edits_table, array(
                'tracking_id'     => $p->id,
                'tracking_number' => $p->tracking_number,
                'admin_id'        => 1,
                'admin_name'      => 'admin',
                'action'          => $e['action'],
                'summary'         => $e['summary'],
                'changes'         => wp_json_encode($e['field'] ? array(array('field' => $e['field'], 'old' => $e['old'], 'new' => $e['new'])) : array()),
                'source'          => 'web',
                'ip_address'      => '127.0.0.1',
                'date_created'    => $e['date'],
            ));
            $backfilled_edits++;
        }
    }

    // Cartons for packed/completed parcels that still lack them.
    if (in_array($status, array('packed', 'completed')) && !empty($batch_id) && !empty($cartons_n)) {
        $have = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $cartons_table WHERE batch_id = %s", $batch_id)));
        for ($c = $have + 1; $c <= intval($cartons_n); $c++) {
            $wpdb->insert($cartons_table, array(
                'batch_id'      => $batch_id,
                'carton_number' => $c,
                'weight_kg'     => round(floatval($weight) / intval($cartons_n), 2),
                'payment_amount'=> round(floatval($p->payment_amount ?: 0) / intval($cartons_n), 2),
                'payment_currency' => 'CNY',
                'date_created'  => current_time('mysql'),
            ));
            $backfilled_cartons++;
        }
    }
}
echo "backfill: batches=$backfilled_batches edits=$backfilled_edits cartons=$backfilled_cartons\n";

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "\n=== Summary ===\n";
echo "tracking rows: " . $wpdb->get_var("SELECT COUNT(*) FROM $tracking_table") . "\n";
echo "edit-history rows: " . $wpdb->get_var("SELECT COUNT(*) FROM $edits_table") . "\n";
echo "carton rows: " . $wpdb->get_var("SELECT COUNT(*) FROM $cartons_table") . "\n";
echo "chat rows: " . $wpdb->get_var("SELECT COUNT(*) FROM $chat_table") . "\n";
echo "Demo customer password: Demo2026\n";
echo "Done.\n";
