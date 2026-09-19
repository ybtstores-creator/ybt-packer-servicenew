<?php
/**
 * YBT Logger Class - SECURE VERSION
 * Structured logging with sensitive data masking and secure storage
 *
 * SECURITY FIXES:
 * ✅ Logs stored OUTSIDE web root (configurable via YBT_LOG_DIR)
 * ✅ Sensitive data masking (passwords, tokens, etc.)
 * ✅ Directory permissions hardened (0700)
 * ✅ index.php protection against directory listing
 * ✅ Critical alerts sent to admin email
 */
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_Logger {
    private static $instance = null;
    private $log_dir;
    private $max_file_size = 5242880; // 5MB
    private $max_files = 5;

    // Sensitive keys to mask in logs
    private $sensitive_keys = array(
        'password', 'pass', 'token', 'api_key', 'secret', 'secret_key',
        'cvv', 'card', 'credit_card', 'auth', 'jwt', 'private_key',
        'fcm_token', 'firebase_token', 'session_id'
    );

    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Determine secure log directory
        // Priority 1: Custom directory defined in wp-config.php
        if (defined('YBT_LOG_DIR') && is_dir(YBT_LOG_DIR)) {
            $this->log_dir = YBT_LOG_DIR . '/ybt-logs';
        }
        // Priority 2: Directory outside web root (one level above uploads)
        else {
            $upload_dir = wp_upload_dir();
            $base_dir = dirname($upload_dir['basedir']); // Parent of uploads
            $this->log_dir = $base_dir . '/ybt-secure-logs/ybt-logs';
        }

        // Create directory with strict permissions
        wp_mkdir_p($this->log_dir);
        @chmod($this->log_dir, 0700); // Owner-only access

        // Add index.php to prevent directory listing
        $index_file = $this->log_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden.' . PHP_EOL);
        }

        // NEVER use .htaccess alone for protection (Nginx won't honor it)
        $htaccess_file = $this->log_dir . '/.htaccess';
        if (file_exists($htaccess_file)) {
            @unlink($htaccess_file); // Remove if exists (redundant with directory location)
        }
    }

    /**
     * Mask sensitive data in context array
     */
    private function sanitize_context($context) {
        if (!is_array($context)) {
            return $context;
        }

        foreach ($context as $key => $value) {
            // Check if key contains sensitive terms (case-insensitive)
            $key_lower = strtolower($key);
            foreach ($this->sensitive_keys as $sensitive_key) {
                if (strpos($key_lower, $sensitive_key) !== false) {
                    $context[$key] = '***REDACTED***';
                    break;
                }
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $context[$key] = $this->sanitize_context($value);
            }
        }

        return $context;
    }

    /**
     * Log debug message
     */
    public function debug($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log(self::LEVEL_DEBUG, $message, $context);
        }
    }

    /**
     * Log info message
     */
    public function info($message, $context = array()) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning($message, $context = array()) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     */
    public function error($message, $context = array()) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical($message, $context = array()) {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
        // Send email notification for critical errors
        $this->send_critical_alert($message, $context);
    }

    /**
     * Main logging method
     */
    private function log($level, $message, $context = array()) {
        // Sanitize sensitive data BEFORE logging
        $context = $this->sanitize_context($context);

        $log_file = $this->get_log_file();

        // Rotate log if needed
        $this->rotate_logs($log_file);

        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();

        $log_entry = sprintf(
            "[%s] [%s] [User:%d] [IP:%s] %s %s\n",
            $timestamp,
            $level,
            $user_id,
            $ip,
            $message,
            !empty($context) ? wp_json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );

        // Write to log file
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // Also log to WordPress debug.log if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("YBT [{$level}]: {$message}");
        }
    }

    /**
     * Get current log file path
     */
    private function get_log_file() {
        $date = current_time('Y-m-d');
        return $this->log_dir . "/ybt-{$date}.log";
    }

    /**
     * Rotate logs when file size exceeds limit
     */
    private function rotate_logs($log_file) {
        if (!file_exists($log_file)) {
            return;
        }

        if (filesize($log_file) > $this->max_file_size) {
            $timestamp = time();
            $rotated_file = str_replace('.log', "-{$timestamp}.log", $log_file);
            @rename($log_file, $rotated_file);
            // Clean old log files
            $this->cleanup_old_logs();
        }
    }

    /**
     * Clean up old log files
     */
    private function cleanup_old_logs() {
        $files = glob($this->log_dir . '/ybt-*.log');
        if (count($files) > $this->max_files) {
            // Sort by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            // Delete oldest files
            $to_delete = array_slice($files, 0, count($files) - $this->max_files);
            foreach ($to_delete as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    /**
     * Send critical error alert to admin
     */
    private function send_critical_alert($message, $context) {
        // Rate limit critical alerts (max 1 per hour)
        if (get_transient('ybt_critical_alert_sent')) {
            return;
        }
        set_transient('ybt_critical_alert_sent', true, HOUR_IN_SECONDS);

        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = sprintf("[%s] CRITICAL ERROR ALERT", $site_name);

        $body = "A critical error has occurred on your YBT Packer Service:\n\n";
        $body .= "Message: {$message}\n";
        $body .= "Time: " . current_time('Y-m-d H:i:s') . "\n";
        $body .= "IP Address: " . $this->get_client_ip() . "\n";
        $body .= "User ID: " . get_current_user_id() . "\n\n";

        if (!empty($context)) {
            $body .= "Context:\n" . print_r($this->sanitize_context($context), true) . "\n";
        }

        $body .= "\n---\n";
        $body .= "This is an automated alert from YBT Packer Service.\n";
        $body .= "Please check your logs immediately at: " . $this->log_dir;

        // Send email with error suppression (don't break on mail failure)
        @wp_mail($admin_email, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
    }

    /**
     * Get recent logs (sanitized)
     */
    public function get_recent_logs($lines = 100, $level = null) {
        $log_file = $this->get_log_file();
        if (!file_exists($log_file)) {
            return array();
        }

        $file = new SplFileObject($log_file);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        $start_line = max(0, $total_lines - $lines);
        $file->seek($start_line);

        $logs = array();
        while (!$file->eof()) {
            $line = $file->current();
            // Sanitize line before returning (mask any sensitive data that might have slipped through)
            $line = $this->sanitize_log_line($line);

            if ($level === null || strpos($line, "[{$level}]") !== false) {
                $logs[] = trim($line);
            }
            $file->next();
        }

        return array_reverse($logs); // Most recent first
    }

    /**
     * Sanitize individual log line
     */
    private function sanitize_log_line($line) {
        if (empty($line)) {
            return $line;
        }

        // Mask any tokens/API keys that might appear in messages
        $patterns = array(
            '/(token[\'"\s]*[:=][\'"\s]*)([a-zA-Z0-9\-_]{32,})/i',
            '/(api[_-]?key[\'"\s]*[:=][\'"\s]*)([a-zA-Z0-9\-_]{20,})/i',
            '/(password[\'"\s]*[:=][\'"\s]*)([^\s\'",]+)/i',
            '/(fcm[_-]?token[\'"\s]*[:=][\'"\s]*)([a-zA-Z0-9\-_]{100,})/i',
            '/(secret[\'"\s]*[:=][\'"\s]*)([a-zA-Z0-9\-_]{8,})/i',
            '/(jwt[\'"\s]*[:=][\'"\s]*)([a-zA-Z0-9\-_\.]{20,})/i',
            '/(private[_-]?key[\'"\s]*[:=][\'"\s]*)([a-zA-Z0-9\-_]{20,})/i',
            '/(auth[\'"\s]*[:=][\'"\s]*)([a-zA-Z0-9\-_]{8,})/i',
            '/(cvv[\'"\s]*[:=][\'"\s]*)([0-9]{3,4})/i',
            '/(card[\'"\s]*[:=][\'"\s]*|credit[_-]?card[\'"\s]*[:=][\'"\s]*)([0-9]{4,})/i',
            '/(session[_-]?id[\'"\s]*[:=][\'"\s]*)([a-zA-Z0-9\-_]{20,})/i'
        );

        foreach ($patterns as $pattern) {
            $line = preg_replace($pattern, '$1***REDACTED***', $line);
        }

        return $line;
    }

    /**
     * Clear all logs (requires admin capability)
     */
    public function clear_logs() {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $files = glob($this->log_dir . '/ybt-*.log');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }
}

// Helper functions
if (!function_exists('ybt_logger')) {
    function ybt_logger() {
        return YBT_Logger::getInstance();
    }
}

if (!function_exists('ybt_log_debug')) {
    function ybt_log_debug($message, $context = array()) {
        ybt_logger()->debug($message, $context);
    }
}

if (!function_exists('ybt_log_info')) {
    function ybt_log_info($message, $context = array()) {
        ybt_logger()->info($message, $context);
    }
}

if (!function_exists('ybt_log_warning')) {
    function ybt_log_warning($message, $context = array()) {
        ybt_logger()->warning($message, $context);
    }
}

if (!function_exists('ybt_log_error')) {
    function ybt_log_error($message, $context = array()) {
        ybt_logger()->error($message, $context);
    }
}

if (!function_exists('ybt_log_critical')) {
    function ybt_log_critical($message, $context = array()) {
        ybt_logger()->critical($message, $context);
    }
}

/**
 * Security event logger (separate from regular logs)
 */
if (!function_exists('ybt_log_security_event')) {
    function ybt_log_security_event($event, $details = array()) {
        // Log to regular logger if available
        if (function_exists('ybt_logger')) {
            ybt_logger()->warning('SECURITY EVENT: ' . $event, $details);
        }

        // Persist to the DB security log so the admin dashboard can query it
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_security_log';
        if (function_exists('ybt_table_exists') && ybt_table_exists($table_name)) {
            $detail_text = is_string($details) ? $details : wp_json_encode($details);
            $user_id = 0;
            if (is_array($details) && isset($details['user_id'])) {
                $user_id = (int) $details['user_id'];
            }
            if (!$user_id) {
                $user_id = get_current_user_id();
            }
            $wpdb->insert(
                $table_name,
                array(
                    'event_type'  => sanitize_text_field(substr((string) $event, 0, 100)),
                    'description' => sanitize_textarea_field(substr((string) $detail_text, 0, 1000)),
                    'severity'    => ybt_security_event_severity($event),
                    'user_id'     => $user_id ?: null,
                    'ip_address'  => function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : 'unknown',
                    'date_created' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%s', '%s')
            );
        }

        // Also log to dedicated security log file if in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_entry = array(
                'timestamp' => current_time('mysql'),
                'event' => $event,
                'ip' => function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : 'unknown',
                'details' => $details
            );
            $log_file = WP_CONTENT_DIR . '/ybt-security.log';
            $log_line = date('Y-m-d H:i:s') . ' - ' . $event . ' - ' . json_encode($log_entry) . "\n";
            if (is_writable(dirname($log_file))) {
                @file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
            }
        }
    }
}

if (!function_exists('ybt_security_event_severity')) {
    function ybt_security_event_severity($event) {
        $event = strtoupper((string) $event);
        if (preg_match('/UNAUTHORIZED|BLOCKED|LOCKOUT|LOCKED|ATTACK|FORGED|REJECTED|ABUSE|TRAVERSAL|INJECTION/', $event)) {
            return 'critical';
        }
        if (preg_match('/FAILED|INVALID|EXCEEDED|ROTATED|DELETED|DISABLED|SUSPICIOUS/', $event)) {
            return 'warning';
        }
        return 'info';
    }
}

/**
 * Log activity to the database activity log table
 */
if (!function_exists('ybt_log_activity')) {
    function ybt_log_activity($user_id, $action, $details = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_activity_log';
        
        // Use helper if available to check table existence
        $table_exists = false;
        if (function_exists('ybt_table_exists')) {
            $table_exists = ybt_table_exists($table_name);
        } else {
            $table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name);
        }

        if ($table_exists) {
            $wpdb->insert($table_name, array(
                'user_id' => (int) $user_id,
                'action' => sanitize_text_field($action),
                'details' => is_string($details) ? sanitize_textarea_field($details) : wp_json_encode($details),
                'ip_address' => function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0',
                'date_created' => current_time('mysql')
            ));
        }
        
        // Also log to info log for extra visibility
        ybt_log_info("Activity: $action (User: $user_id)", array('details' => $details));
    }
}
