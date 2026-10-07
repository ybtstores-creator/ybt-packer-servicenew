<?php
/**
 * YBT Admin Settings AJAX Handlers
 */

if (!defined('ABSPATH')) exit;

/**
 * Save Alert Settings
 */
function ybt_save_alert_settings_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $enabled = isset($_POST['popup_enabled']) ? 1 : 0;
    $message = isset($_POST['popup_message']) ? wp_kses_post($_POST['popup_message']) : '';
    
    update_option('ybt_popup_enabled', $enabled);
    update_option('ybt_popup_message', $message);

    // Handle Image Upload — replace any previous popup image first so the
    // old file doesn't leak on disk.
    if (!empty($_FILES['popup_image']['name'])) {
        if (function_exists('ybt_validate_file_upload')) {
            $validation = ybt_validate_file_upload($_FILES['popup_image'], array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
            if (is_wp_error($validation)) {
                wp_send_json_error('Image upload failed: ' . $validation->get_error_message());
            }
        }
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $old_popup = get_option('ybt_popup_image', '');
        $upload = wp_handle_upload($_FILES['popup_image'], array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'gif'          => 'image/gif',
                'webp'         => 'image/webp'
            )
        ));
        if (isset($upload['url'])) {
            if (!empty($old_popup) && $old_popup !== $upload['url'] && class_exists('YBT_File_Handler')) {
                YBT_File_Handler::delete_urls(array($old_popup));
            }
            update_option('ybt_popup_image', $upload['url']);
        } elseif (isset($upload['error'])) {
            wp_send_json_error('Upload error: ' . $upload['error']);
        }
    }

    if (isset($_POST['remove_popup_image']) && $_POST['remove_popup_image'] == '1') {
        $old_popup = get_option('ybt_popup_image', '');
        if (!empty($old_popup) && class_exists('YBT_File_Handler')) {
            YBT_File_Handler::delete_urls(array($old_popup));
        }
        update_option('ybt_popup_image', '');
    }

    if (function_exists('ybt_cache')) {
        ybt_cache()->flush_group('dashboard');
    }

    wp_send_json_success('Settings saved');
}
add_action('wp_ajax_ybt_save_alert_settings', 'ybt_save_alert_settings_handler');

/**
 * Save Email Settings
 */
function ybt_save_email_settings_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    update_option('ybt_email_from_name', sanitize_text_field($_POST['from_name']));
    update_option('ybt_email_from_email', sanitize_email($_POST['from_email']));
    update_option('ybt_email_notifications_enabled', isset($_POST['enable_notifications']) ? 1 : 0);
    
    wp_send_json_success('Email settings saved');
}
add_action('wp_ajax_ybt_save_email_settings', 'ybt_save_email_settings_handler');

/**
 * Save SMS Settings
 */
function ybt_save_sms_settings_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    update_option('ybt_sms_api_url', esc_url_raw($_POST['api_url']));
    update_option('ybt_sms_api_key', sanitize_text_field($_POST['api_key']));
    update_option('ybt_sms_sender_id', sanitize_text_field($_POST['sender_id']));
    
    wp_send_json_success('SMS settings saved');
}
add_action('wp_ajax_ybt_save_sms_settings', 'ybt_save_sms_settings_handler');

/**
 * Save API Security Settings
 */
function ybt_save_api_security_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $whitelist = isset($_POST['whitelist']) ? sanitize_textarea_field($_POST['whitelist']) : '';
    
    // Clean up whitelist (comma separated IPs)
    $ips = array_filter(array_map('trim', explode(',', $whitelist)));
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            wp_send_json_error('Invalid IP address in whitelist: ' . $ip);
        }
    }
    
    update_option('ybt_api_key_whitelist', implode(', ', $ips));
    wp_send_json_success('API Security settings saved');
}
add_action('wp_ajax_ybt_save_api_security', 'ybt_save_api_security_handler');

/**
 * Rotate API Key
 */
function ybt_rotate_api_key_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    if (!function_exists('ybt_encryption')) {
        wp_send_json_error('Encryption engine not loaded');
    }

    $encryption = ybt_encryption();
    $new_api_key = $encryption->generate_api_key();
    $new_hash = $encryption->hash_api_key($new_api_key);
    $display_key = substr($new_api_key, 0, 8) . '****';

    update_option('ybt_api_key_hash', $new_hash);
    update_option('ybt_api_key_display', $display_key);
    // SECURITY: bind the key to the rotating admin; unbound keys are rejected.
    update_option('ybt_api_key_user_id', get_current_user_id());

    ybt_log_security_event('API_KEY_ROTATED', array(
        'user_id' => get_current_user_id(),
        'ip' => ybt_get_client_ip()
    ));

    wp_send_json_success(array(
        'new_key' => $new_api_key,
        'display' => $display_key
    ));
}
add_action('wp_ajax_ybt_rotate_api_key', 'ybt_rotate_api_key_handler');

/**
 * Save Role Permission Mapping
 */
function ybt_save_role_permissions_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    // Parse serialized mapping data
    parse_str($_POST['mapping'], $data);
    
    if (!isset($data['mapping']) || !is_array($data['mapping'])) {
        wp_send_json_error('Invalid mapping data');
    }

    $final_mapping = array();
    $allowed_caps = array('dashboard', 'packing', 'reports', 'settings');
    $allowed_roles = array('editor', 'author', 'contributor', 'subscriber');

    foreach ($data['mapping'] as $cap => $roles) {
        if (!in_array($cap, $allowed_caps)) continue;
        
        $safe_roles = array();
        foreach ((array)$roles as $role) {
            if (in_array($role, $allowed_roles)) {
                $safe_roles[] = $role;
            }
        }
        $final_mapping[$cap] = $safe_roles;
    }

    update_option('ybt_permission_mapping', $final_mapping);
    wp_send_json_success('Permissions updated');
}
add_action('wp_ajax_ybt_save_role_permissions', 'ybt_save_role_permissions_handler');

/**
 * Add Dashboard Message
 */
function ybt_add_message_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
    $video_url = isset($_POST['video_url']) ? esc_url_raw($_POST['video_url']) : '';
    $image_url = '';

    if (empty($message)) wp_send_json_error('Message is required');

    // Handle Image Upload
    if (!empty($_FILES['message_image']['name'])) {
        if (function_exists('ybt_validate_file_upload')) {
            $validation = ybt_validate_file_upload($_FILES['message_image'], array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
            if (is_wp_error($validation)) {
                wp_send_json_error('Image upload failed: ' . $validation->get_error_message());
            }
        }
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload = wp_handle_upload($_FILES['message_image'], array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'gif'          => 'image/gif',
                'webp'         => 'image/webp'
            )
        ));
        if (isset($upload['url'])) {
            $image_url = $upload['url'];
        } elseif (isset($upload['error'])) {
            wp_send_json_error('Upload error: ' . $upload['error']);
        }
    }

    global $wpdb;
    $result = $wpdb->insert(
        $wpdb->prefix . 'ybt_admin_messages',
        array(
            'message' => $message,
            'video_url' => $video_url,
            'image_url' => $image_url,
            'date_created' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s')
    );

    if ($result) {
        // Clear dashboard cache to show new announcement immediately
        if (function_exists('ybt_cache')) {
            ybt_cache()->flush_group('dashboard');
        }
        wp_send_json_success('Message added');
    } else {
        wp_send_json_error('Failed to add message');
    }
}
add_action('wp_ajax_ybt_add_message', 'ybt_add_message_handler');

/**
 * Delete Dashboard Message
 */
function ybt_delete_message_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    if (!$id) wp_send_json_error('Invalid ID');

    global $wpdb;

    // Fetch the message image before deletion so the physical file is removed too.
    $message_image = $wpdb->get_var($wpdb->prepare(
        "SELECT image_url FROM {$wpdb->prefix}ybt_admin_messages WHERE id = %d",
        $id
    ));

    $result = $wpdb->delete(
        $wpdb->prefix . 'ybt_admin_messages',
        array('id' => $id),
        array('%d')
    );

    if ($result && !empty($message_image) && class_exists('YBT_File_Handler')) {
        YBT_File_Handler::delete_urls(array($message_image));
    }

    if ($result) {
        // Clear dashboard cache to reflect deletion immediately
        if (function_exists('ybt_cache')) {
            ybt_cache()->flush_group('dashboard');
        }
        wp_send_json_success('Message deleted');
    } else {
        wp_send_json_error('Failed to delete message');
    }
}
add_action('wp_ajax_ybt_delete_message', 'ybt_delete_message_handler');



/**
 * Save Payment Settings (bank details + optional QR image)
 * Called by templates/admin-payment-settings.php
 */
function ybt_save_payment_settings_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_payment_settings';

    $bank_name      = isset($_POST['bank_name']) ? sanitize_text_field(wp_unslash($_POST['bank_name'])) : '';
    $account_name   = isset($_POST['account_name']) ? sanitize_text_field(wp_unslash($_POST['account_name'])) : '';
    $account_number = isset($_POST['account_number']) ? sanitize_text_field(wp_unslash($_POST['account_number'])) : '';

    if ($bank_name === '' || $account_name === '' || $account_number === '') {
        wp_send_json_error('Bank name, account name and account number are required.');
    }

    $qr_code_image = '';
    if (!empty($_FILES['qr_code']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $file = $_FILES['qr_code'];
        if (function_exists('ybt_validate_file_upload')) {
            $validation = ybt_validate_file_upload($file, array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
            if (is_wp_error($validation)) {
                wp_send_json_error('QR code: ' . $validation->get_error_message());
            }
        }
        $upload = wp_handle_upload($file, array('test_form' => false));
        if (isset($upload['error'])) {
            wp_send_json_error('QR code upload failed: ' . $upload['error']);
        }
        $qr_code_image = $upload['url'];
    }

    $existing = $wpdb->get_row("SELECT id FROM $table WHERE is_active = 1 ORDER BY date_created DESC LIMIT 1");

    if ($existing) {
        $data = array(
            'bank_name'      => $bank_name,
            'account_name'   => $account_name,
            'account_number' => $account_number,
        );
        $formats = array('%s', '%s', '%s');
        if ($qr_code_image !== '') {
            $data['qr_code_image'] = $qr_code_image;
            $formats[] = '%s';
        }
        $updated = $wpdb->update($table, $data, array('id' => (int) $existing->id), $formats, array('%d'));
        if ($updated === false) {
            wp_send_json_error('Failed to save payment settings.');
        }
    } else {
        $inserted = $wpdb->insert(
            $table,
            array(
                'bank_name'      => $bank_name,
                'account_name'   => $account_name,
                'account_number' => $account_number,
                'qr_code_image'  => $qr_code_image,
                'is_active'      => 1,
                'date_created'   => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );
        if (!$inserted) {
            wp_send_json_error('Failed to save payment settings.');
        }
    }

    if (function_exists('ybt_cache')) {
        ybt_cache()->flush_group('dashboard');
    }

    wp_send_json_success('Payment settings saved.');
}
add_action('wp_ajax_ybt_save_payment_settings', 'ybt_save_payment_settings_handler');
