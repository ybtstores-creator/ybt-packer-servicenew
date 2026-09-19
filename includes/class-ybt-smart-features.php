<?php
if (!defined('ABSPATH')) exit;

class YBT_Smart_Features {

    public function __construct() {
        // Separate handlers for better clarity
        add_action('wp_ajax_ybt_smart_analytics', array($this, 'ajax_smart_analytics'));
        add_action('wp_ajax_ybt_predictive_insights', array($this, 'ajax_predictive_insights'));
        add_action('wp_ajax_ybt_get_analytics', array($this, 'ajax_get_analytics'));
        add_action('wp_ajax_ybt_smart_notifications', array($this, 'ajax_smart_notifications'));
        add_action('wp_ajax_ybt_auto_optimize', array($this, 'ajax_auto_optimize'));
        add_action('wp_ajax_ybt_smart_search', array($this, 'ajax_smart_search'));
        add_action('wp_ajax_ybt_performance_monitor', array($this, 'ajax_performance_monitor'));
        add_action('wp_ajax_ybt_smart_recommendations', array($this, 'ajax_smart_recommendations'));
    }

    // AI-Powered Predictive Analytics
    public function ajax_smart_analytics() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {

            global $wpdb;

            // Get historical data for predictions
            $data = array();

            // Predict peak times
            $hourly_data = $wpdb->get_results("
                SELECT HOUR(date_added) as hour, COUNT(*) as count
                FROM {$wpdb->prefix}ybt_tracking
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY HOUR(date_added)
                ORDER BY count DESC
            ");

            $peak_hours = !empty($hourly_data) ? array_slice($hourly_data, 0, 3) : array();

            // Predict busy days
            $daily_data = $wpdb->get_results("
                SELECT DAYOFWEEK(date_added) as day, COUNT(*) as count
                FROM {$wpdb->prefix}ybt_tracking
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DAYOFWEEK(date_added)
                ORDER BY count DESC
            ");

            // Processing time analysis
            $avg_processing_time = $wpdb->get_var("
                SELECT AVG(TIMESTAMPDIFF(HOUR, date_added, date_updated))
                FROM {$wpdb->prefix}ybt_tracking
                WHERE status = 'completed' AND date_updated IS NOT NULL
            ");

            // Bottleneck detection
            $bottlenecks = $wpdb->get_results("
                SELECT status, COUNT(*) as count,
                       AVG(TIMESTAMPDIFF(HOUR, date_added, COALESCE(date_updated, NOW()))) as avg_time
                FROM {$wpdb->prefix}ybt_tracking
                GROUP BY status
                HAVING avg_time > 24
            ");

            $data = array(
                'peak_hours' => $peak_hours,
                'busy_days' => $daily_data,
                'avg_processing_time' => $avg_processing_time !== null ? round($avg_processing_time, 2) : 0,
                'bottlenecks' => $bottlenecks,
                'predictions' => $this->generate_predictions()
            );

            wp_send_json_success($data);
        } catch (Exception $e) {
            wp_send_json_error(array('error' => 'Failed to load analytics: ' . $e->getMessage()));
        }
    }

    // Predictive Insights (separate from smart analytics)
    public function ajax_predictive_insights() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {

            global $wpdb;

            $insights = array();

            // Trend analysis
            $current_week = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            $previous_week = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND date_added < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            $trend_insight = array(
                'type' => 'trend',
                'title' => 'Weekly Trend',
                'message' => 'Analyzing delivery trends for better forecasting',
                'data' => array(
                    'current_week' => $current_week ?: 0,
                    'previous_week' => $previous_week ?: 0
                )
            );

            $insights[] = $trend_insight;

            wp_send_json_success($insights);
        } catch (Exception $e) {
            wp_send_json_error(array('error' => 'Failed to load predictive insights: ' . $e->getMessage()));
        }
    }

    // Get Analytics (simplified version for charts)
    public function ajax_get_analytics() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {

            global $wpdb;

            $today_parcels = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE DATE(date_added) = CURDATE()
            ");

            wp_send_json_success(array(
                'today_parcels' => intval($today_parcels)
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('error' => 'Failed to load analytics: ' . $e->getMessage()));
        }
    }

    // Smart Notifications System
    public function ajax_smart_notifications() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {

            global $wpdb;

            $notifications = array();

            // Overdue items
            $overdue = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE status IN ('not_received', 'received')
                AND date_added < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            if ($overdue > 0) {
                $notifications[] = array(
                    'type' => 'warning',
                    'title' => 'Overdue Items',
                    'message' => "$overdue items are overdue for processing",
                    'action' => 'view_overdue',
                    'priority' => 'high'
                );
            }

            // Capacity alerts
            $daily_capacity = get_option('ybt_daily_capacity', 100);
            $today_count = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE DATE(date_added) = CURDATE()
            ");

            if ($daily_capacity > 0 && $today_count > $daily_capacity * 0.8) {
                $percentage = round(($today_count / $daily_capacity) * 100);
                $notifications[] = array(
                    'type' => 'info',
                    'title' => 'Capacity Alert',
                    'message' => "Today's volume is at $percentage% capacity ({$today_count}/{$daily_capacity})",
                    'action' => 'adjust_capacity',
                    'priority' => 'medium'
                );
            }

            // Performance insights
            $slow_users = $wpdb->get_results("
                SELECT username, COUNT(*) as count
                FROM {$wpdb->prefix}ybt_tracking
                WHERE status = 'not_received'
                AND date_added < DATE_SUB(NOW(), INTERVAL 3 DAY)
                GROUP BY username
                HAVING count > 5
            ");

            if (!empty($slow_users)) {
                $user_list = array();
                foreach ($slow_users as $user) {
                    $user_list[] = $user->username . ' (' . $user->count . ')';
                }
                $notifications[] = array(
                    'type' => 'info',
                    'title' => 'User Follow-up',
                    'message' => count($slow_users) . " users have multiple pending items: " . implode(', ', $user_list),
                    'action' => 'contact_users',
                    'priority' => 'low'
                );
            }

            wp_send_json_success($notifications);
        } catch (Exception $e) {
            wp_send_json_error(array('error' => 'Failed to load notifications: ' . $e->getMessage()));
        }
    }

    // Auto-Optimization Engine
    public function ajax_auto_optimize() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {

            global $wpdb;

            $optimizations = array();

            // Auto-update old items
            $updated = $wpdb->query("
                UPDATE {$wpdb->prefix}ybt_tracking
                SET status = 'received', date_updated = NOW()
                WHERE status = 'not_received'
                AND date_added < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");

            if ($updated > 0) {
                $optimizations[] = "Auto-updated $updated old items to 'received' status";
            }

            // Clean up duplicate entries
            $duplicates = $wpdb->query("
                DELETE t1 FROM {$wpdb->prefix}ybt_tracking t1
                INNER JOIN {$wpdb->prefix}ybt_tracking t2
                ON t1.tracking_number = t2.tracking_number
                AND t1.username = t2.username
                WHERE t1.id < t2.id
            ");

            if ($duplicates > 0) {
                $optimizations[] = "Removed $duplicates duplicate entries";
            }

            // Optimize database
            $optimize_result = $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}ybt_tracking");
            if ($optimize_result !== false) {
                $optimizations[] = "Database tables optimized";
            } else {
                $optimizations[] = "Database optimization attempted";
            }

            wp_send_json_success(array(
                'optimizations' => $optimizations,
                'timestamp' => current_time('mysql')
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('error' => 'Failed to perform optimizations: ' . $e->getMessage()));
        }
    }

    // Smart Search with AI
    public function ajax_smart_search() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {

            $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
            global $wpdb;

            $results = array();
            $suggestions = array();

            if (!empty($query)) {
                // Direct matches
                $direct = $wpdb->get_results($wpdb->prepare("
                    SELECT *, 'exact' as match_type FROM {$wpdb->prefix}ybt_tracking
                    WHERE tracking_number LIKE %s OR username LIKE %s
                    LIMIT 10
                ", "%$query%", "%$query%"));

                // Fuzzy matches for typos
                $fuzzy = $wpdb->get_results($wpdb->prepare("
                    SELECT *, 'fuzzy' as match_type FROM {$wpdb->prefix}ybt_tracking
                    WHERE SOUNDEX(tracking_number) = SOUNDEX(%s)
                    OR SOUNDEX(username) = SOUNDEX(%s)
                    AND (tracking_number NOT LIKE %s OR username NOT LIKE %s)
                    LIMIT 5
                ", $query, $query, "%$query%", "%$query%"));

                $results = array_merge($direct, $fuzzy);

                // Add search suggestions
                $suggestions = $this->get_search_suggestions($query);
            }

            wp_send_json_success(array(
                'results' => $results,
                'suggestions' => $suggestions,
                'total' => count($results),
                'query' => $query
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('error' => 'Search failed: ' . $e->getMessage()));
        }
    }

    // Performance Monitoring
    public function ajax_performance_monitor() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {

            global $wpdb;

            $metrics = array();

            // Response time metrics
            $start_time = microtime(true);
            $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking");
            $db_response_time = (microtime(true) - $start_time) * 1000;

            // System health
            $metrics['database'] = array(
                'response_time' => round($db_response_time, 2),
                'status' => $db_response_time < 100 ? 'good' : ($db_response_time < 500 ? 'warning' : 'critical')
            );

            // Memory usage
            $memory_usage = memory_get_usage(true);
            $memory_limit = ini_get('memory_limit');
            $memory_limit_bytes = $this->convert_to_bytes($memory_limit);

            $metrics['memory'] = array(
                'usage' => round($memory_usage / 1024 / 1024, 2),
                'limit' => $memory_limit_bytes ? round($memory_limit_bytes / 1024 / 1024, 2) : 'unknown',
                'percentage' => $memory_limit_bytes ? round(($memory_usage / $memory_limit_bytes) * 100, 2) : 'unknown'
            );

            // Processing efficiency
            $efficiency = $wpdb->get_row("
                SELECT
                    AVG(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100 as completion_rate,
                    AVG(TIMESTAMPDIFF(HOUR, date_added, date_updated)) as avg_processing_time
                FROM {$wpdb->prefix}ybt_tracking
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            $metrics['efficiency'] = array(
                'completion_rate' => $efficiency && $efficiency->completion_rate !== null ? round($efficiency->completion_rate, 2) : 0,
                'avg_processing_time' => $efficiency && $efficiency->avg_processing_time !== null ? round($efficiency->avg_processing_time, 2) : 0
            );

            wp_send_json_success($metrics);
        } catch (Exception $e) {
            wp_send_json_error(array('error' => 'Performance monitoring failed: ' . $e->getMessage()));
        }
    }

    // Smart Recommendations Engine
    public function ajax_smart_recommendations() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {

            global $wpdb;

            $recommendations = array();

            // Workflow optimization
            $bottleneck_status = $wpdb->get_row("
                SELECT status, COUNT(*) as count
                FROM {$wpdb->prefix}ybt_tracking
                WHERE status IN ('not_received', 'received')
                GROUP BY status
                ORDER BY count DESC
                LIMIT 1
            ");

            if ($bottleneck_status && $bottleneck_status->count > 50) {
                $recommendations[] = array(
                    'type' => 'workflow',
                    'title' => 'Process Bottleneck Detected',
                    'description' => "You have {$bottleneck_status->count} items in '{$bottleneck_status->status}' status",
                    'action' => 'Prioritize processing these items',
                    'impact' => 'high'
                );
            }

            // User engagement
            $inactive_users = $wpdb->get_var("
                SELECT COUNT(DISTINCT username)
                FROM {$wpdb->prefix}ybt_tracking
                WHERE date_added < DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND username NOT IN (
                    SELECT DISTINCT username
                    FROM {$wpdb->prefix}ybt_tracking
                    WHERE date_added >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                )
            ");

            if ($inactive_users > 0) {
                $recommendations[] = array(
                    'type' => 'engagement',
                    'title' => 'Re-engage Inactive Users',
                    'description' => "$inactive_users users haven't been active recently",
                    'action' => 'Send follow-up communications',
                    'impact' => 'medium'
                );
            }

            // Capacity planning
            $growth_rate = $this->calculate_growth_rate();
            if ($growth_rate > 20) {
                $recommendations[] = array(
                    'type' => 'capacity',
                    'title' => 'Scale Up Resources',
                    'description' => "Volume has grown by {$growth_rate}% this month",
                    'action' => 'Consider increasing processing capacity',
                    'impact' => 'high'
                );
            }

            wp_send_json_success($recommendations);
        } catch (Exception $e) {
            wp_send_json_error(array('error' => 'Failed to load recommendations: ' . $e->getMessage()));
        }
    }

    private function generate_predictions() {
        global $wpdb;

        try {
            // Simple trend analysis for next week prediction
            $last_week = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            $prev_week = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND date_added < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            $growth_rate = $prev_week > 0 ? (($last_week - $prev_week) / $prev_week) : 0;
            $predicted_next_week = round($last_week * (1 + $growth_rate));

            return array(
                'next_week_volume' => $predicted_next_week,
                'growth_rate' => round($growth_rate * 100, 2),
                'trend' => $growth_rate > 0.1 ? 'increasing' : ($growth_rate < -0.1 ? 'decreasing' : 'stable')
            );
        } catch (Exception $e) {
            return array(
                'next_week_volume' => 0,
                'growth_rate' => 0,
                'trend' => 'stable'
            );
        }
    }

    private function get_search_suggestions($query) {
        global $wpdb;

        try {
            if (empty($query)) {
                return array();
            }

            // Get common tracking patterns
            $suggestions = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT tracking_number
                FROM {$wpdb->prefix}ybt_tracking
                WHERE tracking_number LIKE %s
                LIMIT 5
            ", "$query%"));

            return !empty($suggestions) ? array_column($suggestions, 'tracking_number') : array();
        } catch (Exception $e) {
            return array();
        }
    }

    private function convert_to_bytes($size) {
        if (empty($size)) {
            return 0;
        }

        $unit = strtolower(substr($size, -1));
        $value = (int) $size;

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }

    private function calculate_growth_rate() {
        global $wpdb;

        try {
            $this_month = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE MONTH(date_added) = MONTH(NOW())
                AND YEAR(date_added) = YEAR(NOW())
            ");

            $last_month = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
                WHERE MONTH(date_added) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
                AND YEAR(date_added) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
            ");

            if ($last_month > 0) {
                return round((($this_month - $last_month) / $last_month) * 100, 2);
            }
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

new YBT_Smart_Features();
