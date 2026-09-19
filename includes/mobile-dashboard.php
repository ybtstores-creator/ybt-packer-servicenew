<?php
/**
 * YBT Mobile Dashboard Handlers
 */
if (!defined('ABSPATH')) exit;

/**
 * Mobile dashboard information
 */
function ybt_mobile_dashboard_info_handler() {
    ybt_clear_output_buffers();

    // FIXED: Check Authorization header first, then fallback to POST (matches mobile-tracking.php pattern)
    $token = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = trim($matches[1]);
        }
    } elseif (!empty($_POST['token'])) {
        // SECURITY: POST body only — $_REQUEST would accept ?token= and leak the
        // JWT into access logs, history and Referer headers.
        $token = sanitize_text_field(wp_unslash($_POST['token']));
    }

    $token_data = ybt_validate_mobile_token($token);
    if (!$token_data) {
        status_header(401);
        wp_send_json(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    global $wpdb;
    $user_id = $token_data['user_id'];

    // Helper logic for fetching dashboard data
    $fetch_data = function() use ($wpdb, $user_id) {
        if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
            return [
                'stats' => ['not_received' => 0, 'received' => 0, 'packed' => 0, 'completed' => 0],
                'payment' => null,
                'warehouse' => null
            ];
        }

        // Stats
        $stats = array(
            'not_received' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE user_id = %d AND status = 'not_received'",
                $user_id
            ))),
            'received' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE user_id = %d AND status = 'received'",
                $user_id
            ))),
            'packed' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE user_id = %d AND status = 'packed'",
                $user_id
            ))),
            'completed' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE user_id = %d AND status = 'completed'",
                $user_id
            )))
        );

        // Address & Announcement (Proactive table existence checks)
        $payment = null;
        if (function_exists('ybt_table_exists') && ybt_table_exists($wpdb->prefix . 'ybt_payment_settings')) {
            $payment = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ybt_payment_settings WHERE is_active = 1 LIMIT 1");
        }

        $custom_user_id = get_user_meta($user_id, 'ybt_custom_user_id', true) ?: 'Pending';

        $warehouse = null;
        if (function_exists('ybt_table_exists') && ybt_table_exists($wpdb->prefix . 'ybt_warehouse_settings')) {
            // SYNC FIX: Match website behavior by specifically fetching the 'Air' warehouse
            $warehouse = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ybt_warehouse_settings WHERE shipping_type = %s AND is_active = 1 ORDER BY date_created DESC LIMIT 1",
                'air'
            ));
            
            if ($warehouse && !empty($warehouse->address)) {
                $warehouse->address = ybt_smart_format_address($warehouse->address, $custom_user_id);
            }
        }
        
        $announcement = null;
        if (function_exists('ybt_table_exists') && ybt_table_exists($wpdb->prefix . 'ybt_admin_messages')) {
            $announcement = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ybt_admin_messages ORDER BY date_created DESC LIMIT 1");
        }

        return [
            'stats' => $stats,
            'payment_settings' => $payment,
            'warehouse_address' => $warehouse,
            'announcement' => $announcement,
            'user_profile' => [
                'shipping_address' => get_user_meta($user_id, 'ybt_shipping_address', true) ?: '',
                'shipping_phone'   => get_user_meta($user_id, 'ybt_shipping_phone', true) ?: '',
                'full_name'        => get_user_meta($user_id, 'display_name', true) ?: get_userdata($user_id)->display_name,
                'custom_user_id'   => $custom_user_id
            ]
        ];
    };

    // Use cache if available
    if (function_exists('ybt_cache')) {
        $dashboard_data = ybt_cache()->remember('mobile_dash_' . $user_id, $fetch_data, 600, 'dashboard');
    } else {
        $dashboard_data = $fetch_data();
    }

    wp_send_json([
        'success' => true,
        'data' => $dashboard_data
    ]);
}
/**
 * Admin: Update Warehouse Address from Mobile
 */
function ybt_mobile_admin_update_warehouse_handler() {
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

    $user_id = $token_data['user_id'];
    if (!user_can($user_id, 'manage_options')) {
        status_header(403);
        wp_send_json(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    $address = sanitize_textarea_field($_POST['address'] ?? '');
    if (empty($address)) {
        wp_send_json(['success' => false, 'message' => 'Address cannot be empty']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'ybt_warehouse_settings';

    // Update or insert the latest record (matches the unified logic)
    $existing_id = $wpdb->get_var("SELECT id FROM $table_name ORDER BY id DESC LIMIT 1");

    $data = array(
        'address'      => $address,
        'is_active'    => 1,
        'date_created' => current_time('mysql')
    );

    if ($existing_id) {
        $result = $wpdb->update($table_name, $data, array('id' => $existing_id));
    } else {
        $result = $wpdb->insert($table_name, $data);
    }

    if ($result !== false) {
        // Clear caches
        if (function_exists('ybt_cache')) {
            ybt_cache()->delete('ybt_warehouse_settings_latest', 'settings');
            ybt_cache()->delete('ybt_air_warehouse', 'settings'); // legacy 
        }
        wp_send_json(['success' => true, 'message' => 'Warehouse address updated successfully']);
    } else {
        wp_send_json(['success' => false, 'message' => 'Failed to update database']);
    }
}
add_action('wp_ajax_ybt_mobile_admin_update_warehouse', 'ybt_mobile_admin_update_warehouse_handler');
add_action('wp_ajax_nopriv_ybt_mobile_admin_update_warehouse', 'ybt_mobile_admin_update_warehouse_handler');

add_action('wp_ajax_ybt_mobile_dashboard_info', 'ybt_mobile_dashboard_info_handler');
add_action('wp_ajax_nopriv_ybt_mobile_dashboard_info', 'ybt_mobile_dashboard_info_handler');

