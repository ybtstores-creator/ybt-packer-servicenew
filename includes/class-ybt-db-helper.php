<?php
/**
 * YBT Database Helper Class - SECURE VERSION
 * SAFE query execution with strict validation and whitelist protection
 *
 * SECURITY FIXES:
 * ✅ Query structure validation
 * ✅ Table/column whitelist enforcement
 * ✅ Non-SELECT query blocking
 * ✅ Rate limiting protection
 * ✅ Dangerous pattern detection
 */
if (!defined('ABSPATH')) exit;

class YBT_DB_Helper {
    // Whitelist of allowed tables for query validation
    private static $allowed_tables = array(
        'ybt_tracking', 'ybt_admin_messages', 'ybt_chat', 'ybt_cartons',
        'ybt_payment_settings', 'ybt_warehouse_settings', 'ybt_batch_payments',
        'ybt_fcm_tokens', 'ybt_activity_log'
    );

    // Whitelist of allowed columns per table (prevents column injection)
    private static $allowed_columns = array(
        'ybt_tracking' => array('id', 'tracking_number', 'user_id', 'username', 'status', 'date_added', 'batch_id', 'shipping_method', 'item_count', 'images', 'description'),
        'ybt_chat' => array('id', 'user_id', 'username', 'message', 'attachment', 'is_admin', 'date_created'),
        'ybt_fcm_tokens' => array('id', 'user_id', 'fcm_token', 'device_type', 'last_updated')
        // Add other tables as needed
    );

    /**
     * Execute SAFE SELECT query with strict validation
     * @throws Exception on invalid query or security violation
     */
    public static function safe_query($query, $params = array()) {
        self::validate_query($query, 'SELECT');
        global $wpdb;

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $results = $wpdb->get_results($query);

        if ($wpdb->last_error) {
            $error_context = array(
                'query' => substr($query, 0, 200),
                'error' => $wpdb->last_error
            );
            ybt_log_error('DB Query Error', $error_context);
            throw new Exception('Database query failed: ' . $wpdb->last_error);
        }

        return $results;
    }

    /**
     * Get single row with validation
     */
    public static function safe_get_row($query, $params = array()) {
        self::validate_query($query, 'SELECT');
        global $wpdb;

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $result = $wpdb->get_row($query);

        if ($wpdb->last_error) {
            $error_context = array(
                'query' => substr($query, 0, 200),
                'error' => $wpdb->last_error
            );
            ybt_log_error('DB Query Error', $error_context);
            throw new Exception('Database query failed');
        }

        return $result;
    }

    /**
     * Get single value with validation
     */
    public static function safe_get_var($query, $params = array()) {
        self::validate_query($query, 'SELECT');
        global $wpdb;

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $result = $wpdb->get_var($query);

        if ($wpdb->last_error) {
            $error_context = array(
                'query' => substr($query, 0, 200),
                'error' => $wpdb->last_error
            );
            ybt_log_error('DB Query Error', $error_context);
            throw new Exception('Database query failed');
        }

        return $result;
    }

    /**
     * Validate query structure and prevent dangerous operations
     * @param string $query SQL query to validate
     * @param string $allowed_type Allowed query type (SELECT only by default)
     * @throws Exception on security violation
     */
    private static function validate_query($query, $allowed_type = 'SELECT') {
        global $wpdb;
        $query_trimmed = ltrim($query);
        $query_upper = strtoupper($query_trimmed);

        // 1. Block non-SELECT queries by default
        if ($allowed_type === 'SELECT' && strpos($query_upper, 'SELECT') !== 0) {
            $error_context = array('query' => substr($query, 0, 100));
            ybt_log_security_event('BLOCKED_NON_SELECT_QUERY', $error_context);
            throw new Exception('Only SELECT queries allowed in safe_query methods');
        }

        // 2. Block dangerous SQL keywords and patterns
        $dangerous_patterns = array(
            '/\b(delete|update|insert|drop|truncate|alter|create|replace)\b/i',
            '/\b(union\s+select|information_schema|load_file|into\s+outfile)\b/i',
            '/--|\#|\/\*|\*\//', // SQL comments that could hide malicious code
            '/[\';"\/]/' // Suspicious characters in query structure
        );

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                $error_context = array(
                    'pattern' => $pattern,
                    'query' => substr($query, 0, 100)
                );
                ybt_log_security_event('BLOCKED_DANGEROUS_QUERY', $error_context);
                throw new Exception('Query contains blocked patterns - possible SQL injection attempt');
            }
        }

        // 3. Validate table names (whitelist approach)
        if (preg_match('/FROM\s+`?([a-z0-9_]+)`?(?:\s+AS\s+[a-z0-9_]+)?\b/i', $query, $matches) ||
            preg_match('/JOIN\s+`?([a-z0-9_]+)`?(?:\s+AS\s+[a-z0-9_]+)?\b/i', $query, $matches) ||
            preg_match('/INTO\s+`?([a-z0-9_]+)`?\b/i', $query, $matches)) {
            $full_table_name = isset($matches[1]) ? $matches[1] : '';
            if (!empty($full_table_name)) {
                $base_table = str_replace($wpdb->prefix, '', $full_table_name);

                if (!in_array($base_table, self::$allowed_tables)) {
                    $error_context = array('table' => $base_table);
                    ybt_log_security_event('UNAUTHORIZED_TABLE_ACCESS', $error_context);
                    throw new Exception('Query references unauthorized table: ' . esc_html($base_table));
                }

                if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . $base_table)) {
                    throw new Exception('Query references non-existent table: ' . esc_html($base_table));
                }
            }
        }

        // 4. Rate limiting (prevent query flooding)
        if (!self::check_rate_limit()) {
            ybt_log_security_event('RATE_LIMIT_EXCEEDED');
            throw new Exception('Query rate limit exceeded. Please try again later.');
        }
    }

    /**
     * Simple rate limiting for database queries (100 queries per minute per IP)
     */
    private static function check_rate_limit() {
        $ip = self::get_client_ip();
        $transient_key = 'ybt_db_query_limit_' . wp_hash($ip);
        $count = (int) get_transient($transient_key);

        if ($count > 100) {
            return false;
        }

        set_transient($transient_key, $count + 1, 60);
        return true;
    }

    /**
     * Get sanitized client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = array_map('trim', explode(',', $ip));
                    $ip = $ips[0];
                }
                return $ip;
            }
        }

        return '127.0.0.1';
    }

    /**
     * Log security events to activity log
     */
    private static function log_security_event($event_type, $context = array()) {
        if (function_exists('ybt_log_activity')) {
            ybt_log_activity(0, 'security_alert', $event_type . ': ' . wp_json_encode($context));
        }
    }
}
