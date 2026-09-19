<?php
/**
 * YBT Performance Optimizer
 * Handles object caching, query caching, lazy loading, and asset optimization
 *
 * @package YBT_Packer
 * @version 1.0
 */
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_Performance {
    private static $instance = null;
    private $defer_handles = array();
    private $async_handles = array();

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Define script handles for optimization
        $this->defer_handles = array('ybt-frontend', 'ybt-combined', 'ybt-bootstrap');
        $this->async_handles = array('ybt-admin-combined', 'ybt-bootstrap');

        // Initialize optimizations
        add_action('init', array($this, 'init_optimizations'), 1);
        add_action('wp_enqueue_scripts', array($this, 'preload_critical_assets'), 1);
        add_action('admin_enqueue_scripts', array($this, 'preload_critical_assets'), 1);
    }

    /**
     * Initialize performance optimizations
     */
    public function init_optimizations() {
        // Enable persistent object cache if available
        if (wp_using_ext_object_cache()) {
            add_filter('wp_cache_themes_persistently', '__return_true');
        }

        // Add GZIP compression support
        add_action('send_headers', array($this, 'enable_gzip_compression'), 1);

        // Add DNS prefetch for CDNs
        add_action('wp_head', array($this, 'add_dns_prefetch'), 1);
        add_action('admin_head', array($this, 'add_dns_prefetch'), 1);

        // Script optimization filters
        add_filter('script_loader_tag', array($this, 'optimize_script_tags'), 10, 3);
        add_filter('style_loader_tag', array($this, 'optimize_style_tags'), 10, 2);

        // Lazy load images
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_loading'), 10, 2);
        add_filter('the_content', array($this, 'lazy_load_content_images'), 999);

        // Optimize Gravatar
        add_filter('get_avatar', array($this, 'lazy_load_gravatar'), 10, 2);

        // Database query optimization
        add_action('wp_footer', array($this, 'log_query_stats'), 999);
    }

    /**
     * Enable GZIP compression for output
     */
    public function enable_gzip_compression() {
        if (!headers_sent() && ob_get_level() === 0) {
            // Check if browser supports GZIP
            if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) &&
                substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
                // Start GZIP output buffering
                add_action('wp_footer', array($this, 'start_gzip_buffer'), 0);
                add_action('admin_footer', array($this, 'start_gzip_buffer'), 0);
            }
        }
    }

    /**
     * Start GZIP output buffer
     */
    public function start_gzip_buffer() {
        if (!headers_sent() && ob_get_level() === 0) {
            ob_start('ob_gzhandler');
        }
    }

    /**
     * Add DNS prefetch for external resources
     */
    public function add_dns_prefetch() {
        $prefetch_urls = array(
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
        );

        foreach ($prefetch_urls as $url) {
            echo '<link rel="dns-prefetch" href="' . esc_url($url) . '">' . "\n";
        }

        // Preconnect for faster connections
        echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
    }

    /**
     * Preload critical CSS assets
     */
    /**
     * Preload critical CSS assets
     */
    public function preload_critical_assets() {
        // Avoid enqueuing duplicate ybt-preload-main when ybt-combined is used
        if ($this->is_ybt_page() && wp_style_is('ybt-combined', 'registered')) {
            wp_style_add_data('ybt-combined', 'preload', true);
        }
    }

    /**
     * Optimize script tags with defer/async
     */
    public function optimize_script_tags($tag, $handle, $src) {
        // Skip in admin unless specified
        if (is_admin() && !in_array($handle, $this->async_handles)) {
            return $tag;
        }

        // Add defer attribute for frontend scripts
        if (!is_admin() && in_array($handle, $this->defer_handles)) {
            if (strpos($tag, 'defer') === false) {
                $tag = str_replace(' src', ' defer="defer" src', $tag);
            }
        }

        // Add async attribute for admin scripts
        if (is_admin() && in_array($handle, $this->async_handles)) {
            if (strpos($tag, 'async') === false) {
                $tag = str_replace(' src', ' async="async" src', $tag);
            }
        }

        // Add crossorigin for CDN scripts
        if (strpos($src, 'cdn.jsdelivr.net') !== false || strpos($src, 'cdnjs.cloudflare.com') !== false) {
            if (strpos($tag, 'crossorigin') === false) {
                $tag = str_replace(' src', ' crossorigin="anonymous" src', $tag);
            }
        }

        return $tag;
    }

    /**
     * Optimize CSS loading
     */
    public function optimize_style_tags($tag, $handle) {
        // Add media print for non-critical CSS, then swap with JS
        $non_critical_handles = array('ybt-font-awesome');

        if (in_array($handle, $non_critical_handles) && strpos($tag, 'media=') === false) {
            $tag = str_replace("rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $tag);
        }

        return $tag;
    }

    /**
     * Add lazy loading to attachment images
     */
    public function add_lazy_loading($attr, $attachment) {
        // Skip for above-fold images (first 2 images)
        static $image_count = 0;
        $image_count++;

        if ($image_count <= 2) {
            // Eager load first images
            $attr['loading'] = 'eager';
            $attr['fetchpriority'] = 'high';
            $attr['decoding'] = 'async';
        } else {
            $attr['loading'] = 'lazy';
            $attr['decoding'] = 'async';
        }

        return $attr;
    }

    /**
     * Add lazy loading to content images while optimizing LCP logos
     */
    public function lazy_load_content_images($content) {
        if (is_admin()) {
            return $content;
        }

        // Add loading="lazy" to images without it, while prioritizing logo/hero images
        $content = preg_replace_callback(
            '/<img([^>]+)>/i',
            function($matches) {
                $img_tag = $matches[0];
                $attributes = $matches[1];

                // Check if this is a logo or hero image (above fold LCP element)
                $is_lcp = (strpos($attributes, 'logo') !== false || strpos($attributes, 'hero') !== false || strpos($attributes, 'fetchpriority') !== false);

                if ($is_lcp) {
                    // Remove any existing loading or decoding attributes to avoid duplicates
                    $clean_tag = preg_replace('/\s+(loading|decoding)=["\'][^"\']*["\']/i', '', $img_tag);
                    return str_replace('<img', '<img loading="eager" fetchpriority="high" decoding="async"', $clean_tag);
                }

                // Remove duplicate decoding attributes if present
                if (substr_count($attributes, 'decoding=') > 1) {
                    $img_tag = preg_replace('/\s+decoding=["\'][^"\']*["\']/', '', $img_tag, substr_count($attributes, 'decoding=') - 1);
                }

                if (strpos($attributes, 'loading=') === false) {
                    return str_replace('<img', '<img loading="lazy" decoding="async"', $img_tag);
                }

                return $img_tag;
            },
            $content
        );

        return $content;
    }

    /**
     * Lazy load Gravatar images
     */
    public function lazy_load_gravatar($avatar) {
        if (strpos($avatar, 'gravatar') !== false && strpos($avatar, 'loading=') === false) {
            $avatar = str_replace('<img', '<img loading="lazy" decoding="async"', $avatar);
        }
        return $avatar;
    }

    /**
     * Log database query statistics for debugging
     */
    public function log_query_stats() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        global $wpdb;

        if ($wpdb->num_queries > 50) {
            error_log(sprintf(
                '[YBT Performance] High query count: %d queries on %s',
                $wpdb->num_queries,
                $_SERVER['REQUEST_URI'] ?? 'Unknown'
            ));
        }

        // Log slow queries if query log exists
        if (!empty($wpdb->queries)) {
            foreach ($wpdb->queries as $query_data) {
                if (isset($query_data[1]) && $query_data[1] > 1.0) { // Queries taking > 1 second
                    error_log(sprintf(
                        '[YBT Performance] Slow query (%.3fs): %s',
                        $query_data[1],
                        substr($query_data[0], 0, 200)
                    ));
                }
            }
        }
    }

    /**
     * Check if current page is a YBT page
     */
    private function is_ybt_page() {
        if (is_admin()) {
            global $pagenow;
            return isset($_GET['page']) && strpos($_GET['page'], 'ybt-') !== false;
        }

        return is_page(array('dashboard', 'add-tracking', 'received', 'packed', 'completed', 'profile', 'login', 'register'));
    }

    /**
     * Preload user data for faster access
     */
    public function preload_user_data($user_id) {
        if (!$user_id) {
            return;
        }

        // Cache user meta that's frequently accessed
        $user_meta_keys = array('ybt_custom_user_id', 'ybt_mobile_number');

        foreach ($user_meta_keys as $meta_key) {
            $value = get_user_meta($user_id, $meta_key, true);
            // WordPress already caches this internally
        }
    }

    /**
     * Batch get user meta for multiple users (reduces queries)
     */
    public function batch_get_user_meta($user_ids, $meta_key) {
        global $wpdb;

        if (empty($user_ids)) {
            return array();
        }

        $user_ids = array_map('intval', $user_ids);
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

        $cache_key = 'ybt_batch_meta_' . wp_hash($meta_key . '_' . implode('_', $user_ids));

        return ybt_cache()->remember($cache_key, function() use ($wpdb, $user_ids, $meta_key, $placeholders) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta}
                 WHERE user_id IN ($placeholders) AND meta_key = %s",
                array_merge($user_ids, array($meta_key))
            ), OBJECT_K);

            $meta = array();
            foreach ($results as $user_id => $data) {
                $meta[$user_id] = $data->meta_value;
            }

            return $meta;
        }, 3600, 'user_meta');
    }
}

/**
 * Global helper for performance optimizer
 */
if (!function_exists('ybt_performance')) {
    function ybt_performance() {
        return YBT_Performance::getInstance();
    }
}

// Auto-initialize
ybt_performance();
