<?php
/**
 * YBT Memory Management Class
 * Handles memory optimization and cleanup for better performance
 */
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_Memory_Manager {
    private static $instance = null;
    private $memory_threshold = 64 * 1024 * 1024; // 64MB
    private $cleanup_actions = array();

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register shutdown cleanup
        add_action('shutdown', array($this, 'cleanup_memory'));

        // Monitor memory usage
        add_action('admin_init', array($this, 'monitor_memory_usage'));
    }

    /**
     * Check if memory usage is approaching limits
     */
    public function check_memory_usage() {
        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->get_memory_limit();

        if ($memory_limit > 0) {
            $usage_percentage = ($memory_usage / $memory_limit) * 100;

            if ($usage_percentage > 85) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get PHP memory limit
     */
    private function get_memory_limit() {
        if (function_exists('ini_get')) {
            $memory_limit = ini_get('memory_limit');

            if (empty($memory_limit)) {
                return 0;
            }

            if (is_numeric($memory_limit)) {
                return $memory_limit * 1024 * 1024; // Convert to bytes
            }

            // Handle shorthand notation (e.g., '64M', '1G')
            $unit = strtolower(substr($memory_limit, -1));
            $value = (int)$memory_limit;

            switch ($unit) {
                case 'g':
                    return $value * 1024 * 1024 * 1024;
                case 'm':
                    return $value * 1024 * 1024;
                case 'k':
                    return $value * 1024;
                default:
                    return $value;
            }
        }

        return 0;
    }

    /**
     * Register cleanup action
     */
    public function register_cleanup_action($callback, $priority = 10) {
        $this->cleanup_actions[] = array(
            'callback' => $callback,
            'priority' => $priority
        );

        // Sort by priority
        usort($this->cleanup_actions, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }

    public function trigger_cleanup() {
        // Prevent infinite loops
        static $is_cleaning = false;
        if ($is_cleaning) return;
        $is_cleaning = true;

        $this->cleanup_globals();

        foreach ($this->cleanup_actions as $action) {
            if (is_callable($action['callback'])) {
                call_user_func($action['callback']);
            }
        }

        // Force GC
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $is_cleaning = false;
    }

    /**
     * Trigger cleanup actions on shutdown if needed
     */
    public function cleanup_memory() {
        if ($this->check_memory_usage()) {
            $this->trigger_cleanup();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[YBT Memory] Emergency cleanup performed. Usage: ' . $this->format_bytes(memory_get_usage(true)));
            }
        }
    }

    /**
     * Monitor memory usage in admin
     */
    public function monitor_memory_usage() {
        if (is_admin() && current_user_can('manage_options')) {
            $memory_usage = memory_get_usage(true);
            $memory_limit = $this->get_memory_limit();

            if ($memory_limit > 0) {
                $usage_percentage = ($memory_usage / $memory_limit) * 100;

                if ($usage_percentage > 70) {
                    add_action('admin_notices', function() use ($usage_percentage) {
                        echo '<div class="notice notice-warning is-dismissible">';
                        echo '<p><strong>YBT Packing Service:</strong> Memory usage is high (' . round($usage_percentage) . '%). Consider increasing PHP memory limit if you experience issues.</p>';
                        echo '</div>';
                    });
                }
            }
        }
    }

    /**
     * Format bytes for human readability
     */
    private function format_bytes($bytes) {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Optimize database queries for memory efficiency
     */
    public function optimize_database_queries() {
        global $wpdb;

        // Set MySQL to use less memory for large result sets
        $wpdb->query("SET SESSION SQL_BIG_RESULT = 1");

        // Use unbuffered queries for large datasets
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $wpdb->query("SET SESSION SESSION_QUERY_CACHE_TYPE = OFF");
        }
    }

    /**
     * Clean up global variables
     */
    public function cleanup_globals() {
        // Clear database query log if it exists (saves memory on long-running processes)
        if (isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb']->queries = array();
        }

        // Clear internal non-persistent cache
        global $wp_object_cache;
        if (is_object($wp_object_cache) && property_exists($wp_object_cache, 'cache')) {
            $wp_object_cache->cache = array();
        }

        // Clean up post and request globals
        if (isset($GLOBALS['post']) && is_object($GLOBALS['post'])) {
            unset($GLOBALS['post']->post_content_filtered);
            unset($GLOBALS['post']->post_excerpt);
        }

        // Proactively clear output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Get memory usage statistics
     */
    public function get_memory_stats() {
        return array(
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'memory_limit' => $this->get_memory_limit(),
            'usage_percentage' => $this->get_memory_limit() > 0 ?
                (memory_get_usage(true) / $this->get_memory_limit()) * 100 : 0
        );
    }
}

/**
 * Global helper for memory manager
 */
if (!function_exists('ybt_memory')) {
    function ybt_memory() {
        return YBT_Memory_Manager::getInstance();
    }
}

// Auto-initialize
ybt_memory();
