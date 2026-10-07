<?php
/**
 * Advanced Search AJAX endpoint (admin).
 *
 * Previously lived inline in templates/admin-advanced-search.php and was only
 * registered when the template rendered — which never happens during an
 * admin-ajax request, so the endpoint always 400'd. Now loaded with the admin
 * class and backed by a real query against the tracking table.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_ybt_advanced_search', 'ybt_handle_advanced_search');

function ybt_handle_advanced_search() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized.', 'ybt-packer'));
    }

    global $wpdb;

    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to   = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    $status    = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $username  = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
    $tracking  = isset($_POST['tracking']) ? sanitize_text_field($_POST['tracking']) : '';

    if (!empty($date_from) && !empty($date_to) && strtotime($date_from) > strtotime($date_to)) {
        wp_send_json_error(__('Invalid date range. "Date From" must be before "Date To".', 'ybt-packer'));
    }

    $table   = $wpdb->prefix . 'ybt_tracking';
    $where   = array('1=1');
    $params  = array();

    if ($date_from !== '') {
        $where[]  = 'date_added >= %s';
        $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '') {
        $where[]  = 'date_added <= %s';
        $params[] = $date_to . ' 23:59:59';
    }
    if ($status !== '') {
        $where[]  = 'status = %s';
        $params[] = $status;
    }
    if ($username !== '') {
        $where[]  = 'username LIKE %s';
        $params[] = '%' . $wpdb->esc_like($username) . '%';
    }
    if ($tracking !== '') {
        $where[]  = 'tracking_number LIKE %s';
        $params[] = '%' . $wpdb->esc_like($tracking) . '%';
    }

    $sql = "SELECT tracking_number, username, status, date_added, date_received, description
            FROM {$table}
            WHERE " . implode(' AND ', $where) . '
            ORDER BY date_added DESC
            LIMIT 50';

    $rows = $params
        ? $wpdb->get_results($wpdb->prepare($sql, ...$params))
        : $wpdb->get_results($sql);

    if (empty($rows)) {
        wp_send_json_error(__('No results found matching your criteria.', 'ybt-packer'));
    }

    wp_send_json_success(array_map(function ($r) {
        return array(
            'tracking_number' => $r->tracking_number,
            'username'        => $r->username,
            'status'          => $r->status,
            'date_added'      => $r->date_added,
            'date_received'   => $r->date_received ?: '-',
            'description'     => $r->description ?: '-',
        );
    }, $rows));
}
