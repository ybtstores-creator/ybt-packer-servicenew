<?php
/**
 * YBT Admin Announcements
 *
 * Admin-composed notifications that pop up for users as soon as they log in —
 * on the web dashboard and in the mobile app. Reuses the wp_ybt_notifications
 * inbox (type 'announcement', tracking_* NULL) with one row per recipient, so
 * delivery is persistent: the popup appears at the next login and stays until
 * the user dismisses it (dismiss = mark that row read, which also clears it
 * from the bell badge and the mobile Alerts tab).
 *
 * Flows:
 *   1. Admin composes on the Send Notification admin page (manage_options)
 *      -> ybt_send_announcement -> one inbox row per recipient (+ best-effort
 *      FCM push via YBT_Notifications when credentials are configured).
 *   2. Web: dashboard loads -> ybt_get_unread_announcements -> modal popup
 *      -> ybt_dismiss_announcement (also backs the bell inbox).
 *   3. Mobile: MainActivity launch -> ybt_mobile_announcements_unread ->
 *      dialog popup -> ybt_mobile_announcements_dismiss.
 *
 * Auth follows the two house patterns:
 *   - Web AJAX:  check_ajax_referer('ybt_nonce','nonce') + is_user_logged_in()
 *   - Mobile:    JWT Bearer via YBT_API_Handler_Base::authenticate()
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// Shared helpers
// =============================================================================

/**
 * Create the announcement inbox rows (one per recipient) and attempt an FCM
 * push when credentials are configured. Returns a per-run summary.
 *
 * @param int[]  $recipient_ids WordPress user IDs (validated by the caller).
 * @param string $title         Announcement headline.
 * @param string $body          Announcement message.
 * @return array { recipients:int, inserted:int, push_sent:int, push_warning:string }
 */
function ybt_announcements_deliver($recipient_ids, $title, $body) {
    global $wpdb;

    $table = $wpdb->prefix . 'ybt_notifications';
    $now = current_time('mysql');
    $inserted = 0;
    $pushed = 0;

    // Insert inbox notifications in bulk batches of 100
    $chunks = array_chunk($recipient_ids, 100);
    foreach ($chunks as $chunk) {
        $values = array();
        $placeholders = array();
        foreach ($chunk as $uid) {
            $placeholders[] = "(%d, %s, %s, 'announcement', NULL, NULL, 0, %s)";
            $values[] = (int) $uid;
            $values[] = $title;
            $values[] = $body;
            $values[] = $now;
        }
        if (!empty($placeholders)) {
            $query = "INSERT INTO {$table} (user_id, title, body, type, tracking_id, tracking_number, is_read, date_created) VALUES " . implode(', ', $placeholders);
            $res = $wpdb->query($wpdb->prepare($query, $values));
            if ($res !== false) {
                $inserted += count($chunk);
            }
        }
    }

    // Best-effort push dispatch (bulk / capped so it never times out)
    $push_warning = '';
    if (class_exists('YBT_Notifications')) {
        if (count($recipient_ids) === 1) {
            $res = YBT_Notifications::send_to_user((int) $recipient_ids[0], $title, $body, array('type' => 'announcement'));
            if ($res) $pushed = 1;
        } else {
            $pushed = YBT_Notifications::send_bulk_push($recipient_ids, $title, $body, array('type' => 'announcement'));
        }

        // An announcement that only lands in the in-app inbox must not be
        // reported as "delivered": say plainly that the push was skipped.
        if (!$pushed && !empty($recipient_ids)) {
            $fcm_status = YBT_Notifications::status();
            $push_warning = $fcm_status['message'] ?? 'Push notifications are not available.';
        }
    }

    if ($inserted > 0 && function_exists('ybt_log_error')) {
        ybt_log_error("Announcement delivered to {$inserted} user(s): {$title}");
    }

    return array(
        'recipients' => count($recipient_ids),
        'inserted'   => $inserted,
        'push_sent'  => $pushed,
        'push_warning' => $push_warning,
    );
}

/**
 * Unread announcement rows for a user, newest first.
 *
 * @return array[] Each: id, title, body, created_at.
 */
function ybt_announcements_get_unread($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'ybt_notifications';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, body, date_created
           FROM {$table}
          WHERE user_id = %d AND type = 'announcement' AND is_read = 0
          ORDER BY date_created ASC, id ASC
          LIMIT 10",
        (int) $user_id
    ));

    $items = array();
    foreach ((array) $rows as $row) {
        $items[] = array(
            'id'         => (int) $row->id,
            'title'      => $row->title,
            'body'       => $row->body,
            'created_at' => $row->date_created,
        );
    }
    return $items;
}

/**
 * Mark one announcement row read (owned by $user_id). Returns rows updated.
 */
function ybt_announcements_dismiss($user_id, $id) {
    global $wpdb;
    $table = $wpdb->prefix . 'ybt_notifications';

    return (int) $wpdb->update(
        $table,
        array('is_read' => 1),
        array(
            'id'      => (int) $id,
            'user_id' => (int) $user_id,
            'type'    => 'announcement',
            'is_read' => 0,
        ),
        array('%d'),
        array('%d', '%d', '%s', '%d')
    );
}

/**
 * Recent announcements for the admin page (any recipient), newest first.
 *
 * @return array[] { id, title, body, user_id, is_read, date_created }
 */
function ybt_announcements_recent($limit = 15) {
    global $wpdb;
    $table = $wpdb->prefix . 'ybt_notifications';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT n.id, n.title, n.body, n.user_id, n.is_read, n.date_created, u.user_login
           FROM {$table} n
      LEFT JOIN {$wpdb->users} u ON u.ID = n.user_id
          WHERE n.type = 'announcement'
          ORDER BY n.date_created DESC, n.id DESC
          LIMIT %d",
        max(1, min(50, (int) $limit))
    ));

    $items = array();
    foreach ((array) $rows as $row) {
        $items[] = array(
            'id'           => (int) $row->id,
            'title'        => $row->title,
            'body'         => $row->body,
            'user_id'      => (int) $row->user_id,
            'user_login'   => $row->user_login ? $row->user_login : ('#' . $row->user_id),
            'is_read'      => (int) $row->is_read,
            'date_created' => $row->date_created,
        );
    }
    return $items;
}

// =============================================================================
// Admin send endpoint (wp_ajax — admin session + ybt_nonce, AI-operator style)
// =============================================================================

function ybt_send_announcement_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $body  = isset($_POST['body']) ? sanitize_textarea_field(wp_unslash($_POST['body'])) : '';
    $aud   = isset($_POST['audience']) ? sanitize_key($_POST['audience']) : 'user';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($title === '') {
        wp_send_json_error('Title is required');
        return;
    }
    if (mb_strlen($title) > 140) {
        wp_send_json_error('Title too long (max 140 characters)');
        return;
    }
    if ($body === '') {
        wp_send_json_error('Message is required');
        return;
    }

    // Recipients: every real account, or one specific user.
    if ($aud === 'all') {
        // NOTE: 'fields' must be the string 'ID' — the array form returns
        // stdClass objects and intval(object) collapses every row to 1.
        $recipients = get_users(array(
            'fields' => 'ID',
            'number' => 5000,
        ));
        $recipient_ids = array_map('intval', (array) $recipients);
    } else {
        $u = get_userdata($user_id);
        if (!$u) {
            wp_send_json_error('Recipient user not found');
            return;
        }
        $recipient_ids = array((int) $u->ID);
    }

    if (!$recipient_ids) {
        wp_send_json_error('No recipients matched');
        return;
    }

    $result = ybt_announcements_deliver($recipient_ids, $title, $body);
    wp_send_json_success($result);
}
add_action('wp_ajax_ybt_send_announcement', 'ybt_send_announcement_handler');

// =============================================================================
// Web popup: fetch unread announcements (any logged-in dashboard user)
// =============================================================================

function ybt_get_unread_announcements_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }

    wp_send_json_success(array(
        'items' => ybt_announcements_get_unread(get_current_user_id()),
    ));
}
add_action('wp_ajax_ybt_get_unread_announcements', 'ybt_get_unread_announcements_handler');

/**
 * Web popup dismiss (mark that announcement read). One id per call — the
 * modal walks through announcements one at a time.
 */
function ybt_dismiss_announcement_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        wp_send_json_error('id required');
        return;
    }

    $updated = ybt_announcements_dismiss(get_current_user_id(), $id);
    wp_send_json_success(array('updated' => $updated));
}
add_action('wp_ajax_ybt_dismiss_announcement', 'ybt_dismiss_announcement_handler');

// Mobile counterparts (ybt_mobile_announcements_unread / _dismiss) live in
// YBT_API_Notifications_Handler (includes/api/class-ybt-api-notifications-handler.php)
// and are registered by YBT_API_Router alongside the other ybt_mobile_* routes.
