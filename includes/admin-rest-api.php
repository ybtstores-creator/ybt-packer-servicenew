<?php
/**
 * YBT Admin REST API - Isolated Admin-Only Endpoints
 *
 * This file creates a completely separate REST API namespace (ybt-admin/v1)
 * that does NOT interfere with existing ybt/v1 endpoints or any AJAX handlers.
 *
 * Supports BOTH JWT Bearer token AND API key authentication.
 * Zero changes to existing code - old endpoints remain fully operational.
 */

if (!defined('ABSPATH')) {
    exit;
}

class YBT_Admin_REST_API {

    private $namespace = 'ybt-admin/v1';
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_pre_serve_request', array($this, 'add_cors_headers'), 10, 3);
    }

    public function add_cors_headers($served, $result, $request) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        $allowed_origins = array(get_option('siteurl'), home_url());
        $custom_origins = get_option('ybt_allowed_cors_origins', '');
        if (!empty($custom_origins)) {
            $allowed_origins = array_merge($allowed_origins, array_filter(array_map('trim', explode(',', $custom_origins))));
        }
        if (!empty($origin) && in_array($origin, $allowed_origins, true)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            header('Access-Control-Allow-Credentials: true');
        }
        // SECURITY: Do NOT emit ACAO for rejected origins — only the
        // whitelisted origin above gets the header.
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-API-Key, X-YBT-Signature, X-YBT-Timestamp, X-YBT-Current-Version');
        if ($request->get_method() === 'OPTIONS') {
            status_header(200);
            exit;
        }
        return $served;
    }

    public function register_routes() {
        // Auth endpoints
        register_rest_route($this->namespace, '/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'admin_login'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/auth/verify', array(
            'methods' => 'POST',
            'callback' => array($this, 'verify_api_key'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/auth/capabilities', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_capabilities'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // Dashboard
        register_rest_route($this->namespace, '/dashboard/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard_stats'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // Users
        register_rest_route($this->namespace, '/users', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_users'),
                'permission_callback' => array($this, 'require_admin_auth'),
                'args' => array(
                    'search' => array('sanitize_callback' => 'sanitize_text_field'),
                    'offset' => array('default' => 0, 'sanitize_callback' => 'absint'),
                    'limit' => array('default' => 50, 'sanitize_callback' => 'absint')
                )
            )
        ));

        register_rest_route($this->namespace, '/users/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_details'),
            'permission_callback' => array($this, 'require_admin_auth'),
            'args' => array('id' => array('required' => true, 'sanitize_callback' => 'absint'))
        ));

        register_rest_route($this->namespace, '/users/(?P<id>\d+)/reset-password', array(
            'methods' => 'POST',
            'callback' => array($this, 'reset_user_password'),
            'permission_callback' => array($this, 'require_admin_auth'),
            'args' => array(
                'id' => array('required' => true, 'sanitize_callback' => 'absint'),
                'new_password' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field')
            )
        ));

        // Tracking
        register_rest_route($this->namespace, '/tracking', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_tracking'),
                'permission_callback' => array($this, 'require_admin_auth'),
                'args' => array(
                    'status' => array('sanitize_callback' => 'sanitize_text_field'),
                    'search' => array('sanitize_callback' => 'sanitize_text_field'),
                    'user_id' => array('sanitize_callback' => 'absint'),
                    'has_images' => array('sanitize_callback' => 'sanitize_text_field'),
                    'offset' => array('default' => 0, 'sanitize_callback' => 'absint'),
                    'limit' => array('default' => 20, 'sanitize_callback' => 'absint')
                )
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_tracking_bulk'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        register_rest_route($this->namespace, '/tracking/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_tracking_by_id'),
                'permission_callback' => array($this, 'require_admin_auth'),
                'args' => array('id' => array('required' => true, 'sanitize_callback' => 'absint'))
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_tracking'),
                'permission_callback' => array($this, 'require_admin_auth'),
                'args' => array('id' => array('required' => true, 'sanitize_callback' => 'absint'))
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_tracking'),
                'permission_callback' => array($this, 'require_admin_auth'),
                'args' => array('id' => array('required' => true, 'sanitize_callback' => 'absint'))
            )
        ));

        register_rest_route($this->namespace, '/tracking/(?P<id>\d+)/assign', array(
            'methods' => 'POST',
            'callback' => array($this, 'assign_parcel'),
            'permission_callback' => array($this, 'require_admin_auth'),
            'args' => array(
                'id' => array('required' => true, 'sanitize_callback' => 'absint'),
                'user_id' => array('required' => true, 'sanitize_callback' => 'absint')
            )
        ));

        register_rest_route($this->namespace, '/tracking/(?P<id>\d+)/image', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_tracking_image'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        register_rest_route($this->namespace, '/tracking/(?P<id>\d+)/image', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_tracking_image'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        register_rest_route($this->namespace, '/tracking/(?P<id>\d+)/images', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_tracking_images_bulk'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // Scan
        register_rest_route($this->namespace, '/scan', array(
            'methods' => 'POST',
            'callback' => array($this, 'scan_barcode'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // Packing
        register_rest_route($this->namespace, '/packing-requests', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_packing_requests'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        register_rest_route($this->namespace, '/packing-requests/(?P<id>[a-zA-Z0-9_\-]+)/cartons', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_batch_cartons'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_batch_cartons'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Chat
        register_rest_route($this->namespace, '/chat/users', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_chat_users'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        register_rest_route($this->namespace, '/chat/(?P<user_id>\d+)/messages', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_chat_messages'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'send_chat_message'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Warehouse
        register_rest_route($this->namespace, '/warehouse', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_warehouse_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_warehouse_address'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Data Management
        register_rest_route($this->namespace, '/data/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'data_stats'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        register_rest_route($this->namespace, '/data/preview', array(
            'methods' => 'POST',
            'callback' => array($this, 'data_preview'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        register_rest_route($this->namespace, '/data/delete', array(
            'methods' => 'POST',
            'callback' => array($this, 'data_delete'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // Export
        register_rest_route($this->namespace, '/export', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_data'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // Settings — General (site name, currency, language, timezone, popup)
        register_rest_route($this->namespace, '/settings/general', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_general_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_general_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Settings — FCM Push Notifications
        register_rest_route($this->namespace, '/settings/fcm', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_fcm_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_fcm_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Settings — Payment (pricing, bank info)
        register_rest_route($this->namespace, '/settings/payment', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_payment_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_payment_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Settings — Email
        register_rest_route($this->namespace, '/settings/email', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_email_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_email_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Settings — Security
        register_rest_route($this->namespace, '/settings/security', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_security_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_security_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Settings — System Tools
        register_rest_route($this->namespace, '/tools', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_system_tools'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'run_system_tool'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Settings — App Updates
        register_rest_route($this->namespace, '/app-update', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_app_update_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_app_update_settings'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // Settings — Payment Evidence list
        register_rest_route($this->namespace, '/payment-evidence', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_payment_evidence'),
                'permission_callback' => array($this, 'require_admin_auth')
            )
        ));

        // AI Operator — Diagnostics
        register_rest_route($this->namespace, '/ai/diagnostics', array(
            'methods' => 'GET',
            'callback' => array($this, 'ai_diagnostics'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // AI Operator — Auto Repair
        register_rest_route($this->namespace, '/ai/repair', array(
            'methods' => 'POST',
            'callback' => array($this, 'ai_repair'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // AI Operator — Prune Images
        register_rest_route($this->namespace, '/ai/prune', array(
            'methods' => 'POST',
            'callback' => array($this, 'ai_prune'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));

        // AI Operator — Generate Report
        register_rest_route($this->namespace, '/ai/report', array(
            'methods' => 'GET',
            'callback' => array($this, 'ai_report'),
            'permission_callback' => array($this, 'require_admin_auth')
        ));
    }

    // =========================================================================
    // AUTHENTICATION
    // =========================================================================

    public function require_admin_auth($request) {
        // Try WordPress admin nonce (for admin pages using the REST API)
        $nonce = $request->get_header('X-WP-Nonce') ?? $request->get_param('_wpnonce');
        if (!empty($nonce) && wp_verify_nonce($nonce, 'wp_rest') && is_user_logged_in() && current_user_can('manage_options')) {
            return true;
        }

        // Try JWT via shared token extractor (Authorization header, POST, or GET)
        $token = ybt_get_request_token();
        if (!empty($token)) {
            $token_data = ybt_validate_mobile_token($token);
            if ($token_data) {
                $user = get_userdata($token_data['user_id']);
                if ($user && user_can($user->ID, 'manage_options')) {
                    // Establish the authenticated user as the current WP user so
                    // downstream handlers using current_user_can() (e.g. reset
                    // password) see the real admin instead of an anonymous session.
                    wp_set_current_user($user->ID);
                    return true;
                }
            }
            return new WP_Error('rest_forbidden', 'Invalid or expired token', array('status' => 401));
        }

        // Try API key from header
        $api_key = $request->get_header('X-API-Key');
        if ($api_key) {
            $stored_key_hash = get_option('ybt_api_key_hash');
            if (!empty($stored_key_hash)) {
            $provided_key_hash = function_exists('ybt_encryption') ? ybt_encryption()->hash_api_key($api_key) : (function_exists('hash') ? hash('sha256', $api_key) : '');
            if (empty($provided_key_hash)) {
                return new WP_Error('rest_forbidden', 'Server security module unavailable', array('status' => 500));
            }
            if (hash_equals($stored_key_hash, $provided_key_hash)) {
                // SECURITY FIX: Bind API key to user and check admin capability
                $key_user_id = (int) get_option('ybt_api_key_user_id', 0);
                if ($key_user_id <= 0) {
                    return new WP_Error('rest_forbidden', 'API key not bound to any user. Configure ybt_api_key_user_id.', array('status' => 403));
                }
                $key_user = get_userdata($key_user_id);
                if (!$key_user || !user_can($key_user->ID, 'manage_options')) {
                    return new WP_Error('rest_forbidden', 'API key user lacks admin privileges', array('status' => 403));
                }
                wp_set_current_user($key_user->ID);
                return true;
            }
        }
        return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 401));
        }

        return new WP_Error('rest_forbidden', 'Authentication required', array('status' => 401));
    }

    public function admin_login($request) {
        // Rate limiting for login attempts (5 attempts per 15 minutes)
        $ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';
        if (function_exists('ybt_check_rate_limit') && !ybt_check_rate_limit('admin_rest_login', 5, 900)) {
            return new WP_Error('rate_limit', 'Too many failed attempts. Please try again later.', array('status' => 429));
        }

        $params = $request->get_json_params();
        $username = sanitize_user($params['username'] ?? '');
        $password = $params['password'] ?? '';

        if (empty($username) || empty($password)) {
            return new WP_Error('missing_credentials', 'Username and password required', array('status' => 400));
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            if (function_exists('ybt_log_security_event')) {
                ybt_log_security_event('ADMIN_REST_LOGIN_FAILED', array('ip' => $ip, 'username' => $username));
            }
            return new WP_Error('invalid_credentials', 'Invalid username or password', array('status' => 401));
        }

        if (!user_can($user->ID, 'manage_options')) {
            return new WP_Error('not_admin', 'Admin access required', array('status' => 403));
        }

        // Clear rate limit on successful login
        if (function_exists('ybt_clear_rate_limit')) {
            ybt_clear_rate_limit('admin_rest_login');
        }

        $token = ybt_generate_mobile_token($user->ID);

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'custom_user_id' => get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: 'Pending',
                'role' => 'admin',
                'is_admin' => true,
                'token' => $token
            )
        ));
    }

    public function verify_api_key($request) {
        // Rate limiting: 10 attempts per 15 minutes
        $ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';
        if (function_exists('ybt_check_rate_limit') && !ybt_check_rate_limit('verify_api_key', 10, 900)) {
            return new WP_Error('rate_limit', 'Too many attempts. Please try again later.', array('status' => 429));
        }

        $params = $request->get_json_params();
        $api_key = $params['api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_Error('missing_key', 'API key required', array('status' => 400));
        }

        $stored_key_hash = get_option('ybt_api_key_hash');
        if (empty($stored_key_hash)) {
            return new WP_Error('not_configured', 'API key not configured on server', array('status' => 500));
        }

        $provided_key_hash = function_exists('ybt_encryption') ? ybt_encryption()->hash_api_key($api_key) : hash('sha256', $api_key);

        if (hash_equals($stored_key_hash, $provided_key_hash)) {
            return rest_ensure_response(array('success' => true, 'data' => array('verified' => true)));
        }

        return new WP_Error('invalid_key', 'Invalid API key', array('status' => 401));
    }

    public function get_capabilities($request) {
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'server_version' => defined('YBT_VERSION') ? YBT_VERSION : 'unknown',
                'capabilities' => array('bulk_tracking', 'image_upload', 'carton_management', 'export_csv', 'chat', 'warehouse'),
                'deprecated_endpoints' => array('ybt_admin_api'),
                'feature_flags' => array(
                    'feature_tracking_bulk_upload' => true,
                    'feature_analytics_v2' => false,
                    'feature_export_csv' => true,
                    'feature_chat_v2' => true,
                    'feature_warehouse_v2' => true,
                    'feature_security_audit' => false,
                    'feature_notifications_v2' => false
                )
            )
        ));
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function get_dashboard_stats($request) {
        global $wpdb;

        $tracking_table = $wpdb->prefix . 'ybt_tracking';

        // Guard: Check table exists before querying (prevents PHP fatal errors)
        if (function_exists('ybt_table_exists') && !ybt_table_exists($tracking_table)) {
            return new WP_Error('db_not_ready', 'Database table not initialized', array('status' => 503));
        }

        // Cache stats for 60 seconds to reduce database load from rapid polling
        $cache = function_exists('ybt_cache') ? ybt_cache() : null;
        if ($cache) {
            $stats = $cache->remember('admin_dashboard_stats', function() use ($wpdb, $tracking_table) {
                return self::compute_dashboard_stats($wpdb, $tracking_table);
            }, 60, 'dashboard');
        } else {
            $stats = self::compute_dashboard_stats($wpdb, $tracking_table);
        }

        return rest_ensure_response(array('success' => true, 'data' => $stats));
    }

    /**
     * Compute dashboard stats from a single grouped query.
     * Extracted for caching support.
     */
    private static function compute_dashboard_stats($wpdb, $tracking_table) {
        $total_users = count_users();

        // Single grouped query replaces 3 separate COUNT queries
        $row = $wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'not_received' THEN 1 ELSE 0 END) as not_received,
                SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received,
                SUM(CASE WHEN status = 'packing_in_progress' THEN 1 ELSE 0 END) as packing_in_progress,
                SUM(CASE WHEN status = 'packed' THEN 1 ELSE 0 END) as packed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN (images IS NULL OR images = '') AND status != 'not_received' THEN 1 ELSE 0 END) as without_images,
                SUM(CASE WHEN DATE(date_added) = CURDATE() THEN 1 ELSE 0 END) as today_added
            FROM {$tracking_table}
        ", ARRAY_A);

        if (!$row) {
            return array(
                'total_users' => $total_users['total_users'],
                'total_tracking' => 0,
                'not_received' => 0,
                'received' => 0,
                'packing_in_progress' => 0,
                'packed' => 0,
                'completed' => 0,
                'pending_packing' => 0,
                'without_images' => 0,
                'today_added' => 0
            );
        }

        return array(
            'total_users' => $total_users['total_users'],
            'total_tracking' => (int) $row['total'],
            'not_received' => (int) $row['not_received'],
            'received' => (int) $row['received'],
            'packing_in_progress' => (int) $row['packing_in_progress'],
            'packed' => (int) $row['packed'],
            'completed' => (int) $row['completed'],
            'pending_packing' => (int) $row['packing_in_progress'],
            'without_images' => (int) $row['without_images'],
            'today_added' => (int) $row['today_added']
        );
    }

    // =========================================================================
    // USERS
    // =========================================================================

    public function get_users($request) {
        $search = $request->get_param('search') ?: '';
        $offset = $request->get_param('offset') ?: 0;
        $limit = min($request->get_param('limit') ?: 50, 100);

        $args = array('order' => 'DESC', 'orderby' => 'registered', 'number' => $limit, 'offset' => $offset);
        if (!empty($search)) {
            $args['search'] = "*{$search}*";
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $user_query = new WP_User_Query($args);
        $users = array_map(function($user) {
            return array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'custom_id' => get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: 'Pending',
                'registered' => $user->user_registered,
                'role' => reset($user->roles)
            );
        }, $user_query->get_results());

        return rest_ensure_response(array(
            'success' => true,
            'data' => array('users' => $users, 'total' => $user_query->get_total())
        ));
    }

    public function get_user_details($request) {
        global $wpdb;
        $user_id = $request->get_param('id');
        $user = get_userdata($user_id);
        if (!$user) return new WP_Error('not_found', 'User not found', array('status' => 404));

        $parcels = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE user_id = %d ORDER BY date_added DESC LIMIT 10",
            $user_id
        ), ARRAY_A);

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'profile' => array(
                    'id' => $user->ID, 'username' => $user->user_login,
                    'email' => $user->user_email,
                    'custom_id' => get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: 'Pending',
                    'registered' => $user->user_registered
                ),
                'recent_parcels' => $parcels
            )
        ));
    }

    public function reset_user_password($request) {
        // SECURITY FIX: Verify the authenticated user has admin capability
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Admin access required to reset passwords', array('status' => 403));
        }

        $user_id = absint($request->get_param('id'));
        $new_password = $request->get_param('new_password');

        if (!$user_id || empty($new_password)) {
            return new WP_Error('missing_data', 'User ID and password required', array('status' => 400));
        }

        // Use centralized password validation from YBT_Security
        $security = function_exists('ybt_security') ? ybt_security() : null;
        $validation = $security ? $security->validate_password($new_password) : array('valid' => true, 'errors' => array());
        if (!$validation['valid']) {
            return new WP_Error('weak_password', implode(' ', $validation['errors']), array('status' => 400));
        }

        if (!get_userdata($user_id)) {
            return new WP_Error('not_found', 'User not found', array('status' => 404));
        }

        wp_set_password($new_password, $user_id);
        return rest_ensure_response(array('success' => true, 'data' => array('message' => 'Password reset successfully')));
    }

    // =========================================================================
    // TRACKING
    // =========================================================================

    public function get_tracking($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ybt_tracking';
        $status = $request->get_param('status');
        $search = $request->get_param('search');
        $user_id = $request->get_param('user_id');
        $has_images = $request->get_param('has_images');
        $offset = $request->get_param('offset') ?: 0;
        $limit = min($request->get_param('limit') ?: 20, 100);

        $where = array('1=1');
        $params = array();

        if ($status && $status !== 'all') { $where[] = 'status = %s'; $params[] = $status; }
        if ($user_id) { $where[] = 'user_id = %d'; $params[] = $user_id; }
        if ($has_images === 'no') { $where[] = "(images IS NULL OR images = '')"; }
        elseif ($has_images === 'yes') { $where[] = "images IS NOT NULL AND images != ''"; }
        if ($search) { $where[] = '(tracking_number LIKE %s OR description LIKE %s OR username LIKE %s)'; $wild = '%' . $wpdb->esc_like($search) . '%'; $params[] = $wild; $params[] = $wild; $params[] = $wild; }

        $where_sql = implode(' AND ', $where);
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where_sql ORDER BY date_added DESC LIMIT %d OFFSET %d", array_merge($params, array($limit, $offset))), ARRAY_A);
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where_sql", $params));

        // Ensure integer types
        foreach ($items as &$item) { $item['id'] = (int) $item['id']; $item['user_id'] = (int) $item['user_id']; $item['item_count'] = (int) $item['item_count']; }

        return rest_ensure_response(array('success' => true, 'data' => array('items' => $items, 'total' => $total)));
    }

    public function get_tracking_by_id($request) {
        global $wpdb;
        $id = $request->get_param('id');
        $table = $wpdb->prefix . 'ybt_tracking';

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if (!$item) return new WP_Error('not_found', 'Tracking item not found', array('status' => 404));

        // Ensure integer types for Android compatibility
        $item['id'] = (int) $item['id'];
        $item['user_id'] = (int) $item['user_id'];
        $item['item_count'] = (int) $item['item_count'];

        // Fetch cartons for this tracking item if it has a batch
        $cartons = array();
        if (!empty($item['batch_id'])) {
            $cartons = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ybt_cartons WHERE batch_id = %s ORDER BY carton_number ASC",
                $item['batch_id']
            ), ARRAY_A);
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'item' => $item,
                'cartons' => $cartons
            )
        ));
    }

    public function add_tracking_bulk($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $raw_numbers = isset($params['tracking_numbers']) ? $params['tracking_numbers'] : array();
        // Accept a JSON array or a single string with one number per line / comma.
        $tracking_numbers = class_exists('YBT_API_Admin_Handler')
            ? YBT_API_Admin_Handler::normalize_tracking_numbers($raw_numbers)
            : array_values(array_filter(array_map('trim', is_array($raw_numbers) ? $raw_numbers : preg_split('/[\r\n,;|]+/', (string) $raw_numbers))));
        $target_user_id = isset($params['target_user_id']) ? (int) $params['target_user_id'] : 0;
        $description = sanitize_text_field($params['description'] ?? '');
        $item_count = (int) ($params['item_count'] ?? 1);
        $shipping_method = sanitize_text_field($params['shipping_method'] ?? 'sea');

        if (empty($tracking_numbers)) {
            return new WP_Error('missing_data', 'Tracking numbers required', array('status' => 400));
        }

        $table = $wpdb->prefix . 'ybt_tracking';
        $added = 0;
        $target_user = $target_user_id > 0 ? get_userdata($target_user_id) : null;
        $timestamp = current_time('mysql');

        foreach ($tracking_numbers as $number) {
            $number = strtoupper(trim($number));
            if (empty($number)) continue;

            $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE UPPER(tracking_number) = %s", $number));
            if ($existing) {
                YBT_Tracking_Audit::apply_update($existing->id, array('status' => 'received', 'date_received' => $timestamp, 'user_id' => $target_user_id ?: $existing->user_id, 'username' => $target_user ? $target_user->user_login : $existing->username), 'bulk_add', array('source' => 'rest'));
            } else {
                $wpdb->insert($table, array('tracking_number' => $number, 'user_id' => $target_user_id ?: 0, 'username' => $target_user ? $target_user->user_login : 'Anonymous', 'description' => $description, 'item_count' => $item_count, 'status' => 'received', 'shipping_method' => $shipping_method, 'date_added' => $timestamp, 'date_received' => $timestamp));
                if ($wpdb->insert_id) {
                    YBT_Tracking_Audit::log_edit($wpdb->insert_id, 'create', array(), array(
                        'summary'         => 'Tracking number created',
                        'tracking_number' => $number,
                        'source'          => 'rest',
                        'log_activity'    => false,
                    ));
                }
            }
            $added++;
        }

        return rest_ensure_response(array('success' => true, 'data' => array('message' => "Successfully processed $added parcel(s)", 'added' => $added)));
    }

    public function update_tracking($request) {
        global $wpdb;
        $id = $request->get_param('id');
        $params = $request->get_json_params();
        $table = $wpdb->prefix . 'ybt_tracking';

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$item) return new WP_Error('not_found', 'Tracking not found', array('status' => 404));

        $update_data = array();
        if (isset($params['status'])) {
            // Whitelist valid status values
            $valid_statuses = array('not_received', 'received', 'packing_in_progress', 'packed', 'completed');
            if (!in_array($params['status'], $valid_statuses, true)) {
                return new WP_Error('invalid_status', 'Invalid status value', array('status' => 400));
            }
            $update_data['status'] = $params['status'];
            if ($params['status'] === 'received' && empty($item->date_received)) $update_data['date_received'] = current_time('mysql');
            elseif ($params['status'] === 'packed' && empty($item->date_packed)) $update_data['date_packed'] = current_time('mysql');
            elseif ($params['status'] === 'completed' && empty($item->date_completed)) $update_data['date_completed'] = current_time('mysql');
        }
        if (isset($params['item_count'])) $update_data['item_count'] = (int) $params['item_count'];
        if (isset($params['description'])) $update_data['description'] = sanitize_textarea_field($params['description']);
        if (isset($params['admin_notes'])) $update_data['admin_notes'] = sanitize_textarea_field($params['admin_notes']);

        if (empty($update_data)) return new WP_Error('missing_data', 'No data to update', array('status' => 400));

        YBT_Tracking_Audit::apply_update($id, $update_data, 'edit', array('before' => $item, 'source' => 'rest'));
        return rest_ensure_response(array('success' => true, 'data' => array('message' => 'Tracking updated')));
    }

    public function delete_tracking($request) {
        global $wpdb;
        $id = $request->get_param('id');
        $table = $wpdb->prefix . 'ybt_tracking';

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$item) return new WP_Error('not_found', 'Tracking not found', array('status' => 404));

        $wpdb->delete($table, array('id' => $id));

        YBT_Tracking_Audit::log_edit($id, 'delete', array(), array(
            'summary'         => 'Deleted tracking number',
            'tracking_number' => $item->tracking_number,
            'source'          => 'rest',
            'log_activity'    => false,
        ));

        return rest_ensure_response(array('success' => true, 'data' => array('message' => 'Tracking deleted')));
    }

    public function assign_parcel($request) {
        global $wpdb;
        $tracking_id = $request->get_param('id');
        $user_id = $request->get_param('user_id');

        $target_user = get_userdata($user_id);
        if (!$target_user) return new WP_Error('not_found', 'User not found', array('status' => 404));

        YBT_Tracking_Audit::apply_update(
            $tracking_id,
            array('user_id' => $user_id, 'username' => $target_user->user_login),
            'assign',
            array('source' => 'rest')
        );
        return rest_ensure_response(array('success' => true, 'data' => array('message' => 'Parcel assigned')));
    }

    public function upload_tracking_image($request) {
        // Multipart file upload - handled via WordPress media functions
        $tracking_id = $request->get_param('tracking_id');
        if (!$tracking_id) return new WP_Error('missing_data', 'Tracking ID required', array('status' => 400));

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('missing_file', 'No image uploaded', array('status' => 400));
        }

        if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload = wp_handle_upload($_FILES['image'], array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            )
        ));
        if (isset($upload['error'])) return new WP_Error('upload_failed', $upload['error'], array('status' => 500));

        // Compress image uploads to reduce disk space (best-effort, never blocks the upload)
        if (!empty($upload['file']) && class_exists('YBT_File_Handler')) {
            YBT_File_Handler::compress_image($upload['file']);
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare("SELECT images FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $tracking_id));
        $existing_images = $item && $item->images ? explode(',', $item->images) : array();
        $existing_images[] = $upload['url'];
        YBT_Tracking_Audit::apply_update($tracking_id, array('images' => implode(',', array_unique($existing_images))), 'image_upload', array('source' => 'rest'));

        return rest_ensure_response(array('success' => true, 'data' => array('url' => $upload['url'])));
    }

    public function delete_tracking_image($request) {
        $tracking_id = $request->get_param('id');
        $params = $request->get_json_params();
        $image_url = sanitize_text_field($params['image_url'] ?? '');

        if (!$tracking_id || empty($image_url)) {
            return new WP_Error('missing_data', 'Tracking ID and image URL required', array('status' => 400));
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare("SELECT images FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $tracking_id));

        if (!$item || empty($item->images)) {
            return new WP_Error('not_found', 'No images found', array('status' => 404));
        }

        $images = explode(',', (string) $item->images);
        $new_images = array_filter($images, function($url) use ($image_url) {
            return trim($url) !== trim($image_url);
        });

        $wpdb->update(
            $wpdb->prefix . 'ybt_tracking',
            array('images' => implode(',', $new_images)),
            array('id' => $tracking_id)
        );

        // Physically delete file if it's on our server — shared, containment-checked
        // helper (resolves dated subdirs correctly instead of basename-only matching).
        if (class_exists('YBT_File_Handler')) {
            YBT_File_Handler::delete_urls(array($image_url));
        }

        return rest_ensure_response(array('success' => true, 'data' => array('message' => 'Image deleted')));
    }

    public function upload_tracking_images_bulk($request) {
        $tracking_id = $request->get_param('id');
        if (!$tracking_id) return new WP_Error('missing_data', 'Tracking ID required', array('status' => 400));

        if (empty($_FILES['images']) || empty($_FILES['images']['name'][0])) {
            return new WP_Error('missing_file', 'No images uploaded', array('status' => 400));
        }

        if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');

        $uploaded_urls = array();
        $files = $_FILES['images'];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $file_array = array(
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            );

            if ($file_array['error'] !== UPLOAD_ERR_OK) continue;

            // Move tmp file to proper location
            $tmp = wp_tempnam($file_array['name']);
            move_uploaded_file($file_array['tmp_name'], $tmp);
            $file_array['tmp_name'] = $tmp;

            $_FILES['image'] = $file_array;
            $upload = wp_handle_upload($file_array, array(
                'test_form' => false,
                'mimes' => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                )
            ));

            if (!isset($upload['error']) && isset($upload['url'])) {
                // Compress to reduce disk space (best-effort)
                if (!empty($upload['file']) && class_exists('YBT_File_Handler')) {
                    YBT_File_Handler::compress_image($upload['file']);
                }
                $uploaded_urls[] = $upload['url'];
            }

            @unlink($tmp);
        }

        if (!empty($uploaded_urls)) {
            global $wpdb;
            $item = $wpdb->get_row($wpdb->prepare("SELECT images FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $tracking_id));
            $existing = $item && $item->images ? explode(',', $item->images) : array();
            $all_images = array_merge($existing, $uploaded_urls);
            YBT_Tracking_Audit::apply_update(
                $tracking_id,
                array('images' => implode(',', array_unique($all_images))),
                'image_upload',
                array('source' => 'rest')
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'uploaded' => count($uploaded_urls),
                'urls' => $uploaded_urls
            )
        ));
    }

    public function scan_barcode($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $barcode = strtoupper(trim($params['barcode'] ?? ''));
        if (empty($barcode)) return new WP_Error('missing_data', 'Barcode required', array('status' => 400));

        $table = $wpdb->prefix . 'ybt_tracking';
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE UPPER(tracking_number) = %s", $barcode), ARRAY_A);

        if (!$item) {
            $wpdb->insert($table, array('tracking_number' => $barcode, 'user_id' => 0, 'username' => 'Anonymous', 'status' => 'received', 'date_added' => current_time('mysql'), 'date_received' => current_time('mysql'), 'shipping_method' => 'sea', 'item_count' => 1));
            if ($wpdb->insert_id) {
                YBT_Tracking_Audit::log_edit($wpdb->insert_id, 'create', array(), array(
                    'summary'         => 'Tracking number created (barcode scan)',
                    'tracking_number' => $barcode,
                    'source'          => 'rest',
                    'log_activity'    => false,
                ));
            }
            return rest_ensure_response(array('success' => true, 'data' => array('tracking_number' => $barcode, 'status' => 'received', 'is_new' => true)));
        }

        if ($item['status'] === 'not_received') {
            YBT_Tracking_Audit::apply_update(
                $item['id'],
                array('status' => 'received', 'date_received' => current_time('mysql')),
                'status_change',
                array('summary' => 'Status: Not Received → Received (barcode scan)', 'source' => 'rest')
            );
            $item['status'] = 'received';
        }

        return rest_ensure_response(array('success' => true, 'data' => $item));
    }

    // =========================================================================
    // PACKING
    // =========================================================================

    public function get_packing_requests($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ybt_tracking';
        $items = $wpdb->get_results("SELECT batch_id, user_id, username, COUNT(*) as item_count, SUM(weight_kg) as total_weight, MAX(date_packing_requested) as date_requested, MAX(shipping_company_name) as shipping_company_name, MAX(shipping_address) as shipping_address, MAX(shipping_mark) as shipping_mark FROM $table WHERE batch_id IS NOT NULL AND batch_id != '' AND status = 'packing_in_progress' GROUP BY batch_id, user_id, username ORDER BY date_requested DESC LIMIT 50", ARRAY_A);
        return rest_ensure_response(array('success' => true, 'data' => array('requests' => $items)));
    }

    public function get_batch_cartons($request) {
        global $wpdb;
        $batch_id = $request->get_param('id');
        $table = $wpdb->prefix . 'ybt_tracking';
        $cartons = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_cartons WHERE batch_id = %s ORDER BY carton_number ASC",
            $batch_id
        ), ARRAY_A);

        // Also fetch all tracking items in this batch
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE batch_id = %s ORDER BY date_added DESC",
            $batch_id
        ), ARRAY_A);

        // Ensure integer types for Android compatibility
        foreach ($items as &$item) {
            $item['id'] = (int) $item['id'];
            $item['user_id'] = (int) $item['user_id'];
            $item['item_count'] = (int) $item['item_count'];
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'cartons' => $cartons ?: array(),
                'items' => $items ?: array()
            )
        ));
    }

    public function save_batch_cartons($request) {
        global $wpdb;
        $batch_id = $request->get_param('id');
        $params = $request->get_json_params();
        $cartons = $params['cartons'] ?? array();

        $wpdb->delete($wpdb->prefix . 'ybt_cartons', array('batch_id' => $batch_id));

        foreach ($cartons as $index => $carton) {
            $wpdb->insert($wpdb->prefix . 'ybt_cartons', array(
                'batch_id' => $batch_id,
                'carton_number' => $index + 1,
                'weight_kg' => (float) ($carton['weight'] ?? 0),
                'payment_amount' => (float) ($carton['amount'] ?? 0),
                'payment_currency' => sanitize_text_field($carton['currency'] ?? 'CNY'),
                'date_created' => current_time('mysql')
            ));
        }

        return rest_ensure_response(array('success' => true, 'data' => array('message' => count($cartons) . ' cartons saved')));
    }

    // =========================================================================
    // CHAT
    // =========================================================================

    public function get_chat_users($request) {
        global $wpdb;
        $users = $wpdb->get_results("SELECT DISTINCT u.ID as user_id, u.user_login as username, (SELECT COUNT(*) FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID AND is_read = 0 AND is_admin = 0) as unread_count, (SELECT MAX(date_created) FROM {$wpdb->prefix}ybt_chat WHERE user_id = u.ID) as last_message FROM {$wpdb->users} u INNER JOIN {$wpdb->prefix}ybt_chat c ON u.ID = c.user_id ORDER BY last_message DESC", ARRAY_A);
        return rest_ensure_response(array('success' => true, 'data' => array('users' => $users)));
    }

    public function get_chat_messages($request) {
        global $wpdb;
        $user_id = $request->get_param('user_id');
        $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_chat WHERE user_id = %d ORDER BY date_created ASC", $user_id), ARRAY_A);
        return rest_ensure_response(array('success' => true, 'data' => array('messages' => $messages)));
    }

    public function send_chat_message($request) {
        global $wpdb;
        $user_id = $request->get_param('user_id');
        $params = $request->get_json_params();
        $message = sanitize_textarea_field($params['message'] ?? '');

        if (empty($message)) return new WP_Error('missing_data', 'Message required', array('status' => 400));

        $user = get_userdata($user_id);
        if (!$user) return new WP_Error('not_found', 'User not found', array('status' => 404));

        $wpdb->insert($wpdb->prefix . 'ybt_chat', array('user_id' => $user_id, 'username' => $user->user_login, 'message' => $message, 'is_admin' => 1, 'is_read' => 0, 'date_created' => current_time('mysql')));
        return rest_ensure_response(array('success' => true, 'data' => array('message' => 'Message sent')));
    }

    // =========================================================================
    // WAREHOUSE
    // =========================================================================

    public function get_warehouse_settings($request) {
        global $wpdb;
        $warehouse = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ybt_warehouse_settings WHERE shipping_type = 'air' AND is_active = 1 ORDER BY date_created DESC LIMIT 1", ARRAY_A);
        return rest_ensure_response(array('success' => true, 'data' => $warehouse ?: array()));
    }

    public function update_warehouse_address($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $address = sanitize_textarea_field($params['address'] ?? '');
        if (empty($address)) return new WP_Error('missing_data', 'Address required', array('status' => 400));

        $table = $wpdb->prefix . 'ybt_warehouse_settings';
        $existing_id = $wpdb->get_var("SELECT id FROM $table ORDER BY id DESC LIMIT 1");
        $data = array('address' => $address, 'is_active' => 1, 'date_created' => current_time('mysql'));

        if ($existing_id) { $wpdb->update($table, $data, array('id' => $existing_id)); }
        else { $wpdb->insert($table, $data); }

        return rest_ensure_response(array('success' => true, 'data' => array('message' => 'Warehouse updated')));
    }

    // =========================================================================
    // DATA MANAGEMENT — disk-space cleanup
    // =========================================================================

    /**
     * GET /data/stats — return row counts + approximate sizes for all YBT tables
     */
    public function data_stats($request) {
        global $wpdb;

        $tables = array(
            'ybt_tracking'        => 'Parcels / Tracking',
            'ybt_chat'            => 'Chat Messages',
            'ybt_activity_log'    => 'Activity Logs',
            'ybt_cartons'         => 'Cartons',
            'ybt_fcm_tokens'      => 'FCM Tokens',
            'ybt_admin_messages'  => 'Admin Messages',
            'ybt_invoices'        => 'Invoices',
            'packing_requests'    => 'Packing Requests',
            'ybt_notifications'   => 'Notifications Inbox',
            'ybt_tracking_edits'  => 'Tracking Edit History',
        );

        $result = array();
        foreach ($tables as $tbl_suffix => $label) {
            $table = $wpdb->prefix . $tbl_suffix;
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT table_rows AS row_count, ROUND(data_length/1024, 1) AS data_kb, ROUND(index_length/1024, 1) AS index_kb " .
                    "FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                    $wpdb->dbname, $table
                )
            );
            $result[] = array(
                'table'    => $tbl_suffix,
                'label'    => $label,
                'rows'     => (int)($row->row_count ?? 0),
                'data_kb'  => (float)($row->data_kb ?? 0),
                'index_kb' => (float)($row->index_kb ?? 0),
            );
        }

        // Uploads directory size — cached in transient to avoid scanning gigabytes on every page load
        $force_refresh = !empty($request->get_param('refresh'));
        $cached_size   = get_transient('ybt_disk_uploads_size_kb');
        if ($cached_size !== false && !$force_refresh) {
            $uploads_kb = (float)$cached_size;
        } else {
            $uploads = wp_upload_dir();
            $upload_size = 0;
            if (is_dir($uploads['basedir'])) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads['basedir'], FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->isFile()) $upload_size += $file->getSize();
                }
            }
            $uploads_kb = round($upload_size / 1024, 1);
            set_transient('ybt_disk_uploads_size_kb', $uploads_kb, 6 * HOUR_IN_SECONDS);
        }

        // Chat attachments count
        $chat_attach = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_chat WHERE attachment IS NOT NULL AND attachment != ''"
        );

        // Tracking images count (stored as JSON in 'images' column)
        $tracking_with_images = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE images IS NOT NULL AND images != '' AND images != '[]'"
        );

        return rest_ensure_response(array(
            'success' => true,
            'data'    => array(
                'tables'             => $result,
                'uploads_kb'         => $uploads_kb,
                'chat_attachments'   => $chat_attach,
                'tracking_with_images' => $tracking_with_images,
            )
        ));
    }

    /**
     * POST /data/preview — preview rows that would be deleted
     * Body: { type: string, older_than_days?: int, status?: string, user_id?: int }
     */
    public function data_preview($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $type = sanitize_text_field($params['type'] ?? '');
        $days = absint($params['older_than_days'] ?? 0);
        $status = sanitize_text_field($params['status'] ?? '');
        $user_id = absint($params['user_id'] ?? 0);

        $where = '1=1';
        $p = array();
        if ($days > 0) {
            $where .= ' AND COALESCE(date_completed, date_packed, date_received, date_added) < DATE_SUB(NOW(), INTERVAL %d DAY)';
            $p[] = $days;
        }
        if ($status) {
            $where .= ' AND status = %s';
            $p[] = $status;
        }
        if ($user_id > 0) {
            $where .= ' AND user_id = %d';
            $p[] = $user_id;
        }

        switch ($type) {
            case 'tracking':
                $sql_count = "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE $where";
                $count = (int)(!empty($p) ? $wpdb->get_var($wpdb->prepare($sql_count, $p)) : $wpdb->get_var($sql_count));

                $sql_sample = "SELECT id, tracking_number, status, user_id, COALESCE(date_completed, date_packed, date_received, date_added) AS last_active FROM {$wpdb->prefix}ybt_tracking WHERE $where ORDER BY last_active DESC LIMIT 10";
                $sample = !empty($p) ? $wpdb->get_results($wpdb->prepare($sql_sample, $p), ARRAY_A) : $wpdb->get_results($sql_sample, ARRAY_A);

                $files = class_exists('YBT_File_Handler')
                    ? YBT_File_Handler::plan_data_cleanup('tracking', array('older_than_days' => $days, 'status' => $status, 'user_id' => $user_id))
                    : array('count' => 0, 'size_bytes' => 0);
                return rest_ensure_response(array('success' => true, 'data' => array(
                    'count' => $count, 'sample' => $sample,
                    'file_count' => $files['count'], 'size_kb' => round($files['size_bytes'] / 1024, 1)
                )));

            case 'chat':
                $where_chat = '1=1';
                $pc = array();
                if ($days > 0) {
                    $where_chat .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)';
                    $pc[] = $days;
                }
                if ($user_id > 0) {
                    $where_chat .= ' AND user_id = %d';
                    $pc[] = $user_id;
                }
                $sql_chat_count = "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_chat WHERE $where_chat";
                $count = (int)(!empty($pc) ? $wpdb->get_var($wpdb->prepare($sql_chat_count, $pc)) : $wpdb->get_var($sql_chat_count));

                $sql_chat_sample = "SELECT id, username, LEFT(message, 60) AS message, is_admin, date_created FROM {$wpdb->prefix}ybt_chat WHERE $where_chat ORDER BY date_created DESC LIMIT 10";
                $sample = !empty($pc) ? $wpdb->get_results($wpdb->prepare($sql_chat_sample, $pc), ARRAY_A) : $wpdb->get_results($sql_chat_sample, ARRAY_A);

                $files = class_exists('YBT_File_Handler')
                    ? YBT_File_Handler::plan_data_cleanup('chat', array('older_than_days' => $days, 'user_id' => $user_id))
                    : array('count' => 0, 'size_bytes' => 0);
                return rest_ensure_response(array('success' => true, 'data' => array(
                    'count' => $count, 'sample' => $sample,
                    'file_count' => $files['count'], 'size_kb' => round($files['size_bytes'] / 1024, 1)
                )));

            case 'activity_log':
                $where_act = '1=1';
                $pa = array();
                if ($days > 0) {
                    $where_act .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)';
                    $pa[] = $days;
                }
                $sql_act_count = "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_activity_log WHERE $where_act";
                $count = (int)(!empty($pa) ? $wpdb->get_var($wpdb->prepare($sql_act_count, $pa)) : $wpdb->get_var($sql_act_count));

                $sql_act_sample = "SELECT id, action, LEFT(details, 60) AS details, date_created FROM {$wpdb->prefix}ybt_activity_log WHERE $where_act ORDER BY date_created DESC LIMIT 10";
                $sample = !empty($pa) ? $wpdb->get_results($wpdb->prepare($sql_act_sample, $pa), ARRAY_A) : $wpdb->get_results($sql_act_sample, ARRAY_A);
                return rest_ensure_response(array('success' => true, 'data' => array('count' => $count, 'sample' => $sample)));

            case 'cartons':
                $where_cart = '1=1';
                $pk = array();
                if ($days > 0) {
                    $where_cart .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)';
                    $pk[] = $days;
                }
                $sql_cart_count = "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_cartons WHERE $where_cart";
                $count = (int)(!empty($pk) ? $wpdb->get_var($wpdb->prepare($sql_cart_count, $pk)) : $wpdb->get_var($sql_cart_count));

                $files = class_exists('YBT_File_Handler')
                    ? YBT_File_Handler::plan_data_cleanup('cartons', array('older_than_days' => $days))
                    : array('count' => 0, 'size_bytes' => 0);
                return rest_ensure_response(array('success' => true, 'data' => array(
                    'count' => $count, 'sample' => array(),
                    'file_count' => $files['count'], 'size_kb' => round($files['size_bytes'] / 1024, 1)
                )));

            case 'fcm_tokens':
                $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_fcm_tokens");
                return rest_ensure_response(array('success' => true, 'data' => array('count' => $count, 'sample' => array())));

            case 'notifications':
                $where_notif = '1=1';
                $pn = array();
                if ($days > 0) {
                    $where_notif .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)';
                    $pn[] = $days;
                }
                if ($user_id > 0) {
                    $where_notif .= ' AND user_id = %d';
                    $pn[] = $user_id;
                }
                $sql_notif_count = "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_notifications WHERE $where_notif";
                $count = (int)(!empty($pn) ? $wpdb->get_var($wpdb->prepare($sql_notif_count, $pn)) : $wpdb->get_var($sql_notif_count));

                $sql_notif_sample = "SELECT id, user_id, title, LEFT(body, 60) AS body, date_created FROM {$wpdb->prefix}ybt_notifications WHERE $where_notif ORDER BY date_created DESC LIMIT 10";
                $sample = !empty($pn) ? $wpdb->get_results($wpdb->prepare($sql_notif_sample, $pn), ARRAY_A) : $wpdb->get_results($sql_notif_sample, ARRAY_A);
                return rest_ensure_response(array('success' => true, 'data' => array('count' => $count, 'sample' => $sample)));

            case 'packing_requests':
                $where_pr = '1=1';
                $ppr = array();
                if ($days > 0) {
                    $where_pr .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)';
                    $ppr[] = $days;
                }
                $sql_pr_count = "SELECT COUNT(*) FROM {$wpdb->prefix}packing_requests WHERE $where_pr";
                $count = (int)(!empty($ppr) ? $wpdb->get_var($wpdb->prepare($sql_pr_count, $ppr)) : $wpdb->get_var($sql_pr_count));
                return rest_ensure_response(array('success' => true, 'data' => array('count' => $count, 'sample' => array())));

            case 'orphans':
                // Files in YBT upload locations that NO YBT record references.
                // Age filter is mandatory (same rule as delete): fresh orphans
                // may be mid-upload or referenced by a record type the scan
                // does not know about.
                if ($days <= 0) {
                    return new WP_Error('age_required',
                        'Pass older_than_days > 0 to preview orphaned files. Only files not referenced by any YBT record are eligible.',
                        array('status' => 400));
                }
                if (!class_exists('YBT_File_Handler')) {
                    return new WP_Error('helper_missing', 'File handler unavailable', array('status' => 500));
                }
                $orphan_plan = YBT_File_Handler::plan_orphan_cleanup(array('older_than_days' => $days));
                $orphan_sample = array();
                foreach (array_slice($orphan_plan['files'], 0, 10) as $orphan_file) {
                    $orphan_sample[] = array('file' => $orphan_file['ref'], 'owner' => 'orphan', 'size_bytes' => $orphan_file['size']);
                }
                return rest_ensure_response(array('success' => true, 'data' => array(
                    'count' => $orphan_plan['count'],
                    'size_kb' => round($orphan_plan['size_bytes'] / 1024, 1),
                    'scanned' => $orphan_plan['scanned'],
                    'excluded' => $orphan_plan['excluded'],
                    'scope' => 'Only files in YBT upload locations (uploads root + ybt-cartons) that no YBT record references. Media Library files and referenced files are never touched.',
                    'sample' => $orphan_sample
                )));

            case 'uploads':
                // YBT-referenced files ONLY — never the whole uploads directory.
                // plan_data_cleanup() is the same code path /data/delete runs;
                // these numbers cannot drift from what delete actually frees.
                if (!class_exists('YBT_File_Handler')) {
                    return new WP_Error('helper_missing', 'File handler unavailable', array('status' => 500));
                }
                if ($days <= 0) {
                    return new WP_Error('age_required',
                        'Refusing to preview uploads without an age filter. Pass older_than_days > 0; only files referenced by YBT records are eligible.',
                        array('status' => 400));
                }
                $plan = YBT_File_Handler::plan_data_cleanup('uploads', array('older_than_days' => $days));
                $sample = array();
                foreach (array_slice($plan['files'], 0, 10) as $file) {
                    $sample[] = array('file' => $file['ref'], 'owner' => $file['owner'], 'size_bytes' => $file['size']);
                }
                return rest_ensure_response(array('success' => true, 'data' => array(
                    'count' => $plan['count'],
                    'size_kb' => round($plan['size_bytes'] / 1024, 1),
                    'scope' => 'Only files referenced by YBT records (parcels, cartons, chat, messages). The rest of wp-content/uploads is never touched.',
                    'sample' => $sample
                )));

            default:
                return new WP_Error('invalid_type', 'Unknown data type: ' . $type, array('status' => 400));
        }
    }

    /**
     * POST /data/delete — execute deletion
     * Body: { type: string, older_than_days?: int, status?: string, user_id?: int, confirm: true }
     *
     * File removal and freed-space accounting are delegated entirely to
     * YBT_File_Handler::plan_data_cleanup() + execute_data_cleanup() — the
     * same code path /data/preview reports, so the numbers cannot drift.
     * This method only handles row deletion (SQL DELETE) for table-backed
     * types and the age-filter safety rule for uploads.
     */
    public function data_delete($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $type = sanitize_text_field($params['type'] ?? '');
        $days = absint($params['older_than_days'] ?? 0);
        $status = sanitize_text_field($params['status'] ?? '');
        $user_id = absint($params['user_id'] ?? 0);
        $confirm = !empty($params['confirm']);

        if (!$confirm) {
            return new WP_Error('not_confirmed', 'Set confirm=true to proceed with deletion', array('status' => 400));
        }

        if (!function_exists('ybt_log_activity')) {
            require_once __DIR__ . '/ajax-helpers.php';
        }

        $deleted  = 0;
        $freed_kb = 0.0;
        $filters  = array('older_than_days' => $days, 'status' => $status, 'user_id' => $user_id);

        // Runs one unified file-cleanup plan and folds the freed space into
        // the response accounting. Files are deleted and reference columns
        // cleared inside YBT_File_Handler; here we only DELETE table rows.
        $run_cleanup = function($cleanup_type, $f, $clear_fields) use (&$freed_kb) {
            if (!class_exists('YBT_File_Handler')) {
                return 0;
            }
            $plan = YBT_File_Handler::plan_data_cleanup($cleanup_type, $f);
            $res  = YBT_File_Handler::execute_data_cleanup($plan, $clear_fields);
            $freed_kb += $res['freed_bytes'] / 1024;
            return (int) $res['deleted_files'];
        };

        switch ($type) {
            case 'tracking':
                $where = '1=1';
                $p = array();
                if ($days > 0) { $where .= ' AND COALESCE(date_completed, date_packed, date_received, date_added) < DATE_SUB(NOW(), INTERVAL %d DAY)'; $p[] = $days; }
                if ($status) { $where .= ' AND status = %s'; $p[] = $status; }
                if ($user_id > 0) { $where .= ' AND user_id = %d'; $p[] = $user_id; }

                // Fetch matching rows so we can cascade delete edit histories and notifications
                $get_ids_sql = "SELECT id, tracking_number FROM {$wpdb->prefix}ybt_tracking WHERE $where";
                $matched_rows = !empty($p) ? (array)$wpdb->get_results($wpdb->prepare($get_ids_sql, $p), ARRAY_A) : (array)$wpdb->get_results($get_ids_sql, ARRAY_A);
                $matched_ids = array_filter(array_column($matched_rows, 'id'));
                $matched_tracking_numbers = array_filter(array_column($matched_rows, 'tracking_number'));

                // Rows are deleted wholesale below, so no field clearing.
                $run_cleanup('tracking', $filters, false);

                $del_sql = "DELETE FROM {$wpdb->prefix}ybt_tracking WHERE $where";
                $deleted = !empty($p) ? $wpdb->query($wpdb->prepare($del_sql, $p)) : $wpdb->query($del_sql);

                // Cascade delete orphaned tracking edits and linked notifications
                if (!empty($matched_ids)) {
                    foreach (array_chunk($matched_ids, 500) as $chunk) {
                        $ph = implode(',', array_fill(0, count($chunk), '%d'));
                        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ybt_tracking_edits WHERE tracking_id IN ($ph)", $chunk));
                        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ybt_notifications WHERE tracking_id IN ($ph)", $chunk));
                    }
                }
                if (!empty($matched_tracking_numbers)) {
                    foreach (array_chunk($matched_tracking_numbers, 500) as $chunk) {
                        $ph = implode(',', array_fill(0, count($chunk), '%s'));
                        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ybt_notifications WHERE tracking_number IN ($ph)", $chunk));
                    }
                }
                break;

            case 'chat':
                $where = '1=1';
                $p = array();
                if ($days > 0) { $where .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)'; $p[] = $days; }
                if ($user_id > 0) { $where .= ' AND user_id = %d'; $p[] = $user_id; }
                $run_cleanup('chat', $filters, false);
                $del_sql = "DELETE FROM {$wpdb->prefix}ybt_chat WHERE $where";
                $deleted = !empty($p) ? $wpdb->query($wpdb->prepare($del_sql, $p)) : $wpdb->query($del_sql);
                break;

            case 'activity_log':
                $where = '1=1';
                $p = array();
                if ($days > 0) { $where .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)'; $p[] = $days; }
                $del_sql = "DELETE FROM {$wpdb->prefix}ybt_activity_log WHERE $where";
                $deleted = !empty($p) ? $wpdb->query($wpdb->prepare($del_sql, $p)) : $wpdb->query($del_sql);
                break;

            case 'cartons':
                $where = '1=1';
                $p = array();
                if ($days > 0) { $where .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)'; $p[] = $days; }
                $run_cleanup('cartons', $filters, false);
                $del_sql = "DELETE FROM {$wpdb->prefix}ybt_cartons WHERE $where";
                $deleted = !empty($p) ? $wpdb->query($wpdb->prepare($del_sql, $p)) : $wpdb->query($del_sql);
                break;

            case 'fcm_tokens':
                $deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}ybt_fcm_tokens");
                break;

            case 'notifications':
                $where = '1=1';
                $p = array();
                if ($days > 0) { $where .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)'; $p[] = $days; }
                if ($user_id > 0) { $where .= ' AND user_id = %d'; $p[] = $user_id; }
                $del_sql = "DELETE FROM {$wpdb->prefix}ybt_notifications WHERE $where";
                $deleted = !empty($p) ? $wpdb->query($wpdb->prepare($del_sql, $p)) : $wpdb->query($del_sql);
                break;

            case 'packing_requests':
                $where = '1=1';
                $p = array();
                if ($days > 0) { $where .= ' AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)'; $p[] = $days; }
                $del_sql = "DELETE FROM {$wpdb->prefix}packing_requests WHERE $where";
                $deleted = !empty($p) ? $wpdb->query($wpdb->prepare($del_sql, $p)) : $wpdb->query($del_sql);
                break;

            case 'orphans':
                // SAFETY: an age filter is mandatory. Only files NOT referenced
                // by any YBT row (and not Media-Library attachments) are
                // eligible, and only inside the YBT scan directories.
                if ($days <= 0) {
                    return new WP_Error('age_required',
                        'Refusing to purge orphaned files without an age filter. Pass older_than_days > 0.',
                        array('status' => 400));
                }
                if (!class_exists('YBT_File_Handler')) {
                    return new WP_Error('helper_missing', 'File handler unavailable', array('status' => 500));
                }
                $orphan_plan = YBT_File_Handler::plan_orphan_cleanup(array('older_than_days' => $days));
                $orphan_res  = YBT_File_Handler::execute_orphan_cleanup($orphan_plan);
                $deleted  = (int) $orphan_res['deleted_files'];
                $freed_kb += $orphan_res['freed_bytes'] / 1024;
                delete_transient('ybt_disk_uploads_size_kb');
                break;

            case 'uploads':
                // SAFETY: an age filter is mandatory, and only YBT-owned files
                // referenced by DB records are eligible. This can no longer wipe
                // the whole uploads directory (previously days=0 did exactly that,
                // taking the Media Library and every other plugin's files with it).
                if ($days <= 0) {
                    return new WP_Error('age_required',
                        'Refusing to delete uploads without an age filter. Pass older_than_days > 0; only files referenced by YBT records are eligible.',
                        array('status' => 400));
                }
                // No table rows are deleted here, so reference columns ARE
                // cleared on rows whose files were removed.
                $deleted = $run_cleanup('uploads', $filters, true);
                delete_transient('ybt_disk_uploads_size_kb');
                break;

            case 'all_parcels':
                // Files first, then the rows (rows vanish below, so no clearing).
                $run_cleanup('all_parcels', $filters, false);
                $deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}ybt_tracking");
                $wpdb->query("DELETE FROM {$wpdb->prefix}ybt_cartons");
                $wpdb->query("DELETE FROM {$wpdb->prefix}ybt_tracking_edits");
                $wpdb->query("DELETE FROM {$wpdb->prefix}ybt_notifications WHERE tracking_id IS NOT NULL OR tracking_number IS NOT NULL");
                delete_transient('ybt_disk_uploads_size_kb');
                break;

            case 'all_chat':
                $run_cleanup('all_chat', $filters, false);
                $deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}ybt_chat");
                delete_transient('ybt_disk_uploads_size_kb');
                break;

            case 'all_activity':
                $deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}ybt_activity_log");
                break;

            default:
                return new WP_Error('invalid_type', 'Unknown data type: ' . $type, array('status' => 400));
        }

        if ($freed_kb > 0) {
            delete_transient('ybt_disk_uploads_size_kb');
        }

        if (function_exists('ybt_log_activity')) {
            ybt_log_activity(get_current_user_id(), 'admin_data_delete',
                "Deleted {$deleted} {$type} records" . ($freed_kb > 0 ? ", freed ~" . round($freed_kb) . "KB" : ''));
        }

        return rest_ensure_response(array(
            'success'  => true,
            'data'     => array(
                'type'     => $type,
                'deleted'  => (int)$deleted,
                'freed_kb' => round($freed_kb, 1),
            )
        ));
    }
    // =========================================================================
    // EXPORT
    // =========================================================================

    public function export_data($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $type = sanitize_text_field($params['type'] ?? 'tracking');
        $status = sanitize_text_field($params['status'] ?? 'all');

        $where = '1=1';
        $p = array();
        if ($status && $status !== 'all') { $where .= ' AND status = %s'; $p[] = $status; }

        if ($type === 'tracking') {
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE $where ORDER BY date_added DESC LIMIT 1000", $p), ARRAY_A);
            return rest_ensure_response(array('success' => true, 'data' => array('type' => 'tracking', 'count' => count($items), 'items' => $items)));
        } elseif ($type === 'users') {
            $users = get_users(array('number' => 200));
            $data = array_map(function($u) { return array('id' => $u->ID, 'username' => $u->user_login, 'custom_id' => get_user_meta($u->ID, 'ybt_custom_user_id', true)); }, $users);
            return rest_ensure_response(array('success' => true, 'data' => array('type' => 'users', 'count' => count($data), 'items' => $data)));
        }

        return new WP_Error('invalid_type', 'Invalid export type', array('status' => 400));
    }
    // =========================================================================
    // SETTINGS HANDLERS
    // =========================================================================

    public function get_general_settings($request) {
        $data = array(
            'site_name'          => get_option('blogname', ''),
            'site_description'   => get_option('blogdescription', ''),
            'currency'           => get_option('ybt_currency', 'USD'),
            'timezone'           => get_option('timezone_string', 'UTC'),
            'language'           => get_option('ybt_language', 'en'),
            'popup_enabled'      => (bool) get_option('ybt_popup_enabled', 0),
            'popup_message'      => get_option('ybt_popup_message', ''),
            'popup_image'        => get_option('ybt_popup_image', ''),
            'rate_per_kg'        => get_option('ybt_rate_per_kg', 0),
        );
        return rest_ensure_response(array('success' => true, 'data' => $data));
    }

    public function save_general_settings($request) {
        $params = $request->get_json_params();
        if (isset($params['site_name'])) update_option('blogname', sanitize_text_field($params['site_name']));
        if (isset($params['site_description'])) update_option('blogdescription', sanitize_text_field($params['site_description']));
        if (isset($params['currency'])) update_option('ybt_currency', sanitize_text_field($params['currency']));
        if (isset($params['timezone'])) update_option('timezone_string', sanitize_text_field($params['timezone']));
        if (isset($params['language'])) update_option('ybt_language', sanitize_text_field($params['language']));
        if (isset($params['popup_enabled'])) update_option('ybt_popup_enabled', intval($params['popup_enabled']));
        if (isset($params['popup_message'])) update_option('ybt_popup_message', wp_kses_post($params['popup_message']));
        if (isset($params['rate_per_kg'])) update_option('ybt_rate_per_kg', floatval($params['rate_per_kg']));
        return rest_ensure_response(array('success' => true, 'message' => 'General settings saved.'));
    }

    public function get_fcm_settings($request) {
        $data = array(
            'fcm_server_key'    => get_option('ybt_fcm_server_key', ''),
            'fcm_sender_id'     => get_option('ybt_fcm_sender_id', ''),
            'fcm_enabled'       => (bool) get_option('ybt_fcm_enabled', 0),
            'fcm_token_count'   => $this->_count_fcm_tokens(),
        );
        return rest_ensure_response(array('success' => true, 'data' => $data));
    }

    public function save_fcm_settings($request) {
        $params = $request->get_json_params();
        if (isset($params['fcm_server_key'])) update_option('ybt_fcm_server_key', sanitize_text_field($params['fcm_server_key']));
        if (isset($params['fcm_sender_id'])) update_option('ybt_fcm_sender_id', sanitize_text_field($params['fcm_sender_id']));
        if (isset($params['fcm_enabled'])) update_option('ybt_fcm_enabled', intval($params['fcm_enabled']));
        return rest_ensure_response(array('success' => true, 'message' => 'FCM settings saved.'));
    }

    public function get_payment_settings($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ybt_payment_settings';
        $rows = array();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC", ARRAY_A);
        }
        $data = array(
            'bank_name'         => get_option('ybt_bank_name', ''),
            'bank_account_name' => get_option('ybt_bank_account_name', ''),
            'bank_account_number' => get_option('ybt_bank_account_number', ''),
            'bank_routing'      => get_option('ybt_bank_routing', ''),
            'bank_swift'        => get_option('ybt_bank_swift', ''),
            'paypal_email'      => get_option('ybt_paypal_email', ''),
            'rate_per_kg'       => get_option('ybt_rate_per_kg', 0),
            'payment_methods'   => $rows,
        );
        return rest_ensure_response(array('success' => true, 'data' => $data));
    }

    public function save_payment_settings($request) {
        $params = $request->get_json_params();
        $fields = array('bank_name', 'bank_account_name', 'bank_account_number', 'bank_routing', 'bank_swift', 'paypal_email', 'rate_per_kg');
        foreach ($fields as $f) {
            if (isset($params[$f])) {
                $val = ($f === 'rate_per_kg') ? floatval($params[$f]) : sanitize_text_field($params[$f]);
                update_option('ybt_' . $f, $val);
            }
        }
        return rest_ensure_response(array('success' => true, 'message' => 'Payment settings saved.'));
    }

    public function get_email_settings($request) {
        $data = array(
            'from_name'                => get_option('ybt_email_from_name', get_option('blogname', '')),
            'from_email'               => get_option('ybt_email_from_email', get_option('admin_email', '')),
            'notifications_enabled'    => (bool) get_option('ybt_email_notifications_enabled', 0),
            'sms_api_url'             => get_option('ybt_sms_api_url', ''),
            'sms_api_key'             => get_option('ybt_sms_api_key', ''),
            'sms_sender_id'           => get_option('ybt_sms_sender_id', ''),
        );
        return rest_ensure_response(array('success' => true, 'data' => $data));
    }

    public function save_email_settings($request) {
        $params = $request->get_json_params();
        if (isset($params['from_name'])) update_option('ybt_email_from_name', sanitize_text_field($params['from_name']));
        if (isset($params['from_email'])) update_option('ybt_email_from_email', sanitize_email($params['from_email']));
        if (isset($params['notifications_enabled'])) update_option('ybt_email_notifications_enabled', intval($params['notifications_enabled']));
        if (isset($params['sms_api_url'])) update_option('ybt_sms_api_url', esc_url_raw($params['sms_api_url']));
        if (isset($params['sms_api_key'])) update_option('ybt_sms_api_key', sanitize_text_field($params['sms_api_key']));
        if (isset($params['sms_sender_id'])) update_option('ybt_sms_sender_id', sanitize_text_field($params['sms_sender_id']));
        return rest_ensure_response(array('success' => true, 'message' => 'Email settings saved.'));
    }

    public function get_security_settings($request) {
        $data = array(
            'api_key_display'       => get_option('ybt_api_key_display', 'Not set'),
            'cors_origins'          => get_option('ybt_allowed_cors_origins', ''),
            'mobile_api_url'        => get_option('ybt_mobile_api_url', ''),
            'enforce_https'         => (bool) get_option('ybt_enforce_https', 0),
            'blocked_ips'           => get_option('ybt_blocked_ips', array()),
            'rate_limit_max'        => get_option('ybt_rate_limit_max', 30),
            'rate_limit_window'     => get_option('ybt_rate_limit_window', 60),
            'failed_logins_24h'     => $this->_count_failed_logins_24h(),
            'total_users'           => count_users()['total_users'] ?? 0,
            'admin_users'           => count_users()['avail_roles']['administrator'] ?? 0,
        );
        return rest_ensure_response(array('success' => true, 'data' => $data));
    }

    public function save_security_settings($request) {
        $params = $request->get_json_params();
        if (isset($params['cors_origins'])) update_option('ybt_allowed_cors_origins', sanitize_textarea_field($params['cors_origins']));
        if (isset($params['mobile_api_url'])) update_option('ybt_mobile_api_url', esc_url_raw($params['mobile_api_url']));
        if (isset($params['enforce_https'])) update_option('ybt_enforce_https', intval($params['enforce_https']));
        if (isset($params['rate_limit_max'])) update_option('ybt_rate_limit_max', intval($params['rate_limit_max']));
        if (isset($params['rate_limit_window'])) update_option('ybt_rate_limit_window', intval($params['rate_limit_window']));
        if (isset($params['block_ip'])) {
            $ip = sanitize_text_field($params['block_ip']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $blocked = get_option('ybt_blocked_ips', array());
                if (!in_array($ip, $blocked)) { $blocked[] = $ip; update_option('ybt_blocked_ips', $blocked); }
            }
        }
        if (isset($params['unblock_ip'])) {
            $ip = sanitize_text_field($params['unblock_ip']);
            $blocked = get_option('ybt_blocked_ips', array());
            $key = array_search($ip, $blocked);
            if ($key !== false) { unset($blocked[$key]); update_option('ybt_blocked_ips', array_values($blocked)); }
        }
        return rest_ensure_response(array('success' => true, 'message' => 'Security settings saved.'));
    }

    public function get_system_tools($request) {
        global $wpdb;
        $tables = array();
        $ybt_tables = array('ybt_tracking', 'ybt_chat', 'ybt_cartons', 'ybt_admin_messages', 'ybt_fcm_tokens', 'ybt_activity_log', 'ybt_warehouse_settings', 'ybt_payment_settings', 'ybt_security_events', 'ybt_failed_logins');
        foreach ($ybt_tables as $t) {
            $full = $wpdb->prefix . $t;
            $exists = ($wpdb->get_var("SHOW TABLES LIKE '$full'") === $full);
            $rows = $exists ? intval($wpdb->get_var("SELECT COUNT(*) FROM $full")) : 0;
            $tables[] = array('name' => $t, 'exists' => $exists, 'rows' => $rows);
        }
        $data = array(
            'tables'              => $tables,
            'recent_imports'      => $wpdb->get_results($wpdb->prepare(
                "SELECT details, date_created FROM {$wpdb->prefix}ybt_activity_log WHERE action = %s ORDER BY date_created DESC LIMIT 10", 'bulk_import'
            ), ARRAY_A),
            'plugin_version'      => defined('YBT_VERSION') ? YBT_VERSION : 'unknown',
            'php_version'         => phpversion(),
            'mysql_version'       => $wpdb->get_var('SELECT VERSION()'),
        );
        return rest_ensure_response(array('success' => true, 'data' => $data));
    }

    public function run_system_tool($request) {
        $params = $request->get_json_params();
        $tool = sanitize_text_field($params['tool'] ?? '');
        global $wpdb;

        switch ($tool) {
            case 'recreate_tables':
                if (class_exists('YBT_Database')) { YBT_Database::create_tables(); }
                return rest_ensure_response(array('success' => true, 'message' => 'Tables recreated.'));
            case 'fix_null_shipping':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ybt_tracking SET shipping_method = %s WHERE shipping_method IS NULL OR shipping_method = ''", 'sea'
                ));
                return rest_ensure_response(array('success' => true, 'message' => 'Null shipping methods fixed.'));
            case 'optimize_images':
                $items = $wpdb->get_results("SELECT id, images FROM {$wpdb->prefix}ybt_tracking WHERE images IS NOT NULL AND images != ''");
                $cleaned = 0;
                foreach ($items as $item) {
                    $valid = array_filter(array_map('trim', explode(',', $item->images)), function($u) { return !empty($u) && filter_var($u, FILTER_VALIDATE_URL); });
                    $updated = implode(',', $valid);
                    if ($updated !== $item->images) {
                        if (class_exists('YBT_Tracking_Audit')) {
                            YBT_Tracking_Audit::apply_update($item->id, array('images' => $updated), 'image_delete', array('before' => $item, 'summary' => 'Removed invalid image URLs (system cleanup)', 'source' => 'rest', 'log_activity' => false));
                        } else {
                            $wpdb->update($wpdb->prefix . 'ybt_tracking', array('images' => $updated), array('id' => $item->id));
                        }
                        $cleaned++;
                    }
                }
                return rest_ensure_response(array('success' => true, 'message' => "Optimized $cleaned records."));
            case 'assign_missing_ids':
                $users = get_users(array('meta_key' => 'ybt_custom_user_id', 'meta_compare' => 'NOT EXISTS'));
                $assigned = 0;
                foreach ($users as $u) {
                    update_user_meta($u->ID, 'ybt_custom_user_id', 'YBT' . str_pad($u->ID, 5, '0', STR_PAD_LEFT));
                    $assigned++;
                }
                $pending = get_users(array('meta_key' => 'ybt_custom_user_id', 'meta_value' => 'Pending'));
                foreach ($pending as $u) {
                    update_user_meta($u->ID, 'ybt_custom_user_id', 'YBT' . str_pad($u->ID, 5, '0', STR_PAD_LEFT));
                    $assigned++;
                }
                return rest_ensure_response(array('success' => true, 'message' => "Assigned IDs to $assigned users."));
            case 'rotate_api_key':
                if (!function_exists('ybt_encryption')) return new WP_Error('no_encryption', 'Encryption engine not loaded', array('status' => 500));
                $enc = ybt_encryption();
                $new_key = $enc->generate_api_key();
                $hash = $enc->hash_api_key($new_key);
                update_option('ybt_api_key_hash', $hash);
                update_option('ybt_api_key_display', substr($new_key, 0, 8) . '****');
                // SECURITY: bind the key to the rotating admin; unbound keys are rejected.
                update_option('ybt_api_key_user_id', get_current_user_id());
                return rest_ensure_response(array('success' => true, 'data' => array('new_key' => $new_key, 'display' => substr($new_key, 0, 8) . '****')));
            default:
                return new WP_Error('unknown_tool', 'Unknown tool: ' . $tool, array('status' => 400));
        }
    }

    public function get_app_update_settings($request) {
        $data = array(
            'latest_version'    => get_option('ybt_app_latest_version', ''),
            'download_url'      => get_option('ybt_app_download_url', ''),
            'update_message'    => get_option('ybt_app_update_message', ''),
            'update_enabled'    => (bool) get_option('ybt_app_update_enabled', 0),
        );
        return rest_ensure_response(array('success' => true, 'data' => $data));
    }

    public function save_app_update_settings($request) {
        $params = $request->get_json_params();
        if (isset($params['latest_version'])) update_option('ybt_app_latest_version', sanitize_text_field($params['latest_version']));
        if (isset($params['download_url'])) update_option('ybt_app_download_url', esc_url_raw($params['download_url']));
        if (isset($params['update_message'])) update_option('ybt_app_update_message', sanitize_textarea_field($params['update_message']));
        if (isset($params['update_enabled'])) update_option('ybt_app_update_enabled', intval($params['update_enabled']));
        return rest_ensure_response(array('success' => true, 'message' => 'App update settings saved.'));
    }

    public function get_payment_evidence($request) {
        global $wpdb;
        $evidence = array();
        $table = $wpdb->prefix . 'ybt_tracking';
        $meta_table = $wpdb->usermeta;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            // FIXED: ybt_tracking has no custom_user_id column — the old SELECT
            // threw "Unknown column" and the whole payment-evidence view failed.
            // Resolve the customer marking code from usermeta instead.
            $evidence = $wpdb->get_results(
                "SELECT t.id, t.tracking_number, t.username, t.payment_amount, t.payment_currency, t.payment_evidence,
                        (SELECT um.meta_value FROM $meta_table um
                          WHERE um.user_id = t.user_id AND um.meta_key = 'ybt_custom_user_id' LIMIT 1) AS custom_user_id
                 FROM $table t
                 WHERE t.payment_evidence IS NOT NULL AND t.payment_evidence != ''
                 ORDER BY t.date_added DESC LIMIT 50",
                ARRAY_A
            );
        }
        return rest_ensure_response(array('success' => true, 'data' => array('items' => $evidence, 'count' => count($evidence))));
    }

    // =========================================================================
    // AI OPERATOR HANDLERS
    // =========================================================================

    public function ai_diagnostics($request) {
        if (!class_exists('YBT_AI_Operator')) {
            return new WP_Error('no_ai', 'AI Operator class not loaded', array('status' => 500));
        }
        $ai = YBT_AI_Operator::instance();
        $diag = $ai->run_full_diagnostics();
        return rest_ensure_response(array('success' => true, 'data' => $diag));
    }

    public function ai_repair($request) {
        if (!class_exists('YBT_AI_Operator')) {
            return new WP_Error('no_ai', 'AI Operator class not loaded', array('status' => 500));
        }
        $ai = YBT_AI_Operator::instance();
        $actions = $ai->auto_repair_system();
        return rest_ensure_response(array(
            'success' => true,
            'data' => array('message' => 'Auto-repair completed.', 'actions' => $actions)
        ));
    }

    public function ai_prune($request) {
        if (!class_exists('YBT_AI_Operator')) {
            return new WP_Error('no_ai', 'AI Operator class not loaded', array('status' => 500));
        }
        $params = $request->get_json_params();
        $months = isset($params['months']) ? max(1, intval($params['months'])) : 5;
        $dry_run = !empty($params['dry_run']);
        $ai = YBT_AI_Operator::instance();
        $result = $ai->prune_images_older_than_months($months, $dry_run);
        return rest_ensure_response(array('success' => true, 'data' => $result));
    }

    public function ai_report($request) {
        if (!class_exists('YBT_AI_Operator')) {
            return new WP_Error('no_ai', 'AI Operator class not loaded', array('status' => 500));
        }
        $ai = YBT_AI_Operator::instance();
        $diag = $ai->run_full_diagnostics();
        $report = "=== YBT PACKING SERVICE OPERATIONAL REPORT (" . date('Y-m-d H:i') . ") ===\n";
        $report .= "System Health Score: " . $diag['health_score'] . "%\n";
        $report .= "Database Storage: " . $diag['db_size_mb'] . " MB\n";
        $report .= "Unclaimed Parcels: " . $diag['unclaimed_parcels'] . "\n";
        $report .= "Stale Warehouse Parcels (>14 days): " . $diag['stale_parcels'] . "\n";
        $report .= "Missing Photos: " . $diag['missing_photos'] . "\n";
        $report .= "Images Older Than 5 Months: " . $diag['old_images_5m_count'] . " images in " . $diag['old_records_5m_count'] . " records\n";
        $report .= "SSL Enabled: " . ($diag['is_ssl'] ? 'Yes' : 'No') . "\n";
        $report .= "JWT Configured: " . ($diag['jwt_configured'] ? 'Yes' : 'No') . "\n";
        return rest_ensure_response(array('success' => true, 'data' => array('report' => $report)));
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function _count_fcm_tokens() {
        global $wpdb;
        $table = $wpdb->prefix . 'ybt_fcm_tokens';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return intval($wpdb->get_var("SELECT COUNT(*) FROM $table"));
        }
        return 0;
    }

    private function _count_failed_logins_24h() {
        global $wpdb;
        $table = $wpdb->prefix . 'ybt_failed_logins';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE attempted_at > %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            )));
        }
        return 0;
    }
}

// Initialize - only if not already initialized
if (!has_action('rest_api_init', array(YBT_Admin_REST_API::instance(), 'register_routes'))) {
    YBT_Admin_REST_API::instance();
}
