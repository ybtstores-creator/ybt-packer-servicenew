<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('Access denied');

global $wpdb;

// Get notification statistics
$total_notifications = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_notifications"));
$unread_notifications = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_notifications WHERE is_read = %d", 0));
$critical_alerts = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_security_log WHERE severity = %s AND date_created >= DATE_SUB(NOW(), INTERVAL 24 HOUR)", 'critical'));
?>

<div class="wrap ybt-smart-notifications">
    <h1><i class="dashicons dashicons-bell"></i> Smart Notifications Center</h1>

    <!-- Notification Stats -->
    <div class="notification-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_notifications; ?></div>
            <div class="stat-label">Total Notifications</div>
        </div>
        <div class="stat-card unread">
            <div class="stat-number"><?php echo $unread_notifications; ?></div>
            <div class="stat-label">Unread</div>
        </div>
        <div class="stat-card critical">
            <div class="stat-number"><?php echo $critical_alerts; ?></div>
            <div class="stat-label">Critical Alerts (24h)</div>
        </div>
    </div>

    <!-- Smart Alert Configuration -->
    <div class="alert-config-panel">
        <h2><i class="dashicons dashicons-admin-settings"></i> Smart Alert Configuration</h2>
        <form id="smart-alerts-form">
            <table class="form-table">
                <tr>
                    <th scope="row">Overdue Item Threshold</th>
                    <td>
                        <input type="number" name="overdue_threshold" value="<?php echo get_option('ybt_overdue_threshold', 7); ?>" min="1" max="30" />
                        <p class="description">Days after which items are considered overdue</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Capacity Warning Level</th>
                    <td>
                        <input type="number" name="capacity_warning" value="<?php echo get_option('ybt_capacity_warning', 80); ?>" min="50" max="100" />%
                        <p class="description">Percentage of daily capacity that triggers warnings</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Performance Alert Threshold</th>
                    <td>
                        <input type="number" name="performance_threshold" value="<?php echo get_option('ybt_performance_threshold', 500); ?>" min="100" max="2000" />ms
                        <p class="description">Database response time that triggers performance alerts</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Enable Email Alerts</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_email_alerts" <?php checked(get_option('ybt_enable_email_alerts', 0)); ?> />
                            Send critical alerts via email
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Alert Email Recipients</th>
                    <td>
                        <textarea name="alert_emails" rows="3" cols="50"><?php echo esc_textarea(get_option('ybt_alert_emails', get_option('admin_email'))); ?></textarea>
                        <p class="description">One email per line</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save Alert Settings</button>
            </p>
        </form>
    </div>

    <!-- Real-time Alerts -->
    <div class="realtime-alerts">
        <h2><i class="dashicons dashicons-warning"></i> Real-time System Alerts</h2>
        <div id="realtime-alerts-container">
            <div class="loading">Loading real-time alerts...</div>
        </div>
    </div>

    <!-- Alert History -->
    <div class="alert-history">
        <h2><i class="dashicons dashicons-clock"></i> Alert History</h2>
        <div class="alert-filters">
            <select id="alert-type-filter">
                <option value="">All Types</option>
                <option value="system">System</option>
                <option value="security">Security</option>
                <option value="performance">Performance</option>
                <option value="capacity">Capacity</option>
            </select>
            <select id="alert-severity-filter">
                <option value="">All Severities</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="critical">Critical</option>
            </select>
            <button id="filter-alerts" class="button">Filter</button>
            <button id="clear-old-alerts" class="button">Clear Old Alerts</button>
        </div>
        <div id="alert-history-container">
            <div class="loading">Loading alert history...</div>
        </div>
    </div>

    <!-- Predictive Alerts -->
    <div class="predictive-alerts">
        <h2><i class="dashicons dashicons-chart-line"></i> Predictive Alerts</h2>
        <div id="predictive-alerts-container">
            <div class="prediction-card">
                <h3>📈 Volume Prediction</h3>
                <div id="volume-prediction">Analyzing trends...</div>
            </div>
            <div class="prediction-card">
                <h3>⚡ Performance Forecast</h3>
                <div id="performance-forecast">Calculating metrics...</div>
            </div>
            <div class="prediction-card">
                <h3>🎯 Bottleneck Detection</h3>
                <div id="bottleneck-detection">Scanning processes...</div>
            </div>
        </div>
    </div>
</div>

<style>
.ybt-smart-notifications {
    background: #f1f1f1;
    padding: 20px;
}

.notification-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #0073aa, #005a87);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.stat-card.unread {
    background: linear-gradient(135deg, #f0b849, #d4a017);
}

.stat-card.critical {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.alert-config-panel,
.realtime-alerts,
.alert-history,
.predictive-alerts {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.alert-filters {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.alert-filters select {
    padding: 5px 10px;
}

.predictive-alerts-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.prediction-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
}

.prediction-card h3 {
    margin: 0 0 10px 0;
    color: #495057;
}

.alert-item {
    padding: 12px;
    margin: 8px 0;
    border-radius: 4px;
    border-left: 4px solid;
}

.alert-item.info {
    background: #d1ecf1;
    border-color: #0073aa;
}

.alert-item.warning {
    background: #fff3cd;
    border-color: #f0b849;
}

.alert-item.critical {
    background: #f8d7da;
    border-color: #dc3545;
}

.alert-timestamp {
    font-size: 0.8em;
    color: #666;
    margin-top: 5px;
}

.loading {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load real-time alerts
    loadRealtimeAlerts();

    // Load alert history
    loadAlertHistory();

    // Load predictive alerts
    loadPredictiveAlerts();

    // Auto-refresh every 30 seconds
    setInterval(function() {
        loadRealtimeAlerts();
    }, 30000);

    // Save alert settings
    $('#smart-alerts-form').on('submit', function(e) {
        e.preventDefault();
        saveAlertSettings();
    });

    // Filter alerts
    $('#filter-alerts').on('click', function() {
        loadAlertHistory();
    });

    // Clear old alerts
    $('#clear-old-alerts').on('click', function() {
        if (confirm('Clear alerts older than 30 days?')) {
            clearOldAlerts();
        }
    });

    function loadRealtimeAlerts() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_smart_notifications',
                nonce: '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayRealtimeAlerts(response.data);
                }
            }
        });
    }

    function loadAlertHistory() {
        const typeFilter = $('#alert-type-filter').val();
        const severityFilter = $('#alert-severity-filter').val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_get_alert_history',
                nonce: '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>',
                type_filter: typeFilter,
                severity_filter: severityFilter
            },
            success: function(response) {
                if (response.success) {
                    displayAlertHistory(response.data);
                }
            }
        });
    }

    function loadPredictiveAlerts() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_predictive_insights',
                nonce: '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayPredictiveAlerts(response.data);
                }
            }
        });
    }

    function displayRealtimeAlerts(alerts) {
        let html = '';
        if (alerts.length === 0) {
            html = '<div class="alert-item info">✅ No active alerts. All systems operating normally.</div>';
        } else {
            alerts.forEach(function(alert) {
                const icon = getAlertIcon(alert.type, alert.priority);
                const title = $('<div>').text(alert.title).html();
                const message = $('<div>').text(alert.message).html();
                const action = alert.action ? $('<div>').text(alert.action).html() : '';
                const priority = $('<div>').text(alert.priority).html();
                html += `<div class="alert-item ${alert.type}">
                    ${icon} <strong>${title}</strong><br>
                    ${message}
                    ${action ? `<br><em>Action: ${action}</em>` : ''}
                    <div class="alert-timestamp">Priority: ${priority}</div>
                </div>`;
            });
        }
        $('#realtime-alerts-container').html(html);
    }

    function displayAlertHistory(history) {
        let html = '';
        if (history.length === 0) {
            html = '<div class="alert-item info">No alerts found for the selected filters.</div>';
        } else {
            history.forEach(function(alert) {
                const icon = getAlertIcon(alert.event_type, alert.severity);
                const eventType = $('<div>').text(alert.event_type).html();
                const description = $('<div>').text(alert.description).html();
                const dateCreated = $('<div>').text(alert.date_created).html();
                html += `<div class="alert-item ${alert.severity}">
                    ${icon} <strong>${eventType}</strong><br>
                    ${description}
                    <div class="alert-timestamp">${dateCreated}</div>
                </div>`;
            });
        }
        $('#alert-history-container').html(html);
    }

    function displayPredictiveAlerts(predictions) {
        if (predictions.volume_prediction) {
            $('#volume-prediction').text(predictions.volume_prediction);
        }
        if (predictions.performance_forecast) {
            $('#performance-forecast').text(predictions.performance_forecast);
        }
        if (predictions.bottleneck_detection) {
            $('#bottleneck-detection').text(predictions.bottleneck_detection);
        }
    }

    function saveAlertSettings() {
        const formData = $('#smart-alerts-form').serialize();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_save_alert_settings',
                nonce: '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>',
                settings: formData
            },
            success: function(response) {
                if (response.success) {
                    alert('Alert settings saved successfully!');
                } else {
                    alert('Failed to save settings: ' + response.data);
                }
            }
        });
    }

    function clearOldAlerts() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_clear_old_alerts',
                nonce: '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Old alerts cleared successfully!');
                    loadAlertHistory();
                } else {
                    alert('Failed to clear alerts: ' + response.data);
                }
            }
        });
    }

    function getAlertIcon(type, severity) {
        const icons = {
            'system': '⚙️',
            'security': '🔒',
            'performance': '⚡',
            'capacity': '📊',
            'warning': '⚠️',
            'critical': '🚨',
            'info': '💡'
        };
        return icons[type] || icons[severity] || '📢';
    }
});
</script>
