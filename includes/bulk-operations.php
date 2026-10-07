<?php
/**
 * YBT Bulk Operations AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle bulk status updates
 * Enhanced with security limits to prevent DoS attacks
 */
function ybt_bulk_update_status_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    
    // SECURITY: Parse and validate input
    $tracking_ids_raw = $_POST['tracking_ids'] ?? '';
    $status = sanitize_text_field($_POST['status'] ?? '');

    // SECURITY: Limit maximum items per bulk operation (prevent DoS)
    $max_bulk_items = 500;
    $ids = array_map('intval', explode(',', $tracking_ids_raw));
    $ids = array_filter($ids); // Remove empty values
    $ids = array_unique($ids); // Remove duplicates
    
    if (count($ids) > $max_bulk_items) {
        wp_send_json_error('Too many items. Maximum ' . $max_bulk_items . ' items per bulk operation.');
    }

    if (empty($ids) || empty($status)) {
        wp_send_json_error('Invalid input');
    }

    // SECURITY: Validate status against whitelist
    $valid_statuses = array('not_received', 'received', 'packing_in_progress', 'packed', 'completed');
    if (!in_array($status, $valid_statuses, true)) {
        wp_send_json_error('Invalid status value');
    }

    // Build update data with timestamps based on status
    $update_data = ['status' => $status];
    
    // Set appropriate timestamp based on status
    if ($status === 'received') {
        $update_data['date_received'] = current_time('mysql');
    } elseif ($status === 'packed') {
        $update_data['date_packed'] = current_time('mysql');
    } elseif ($status === 'completed') {
        $update_data['date_completed'] = current_time('mysql');
    }

    // Build SET clause dynamically with correct format specifiers
    $set_parts = [];
    $values = [];
    foreach ($update_data as $column => $value) {
        // Status is enum, timestamps are datetime
        if ($column === 'status') {
            $set_parts[] = "$column = %s";
        } else {
            $set_parts[] = "$column = %s";
        }
        $values[] = $value;
    }
    
    // Add ID placeholders
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $values = array_merge($values, $ids);

    // Audit: snapshot the rows BEFORE the bulk update so "old -> new" is accurate.
    $audit_before = array();
    if (class_exists('YBT_Tracking_Audit')) {
        foreach ($ids as $audit_id) {
            $audit_before[$audit_id] = YBT_Tracking_Audit::snapshot($audit_id);
        }
    }

    // Execute update
    $set_clause = implode(', ', $set_parts);
    $affected = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}ybt_tracking SET $set_clause WHERE id IN ($placeholders)",
        $values
    ));

    // Without this check a failed UPDATE still audits, notifies and reports success.
    if ($affected === false) {
        error_log('YBT: bulk_update_status failed: ' . $wpdb->last_error);
        wp_send_json_error('Bulk update failed: ' . $wpdb->last_error);
        return;
    }

    // Audit: attribute the bulk change to this admin, per tracking number.
    if (class_exists('YBT_Tracking_Audit')) {
        foreach ($ids as $audit_id) {
            YBT_Tracking_Audit::log_after_raw_update(
                $audit_id,
                isset($audit_before[$audit_id]) ? $audit_before[$audit_id] : null,
                $update_data,
                'bulk_status'
            );
        }
    }

    // Notify owners, from the same pre-update snapshot used for auditing.
    foreach ($audit_before as $notify_id => $before) {
        if ($before && $before->status !== $status) {
            ybt_notify_status_change($before->user_id, $before->tracking_number, $status);
        }
    }

    // Clear cache
    do_action('ybt_bulk_operation_complete');

    wp_send_json_success(count($ids) . " items updated.");
}
add_action('wp_ajax_ybt_bulk_update_status', 'ybt_bulk_update_status_handler');

/**
 * Handle bulk deleting tracking items
 * Enhanced with security limits to prevent DoS attacks
 */
function ybt_bulk_delete_items_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    
    // SECURITY: Parse and validate input with limits
    $tracking_ids_raw = $_POST['tracking_ids'] ?? '';
    $max_bulk_items = 500;
    $ids = array_map('intval', explode(',', $tracking_ids_raw));
    $ids = array_filter($ids);
    $ids = array_unique($ids);
    
    if (count($ids) > $max_bulk_items) {
        wp_send_json_error('Too many items. Maximum ' . $max_bulk_items . ' items per bulk operation.');
    }

    if (empty($ids)) {
        wp_send_json_error('No items selected');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    // Audit: capture tracking numbers + previous state before they are removed,
    // so the deletion is still attributable after the rows are gone.
    $deleted_items = $wpdb->get_results($wpdb->prepare(
        "SELECT id, tracking_number, username, images FROM {$wpdb->prefix}ybt_tracking WHERE id IN ($placeholders)",
        $ids
    ));

    // Delete the parcels' physical image files BEFORE the rows are gone —
    // through the unified cleanup engine (plan + execute), so freed-space
    // accounting has exactly one implementation across preview, delete,
    // bulk and prune flows. No field clearing here: the rows themselves
    // are deleted below.
    $plan = null;
    if (class_exists('YBT_File_Handler')) {
        $plan = YBT_File_Handler::plan_tracking_images(array('rows' => $deleted_items));
        YBT_File_Handler::execute_data_cleanup($plan, false);
    }

    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}ybt_tracking WHERE id IN ($placeholders)",
        $ids
    ));

    if ($deleted_items && class_exists('YBT_Tracking_Audit')) {
        foreach ($deleted_items as $deleted_item) {
            YBT_Tracking_Audit::log_edit($deleted_item->id, 'bulk_delete', array(), array(
                'summary'         => sprintf('Bulk deleted (user: %s)', $deleted_item->username ? $deleted_item->username : 'Anonymous'),
                'tracking_number' => $deleted_item->tracking_number,
                'log_activity'    => false,
            ));
        }

        if (function_exists('ybt_log_activity')) {
            ybt_log_activity(
                get_current_user_id(),
                'admin_bulk_delete_tracking',
                sprintf('Bulk deleted %d tracking number(s)', count($deleted_items))
            );
        }
    }

    do_action('ybt_bulk_operation_complete');

    $freed_kb = ($plan && isset($plan['size_bytes'])) ? round($plan['size_bytes'] / 1024, 1) : 0;
    wp_send_json_success(count($ids) . " items deleted." . ($freed_kb > 0 ? " Freed ~{$freed_kb} KB of images." : ''));
}
add_action('wp_ajax_ybt_bulk_delete_items', 'ybt_bulk_delete_items_handler');

/**
 * Bulk assign to user
 */
function ybt_bulk_assign_user_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $tracking_ids_raw = $_POST['tracking_ids'] ?? '';
    $max_bulk_items = 500;
    $ids = array_map('intval', explode(',', $tracking_ids_raw));
    $ids = array_filter($ids);
    $ids = array_unique($ids);

    if (count($ids) > $max_bulk_items) {
        wp_send_json_error('Too many items. Maximum ' . $max_bulk_items . ' items per bulk operation.');
    }
    if (empty($ids)) {
        wp_send_json_error('No items selected');
    }

    $username = sanitize_text_field($_POST['username'] ?? '');

    $user = get_user_by('login', $username);
    if (!$user) wp_send_json_error('User not found');

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    // Audit: snapshot before reassignment so the previous owner is recorded.
    $audit_before = array();
    if (class_exists('YBT_Tracking_Audit')) {
        foreach ($ids as $audit_id) {
            $audit_before[$audit_id] = YBT_Tracking_Audit::snapshot($audit_id);
        }
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}ybt_tracking SET user_id = %d, username = %s WHERE id IN ($placeholders)",
        array_merge([$user->ID, $user->user_login], $ids)
    ));

    if (class_exists('YBT_Tracking_Audit')) {
        foreach ($ids as $audit_id) {
            YBT_Tracking_Audit::log_after_raw_update(
                $audit_id,
                isset($audit_before[$audit_id]) ? $audit_before[$audit_id] : null,
                array('user_id' => $user->ID, 'username' => $user->user_login),
                'bulk_assign'
            );
        }
    }

    do_action('ybt_bulk_operation_complete');

    wp_send_json_success(count($ids) . " items assigned to " . $username);
}
add_action('wp_ajax_ybt_bulk_assign_user', 'ybt_bulk_assign_user_handler');

/**
 * Handle bulk importing tracking numbers from CSV
 */
function ybt_bulk_import_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('File upload failed.');
    }

    // Security: Validate file extension and size (max 5MB)
    $file_name = $_FILES['csv_file']['name'] ?? '';
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'], true)) {
        wp_send_json_error('Invalid file type. Only CSV files are allowed.');
    }

    if ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
        wp_send_json_error('CSV file exceeds the 5MB size limit.');
    }

    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $headers = fgetcsv($file);
    if (!$headers) {
        fclose($file);
        wp_send_json_error('Empty CSV');
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $username = wp_get_current_user()->user_login;
    $values = [];
    $placeholders = [];
    $imported_numbers = [];
    $timestamp = current_time('mysql');
    $count = 0;
    $max_rows = 1000;

    while (($data = fgetcsv($file)) !== false) {
        if ($count >= $max_rows) {
            fclose($file);
            wp_send_json_error("CSV exceeds maximum allowed limit of {$max_rows} rows per import.");
        }

        $tracking_number = strtoupper(trim(sanitize_text_field($data[0])));
        if (empty($tracking_number)) continue;

        $placeholders[] = "(%s, %d, %s, %s, %s, %s)";
        $values[] = $tracking_number;
        $values[] = $user_id;
        $values[] = $username;
        $values[] = 'received';
        $values[] = $timestamp;
        $values[] = $timestamp;
        $imported_numbers[] = $tracking_number;
        $count++;
    }

    if ($count > 0) {
        $sql = "INSERT INTO {$wpdb->prefix}ybt_tracking (tracking_number, user_id, username, status, date_added, date_received) VALUES " . implode(',', $placeholders);
        $wpdb->query($wpdb->prepare($sql, $values));

        // Audit: attribute the CSV import to the admin who ran it.
        if (class_exists('YBT_Tracking_Audit')) {
            foreach ($imported_numbers as $imported_number) {
                $new_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = %s",
                    $imported_number
                ));
                if ($new_id) {
                    YBT_Tracking_Audit::log_edit((int) $new_id, 'bulk_import', array(), array(
                        'summary'         => 'Imported from CSV',
                        'tracking_number' => $imported_number,
                        'log_activity'    => false,
                    ));
                }
            }
            if (function_exists('ybt_log_activity')) {
                ybt_log_activity($user_id, 'admin_bulk_import', sprintf('Imported %d tracking number(s) from CSV', $count));
            }
        }

        do_action('ybt_bulk_operation_complete');
    }

    fclose($file);
    wp_send_json_success("Imported $count items.");
}
add_action('wp_ajax_ybt_bulk_import', 'ybt_bulk_import_handler');
