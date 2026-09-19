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
add_action('wp_ajax_ybt_mobile_tracking', 'ybt_mobile_tracking_handler');
add_action('wp_ajax_nopriv_ybt_mobile_tracking', 'ybt_mobile_tracking_handler');

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
add_action('wp_ajax_ybt_mobile_add_tracking', 'ybt_mobile_add_tracking_handler');
// FIXED: Android app authenticates via JWT Bearer token, not WordPress session cookies.
// WordPress sees token-only requests as unauthenticated (nopriv), so we must register both.
// The handler itself validates the JWT token, so this is safe — unauthenticated requests
// (no token or invalid token) are rejected inside the handler with a 401.
add_action('wp_ajax_nopriv_ybt_mobile_add_tracking', 'ybt_mobile_add_tracking_handler');
/**
 * Handle mobile status update via scan
 * FIXED: Support token from Authorization header and GET/POST parameters
 */
function ybt_mobile_scan_handler() {
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
        wp_send_json_error('Unauthorized');
        return;
    }

    // FIXED: Support both POST and GET for barcode parameter
    $barcode = isset($_POST['barcode']) ? sanitize_text_field(wp_unslash($_POST['barcode'])) : (isset($_GET['barcode']) ? sanitize_text_field(wp_unslash($_GET['barcode'])) : '');
    $barcode = strtoupper(trim($barcode));
    
    if (empty($barcode)) {
        wp_send_json_error('No barcode');
        return;
    }

    global $wpdb;

    if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
        wp_send_json_error('Tracking table does not exist');
        return;
    }

    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = %s",
        $barcode
    ));

    $user = get_userdata($token_data['user_id']);
    $is_admin = ($user && user_can($user->ID, 'manage_options'));

    if (!$item) {
        // Auto-add if admin is scanning
        if ($is_admin) {
            $wpdb->insert($wpdb->prefix . 'ybt_tracking', [
                'tracking_number' => $barcode,
                'status' => 'received',
                'date_added' => current_time('mysql'),
                'date_received' => current_time('mysql'),
                'user_id' => 0, // Anonymous
                'username' => 'Anonymous'
            ]);
            // Audit: attribute the auto-add to the admin who scanned it
            if ($wpdb->insert_id && class_exists('YBT_Tracking_Audit')) {
                YBT_Tracking_Audit::log_edit((int) $wpdb->insert_id, 'create', array(), array(
                    'summary'         => 'Created via barcode scan (auto-add)',
                    'tracking_number' => $barcode,
                    'admin_id'        => (int) $token_data['user_id'],
                    'source'          => 'mobile',
                    'log_activity'    => false,
                ));
            }
            wp_send_json_success(['message' => 'New item added and marked received', 'id' => $wpdb->insert_id]);
            return;
        }
        wp_send_json_error('Item not found');
        return;
    }

    // If item exists and scanned by admin, mark as received
    if ($is_admin && $item->status === 'not_received') {
        // Audit: attribute the scan action to the admin who scanned it
        if (class_exists('YBT_Tracking_Audit')) {
            YBT_Tracking_Audit::apply_update($item->id, [
                'status' => 'received',
                'date_received' => current_time('mysql')
            ], 'status_change', [
                'before'   => $item,
                'summary'  => 'Marked received via barcode scan',
                'admin_id' => (int) $token_data['user_id'],
                'source'   => 'mobile',
            ]);
        } else {
            $wpdb->update($wpdb->prefix . 'ybt_tracking', [
                'status' => 'received',
                'date_received' => current_time('mysql')
            ], ['id' => $item->id]);
        }
        
        // Refresh item data
        $item->status = 'received';
        $item->date_received = current_time('mysql');
    }

    wp_send_json_success(['tracking' => $item]);
}
add_action('wp_ajax_ybt_mobile_scan', 'ybt_mobile_scan_handler');
add_action('wp_ajax_nopriv_ybt_mobile_scan', 'ybt_mobile_scan_handler');
