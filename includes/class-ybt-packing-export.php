<?php
/**
 * YBT Packing Request Export Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class YBT_Packing_Export {

    public static function init() {
        add_action('wp_ajax_ybt_download_user_packing_requests', array(__CLASS__, 'handle_download'));
    }

    public static function handle_download() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ybt_admin_nonce')) {
            wp_die('Invalid security token');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $username = sanitize_text_field($_GET['user'] ?? '');
        $method = sanitize_text_field($_GET['method'] ?? 'air');

        if (empty($username)) {
            wp_die('User parameter is required');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ybt_tracking';

        // Get items for this user and method that have been REQUESTED for packing
        // We only include items where date_packing_requested IS NOT NULL
        // We Use LOWER() to ensure case-insensitive matching for method
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE username = %s AND LOWER(shipping_method) = %s AND status IN ('received', 'packing_in_progress') AND date_packing_requested IS NOT NULL AND date_packing_requested != '' ORDER BY date_packing_requested DESC",
            $username, strtolower($method)
        ));

        if (empty($items)) {
            wp_die(sprintf(
                'No %s packing requests found for user "%s". They may have already been marked as packed or completed.',
                esc_html(strtoupper($method)),
                esc_html($username)
            ));
        }

        // Get user details
        $user = get_user_by('login', $username);
        if (!$user) {
            // Try searching by custom username meta if not found by login
            $user_query = new WP_User_Query(array(
                'meta_key' => 'ybt_custom_user_id',
                'meta_value' => $username,
                'number' => 1
            ));
            $users = $user_query->get_results();
            if (!empty($users)) {
                $user = $users[0];
            }
        }

        $user_email = $user ? $user->user_email : 'N/A';
        
        // Use the new shipping fields if available, fallback to others
        $user_phone = $user ? (get_user_meta($user->ID, 'ybt_shipping_phone', true) ?: get_user_meta($user->ID, 'ybt_mobile_number', true) ?: get_user_meta($user->ID, 'phone', true) ?: 'N/A') : 'N/A';
        $user_mark = $user ? (get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: get_user_meta($user->ID, 'user_mark', true) ?: 'N/A') : 'N/A';
        $user_address = $user ? get_user_meta($user->ID, 'ybt_shipping_address', true) : '';

        // Clean any previous output to ensure clean HTML/PDF generation
        if (ob_get_length()) {
            ob_clean();
        }

        include YBT_PLUGIN_PATH . 'templates/admin-packing-print.php';
        exit;
    }
}

YBT_Packing_Export::init();
