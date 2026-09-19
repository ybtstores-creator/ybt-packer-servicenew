<?php
/**
 * YBT Cache Management Class
 * Implements WordPress transient-based caching with support for object caching
 */
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_Cache {
    private static $instance = null;
    private $cache_prefix = 'ybt_v2_';
    private $default_expiration = 3600; // 1 hour
    private $group_versions = array();

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Nothing special in constructor
    }

    /**
     * Get group version for invalidation
     */
    private function get_group_version($group) {
        if (isset($this->group_versions[$group])) {
            return $this->group_versions[$group];
        }

        $version_key = $this->cache_prefix . 'gv_' . $group;
        $version = wp_using_ext_object_cache() ? wp_cache_get($version_key, 'ybt_versions') : get_transient($version_key);

        if ($version === false) {
            $version = time();
            // Use 0 expiration for persistent storage (until manually invalidated)
            if (wp_using_ext_object_cache()) {
                wp_cache_set($version_key, $version, 'ybt_versions', 0);
            } else {
                set_transient($version_key, $version, 0);
            }
        }

        $this->group_versions[$group] = $version;
        return $version;
    }

    /**
     * Flush a group by incrementing its version
     */
    public function flush_group($group) {
        $version_key = $this->cache_prefix . 'gv_' . $group;
        $new_version = time();

        // Store new version with 0 expiration (persistent)
        if (wp_using_ext_object_cache()) {
            wp_cache_set($version_key, $new_version, 'ybt_versions', 0);
        } else {
            set_transient($version_key, $new_version, 0);
        }

        $this->group_versions[$group] = $new_version;
        return true;
    }

    /**
     * Build cache key with group versioning
     */
    private function build_key($key, $group) {
        $version = $this->get_group_version($group);
        return $this->cache_prefix . $group . '_' . $version . '_' . wp_hash($key);
    }

    /**
     * Get cached data
     */
    public function get($key, $group = 'default') {
        $cache_key = $this->build_key($key, $group);

        if (wp_using_ext_object_cache()) {
            $value = wp_cache_get($cache_key, 'ybt');
            if ($value !== false) return $value;
        }

        return get_transient($cache_key);
    }

    /**
     * Set cached data
     */
    public function set($key, $value, $expiration = null, $group = 'default') {
        $cache_key = $this->build_key($key, $group);
        $expiration = $expiration ?? $this->default_expiration;

        if (wp_using_ext_object_cache()) {
            wp_cache_set($cache_key, $value, 'ybt', $expiration);
        }

        return set_transient($cache_key, $value, $expiration);
    }

    /**
     * Delete specific cache key
     */
    public function delete($key, $group = 'default') {
        $cache_key = $this->build_key($key, $group);

        if (wp_using_ext_object_cache()) {
            wp_cache_delete($cache_key, 'ybt');
        }

        return delete_transient($cache_key);
    }

    /**
     * Get or set pattern (Remember)
     */
    public function remember($key, $callback, $expiration = null, $group = 'default') {
        $value = $this->get($key, $group);
        if ($value !== false) {
            return $value;
        }

        $value = call_user_func($callback);
        if ($value !== null && $value !== false) {
            $this->set($key, $value, $expiration, $group);
        }

        return $value;
    }

    /**
     * Comprehensive invalidation
     */
    public function invalidate_tracking_cache() {
        $this->flush_group('dashboard');
        $this->flush_group('tracking_queries');
        $this->flush_group('stats');
    }
}

// Global helper
if (!function_exists('ybt_cache')) {
    function ybt_cache() {
        return YBT_Cache::getInstance();
    }
}

// Global Invalidation Hooks
add_action('ybt_tracking_created', array(ybt_cache(), 'invalidate_tracking_cache'));
add_action('ybt_tracking_updated', array(ybt_cache(), 'invalidate_tracking_cache'));
add_action('ybt_tracking_deleted', array(ybt_cache(), 'invalidate_tracking_cache'));
add_action('ybt_bulk_operation_complete', array(ybt_cache(), 'invalidate_tracking_cache'));
