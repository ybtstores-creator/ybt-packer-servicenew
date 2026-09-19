<?php
/**
 * YBT Configuration Management Class
 * Handles production settings and environment configuration
 */
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_Config {
    private static $instance = null;
    private $config = array();

    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_config();
    }

    /**
     * Load configuration based on environment
     */
    private function load_config() {
        $this->config = array(
            // Production Settings
            'production' => array(
                'debug' => false,
                'cache_enabled' => true,
                'cache_duration' => 3600,
                'rate_limit' => 100,
                'max_file_size' => 10485760, // 10MB
                'allowed_file_types' => array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'),
                'session_timeout' => 1800, // 30 minutes
                'max_login_attempts' => 5,
                'lockout_duration' => 900, // 15 minutes
                'enable_logging' => true,
                'log_level' => 'error',
                'backup_enabled' => true,
                'backup_frequency' => 'daily'
            ),
            // Development Settings
            'development' => array(
                'debug' => true,
                'cache_enabled' => false,
                'cache_duration' => 60,
                'rate_limit' => 1000,
                'max_file_size' => 52428800, // 50MB
                'allowed_file_types' => array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'),
                'session_timeout' => 7200, // 2 hours
                'max_login_attempts' => 10,
                'lockout_duration' => 300, // 5 minutes
                'enable_logging' => true,
                'log_level' => 'debug',
                'backup_enabled' => false,
                'backup_frequency' => 'never'
            ),
            // Security Settings
            'security' => array(
                'enable_csrf_protection' => true,
                'enable_rate_limiting' => true,
                'enable_ip_whitelist' => false,
                'allowed_ips' => array(),
                'enable_2fa' => false,
                'password_min_length' => 8,
                'password_require_special' => true,
                'enable_audit_log' => true,
                'session_regenerate' => true
            ),
            // Performance Settings
            'performance' => array(
                'enable_compression' => true,
                'enable_minification' => true,
                'lazy_load_images' => true,
                'database_optimization' => true,
                'query_cache' => true,
                'object_cache' => false,
                'cdn_enabled' => false,
                'cdn_url' => ''
            ),
            // Email Settings
            'email' => array(
                'smtp_enabled' => false,
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',  // Always empty by default
                'smtp_encryption' => 'tls',
                'from_email' => get_option('admin_email'),
                'from_name' => get_bloginfo('name'),
                'notification_enabled' => true
            ),
            // API Settings
            'api' => array(
                'enable_rest_api' => true,
                'api_version' => 'v1',
                'rate_limit_per_minute' => 60,
                'require_authentication' => true,
                'enable_cors' => false,
                'allowed_origins' => array(),
                'api_key_required' => false
            )
        );
    }

    /**
     * Get configuration value
     */
    public function get($key, $default = null) {
        $environment = $this->get_environment();

        // Check environment-specific config first
        if (isset($this->config[$environment][$key])) {
            return $this->config[$environment][$key];
        }

        // Check other config sections
        foreach ($this->config as $section => $values) {
            if (isset($values[$key])) {
                return $values[$key];
            }
        }

        return $default;
    }

    /**
     * Get section configuration
     */
    public function get_section($section) {
        return isset($this->config[$section]) ? $this->config[$section] : array();
    }

    /**
     * Set configuration value
     */
    public function set($key, $value, $section = null) {
        // Validate sensitive configuration
        $sensitive_keys = array('smtp_password', 'api_key', 'secret_key');
        if (in_array($key, $sensitive_keys)) {
            if (!current_user_can('manage_options')) {
                return false;
            }
            $value = sanitize_text_field($value);
        }

        if ($section) {
            if (!isset($this->config[$section])) {
                $this->config[$section] = array();
            }
            $this->config[$section][$key] = $value;
        } else {
            $environment = $this->get_environment();
            $this->config[$environment][$key] = $value;
        }

        $this->save_config();
        return true;
    }

    /**
     * Determine current environment
     */
    private function get_environment() {
        // Check for explicit environment setting
        if (defined('YBT_ENVIRONMENT')) {
            return YBT_ENVIRONMENT;
        }

        // Check WordPress debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }

        // Check server environment
        if (isset($_SERVER['SERVER_NAME'])) {
            $server_name = $_SERVER['SERVER_NAME'];
            if (strpos($server_name, 'localhost') !== false ||
                strpos($server_name, '127.0.0.1') !== false ||
                strpos($server_name, '.local') !== false ||
                strpos($server_name, 'dev.') === 0 ||
                strpos($server_name, 'staging.') === 0) {
                return 'development';
            }
        }

        return 'production';
    }

    /**
     * Save configuration to database
     */
    private function save_config() {
        update_option('ybt_config', $this->config);
    }

    /**
     * Load configuration from database
     */
    public function load_from_database() {
        $saved_config = get_option('ybt_config', array());
        if (!empty($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }
    }

    /**
     * Reset configuration to defaults
     */
    public function reset_to_defaults() {
        delete_option('ybt_config');
        $this->load_config();
    }

    /**
     * Validate configuration
     */
    public function validate() {
        $errors = array();

        // Validate file size limits
        $max_file_size = $this->get('max_file_size');
        if ($max_file_size > wp_max_upload_size()) {
            $errors[] = 'Max file size exceeds WordPress upload limit';
        }

        // Validate email settings if SMTP is enabled
        if ($this->get('smtp_enabled')) {
            if (empty($this->get('smtp_host'))) {
                $errors[] = 'SMTP host is required when SMTP is enabled';
            }
            if (empty($this->get('smtp_username'))) {
                $errors[] = 'SMTP username is required when SMTP is enabled';
            }
        }

        // Validate rate limits
        $rate_limit = $this->get('rate_limit');
        if (!is_numeric($rate_limit) || $rate_limit < 1) {
            $errors[] = 'Rate limit must be a positive number';
        }

        return $errors;
    }

    /**
     * Get all configuration for admin display
     */
    public function get_all_config() {
        return $this->config;
    }

    /**
     * Check if feature is enabled
     */
    public function is_enabled($feature) {
        return (bool) $this->get($feature, false);
    }

    /**
     * Get current environment name
     */
    public function get_current_environment() {
        return $this->get_environment();
    }

    /**
     * Export configuration
     */
    public function export_config($include_sensitive = false) {
        $export_config = $this->config;

        if (!$include_sensitive) {
            // Remove or mask sensitive data
            $sensitive_keys = array('smtp_password', 'api_key', 'secret_key', 'password');
            foreach ($export_config as $section => &$values) {
                foreach ($sensitive_keys as $key) {
                    if (isset($values[$key])) {
                        $values[$key] = '***REDACTED***';
                    }
                }
            }
        }

        return wp_json_encode($export_config, JSON_PRETTY_PRINT);
    }

    /**
     * Import configuration
     */
    public function import_config($json_config) {
        $imported = json_decode($json_config, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->config = array_merge($this->config, $imported);
            $this->save_config();
            return true;
        }
        return false;
    }
}

// Initialize configuration
function ybt_config() {
    return YBT_Config::getInstance();
}

// Load configuration from database on init
add_action('init', function() {
    ybt_config()->load_from_database();
});
