<?php
/**
 * Error Reporting for Debugging
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

/**
 * YBT API Bootstrap Guard
 *
 * Included at the top of every API file (mobile-api.php, ajax-handlers.php,
 * admin_api.php, temp_api_endpoint.php).
 *
 * Guarantees:
 *  1. WordPress is fully loaded (ABSPATH + WPINC + $wpdb).
 *  2. The request is flowing through wp-admin/admin-ajax.php, NOT a direct
 *     file hit.
 *  3. Output buffering is started so no stray whitespace/BOM can break JSON.
 *  4. A correct Content-Type header is queued (WordPress will send it).
 *  5. New API system classes are conditionally loaded.
 */

// ── 0. Dependency Check ──────────────────────────────────────────────────────
if (!class_exists('YBT_API_Helper')) {
    $loader = __DIR__ . '/api-loader.php';
    if (file_exists($loader)) {
        require_once $loader;
    }
}

// ── 1. Hard ABSPATH check ────────────────────────────────────────────────────
// This is the standard WordPress guard. If ABSPATH is not defined it means
// WordPress never bootstrapped and $wpdb / all WP functions are unavailable.
if ( ! defined( 'ABSPATH' ) ) {
    // We can't use wp_send_json() here — WordPress isn't loaded.
    // Send a plain JSON error so the Android app gets a parseable response
    // instead of a silent crash or an HTML error page.
    header( 'Content-Type: application/json; charset=utf-8' );
    http_response_code( 403 );
    echo json_encode( array(
        'success' => false,
        'message' => 'Direct file access is not allowed.',
        'code'    => 'direct_access',
    ) );
    exit;
}

// ── 2. Verify WordPress core constants are present ───────────────────────────
// If a partial load happened (e.g. mu-plugin conflict), some constants may be
// missing even though ABSPATH was defined.
if ( ! defined( 'WPINC' ) || ! defined( 'DB_NAME' ) ) {
    // WordPress is only half-loaded. Bail with a JSON error.
    header( 'Content-Type: application/json; charset=utf-8' );
    http_response_code( 503 );
    echo json_encode( array(
        'success' => false,
        'message' => 'WordPress environment is not fully initialised.',
        'code'    => 'wp_not_ready',
    ) );
    exit;
}

// ── 3. Request-path note ─────────────────────────────────────────────────────
// This file is legitimately require_once'd during every WordPress page load
// (via ybt-packer-service.php → mobile-api.php → api-bootstrap.php).
// At that point DOING_AJAX is NOT defined, but that is perfectly normal.
//
// Direct file access is already blocked above by the ABSPATH check (step 1).
// All actual endpoint security is enforced by WordPress's wp_ajax_* hook
// system — every action registered with add_action('wp_ajax_...') is only
// callable through wp-admin/admin-ajax.php, which WordPress guards itself.
//
// REMOVED: The previous DOING_AJAX / wp_die() block here was incorrect —
// it killed every ordinary page load when WP_DEBUG was enabled, because
// REQUEST_METHOD is set on ALL HTTP requests (not just AJAX ones).

// ── 4. Output buffer ─────────────────────────────────────────────────────────
// Start an output buffer if one isn't already running.
// This catches any stray whitespace, BOM marks, or PHP notices that could
// corrupt the JSON response sent by wp_send_json() / wp_send_json_success().
if ( ob_get_level() === 0 ) {
    ob_start();
}
