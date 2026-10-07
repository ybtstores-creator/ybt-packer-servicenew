<?php
/**
 * YBT Notifications — web AJAX endpoints
 *
 * Browser-side counterparts of the mobile inbox endpoints (navbar bell).
 * House pattern: check_ajax_referer('ybt_nonce') + is_user_logged_in().
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * List the current user's inbox (marks listed rows read, like the app).
 */
function ybt_get_notifications_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_notifications';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === null) {
        wp_send_json_success(array('items' => array(), 'has_more' => false));
        return;
    }

    $user_id = get_current_user_id();
    $limit = max(1, min(100, intval($_REQUEST['limit'] ?? 25)));
    $offset = max(0, intval($_REQUEST['offset'] ?? 0));

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, body, type, tracking_id, tracking_number, is_read, date_created
           FROM {$table}
          WHERE user_id = %d
          ORDER BY date_created DESC, id DESC
          LIMIT %d OFFSET %d",
        $user_id,
        $limit + 1,
        $offset
    ));

    $has_more = $rows && count($rows) > $limit;
    if ($has_more) {
        array_pop($rows);
    }

    $items = array();
    foreach ((array) $rows as $row) {
        $items[] = array(
            'id' => (int) $row->id,
            'title' => $row->title,
            'body' => $row->body,
            'type' => $row->type,
            'tracking_id' => $row->tracking_id ? (int) $row->tracking_id : null,
            'tracking_number' => $row->tracking_number,
            'is_read' => (int) $row->is_read,
            'created_at' => $row->date_created,
        );
    }

    // Only the rows actually rendered become read — a 8-item bell preview must
    // not silently consume the rest of the inbox. Announcements are excluded:
    // they stay unread until dismissed through the dedicated login-popup
    // endpoint, otherwise the popup at the next login would never fire.
    $ids = array();
    foreach ($items as $item) {
        if ($item['type'] !== 'announcement') {
            $ids[] = $item['id'];
        }
    }
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_read = 1
              WHERE user_id = %d AND is_read = 0 AND id IN ($placeholders)",
            array_merge(array($user_id), $ids)
        ));
    }

    $unread = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
        $user_id
    ));

    wp_send_json_success(array('items' => $items, 'has_more' => $has_more, 'unread_count' => $unread));
}
add_action('wp_ajax_ybt_get_notifications', 'ybt_get_notifications_handler');

/**
 * Mark one (id=N) or all (all=1) notifications read.
 */
function ybt_mark_notifications_read_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_notifications';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === null) {
        wp_send_json_success(array('updated' => 0));
        return;
    }

    $user_id = get_current_user_id();
    $all = !empty($_POST['all']);
    $id = intval($_POST['id'] ?? 0);

    if ($all) {
        $updated = $wpdb->update($table, array('is_read' => 1), array('user_id' => $user_id, 'is_read' => 0));
    } elseif ($id > 0) {
        $updated = $wpdb->update($table, array('is_read' => 1), array('id' => $id, 'user_id' => $user_id, 'is_read' => 0));
    } else {
        wp_send_json_error('id or all required');
        return;
    }

    wp_send_json_success(array('updated' => (int) $updated));
}
add_action('wp_ajax_ybt_mark_notifications_read', 'ybt_mark_notifications_read_handler');

/**
 * Badge poll: unread count + newest id.
 */
function ybt_notifications_unread_count_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_notifications';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === null) {
        wp_send_json_success(array('last_id' => 0, 'unread' => 0));
        return;
    }

    $user_id = get_current_user_id();
    $last_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(id) FROM {$table} WHERE user_id = %d",
        $user_id
    ));
    $unread = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
        $user_id
    ));

    wp_send_json_success(array('last_id' => $last_id, 'unread' => $unread));
}
add_action('wp_ajax_ybt_notifications_unread_count', 'ybt_notifications_unread_count_handler');

/**
 * Parcel timeline for the details modal (web counterpart of the mobile
 * ybt_mobile_parcel_timeline endpoint; same house pattern + IDOR guard).
 */
function ybt_get_parcel_timeline_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }

    global $wpdb;

    $tracking_id = isset($_REQUEST['tracking_id']) ? intval($_REQUEST['tracking_id']) : 0;
    if ($tracking_id <= 0) {
        wp_send_json_error('tracking_id required');
        return;
    }

    $user_id  = get_current_user_id();
    $is_admin = current_user_can('manage_options');

    // IDOR guard: the parcel must belong to the caller (admins see any).
    $owned = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}ybt_tracking
          WHERE id = %d AND (user_id = %d OR username = %s)",
        $tracking_id,
        $user_id,
        wp_get_current_user()->user_login
    ));
    if (!$owned && !$is_admin) {
        wp_send_json_error('Parcel not found');
        return;
    }

    $limit  = isset($_REQUEST['limit'])  ? max(1, min(500, intval($_REQUEST['limit'])))  : 25;
    $offset = isset($_REQUEST['offset']) ? max(0, intval($_REQUEST['offset'])) : 0;

    if (!class_exists('YBT_Tracking_Events')) {
        wp_send_json_success(array('items' => array(), 'has_more' => false));
        return;
    }

    // Pass the viewer's id so a reassigned parcel never shows the previous
    // owner's name; admins pass null and see everything.
    $timeline = YBT_Tracking_Events::get_timeline(
        $tracking_id,
        $limit,
        $offset,
        $is_admin ? null : $user_id
    );

    wp_send_json_success(array(
        'items'    => $timeline['items'],
        'has_more' => $timeline['has_more'],
    ));
}
add_action('wp_ajax_ybt_get_parcel_timeline', 'ybt_get_parcel_timeline_handler');
