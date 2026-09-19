<?php
/**
 * YBT Dashboard Stats AJAX Handler
 * Optimized endpoint for fetching dashboard statistics
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get dashboard stats for admin
 */
function ybt_get_dashboard_stats_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Force refresh if requested
    $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';

    // Get cached stats (1 hour cache by default)
    $stats = ybt_get_dashboard_stats($force_refresh);

    if ($stats === false) {
        wp_send_json_error(array('message' => 'Failed to retrieve stats'));
    }

    wp_send_json_success(array(
        'stats' => $stats,
        'cached' => !$force_refresh,
        'timestamp' => current_time('mysql')
    ));
}
add_action('wp_ajax_ybt_get_dashboard_stats', 'ybt_get_dashboard_stats_handler');

/**
 * Get user-specific dashboard stats
 */
function ybt_get_user_dashboard_stats_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Login required'));
    }

    $user_id = get_current_user_id();
    $username = wp_get_current_user()->user_login;

    // Cache key for user stats
    $cache_key = 'ybt_user_stats_' . $user_id . '_' . wp_hash($username);

    global $wpdb;
    $stats = ybt_cache()->remember($cache_key, function() use ($wpdb, $user_id, $username) {
        return $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'not_received' THEN 1 ELSE 0 END) as not_received,
                SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received,
                SUM(CASE WHEN status = 'packing_in_progress' THEN 1 ELSE 0 END) as packing_in_progress,
                SUM(CASE WHEN status = 'packed' THEN 1 ELSE 0 END) as packed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM {$wpdb->prefix}ybt_tracking
            WHERE user_id = %d OR username = %s
        ", $user_id, $username), ARRAY_A);
    }, 300, 'user_dashboard');

    if ($stats === null) {
        $stats = array_fill_keys(['total', 'not_received', 'received', 'packing_in_progress', 'packed', 'completed'], 0);
    }

    wp_send_json_success(array(
        'stats' => $stats,
        'user_id' => $user_id,
        'cached' => true
    ));
}
add_action('wp_ajax_ybt_get_user_dashboard_stats', 'ybt_get_user_dashboard_stats_handler');

/**
 * Get recent activity (paginated)
 */
function ybt_get_recent_activity_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
    $limit = min($limit, 100); // Max 100 items

    global $wpdb;
    $activity = ybt_cache()->remember('ybt_admin_recent_activity_' . $limit, function() use ($wpdb, $limit) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, u.display_name as user_display_name
             FROM {$wpdb->prefix}ybt_tracking t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             ORDER BY t.date_added DESC
             LIMIT %d",
            $limit
        ));
    }, 300, 'dashboard');

    wp_send_json_success(array(
        'activity' => $activity,
        'count' => count($activity)
    ));
}
add_action('wp_ajax_ybt_get_recent_activity', 'ybt_get_recent_activity_handler');

/**
 * Quick stats for admin bar
 */
function ybt_get_quick_stats_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Very lightweight query - cached for 15 minutes
    $stats = ybt_cache()->remember('ybt_quick_stats', function() use ($wpdb) {
        $table = $wpdb->prefix . 'ybt_tracking';
        return $wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN DATE(date_added) = CURDATE() THEN 1 ELSE 0 END) as today
            FROM {$table}
        ", ARRAY_A);
    }, 900, 'stats');

    wp_send_json_success(array(
        'stats' => $stats,
        'cached' => true
    ));
}
add_action('wp_ajax_ybt_get_quick_stats', 'ybt_get_quick_stats_handler');
