<?php
/**
 * YBT Packing Request Assets
 * Handles enqueuing packing request functionality assets.
 * Loaded as part of the YBT Packing Service plugin.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue packing request assets
 */
add_action( 'wp_enqueue_scripts', 'ybt_enqueue_packing_request_assets' );
function ybt_enqueue_packing_request_assets() {
    // YBT_PLUGIN_URL is defined in the main plugin file (ybt-packer-service.php)
    $js_file_url = YBT_PLUGIN_URL . 'assets/packing-request.js';
    $full_js_path = YBT_PLUGIN_PATH . 'assets/packing-request.js';

    if ( file_exists( $full_js_path ) ) {
        wp_enqueue_script(
            'ybt-packing-request',
            $js_file_url,
            array( 'jquery' ),
            '1.0.0',
            true // Load in footer
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script( 'ybt-packing-request', 'ybt_packing_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ybt_packing_request' )
        ) );
    } else {
        error_log( 'Packing Request JS file not found at: ' . $full_js_path );
    }
}
