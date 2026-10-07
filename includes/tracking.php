<?php
/**
 * YBT User Tracking AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle parcel claiming
 */
function ybt_claim_parcel_handler() {
    check_ajax_referer('ybt_claim_parcel', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
    }

    // Rate limiting for claiming
    $ip = ybt_get_client_ip();
    $claim_rate_key = 'ybt_claim_rate_' . wp_hash($ip);
    $claims = get_transient($claim_rate_key) ?: 0;

    // Allow maximum 10 claims per 10 minutes
    if ($claims >= 10) {
        ybt_log_security_event('CLAIM_RATE_LIMIT_EXCEEDED', array(
            'ip' => $ip
        ));
        wp_send_json_error('Rate limit exceeded. Please try again later.');
        return;
    }

    // Increment request counter
    set_transient($claim_rate_key, $claims + 1, 600); // 10 minutes

    global $wpdb;
    $tracking_num = strtoupper(trim(sanitize_text_field($_POST['tracking_number'] ?? '')));
    $description = sanitize_text_field($_POST['description'] ?? '');
    $item_count = intval($_POST['item_count'] ?? 1);
    $user_id = get_current_user_id();

    if (empty($tracking_num)) {
        wp_send_json_error('Tracking number required');
    }

    // Validate tracking number format
    if (!preg_match('/^[A-Z0-9\-]+$/', $tracking_num)) {
        ybt_log_security_event('INVALID_TRACKING_NUMBER_FORMAT', array(
            'tracking_number' => $tracking_num,
            'ip' => $ip
        ));
        wp_send_json_error('Invalid tracking number format');
        return;
    }

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, status FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = %s",
        $tracking_num
    ));

    if (!$existing) {
        wp_send_json_error('Parcel not found in warehouse.');
    }

    if (!empty($existing->user_id) && $existing->user_id != 0) {
        wp_send_json_error('Parcel already assigned.');
    }

    $wpdb->update(
        $wpdb->prefix . 'ybt_tracking',
        array(
            'user_id' => $user_id, 
            'username' => wp_get_current_user()->user_login,
            'description' => $description,
            'item_count' => $item_count,
            'status' => 'received',
            'date_received' => current_time('mysql')
        ),
        array('id' => $existing->id)
    );

    if (class_exists('YBT_Tracking_Events')) {
        $current_user = wp_get_current_user();
        YBT_Tracking_Events::record($existing->id, 'claimed', array(
            'tracking_number' => $tracking_num,
            'user_id'         => $user_id,
            'status_from'     => $existing->status,
            'status_to'       => 'received',
            'actor_id'        => $user_id,
            'actor_name'      => $current_user->display_name ?: $current_user->user_login,
            'actor_role'      => 'customer',
            'source'          => 'web',
        ));
    }

    do_action('ybt_tracking_updated', $existing->id, 'received');

    // Log successful claim
    ybt_log_security_event('PARCEL_CLAIMED', array(
        'tracking_number' => $tracking_num,
        'user_id' => $user_id
    ));

    wp_send_json_success('Parcel claimed successfully.');
}
add_action('wp_ajax_ybt_claim_parcel', 'ybt_claim_parcel_handler');

/**
 * Handle adding tracking numbers
 * Users add tracking as 'not_received' until admin marks them as 'received'
 */
function ybt_user_add_tracking_handler() {
    check_ajax_referer('ybt_user_add_tracking', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
    }

    global $wpdb;
    $tracking_numbers = (array)($_POST['tracking_numbers'] ?? []);
    $descriptions = (array)($_POST['descriptions'] ?? []);
    $item_counts = (array)($_POST['item_counts'] ?? []);

    if (empty($tracking_numbers) && isset($_POST['tracking_number'])) {
        $tracking_numbers[] = $_POST['tracking_number'];
    }

    $user_id = get_current_user_id();
    $username = wp_get_current_user()->user_login;
    $count = 0;
    $timestamp = current_time('mysql');
    $created_ids = array();

    foreach ($tracking_numbers as $index => $num) {
        $num = strtoupper(trim(sanitize_text_field($num)));
        if (empty($num)) continue;

        $description = sanitize_text_field($descriptions[$index] ?? '');
        $item_count = intval($item_counts[$index] ?? 1);

        // Check if exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, username, status FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = %s",
            $num
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
                    'date_added' => $timestamp
                ], ['id' => $existing->id]);
                if (class_exists('YBT_Tracking_Events')) {
                    YBT_Tracking_Events::record($existing->id, 'claimed', [
                        'tracking_number' => $num,
                        'user_id'         => $user_id,
                        'status_from'     => $existing->status,
                        'status_to'       => $existing->status,
                        'actor_id'        => $user_id,
                        'actor_name'      => $username,
                        'actor_role'      => 'customer',
                        'source'          => 'web',
                    ]);
                }
                $count++;
            } elseif ($is_owner) {
                // Already owned by this user - update description and item count only
                $wpdb->update($wpdb->prefix . 'ybt_tracking', [
                    'description' => $description,
                    'item_count' => $item_count
                ], ['id' => $existing->id]);
                if (class_exists('YBT_Tracking_Events')) {
                    YBT_Tracking_Events::record($existing->id, 'details_updated', [
                        'tracking_number' => $num,
                        'user_id'         => $user_id,
                        'actor_id'        => $user_id,
                        'actor_name'      => $username,
                        'actor_role'      => 'customer',
                        'source'          => 'web',
                        'dedupe'          => true,
                    ]);
                }
                $count++;
            }
            // Skip parcels owned by other users
            continue;
        }

        // Insert new tracking - users add as 'not_received' until admin confirms
        $wpdb->insert($wpdb->prefix . 'ybt_tracking', [
            'tracking_number' => $num,
            'user_id' => $user_id,
            'username' => $username,
            'description' => $description,
            'item_count' => $item_count,
            'status' => 'not_received',
            'date_added' => $timestamp
        ]);
        if ($wpdb->insert_id) {
            $created_ids[] = (int) $wpdb->insert_id;
            if (class_exists('YBT_Tracking_Events')) {
                YBT_Tracking_Events::record($wpdb->insert_id, 'created', [
                    'tracking_number' => $num,
                    'user_id'         => $user_id,
                    'status_to'       => 'not_received',
                    'actor_id'        => $user_id,
                    'actor_name'      => $username,
                    'actor_role'      => 'customer',
                    'source'          => 'web',
                ]);
            }
            $count++;
        }
    }

    foreach ($created_ids as $new_id) {
        do_action('ybt_tracking_created', $new_id, 'not_received');
    }

    wp_send_json_success("$count items added.");
}
add_action('wp_ajax_ybt_user_add_tracking', 'ybt_user_add_tracking_handler');

/**
 * Quick search for user parcels
 */
function ybt_quick_search_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    // SECURITY FIX: Ensure the user is authenticated before returning any tracking data
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }
    $query = sanitize_text_field($_POST['query'] ?? '');
    if (strlen($query) < 3) wp_send_json_success([]);

    global $wpdb;
    $user_id = get_current_user_id();
    $is_admin = current_user_can('manage_options');

    $sql = "SELECT tracking_number, status, description FROM {$wpdb->prefix}ybt_tracking WHERE tracking_number LIKE %s";
    $params = ['%' . $wpdb->esc_like($query) . '%'];

    if (!$is_admin) {
        $sql .= " AND user_id = %d";
        $params[] = $user_id;
    }

    $results = $wpdb->get_results($wpdb->prepare($sql, $params));
    wp_send_json_success($results);
}
/**
 * Retrieve tracking data with pagination (Admin)
 */
function ybt_get_tracking_data_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_tracking';

    $page = intval($_POST['page'] ?? 1);
    $per_page = intval($_POST['per_page'] ?? 20);
    $status = sanitize_text_field($_POST['status'] ?? '');
    $shipping_method = sanitize_text_field($_POST['shipping_method'] ?? '');
    $user_filter = sanitize_text_field($_POST['user'] ?? '');
    $has_images = sanitize_text_field($_POST['has_images'] ?? '');
    $search = sanitize_text_field($_POST['search'] ?? '');

    $where = ["1=1"];
    $params = [];

    // 'all' is the no-filter sentinel used by every other tracking route (admin REST,
    // mobile); matching it against the status ENUM here would return zero rows.
    if (!empty($status) && $status !== 'all') {
        $where[] = "status = %s";
        $params[] = $status;
    }

    if (!empty($shipping_method) && $shipping_method !== 'all') {
        $where[] = "shipping_method = %s";
        $params[] = $shipping_method;
    }

    if (!empty($user_filter) && $user_filter !== 'all') {
        $where[] = "(username LIKE %s OR user_id = %d)";
        $params[] = '%' . $wpdb->esc_like($user_filter) . '%';
        $params[] = intval($user_filter);
    }

    // Filter by images status
    if ($has_images === 'no') {
        $where[] = "(images IS NULL OR images = '')";
    } elseif ($has_images === 'yes') {
        $where[] = "images IS NOT NULL AND images != ''";
    }

    if (!empty($search)) {
        $where[] = "tracking_number LIKE %s";
        $params[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $where_sql = implode(' AND ', $where);

    // Total count
    $total_items = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE $where_sql",
        $params
    ));

    // Items
    $offset = ($page - 1) * $per_page;
    $query_params = array_merge($params, [$per_page, $offset]);
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE $where_sql ORDER BY date_added DESC LIMIT %d OFFSET %d",
        $query_params
    ));

    // Ensure ID and numeric fields are returned as integers for JavaScript compatibility
    // $wpdb->get_results returns strings by default, so we must cast explicitly
    if (!empty($items)) {
        $casted_items = array();
        foreach ($items as $item) {
            $item->id = (int) $item->id;
            $item->user_id = (int) $item->user_id;
            $item->item_count = (int) $item->item_count;
            $casted_items[] = $item;
        }
        $items = $casted_items;
    }

    wp_send_json_success([
        'items' => $items,
        'total_items' => intval($total_items),
        'total_pages' => ceil($total_items / $per_page)
    ]);
}
add_action('wp_ajax_ybt_get_tracking_data', 'ybt_get_tracking_data_handler');

/**
 * Update the status of a tracking item
 */
function ybt_update_status_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $id     = intval($_POST['tracking_id']);
    $status = sanitize_text_field($_POST['status']);

    // SECURITY FIX: Whitelist valid status values to prevent arbitrary string injection
    $valid_statuses = array('not_received', 'received', 'packing_in_progress', 'packed', 'completed');
    if (!in_array($status, $valid_statuses, true)) {
        wp_send_json_error('Invalid status value');
        return;
    }

    // Snapshot first: reused by the audit and by the notification guard below.
    $before = YBT_Tracking_Audit::snapshot($id);

    $data = ['status' => $status];

    // Automatically set timestamps based on status
    if ($status === 'received') {
        $data['date_received'] = current_time('mysql');
    } elseif ($status === 'packed') {
        $data['date_packed'] = current_time('mysql');
    } elseif ($status === 'completed') {
        $data['date_completed'] = current_time('mysql');
    }

    // Audit: records which admin changed the status, and from what.
    $updated = YBT_Tracking_Audit::apply_update($id, $data, 'status_change', ['before' => $before]);

    do_action('ybt_tracking_updated', $id, $status);

    if ($updated !== false && $before && $before->status !== $status) {
        ybt_notify_status_change($before->user_id, $before->tracking_number, $status);
    }

    wp_send_json_success('Status updated');
}
add_action('wp_ajax_ybt_update_status', 'ybt_update_status_handler');

/**
 * Delete a tracking item (Admin only)
 */
function ybt_delete_tracking_item_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$id) {
        wp_send_json_error('Invalid tracking ID');
        return;
    }

    // Get item info before deletion for logging
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT tracking_number, username FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
        $id
    ));

    if (!$item) {
        wp_send_json_error('Tracking record not found');
        return;
    }

    $deleted = $wpdb->delete(
        $wpdb->prefix . 'ybt_tracking',
        array('id' => $id),
        array('%d')
    );

    if ($deleted) {
        // Audit: record which admin deleted the tracking number (history is kept
        // even though the tracking row itself is gone).
        YBT_Tracking_Audit::log_edit($id, 'delete', array(), array(
            'summary'         => sprintf('Deleted tracking number (user: %s)', $item->username ? $item->username : 'Anonymous'),
            'tracking_number' => $item->tracking_number,
            'source'          => (function_exists('wp_doing_ajax') && wp_doing_ajax()) ? 'ajax' : 'admin',
            'log_activity'    => false, // existing code below already logs this deletion
        ));

        // Log the deletion
        if (function_exists('ybt_log_activity')) {
            ybt_log_activity(
                get_current_user_id(),
                'admin_delete_tracking',
                "Deleted tracking: {$item->tracking_number} (User: {$item->username})"
            );
        }
        wp_send_json_success('Tracking deleted successfully');
    } else {
        wp_send_json_error('Failed to delete tracking');
    }
}
add_action('wp_ajax_ybt_delete_tracking_item', 'ybt_delete_tracking_item_handler');

/**
 * Update item details handler (item_count, description, etc.)
 */
function ybt_update_item_details_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $tracking_id = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
    $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
    $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';

    if (empty($tracking_id) || empty($field)) {
        wp_send_json_error('Missing required parameters');
    }

    // Validate allowed fields
    $allowed_fields = array('item_count', 'description', 'shipping_method');
    if (!in_array($field, $allowed_fields)) {
        wp_send_json_error('Invalid field');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_tracking';

    // Sanitize value based on field type
    if ($field === 'item_count') {
        $value = intval($value);
        if ($value < 1) {
            $value = 1;
        }
    } elseif ($field === 'description') {
        $value = sanitize_textarea_field($value);
    } else {
        $value = sanitize_text_field($value);
    }

    // FIXED: Use correct format specifier — %d for integer fields, %s for text
    $format = ($field === 'item_count') ? '%d' : '%s';

    // Audit: captures the previous value so the history shows "old -> new".
    $result = YBT_Tracking_Audit::apply_update(
        $tracking_id,
        array($field => $value),
        ($field === 'item_count') ? 'item_count' : 'edit',
        array('format' => array($format), 'where_format' => array('%d'))
    );

    if ($result === false) {
        wp_send_json_error('Failed to update item');
    }

    wp_send_json_success('Item updated successfully');
}
add_action('wp_ajax_ybt_update_item_details', 'ybt_update_item_details_handler');

/**
 * Fix existing packed items with NULL date_packed
 * Updates date_packed to use date_added as fallback for existing records
 */
function ybt_fix_packed_items_date_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_tracking';

    // Update date_packed for items that are 'packed' but have NULL date_packed
    // Use date_added as fallback, or date_packing_requested if available
    $updated = $wpdb->query("
        UPDATE $table
        SET date_packed = COALESCE(date_packing_requested, date_added, NOW())
        WHERE status = 'packed' AND (date_packed IS NULL OR date_packed = '0000-00-00 00:00:00')
    ");

    if ($updated === false) {
        wp_send_json_error('Failed to update packed items');
    }

    wp_send_json_success(array(
        'message' => 'Successfully updated ' . intval($updated) . ' packed items',
        'count' => intval($updated)
    ));
}
add_action('wp_ajax_ybt_fix_packed_items_date', 'ybt_fix_packed_items_date_handler');

/**
 * Barcode scanning handler
 */
function ybt_scan_barcode_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $barcode = strtoupper(trim(sanitize_text_field($_POST['barcode'])));

    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = %s",
        $barcode
    ));

    if ($item) {
        // If scanned by admin and status is not_received, mark as received
        if ($item->status === 'not_received') {
            YBT_Tracking_Audit::apply_update($item->id, [
                'status' => 'received',
                'date_received' => current_time('mysql')
            ], 'status_change', array('before' => $item, 'summary' => 'Status: Not Received → Received (barcode scan)'));
            
            // Refresh local object
            $item->status = 'received';
            $item->date_received = current_time('mysql');

            ybt_notify_status_change($item->user_id, $item->tracking_number, 'received');
        }
        wp_send_json_success($item);
    } else {
        wp_send_json_error('Item not found');
    }
}
add_action('wp_ajax_ybt_scan_barcode', 'ybt_scan_barcode_handler');

/**
 * Admin add single tracking handler
 */
function ybt_add_tracking_handler() {
    // Clear any output buffers to ensure clean JSON response
    if (function_exists('ybt_clear_output_buffers')) {
        ybt_clear_output_buffers();
    } else {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    // Verify nonce first
    $nonce_result = check_ajax_referer('ybt_admin_nonce', 'nonce', false);
    if (!$nonce_result) {
        wp_send_json_error('Invalid security token');
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    global $wpdb;

    $tracking_num_raw = isset($_POST['tracking_number']) ? $_POST['tracking_number'] : '';
    $tracking_number = strtoupper(trim(sanitize_text_field($tracking_num_raw)));

    $username_raw = isset($_POST['username']) ? $_POST['username'] : '';
    $username = sanitize_text_field($username_raw);

    $item_count_raw = isset($_POST['item_count']) ? $_POST['item_count'] : 1;
    $item_count = intval($item_count_raw);

    $shipping_raw = isset($_POST['shipping_method']) ? $_POST['shipping_method'] : 'sea';
    $shipping_method = sanitize_text_field($shipping_raw);

    $tracking_id_raw = isset($_POST['tracking_id']) ? $_POST['tracking_id'] : 0;
    $tracking_id = intval($tracking_id_raw);

    if (empty($tracking_number)) {
        wp_send_json_error('Tracking number is required');
        return;
    }

    // Get user info if username provided
    $user_id = 0;
    if (!empty($username)) {
        $user = get_user_by('login', $username);
        if (!$user) {
            $user = get_user_by('slug', $username);
        }
        if ($user) {
            $user_id = $user->ID;
        }
    }

    // Handle file upload
    $images = '';
    if (!empty($_FILES['tracking_images']) && is_array($_FILES['tracking_images']['name'])) {
        $image_urls = array();
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        foreach ($_FILES['tracking_images']['name'] as $key => $value) {
            if ($_FILES['tracking_images']['error'][$key] === 0) {
                $file = array(
                    'name'     => $_FILES['tracking_images']['name'][$key],
                    'type'     => $_FILES['tracking_images']['type'][$key],
                    'tmp_name' => $_FILES['tracking_images']['tmp_name'][$key],
                    'error'    => $_FILES['tracking_images']['error'][$key],
                    'size'     => $_FILES['tracking_images']['size'][$key]
                );

                // SECURITY: Validate file before upload
                $validation = ybt_validate_file_upload($file, array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
                if (is_wp_error($validation)) {
                    wp_send_json_error($validation->get_error_message());
                    return;
                }

                // Sanitize filename
                $file['name'] = ybt_sanitize_filename($file['name']);

                $upload = wp_handle_upload($file, array(
                    'test_form' => false,
                    'mimes' => array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp')
                ));

                if (!is_wp_error($upload) && isset($upload['url'])) {
                    // Compress to reduce disk space (best-effort)
                    if (!empty($upload['file']) && class_exists('YBT_File_Handler')) {
                        YBT_File_Handler::compress_image($upload['file']);
                    }
                    $image_urls[] = $upload['url'];
                }
            }
        }

        if (!empty($image_urls)) {
            $images = implode(',', $image_urls);
        }
    }

    // Edit mode
    if ($tracking_id > 0) {
        $edit_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
            $tracking_id
        ));

        if (!$edit_item) {
            wp_send_json_error('Tracking entry not found');
            return;
        }

        // Keep existing images if no new ones uploaded
        if (empty($images) && !empty($edit_item->images)) {
            $images = $edit_item->images;
        }

        $update_data = array(
            'tracking_number' => $tracking_number,
            'user_id' => $user_id,
            'username' => !empty($username) ? $username : $edit_item->username,
            'item_count' => $item_count,
            'shipping_method' => $shipping_method,
        );

        if (!empty($images)) {
            $update_data['images'] = $images;
        }

        $result = YBT_Tracking_Audit::apply_update(
            $tracking_id,
            $update_data,
            'full_edit',
            array('before' => $edit_item)
        );

        if ($result === false) {
            wp_send_json_error('Failed to update: ' . $wpdb->last_error);
            return;
        }

        do_action('ybt_tracking_updated', $tracking_id, $edit_item->status);
        wp_send_json_success('Shipment updated successfully');
        return;
    }

    // Check for duplicate
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, username, status, description, item_count FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = %s",
        $tracking_number
    ));

    if ($existing) {
        $is_anonymous = empty($existing->user_id) || $existing->user_id == 0 || strtolower($existing->username ?? '') === 'anonymous';

        // If admin is adding with a username and tracking exists as anonymous, assign it to the user
        if ($is_anonymous && !empty($username)) {
            $update_data = array(
                'user_id' => $user_id,
                'username' => $username,
                'item_count' => $item_count,
                'description' => sanitize_text_field($_POST['description'] ?? $existing->description),
                'status' => 'received',
                'date_received' => current_time('mysql'),
                'shipping_method' => $shipping_method,
            );
            if (!empty($images)) {
                $update_data['images'] = $images;
            }

            $result = YBT_Tracking_Audit::apply_update($existing->id, $update_data, 'assign');
            if ($result === false) {
                wp_send_json_error('Failed to update tracking: ' . $wpdb->last_error);
                return;
            }
            do_action('ybt_tracking_updated', $existing->id, 'received');
            // The row was anonymous, so notify the customer it was just assigned to.
            ybt_notify_status_change($user_id, $existing->tracking_number, 'received');
            wp_send_json_success('Anonymous shipment claimed and assigned to ' . $username);
            return;
        }

        // If tracking exists (user-added as not_received or already received), mark/update as received
        $update_data = array(
            'status' => 'received',
            'date_received' => current_time('mysql'),
            'shipping_method' => $shipping_method,
        );
        
        $result = YBT_Tracking_Audit::apply_update($existing->id, $update_data, 'status_change');
        if ($result === false) {
            wp_send_json_error('Failed to update tracking: ' . $wpdb->last_error);
            return;
        }

        do_action('ybt_tracking_updated', $existing->id, 'received');
        if ($existing->status !== 'received') {
            ybt_notify_status_change($existing->user_id, $existing->tracking_number, 'received');
        }
        wp_send_json_success('Shipment already existed and has been marked as RECEIVED');
        return;
    }

    // Insert new tracking
    $is_anonymous = empty($username) || strtolower($username) === 'anonymous';
    $wpdb->insert(
        $wpdb->prefix . 'ybt_tracking',
        array(
            'tracking_number' => $tracking_number,
            'user_id' => $user_id,
            'username' => !empty($username) ? $username : 'Anonymous',
            'item_count' => $item_count,
            'shipping_method' => $shipping_method,
            'status' => $is_anonymous ? 'received' : 'not_received',
            'images' => $images,
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'date_added' => current_time('mysql'),
            'date_received' => $is_anonymous ? current_time('mysql') : null
        )
    );

    if ($wpdb->insert_id) {
        // Audit: attribute the creation to the admin who registered it.
        YBT_Tracking_Audit::log_edit($wpdb->insert_id, 'create', array(), array(
            'summary'         => 'Tracking number created',
            'tracking_number' => $tracking_number,
        ));
        do_action('ybt_tracking_created', $wpdb->insert_id, $is_anonymous ? 'received' : 'not_received');
        wp_send_json_success('Shipment registered successfully');
    } else {
        wp_send_json_error('Failed to register shipment');
    }
}
add_action('wp_ajax_ybt_add_tracking', 'ybt_add_tracking_handler');

/**
 * Admin add multiple tracking handler
 * When admin adds tracking with username, anonymous tracking gets assigned to that user
 */
function ybt_add_multiple_tracking_handler() {
    if (function_exists('ybt_clear_output_buffers')) {
        ybt_clear_output_buffers();
    }
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    global $wpdb;

    $tracking_numbers = isset($_POST['tracking_numbers']) ? (array)$_POST['tracking_numbers'] : array();
    $username = sanitize_text_field($_POST['username'] ?? '');
    $shipping_method = sanitize_text_field($_POST['shipping_method'] ?? 'sea');

    if (empty($tracking_numbers)) {
        wp_send_json_error('No tracking numbers provided');
        return;
    }

    // Normalize and clean
    $tracking_numbers = array_map(function($n) {
        return strtoupper(trim(sanitize_text_field($n)));
    }, $tracking_numbers);
    $tracking_numbers = array_filter($tracking_numbers);
    $tracking_numbers = array_unique($tracking_numbers);

    if (empty($tracking_numbers)) {
        wp_send_json_error('No valid tracking numbers');
        return;
    }

    // Get user info if username provided
    $user_id = 0;
    if (!empty($username)) {
        $user = get_user_by('login', $username);
        if (!$user) {
            $user = get_user_by('slug', $username);
        }
        if ($user) {
            $user_id = $user->ID;
        }
    }

    // Check for existing - get full record to check if anonymous
    $placeholders = implode(',', array_fill(0, count($tracking_numbers), '%s'));
    $existing_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, tracking_number, user_id, username, status FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) IN ($placeholders)",
        $tracking_numbers
    ));

    // Separate anonymous from non-anonymous existing tracking
    $anonymous_ids = array();
    $non_anonymous_ids = array();
    $existing_tracking_numbers = array();
    $tracking_number_by_id = array();
    
    foreach ($existing_rows as $row) {
        $existing_tracking_numbers[] = strtoupper($row->tracking_number);
        $tracking_number_by_id[$row->id] = $row->tracking_number;
        $is_anonymous = empty($row->user_id) || $row->user_id == 0 || strtolower($row->username ?? '') === 'anonymous';
        if ($is_anonymous) {
            $anonymous_ids[] = $row->id;
        } else {
            $non_anonymous_ids[] = $row->id;
        }
    }

    $to_insert = array_diff($tracking_numbers, $existing_tracking_numbers);
    $inserted = 0;

    $timestamp = current_time('mysql');
    $is_anonymous_entry = empty($username) || strtolower($username) === 'anonymous';

    // Update anonymous tracking to assigned user if username provided
    $assigned_count = 0;
    if (!empty($anonymous_ids) && !empty($username)) {
        foreach ($anonymous_ids as $id) {
            $result = YBT_Tracking_Audit::apply_update($id, array(
                'user_id' => $user_id,
                'username' => $username,
                'status' => 'received',
                'date_received' => $timestamp,
                'shipping_method' => $shipping_method,
            ), 'assign');
            if ($result !== false) {
                $assigned_count++;
                do_action('ybt_tracking_updated', $id, 'received');
                ybt_notify_status_change($user_id, $tracking_number_by_id[$id], 'received');
            }
        }
    }

    // Update non-anonymous existing tracking to received
    $updated_count = 0;
    if (!empty($non_anonymous_ids)) {
        // Audit: snapshot before the raw bulk UPDATE so the old status is preserved.
        $bulk_before = array();
        foreach ($non_anonymous_ids as $id) {
            $bulk_before[$id] = YBT_Tracking_Audit::snapshot($id);
        }

        $non_anonymous_ids_placeholders = implode(',', array_fill(0, count($non_anonymous_ids), '%d'));
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ybt_tracking SET status = 'received', date_received = %s, shipping_method = %s WHERE id IN ($non_anonymous_ids_placeholders)",
            array_merge(array($timestamp, $shipping_method), $non_anonymous_ids)
        ));
        if ($result !== false) {
            $updated_count = count($non_anonymous_ids);
            // Trigger action for each updated tracking
            foreach ($non_anonymous_ids as $id) {
                YBT_Tracking_Audit::log_after_raw_update(
                    $id,
                    isset($bulk_before[$id]) ? $bulk_before[$id] : null,
                    array('status' => 'received', 'date_received' => $timestamp, 'shipping_method' => $shipping_method),
                    'bulk_status'
                );
                do_action('ybt_tracking_updated', $id, 'received');

                $before = isset($bulk_before[$id]) ? $bulk_before[$id] : null;
                if ($before && $before->status !== 'received') {
                    ybt_notify_status_change($before->user_id, $before->tracking_number, 'received');
                }
            }
        }
    }

    if (!empty($to_insert)) {
        $values = array();
        $val_placeholders = array();

        foreach ($to_insert as $num) {
            $val_placeholders[] = "(%s, %d, %s, %s, %s, %d, %s, %s)";
            $values[] = $num;
            $values[] = $user_id;
            $values[] = !empty($username) ? $username : 'Anonymous';
            $values[] = $is_anonymous_entry ? 'received' : 'not_received';
            $values[] = $timestamp;
            $values[] = 1; // item_count
            $values[] = $shipping_method;
            $values[] = $is_anonymous_entry ? $timestamp : null;
            $inserted++;
        }

        $sql = "INSERT INTO {$wpdb->prefix}ybt_tracking (tracking_number, user_id, username, status, date_added, item_count, shipping_method, date_received) VALUES " . implode(',', $val_placeholders);
        $wpdb->query($wpdb->prepare($sql, $values));

        // Audit: attribute each newly created tracking number to this admin.
        $created_ids = array();
        foreach ($to_insert as $num) {
            $new_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = %s",
                strtoupper($num)
            ));
            if ($new_id) {
                $created_ids[] = (int) $new_id;
                YBT_Tracking_Audit::log_edit((int) $new_id, 'create', array(), array(
                    'summary'         => 'Tracking number created' . ($is_anonymous_entry ? ' (anonymous)' : ''),
                    'tracking_number' => $num,
                ));
            }
        }

        foreach ($created_ids as $new_id) {
            do_action('ybt_tracking_created', $new_id, $is_anonymous_entry ? 'received' : 'not_received');
        }
    }

    $message = "$inserted shipments registered successfully.";
    if ($assigned_count > 0) {
        $message .= " ($assigned_count anonymous items assigned to $username)";
    }
    if ($updated_count > 0) {
        $message .= " ($updated_count existing items updated to RECEIVED)";
    }

    wp_send_json_success(array(
        'message' => $message,
        'inserted' => $inserted,
        'assigned' => $assigned_count,
        'updated' => $updated_count
    ));
}
add_action('wp_ajax_ybt_add_multiple_tracking', 'ybt_add_multiple_tracking_handler');

/**
 * Get recent additions for admin dashboard
 */
function ybt_get_recent_additions_handler() {
    $nonce_result = check_ajax_referer('ybt_admin_nonce', 'nonce', false);
    if (!$nonce_result) {
        wp_send_json_error('Invalid security token');
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    global $wpdb;

    // Query today's shipments (no prepare needed since no variables)
    $recent = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}ybt_tracking
         WHERE DATE(date_added) = CURDATE()
         ORDER BY date_added DESC
         LIMIT 10"
    );

    if (empty($recent)) {
        wp_send_json_success('<div class="text-center text-muted py-4">No shipments received today.</div>');
        return;
    }

    ob_start();
    ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Tracking #</th>
                    <th>User</th>
                    <th>Items</th>
                    <th>Method</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $item): ?>
                <tr>
                    <td><strong><?php echo esc_html($item->tracking_number); ?></strong></td>
                    <td><?php echo esc_html($item->username ?: 'Anonymous'); ?></td>
                    <td><?php echo esc_html($item->item_count ?: 1); ?></td>
                    <td><span class="badge bg-<?php echo $item->shipping_method === 'air' ? 'info' : 'secondary'; ?>"><?php echo esc_html(ucfirst($item->shipping_method)); ?></span></td>
                    <td><?php echo human_time_diff(strtotime($item->date_added), current_time('timestamp')) . ' ago'; ?></td>
                    <td><span class="badge bg-<?php echo ybt_get_status_badge_color($item->status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $item->status))); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    wp_send_json_success(ob_get_clean());
}
add_action('wp_ajax_ybt_get_recent_additions', 'ybt_get_recent_additions_handler');

/**
 * Get single tracking details for admin packing page
 */
function ybt_get_tracking_details_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    global $wpdb;
    $tracking_id = intval($_POST['tracking_id'] ?? 0);

    if (empty($tracking_id)) {
        wp_send_json_error('Invalid tracking ID');
        return;
    }

    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
        $tracking_id
    ));

    if (!$item) {
        wp_send_json_error('Tracking not found');
        return;
    }

    // Get username
    $username = 'Unknown';
    if ($item->user_id) {
        $user = get_user_by('ID', $item->user_id);
        if ($user) {
            $username = $user->user_login;
        }
    } elseif ($item->username) {
        $username = $item->username;
    }

    wp_send_json_success(array(
        'id' => $item->id,
        'tracking_number' => $item->tracking_number,
        'status' => str_replace('_', ' ', ucfirst($item->status)),
        'shipping_method' => $item->shipping_method,
        'username' => $username,
        'user_id' => $item->user_id,
        'description' => $item->description,
        'item_count' => $item->item_count,
        'weight' => $item->weight,
        'images' => $item->images,
        'date_added' => $item->date_added,
        'date_received' => $item->date_received,
        'date_packed' => $item->date_packed,
        'date_completed' => $item->date_completed,
        'batch_id' => $item->batch_id,
        'is_complete' => $item->is_complete
    ));
}
add_action('wp_ajax_ybt_get_tracking_details', 'ybt_get_tracking_details_handler');

/**
 * Get carton details for a batch (Admin Manage Cartons page)
 */
function ybt_get_carton_details_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    global $wpdb;
    $batch_id = sanitize_text_field($_POST['batch_id'] ?? '');

    if (empty($batch_id)) {
        wp_send_json_error('Batch ID is required');
        return;
    }

    // Get cartons for this batch
    $cartons = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ybt_cartons WHERE batch_id = %s ORDER BY carton_number ASC",
        $batch_id
    ));

    if (empty($cartons)) {
        wp_send_json_success(array());
        return;
    }

    // Format carton data
    $carton_data = array();
    foreach ($cartons as $carton) {
        $carton_data[] = array(
            'id' => $carton->id,
            'batch_id' => $carton->batch_id,
            'carton_number' => $carton->carton_number,
            'weight_kg' => $carton->weight_kg,
            'payment_amount' => $carton->payment_amount,
            'payment_currency' => $carton->payment_currency,
            'carton_image' => $carton->carton_image,
            'date_created' => $carton->date_created
        );
    }

    wp_send_json_success($carton_data);
}
add_action('wp_ajax_ybt_get_carton_details', 'ybt_get_carton_details_handler');

/**
 * Attach cartons to a batch (Admin Process Packing)
 */
function ybt_attach_cartons_to_batch_v2_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    global $wpdb;
    $batch_id = sanitize_text_field($_POST['batch_id'] ?? '');
    $carton_data = isset($_POST['carton_data']) ? json_decode(wp_unslash($_POST['carton_data']), true) : array();

    if (empty($batch_id)) {
        wp_send_json_error('Batch ID is required');
        return;
    }

    if (empty($carton_data) || !is_array($carton_data)) {
        wp_send_json_error('Carton data is required');
        return;
    }

    $cartons_table = $wpdb->prefix . 'ybt_cartons';

    // Delete existing cartons for this batch
    $wpdb->delete($cartons_table, array('batch_id' => $batch_id));

    // Load WordPress file upload functions
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    if (!function_exists('wp_upload_dir')) {
        require_once(ABSPATH . 'wp-includes/functions.php');
    }

    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'] . '/ybt-cartons/';
    $base_path = $upload_dir['basedir'] . '/ybt-cartons/';

    // Create directory if it doesn't exist
    if (!file_exists($base_path)) {
        wp_mkdir_p($base_path);
    }

    // Insert new cartons
    $inserted = 0;
    foreach ($carton_data as $index => $carton) {
        $weight = isset($carton['weight']) ? floatval($carton['weight']) : 0;
        $amount = isset($carton['amount']) ? floatval($carton['amount']) : 0;
        $currency = isset($carton['currency']) ? sanitize_text_field($carton['currency']) : 'CNY';
        $image_urls = array();

        // Handle image uploads for this carton
        $file_key = 'carton_image_' . $index;
        if (!empty($_FILES[$file_key])) {
            $uploaded_files = $_FILES[$file_key];
            
            // Normalize files array if it's multiple (due to [])
            if (is_array($uploaded_files['name'])) {
                for ($i = 0; $i < count($uploaded_files['name']); $i++) {
                    if ($uploaded_files['error'][$i] === UPLOAD_ERR_OK) {
                        $file = array(
                            'name'     => $uploaded_files['name'][$i],
                            'type'     => $uploaded_files['type'][$i],
                            'tmp_name' => $uploaded_files['tmp_name'][$i],
                            'error'    => $uploaded_files['error'][$i],
                            'size'     => $uploaded_files['size'][$i]
                        );
                        
                        // SECURITY: Validate file before upload
                        $validation = ybt_validate_file_upload($file, array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
                        if (is_wp_error($validation)) {
                            continue; // Skip invalid files
                        }
                        
                        // Sanitize filename
                        $file['name'] = ybt_sanitize_filename($file['name']);
                        
                        $upload = wp_handle_upload($file, array(
                            'test_form' => false,
                            'upload_dir' => $base_path,
                            'upload_url' => $base_url
                        ));
                        if (isset($upload['url']) && !isset($upload['error'])) {
                            $image_urls[] = $upload['url'];
                        }
                    }
                }
            } else {
                // Single file upload
                if ($uploaded_files['error'] === UPLOAD_ERR_OK) {
                    // SECURITY: Validate file before upload
                    $validation = ybt_validate_file_upload($uploaded_files, array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
                    if (!is_wp_error($validation)) {
                        // Sanitize filename
                        $uploaded_files['name'] = ybt_sanitize_filename($uploaded_files['name']);
                        
                        $upload = wp_handle_upload($uploaded_files, array(
                            'test_form' => false,
                            'upload_dir' => $base_path,
                            'upload_url' => $base_url
                        ));
                        if (isset($upload['url']) && !isset($upload['error'])) {
                            $image_urls[] = $upload['url'];
                        }
                    }
                }
            }
        }

        $wpdb->insert($cartons_table, array(
            'batch_id' => $batch_id,
            'carton_number' => $index + 1,
            'weight_kg' => $weight,
            'payment_amount' => $amount,
            'payment_currency' => $currency,
            'carton_image' => implode(',', $image_urls),
            'date_created' => current_time('mysql')
        ));

        $inserted++;
    }

    // Update tracking records to packing_in_progress if not already
    // Audit: snapshot the affected rows first so the previous status is recorded.
    $batched_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s",
        $batch_id
    ));

    $wpdb->update(
        $wpdb->prefix . 'ybt_tracking',
        array('status' => 'packing_in_progress'),
        array('batch_id' => $batch_id)
    );

    if ($batched_rows) {
        foreach ($batched_rows as $batched_row) {
            YBT_Tracking_Audit::log_after_raw_update(
                $batched_row->id,
                $batched_row,
                array('status' => 'packing_in_progress'),
                'packing'
            );
        }
    }

    wp_send_json_success(array(
        'message' => sprintf('%d carton(s) attached successfully', $inserted),
        'cartons' => $inserted
    ));
}
add_action('wp_ajax_ybt_attach_cartons_to_batch_v2', 'ybt_attach_cartons_to_batch_v2_handler');

/**
 * Attach cartons to multiple tracking IDs (Admin Process Packing)
 */
function ybt_attach_cartons_to_ids_v2_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    global $wpdb;
    $tracking_ids = isset($_POST['tracking_ids']) ? array_map('intval', explode(',', sanitize_text_field($_POST['tracking_ids']))) : array();
    $carton_data = isset($_POST['carton_data']) ? json_decode(wp_unslash($_POST['carton_data']), true) : array();

    if (empty($tracking_ids)) {
        wp_send_json_error('Tracking IDs are required');
        return;
    }

    if (empty($carton_data) || !is_array($carton_data)) {
        wp_send_json_error('Carton data is required');
        return;
    }

    $cartons_table = $wpdb->prefix . 'ybt_cartons';
    $tracking_table = $wpdb->prefix . 'ybt_tracking';

    // 1. Group all selected items into ONE shared batch ID
    // We try to find an existing batch ID among selected items, or create a new one
    $existing_batch = $wpdb->get_var($wpdb->prepare(
        "SELECT batch_id FROM {$tracking_table} WHERE id IN (" . implode(',', array_fill(0, count($tracking_ids), '%d')) . ") AND batch_id IS NOT NULL AND batch_id != '' LIMIT 1",
        $tracking_ids
    ));

    $shared_batch_id = $existing_batch ?: ('BATCH_' . date('Ymd_His') . '_' . wp_generate_password(6, false));

    // 2. Summary calculations for the tracking table updates
    $total_weight = 0;
    $total_amount = 0;
    $total_cartons = count($carton_data);
    $currency = 'CNY';

    foreach ($carton_data as $c) {
        $total_weight += floatval($c['weight'] ?? 0);
        $total_amount += floatval($c['amount'] ?? 0);
        if (!empty($c['currency'])) $currency = $c['currency'];
    }

    // 3. Update all selected tracking records with the shared Batch ID and summary data
    $placeholders = implode(',', array_fill(0, count($tracking_ids), '%d'));

    // The timeline and the customer push both need the pre-update status.
    $packing_before = array();
    $packing_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$tracking_table} WHERE id IN ($placeholders)",
        $tracking_ids
    ));
    foreach ($packing_rows as $row) {
        $packing_before[$row->id] = $row;
    }

    $packing_now = current_time('mysql');
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$tracking_table} 
         SET batch_id = %s, 
             status = 'packing_in_progress',
             date_packing_requested = %s,
             cartons = %d,
             weight_kg = %f,
             payment_amount = %f,
             payment_currency = %s
         WHERE id IN ($placeholders)",
        array_merge([$shared_batch_id, $packing_now, $total_cartons, $total_weight, $total_amount, $currency], $tracking_ids)
    ));

    if ($updated !== false) {
        $packing_change = array(
            'status' => 'packing_in_progress',
            'batch_id' => $shared_batch_id,
            'cartons' => $total_cartons,
            'date_packing_requested' => $packing_now,
        );
        foreach ($tracking_ids as $packing_id) {
            $before = isset($packing_before[$packing_id]) ? $packing_before[$packing_id] : null;
            YBT_Tracking_Audit::log_after_raw_update($packing_id, $before, $packing_change, 'packing');
            if ($before && $before->status !== 'packing_in_progress') {
                ybt_notify_status_change($before->user_id, $before->tracking_number, 'packing_in_progress');
            }
        }
    }

    // 4. Handle file uploads and insert/update cartons for this batch
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $upload_dir = wp_upload_dir();
    $base_path = $upload_dir['basedir'] . '/ybt-cartons/';
    if (!file_exists($base_path)) wp_mkdir_p($base_path);

    // Re-pack the batch: Delete existing cartons for this batch first
    $wpdb->delete($cartons_table, array('batch_id' => $shared_batch_id));

    $inserted_cartons = 0;
    foreach ($carton_data as $index => $carton) {
        $weight = floatval($carton['weight'] ?? 0);
        $amount = floatval($carton['amount'] ?? 0);
        $curr = sanitize_text_field($carton['currency'] ?? 'CNY');
        
        $image_urls = array();
        $file_key = 'carton_image_' . ($index + 1); // JS uses 1-based index
        
        if (!empty($_FILES[$file_key])) {
            $files = is_array($_FILES[$file_key]['name']) ? $_FILES[$file_key]['name'] : array($_FILES[$file_key]['name']);
            foreach ($files as $f_idx => $name) {
                if (empty($_FILES[$file_key]['tmp_name'][$f_idx])) continue;
                $file = array(
                    'name'     => $_FILES[$file_key]['name'][$f_idx],
                    'type'     => $_FILES[$file_key]['type'][$f_idx],
                    'tmp_name' => $_FILES[$file_key]['tmp_name'][$f_idx],
                    'error'    => $_FILES[$file_key]['error'][$f_idx],
                    'size'     => $_FILES[$file_key]['size'][$f_idx]
                );
                
                // SECURITY: Validate file before upload
                $validation = ybt_validate_file_upload($file, array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
                if (is_wp_error($validation)) {
                    continue; // Skip invalid files
                }
                
                // Sanitize filename
                $file['name'] = ybt_sanitize_filename($file['name']);
                
                $upload = wp_handle_upload($file, array('test_form' => false));
                if (isset($upload['url']) && !isset($upload['error'])) {
                    $image_urls[] = $upload['url'];
                }
            }
        }

        $requested_images = isset($carton['images']) && $carton['images'] !== ''
            ? implode(',', array_map('esc_url_raw', explode(',', $carton['images'])))
            : '';
        $images_str = !empty($image_urls) ? implode(',', $image_urls) : $requested_images;

        $wpdb->insert($cartons_table, array(
            'batch_id' => $shared_batch_id,
            'carton_number' => $index + 1,
            'weight_kg' => $weight,
            'payment_amount' => $amount,
            'payment_currency' => $curr,
            'carton_image' => $images_str,
            'date_created' => current_time('mysql')
        ));
        $inserted_cartons++;
    }

    wp_send_json_success(array(
        'message' => sprintf('%d carton(s) attached successfully to %d items.', $inserted_cartons, count($tracking_ids)),
        'batch_id' => $shared_batch_id
    ));
}
add_action('wp_ajax_ybt_attach_cartons_to_ids_v2', 'ybt_attach_cartons_to_ids_v2_handler');

/**
 * Helper function for status badge colors
 */
function ybt_get_status_badge_color($status) {
    $colors = array(
        'not_received' => 'secondary',
        'received' => 'info',
        'packing_in_progress' => 'warning',
        'packed' => 'primary',
        'completed' => 'success',
    );
    return $colors[$status] ?? 'secondary';
}

/**
 * Handle user request packing (AJAX version for web frontend)
 */
function ybt_request_packing_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }

    global $wpdb;
    $user_id = get_current_user_id();
    
    // Get and Sanitize parameters
    $tracking_ids = isset($_POST['tracking_ids']) ? sanitize_text_field($_POST['tracking_ids']) : '';
    $shipping_company_name = isset($_POST['shipping_company_name']) ? sanitize_text_field($_POST['shipping_company_name']) : '';
    $shipping_company_address = isset($_POST['shipping_company_address']) ? sanitize_textarea_field($_POST['shipping_company_address']) : '';
    $shipping_company_phone = isset($_POST['shipping_company_phone']) ? sanitize_text_field($_POST['shipping_company_phone']) : '';
    $shipping_mark = isset($_POST['shipping_mark']) ? sanitize_text_field($_POST['shipping_mark']) : '';
    $instructions = isset($_POST['instructions']) ? sanitize_textarea_field($_POST['instructions']) : '';

    if (empty($tracking_ids)) {
        wp_send_json_error('Please select at least one item');
        return;
    }

    if (empty($shipping_company_name) || empty($shipping_company_address) || empty($shipping_company_phone)) {
        wp_send_json_error('Please fill in all required shipping fields');
        return;
    }

    $ids_array = array_filter(array_map('intval', explode(',', $tracking_ids)));
    if (empty($ids_array)) {
        wp_send_json_error('No valid tracking IDs provided');
        return;
    }

    // Verify ownership
    $placeholders = implode(',', array_fill(0, count($ids_array), '%d'));
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE id IN ($placeholders) AND user_id = %d",
        array_merge($ids_array, array($user_id))
    );
    $valid_count = (int) $wpdb->get_var($query);

    if ($valid_count !== count($ids_array)) {
        wp_send_json_error('Security error: Some selected items do not belong to your account');
        return;
    }

    // Determine the shipping method (sea/air) of the parcels being packed
    $placeholders_merge = implode(',', array_fill(0, count($ids_array), '%d'));
    $shipping_method = $wpdb->get_var($wpdb->prepare(
        "SELECT shipping_method FROM {$wpdb->prefix}ybt_tracking WHERE id IN ($placeholders_merge) AND user_id = %d LIMIT 1",
        array_merge($ids_array, array($user_id))
    ));
    if (empty($shipping_method)) $shipping_method = 'sea';

    // Check if user already has an active packing request with the same shipping method (sea or air)
    // Wrap batch assignment in a transaction to prevent race conditions during concurrent packing requests
    $wpdb->query('START TRANSACTION');

    // If so, merge the new items into the existing batch instead of creating a separate one
    $existing_batch = $wpdb->get_var($wpdb->prepare(
        "SELECT batch_id FROM {$wpdb->prefix}ybt_tracking WHERE user_id = %d AND shipping_method = %s AND status = 'packing_in_progress' AND batch_id IS NOT NULL AND batch_id != '' LIMIT 1 FOR UPDATE",
        $user_id,
        $shipping_method
    ));
    
    if (!empty($existing_batch)) {
        // Merge into existing batch
        $batch_id = $existing_batch;
    } else {
        // Generate new collision-resistant Batch ID with microsecond timestamp and random hex
        $batch_id = 'BATCH_' . date('Ymd_His') . '_' . sprintf('%06d', (int)((microtime(true) - floor(microtime(true))) * 1000000)) . '_' . wp_generate_password(8, false);
    }
    
    // Format full shipping info (merge address and instructions for easier display)
    $final_address = $shipping_company_address;
    if (!empty($instructions)) {
        $final_address .= "\n\n--- Special Instructions ---\n" . $instructions;
    }

    // Update user profile with latest shipping info (auto-save for next time)
    update_user_meta($user_id, 'ybt_shipping_address', sanitize_textarea_field($shipping_company_address));
    update_user_meta($user_id, 'ybt_shipping_phone', sanitize_text_field($shipping_company_phone));
    update_user_meta($user_id, 'display_name', sanitize_text_field($shipping_company_name)); // Update display name as well if provided

    // Update tracking records
    $updated_count = 0;
    $table_name = $wpdb->prefix . 'ybt_tracking';
    
    foreach ($ids_array as $tracking_id) {
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => 'packing_in_progress',
                'batch_id' => $batch_id,
                'shipping_company_name' => $shipping_company_name,
                'shipping_address' => $final_address,
                'shipping_company_phone' => $shipping_company_phone,
                'shipping_mark' => $shipping_mark,
                'date_packing_requested' => current_time('mysql')
            ),
            array('id' => $tracking_id, 'user_id' => $user_id)
        );
        if ($result !== false) {
            $updated_count++;
            if (class_exists('YBT_Tracking_Events')) {
                YBT_Tracking_Events::record($tracking_id, 'packing_requested', array(
                    'user_id'    => $user_id,
                    'status_to'  => 'packing_in_progress',
                    'actor_id'   => $user_id,
                    'actor_name' => wp_get_current_user()->display_name,
                    'actor_role' => 'customer',
                    'source'     => 'web',
                    'meta'       => array('batch_id' => $batch_id),
                    'dedupe'     => true,
                ));
            }
        }
    }

    if ($updated_count > 0) {
        $wpdb->query('COMMIT');
        // Log the activity
        if (function_exists('ybt_log_activity')) {
            ybt_log_activity($user_id, 'request_packing', array(
                'batch_id' => $batch_id,
                'items' => $updated_count,
                'shipping_company' => $shipping_company_name
            ));
        }
        
        // Push notification will be handled later if needed via hooks
        do_action('ybt_packing_requested', $batch_id, $user_id, $ids_array);

        wp_send_json_success(array(
            'batch_id' => $batch_id,
            'items_count' => $updated_count,
            'message' => 'Packing request submitted successfully. You can track your batch status in the dashboard.'
        ));
    } else {
        // Nothing was updated — roll back so the transaction isn't left open
        // (an open transaction would keep the FOR UPDATE row locks held until
        // the request ends).
        $wpdb->query('ROLLBACK');
        wp_send_json_error('Failed to update tracking items. Please try again.');
    }
}
add_action('wp_ajax_ybt_request_packing', 'ybt_request_packing_handler');

/**
 * AJAX: Return the rendered item rows for one user group on the Process Packing
 * page. The page renders only group headers (cheap COUNT query); this supplies
 * the rows the first time a group is expanded.
 *
 * POST: user (group username), search (optional mirror of the page filter)
 * Returns: { html, shown, total, truncated }
 */
function ybt_get_packing_group_rows_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $username = isset($_POST['user']) ? sanitize_text_field(wp_unslash($_POST['user'])) : '';
    $search   = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    if ($username === '') {
        wp_send_json_error('Missing user');
        return;
    }

    $limit = max(1, (int) apply_filters('ybt_packing_group_rows_limit', 200));

    $result = YBT_Packing::get_filtered_requests($username, $search, $limit);
    $items  = $result['items'];

    if (empty($items)) {
        wp_send_json_success(array(
            'html'      => '<tr><td colspan="10" class="text-center text-muted p-4">' . esc_html__('No packing requests found for this user.', 'ybt-packer') . '</td></tr>',
            'shown'     => 0,
            'total'     => 0,
            'truncated' => false,
        ));
        return;
    }

    $warehouse_cache = YBT_Packing::get_warehouse_cache();

    ob_start();
    // Reuses templates/components/admin-packing-item-rows.php so AJAX-served
    // rows always match the canonical row markup.
    include YBT_PLUGIN_PATH . 'templates/components/admin-packing-item-rows.php';
    $html = ob_get_clean();

    wp_send_json_success(array(
        'html'      => $html,
        'shown'     => count($items),
        'total'     => (int) $result['total'],
        'truncated' => (bool) $result['truncated'],
    ));
}
add_action('wp_ajax_ybt_get_packing_group_rows', 'ybt_get_packing_group_rows_handler');
