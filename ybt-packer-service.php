<?php
/**
 * Plugin Name: YBT Packing Service
 * Plugin URI: https://yourcompany.com/ybt-packer-service
 * Description: Professional freight packaging and tracking management system with advanced analytics and automation.
 * Version: 3.2
 * Author: YBT Solutions
 * Author URI: https://yourcompany.com
 * Text Domain: ybt-packer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'YBT_VERSION', '3.3' );
define( 'YBT_PLUGIN_FILE', __FILE__ );
define( 'YBT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'YBT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YBT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'YBT_MIN_PHP_VERSION', '7.4' );
define( 'YBT_MIN_WP_VERSION', '5.0' );
define( 'YBT_DEBUG', defined('WP_DEBUG') && WP_DEBUG );

// =============================================================================
// 1. SYSTEM REQUIREMENTS CHECK
// =============================================================================
function ybt_check_requirements() {
	if ( version_compare( PHP_VERSION, YBT_MIN_PHP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'ybt_php_version_notice' );
		return false;
	}

	if ( version_compare( get_bloginfo( 'version' ), YBT_MIN_WP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'ybt_wp_version_notice' );
		return false;
	}

	return true;
}

function ybt_php_version_notice() {
	echo '<div class="notice notice-error is-dismissible"><p><strong>YBT Packing Service:</strong> Requires PHP ' . esc_html(YBT_MIN_PHP_VERSION) . ' or higher. Current version: ' . esc_html(PHP_VERSION) . '</p></div>';
}

function ybt_wp_version_notice() {
	echo '<div class="notice notice-error is-dismissible"><p><strong>YBT Packing Service:</strong> Requires WordPress ' . esc_html(YBT_MIN_WP_VERSION) . ' or higher.</p></div>';
}

// System Requirements Check - stop loading if requirements not met
if ( ! ybt_check_requirements() ) {
	return;
}

// =============================================================================
// 2. LOAD MINIMUM REQUIRED FILES FIRST (critical for activation)
// =============================================================================
$minimum_files = [
	'includes/class-ybt-core.php',
	'includes/class-ybt-database.php',
	'includes/class-ybt-cache.php',
	'includes/ajax-helpers.php',
];

foreach ( $minimum_files as $file ) {
	$file_path = YBT_PLUGIN_PATH . $file;
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

// =============================================================================
// ACTIVATION HOOK - Create pages, database tables, security keys
// =============================================================================
require_once YBT_PLUGIN_PATH . 'includes/class-ybt-activator.php';
register_activation_hook( __FILE__, array( 'YBT_Activator', 'activate' ) );

// =============================================================================
// 3. LOAD ADDITIONAL FILES (after minimum loaded)
// =============================================================================
$additional_files = [
	'includes/class-ybt-config.php',
	'includes/class-ybt-smart-features.php',
	'includes/class-ybt-admin.php',

	'includes/class-ybt-db-helper.php',
	'includes/class-ybt-memory-manager.php',
	'includes/class-ybt-profiler.php',
	'includes/class-ybt-performance.php',
	'includes/class-ybt-security.php',
	'includes/class-ybt-validator.php',
	'includes/class-ybt-file-handler.php',
	'includes/class-ybt-file-validator.php',
	'includes/class-ybt-logger.php',
	'includes/class-ybt-tracking-events.php',
	'includes/class-ybt-tracking-audit.php',
	'includes/class-ybt-encryption.php',
	'includes/class-ybt-packing.php',
	'includes/class-ybt-packing-export.php',
	'includes/class-ybt-shortcodes.php',
	'includes/class-ybt-user-profile.php',
	'includes/class-ybt-notifications.php',
	'includes/notifications-web.php',
	'includes/notifications-announcements.php',
	'includes/authentication.php',
	'includes/tracking.php',
	'includes/file-upload.php',
	'includes/settings.php',
	'includes/export.php',
	'includes/bulk-operations.php',
	'includes/chat.php',
	'includes/mobile-auth.php',
	'includes/mobile-tracking.php',
	'includes/mobile-dashboard.php',
	'includes/mobile-crash-log.php',
	'includes/dashboard-stats.php',
	'includes/ybt-offline-sync.php',
	'includes/api-bootstrap.php',
	'includes/api-loader.php',
	'includes/rest-api.php',
	'includes/ybt-app-update-handlers.php',
	'includes/ybt-mobile-sync-handler.php',
	'includes/enqueue-scripts.php',
	'includes/class-ybt-invoice.php',
	'includes/class-ybt-automation.php',
	'includes/class-ybt-admin-menu-style.php',
	'includes/class-ybt-admin-shell.php',
    'includes/admin-settings-handlers.php',
    'includes/admin-rest-api.php',
    'includes/class-ybt-ai-operator.php',
];

foreach ( $additional_files as $file ) {
	$file_path = YBT_PLUGIN_PATH . $file;
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

// =============================================================================
// 4. INITIALIZE PLUGIN
// =============================================================================
function ybt_init_plugin() {
	// Initialize Core (handles init, admin_menu, assets, etc.)
	if ( class_exists( 'YBT_Core' ) ) {
		$core = YBT_Core::instance();
	}

	// Initialize Automation & Invoices
	if ( class_exists( 'YBT_Automation' ) ) {
		YBT_Automation::instance();
	}
	if ( class_exists( 'YBT_Invoice' ) ) {
		YBT_Invoice::instance();
	}

	// Initialize AI Operator (registers AJAX handlers for Data Management)
	if ( class_exists( 'YBT_AI_Operator' ) ) {
		YBT_AI_Operator::instance();
	}

	// Performance: Start profiling main init (only if function exists)
	if ( function_exists( 'ybt_profile_start' ) ) {
		ybt_profile_start( 'plugin_initialization' );
	}

	// Check for database updates (version bump, or tables added since this install was created)
	$stored_version = get_option( 'ybt_db_version', '0' );
	$missing_tables = false;
	if ( class_exists( 'YBT_Database' ) && function_exists( 'ybt_table_exists' ) ) {
		global $wpdb;
		$missing_tables = ! ybt_table_exists( $wpdb->prefix . 'ybt_login_attempts' )
			|| ! ybt_table_exists( $wpdb->prefix . 'ybt_security_log' );
	}
	if ( version_compare( $stored_version, YBT_VERSION, '<' ) || $missing_tables ) {
		$migrated = true;
		if ( class_exists( 'YBT_Database' ) ) {
			$migrated = YBT_Database::create_tables( true );
		}
		if ( $migrated !== false ) {
			update_option( 'ybt_db_version', YBT_VERSION );
		}
	}

	if ( function_exists( 'ybt_profile_stop' ) ) {
		ybt_profile_stop( 'plugin_initialization' );
	}
}
add_action( 'plugins_loaded', 'ybt_init_plugin' );

// Initialize Admin (must be loaded before admin_menu hook fires)
function ybt_init_admin() {
	if ( class_exists( 'YBT_Admin' ) ) {
		new YBT_Admin();
	}
}
add_action( 'plugins_loaded', 'ybt_init_admin', 20 );

// =============================================================================
// 5. SECURITY INITIALIZATION
// =============================================================================
function ybt_init_security() {
	// Rate limiting for AJAX requests
	add_action( 'wp_ajax_ybt_rate_limit_check', 'ybt_rate_limit_check' );
	add_action( 'wp_ajax_nopriv_ybt_rate_limit_check', 'ybt_rate_limit_check' );

	// Add plugin action links
	add_filter( 'plugin_action_links_' . YBT_PLUGIN_BASENAME, 'ybt_plugin_action_links' );
	
	// Add security headers
	add_action( 'send_headers', 'ybt_add_security_headers' );
}
add_action( 'init', 'ybt_init_security' );

// Security Headers (CORS + Security)
// FIXED: Implement proper origin validation instead of wildcard
function ybt_add_security_headers() {
	if ( headers_sent() ) {
		return;
	}
	
	// SECURITY FIX: Enforce HTTPS site-wide (excluding AJAX requests and optionally localhost)
	$enforce_https = get_option('ybt_enforce_https', '1') === '1';
	$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
	$is_local = in_array($remote_ip, array('127.0.0.1', '::1')) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
	$is_https = function_exists('ybt_is_https') ? ybt_is_https() : is_ssl();
	
	if ( $enforce_https && ! $is_https && ! defined('WP_CLI') && ! defined('DOING_AJAX') && !$is_local ) {
		header( 'Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301 );
		exit;
	}
	
	// Get the origin from the request
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
	
	// Whitelist of allowed origins (mobile apps and web frontend)
	$allowed_origins = array(
		get_option('siteurl'), // WordPress site URL
		home_url(), // Home URL
	);
	
	// Add custom allowed origins from settings if configured
	$custom_origins = get_option('ybt_allowed_cors_origins', '');
	if (!empty($custom_origins)) {
		$custom_origins_array = array_filter(array_map('trim', explode(',', $custom_origins)));
		$allowed_origins = array_merge($allowed_origins, $custom_origins_array);
	}
	
	// Check if origin is in whitelist
	if (!empty($origin) && in_array($origin, $allowed_origins, true)) {
		header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
		header('Access-Control-Allow-Credentials: true');
	} elseif (empty($origin)) {
		// Same-origin request - no CORS needed
		header('Access-Control-Allow-Origin: ' . esc_url_raw(get_option('siteurl')));
		header('Access-Control-Allow-Credentials: true');
	} else {
		// Origin not allowed - use same-origin
		header('Access-Control-Allow-Origin: ' . esc_url_raw(get_option('siteurl')));
		header('Access-Control-Allow-Credentials: true');
	}
	
	header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
	header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With, X-API-Key, X-YBT-Signature, X-YBT-Timestamp, X-YBT-Current-Version, X-YBT-Token');
	
	// Handle preflight OPTIONS request
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
		exit( 0 );
	}

	// Standard security headers
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'X-XSS-Protection: 1; mode=block' );
	
	// Content-Security-Policy (CSP) - Enhanced security
	// NOTE: 'unsafe-inline' is required for inline scripts/styles used in templates
	// and for Font Awesome icon fonts to display properly.
	$csp_directives = array(
		"default-src 'self' data: blob:",
		"script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
		"style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
		"font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
		"img-src 'self' data: blob: https:",
		"connect-src 'self' https:",
		"frame-ancestors 'self'",
		"base-uri 'self'",
		"form-action 'self'"
	);
	header('Content-Security-Policy: ' . implode('; ', $csp_directives));
}

// =============================================================================
// 6. RATE LIMITING FUNCTION
// =============================================================================
function ybt_rate_limit_check() {
	check_ajax_referer( 'ybt_nonce', 'nonce' );

	$action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : 'general';
	$max_attempts = isset( $_POST['max_attempts'] ) ? intval( $_POST['max_attempts'] ) : 5;
	$window_seconds = isset( $_POST['window'] ) ? intval( $_POST['window'] ) : 300; // 5 minutes

	// Use helper function if available, otherwise fallback
	if ( function_exists( 'ybt_check_rate_limit' ) ) {
		if ( ! ybt_check_rate_limit( $action, $max_attempts, $window_seconds ) ) {
			wp_send_json_error( array(
				'allowed' => false,
				'message' => 'Rate limit exceeded. Please try again later.',
			) );
			return;
		}
		wp_send_json_success( array( 'allowed' => true ) );
	} else {
		// Fallback inline implementation
		$ip = function_exists( 'ybt_get_client_ip' ) ? ybt_get_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0' );
		$transient_name = 'ybt_rl_' . $action . '_' . wp_hash( $ip );
		$attempts = get_transient( $transient_name );

		if ( false === $attempts ) {
			set_transient( $transient_name, 1, $window_seconds );
			wp_send_json_success( array( 'allowed' => true, 'attempts' => 1 ) );
			return;
		}

		if ( $attempts >= $max_attempts ) {
			wp_send_json_error( array(
				'allowed' => false,
				'message' => 'Rate limit exceeded. Please try again later.',
				'attempts' => $attempts,
			) );
			return;
		}

		set_transient( $transient_name, $attempts + 1, $window_seconds );
		wp_send_json_success( array( 'allowed' => true, 'attempts' => $attempts + 1 ) );
	}
}

// =============================================================================
// 7. PLUGIN ACTION LINKS
// =============================================================================
function ybt_plugin_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=ybt-packer' ) . '">' . __( 'Dashboard', 'ybt-packer' ) . '</a>',
		'<a href="' . admin_url( 'admin.php?page=ybt-settings' ) . '">' . __( 'Settings', 'ybt-packer' ) . '</a>',
		'<a href="https://yourcompany.com/docs/ybt-packer" target="_blank">' . __( 'Documentation', 'ybt-packer' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}

// =============================================================================
// 8. GET CLIENT IP
// NOTE: ybt_get_client_ip() is defined in includes/ajax-helpers.php (loaded via
// $minimum_files above). That version only trusts REMOTE_ADDR to prevent IP
// spoofing. This block is intentionally empty to avoid duplicate definitions.
// =============================================================================

// =============================================================================
// 9. HELPER: ICON ENQUEUE (For third-party integrations if needed)
// =============================================================================
function ybt_enqueue_icons() {
	wp_enqueue_style( 'font-awesome', YBT_PLUGIN_URL . 'assets/vendor/font-awesome.min.css', array(), '6.5.0' );
}

// =============================================================================
// 10. OPTIMIZED DATA RETRIEVAL - Dashboard Stats
// FIXED: Removed %i placeholder for WordPress 5.0 compatibility
// =============================================================================
function ybt_get_dashboard_stats( $force_refresh = false ) {
	if ( $force_refresh ) {
		if ( function_exists( 'ybt_cache' ) ) {
			ybt_cache()->invalidate_tracking_cache();
		}
	}

	if ( ! function_exists( 'ybt_cache' ) ) {
		return false;
	}

	return ybt_cache()->remember( 'dashboard_main_stats', function() {
		global $wpdb;
		$table = $wpdb->prefix . 'ybt_tracking';

		// Check table exists using helper if available
		if ( function_exists( 'ybt_table_exists' ) ) {
			if ( ! ybt_table_exists( $table ) ) {
				return false;
			}
		} else {
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
				return false;
			}
		}

		// FIXED: Direct table concatenation instead of %i placeholder (WP 5.0 compatible)
		$results = $wpdb->get_row( "
			SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'not_received' THEN 1 ELSE 0 END) as not_received,
				SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received,
				SUM(CASE WHEN status = 'packing_in_progress' THEN 1 ELSE 0 END) as packing_in_progress,
				SUM(CASE WHEN status = 'packed' THEN 1 ELSE 0 END) as packed,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN DATE(date_added) = CURDATE() THEN 1 ELSE 0 END) as today_added,
				SUM(CASE WHEN status = 'completed' AND date_completed >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_completed
			FROM {$table}
		", ARRAY_A );

		if ( ! $results ) {
			return false;
		}

		foreach ( $results as $key => $val ) {
			$results[ $key ] = (int) $val;
		}

		return $results;
	}, 3600, 'dashboard' );
}

// =============================================================================
// 11. CACHE INVALIDATION STRATEGY
// =============================================================================
function ybt_clear_dashboard_cache() {
	if ( function_exists( 'ybt_cache' ) ) {
		ybt_cache()->invalidate_tracking_cache();
	}
}

// =============================================================================
// 12. ASSET OPTIMIZATION HELPER FUNCTIONS
// FIXED: Check plugin basename in activation hook
// =============================================================================
function ybt_optimize_logo_image( $plugin = '', $network_wide = false ) {
	// Only run optimization for THIS plugin
	if ( $plugin && $plugin !== YBT_PLUGIN_BASENAME ) {
		return;
	}

	$logo_path = YBT_PLUGIN_PATH . 'assets/ybt-logo-main.png';
	$optimized_path = YBT_PLUGIN_PATH . 'assets/ybt-logo-main-optimized.webp';

	if ( file_exists( $logo_path ) && ! file_exists( $optimized_path ) ) {
		if ( function_exists( 'imagewebp' ) && function_exists( 'imagecreatefrompng' ) ) {
			$image = @imagecreatefrompng( $logo_path );
			if ( $image ) {
				// Enable transparency for WebP
				imagepalettetotruecolor( $image );
				imagealphablending( $image, true );
				imagesavealpha( $image, true );

				imagewebp( $image, $optimized_path, 80 );
				imagedestroy( $image );
			}
		}
	}
}

/**
 * Get the optimized logo URL if available
 */
function ybt_get_logo_url() {
	$optimized_rel = 'assets/ybt-logo-main-optimized.webp';
	$default_rel = 'assets/ybt-logo-main.png';

	if ( file_exists( YBT_PLUGIN_PATH . $optimized_rel ) ) {
		return YBT_PLUGIN_URL . $optimized_rel;
	}

	return YBT_PLUGIN_URL . $default_rel;
}

// Run optimization on plugin activation (with proper hook signature)
add_action( 'activated_plugin', 'ybt_optimize_logo_image', 10, 2 );

// =============================================================================
// 13. ERROR LOGGING
// =============================================================================
if ( ! function_exists( 'ybt_log_error' ) ) {
	function ybt_log_error( $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'YBT Packing Service: ' . $message . ' Context: ' . wp_json_encode( $context ) );
		}
	}
}

// =============================================================================
// 14. SECURITY LOGGING
// =============================================================================
if ( ! function_exists( 'ybt_log_security_event' ) ) {
	function ybt_log_security_event( $event, $details = array() ) {
		// SECURITY FIX: Always log security events regardless of WP_DEBUG
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'event'     => $event,
			'ip'        => function_exists( 'ybt_get_client_ip' ) ? ybt_get_client_ip() : 'unknown',
			'user_agent'=> isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : 'unknown',
			'details'   => $details
		);

		$log_dir = WP_CONTENT_DIR . '/ybt-logs';
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			file_put_contents( $log_dir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Deny,Allow\n    Deny from all\n</IfModule>\n" );
			file_put_contents( $log_dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		$log_file = $log_dir . '/ybt-security.log';
		$log_line = date( 'Y-m-d H:i:s' ) . ' - ' . $event . ' - ' . json_encode( $log_entry ) . "\n";
		
		if ( is_writable( dirname( $log_file ) ) ) {
			file_put_contents( $log_file, $log_line, FILE_APPEND | LOCK_EX );
		}
	}
}

// =============================================================================
// 15. PLUGIN UPDATE CHECK
// FIXED: Properly integrate with WordPress update system
// =============================================================================
function ybt_check_for_updates( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$current_version = YBT_VERSION;
	$plugin_slug = YBT_PLUGIN_BASENAME;
	$update_url = 'https://yourcompany.com/api/plugin-version?plugin=ybt-packer-service';

	$response = wp_remote_get( $update_url, array(
		'timeout' => 5,
		'headers' => array(
			'X-YBT-Current-Version' => $current_version
		)
	));

	if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( $data && isset( $data->new_version ) && version_compare( $current_version, $data->new_version, '<' ) ) {
			$plugin_info = new stdClass();
			$plugin_info->slug = dirname( $plugin_slug );
			$plugin_info->plugin = $plugin_slug;
			$plugin_info->new_version = $data->new_version;
			$plugin_info->url = isset( $data->url ) ? $data->url : 'https://yourcompany.com/ybt-packer-service';
			$plugin_info->package = isset( $data->package ) ? $data->package : '';
			$plugin_info->tested = isset( $data->tested ) ? $data->tested : YBT_MIN_WP_VERSION;
			$plugin_info->requires_php = isset( $data->requires_php ) ? $data->requires_php : YBT_MIN_PHP_VERSION;

			$transient->response[ $plugin_slug ] = $plugin_info;
		}
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'ybt_check_for_updates' );

// Async update check for admin (non-blocking)
function ybt_check_for_updates_async() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$current_version = YBT_VERSION;
	$update_url = 'https://yourcompany.com/api/plugin-version?plugin=ybt-packer-service';
	
	// Cache check for 12 hours
	$last_check = get_option( 'ybt_last_update_check', 0 );
	$cache_time = 12 * HOUR_IN_SECONDS;
	
	if ( time() - $last_check < $cache_time ) {
		return;
	}

	// Check for updates asynchronously to avoid slowing down admin
	wp_remote_get( $update_url, array(
		'timeout' => 5,
		'blocking' => false,
		'headers' => array(
			'X-YBT-Current-Version' => $current_version
		)
	));
	
	update_option( 'ybt_last_update_check', time() );
}
add_action( 'admin_init', 'ybt_check_for_updates_async' );

// =============================================================================
// 16. FILE INTEGRITY CHECK
// =============================================================================
function ybt_check_file_integrity() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	global $missing_files_list;
	$missing_files_list = '';
	
	$critical_files = array(
		'includes/class-ybt-core.php',
		'includes/class-ybt-database.php',
		'includes/ajax-helpers.php',
	);
	
	$missing = array();
	foreach ( $critical_files as $file ) {
		if ( ! file_exists( YBT_PLUGIN_PATH . $file ) ) {
			$missing[] = $file;
		}
	}
	
	if ( ! empty( $missing ) ) {
		$missing_files_list = implode( ', ', $missing );
		add_action( 'admin_notices', 'ybt_missing_files_notice' );
		// Only deactivate if not already being deactivated
		if ( ! defined( 'WP_CLI' ) ) {
			deactivate_plugins( YBT_PLUGIN_BASENAME );
		}
	}
}
add_action( 'admin_init', 'ybt_check_file_integrity' );

function ybt_missing_files_notice() {
	global $missing_files_list;
	echo '<div class="notice notice-error"><p><strong>YBT Packing Service:</strong> Critical files missing: ' . esc_html( $missing_files_list ) . '. Plugin has been deactivated.</p></div>';
}

// =============================================================================
// 17. DATABASE HEALTH CHECK (OPTIMIZED & HOOKED)
// =============================================================================
function ybt_check_database_health() {
	global $wpdb;
	$required_tables = array(
		'ybt_tracking', 'ybt_chat', 'ybt_activity_log', 'ybt_cartons',
		'ybt_payment_settings', 'ybt_warehouse_settings'
	);

	$healthy = true;
	foreach ( $required_tables as $table ) {
		$table_name = $wpdb->prefix . $table;
		
		// Use helper if available
		if ( function_exists( 'ybt_table_exists' ) ) {
			if ( ! ybt_table_exists( $table_name ) ) {
				$healthy = false;
				break;
			}
		} else {
			// Fallback direct query
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
				$healthy = false;
				break;
			}
		}
	}

	update_option( 'ybt_db_healthy', $healthy ? '1' : '0' );
	update_option( 'ybt_last_db_health_check', time() );

	return $healthy;
}
// FIXED: Actually hook the database health check so it runs
add_action( 'admin_init', 'ybt_check_database_health' );

// =============================================================================
// 18. PLUGIN LOADED HOOK (Fire after everything is initialized)
// FIXED: Hook to plugins_loaded instead of firing at file include time.
// The previous do_action() ran during require_once, before WordPress hooks
// had fired, meaning listeners executed before plugins_loaded with an
// incomplete environment.
// =============================================================================
add_action( 'plugins_loaded', function() {
	do_action( 'ybt_packer_loaded' );
}, 99 );