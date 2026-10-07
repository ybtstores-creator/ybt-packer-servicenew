<?php
/**
 * YBT API Chat Handler
 * WhatsApp-style chat: text + image attachments, unread tracking.
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Chat_Handler extends YBT_API_Handler_Base {

    /**
     * Normalize a message row for the mobile app: full URLs for attachments,
     * consistent field names, friendly time fields.
     */
    private function shape_message($m) {
        if (!$m) return $m;
        if (!empty($m->attachment)) {
            // Some rows may store a bare /wp-content path — make it absolute.
            if (strpos($m->attachment, 'http') !== 0) {
                $m->attachment = home_url($m->attachment);
            }
        }
        $m->is_admin = intval($m->is_admin);
        $m->id = intval($m->id);
        $m->user_id = intval($m->user_id);
        $m->is_read = intval($m->is_read ?? 0);
        return $m;
    }

    /**
     * Validate + store an uploaded chat image. Returns URL string or ''.
     * Only images are allowed for chat (jpg/png/webp/gif), max 10 MB.
     */
    private function handle_attachment() {
        if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        // The plugin's hardened uploader validates extension, MIME (via
        // getimagesize), filename safety, compresses the image and stores it
        // under wp-content/uploads. Returns ['success'=>bool,'url'=>string].
        if (function_exists('ybt_security')) {
            $upload = ybt_security()->secure_file_upload($_FILES['attachment'], array('jpg', 'jpeg', 'png', 'gif', 'webp'));
            if (!empty($upload['success'])) return $upload['url'];
            return '';
        }

        // Fallback: standard WordPress handling with a size guard.
        if (!empty($_FILES['attachment']['size']) && $_FILES['attachment']['size'] > 10485760) {
            return '';
        }
        if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload = wp_handle_upload($_FILES['attachment'], array('test_form' => false, 'mimes' => array(
            'jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        )));
        if (isset($upload['error'])) return '';
        return $upload['url'];
    }

    /**
     * Get chat messages for the logged-in user (both sides of the conversation).
     */
    public function get_messages() {
        $this->authenticate();

        global $wpdb;
        // Incoming messages (from support) become read once the user opens chat.
        $wpdb->update($wpdb->prefix . 'ybt_chat', array('is_read' => 1), array('user_id' => $this->user_id, 'is_admin' => 1, 'is_read' => 0));

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_chat WHERE user_id = %d ORDER BY date_created ASC, id ASC",
            $this->user_id
        ));

        $messages = array_map(array($this, 'shape_message'), $messages);
        YBT_API_Response::success(array('messages' => $messages));
    }

    /**
     * Lightweight poll endpoint: just the newest message id + unread count.
     */
    public function get_unread() {
        $this->authenticate();
        global $wpdb;
        $last_id = intval($wpdb->get_var($wpdb->prepare(
            "SELECT MAX(id) FROM {$wpdb->prefix}ybt_chat WHERE user_id = %d",
            $this->user_id
        )));
        $unread = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_chat WHERE user_id = %d AND is_admin = 1 AND is_read = 0",
            $this->user_id
        )));
        YBT_API_Response::success(array('last_id' => $last_id, 'unread' => $unread));
    }

    /**
     * Send chat message (text and/or image). Multipart with 'attachment' file field.
     */
    public function send_message() {
        $this->authenticate();

        $message = $this->get_param('message', '', 'textarea');
        $attachment_url = $this->handle_attachment();

        if ($message === '' && $attachment_url === '') {
            YBT_API_Response::error('Message or attachment required', 'empty_message');
        }

        global $wpdb;
        $user = get_userdata($this->user_id);

        $result = $wpdb->insert($wpdb->prefix . 'ybt_chat', array(
            'user_id' => $this->user_id,
            'username' => $user ? $user->user_login : '',
            'message' => $message,
            'attachment' => $attachment_url,
            'is_admin' => 0,
            'date_created' => current_time('mysql'),
            'is_read' => 0,
        ));

        if ($result) {
            YBT_API_Response::success(null, 'Message sent');
        } else {
            YBT_API_Response::error('Failed to send message', 'insert_failed');
        }
    }

    /**
     * Admin: Get all chat users (conversation list).
     */
    public function admin_get_chats() {
        $this->require_admin();

        global $wpdb;
        $search = $this->get_param('search', '', 'text', 'get');
        $search_sql = "";
        if (!empty($search)) {
            $search_sql = $wpdb->prepare("AND (u.user_login LIKE %s OR u.display_name LIKE %s)", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
        }

        $chat_users = $wpdb->get_results("
            SELECT DISTINCT
                u.ID as user_id,
                u.user_login as username,
                u.display_name,
                (SELECT message FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID ORDER BY date_created DESC, id DESC LIMIT 1) as last_message,
                (SELECT attachment FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID ORDER BY date_created DESC, id DESC LIMIT 1) as last_attachment,
                (SELECT is_admin FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID ORDER BY date_created DESC, id DESC LIMIT 1) as last_is_admin,
                (SELECT date_created FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID ORDER BY date_created DESC, id DESC LIMIT 1) as last_message_date,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID AND is_admin = 0 AND is_read = 0) as unread_count
            FROM {$wpdb->users} u
            JOIN {$wpdb->prefix}ybt_chat c ON u.ID = c.user_id
            WHERE 1=1 $search_sql
            ORDER BY last_message_date DESC
        ");

        foreach ($chat_users as &$user) {
            $user->user_id = intval($user->user_id);
            $user->unread_count = intval($user->unread_count);
            $user->last_is_admin = intval($user->last_is_admin);
            // WhatsApp-style preview line for image-only messages.
            if (empty($user->last_message) && !empty($user->last_attachment)) {
                $user->last_message = '📷 Photo';
            }
            if (!empty($user->last_attachment) && strpos($user->last_attachment, 'http') !== 0) {
                $user->last_attachment = home_url($user->last_attachment);
            }
            $user->custom_user_id = get_user_meta($user->user_id, 'ybt_custom_user_id', true) ?: 'Pending';
        }

        YBT_API_Response::success(array('chats' => $chat_users));
    }

    /**
     * Admin: Get specific user chat (also marks user's outgoing as read).
     */
    public function admin_get_messages() {
        $this->require_admin();

        $target_user_id = $this->get_param('target_user_id', 0, 'int', 'get');
        if (!$target_user_id) {
            $target_user_id = $this->get_param('user_id', 0, 'int', 'get');
        }
        if (!$target_user_id) {
            YBT_API_Response::error('Target user ID required', 'missing_id');
            return;
        }

        global $wpdb;
        // Messages the user sent (is_admin = 0) are read when admin opens the chat.
        $wpdb->update($wpdb->prefix . 'ybt_chat', array('is_read' => 1), array('user_id' => $target_user_id, 'is_admin' => 0, 'is_read' => 0));

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_chat WHERE user_id = %d ORDER BY date_created ASC, id ASC",
            $target_user_id
        ));

        $messages = array_map(array($this, 'shape_message'), $messages);
        YBT_API_Response::success(array('messages' => $messages));
    }

    /**
     * Admin: Send chat message (text and/or image) to a user.
     */
    public function admin_send_message() {
        $this->require_admin();

        $target_user_id = $this->get_param('target_user_id', 0, 'int');
        if (!$target_user_id) {
            $target_user_id = $this->get_param('user_id', 0, 'int');
        }
        $message = $this->get_param('message', '', 'textarea');
        $attachment_url = $this->handle_attachment();

        if (!$target_user_id || ($message === '' && $attachment_url === '')) {
            YBT_API_Response::error('Target user and message or attachment required', 'missing_data');
            return;
        }

        global $wpdb;
        $user_info = get_userdata($target_user_id);
        if (!$user_info) {
            YBT_API_Response::error('User not found', 'not_found', 404);
            return;
        }

        $result = $wpdb->insert($wpdb->prefix . 'ybt_chat', array(
            'user_id' => $target_user_id,
            'username' => $user_info->user_login,
            'message' => $message,
            'attachment' => $attachment_url,
            'is_admin' => 1,
            'date_created' => current_time('mysql'),
            'is_read' => 0,
        ));

        if ($result) {
            if (class_exists('YBT_Notifications')) {
                $notif_message = !empty($message) ? $message : '📷 Photo';
                $chat_message_id = (int) $wpdb->insert_id;
                $sender = wp_get_current_user();
                // Inbox row + instant FCM push — the inbox row is what makes
                // the app's badge/popup see chat messages. sender_id/name let
                // the notification tap deep-link into this conversation.
                YBT_Notifications::notify(
                    $target_user_id,
                    'New message',
                    'Support: ' . $notif_message,
                    array(
                        'type' => 'chat_message',
                        'chat_message_id' => $chat_message_id,
                        'sender_id' => $sender->ID,
                        'sender_name' => $sender->display_name ?: $sender->user_login,
                    ),
                    array('type' => 'chat_message')
                );
            }
            YBT_API_Response::success(null, 'Message sent');
        } else {
            YBT_API_Response::error('Failed to send message', 'insert_failed');
        }
    }

    /**
     * Admin: Delete a chat message
     */
    public function admin_delete_chat() {
        $this->require_admin();

        $message_id = $this->get_param('message_id', 0, 'int');
        if (!$message_id) {
            YBT_API_Response::error('Message ID required', 'missing_id');
            return;
        }

        global $wpdb;
        $deleted = $wpdb->delete("{$wpdb->prefix}ybt_chat", array('id' => $message_id));

        if ($deleted) {
            YBT_API_Response::success(null, 'Message deleted');
        } else {
            YBT_API_Response::error('Failed to delete message', 'delete_failed');
        }
    }
}
