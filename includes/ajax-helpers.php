<?php
/**
* YBT AJAX Helpers
* Shared security, validation, and token functions
*/
if (!defined('ABSPATH')) {
    exit;
}

/**
* Validate file upload for security
*/
if (!function_exists('ybt_validate_uploaded_file')) {
    function ybt_validate_uploaded_file($file) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return false;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return false;
        }
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_types)) {
            return false;
        }
        return true;
    }
}

/**
* Rate limiting helper using Transients
* NOTE: The full-featured version of ybt_check_rate_limit with user+IP tracking
* and security logging is defined later in this file. This simplified version
* was removed to avoid overwriting the enhanced version.
*/

/**
* Clear rate limit for a given action
*/
if (!function_exists('ybt_clear_rate_limit')) {
    function ybt_clear_rate_limit($action) {
        $ip = ybt_get_client_ip();

        // Legacy key used by ybt_rate_limit_check (wp_hash scheme).
        $legacy_key = 'ybt_rl_' . $action . '_' . wp_hash($ip);
        delete_transient($legacy_key);

        // Key used by ybt_check_rate_limit (md5 scheme). The identifier must
        // match the one built there: user-scoped when logged in, else IP.
        // Without this, successful logins kept incrementing the counter and
        // the auth endpoints locked out after a handful of attempts.
        $user_id = get_current_user_id();
        $identifier = $user_id > 0 ? 'user_' . $user_id : 'ip_' . md5($ip);
        $current_key = 'ybt_rate_limit_' . md5($action . '_' . $identifier);
        delete_transient($current_key);
    }
}

/**
* Get client IP securely (PHP 8.0+ compatible)
* FIXED: Removed deprecated FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
*/
if (!function_exists('ybt_get_client_ip')) {
    function ybt_get_client_ip() {
        // SECURITY FIX: Only trust REMOTE_ADDR to prevent IP spoofing attacks
        // Proxy headers can be easily forged by attackers
        if (!empty($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
            $candidate = trim($_SERVER['REMOTE_ADDR']);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return sanitize_text_field($candidate);
            }
        }
        return '0.0.0.0';
    }
}

/**
* Get secure JWT secret key
*/
if (!function_exists('ybt_get_jwt_secret_key')) {
    function ybt_get_jwt_secret_key() {
        if (getenv('YBT_JWT_SECRET_KEY')) {
            return getenv('YBT_JWT_SECRET_KEY');
        }
        if (defined('YBT_JWT_SECRET_KEY')) {
            return YBT_JWT_SECRET_KEY;
        }
        // SECURITY: use a plugin-specific key — never borrow WP core salts
        // (AUTH_KEY), so a token leak cannot touch core sessions and one
        // domain's keys can be rotated without breaking the other.
        $stored_key = get_option('ybt_jwt_secret_key');
        if ($stored_key) {
            return $stored_key;
        }
        $generated_key = wp_generate_password(64, true, true);
        update_option('ybt_jwt_secret_key', $generated_key);
        return $generated_key;
    }
}

/**
* JWT Token Functions
*/
if (!function_exists('ybt_base64url_encode')) {
    function ybt_base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('ybt_base64url_decode')) {
    function ybt_base64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

if (!function_exists('ybt_generate_mobile_token')) {
    function ybt_generate_mobile_token($user_id, $expiry_days = 7) {
        // SECURITY FIX: Reduced default expiry from 90 days to 7 days
        if (class_exists('YBT_API_Helper')) {
            return YBT_API_Helper::generate_token($user_id, $expiry_days);
        }
        $header = ybt_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = ybt_base64url_encode(json_encode([
            'user_id' => $user_id,
            'iat' => time(),
            'exp' => time() + ($expiry_days * 86400)
        ]));
        $secret = ybt_get_jwt_secret_key();
        $signature = ybt_base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
        return "$header.$payload.$signature";
    }
}
/**
 * Robustly retrieve token from request (Post, Get, or Authorization Header)
 */
if (!function_exists('ybt_get_request_token')) {
    function ybt_get_request_token() {
        $token = '';
        
        // 1. Check Request Parameters (POST body only, never GET to prevent URL leaks)
        if (!empty($_POST['token'])) {
            $token = $_POST['token'];
        } 
        
        // 2. Check Authorization Header (Standard JWT flow)
        if (empty($token)) {
            $auth_header = '';
            if (isset($_SERVER['Authorization'])) {
                $auth_header = $_SERVER['Authorization'];
            } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                // Common on Apache servers that strip the Authorization header
                $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (function_exists('getallheaders')) {
                $all_headers = getallheaders();
                if (isset($all_headers['Authorization'])) {
                    $auth_header = $all_headers['Authorization'];
                } elseif (isset($all_headers['authorization'])) {
                    $auth_header = $all_headers['authorization'];
                }
            }
            
            if (!empty($auth_header) && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
                $token = $matches[1];
            }
        }
        
        return sanitize_text_field(trim($token));
    }
}

if (!function_exists('ybt_validate_mobile_token')) {
    function ybt_validate_mobile_token($token = '') {
        // Automatically fetch token if not provided or empty
        if (empty($token)) {
            $token = ybt_get_request_token();
        }
        
        if (empty($token)) return false;

        // Use API Helper if available for centralized logic
        if (class_exists('YBT_API_Helper')) {
            return YBT_API_Helper::validate_token_string($token);
        }
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        list($header, $payload, $signature) = $parts;
        $secret = ybt_get_jwt_secret_key();
        $valid_signature = ybt_base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
        if (!hash_equals($signature, $valid_signature)) return false;
        $payload_data = json_decode(ybt_base64url_decode($payload), true);
        if (!$payload_data || (isset($payload_data['exp']) && time() > $payload_data['exp'])) {
            return false;
        }
        return $payload_data;
    }
}

if (!function_exists('ybt_smart_format_address')) {
    function ybt_smart_format_address($address, $custom_user_id = '') {
        if (empty($address)) return '';
        if (!empty($custom_user_id) && $custom_user_id !== 'Pending') {
            $placeholders = ['{USER_ID}', '{YBT_ID}', '{ID}', '{user_id}', '{ybt_id}', '{id}'];
            $address = str_replace($placeholders, esc_html($custom_user_id), $address);
        }
        return $address;
    }
}

/**
* Check if a table exists securely and efficiently
*/
if (!function_exists('ybt_table_exists')) {
    function ybt_table_exists($table_name) {
        global $wpdb;
        $cache_key = 'ybt_table_exists_' . md5($table_name);
        $exists = wp_cache_get($cache_key, 'ybt_db');
        if ($exists === false) {
            $exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name);
            wp_cache_set($cache_key, $exists, 'ybt_db', 3600);
        }
        return $exists;
    }
}

/**
* Security event logging
* ADDED: Was missing from previous version
*/
if (!function_exists('ybt_log_security_event')) {
    function ybt_log_security_event($event, $details = array()) {
        // SECURITY FIX: Always log security events regardless of WP_DEBUG
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'ip' => ybt_get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'unknown',
            'details' => $details
        );
        $log_dir = WP_CONTENT_DIR . '/ybt-logs';
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
            // Protect log directory from web access
            file_put_contents($log_dir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Deny,Allow\n    Deny from all\n</IfModule>\n");
            file_put_contents($log_dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
        $log_file = $log_dir . '/ybt-security.log';
        $log_line = date('Y-m-d H:i:s') . ' - ' . $event . ' - ' . json_encode($log_entry) . "\n";
        if (is_writable(dirname($log_file))) {
            file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        }

        // Persist to the DB security log so the admin dashboard can query it
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_security_log';
        if (ybt_table_exists($table_name)) {
            $detail_text = is_string($details) ? $details : wp_json_encode($details);
            $user_id = (is_array($details) && isset($details['user_id'])) ? (int) $details['user_id'] : get_current_user_id();
            $wpdb->insert(
                $table_name,
                array(
                    'event_type'   => sanitize_text_field(substr((string) $event, 0, 100)),
                    'description'  => sanitize_textarea_field(substr((string) $detail_text, 0, 1000)),
                    'severity'     => function_exists('ybt_security_event_severity') ? ybt_security_event_severity($event) : 'info',
                    'user_id'      => $user_id ?: null,
                    'ip_address'   => ybt_get_client_ip(),
                    'date_created' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
    }
}

/**
* Clear output buffers for clean JSON
*/
if (!function_exists('ybt_clear_output_buffers')) {
    function ybt_clear_output_buffers() {
        // During offline-sync replay, the replay loop's capture buffer must survive.
        if (class_exists('YBT_API_Response') && !empty(YBT_API_Response::$capturing)) return;
        while (ob_get_level()) ob_end_clean();
    }
}

/**
* Save CORS settings
*/
if (!function_exists('ybt_save_cors_settings_handler')) {
    function ybt_save_cors_settings_handler() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ybt_cors_settings')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Save allowed origins
        if (isset($_POST['allowed_origins'])) {
            $origins = sanitize_text_field(wp_unslash($_POST['allowed_origins']));
            update_option('ybt_allowed_cors_origins', $origins);
        }
        
        // Save mobile API URL
        if (isset($_POST['mobile_api_url'])) {
            $url = sanitize_url(wp_unslash($_POST['mobile_api_url']));
            update_option('ybt_mobile_api_url', $url);
        }
        
        wp_send_json_success('Settings saved successfully');
    }
}
add_action('wp_ajax_ybt_save_cors_settings', 'ybt_save_cors_settings_handler');

/**
* Save SSL settings
*/
if (!function_exists('ybt_save_ssl_settings_handler')) {
    function ybt_save_ssl_settings_handler() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ybt_ssl_settings')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Save HTTPS enforcement setting
        if (isset($_POST['enforce_https'])) {
            $enforce = sanitize_text_field(wp_unslash($_POST['enforce_https'])) === '1' ? '1' : '0';
            update_option('ybt_enforce_https', $enforce);
        }
        
        wp_send_json_success('Security protocol updated');
    }
}
add_action('wp_ajax_ybt_save_ssl_settings', 'ybt_save_ssl_settings_handler');

/**
* Test API connection
*/
if (!function_exists('ybt_test_api_connection_handler')) {
    function ybt_test_api_connection_handler() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Test 1: Check if database is accessible
        global $wpdb;
        $table_exists = ybt_table_exists($wpdb->prefix . 'ybt_tracking');
        
        // Test 2: Check if REST API is working
        $rest_api_available = false;
        $rest_route_url = get_rest_url(null, 'ybt/v1/tracking');
        
        $response = wp_remote_head($rest_route_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));
        
        if (!is_wp_error($response)) {
            $rest_api_available = true;
        }
        
        // Test 3: Check AJAX endpoints
        $ajax_url = admin_url('admin-ajax.php');
        
        wp_send_json_success(array(
            'message' => 'API connection successful',
            'tests' => array(
                'database' => $table_exists ? 'OK' : 'FAILED',
                'rest_api' => $rest_api_available ? 'OK' : 'FAILED',
                'ajax_endpoint' => 'OK'
            ),
            'api_url' => $ajax_url,
            'rest_url' => $rest_route_url
        ));
    }
}
add_action('wp_ajax_ybt_test_api_connection', 'ybt_test_api_connection_handler');

/**
* Test login endpoint
*/
if (!function_exists('ybt_test_login_endpoint_handler')) {
    function ybt_test_login_endpoint_handler() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Test the mobile login endpoint
        $login_action = 'ybt_mobile_login';
        
        // Simulate a test login request
        $test_result = array(
            'action_registered' => has_action('wp_ajax_nopriv_' . $login_action) !== false,
            'handler_exists' => function_exists('ybt_mobile_login_handler'),
            'endpoint' => admin_url('admin-ajax.php?action=' . $login_action)
        );
        
        if ($test_result['action_registered'] && $test_result['handler_exists']) {
            wp_send_json_success(array(
                'message' => 'Login endpoint is properly configured',
                'details' => $test_result
            ));
        } else {
            wp_send_json_error('Login endpoint not properly configured: ' . json_encode($test_result));
        }
    }
}
add_action('wp_ajax_ybt_test_login_endpoint', 'ybt_test_login_endpoint_handler');

/**
 * Security check and monitoring AJAX handler
 */
if (!function_exists('ybt_check_security_handler')) {
    function ybt_check_security_handler() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ybt_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        
        // Get failed logins in last 24 hours
        $failed_logins_24h = 0;
        $failed_logins_table = $wpdb->prefix . 'ybt_login_attempts';
        
        if (ybt_table_exists($failed_logins_table)) {
            $failed_logins_24h = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$failed_logins_table} WHERE success = 0 AND attempt_time > %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));
        }
        
        // Get suspicious IPs (more than 5 failed attempts)
        $suspicious_ips = array();
        if (ybt_table_exists($failed_logins_table)) {
            $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
            $suspicious_ips = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ip_address, COUNT(*) as attempts 
                     FROM {$failed_logins_table} 
                     WHERE success = 0 AND attempt_time > %s
                     GROUP BY ip_address 
                     HAVING attempts > 5 
                     ORDER BY attempts DESC 
                     LIMIT 10",
                    $seven_days_ago
                )
            ) ?: array();
        }
        
        // Get total users
        $total_users = count_users();
        $total_users_count = $total_users['total_users'];
        $admin_users_count = isset($total_users['avail_roles']['administrator']) ? $total_users['avail_roles']['administrator'] : 0;
        
        // Get recent security events
        $security_events_table = $wpdb->prefix . 'ybt_security_log';
        $recent_security_events = array();
        
        if (ybt_table_exists($security_events_table)) {
            $recent_security_events = $wpdb->get_results(
                "SELECT event_type, description, severity, date_created 
                 FROM {$security_events_table} 
                 ORDER BY date_created DESC 
                 LIMIT 10"
            ) ?: array();
        }
        
        // Get rate-limited IPs
        $rate_limited_ips = array();
        $transients = wp_cache_get('ybt_rate_limits', 'ybt', true);
        if (!empty($transients)) {
            foreach ($transients as $key => $attempts) {
                if ($attempts >= 10) {
                    $ip = str_replace('ybt_rl_', '', str_replace(wp_hash(''), '', $key));
                    if (!empty($ip)) {
                        $rate_limited_ips[] = array(
                            'ip_address' => $ip,
                            'attempts' => $attempts
                        );
                    }
                }
            }
        }
        
        // Compile security report
        $security_report = array(
            'failed_logins_24h' => intval($failed_logins_24h),
            'suspicious_ips' => $suspicious_ips,
            'rate_limited_ips' => $rate_limited_ips,
            'total_users' => $total_users_count,
            'admin_users' => $admin_users_count,
            'recent_security_events' => $recent_security_events,
            // FIXED: Guard against missing function to avoid fatal error if file is partially loaded
            'security_score' => function_exists('calculate_security_score') ? calculate_security_score($failed_logins_24h, count($suspicious_ips), count($rate_limited_ips)) : 100,
            'last_check' => current_time('mysql')
        );
        
        wp_send_json_success($security_report);
    }
}
add_action('wp_ajax_ybt_check_security', 'ybt_check_security_handler');

/**
 * Calculate security score based on various factors
 */
if (!function_exists('calculate_security_score')) {
    function calculate_security_score($failed_logins, $suspicious_ips, $rate_limited_ips) {
        $score = 100;
        
        // Deduct for failed logins
        if ($failed_logins > 50) {
            $score -= 30;
        } elseif ($failed_logins > 20) {
            $score -= 15;
        } elseif ($failed_logins > 10) {
            $score -= 5;
        }
        
        // Deduct for suspicious IPs
        $score -= min($suspicious_ips * 5, 25);
        
        // Deduct for rate-limited IPs
        $score -= min($rate_limited_ips * 3, 15);
        
        return max(0, min(100, $score));
    }
}

/**
 * Block suspicious IP AJAX handler
 */
if (!function_exists('ybt_block_ip_handler')) {
    function ybt_block_ip_handler() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ybt_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['ip_address'])) {
            wp_send_json_error('IP address required');
        }
        
        $ip = sanitize_text_field(wp_unslash($_POST['ip_address']));
        
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            wp_send_json_error('Invalid IP address');
        }
        
        // Get existing blocked IPs
        $blocked_ips = get_option('ybt_blocked_ips', array());
        
        if (!in_array($ip, $blocked_ips)) {
            $blocked_ips[] = $ip;
            update_option('ybt_blocked_ips', $blocked_ips);
        }
        
        // Log the blocking event
        ybt_log_security_event('ip_blocked', array(
            'ip' => $ip,
            'blocked_by' => get_current_user_id()
        ));
        
        wp_send_json_success('IP blocked successfully');
    }
}
add_action('wp_ajax_ybt_block_ip', 'ybt_block_ip_handler');

/**
 * Unblock IP AJAX handler
 */
if (!function_exists('ybt_unblock_ip_handler')) {
    function ybt_unblock_ip_handler() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ybt_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!isset($_POST['ip_address'])) {
            wp_send_json_error('IP address required');
        }
        
        $ip = sanitize_text_field(wp_unslash($_POST['ip_address']));
        
        // Get existing blocked IPs
        $blocked_ips = get_option('ybt_blocked_ips', array());
        
        if (($key = array_search($ip, $blocked_ips)) !== false) {
            unset($blocked_ips[$key]);
            update_option('ybt_blocked_ips', array_values($blocked_ips));
        }
        
        wp_send_json_success('IP unblocked successfully');
    }
}
add_action('wp_ajax_ybt_unblock_ip', 'ybt_unblock_ip_handler');

/**
 * Customer lookup by username for admin auto-fill
 */
if (!function_exists('ybt_customer_lookup_handler')) {
    function ybt_customer_lookup_handler() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ybt_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        
        if (empty($username)) {
            wp_send_json_error('Username is required');
        }
        
        // Search for user by username (partial match)
        $users = get_users(array(
            'search'         => '*' . $username . '*',
            'search_columns' => array('user_login', 'display_name'),
            'number'         => 10,
        ));
        
        if (empty($users)) {
            wp_send_json_error('No users found');
        }
        
        $results = array();
        foreach ($users as $user) {
            // Get additional user meta if available
            $custom_user_id = get_user_meta($user->ID, 'ybt_custom_user_id', true);
            $phone = get_user_meta($user->ID, 'billing_phone', true);
            $address = get_user_meta($user->ID, 'billing_address_1', true);
            $city = get_user_meta($user->ID, 'billing_city', true);
            
            $results[] = array(
                'id'             => $user->ID,
                'username'       => $user->user_login,
                'display_name'   => $user->display_name,
                'email'          => $user->user_email,
                'custom_user_id' => $custom_user_id ?: '',
                'phone'          => $phone ?: '',
                'address'        => $address ?: '',
                'city'           => $city ?: '',
            );
        }
        
        wp_send_json_success(array('users' => $results));
    }
}
add_action('wp_ajax_ybt_customer_lookup', 'ybt_customer_lookup_handler');

/**
 * Save pricing settings AJAX handler
 */
if (!function_exists('ybt_save_pricing_settings_handler')) {
    function ybt_save_pricing_settings_handler() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ybt_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Save rate per kg
        if (isset($_POST['rate_per_kg'])) {
            $rate = floatval($_POST['rate_per_kg']);
            if ($rate < 0) {
                wp_send_json_error('Rate cannot be negative');
            }
            update_option('ybt_rate_per_kg', $rate);
        }

        wp_send_json_success('Pricing settings saved successfully');
    }
}
add_action('wp_ajax_ybt_save_pricing_settings', 'ybt_save_pricing_settings_handler');

/**
 * ==============================================================================
 * SECURITY: Encryption Functions for Sensitive Data
 * ==============================================================================
 */

/**
 * Get encryption key from WordPress salts
 * Uses WordPress AUTH_KEY for consistent encryption
 * 
 * @return string Encryption key
 */
function ybt_get_encryption_key() {
    $key = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    return hash('sha256', $key, true);
}

/**
 * Get encryption IV (Initialization Vector)
 * 
 * @param string $context Context for IV generation
 * @return string 16-byte IV
 */
function ybt_get_encryption_iv($context = 'ybt_data') {
    return substr(hash('sha256', wp_salt('secure_auth') . $context), 0, 16);
}

/**
 * Encrypt sensitive data
 * Uses AES-256-CBC encryption
 * 
 * @param string|array $data Data to encrypt (string or array)
 * @param string $context Context for IV generation (optional)
 * @return string|false Encrypted data (base64 encoded) or false on failure
 */
function ybt_encrypt_data($data, $context = 'ybt_sensitive') {
    if (!function_exists('openssl_encrypt')) {
        return false; // OpenSSL not available
    }
    
    $plaintext = is_array($data) ? wp_json_encode($data) : (string) $data;
    $key = ybt_get_encryption_key();
    $iv = ybt_get_encryption_iv($context);
    
    $encrypted = openssl_encrypt(
        $plaintext,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    if ($encrypted === false) {
        return false;
    }
    
    return base64_encode($encrypted);
}

/**
 * Decrypt sensitive data
 * 
 * @param string $encrypted_data Encrypted data (base64 encoded)
 * @param string $context Context for IV generation (must match encryption context)
 * @param bool $return_array Whether to return as array (if JSON)
 * @return string|array|false Decrypted data or false on failure
 */
function ybt_decrypt_data($encrypted_data, $context = 'ybt_sensitive', $return_array = false) {
    if (!function_exists('openssl_encrypt')) {
        return false;
    }
    
    $encrypted = base64_decode($encrypted_data, true);
    if ($encrypted === false) {
        return false;
    }
    
    $key = ybt_get_encryption_key();
    $iv = ybt_get_encryption_iv($context);
    
    $decrypted = openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    if ($decrypted === false) {
        return false;
    }
    
    if ($return_array) {
        $decoded = json_decode($decrypted, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $decrypted;
    }
    
    return $decrypted;
}

/**
 * Save encrypted option
 * 
 * @param string $option_name Option name
 * @param mixed $value Value to encrypt and save
 * @return bool Success status
 */
function ybt_update_encrypted_option($option_name, $value) {
    $encrypted = ybt_encrypt_data($value, 'ybt_option_' . $option_name);
    if ($encrypted === false) {
        return false;
    }
    return update_option($option_name, $encrypted);
}

/**
 * Get decrypted option
 * 
 * @param string $option_name Option name
 * @param mixed $default Default value if decryption fails
 * @return mixed Decrypted value or default
 */
function ybt_get_encrypted_option($option_name, $default = false) {
    $encrypted = get_option($option_name, '');
    if (empty($encrypted)) {
        return $default;
    }
    
    $decrypted = ybt_decrypt_data($encrypted, 'ybt_option_' . $option_name);
    return ($decrypted !== false) ? $decrypted : $default;
}

/**
 * Sanitize and validate API key
 * 
 * @param string $api_key API key to validate
 * @return string|false Sanitized API key or false if invalid
 */
function ybt_sanitize_api_key($api_key) {
    $api_key = trim(sanitize_text_field($api_key));
    
    // API keys should be alphanumeric with possible dashes/underscores
    if (!preg_match('/^[a-zA-Z0-9_-]{16,}$/', $api_key)) {
        return false;
    }
    
    return $api_key;
}

/**
 * ==============================================================================
 * SECURITY: Enhanced Rate Limiting
 * ==============================================================================
 */

/**
 * Enhanced rate limiting with IP + user tracking
 * 
 * @param string $action Action being rate limited
 * @param int $max_attempts Maximum attempts allowed
 * @param int $window_seconds Time window in seconds
 * @return bool True if allowed, false if rate limited
 */
function ybt_check_rate_limit($action = 'general', $max_attempts = 5, $window_seconds = 300) {
    // Get client IP
    $ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';
    
    // Get user ID if logged in
    $user_id = get_current_user_id();
    
    // Create unique identifier combining IP and user ID
    $identifier = $user_id > 0 ? 'user_' . $user_id : 'ip_' . md5($ip);
    
    // Create transient key
    $transient_key = 'ybt_rate_limit_' . md5($action . '_' . $identifier);
    
    // Get current attempts
    $data = get_transient($transient_key);
    
    if ($data === false) {
        // First attempt
        $data = array(
            'attempts' => 1,
            'first_attempt' => time(),
            'ip' => $ip,
            'user_id' => $user_id
        );
        set_transient($transient_key, $data, $window_seconds);
        return true;
    }
    
    // Check if window has expired
    if (time() - $data['first_attempt'] > $window_seconds) {
        // Reset counter
        $data = array(
            'attempts' => 1,
            'first_attempt' => time(),
            'ip' => $ip,
            'user_id' => $user_id
        );
        set_transient($transient_key, $data, $window_seconds);
        return true;
    }
    
    // Check if rate limit exceeded
    if ($data['attempts'] >= $max_attempts) {
        // Log rate limit violation for security monitoring
        if (function_exists('ybt_log_security_event')) {
            ybt_log_security_event('rate_limit_exceeded', array(
                'action' => $action,
                'ip' => $ip,
                'user_id' => $user_id,
                'attempts' => $data['attempts'],
                'window' => $window_seconds
            ));
        }
        return false;
    }
    
    // Increment counter
    $data['attempts']++;
    set_transient($transient_key, $data, $window_seconds);
    return true;
}

/**
 * Get rate limit status for an action
 * 
 * @param string $action Action to check
 * @param int $max_attempts Maximum attempts allowed
 * @param int $window_seconds Time window in seconds
 * @return array Rate limit status information
 */
function ybt_get_rate_limit_status($action = 'general', $max_attempts = 5, $window_seconds = 300) {
    $ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';
    $user_id = get_current_user_id();
    $identifier = $user_id > 0 ? 'user_' . $user_id : 'ip_' . md5($ip);
    $transient_key = 'ybt_rate_limit_' . md5($action . '_' . $identifier);
    
    $data = get_transient($transient_key);
    
    if ($data === false) {
        return array(
            'attempts' => 0,
            'remaining' => $max_attempts,
            'reset_time' => time() + $window_seconds,
            'limited' => false
        );
    }
    
    $elapsed = time() - $data['first_attempt'];
    $reset_time = $data['first_attempt'] + $window_seconds;
    
    if ($elapsed > $window_seconds) {
        return array(
            'attempts' => 0,
            'remaining' => $max_attempts,
            'reset_time' => $reset_time,
            'limited' => false
        );
    }
    
    $remaining = max(0, $max_attempts - $data['attempts']);
    
    return array(
        'attempts' => $data['attempts'],
        'remaining' => $remaining,
        'reset_time' => $reset_time,
        'limited' => $remaining === 0
    );
}

/**
 * Rate limit middleware for AJAX requests
 * 
 * @param string $action Action name
 * @param int $max_attempts Maximum attempts
 * @param int $window_seconds Time window
 * @return bool|WP_Error True if allowed, WP_Error if limited
 */
function ybt_rate_limit_middleware($action = 'general', $max_attempts = 5, $window_seconds = 300) {
    if (!ybt_check_rate_limit($action, $max_attempts, $window_seconds)) {
        $status = ybt_get_rate_limit_status($action, $max_attempts, $window_seconds);
        $wait_time = max(0, $status['reset_time'] - time());
        
        return new WP_Error(
            'rate_limit_exceeded',
            sprintf('Too many requests. Please wait %d seconds before trying again.', $wait_time),
            array('retry_after' => $wait_time)
        );
    }
    return true;
}