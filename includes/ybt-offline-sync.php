<?php
/**
 * Plugin Name: YBT Offline Sync
 * Description: Adds offline sync functionality to YBT Packing Service mobile app
 * Version: 1.0
 * Author: YBT Solutions
 */

if (!defined('ABSPATH')) exit;

// Enqueue offline sync scripts on frontend
function ybt_offline_enqueue_scripts() {
    // Only load on frontend (not admin)
    if (is_admin()) return;

    // Register and enqueue offline sync scripts
    wp_register_script(
        'ybt-offline-sync',
        YBT_PLUGIN_URL . 'assets/js/offline-sync.js',
        array(),
        '1.0',
        true
    );

    wp_register_script(
        'ybt-offline-api',
        YBT_PLUGIN_URL . 'assets/js/offline-api-wrapper.js',
        array('ybt-offline-sync'),
        '1.0',
        true
    );

    // Localize script with API configuration
    wp_localize_script('ybt-offline-sync', 'ybtApiConfig', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ybt_nonce'),
        'api_url' => site_url('/wp-admin/admin-ajax.php'),
        'home_url' => home_url()
    ));
}
add_action('wp_enqueue_scripts', 'ybt_offline_enqueue_scripts');

// Add offline sync to mobile app pages
function ybt_add_offline_sync_to_app() {
    // Check if this is being accessed from mobile app (via user agent or specific param)
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $is_mobile_app = strpos($user_agent, 'YBT PACKER') !== false ||
                     strpos($user_agent, 'Caporndroid') !== false ||
                     isset($_GET['mobile_app']);

    if ($is_mobile_app) {
        wp_enqueue_script('ybt-offline-sync');
        wp_enqueue_script('ybt-offline-api');

        // Add mobile app meta tags
        add_action('wp_head', 'ybt_mobile_app_meta');
    }
}
add_action('wp', 'ybt_add_offline_sync_to_app');

function ybt_mobile_app_meta() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
    echo '<style>body{-webkit-overflow-scrolling:touch;}</style>' . "\n";
}

// Add query var to detect mobile app access
function ybt_add_mobile_app_query_var($vars) {
    $vars[] = 'mobile_app';
    return $vars;
}
add_filter('query_vars', 'ybt_add_mobile_app_query_var');
