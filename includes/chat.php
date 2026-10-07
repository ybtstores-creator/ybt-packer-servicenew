<?php
/**
 * YBT Chat AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle retrieving chat users
 */
function ybt_get_chat_users_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $users = $wpdb->get_results("
        SELECT DISTINCT
            u.ID as user_id,
            u.user_login as username,
            (SELECT COUNT(*) FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID AND is_read = 0 AND is_admin = 0) as unread_count,
            (SELECT MAX(date_created) FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID) as last_message
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->prefix}ybt_chat c ON u.ID = c.user_id
        ORDER BY last_message DESC
    ");

    wp_send_json_success($users);
}
add_action('wp_ajax_ybt_get_chat_users', 'ybt_get_chat_users_handler');

/**
 * Handle retrieving chat for a specific user
 */
function ybt_get_user_chat_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    global $wpdb;

    $current_user_id = get_current_user_id();
    $target_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : $current_user_id;

    if (!current_user_can('manage_options') && $target_user_id !== $current_user_id) {
        wp_send_json_error('Unauthorized access');
    }

    // When admin reads this chat, mark user messages (is_admin=0) as read.
    // When user reads this chat, mark admin messages (is_admin=1) as read.
    if (current_user_can('manage_options')) {
        $wpdb->update($wpdb->prefix . 'ybt_chat', array('is_read' => 1), array('user_id' => $target_user_id, 'is_admin' => 0, 'is_read' => 0));
    } else {
        $wpdb->update($wpdb->prefix . 'ybt_chat', array('is_read' => 1), array('user_id' => $target_user_id, 'is_admin' => 1, 'is_read' => 0));
    }

    // Bound history: latest 500 messages. Conversations grow forever and this
    // endpoint is re-fetched on every send/poll, so it must never return the
    // whole table for a user.
    $chat_history_limit = (int) apply_filters('ybt_chat_history_limit', 500);
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM (
            SELECT * FROM {$wpdb->prefix}ybt_chat WHERE user_id = %d ORDER BY date_created DESC LIMIT %d
         ) AS recent
         ORDER BY date_created ASC",
        $target_user_id,
        $chat_history_limit
    ));

    wp_send_json_success($messages);
}
add_action('wp_ajax_ybt_get_user_chat', 'ybt_get_user_chat_handler');

/**
 * Handle sending a chat message
 */
function ybt_send_chat_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    global $wpdb;

    $current_user_id = get_current_user_id();
    $is_admin = current_user_can('manage_options');
    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    $target_user_id = isset($_POST['target_user_id']) ? intval($_POST['target_user_id']) : $current_user_id;

    if (!$is_admin) {
        $target_user_id = $current_user_id;
    }

    $user_info = get_userdata($target_user_id);
    if (!$user_info) wp_send_json_error('Invalid target user.');

    $result = $wpdb->insert($wpdb->prefix . 'ybt_chat', array(
        'user_id' => $target_user_id,
        'username' => $user_info->user_login,
        'message' => $message,
        'is_admin' => $is_admin ? 1 : 0,
        'is_read' => 0,
        'date_created' => current_time('mysql')
    ));

    if ($result) {
        wp_send_json_success('Message sent');
    } else {
        wp_send_json_error('Failed to send message');
    }
}
add_action('wp_ajax_ybt_send_chat', 'ybt_send_chat_handler');

/**
 * Handle retrieving chat messages for customer support chat
 * Called by user-chat.php template as 'ybt_get_chat_messages'
 */
function ybt_get_chat_messages_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    global $wpdb;

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('Not logged in');
    }

    // Bound history: latest 500 messages (customer support chat path).
    $chat_history_limit = (int) apply_filters('ybt_chat_history_limit', 500);
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM (
            SELECT * FROM {$wpdb->prefix}ybt_chat WHERE user_id = %d ORDER BY date_created DESC LIMIT %d
         ) AS recent
         ORDER BY date_created ASC",
        $current_user_id,
        $chat_history_limit
    ));

    // Map to template-expected format
    $formatted = array();
    if ($messages) {
        foreach ($messages as $msg) {
            $formatted[] = array(
                'sender_type' => !empty($msg->is_admin) ? 'admin' : 'user',
                'sender_name' => !empty($msg->is_admin) ? 'Support' : ($msg->username ?: 'You'),
                'message' => $msg->message,
                'attachment_url' => !empty($msg->attachment) ? $msg->attachment : '',
                'created_at' => $msg->date_created,
            );
        }
    }

    wp_send_json_success($formatted);
}
add_action('wp_ajax_ybt_get_chat_messages', 'ybt_get_chat_messages_handler');

/**
 * Handle sending a chat message from customer support chat
 * Called by user-chat.php template as 'ybt_send_chat_message'
 */
function ybt_send_chat_message_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');
    global $wpdb;

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('Not logged in');
    }

    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    if (empty($message) && empty($_FILES['attachment'])) {
        wp_send_json_error('No message or attachment provided');
    }

    $attachment_url = '';
    if (!empty($_FILES['attachment']['name'])) {
        if (function_exists('ybt_security')) {
            $upload = ybt_security()->secure_file_upload($_FILES['attachment'], array('jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'));
            if (!$upload['success']) {
                $error_msg = !empty($upload['errors']) ? implode(', ', $upload['errors']) : 'File upload failed';
                wp_send_json_error($error_msg);
                return;
            }
            $attachment_url = $upload['url'];
        } else {
            wp_send_json_error('Security module unavailable');
            return;
        }
    }

    $user_info = get_userdata($current_user_id);
    $username = $user_info ? $user_info->user_login : '';

    $result = $wpdb->insert($wpdb->prefix . 'ybt_chat', array(
        'user_id' => $current_user_id,
        'username' => $username,
        'message' => $message,
        'is_admin' => 0,
        'is_read' => 0,
        'attachment' => $attachment_url,
        'date_created' => current_time('mysql'),
    ));

    if ($result) {
        wp_send_json_success('Message sent');
    } else {
        wp_send_json_error('Failed to send message');
    }
}
add_action('wp_ajax_ybt_send_chat_message', 'ybt_send_chat_message_handler');
