<?php
/**
 * YBT Settings & Utility AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle resetting all system data
 */
function ybt_reset_data_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $confirm = isset($_POST['confirm_truncate']) ? sanitize_text_field($_POST['confirm_truncate']) : '';
    if ($confirm !== 'YES_I_AM_SURE') {
        wp_send_json_error('Confirmation token required to reset system data');
    }

    global $wpdb;

    $tables = array(
        'ybt_tracking', 'ybt_admin_messages', 'ybt_chat', 'ybt_cartons',
        'ybt_payment_settings', 'ybt_warehouse_settings',
        'ybt_notifications', 'ybt_activity_log'
    );

    foreach ($tables as $table) {
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}$table");
    }

    if (function_exists('ybt_log_activity')) {
        ybt_log_activity(get_current_user_id(), 'reset_data', 'All system data reset by admin.');
    }
    wp_send_json_success('All data has been reset.');
}
add_action('wp_ajax_ybt_reset_data', 'ybt_reset_data_handler');

/**
 * Handle recreating database tables
 */
function ybt_recreate_tables_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    if (class_exists('YBT_Database')) {
        YBT_Database::create_tables(true);
        wp_send_json_success('Tables recreated.');
    } else {
        wp_send_json_error('YBT_Database class not found.');
    }
}
add_action('wp_ajax_ybt_recreate_tables', 'ybt_recreate_tables_handler');

/**
 * Handle fixing null shipping methods
 */
function ybt_fix_null_shipping_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    global $wpdb;

    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}ybt_tracking SET shipping_method = %s WHERE shipping_method IS NULL OR shipping_method = ''",
        'sea'
    ));

    wp_send_json_success('Fixed missing shipping methods.');
}
add_action('wp_ajax_ybt_fix_null_shipping', 'ybt_fix_null_shipping_handler');

/**
 * Handle resetting specific user data
 */
function ybt_reset_user_data_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    global $wpdb;

    $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
    if (empty($username)) {
        wp_send_json_error('No username provided');
    }

    // Delete tracking and chat for this user
    $tracking_deleted = $wpdb->delete($wpdb->prefix . 'ybt_tracking', array('username' => $username), array('%s'));
    $chat_deleted = $wpdb->delete($wpdb->prefix . 'ybt_chat', array('username' => $username), array('%s'));

    if (function_exists('ybt_log_activity')) {
        ybt_log_activity(get_current_user_id(), 'reset_user', "Data reset for user: $username");
    }
    wp_send_json_success("Deleted " . intval($tracking_deleted) . " tracking records and " . intval($chat_deleted) . " chat messages for: $username");
}
add_action('wp_ajax_ybt_reset_user_data', 'ybt_reset_user_data_handler');

/**
 * Handle assigning missing YBT user IDs
 */
function ybt_assign_missing_user_ids_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $users = get_users(array('meta_key' => 'ybt_custom_user_id', 'meta_compare' => 'NOT EXISTS'));
    $assigned = 0;

    foreach ($users as $user) {
        $existing_id = get_user_meta($user->ID, 'ybt_custom_user_id', true);
        if (empty($existing_id) || $existing_id === 'Pending') {
            $new_id = 'YBT' . str_pad($user->ID, 5, '0', STR_PAD_LEFT);
            update_user_meta($user->ID, 'ybt_custom_user_id', $new_id);
            $assigned++;
        }
    }

    // Also check for users with 'Pending' value
    $pending_users = get_users(array('meta_key' => 'ybt_custom_user_id', 'meta_value' => 'Pending'));
    foreach ($pending_users as $user) {
        $new_id = 'YBT' . str_pad($user->ID, 5, '0', STR_PAD_LEFT);
        update_user_meta($user->ID, 'ybt_custom_user_id', $new_id);
        $assigned++;
    }

    wp_send_json_success("Assigned IDs to $assigned users.");
}
add_action('wp_ajax_ybt_assign_missing_user_ids', 'ybt_assign_missing_user_ids_handler');

/**
 * Handle optimizing existing images
 */
function ybt_optimize_existing_images_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_tracking';
    $items = $wpdb->get_results("SELECT id, images FROM $table WHERE images IS NOT NULL AND images != ''");
    $cleaned = 0;

    foreach ($items as $item) {
        $images = explode(',', (string) $item->images);
        $valid_images = array();
        foreach ($images as $img) {
            $img = trim($img);
            if (!empty($img) && filter_var($img, FILTER_VALIDATE_URL)) {
                $valid_images[] = $img;
            }
        }
        $updated = implode(',', $valid_images);
        if ($updated !== $item->images) {
            $wpdb->update($table, array('images' => $updated), array('id' => $item->id), array('%s'), array('%d'));
            $cleaned++;
        }
    }

    wp_send_json_success("Optimized $cleaned records. Removed invalid image URLs.");
}
add_action('wp_ajax_ybt_optimize_existing_images', 'ybt_optimize_existing_images_handler');

/**
 * Handle getting recent imports
 */
function ybt_get_recent_imports_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ybt_activity_log 
         WHERE action = 'bulk_import' OR description LIKE 'Imported%' 
         ORDER BY date_created DESC LIMIT 10"
    ));

    if (!empty($logs)) {
        $html = '<table class="widefat fixed striped">';
        $html .= '<thead><tr><th>Date</th><th>Description</th><th>User</th></tr></thead><tbody>';
        foreach ($logs as $log) {
            $user_info = get_userdata($log->user_id);
            $username = $user_info ? $user_info->user_login : 'Unknown';
            $html .= '<tr>';
            $html .= '<td>' . esc_html(date('M j, Y g:i A', strtotime($log->date_created))) . '</td>';
            $html .= '<td>' . esc_html($log->description) . '</td>';
            $html .= '<td>' . esc_html($username) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html = '<p>No recent imports found.</p>';
    }

    wp_send_json_success($html);
}
add_action('wp_ajax_ybt_get_recent_imports', 'ybt_get_recent_imports_handler');
function ybt_site_import_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    if (!isset($_FILES['import_file'])) wp_send_json_error('No file');

    // SECURITY FIX: Reject files larger than 10MB to prevent DoS via large JSON uploads
    if ($_FILES['import_file']['size'] > 10 * 1024 * 1024) {
        wp_send_json_error('File too large. Maximum import size is 10MB.');
    }

    $data = json_decode(file_get_contents($_FILES['import_file']['tmp_name']), true);
    if (!$data) wp_send_json_error('Invalid JSON');

    // Whitelist of allowed tables to prevent injection via crafted JSON
    $allowed_tables = array(
        'ybt_tracking', 'ybt_admin_messages', 'ybt_chat', 'ybt_cartons',
        'ybt_payment_settings', 'ybt_warehouse_settings',
        'ybt_fcm_tokens', 'ybt_activity_log'
    );

    global $wpdb;
    $imported_tables = 0;
    $imported_rows = 0;
    $table_details = array();
    // Cache of real column names per table (column whitelist).
    $table_columns = array();

    foreach ($data as $table => $rows) {
        // Validate table name against whitelist
        $clean_table = sanitize_text_field($table);
        if (!in_array($clean_table, $allowed_tables, true)) {
            continue; // Skip unknown tables
        }
        if (!is_array($rows)) {
            continue; // Rows must be an array of objects.
        }
        $full_table = $wpdb->prefix . $clean_table;

        // SECURITY FIX: whitelist column names against the target table's real schema so a
        // crafted JSON key can never reach $wpdb->replace(). Without this, arbitrary column
        // names flow straight into the generated SQL (identifier/quoting injection), since
        // wpdb only backtick-wraps field names without escaping embedded backticks.
        if (!isset($table_columns[$clean_table])) {
            $table_columns[$clean_table] = ybt_site_import_table_columns($full_table);
        }
        $allowed_columns = $table_columns[$clean_table];
        if (empty($allowed_columns)) {
            continue; // Unknown/unreadable table schema.
        }

        $row_count = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            // Keep only safe, real column names.
            $clean_row = array();
            foreach ($row as $column => $value) {
                $column = (string) $column;
                if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
                    continue; // Reject any non-identifier key.
                }
                if (in_array($column, $allowed_columns, true)) {
                    $clean_row[$column] = $value;
                }
            }
            if (empty($clean_row)) {
                continue;
            }
            $result = $wpdb->replace($full_table, $clean_row);
            if ($result !== false) {
                $row_count++;
            }
        }
        if ($row_count > 0) {
            $imported_tables++;
            $imported_rows += $row_count;
            $table_details[] = $clean_table . ' (' . $row_count . ' rows)';
        }
    }

    if ($imported_rows === 0) {
        wp_send_json_error('No data was imported. The JSON file contained no recognized tables or all rows were invalid.');
        return;
    }

    $message = sprintf(
        'Import complete: %d row(s) imported across %d table(s): %s',
        $imported_rows,
        $imported_tables,
        implode(', ', $table_details)
    );
    wp_send_json_success($message);
}
add_action('wp_ajax_ybt_site_import', 'ybt_site_import_handler');

/**
 * Return the real column names for an import target table.
 *
 * The table name is always composed as `$wpdb->prefix . <whitelisted name>` upstream, so
 * interpolating it here is safe; it is still backtick-quoted for defense in depth.
 *
 * @param string $full_table Fully-prefixed table name.
 * @return string[] Column names, or an empty array if the schema cannot be read.
 */
function ybt_site_import_table_columns($full_table) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is whitelisted + prefixed.
    $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$full_table}`");
    return is_array($columns) ? $columns : array();
}

/**
 * Handle saving warehouse settings
 */
function ybt_save_warehouse_settings_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'ybt_warehouse_settings';

    $shipping_type = isset($_POST['shipping_type']) ? sanitize_text_field($_POST['shipping_type']) : 'air';
    $address       = isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '';
    $phone         = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $email         = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $hours         = isset($_POST['hours']) ? sanitize_text_field($_POST['hours']) : '';
    $is_active     = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    // Check if a record already exists for this shipping type
    $existing_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE shipping_type = %s ORDER BY id DESC LIMIT 1",
        $shipping_type
    ));

    $data = array(
        'shipping_type' => $shipping_type,
        'address'       => $address,
        'phone'         => $phone,
        'email'         => $email,
        'hours'         => $hours,
        'is_active'     => $is_active,
        'date_created'  => current_time('mysql')
    );

    $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%s');

    if ($existing_id) {
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $existing_id),
            $formats,
            array('%d')
        );
    } else {
        $result = $wpdb->insert($table_name, $data, $formats);
    }

    if ($result !== false) {
        wp_send_json_success('Warehouse settings saved successfully.');
    } else {
        wp_send_json_error('Failed to save settings: ' . $wpdb->last_error);
    }
}
add_action('wp_ajax_ybt_save_warehouse_settings', 'ybt_save_warehouse_settings_handler');
