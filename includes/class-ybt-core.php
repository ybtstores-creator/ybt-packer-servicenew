<?php
/**
 * YBT Packer Service Core Class - FIXED VERSION
 * Handles plugin initialization with proper architecture
 */
if (!defined('ABSPATH')) {
    exit;
}

class YBT_Core {
    private static $instance = null;
    private $services = array();
    private $version = '3.2';
    private $db_version = '2.2';

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get a service instance (Simple Dependency Injection)
     */
    public function get($service) {
        if (!isset($this->services[$service])) {
            switch ($service) {
                case 'db':
                    $this->services[$service] = new YBT_Database();
                    break;
                case 'cache':
                    $this->services[$service] = YBT_Cache::getInstance();
                    break;
                case 'profiler':
                    $this->services[$service] = YBT_Profiler::getInstance();
                    break;
                case 'memory':
                    $this->services[$service] = YBT_Memory_Manager::getInstance();
                    break;
            }
        }
        return $this->services[$service] ?? null;
    }

    private function __construct() {
        // Register hooks that don't require plugin file path
        add_action('init', array($this, 'init'));

        // Version-based upgrade handler (Run immediately)
        $this->check_version_upgrade();
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Load text domain early for translations
        load_plugin_textdomain('ybt-packer', false, dirname(YBT_PLUGIN_BASENAME) . '/languages');



        // Template redirects (authentication checks)
        add_action('template_redirect', array($this, 'handle_authentication_redirects'), 5);

        // Serve Digital Asset Links JSON for Android App Links domain verification
        // FIXED: call directly instead of add_action('init', ...) — registering an
        // 'init' hook from inside an 'init' callback never fires because WP's
        // do_action() iterates a snapshot of the callback list.
        $this->register_assetlinks_rewrite();
        add_filter('query_vars', array($this, 'register_assetlinks_query_vars'));
        // Priority 0 so the JSON is served before redirect_canonical (priority 10)
        // can 301 the request to a trailing-slash URL.
        add_action('template_redirect', array($this, 'render_assetlinks_json'), 0);

        // Hide admin bar on YBT pages
        add_action('wp', array($this, 'conditionally_hide_admin_bar'));

        // Shortcodes initialization
        if (class_exists('YBT_Shortcodes')) {
            YBT_Shortcodes::init();
        }

        // NOTE: ybt_init_security() is NOT called here because it is already hooked
        // on 'init' in the main plugin file (ybt-packer-service.php). Calling it again
        // here would register duplicate hooks: duplicate security headers, duplicate
        // rate-limit AJAX handlers, and duplicate plugin action links.

        // User management hooks
        add_action('user_register', array($this, 'assign_custom_user_id'));
        add_action('wp_logout', array($this, 'redirect_after_logout'));

        // Initialize User Profile management
        if (class_exists('YBT_User_Profile')) {
            YBT_User_Profile::instance();
        }

        // Activity logging
        add_action('ybt_tracking_updated', array($this, 'log_tracking_event'));
        add_action('ybt_tracking_created', array($this, 'log_tracking_event'));

        // Body class filter
        add_filter('body_class', array($this, 'add_body_classes'));

        // Global whitespace fix for YBT pages
        add_action('wp_head', array($this, 'inject_global_css_fix'), 100);

        // SEO meta tags for YBT pages
        add_action('wp_head', array($this, 'add_seo_meta_tags'), 1);

        // Clean invalid <p><div...> wrapping from shortcodes
        add_filter('the_content', array($this, 'clean_shortcode_paragraph_wrapper'), 11);
    }

    /**
     * Add SEO meta tags for YBT pages
     */
    public function add_seo_meta_tags() {
        if (!is_page() && !is_front_page()) {
            return;
        }

        $queried_object = get_queried_object();
        $page_slug = $queried_object->post_name ?? '';

        $descriptions = array(
            'home'                => 'YBT Packer Service - Fast, secure package receiving, consolidation, and international freight forwarding service.',
            'login'               => 'Login to your YBT Packer Service account to manage your received parcels, tracking, and packing requests.',
            'register'            => 'Create your free YBT Packer Service account to start consolidating and shipping parcels worldwide.',
            'dashboard'           => 'YBT Customer Dashboard - Track received parcels, view status updates, and manage forwarding requests.',
            'add-tracking'        => 'Add tracking numbers to notify YBT Packer warehouse of incoming parcels.',
            'received'            => 'View parcels received at the YBT warehouse and submit packing requests.',
            'not-received'        => 'View incoming parcels awaiting arrival at YBT Packer warehouse.',
            'packing-in-progress' => 'Track parcel consolidation and packing progress in real time.',
            'packed'              => 'View packed carton details, photos, weights, and payment instructions.',
            'completed'           => 'View completed and dispatched parcel shipments.',
            'profile'             => 'Manage your YBT Packer account profile and personal settings.',
            'support'             => 'Contact YBT Packer customer support team.',
            'anonymous'           => 'Claim unclaimed parcels received at the YBT warehouse.',
        );

        if (is_front_page() && (empty($page_slug) || !isset($descriptions[$page_slug]))) {
            $page_slug = 'home';
        }

        if (isset($descriptions[$page_slug])) {
            echo '<meta name="description" content="' . esc_attr($descriptions[$page_slug]) . '">' . "\n";
        }
    }

    /**
     * Clean invalid <p> tag wrappers around block <div class="ybt-..."> containers
     */
    public function clean_shortcode_paragraph_wrapper($content) {
        if (empty($content)) {
            return $content;
        }

        // Strip <p class="wp-block-paragraph">...<div class="ybt-...">...</div>...</p>
        $content = preg_replace('/<p[^>]*>\s*(<div[^>]*class=["\'][^"\']*ybt-[^"\']*["\'][^>]*>)/i', '$1', $content);
        $content = preg_replace('/(<\/div>)\s*<\/p>/i', '$1', $content);

        return $content;
    }

    /**
     * Inject global CSS fix for YBT pages to eliminate whitespace
     */
    public function inject_global_css_fix() {
        if (is_page()) {
            $queried_object = get_queried_object();
            if ($queried_object && isset($queried_object->post_name)) {
                $page_slug = $queried_object->post_name;
                $ybt_pages = array('dashboard', 'add-tracking', 'not-received', 'received', 'packing-in-progress', 'packed', 'completed', 'profile', 'support', 'anonymous');
                
                if (in_array($page_slug, $ybt_pages)) {
                    echo '<!-- YBT Whitespace Fix -->
                    <style>
                        html, body { 
                            margin-top: 0 !important; 
                            padding-top: 0 !important; 
                        }
                        #wpadminbar { display: none !important; }
                        body.admin-bar, body.logged-in { 
                            margin-top: 0 !important; 
                            padding-top: 0 !important; 
                        }
                        .ybt-navbar-modern {
                            top: 0 !important;
                        }
                    </style>';
                }
            }
        }
    }

    /**
     * Add custom classes to the body element for YBT pages
     */
    public function add_body_classes($classes) {
        if (is_page()) {
            $queried_object = get_queried_object();
            if ($queried_object && isset($queried_object->post_name)) {
                $page_slug = $queried_object->post_name;
                $ybt_pages = array('dashboard', 'add-tracking', 'not-received', 'received', 'packing-in-progress', 'packed', 'completed', 'profile', 'support', 'anonymous');
                
                if (in_array($page_slug, $ybt_pages)) {
                    $classes[] = 'ybt-page';
                    $classes[] = 'ybt-dashboard';
                    $classes[] = 'ybt-' . $page_slug;
                }
            }
        }
        return $classes;
    }

    /**
     * Activation hook - This should be called from the main plugin file
     * using: register_activation_hook(__FILE__, array('YBT_Core', 'activate'));
     */
    public static function activate() {
        // Create database tables
        if (class_exists('YBT_Database')) {
            YBT_Database::create_tables();
        }

        // Create required pages
        self::create_required_pages();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set current version for future upgrades
        update_option('ybt_plugin_version', '3.2'); // Use explicit version
        update_option('ybt_db_version', '2.2');

        // Show activation notice
        set_transient('ybt_activation_notice', true, 30);
    }

    /**
     * Version upgrade handler
     */
    public function check_version_upgrade() {
        $current_version = get_option('ybt_plugin_version', '1.0');
        $db_version = get_option('ybt_db_version', '1.0');

        if (version_compare($current_version, $this->version, '<')) {
            // Run version-specific upgrades
            $this->run_upgrades($current_version);
            update_option('ybt_plugin_version', $this->version);
        }

        if (version_compare($db_version, $this->db_version, '<')) {
            // Run database upgrades
            $this->run_db_upgrades($db_version);
            update_option('ybt_db_version', $this->db_version);
        }
    }

    /**
     * Run database upgrades
     */
    private function run_db_upgrades($from_version) {
        // For now, re-run table creation which handles schema updates via dbDelta
        if (class_exists('YBT_Database')) {
            YBT_Database::create_tables();
        }
    }

    /**
     * Run version-specific upgrades
     */
    private function run_upgrades($from_version) {
        if (version_compare($from_version, '3.0', '<')) {
            // Upgrade tasks for v3.0
            $this->migrate_legacy_data();
        }

        if (version_compare($from_version, '3.2', '<')) {
            // Upgrade tasks for v3.2
            $this->add_missing_indexes();
        }
    }

    /**
     * Placeholder for migrating legacy data
     */
    private function migrate_legacy_data() {
        // Implement data migration logic here if needed
    }

    /**
     * Placeholder for adding missing indexes
     */
    private function add_missing_indexes() {
        // Implement index addition logic here if needed
    }

    /**
     * Create required pages with duplicate prevention
     */
    private static function create_required_pages() {
        $pages = array(
            'dashboard' => array('title' => 'Dashboard', 'content' => '[ybt_dashboard]'),
            // Add other pages as needed
        );

        foreach ($pages as $slug => $page_data) {
            // Check by slug to prevent duplicates (get_page_by_path is not deprecated)
            $page_by_slug = get_page_by_path($slug);

            if (!$page_by_slug) {
                // Get safe author ID
                $author_id = get_current_user_id();
                if (!$author_id) {
                    $admins = get_users(array('role' => 'administrator', 'number' => 1));
                    $author_id = !empty($admins) ? $admins[0]->ID : 1;
                }

                $page_id = wp_insert_post(array(
                    'post_title'   => $page_data['title'],
                    'post_name'    => $slug,
                    'post_content' => $page_data['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_author'  => $author_id
                ));

                if (is_wp_error($page_id)) {
                    error_log('YBT: Failed to create page ' . $slug . ': ' . $page_id->get_error_message());
                }
            }
        }
    }



    /**
     * Handle authentication redirects securely
     */
    public function handle_authentication_redirects() {
        if (!is_page()) {
            return;
        }

        $queried_object = get_queried_object();
        if (!$queried_object || !isset($queried_object->post_name)) {
            return;
        }

        $page_slug = $queried_object->post_name;
        $ybt_pages = array('dashboard', 'add-tracking', 'not-received', 'received', 'packing-in-progress', 'packed', 'completed', 'profile', 'support', 'anonymous');
        $public_pages = array('login', 'register', 'anonymous', 'home');

        // Protected YBT page requiring login
        if (in_array($page_slug, $ybt_pages) && !is_user_logged_in()) {
            wp_safe_redirect(home_url('/login/?redirect_to=' . urlencode(home_url($_SERVER['REQUEST_URI']))));
            exit;
        }

        // Logged-in user trying to access login/register
        if (is_user_logged_in() && in_array($page_slug, array('login', 'register'))) {
            wp_safe_redirect(home_url('/dashboard/'));
            exit;
        }
    }

    /**
     * Conditionally hide admin bar on YBT pages
     */
    public function conditionally_hide_admin_bar() {
        if (is_user_logged_in() && is_page()) {
            $queried_object = get_queried_object();
            if ($queried_object && isset($queried_object->post_name)) {
                $page_slug = $queried_object->post_name;
                $ybt_pages = array('dashboard', 'add-tracking', 'not-received', 'received', 'packing-in-progress', 'packed', 'completed', 'profile', 'support', 'anonymous');
                if (in_array($page_slug, $ybt_pages)) {
                    show_admin_bar(false);
                }
            }
        }
    }

    /**
     * Assign custom user ID on registration
     */
    public function assign_custom_user_id($user_id) {
        $existing = get_user_meta($user_id, 'ybt_custom_user_id', true);
        if (!empty($existing) && $existing !== 'Pending') {
            return;
        }

        global $wpdb;
        $max_id = $wpdb->get_var("SELECT MAX(CAST(SUBSTRING(meta_value, 4) AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = 'ybt_custom_user_id'");
        $next_id = 'YBT' . str_pad(($max_id ? intval($max_id) + 1 : 1), 4, '0', STR_PAD_LEFT);
        update_user_meta($user_id, 'ybt_custom_user_id', $next_id);
    }

    /**
     * Redirect after logout
     */
    public function redirect_after_logout() {
        wp_redirect(home_url('/login/'));
        exit;
    }

    /**
     * Log tracking events
     */
    public function log_tracking_event($tracking_id = 0, $status = '') {
        $tracking_id = intval($tracking_id);
        if (!$tracking_id || !function_exists('ybt_log_activity')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ybt_tracking';
        $row = $wpdb->get_row($wpdb->prepare("SELECT user_id, tracking_number, status FROM {$table} WHERE id = %d", $tracking_id));
        if (!$row) {
            return;
        }

        $details = sprintf('Tracking #%d (%s)', $tracking_id, $row->tracking_number ?: 'n/a');
        if ($status !== '') {
            $details .= ' status: ' . sanitize_text_field($status);
        } elseif (!empty($row->status)) {
            $details .= ' status: ' . $row->status;
        }

        ybt_log_activity(intval($row->user_id), 'tracking_event', $details);
    }

    /**
     * Digital Asset Links Rewrite & Render
     */
    public function register_assetlinks_rewrite() {
        add_rewrite_rule('^\.well-known/assetlinks\.json$', 'index.php?ybt_assetlinks=1', 'top');
    }

    public function register_assetlinks_query_vars($vars) {
        $vars[] = 'ybt_assetlinks';
        return $vars;
    }

    public function render_assetlinks_json() {
        if (get_query_var('ybt_assetlinks')) {
            if (function_exists('ybt_clear_output_buffers')) {
                ybt_clear_output_buffers();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                [
                    "relation" => ["delegate_permission/common.handle_all_urls"],
                    "target" => [
                        "namespace" => "android_app",
                        "package_name" => "com.ybt.packer",
                        "sha256_cert_fingerprints" => [
                            "37:8F:FE:82:60:9A:6F:9E:C2:C9:06:BF:8A:FC:9E:40:5F:CC:85:BF:04:68:88:AB:BD:3F:EA:07:6E:B4:81:DE"
                        ]
                    ]
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// NOTE: Activation hook is registered in the main plugin file (ybt-packer-service.php)
// using YBT_Activator::activate(). The YBT_Core::activate() method is kept for
// backward compatibility but is NOT registered as an activation hook here to avoid
// duplicate activation (double page creation, double table creation).
// To use YBT_Core::activate() instead, update the main plugin file's register_activation_hook call.
