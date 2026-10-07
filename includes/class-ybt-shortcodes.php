<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class YBT_Shortcodes {

    public static function init() {
        // 1. Authentication
        add_shortcode( 'ybt_login', array( __CLASS__, 'login' ) );
        add_shortcode( 'ybt_register', array( __CLASS__, 'register' ) );
        add_shortcode( 'ybt_reset_password', array( __CLASS__, 'reset_password' ) );

        // 2. Dashboard & Tracking
        add_shortcode( 'ybt_dashboard', array( __CLASS__, 'dashboard' ) );
        add_shortcode( 'ybt_add_tracking', array( __CLASS__, 'add_tracking' ) );

        // 3. Status Pages
        add_shortcode( 'ybt_not_received', array( __CLASS__, 'not_received' ) );
        add_shortcode( 'ybt_received', array( __CLASS__, 'received' ) );
        add_shortcode( 'ybt_packing_in_progress', array( __CLASS__, 'packing_in_progress' ) );
        add_shortcode( 'ybt_packed', array( __CLASS__, 'packed' ) );
        add_shortcode( 'ybt_completed', array( __CLASS__, 'completed' ) );

        // 4. Features
        add_shortcode( 'ybt_anonymous', array( __CLASS__, 'anonymous' ) );
        add_shortcode( 'ybt_profile', array( __CLASS__, 'profile' ) );
        add_shortcode( 'ybt_support', array( __CLASS__, 'support' ) );
        add_shortcode( 'ybt_app', array( __CLASS__, 'app_download' ) );

        // 5. Homepage
        add_shortcode( 'ybt_home', array( __CLASS__, 'home' ) );

        // 5. Assets
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );

        // 6. Pagination Support for Shortcode Pages
        add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
    }

    /**
     * Register 'paged' query var for pagination
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'paged';
        return $vars;
    }

    /**
     * Add rewrite rules for pagination on shortcode pages
     */
    public static function add_rewrite_rules() {
        // Add rewrite rules for paginated shortcode pages
        $pages = array(
            'received' => 'user-received.php',
            'packed' => 'user-packed.php',
            'completed' => 'user-completed.php',
            'not-received' => 'user-not-received.php',
            'packing-in-progress' => 'user-packing-in-progress.php'
        );

        foreach ($pages as $page_slug => $template_name) {
            add_rewrite_rule(
                '^' . $page_slug . '/page/([0-9]+)/?$',
                'index.php?pagename=' . $page_slug . '&paged=$matches[1]',
                'top'
            );
        }
    }

    // --- Render Callback Functions ---

    public static function login() {
        return self::load_template('user-login.php');
    }

    public static function register() {
        return self::load_template('user-register.php');
    }

    public static function reset_password() {
        return self::load_template('user-reset-password.php');
    }

    // NOTE: Authentication redirects for protected YBT pages are handled by
    // YBT_Core::handle_authentication_redirects() via the 'template_redirect' hook
    // (priority 5). Shortcode callbacks run later during content rendering, so
    // redirecting here would cause 'headers already sent' issues. The redirect
    // logic was removed from these methods to avoid redundancy and potential bugs.

    public static function dashboard() {
        return self::load_template('user-dashboard.php');
    }

    public static function add_tracking() {
        return self::load_template('user-add-tracking.php');
    }

    public static function not_received() {
        return self::load_template('user-not-received.php');
    }

    public static function received() {
        return self::load_template('user-received.php');
    }

    public static function packing_in_progress() {
        return self::load_template('user-packing-in-progress.php');
    }

    public static function packed() {
        return self::load_template('user-packed.php');
    }

    public static function completed() {
        return self::load_template('user-completed.php');
    }

    public static function anonymous() {
        return self::load_template('user-anonymous.php');
    }

    public static function profile() {
        return self::load_template('user-profile.php');
    }

    public static function support() {
        return self::load_template('user-chat.php');
    }

    /**
     * Enqueue Frontend Assets
     */
    public static function enqueue_frontend_assets() {
        // Simple page check
        $is_ybt_page = is_page(array('dashboard', 'add-tracking', 'not-received', 'received', 'packed', 'completed', 'profile', 'support', 'login', 'register', 'packing-in-progress', 'anonymous', 'home', 'app-download'));

        if (!$is_ybt_page) {
            return;
        }

        // Dequeue conflicting/duplicate theme Bootstrap handles to avoid duplicate CSS & version mismatch
        wp_dequeue_style('bootstrap');
        wp_dequeue_style('bootstrap-css');
        wp_dequeue_script('bootstrap');
        wp_dequeue_script('bootstrap-js');
        wp_deregister_style('bootstrap-css');
        wp_deregister_script('bootstrap-js');

        // 1. External Assets - Unified Bootstrap 5.3.2
        wp_enqueue_style('ybt-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', array(), '5.3.2');
        wp_enqueue_script('ybt-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.2', true);
        wp_enqueue_style('ybt-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css', array(), '6.4.2');
        wp_enqueue_style('ybt-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', array(), null);

        // 2. Core Plugin Assets
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_enqueue_style('ybt-style', YBT_PLUGIN_URL . 'assets/style.css', array(), YBT_VERSION);
            wp_enqueue_style('ybt-enhanced', YBT_PLUGIN_URL . 'assets/css/ybt-enhanced.css', array('ybt-style'), YBT_VERSION . '-flat-v4');
            wp_enqueue_script('ybt-frontend', YBT_PLUGIN_URL . 'assets/script.js', array('jquery'), YBT_VERSION, true);
        } else {
            // PRODUCTION: Use Combined & Minified Assets
            wp_enqueue_style('ybt-combined', YBT_PLUGIN_URL . 'assets/ybt-combined.min.css', array(), YBT_VERSION);
            wp_enqueue_script('ybt-combined', YBT_PLUGIN_URL . 'assets/ybt-combined.min.js', array('jquery'), YBT_VERSION, true);
        }

        // 3b. Consignment-note design system (tokens, atoms, and the legacy
        //     bridge for pages not yet migrated)
        wp_enqueue_style('ybt-consignment', YBT_PLUGIN_URL . 'assets/css/ybt-consignment.css', array('ybt-bootstrap'), YBT_VERSION . '-flat-v2');

        // 4. (user-dashboard-enhanced / user-dashboard-modern removed — fully
        //    superseded by the consignment-note system; all classes orphaned)

        // 4. Localization
        $handle = (defined('WP_DEBUG') && WP_DEBUG) ? 'ybt-frontend' : 'ybt-combined';
        wp_localize_script($handle, 'ybt_ajax', array(
            'ajax_url'          => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('ybt_nonce'),
            'add_tracking_nonce' => wp_create_nonce('ybt_user_add_tracking'),
            'claim_nonce'       => wp_create_nonce('ybt_claim_parcel'),
            'version'           => YBT_VERSION
        ));

        // 5. Notifications bell + parcel timeline (web). The script reads the
        //    ybt_ajax object localized above at load time, so it depends on
        //    $handle to keep the script-data printed before it executes.
        wp_enqueue_style('ybt-timeline', YBT_PLUGIN_URL . 'assets/css/ybt-timeline.css', array(), YBT_VERSION);
        wp_enqueue_script('ybt-timeline', YBT_PLUGIN_URL . 'assets/js/ybt-timeline.js', array('jquery', $handle), YBT_VERSION, true);
    }

    public static function app_download() {
        return self::load_template('app-download.php');
    }

    public static function home() {
        // Show the public homepage with service info, login/register buttons
        return self::load_template('user-home.php');
    }

    /**
     * Helper to load template cleanly
     */
    private static function load_template($filename) {
        ob_start();

        // Check if YBT_PLUGIN_PATH is defined
        if (!defined('YBT_PLUGIN_PATH')) {
            echo "<div class='alert alert-danger'>Error: YBT_PLUGIN_PATH constant is not defined.</div>";
            return ob_get_clean();
        }

        $path = YBT_PLUGIN_PATH . 'templates/' . $filename;

        if (file_exists($path)) {
            include $path;
        } else {
            // Only show detailed error to admins
            if (current_user_can('manage_options')) {
                echo "<div class='alert alert-danger'>Error: Template <strong>$filename</strong> not found in <code>" . esc_html($path) . "</code>.</div>";
            } else {
                echo "<div class='alert alert-info'>Content not available.</div>";
            }
        }
        return ob_get_clean();
    }

    /**
     * Optional: Method to flush rewrite rules (should be called from activation hook)
     */
    public static function flush_rewrite_rules() {
        flush_rewrite_rules(true);
    }
}
