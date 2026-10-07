<?php
/**
 * YBT API Router
 *
 * Routes AJAX requests to the appropriate handlers.
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Router {

    private $auth_handler;
    private $tracking_handler;
    private $packing_handler;
    private $chat_handler;
    private $admin_handler;
    private $notifications_handler;

    public function __construct() {
        $this->auth_handler = new YBT_API_Auth_Handler();
        $this->tracking_handler = new YBT_API_Tracking_Handler();
        $this->packing_handler = new YBT_API_Packing_Handler();
        $this->chat_handler = new YBT_API_Chat_Handler();
        $this->admin_handler = new YBT_API_Admin_Handler();
        $this->notifications_handler = new YBT_API_Notifications_Handler();
    }

    /**
     * Register all API endpoints
     */
    public function register_endpoints() {
        $endpoints = array(
            // Auth Endpoints
            'ybt_mobile_login'           => array($this->auth_handler, 'login'),
            'ybt_mobile_register'        => array($this->auth_handler, 'register'),
            'ybt_mobile_update_fcm'      => array($this->auth_handler, 'update_fcm'),
            'ybt_mobile_register_token'  => array($this->auth_handler, 'update_fcm'),
            'ybt_mobile_unregister_token'=> array($this->auth_handler, 'unregister_fcm'),

            // User Data Endpoints
            'ybt_mobile_dashboard'       => array($this->tracking_handler, 'get_dashboard_stats'),
            'ybt_mobile_tracking'        => array($this->tracking_handler, 'get_tracking_items'),
            'ybt_mobile_add_tracking'    => array($this->tracking_handler, 'add_tracking'),
            'ybt_mobile_claim_parcel'    => array($this->tracking_handler, 'claim_parcel'),
            'ybt_mobile_search_tracking' => array($this->tracking_handler, 'search_tracking'),
            'ybt_mobile_dashboard_info'  => array($this->tracking_handler, 'get_dashboard_info'),

            // Packing Endpoints
            'ybt_mobile_request_packing' => array($this->packing_handler, 'request_packing'),
            'ybt_mobile_packed_batches'  => array($this->packing_handler, 'get_packed_batches'),
            'ybt_mobile_upload_payment'  => array($this->packing_handler, 'upload_payment'),

            // Chat Endpoints
            'ybt_mobile_chat'            => array($this->chat_handler, 'get_messages'),
            'ybt_mobile_send_chat'       => array($this->chat_handler, 'send_message'),
            'ybt_mobile_chat_unread'     => array($this->chat_handler, 'get_unread'),

            // Admin Endpoints
            'ybt_mobile_admin_dashboard' => array($this->admin_handler, 'get_dashboard_stats'),
            'ybt_mobile_admin_users'     => array($this->admin_handler, 'get_users'),
            'ybt_mobile_admin_user_details' => array($this->admin_handler, 'get_user_details'),
            'ybt_mobile_admin_reset_password' => array($this->admin_handler, 'reset_password'),
            'ybt_mobile_admin_orders'     => array($this->admin_handler, 'get_orders'),
            'ybt_mobile_admin_add_tracking_bulk' => array($this->admin_handler, 'add_tracking_bulk'),
            'ybt_mobile_assign_parcel'    => array($this->admin_handler, 'assign_parcel'),
            'ybt_mobile_admin_update_status' => array($this->admin_handler, 'update_status'),
            'ybt_mobile_scan_barcode'    => array($this->admin_handler, 'scan_barcode'),
            'ybt_mobile_admin_upload_image' => array($this->admin_handler, 'upload_image'),
            'ybt_mobile_admin_update_item' => array($this->admin_handler, 'update_item'),
            'ybt_mobile_admin_delete_tracking' => array($this->admin_handler, 'delete_tracking'),
            'ybt_mobile_admin_tracking_history' => array($this->admin_handler, 'get_tracking_history'),

            'ybt_mobile_admin_get_chats'    => array($this->chat_handler, 'admin_get_chats'),
            'ybt_mobile_admin_get_messages' => array($this->chat_handler, 'admin_get_messages'),
            'ybt_mobile_admin_send_chat'    => array($this->chat_handler, 'admin_send_message'),
            'ybt_mobile_admin_delete_chat'  => array($this->chat_handler, 'admin_delete_chat'),

            // Notifications (inbox + badge poll + parcel timeline). These were
            // previously stubbed to an empty response — the real handler existed
            // but was never wired in, so the app's Alerts tab/badge always saw 0.
            'ybt_mobile_notifications_v2'     => array($this->notifications_handler, 'get_notifications'),
            'ybt_mobile_notifications_unread' => array($this->notifications_handler, 'get_unread'),
            'ybt_mobile_notifications_read'   => array($this->notifications_handler, 'mark_read'),
            'ybt_mobile_parcel_timeline'      => array($this->notifications_handler, 'get_parcel_timeline'),

            // Admin announcements: unread list popped as a dialog at login
            // and the per-id acknowledge call. Rows live in the same inbox as
            // the entries above (type 'announcement'), so they also show in
            // the Alerts tab / bell until dismissed.
            'ybt_mobile_announcements_unread'  => array($this->notifications_handler, 'get_announcements'),
            'ybt_mobile_announcements_dismiss' => array($this->notifications_handler, 'dismiss_announcement'),

            'ybt_mobile_admin_packing_requests' => array($this->packing_handler, 'admin_get_packing_requests'),
            'ybt_mobile_admin_batch_details'    => array($this->packing_handler, 'admin_get_batch_details'),
            'ybt_mobile_admin_save_cartons'     => array($this->packing_handler, 'admin_save_cartons'),

            // Test Connection
            'ybt_test_connection'        => array($this, 'test_connection'),
        );

        // SECURITY: Every handler authenticates internally via JWT Bearer token
        // (see YBT_API_Handler_Base::authenticate()), so registering nopriv is safe.
        // The Android app authenticates with an Authorization header, which
        // WordPress treats as an unauthenticated (nopriv) request — without the
        // nopriv registration, token-authenticated calls die with "0" from
        // admin-ajax.php and every class-based endpoint is unreachable.
        foreach ($endpoints as $action => $callback) {
            add_action('wp_ajax_' . $action, $callback);
            add_action('wp_ajax_nopriv_' . $action, $callback);
        }
    }

    /**
     * Test Connection Endpoint
     */
    public function test_connection() {
        YBT_API_Response::success(array(
            'server_time' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => defined('YBT_VERSION') ? YBT_VERSION : 'Unknown',
            'mobile_api_loaded' => true
        ), 'Mobile API is working correctly!');
    }
}
