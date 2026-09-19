<?php
/**
 * Professional Admin Dashboard Template - Enhanced
 * YBT Packer Service v2.1
 *
 * Performance optimizations:
 * - Uses cached stats from ybt_get_dashboard_stats()
 * - Cached recent activity queries
 * - Lazy loading for images
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'ybt-packer'));
}

global $wpdb;

// Check if tables are healthy (cached for 1 week background check)
$db_healthy = get_option('ybt_db_healthy', '1');
$tables_missing = ($db_healthy === '0');
$stats_error = false;
$error_message = '';

try {
    // Get stats from optimized helper (handles single query and caching - 1 hour cache)
    $stats_data = ybt_get_dashboard_stats();

    if ($stats_data === false) {
        // Only do full check if helper fails
        $table_check = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'ybt_tracking'));
        if (!$table_check) {
             throw new Exception(__('Required database tables are missing. Please run repair/setup.', 'ybt-packer'));
        }
        throw new Exception(__('Failed to retrieve statistics from the database.', 'ybt-packer'));
    }

    // Assign stats for template
    $stats = $stats_data;
    $today_added = $stats_data['today_added'];
    $week_completed = $stats_data['week_completed'];

    // Recent activity - cached for 5 minutes
    $recent_activity = ybt_cache()->remember('ybt_admin_recent_activity', function() use ($wpdb) {
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, u.display_name as user_display_name
                 FROM {$wpdb->prefix}ybt_tracking t
                 LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                 ORDER BY t.date_added DESC
                 LIMIT %d",
                10
            )
        );
    }, 300, 'dashboard');

} catch (Exception $e) {
    $stats_error = true;
    $error_message = $e->getMessage();

    // Set default values
    $stats = array_fill_keys(['total', 'not_received', 'received', 'packing_in_progress', 'packed', 'completed'], 0);
    $today_added = $week_completed = 0;
    $recent_activity = array();
}
?>

<div class="wrap ybt-admin-wrapper" style="margin-top: 0; padding-top: 20px;">
    <?php if ($stats_error): ?>
    <div class="notice notice-error is-dismissible" id="ybt-db-notice">
        <p>
            <strong><?php _e('Database Status Alert:', 'ybt-packer'); ?></strong>
            <?php echo esc_html($error_message); ?>
        </p>
        <?php if ($tables_missing): ?>
            <p>
                <button id="ybt-fix-db" class="button button-primary">
                    <i class="fas fa-tools me-1"></i> <?php _e('Attempt Database Repair / Setup', 'ybt-packer'); ?>
                </button>
                <span id="ybt-fix-status" style="margin-left: 10px; font-weight: bold;"></span>
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="wp-heading-inline" style="font-size: 2rem; color: var(--text-primary);"><i class="fas fa-tachometer-alt me-2 text-primary"></i> <?php _e('YBT Management Console', 'ybt-packer'); ?></h1>
            <p class="text-muted mt-2"><?php _e('Professional Freight & Logistics Dashboard', 'ybt-packer'); ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="button button-primary button-large" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> <?php _e('Refresh Data', 'ybt-packer'); ?>
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row">
        <!-- Not Received -->
        <div class="col-md-4 col-lg-2 mb-4">
             <div class="glass-card admin-stat-card">
                <div class="admin-stat-icon" style="background: rgba(218, 165, 32, 0.1); color: #DAA520;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo number_format($stats['not_received']); ?></h3>
                    <p>Pending</p>
                </div>
            </div>
        </div>

        <!-- Received -->
        <div class="col-md-4 col-lg-2 mb-4">
             <div class="glass-card admin-stat-card">
                <div class="admin-stat-icon" style="background: rgba(184, 134, 11, 0.1); color: #B8860B;">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo number_format($stats['received']); ?></h3>
                    <p>Received</p>
                </div>
            </div>
        </div>

        <!-- Processing -->
        <div class="col-md-4 col-lg-2 mb-4">
             <div class="glass-card admin-stat-card">
                <div class="admin-stat-icon" style="background: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo number_format($stats['packing_in_progress']); ?></h3>
                    <p>Packing</p>
                </div>
            </div>
        </div>

        <!-- Packed -->
        <div class="col-md-4 col-lg-2 mb-4">
             <div class="glass-card admin-stat-card">
                <div class="admin-stat-icon" style="background: rgba(34, 139, 34, 0.1); color: #228B22;">
                    <i class="fas fa-box"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo number_format($stats['packed']); ?></h3>
                    <p>Ready</p>
                </div>
            </div>
        </div>

        <!-- Completed -->
        <div class="col-md-4 col-lg-2 mb-4">
             <div class="glass-card admin-stat-card">
                <div class="admin-stat-icon" style="background: rgba(0, 100, 0, 0.1); color: #006400;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo number_format($stats['completed']); ?></h3>
                    <p>Sent</p>
                </div>
            </div>
        </div>

        <!-- Total -->
         <div class="col-md-4 col-lg-2 mb-4">
             <div class="glass-card admin-stat-card">
                <div class="admin-stat-icon" style="background: rgba(0, 0, 0, 0.05); color: #333;">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="admin-stat-info">
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <p>Total</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content Column -->
        <div class="col-lg-8">
            <div class="glass-card">
                <div class="info-card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-history me-2"></i><?php _e('Recent Activity Log', 'ybt-packer'); ?></span>
                    <a href="<?php echo admin_url('admin.php?page=ybt-manage-tracking'); ?>" class="button button-small"><?php _e('View All', 'ybt-packer'); ?></a>
                </div>
                <div class="info-card-body p-0">
                    <?php if ($recent_activity): ?>
                        <div class="table-responsive">
                            <table class="table admin-table mb-0" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Tracking #</th>
                                        <th>User</th>
                                        <th>Added</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activity as $item): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $icon = 'fa-box';
                                            $color = '#666';
                                            switch($item->status) {
                                                case 'not_received': $icon = 'fa-clock'; $color = '#DAA520'; break;
                                                case 'received': $icon = 'fa-warehouse'; $color = '#B8860B'; break;
                                                case 'packing_in_progress': $icon = 'fa-cogs'; $color = '#4CAF50'; break;
                                                case 'packed': $icon = 'fa-check'; $color = '#228B22'; break;
                                                case 'completed': $icon = 'fa-check-double'; $color = '#006400'; break;
                                            }
                                            ?>
                                            <span class="badge-glass" style="color: <?php echo $color; ?>;">
                                                <i class="fas <?php echo $icon; ?> me-1"></i>
                                                <?php echo esc_html(str_replace('_', ' ', ucfirst($item->status))); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($item->tracking_number); ?></strong>
                                            <?php if($item->description): ?>
                                                <br><small class="text-muted"><?php echo esc_html(wp_trim_words($item->description, 5)); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item->user_display_name): ?>
                                                <div class="d-flex align-items-center">
                                                    <div style="width: 24px; height: 24px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 8px; font-size: 10px;">
                                                        <?php echo strtoupper(substr($item->user_display_name, 0, 1)); ?>
                                                    </div>
                                                    <?php echo esc_html($item->user_display_name); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Anonymous</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo human_time_diff(strtotime($item->date_added), current_time('timestamp')) . ' ago'; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=ybt-manage-tracking&edit=' . $item->id); ?>" class="button button-small">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                            <p>No recent activity found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="glass-card mb-4">
                <div class="info-card-header">
                    <span><i class="fas fa-bolt me-2 text-warning"></i><?php _e('Quick Actions', 'ybt-packer'); ?></span>
                </div>
                <div class="info-card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo admin_url('admin.php?page=ybt-add-tracking'); ?>" class="button button-primary" style="text-align: center; justify-content: center; display: flex; align-items: center; padding: 10px;">
                            <i class="fas fa-plus-circle me-2"></i> Add New Package
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=ybt-process-packing'); ?>" class="button button-secondary" style="text-align: center; justify-content: center; display: flex; align-items: center; padding: 10px;">
                            <i class="fas fa-box-open me-2"></i> Process Packing Requests
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=ybt-payment-evidence'); ?>" class="button button-secondary" style="text-align: center; justify-content: center; display: flex; align-items: center; padding: 10px;">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Verify Payments
                        </a>
                         <button class="button button-secondary" onclick="ybtExportData()" style="text-align: center; justify-content: center; display: flex; align-items: center; padding: 10px;">
                            <i class="fas fa-download me-2"></i> Export All Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Admin Tools -->
            <div class="glass-card">
                 <div class="info-card-header">
                    <span><i class="fas fa-sliders-h me-2 text-info"></i><?php _e('System Tools', 'ybt-packer'); ?></span>
                </div>
                 <div class="info-card-body">
                     <ul class="list-unstyled mb-0 d-grid gap-2">
                         <li><a href="<?php echo admin_url('admin.php?page=ybt-settings'); ?>" class="text-decoration-none d-block p-2 rounded hover-bg-light"><i class="fas fa-cog me-2 text-muted"></i> Plugin Settings</a></li>
                         <li><a href="<?php echo admin_url('admin.php?page=ybt-user-summary'); ?>" class="text-decoration-none d-block p-2 rounded hover-bg-light"><i class="fas fa-users me-2 text-muted"></i> User Profiles</a></li>
                         <li><a href="<?php echo admin_url('admin.php?page=ybt-messages'); ?>" class="text-decoration-none d-block p-2 rounded hover-bg-light"><i class="fas fa-bullhorn me-2 text-muted"></i> Broadcast Message</a></li>
                     </ul>
                 </div>
            </div>
        </div>
    </div>
</div>

<script>
// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';

jQuery(document).ready(function($) {
    // Fix whitespace at top
    setTimeout(function() {
        const firstElement = $('#wpbody-content').children().first();
        if (firstElement.length && firstElement.offset().top > 50) {
            firstElement.css('margin-top', '-76px');
        }
    }, 100);

    // Database Fix Handler
    $('#ybt-fix-db').click(function() {
        const btn = $(this);
        const status = $('#ybt-fix-status');

        if (!confirm('This will attempt to create any missing YBT database tables. It will not delete existing data. Proceed?')) return;

        btn.prop('disabled', true).addClass('button-disabled');
        status.text('Running setup...').css('color', '#0073aa');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_recreate_tables',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    status.text('Success! Tables created. Reloading...').css('color', '#46b450');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    status.text('Error: ' + (response.data || 'Failed to setup tables.')).css('color', '#dc3545');
                    btn.prop('disabled', false).removeClass('button-disabled');
                }
            },
            error: function() {
                status.text('Network error. Check server logs.').css('color', '#dc3545');
                btn.prop('disabled', false).removeClass('button-disabled');
            }
        });
    });

    // Export Data Function
    window.ybtExportData = function() {
        console.log('[YBT Dashboard] Export data requested...');
        const downloadUrl = ybt_admin.ajax_url + '?action=ybt_export_data&nonce=' + ybt_admin.nonce;
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = 'ybt-data-' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };
});
</script>
