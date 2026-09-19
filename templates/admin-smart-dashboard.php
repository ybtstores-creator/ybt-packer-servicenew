<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('Access denied');
?>

<div class="wrap ybt-smart-dashboard">
    <h1><i class="dashicons dashicons-chart-area"></i> Smart Dashboard</h1>

    <!-- Smart Notifications Panel -->
    <div class="ybt-notifications-panel" id="smart-notifications">
        <h2><i class="dashicons dashicons-bell"></i> Smart Alerts</h2>
        <div id="notifications-container">
            <div class="loading">Loading smart notifications...</div>
        </div>
    </div>

    <!-- AI Insights Grid -->
    <div class="ybt-insights-grid">
        <!-- Predictive Analytics -->
        <div class="insight-card predictive">
            <h3><i class="dashicons dashicons-chart-line"></i> Predictive Insights</h3>
            <div id="predictive-content">
                <div class="metric">
                    <span class="label">Next Week Prediction:</span>
                    <span class="value" id="next-week-prediction">-</span>
                </div>
                <div class="metric">
                    <span class="label">Growth Trend:</span>
                    <span class="value trend" id="growth-trend">-</span>
                </div>
                <div class="metric">
                    <span class="label">Peak Hours:</span>
                    <span class="value" id="peak-hours">-</span>
                </div>
            </div>
        </div>

        <!-- Performance Monitor -->
        <div class="insight-card performance">
            <h3><i class="dashicons dashicons-performance"></i> System Health</h3>
            <div id="performance-content">
                <div class="health-indicator">
                    <span class="label">Database:</span>
                    <span class="status" id="db-status">-</span>
                </div>
                <div class="health-indicator">
                    <span class="label">Memory:</span>
                    <span class="status" id="memory-status">-</span>
                </div>
                <div class="health-indicator">
                    <span class="label">Efficiency:</span>
                    <span class="status" id="efficiency-status">-</span>
                </div>
            </div>
        </div>

        <!-- Smart Recommendations -->
        <div class="insight-card recommendations">
            <h3><i class="dashicons dashicons-lightbulb"></i> Smart Recommendations</h3>
            <div id="recommendations-content">
                <div class="loading">Analyzing patterns...</div>
            </div>
        </div>

        <!-- Advanced Search -->
        <div class="insight-card search">
            <h3><i class="dashicons dashicons-search"></i> Smart Search</h3>
            <div class="search-container">
                <input type="text" id="smart-search" placeholder="Search with AI assistance..." />
                <div id="search-suggestions"></div>
                <div id="search-results"></div>
            </div>
        </div>
    </div>

    <!-- Auto-Optimization Panel -->
    <div class="optimization-panel">
        <h2><i class="dashicons dashicons-admin-tools"></i> Auto-Optimization</h2>
        <div class="optimization-controls">
            <button class="button button-primary" id="run-optimization">
                <i class="dashicons dashicons-update"></i> Run Smart Optimization
            </button>
            <button class="button" id="schedule-optimization">
                <i class="dashicons dashicons-clock"></i> Schedule Auto-Optimization
            </button>
            <div id="optimization-results"></div>
        </div>
    </div>

    <!-- Real-time Analytics Chart -->
    <div class="analytics-chart-container">
        <h2><i class="dashicons dashicons-chart-bar"></i> Real-time Analytics</h2>
        <canvas id="smart-analytics-chart"></canvas>
    </div>
</div>

<style>
.ybt-smart-dashboard {
    background: #f1f1f1;
    padding: 20px;
}

.ybt-notifications-panel {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.ybt-insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.insight-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.insight-card:hover {
    transform: translateY(-2px);
}

.insight-card.predictive {
    border-left: 4px solid #0073aa;
}

.insight-card.performance {
    border-left: 4px solid #00a32a;
}

.insight-card.recommendations {
    border-left: 4px solid #f0b849;
}

.insight-card.search {
    border-left: 4px solid #826eb4;
}

.metric, .health-indicator {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.metric:last-child, .health-indicator:last-child {
    border-bottom: none;
}

.value.trend.increasing {
    color: #00a32a;
}

.value.trend.decreasing {
    color: #d63638;
}

.value.trend.stable {
    color: #f0b849;
}

.status.good {
    color: #00a32a;
    font-weight: bold;
}

.status.warning {
    color: #f0b849;
    font-weight: bold;
}

.status.critical {
    color: #d63638;
    font-weight: bold;
}

.notification {
    padding: 12px;
    margin: 8px 0;
    border-radius: 4px;
    border-left: 4px solid;
}

.notification.warning {
    background: #fff3cd;
    border-color: #f0b849;
}

.notification.info {
    background: #d1ecf1;
    border-color: #0073aa;
}

.notification.success {
    background: #d4edda;
    border-color: #00a32a;
}

.search-container {
    position: relative;
}

#smart-search {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

#search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.suggestion-item {
    padding: 8px 12px;
    cursor: pointer;
}

.suggestion-item:hover {
    background: #f0f0f0;
}

.optimization-panel {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.optimization-controls {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.analytics-chart-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.loading {
    text-align: center;
    color: #666;
    font-style: italic;
}

.recommendation-item {
    padding: 12px;
    margin: 8px 0;
    background: #f9f9f9;
    border-radius: 4px;
    border-left: 3px solid;
}

.recommendation-item.high {
    border-color: #d63638;
}

.recommendation-item.medium {
    border-color: #f0b849;
}

.recommendation-item.low {
    border-color: #0073aa;
}

.recommendation-title {
    font-weight: bold;
    margin-bottom: 4px;
}

.recommendation-action {
    color: #666;
    font-size: 0.9em;
}
</style>

<script>
// DOM Safety: Check for ybt_admin object (caching-safe fallback)
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

jQuery(document).ready(function($) {
    // Load smart notifications
    loadSmartNotifications();

    // Load predictive insights
    loadPredictiveInsights();

    // Load performance metrics
    loadPerformanceMetrics();

    // Load recommendations
    loadSmartRecommendations();

    // Smart search functionality
    setupSmartSearch();

    // Auto-optimization
    setupOptimization();

    // Refresh data every 30 seconds
    setInterval(function() {
        loadSmartNotifications();
        loadPerformanceMetrics();
    }, 30000);

    function loadSmartNotifications() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_smart_notifications',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayNotifications(response.data);
                }
            }
        });
    }

    function displayNotifications(notifications) {
        let html = '';
        if (notifications.length === 0) {
            html = '<div class="notification success">✅ All systems running smoothly!</div>';
        } else {
            notifications.forEach(function(notif) {
                const title = $('<div>').text(notif.title).html();
                const message = $('<div>').text(notif.message).html();
                const action = notif.action ? $('<div>').text(notif.action).html() : '';
                html += `<div class="notification ${notif.type}">
                    <strong>${title}</strong><br>
                    ${message}
                    ${action ? `<br><em>Action: ${action}</em>` : ''}
                </div>`;
            });
        }
        $('#notifications-container').html(html);
    }

    function loadPredictiveInsights() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_smart_analytics',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const nextWeek = $('<div>').text(data.predictions.next_week_volume + ' parcels').html();
                    const growthRate = $('<div>').text(data.predictions.growth_rate + '%').html();
                    $('#next-week-prediction').html(nextWeek);
                    $('#growth-trend').html(growthRate).addClass(data.predictions.trend);

                    if (data.peak_hours && data.peak_hours.length > 0) {
                        const hours = data.peak_hours.map(h => $('<div>').text(h.hour + ':00').html()).join(', ');
                        $('#peak-hours').html(hours);
                    }
                }
            }
        });
    }

    function loadPerformanceMetrics() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_performance_monitor',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const metrics = response.data;
                    const dbStatus = $('<div>').text(metrics.database.response_time + 'ms').html();
                    const memStatus = $('<div>').text(metrics.memory.percentage + '%').html();
                    const effStatus = $('<div>').text(metrics.efficiency.completion_rate + '%').html();
                    $('#db-status').html(dbStatus).addClass(metrics.database.status);
                    $('#memory-status').html(memStatus).addClass(
                        metrics.memory.percentage < 70 ? 'good' : (metrics.memory.percentage < 90 ? 'warning' : 'critical')
                    );
                    $('#efficiency-status').html(effStatus).addClass(
                        metrics.efficiency.completion_rate > 80 ? 'good' : (metrics.efficiency.completion_rate > 60 ? 'warning' : 'critical')
                    );
                }
            }
        });
    }

    function loadSmartRecommendations() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_smart_recommendations',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayRecommendations(response.data);
                }
            }
        });
    }

    function displayRecommendations(recommendations) {
        let html = '';
        if (recommendations.length === 0) {
            html = '<div class="recommendation-item low">🎯 No immediate recommendations. System is optimized!</div>';
        } else {
            recommendations.forEach(function(rec) {
                const title = $('<div>').text(rec.title).html();
                const description = $('<div>').text(rec.description).html();
                const action = $('<div>').text(rec.action).html();
                html += `<div class="recommendation-item ${rec.impact}">
                    <div class="recommendation-title">${title}</div>
                    <div>${description}</div>
                    <div class="recommendation-action">💡 ${action}</div>
                </div>`;
            });
        }
        $('#recommendations-content').html(html);
    }

    function setupSmartSearch() {
        let searchTimeout;
        $('#smart-search').on('input', function() {
            const query = $(this).val();
            clearTimeout(searchTimeout);

            if (query.length < 2) {
                $('#search-suggestions').hide();
                return;
            }

            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ybt_smart_search',
                        nonce: ybt_admin.nonce,
                        query: query
                    },
                    success: function(response) {
                        if (response.success) {
                            displaySearchResults(response.data);
                        }
                    }
                });
            }, 300);
        });
    }

    function displaySearchResults(data) {
        let html = '';
        if (data.suggestions.length > 0) {
            data.suggestions.forEach(function(suggestion) {
                html += `<div class="suggestion-item">${suggestion}</div>`;
            });
            $('#search-suggestions').html(html).show();
        }

        // Display search results
        let resultsHtml = '';
        if (data.results.length > 0) {
            const total = parseInt(data.total) || 0;
            resultsHtml = `<h4>Found ${total} results:</h4>`;
            data.results.forEach(function(result) {
                const trackingNum = $('<div>').text(result.tracking_number).html();
                const username = $('<div>').text(result.username).html();
                const matchType = $('<div>').text(result.match_type).html();
                resultsHtml += `<div class="search-result">
                    <strong>${trackingNum}</strong> - ${username}
                    <span class="match-type">(${matchType} match)</span>
                </div>`;
            });
        }
        $('#search-results').html(resultsHtml);
    }

    function setupOptimization() {
        $('#run-optimization').click(function() {
            $(this).prop('disabled', true).text('Optimizing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ybt_auto_optimize',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let html = '<h4>Optimization Complete:</h4><ul>';
                        response.data.optimizations.forEach(function(opt) {
                            html += `<li>✅ ${opt}</li>`;
                        });
                        html += '</ul>';
                        $('#optimization-results').html(html);
                    }
                    $('#run-optimization').prop('disabled', false).text('Run Smart Optimization');
                }
            });
        });
    }
});
</script>
