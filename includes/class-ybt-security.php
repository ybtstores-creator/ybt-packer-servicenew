<?php
/**
 * YBT Enhanced Security Class
 * Production-ready security features with comprehensive protection
 * Version: 4.0 - Enhanced Security & Performance
 */
if (!defined('ABSPATH')) exit('Direct access denied.');

class YBT_Security {
    private static $instance = null;
    private $rate_limit_cache = array();
    private $security_events = array();
    private $blocked_ips = array();
    private $config;

    // Security configuration
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 900; // 15 minutes in seconds
    const RATE_LIMIT_WINDOW = 300; // 5 minutes
    const MAX_REQUESTS_PER_WINDOW = 100;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->config = function_exists('ybt_config') ? ybt_config() : array();
        $this->init_security_features();
        $this->load_blocked_ips();
    }

    /**
     * Initialize all security features
     */
    private function init_security_features() {
        // Rate limiting for login attempts
        add_action('wp_login_failed', array($this, 'log_failed_login'));
        add_action('wp_login', array($this, 'log_successful_login'), 10, 2);

        // XML-RPC protection
        add_filter('xmlrpc_enabled', '__return_false');

        // Disable file editor - only define if not already set by wp-config.php or another plugin
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        // Hide WordPress version
        remove_action('wp_head', 'wp_generator');

        // Remove version from script and style URLs
        add_filter('script_loader_src', array($this, 'remove_version_from_urls'), 15, 1);
        add_filter('style_loader_src', array($this, 'remove_version_from_urls'), 15, 1);

        // Login security enhancements
        add_filter('authenticate', array($this, 'check_login_security'), 30, 3);

        // NOTE: Security headers (CSP, CORS, X-Frame-Options, etc.) are registered by the
        // main plugin file's ybt_init_security() → ybt_add_security_headers(). This class
        // does NOT register a second send_headers handler to avoid duplicate headers.
        // The add_security_headers() method is kept for backward compatibility if called directly.

        // Heartbeat control for security
        add_filter('heartbeat_settings', array($this, 'control_heartbeat'));

        // REST API security enhancements
        add_filter('rest_authentication_errors', array($this, 'rest_security_check'), 10);
    }

    /**
     * Enhanced rate limiting with IP tracking
     */
    public function check_rate_limit($action = 'general', $max_requests = null, $window_seconds = null) {
        if ($max_requests === null) {
            $max_requests = self::MAX_REQUESTS_PER_WINDOW;
        }
        if ($window_seconds === null) {
            $window_seconds = self::RATE_LIMIT_WINDOW;
        }

        $ip = $this->get_client_ip();
        $transient_name = 'ybt_rate_' . $action . '_' . wp_hash($ip);

        $request_count = get_transient($transient_name);

        if ($request_count === false) {
            set_transient($transient_name, 1, $window_seconds);
            return array('allowed' => true, 'count' => 1, 'limit' => $max_requests);
        }

        if ($request_count >= $max_requests) {
            $this->log_security_event('rate_limit_exceeded', array(
                'action' => $action,
                'ip' => $ip,
                'count' => $request_count,
                'limit' => $max_requests
            ));

            return array(
                'allowed' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $window_seconds,
                'count' => $request_count,
                'limit' => $max_requests
            );
        }

        set_transient($transient_name, $request_count + 1, $window_seconds);
        return array('allowed' => true, 'count' => $request_count + 1, 'limit' => $max_requests);
    }

    /**
     * Enhanced CSRF token generation and validation
     */
    public function generate_csrf_token($form_id) {
        $token = wp_create_nonce('ybt_csrf_' . $form_id);
        $transient_name = 'ybt_csrf_token_' . wp_hash($token);

        set_transient($transient_name, $form_id, HOUR_IN_SECONDS);

        return array(
            'token' => $token,
            'form_id' => $form_id,
            'expires' => time() + HOUR_IN_SECONDS
        );
    }

    /**
     * Validate CSRF token with form ID check
     */
    public function validate_csrf_token($token, $form_id) {
        if (empty($token) || empty($form_id)) {
            return false;
        }

        $transient_name = 'ybt_csrf_token_' . wp_hash($token);
        $stored_form_id = get_transient($transient_name);

        if ($stored_form_id === false || $stored_form_id !== $form_id) {
            $this->log_security_event('csrf_validation_failed', array(
                'form_id' => $form_id,
                'ip' => $this->get_client_ip()
            ));
            return false;
        }

        // Delete used token
        delete_transient($transient_name);

        return wp_verify_nonce($token, 'ybt_csrf_' . $form_id);
    }

    /**
     * Enhanced nonce verification wrapper
     */
    public function verify_nonce($nonce, $action = 'ybt_nonce') {
        if (!wp_verify_nonce($nonce, $action)) {
            $this->log_security_event('nonce_verification_failed', array(
                'action' => $action,
                'ip' => $this->get_client_ip()
            ));
            return false;
        }
        return true;
    }

    /**
     * Log failed login attempts
     */
    public function log_failed_login($username) {
        $ip = $this->get_client_ip();
        $transient_name = 'ybt_login_fails_' . wp_hash($ip);

        $attempts = get_transient($transient_name);
        if ($attempts === false) {
            $attempts = 0;
        }

        $attempts++;
        set_transient($transient_name, $attempts, self::LOCKOUT_DURATION);

        // Check if account should be locked
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->block_ip($ip, self::LOCKOUT_DURATION, 'Too many failed login attempts');

            $this->log_security_event('account_locked', array(
                'username' => $username,
                'ip' => $ip,
                'attempts' => $attempts
            ));
        }

        // Log to activity - fallback if ybt_log_activity is not available
        if (function_exists('ybt_log_activity')) {
            ybt_log_activity(0, 'login_failed', "Failed login attempt for: $username from IP: $ip");
        }

        $this->record_login_attempt($username, null, 0, $ip);
    }

    /**
     * Persist a login attempt row for the security dashboard
     */
    private function record_login_attempt($username, $user_id, $success, $ip) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_login_attempts';
        if (function_exists('ybt_table_exists') && !ybt_table_exists($table_name)) {
            return;
        }

        $wpdb->insert(
            $table_name,
            array(
                'user_id'      => $user_id ? (int) $user_id : null,
                'username'     => sanitize_text_field(substr((string) $username, 0, 100)),
                'ip_address'   => $ip,
                'success'      => (int) $success,
                'attempt_time' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Log successful login
     */
    public function log_successful_login($user_login, $user) {
        // Reset failed attempts on successful login
        $ip = $this->get_client_ip();
        $transient_name = 'ybt_login_fails_' . wp_hash($ip);
        delete_transient($transient_name);

        // Log to activity - fallback if ybt_log_activity is not available
        if (function_exists('ybt_log_activity')) {
            ybt_log_activity($user->ID, 'login', "User logged in successfully: $user_login");
        }

        $this->record_login_attempt($user_login, $user->ID, 1, $ip);
    }

    /**
     * Login security check
     */
    public function check_login_security($user, $username, $password) {
        if (is_wp_error($user)) {
            return $user;
        }

        $ip = $this->get_client_ip();

        // Check if IP is blocked
        if ($this->is_ip_blocked($ip)) {
            return new WP_Error('ip_blocked', __('Access denied from your IP address.', 'ybt-packer'));
        }

        return $user;
    }

    /**
     * Secure file upload with validation
     */
    public function secure_file_upload($file, $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf')) {
        $upload_errors = array();

        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $upload_errors[] = __('File size exceeds maximum limit (5MB).', 'ybt-packer');
        }

        // Get file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($file_extension, $allowed_types)) {
            $upload_errors[] = __('Invalid file type.', 'ybt-packer');
        }

        // Check for malicious file names
        if ($this->is_malicious_filename($file['name'])) {
            $upload_errors[] = __('Invalid file name.', 'ybt-packer');
        }

        // Verify file is actually an image (if image)
        if (in_array($file_extension, array('jpg', 'jpeg', 'png', 'gif'))) {
            $image_info = getimagesize($file['tmp_name']);
            if ($image_info === false) {
                $upload_errors[] = __('Invalid image file.', 'ybt-packer');
            }
        }

        if (!empty($upload_errors)) {
            return array('success' => false, 'errors' => $upload_errors);
        }

        // Handle upload
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $uploaded_file = wp_handle_upload($file, array('test_form' => false));

        if (isset($uploaded_file['error'])) {
            return array('success' => false, 'errors' => array($uploaded_file['error']));
        }

        // Compress image uploads to reduce disk space (best-effort, never blocks the upload)
        if (!empty($uploaded_file['file'])) {
            if (class_exists('YBT_File_Handler')) {
                YBT_File_Handler::compress_image($uploaded_file['file']);
            } elseif (function_exists('wp_get_image_editor')) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }
        }

        return array(
            'success' => true,
            'url' => $uploaded_file['url'],
            'file' => $uploaded_file['file']
        );
    }

    /**
     * Check for malicious filename patterns
     */
    public function is_malicious_filename($filename) {
        $dangerous_patterns = array(
            '/\.\./',
            '/\.(php|phar|pl|py|jsp|asp|exe|bat|sh|cgi)$/i',
            '/[^a-zA-Z0-9._-]/',
            '/^con$/i',
            '/^prn$/i',
            '/^aux$/i',
            '/^nul$/i',
            '/^com[0-9]$/i',
            '/^lpt[0-9]$/i'
        );

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add enhanced security headers
     * NOTE: CSP is set by the main plugin file (ybt_add_security_headers) which includes
     * 'unsafe-inline' and 'unsafe-eval' required for inline scripts/styles in templates.
     * This method only adds non-CSP security headers to avoid conflicts.
     */
    public function add_security_headers() {
        // Only add non-CSP security headers here to avoid conflicting with
        // the main plugin's CSP (which needs unsafe-inline for templates)
        if (headers_sent()) {
            return;
        }

        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // HSTS for production SSL
        if (is_ssl() && (!defined('WP_DEBUG') || !WP_DEBUG)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Control heartbeat for security
     */
    public function control_heartbeat($settings) {
        $settings['interval'] = 60; // Reduce heartbeat frequency
        $settings['autostart'] = false;
        return $settings;
    }

    /**
     * REST API security check
     */
    public function rest_security_check($result) {
        if (!is_user_logged_in()) {
            // OPTIONS preflights are metadata-only (no operation runs); don't
            // count them against the limit.
            $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
            if ($method === 'OPTIONS') {
                return $result;
            }

            // Requests already authenticated via the mobile JWT (Android app,
            // desktop Electron console) send Bearer tokens, not WP cookies, so
            // is_user_logged_in() is false for them. They are not anonymous:
            // skip the limiter for valid tokens.
            $token = function_exists('ybt_get_request_token') ? ybt_get_request_token() : '';
            if (!empty($token) && function_exists('ybt_validate_mobile_token') && ybt_validate_mobile_token($token)) {
                return $result;
            }

            // Rate limit unauthenticated requests
            $rate_limit = $this->check_rate_limit('rest_api', 30, 60);
            if (!$rate_limit['allowed']) {
                return new WP_Error('rest_rate_limited', __('Rate limit exceeded.'), array('status' => 429));
            }
        }
        return $result;
    }

    /**
     * Block IP address
     */
    public function block_ip($ip, $duration = null, $reason = '') {
        if ($duration === null) {
            $duration = DAY_IN_SECONDS;
        }

        $blocked_ips = get_option('ybt_blocked_ips', array());
        $blocked_ips[$ip] = array(
            'blocked_at' => time(),
            'expires' => time() + $duration,
            'reason' => $reason
        );

        update_option('ybt_blocked_ips', $blocked_ips);
        $this->blocked_ips[$ip] = $blocked_ips[$ip];
    }

    /**
     * Check if IP is blocked
     */
    public function is_ip_blocked($ip = null) {
        if ($ip === null) {
            $ip = $this->get_client_ip();
        }

        if (!isset($this->blocked_ips[$ip])) {
            $this->load_blocked_ips();
        }

        if (!isset($this->blocked_ips[$ip])) {
            return false;
        }

        // Check if block has expired
        if ($this->blocked_ips[$ip]['expires'] < time()) {
            unset($this->blocked_ips[$ip]);
            $blocked_ips = get_option('ybt_blocked_ips', array());
            unset($blocked_ips[$ip]);
            update_option('ybt_blocked_ips', $blocked_ips);
            return false;
        }

        return true;
    }

    /**
     * Load blocked IPs from database
     */
    private function load_blocked_ips() {
        $blocked_ips = get_option('ybt_blocked_ips', array());

        // Clean expired entries
        foreach ($blocked_ips as $ip => $data) {
            if ($data['expires'] < time()) {
                unset($blocked_ips[$ip]);
            } else {
                $this->blocked_ips[$ip] = $data;
            }
        }

        if (count($blocked_ips) !== count($this->blocked_ips)) {
            update_option('ybt_blocked_ips', $this->blocked_ips);
        }
    }

    /**
     * Get client IP address
     * Only trusts REMOTE_ADDR to prevent IP spoofing attacks
     */
    public function get_client_ip() {
        // Only trust REMOTE_ADDR by default to prevent IP spoofing
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    }

    /**
     * Log security event
     */
    public function log_security_event($event_type, $details = array()) {
        $event = array(
            'type' => $event_type,
            'details' => $details,
            'ip' => $this->get_client_ip(),
            'user_id' => get_current_user_id(),
            'timestamp' => time()
        );

        $this->security_events[] = $event;

        // Store in database for persistence
        $stored_events = get_option('ybt_security_events', array());
        array_unshift($stored_events, $event);

        // Keep only last 100 events
        if (count($stored_events) > 100) {
            $stored_events = array_slice($stored_events, 0, 100);
        }

        update_option('ybt_security_events', $stored_events);

        // Send alert for high severity events
        $severity = $this->get_event_severity($event_type);
        if ($severity === 'high') {
            $this->send_security_alert($event);
        }
    }

    /**
     * Get event severity level
     */
    public function get_event_severity($event_type) {
        $high_severity = array(
            'rate_limit_exceeded',
            'csrf_validation_failed',
            'nonce_verification_failed',
            'account_locked',
            'sql_injection_attempt',
            'xss_attempt',
            'unauthorized_access'
        );

        return in_array($event_type, $high_severity) ? 'high' : 'medium';
    }

    /**
     * Send security alert
     */
    public function send_security_alert($event) {
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }

        $subject = sprintf('[%s] Security Alert: %s', get_bloginfo('name'), $event['type']);

        $message = "A security event has been detected:\n\n";
        $message .= "Event Type: " . $event['type'] . "\n";
        $message .= "IP Address: " . $event['ip'] . "\n";
        $message .= "User ID: " . $event['user_id'] . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s', $event['timestamp']) . "\n";
        $message .= "Details: " . json_encode($event['details']) . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Remove version from URLs
     */
    public function remove_version_from_urls($url) {
        // Only strip version from external/third-party URLs, preserve for YBT plugin assets
        if (strpos($url, 'ver=') !== false && strpos($url, 'ybt-packer-service') === false && strpos($url, 'ybt-theme') === false) {
            $url = remove_query_arg('ver', $url);
        }
        return $url;
    }

    /**
     * Get security statistics
     */
    public function get_security_stats() {
        $events = get_option('ybt_security_events', array());

        $stats = array(
            'total_events' => count($events),
            'blocked_ips' => count($this->blocked_ips),
            'by_type' => array()
        );

        foreach ($events as $event) {
            $type = $event['type'];
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
        }

        return $stats;
    }

    /**
     * Enhanced input sanitization
     */
    public function sanitize_input($data, $type = 'text') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize_input($item, $type);
            }, $data);
        }

        $data = wp_unslash($data);

        switch ($type) {
            case 'email':
                return sanitize_email($data);
            case 'url':
                return esc_url_raw($data);
            case 'html':
                return wp_kses_post($data);
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'int':
                return intval($data);
            case 'float':
                return floatval($data);
            case 'alphanumeric':
                return preg_replace('/[^a-zA-Z0-9]/', '', $data);
            case 'filename':
                return sanitize_file_name($data);
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Validate password strength
     */
    public function validate_password($password, $min_length = 8) {
        $errors = array();

        if (strlen($password) < $min_length) {
            $errors[] = sprintf(__('Password must be at least %d characters.'), $min_length);
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = __('Password must contain at least one uppercase letter.');
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = __('Password must contain at least one lowercase letter.');
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = __('Password must contain at least one number.');
        }

        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = __('Password must contain at least one special character.');
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
}

// Initialize security
function ybt_security() {
    return YBT_Security::getInstance();
}

// Initialize on plugins loaded
add_action('plugins_loaded', function() {
    if (class_exists('YBT_Config')) {
        ybt_security();
    }
});
