<?php
/**
 * YBT Mobile Tracking Handlers
 */
if (!defined('ABSPATH')) exit;

/**
 * Get tracking by status
 * FIXED: Support token from Authorization header (Android app sends via header, not POST)
 */
function ybt_mobile_tracking_handler() {
    ybt_clear_output_buffers();

    // Rate limiting for mobile tracking requests
    $ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';
    $tracking_rate_key = 'ybt_mobile_tracking_rate_' . wp_hash($ip);
    $requests = get_transient($tracking_rate_key) ?: 0;

    // Allow maximum 50 requests per 5 minutes
    if ($requests >= 50) {
        if (function_exists('ybt_log_security_event')) {
            ybt_log_security_event('MOBILE_TRACKING_RATE_LIMIT_EXCEEDED', array(
                'ip' => $ip
            ));
        }
        wp_send_json(['success' => false, 'message' => 'Rate limit exceeded']);
        return;
    }

    // Increment request counter
    set_transient($tracking_rate_key, $requests + 1, 300); // 5 minutes

    // FIXED: Check Authorization header first, then fallback to POST
    $token = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = trim($matches[1]);
        }
    } elseif (!empty($_POST['token'])) {
        $token = sanitize_text_field(wp_unslash($_POST['token']));
    }
    
    $token_data = ybt_validate_mobile_token($token);
    if (!$token_data) {
        if (function_exists('ybt_log_security_event')) {
            ybt_log_security_event('MOBILE_TRACKING_UNAUTHORIZED_ACCESS', array(
                'ip' => $ip
            ));
        }
        status_header(401);
        wp_send_json(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    global $wpdb;
    $user_id = $token_data['user_id'];

    // FIXED: Support both POST and GET for query parameters (Android uses @Query = GET)
    $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : (isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'all');
    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : (isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '');

    // Validate status parameter
    $valid_statuses = array('all', 'not_received', 'received', 'packing_in_progress', 'packed', 'completed');
    if ($status !== 'all' && !in_array($status, $valid_statuses)) {
        $status = 'all';
    }

    $where = "user_id = %d";
    $params = [$user_id];

    if ($status !== 'all') {
        $where .= " AND status = %s";
        $params[] = $status;
    }

    if (!empty($search)) {
        $where .= " AND (tracking_number LIKE %s OR description LIKE %s)";
        $wild = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $wild;
        $params[] = $wild;
    }

    // Add additional security - limit results per page
    $limit = 100; // Maximum 100 items per request
    $offset = 0;

    if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
        wp_send_json(['success' => false, 'message' => 'Tracking table does not exist', 'data' => ['items' => []]]);
        return;
    }

    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE $where ORDER BY date_added DESC LIMIT %d OFFSET %d",
        array_merge($params, array($limit, $offset))
    ));

    // FIXED: Only log large result sets (potential abuse) instead of every routine access.
    // Logging every request filled wp_options with millions of security events over time.
    if (count($items) >= 50 && function_exists('ybt_log_security_event')) {
        ybt_log_security_event('MOBILE_TRACKING_LARGE_RESULT', array(
            'user_id' => $user_id,
            'items_returned' => count($items)
        ));
    }

    wp_send_json(['success' => true, 'data' => ['items' => $items]]);
}
// Note: ybt_mobile_tracking is registered by YBT_API_Router (YBT_API_Tracking_Handler).
// Handlers here are preserved for direct procedural references.

/**
 * Add tracking number
 * Users add tracking as 'not_received' until admin confirms receipt
 * FIXED: Support token from Authorization header (Android app sends via header, not POST)
 */
function ybt_mobile_add_tracking_handler() {
    ybt_clear_output_buffers();

    // FIXED: Check Authorization header first, then fallback to POST
    $token = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = trim($matches[1]);
        }
    } elseif (!empty($_POST['token'])) {
        $token = sanitize_text_field(wp_unslash($_POST['token']));
    }

    $token_data = ybt_validate_mobile_token($token);
    if (!$token_data) {
        status_header(401);
        wp_send_json(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $tracking_number = strtoupper(trim(sanitize_text_field($_POST['tracking_number'] ?? '')));
    if (empty($tracking_number)) {
        wp_send_json(['success' => false, 'message' => 'Tracking number required']);
        return;
    }

    $description = sanitize_text_field($_POST['description'] ?? '');
    $item_count = intval($_POST['item_count'] ?? 1);
    $shipping_method = sanitize_text_field($_POST['shipping_method'] ?? 'sea');

    global $wpdb;
    $user_id = $token_data['user_id'];
    $user = get_userdata($user_id);
    $username = $user ? $user->user_login : 'Anonymous';

    if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
        wp_send_json(['success' => false, 'message' => 'Tracking table does not exist']);
        return;
    }

    // Check if exists
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, username, status FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = %s",
        $tracking_number
    ));

    if ($existing) {
        $is_anonymous = empty($existing->user_id) || $existing->user_id == 0 || strtolower($existing->username ?? '') === 'anonymous';
        $is_owner = ($existing->user_id == $user_id);

        if ($is_anonymous) {
            // Claim anonymous tracking: assign to user, update description and item count
            // Keep status as 'received' since admin already added it as received
            $wpdb->update($wpdb->prefix . 'ybt_tracking', [
                'user_id' => $user_id,
                'username' => $username,
                'description' => $description,
                'item_count' => $item_count,
                'shipping_method' => $shipping_method,
                'date_added' => current_time('mysql')
            ], ['id' => $existing->id]);

            wp_send_json(['success' => true, 'message' => 'Anonymous tracking claimed and assigned to you']);
            return;
        } elseif ($is_owner) {
            // Already owned - update description and item count
            $wpdb->update($wpdb->prefix . 'ybt_tracking', [
                'description' => $description,
                'item_count' => $item_count,
                'shipping_method' => $shipping_method
            ], ['id' => $existing->id]);

            wp_send_json(['success' => true, 'message' => 'Tracking details updated']);
            return;
        } else {
            wp_send_json(['success' => false, 'message' => 'Tracking number already registered to another user']);
            return;
        }
    }

    // Insert new tracking - users add as 'not_received' until admin confirms
    $wpdb->insert($wpdb->prefix . 'ybt_tracking', [
        'tracking_number' => $tracking_number,
        'user_id' => $user_id,
        'username' => $username,
        'status' => 'not_received',
        'description' => $description,
        'item_count' => $item_count,
        'shipping_method' => $shipping_method,
        'date_added' => current_time('mysql')
    ]);

    wp_send_json(['success' => true, 'message' => 'Added successfully as not_received']);
}
// Note: ybt_mobile_add_tracking is registered by YBT_API_Router (YBT_API_Tracking_Handler).
// Barcode scanning is registered centrally as `ybt_mobile_scan_barcode` (YBT_API_Router →
// YBT_API_Admin_Handler::scan_barcode, admin-only). The legacy `ybt_mobile_scan` action and its
// handler were removed: no callers existed anywhere in the codebase, and it duplicated the
// canonical endpoint with weaker auth (any valid mobile token vs. require_admin). Use
// `ybt_mobile_scan_barcode` for scan-based status updates.
