<?php
/**
 * YBT Export AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle report generation (CSV Export)
 */
function ybt_generate_report_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ybt_tracking';
    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY date_added DESC LIMIT 1000", ARRAY_A);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ybt-report-' . date('Y-m-d') . '.csv"');

    if (!empty($results)) {
        $sanitize_cell = function($val) {
            if (!is_scalar($val)) {
                return '';
            }
            $val = (string) $val;
            if (strlen($val) > 0 && in_array($val[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
                return "'" . $val;
            }
            return $val;
        };

        $output = fopen('php://output', 'w');
        fputcsv($output, array_map($sanitize_cell, array_keys($results[0])));
        foreach ($results as $row) {
            fputcsv($output, array_map($sanitize_cell, $row));
        }
        fclose($output);
    }
    exit;
}
add_action('wp_ajax_ybt_generate_report', 'ybt_generate_report_handler');

/**
 * Handle exporting site data as JSON
 */
function ybt_site_export_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    global $wpdb;

    $tables = array(
        'ybt_tracking', 'ybt_admin_messages', 'ybt_chat', 'ybt_cartons',
        'ybt_payment_settings', 'ybt_warehouse_settings',
        'ybt_fcm_tokens', 'ybt_activity_log'
    );

    $data = array();
    foreach ($tables as $table) {
        $data[$table] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}$table", ARRAY_A);
    }

    $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $filename = 'ybt-export-' . date('Y-m-d') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo $json;
    exit;
}
add_action('wp_ajax_ybt_site_export', 'ybt_site_export_handler');
