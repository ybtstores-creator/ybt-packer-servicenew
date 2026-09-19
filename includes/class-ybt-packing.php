<?php
/**
 * YBT Packing Service Data Logic
 * Handles all data-related operations for the packing module.
 *
 * @package YBT_Packer
 * @version 1.0
 */

if (!defined('ABSPATH')) exit;

class YBT_Packing {

    /**
     * Fetch tracking requests based on filters
     *
     * @param string $user_filter Filter by username
     * @param string $search      Search by tracking number
     * @return array List of tracking items
     */
    public static function get_filtered_requests($user_filter = '', $search = '') {
        global $wpdb;
        $where_conditions = array("status = %s");
        $where_values = array('packing_in_progress');

        // Filter out anonymous or invalid users for this interface
        $where_conditions[] = "(user_id IS NOT NULL AND user_id > 0 AND username IS NOT NULL AND username != '' AND username != 'Anonymous')";

        if ($user_filter) {
            $where_conditions[] = "username = %s";
            $where_values[] = $user_filter;
        }

        if ($search) {
            $where_conditions[] = "tracking_number LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $query = "SELECT * FROM {$wpdb->prefix}ybt_tracking $where_clause ORDER BY date_packing_requested DESC, username ASC";

        return (array) $wpdb->get_results($wpdb->prepare($query, $where_values));
    }

    /**
     * Group tracking items by user
     *
     * @param array $requests List of tracking items
     * @return array Grouped items [username => [items]]
     */
    public static function group_requests($requests) {
        $grouped = array();
        foreach ($requests as $item) {
            $username = $item->username ?: __('Unknown User', 'ybt-packer');
            if (!isset($grouped[$username])) {
                $grouped[$username] = array();
            }
            $grouped[$username][] = $item;
        }
        return $grouped;
    }

    /**
     * Fetch unique users who have active packing requests
     *
     * @return array List of unique usernames
     */
    public static function get_active_usernames() {
        global $wpdb;
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT username FROM {$wpdb->prefix}ybt_tracking
             WHERE username IS NOT NULL AND username != '' AND username != 'Anonymous'
             AND status = %s ORDER BY username",
            'packing_in_progress'
        ));
    }

    /**
     * Fetch static cache of active warehouse settings
     *
     * @return array Cached settings [shipping_type => settings_object]
     */
    public static function get_warehouse_cache() {
        global $wpdb;
        $results = (array) $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ybt_warehouse_settings WHERE is_active = 1 ORDER BY date_created DESC");
        $cache = array();
        foreach ($results as $row) {
            if (!isset($cache[$row->shipping_type])) {
                $cache[$row->shipping_type] = $row;
            }
        }
        return $cache;
    }

    /**
     * Fetch active batches grouped by user with aggregated carton data
     *
     * @return array Grouped batches [username => [batches]]
     */
    public static function get_user_batches() {
        global $wpdb;

        // 1. Fetch user/batch IDs
        $base_query = $wpdb->prepare(
            "SELECT username, batch_id, COUNT(*) as item_count
             FROM {$wpdb->prefix}ybt_tracking
             WHERE status = %s AND batch_id IS NOT NULL
             AND (user_id IS NOT NULL AND username != '' AND username != 'Anonymous')
             GROUP BY username, batch_id
             ORDER BY username, batch_id DESC",
            'packing_in_progress'
        );
        $batch_items = (array) $wpdb->get_results($base_query);

        if (empty($batch_items)) return array();

        // 2. Fetch aggregated carton data for these batches
        $batch_ids = array_unique(wp_list_pluck($batch_items, 'batch_id'));
        $id_placeholders = implode(',', array_fill(0, count($batch_ids), '%s'));

        $carton_aggs = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT batch_id, COUNT(*) as carton_count,
                    GROUP_CONCAT(CONCAT(payment_currency, ':', total_amount)) as payment_info
             FROM (
                SELECT batch_id, payment_currency, SUM(payment_amount) as total_amount
                FROM {$wpdb->prefix}ybt_cartons
                WHERE batch_id IN ($id_placeholders)
                GROUP BY batch_id, payment_currency
             ) as grouped_payments
             GROUP BY batch_id",
            $batch_ids
        ));

        // Map aggregates for quick lookup
        $agg_map = array();
        foreach ($carton_aggs as $agg) {
            $payments = array();
            if ($agg->payment_info) {
                foreach (explode(',', $agg->payment_info) as $pair) {
                    list($curr, $amt) = explode(':', $pair);
                    $payments[$curr] = $amt;
                }
            }
            $agg_map[$agg->batch_id] = array(
                'count' => $agg->carton_count,
                'payments' => $payments
            );
        }

        // 3. Combine and group
        $grouped = array();
        foreach ($batch_items as $item) {
            $item->aggregated = isset($agg_map[$item->batch_id]) ? $agg_map[$item->batch_id] : array('count' => 0, 'payments' => array());
            if (!isset($grouped[$item->username])) {
                $grouped[$item->username] = array();
            }
            $grouped[$item->username][] = $item;
        }
        return $grouped;
    }
}
