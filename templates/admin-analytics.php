if (!defined('ABSPATH')) exit;

/**
 * Analytics Dashboard Template
 */
?>
<div class="wrap">
    <h1>Analytics Dashboard</h1>

    <div id="analytics-loading" style="text-align:center;padding:50px;">
        <div class="spinner is-active" style="float:none;margin-bottom:10px;"></div>
        <p>Loading analytics data...</p>
    </div>

    <div id="analytics-content" style="display:none;">
        <div class="ybt-analytics-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:20px 0;">
            <div class="ybt-stat-card" style="background:#fff;padding:20px;border-left:4px solid #0073aa;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-radius:4px;">
                <h3 style="margin:0 0 10px;color:#666;font-size:14px;">Total Users</h3>
                <p style="margin:0;font-size:32px;font-weight:bold;color:#0073aa;" id="stat-users">0</p>
            </div>
            <div class="ybt-stat-card" style="background:#fff;padding:20px;border-left:4px solid #00a32a;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-radius:4px;">
                <h3 style="margin:0 0 10px;color:#666;font-size:14px;">Total Parcels</h3>
                <p style="margin:0;font-size:32px;font-weight:bold;color:#00a32a;" id="stat-parcels">0</p>
            </div>
            <div class="ybt-stat-card" style="background:#fff;padding:20px;border-left:4px solid #d63638;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-radius:4px;">
                <h3 style="margin:0 0 10px;color:#666;font-size:14px;">Not Received</h3>
                <p style="margin:0;font-size:32px;font-weight:bold;color:#d63638;" id="stat-not-received">0</p>
            </div>
            <div class="ybt-stat-card" style="background:#fff;padding:20px;border-left:4px solid #f0b849;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-radius:4px;">
                <h3 style="margin:0 0 10px;color:#666;font-size:14px;">Received</h3>
                <p style="margin:0;font-size:32px;font-weight:bold;color:#f0b849;" id="stat-received">0</p>
            </div>
            <div class="ybt-stat-card" style="background:#fff;padding:20px;border-left:4px solid #826eb4;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-radius:4px;">
                <h3 style="margin:0 0 10px;color:#666;font-size:14px;">Packed</h3>
                <p style="margin:0;font-size:32px;font-weight:bold;color:#826eb4;" id="stat-packed">0</p>
            </div>
            <div class="ybt-stat-card" style="background:#fff;padding:20px;border-left:4px solid #228B22;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-radius:4px;">
                <h3 style="margin:0 0 10px;color:#666;font-size:14px;">Completed</h3>
                <p style="margin:0;font-size:32px;font-weight:bold;color:#228B22;" id="stat-completed">0</p>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin:20px 0;">
            <div style="background:#fff;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-radius:4px;">
                <h3>Recent Activity</h3>
                <ul style="list-style:none;padding:0;">
                    <li style="padding:10px 0;border-bottom:1px solid #eee;">Today: <strong id="stat-today">0</strong> parcels</li>
                    <li style="padding:10px 0;border-bottom:1px solid #eee;">This Week: <strong id="stat-week">0</strong> parcels</li>
                    <li style="padding:10px 0;">This Month: <strong id="stat-month">0</strong> parcels</li>
                </ul>
            </div>

            <div style="background:#fff;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-radius:4px;">
                <h3>Top Users</h3>
                <div id="top-users-list"></div>
            </div>
        </div>

        <div style="background:#fff;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin:20px 0;border-radius:4px;">
            <h3>📊 Performance Metrics</h3>
            <div style="height:300px;position:relative;">
                <canvas id="performance-chart"></canvas>
            </div>
        </div>

        <div style="background:#fff;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin:20px 0;border-radius:4px;">
            <h3>🚀 Quick Actions</h3>
            <button class="button button-primary" id="auto-update-old">Auto-Update Old Parcels</button>
            <button class="button" id="send-reminders">Send Pending Reminders</button>
            <button class="button" id="generate-report">Generate Monthly Report</button>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';

    // DOM Safety: Initialize local configuration if global ybt_admin is missing
    const config = window.ybt_admin || {
        nonce: <?php echo json_encode(wp_create_nonce("ybt_admin_nonce")); ?>,
        ajax_url: <?php echo json_encode(admin_url("admin-ajax.php")); ?>
    };

    const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';

    $(function() {
    $.ajax({
        url: config.ajax_url,
        type: 'POST',
        data: {
            action: 'ybt_get_analytics',
            nonce: config.nonce
        },
        success: function(response) {
            if (response.success && response.data) {
                var data = response.data;
                $('#stat-users').text(data.total_users || 0);
                $('#stat-parcels').text(data.total_parcels || 0);
                $('#stat-not-received').text(data.not_received || 0);
                $('#stat-received').text(data.received || 0);
                $('#stat-packed').text(data.packed || 0);
                $('#stat-completed').text(data.completed || 0);
                $('#stat-today').text(data.today_parcels || 0);
                $('#stat-week').text(data.week_parcels || 0);
                $('#stat-month').text(data.month_parcels || 0);

                var topUsersHtml = '<ul style="list-style:none;padding:0;">';
                if (data.top_users && data.top_users.length > 0) {
                    data.top_users.forEach(function(user) {
                        topUsersHtml += '<li style="padding:8px 0;border-bottom:1px solid #eee;"><strong>' + escapeHtml(user.username) + '</strong>: ' + (parseInt(user.count) || 0) + ' parcels</li>';
                    });
                } else {
                    topUsersHtml += '<li>No data available</li>';
                }
                topUsersHtml += '</ul>';
                $('#top-users-list').html(topUsersHtml);

                $('#analytics-loading').hide();
                $('#analytics-content').show();

                // Create performance chart
                if (typeof Chart !== 'undefined') {
                    createPerformanceChart(data);
                }
            } else {
                $('#analytics-loading').html('<p class="error">Error loading analytics: ' + escapeHtml(response.data || 'Unknown error') + '</p>');
            }
        },
        error: function() {
            $('#analytics-loading').html('<p class="error">Failed to connect to the server.</p>');
        }
    });

    function createPerformanceChart(data) {
        var canvas = document.getElementById('performance-chart');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Not Received', 'Received', 'Packed', 'Completed'],
                datasets: [{
                    label: 'Parcels by Status',
                    data: [data.not_received || 0, data.received || 0, data.packed || 0, data.completed || 0],
                    backgroundColor: ['#d63638', '#f0b849', '#0073aa', '#228B22']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }

    $('#auto-update-old').click(function() {
        if (!confirm('Auto-update parcels older than 30 days?')) return;
        $.post(config.ajax_url, {
            action: 'ybt_auto_update_old',
            nonce: config.nonce
        }, function(r) {
            alert(r.success ? escapeHtml(r.data) : 'Error: ' + escapeHtml(r.data));
        }).fail(function(){ alert('Server error.'); });
    });

    $('#send-reminders').click(function() {
        $.post(config.ajax_url, {
            action: 'ybt_send_reminders',
            nonce: config.nonce
        }, function(r) {
            alert(r.success ? escapeHtml(r.data) : 'Error: ' + escapeHtml(r.data));
        }).fail(function(){ alert('Server error.'); });
    });

    $('#generate-report').click(function() {
        window.location.href = config.ajax_url + '?action=ybt_generate_report&nonce=' + config.nonce;
    });
});
})(jQuery);
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
