<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

global $wpdb;

// Enqueue Enhanced CSS

// Get user filter
$selected_user = isset($_GET['user']) ? sanitize_text_field($_GET['user']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Build query conditions
$where_conditions = array();
$where_values = array();

if ($selected_user && $selected_user !== 'all') {
    if ($selected_user === 'anonymous') {
        $where_conditions[] = "(user_id IS NULL AND (username IS NULL OR username = '' OR username = 'N/A' OR username = 'Anonymous'))";
    } else {
        $where_conditions[] = "username = %s";
        $where_values[] = $selected_user;
    }
}

if ($status_filter) {
    $where_conditions[] = "status = %s";
    $where_values[] = $status_filter;
}

// Display-name mapping in SQL (mirrors the PHP mapping used below)
$display_case = "CASE WHEN username IS NULL OR username = '' OR username = 'N/A' OR username = 'Anonymous' THEN 'Anonymous' ELSE username END";

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

/*
 * Per-user aggregate: counts scale with distinct users × statuses, not table rows.
 * (Was: SELECT * over the entire tracking table — a 12MB page at 10k parcels.)
 */
$agg_query = "SELECT {$display_case} AS display_name, status, COUNT(*) AS status_total
              FROM {$wpdb->prefix}ybt_tracking $where_clause
              GROUP BY display_name, status";
$agg_rows = !empty($where_values)
    ? $wpdb->get_results($wpdb->prepare($agg_query, $where_values))
    : $wpdb->get_results($agg_query);

$total_matching = 0;
$global_counts  = array('not_received' => 0, 'received' => 0, 'packing' => 0, 'completed' => 0);
$users_data = array();
foreach ($agg_rows as $row) {
    $username = $row->display_name;
    if (!isset($users_data[$username])) {
        $users_data[$username] = array(
            'total' => 0,
            'not_received' => 0,
            'received' => 0,
            'packing_in_progress' => 0,
            'packed' => 0,
            'completed' => 0,
            'items' => array()
        );
    }
    $count = (int) $row->status_total;
    $users_data[$username]['total'] += $count;
    if (!isset($users_data[$username][$row->status])) {
        $users_data[$username][$row->status] = 0;
    }
    $users_data[$username][$row->status] += $count;
    $total_matching += $count;

    if ($row->status === 'not_received')      $global_counts['not_received'] += $count;
    elseif ($row->status === 'received')      $global_counts['received'] += $count;
    elseif ($row->status === 'packed' || $row->status === 'packing_in_progress') $global_counts['packing'] += $count;
    elseif ($row->status === 'completed')     $global_counts['completed'] += $count;
}
ksort($users_data);

/*
 * Bounded item listing: explicit columns + hard cap (per-user filter gets a
 * larger window). The header counts come from the aggregate above, so they
 * stay accurate even when this list is truncated.
 */
$item_limit = ($selected_user && $selected_user !== 'all') ? 2000 : 500;
$items_query = "SELECT id, tracking_number, username, status, description, item_count, date_added, date_completed
                FROM {$wpdb->prefix}ybt_tracking $where_clause
                ORDER BY date_added DESC LIMIT %d";

if (!empty($where_values)) {
    $tracking_items = $wpdb->get_results($wpdb->prepare($items_query, array_merge($where_values, array($item_limit))));
} else {
    $tracking_items = $wpdb->get_results($wpdb->prepare($items_query, $item_limit));
}

// Get all users for filter dropdown
$all_users = $wpdb->get_results("SELECT DISTINCT username FROM {$wpdb->prefix}ybt_tracking WHERE username IS NOT NULL ORDER BY username");

// Distribute the bounded item list into their user groups
foreach ($tracking_items as $item) {
    $raw_username = $item->username;
    $username = (!$raw_username || $raw_username === '' || $raw_username === 'N/A' || $raw_username === 'Anonymous') ? 'Anonymous' : $raw_username;
    if (isset($users_data[$username])) {
        $users_data[$username]['items'][] = $item;
    }
}
?>

<div class="wrap ybt-admin-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="wp-heading-inline" style="font-size: 2rem; color: var(--text-main);"><i class="fas fa-chart-pie me-2 text-primary"></i> <?php _e('User Summaries', 'ybt-packer'); ?></h1>
    </div>

    <div class="ybt-admin-container">
        <!-- Filters -->
        <div class="glass-card mb-4" style="overflow: visible;">
            <div class="info-card-header bg-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-filter me-2 text-primary"></i>Analytics Filters</span>
            </div>
            <div class="info-card-body">
                <form method="GET" class="filter-form d-flex gap-3 align-items-end flex-wrap">
                    <input type="hidden" name="page" value="ybt-user-summary">

                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label small fw-bold text-muted text-uppercase">User</label>
                        <select name="user" class="w-100">
                            <option value="all">All Users</option>
                            <option value="anonymous" <?php selected($selected_user, 'anonymous'); ?>>Anonymous</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo esc_attr($user->username); ?>" <?php selected($selected_user, $user->username); ?>>
                                    <?php echo esc_html($user->username); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label small fw-bold text-muted text-uppercase">Status</label>
                        <select name="status" class="w-100">
                            <option value="">All Statuses</option>
                            <option value="not_received" <?php selected($status_filter, 'not_received'); ?>>Not Received</option>
                            <option value="received" <?php selected($status_filter, 'received'); ?>>Received</option>
                            <option value="packed" <?php selected($status_filter, 'packed'); ?>>Packed</option>
                            <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="button button-primary">Apply Filters</button>
                        <a href="<?php echo admin_url('admin.php?page=ybt-user-summary'); ?>" class="button">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="glass-card mb-4">
            <div class="info-card-header bg-white">
                <span><i class="fas fa-globe me-2 text-secondary"></i>Global Overview</span>
            </div>
            <div class="info-card-body">
                <div class="stats-grid">
                    <div class="admin-stat-card">
                        <div class="stat-icon bg-primary-light text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($users_data); ?></h3>
                            <p>Active Users</p>
                        </div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="stat-icon bg-success-light text-success">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo (int) $total_matching; ?></h3>
                            <p>Total Items</p>
                        </div>
                    </div>

                    <!-- Breakdown -->
                    <div class="admin-stat-card">
                        <div class="stat-content w-100">
                             <div class="d-flex justify-content-between mb-1">
                                 <span class="text-danger small fw-bold">Not Received</span>
                                 <span class="fw-bold"><?php echo (int) $global_counts['not_received']; ?></span>
                             </div>
                             <div class="d-flex justify-content-between mb-1">
                                 <span class="text-warning small fw-bold" style="color:#DAA520!important;">Received</span>
                                 <span class="fw-bold"><?php echo (int) $global_counts['received']; ?></span>
                             </div>
                             <div class="d-flex justify-content-between mb-1">
                                 <span class="text-info small fw-bold">Packed</span>
                                 <span class="fw-bold"><?php echo (int) $global_counts['packing']; ?></span>
                             </div>
                             <div class="d-flex justify-content-between">
                                 <span class="text-success small fw-bold">Completed</span>
                                 <span class="fw-bold"><?php echo (int) $global_counts['completed']; ?></span>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Truncation Notice -->
        <?php if ($total_matching > $item_limit): ?>
            <div class="glass-card mb-4" style="border-left: 4px solid #f0b849;">
                <div class="info-card-body d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                    <span class="small text-muted">
                        <i class="fas fa-triangle-exclamation me-2" style="color:#f0b849;"></i>
                        Showing the <?php echo (int) $item_limit; ?> most recent of <?php echo (int) $total_matching; ?> parcels. Narrow by user or status to reach older items.
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- User Details -->
        <?php if ($users_data): ?>
            <div class="glass-card">
                <div class="info-card-header bg-white">
                    <span><i class="fas fa-list me-2 text-muted"></i>Detailed User Breakdown</span>
                </div>
                <div class="info-card-body p-0">
                    <?php foreach ($users_data as $username => $user_data): ?>
                        <div class="user-group border-bottom">
                            <div class="user-header d-flex justify-content-between align-items-center p-3" style="cursor: pointer;" onclick="toggleUserDetails('<?php echo esc_js(wp_hash($username)); ?>')">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <i class="fas fa-user-circle fa-2x text-muted"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0 fw-bold"><?php echo esc_html($username); ?></h4>
                                        <span class="text-muted small"><?php echo $user_data['total']; ?> Total Shipments</span>
                                    </div>
                                </div>
                                <div class="user-stats-summary d-flex gap-3">
                                    <?php if($user_data['not_received']): ?>
                                        <span class="badge bg-danger-subtle text-danger small"><?php echo $user_data['not_received']; ?> Pending</span>
                                    <?php endif; ?>
                                    <?php if($user_data['received']): ?>
                                        <span class="badge bg-warning-subtle text-warning small"><?php echo $user_data['received']; ?> Received</span>
                                    <?php endif; ?>
                                    <?php if($user_data['packing_in_progress'] || $user_data['packed']): ?>
                                        <span class="badge bg-info-subtle text-info small"><?php echo $user_data['packing_in_progress'] + $user_data['packed']; ?> Packing</span>
                                    <?php endif; ?>
                                    <?php if($user_data['completed']): ?>
                                        <span class="badge bg-success-subtle text-success small"><?php echo $user_data['completed']; ?> Sent</span>
                                    <?php endif; ?>
                                    <i class="fas fa-chevron-down ms-2 text-muted toggle-icon"></i>
                                </div>
                            </div>
                            <div class="user-details bg-light p-0" id="user-details-<?php echo esc_attr(wp_hash($username)); ?>" style="display: none;">
                                <table class="table admin-table mb-0" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Tracking #</th>
                                            <th>Status</th>
                                            <th>Description</th>
                                            <th>Count</th>
                                            <th>Dates</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_data['items'] as $item): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($item->tracking_number); ?></strong></td>
                                                <td>
                                                    <?php
                                                    $s_class = 'secondary';
                                                    if($item->status=='received') $s_class='warning';
                                                    if($item->status=='packed') $s_class='info';
                                                    if($item->status=='completed') $s_class='success';
                                                    if($item->status=='not_received') $s_class='danger';
                                                    ?>
                                                    <span class="badge-glass text-<?php echo $s_class; ?>">
                                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $item->status))); ?>
                                                    </span>
                                                </td>
                                                <td class="text-muted small"><?php echo esc_html($item->description ?: '-'); ?></td>
                                                <td><?php echo esc_html($item->item_count ?: '1'); ?></td>
                                                <td>
                                                    <div class="d-flex flex-column small">
                                                        <span class="text-muted">Added: <?php echo date('M j', strtotime($item->date_added)); ?></span>
                                                        <?php if ($item->date_completed): ?>
                                                            <span class="text-success">Done: <?php echo date('M j', strtotime($item->date_completed)); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
             <div class="glass-card text-center p-5">
                <i class="fas fa-search fa-3x mb-3 text-muted opacity-25"></i>
                <h3 class="text-muted">No Data Found</h3>
                <p class="text-muted">Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Specific Overrides */
.user-header:hover { background: #f8fafc; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
.admin-stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 15px; }
.stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
.bg-primary-light { background: rgba(13, 110, 253, 0.1); }
.bg-success-light { background: rgba(25, 135, 84, 0.1); }
</style>

<script>
// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';

function toggleUserDetails(username_md5) {
    const containerId = 'user-details-' + username_md5;
    const details = document.getElementById(containerId);
    if (!details) return;

    const header = event.currentTarget;
    const icon = header.querySelector('.toggle-icon');

    if (details.style.display === 'none') {
        jQuery(details).slideDown(200);
        if (icon) icon.style.transform = 'rotate(180deg)';
    } else {
        jQuery(details).slideUp(200);
        if (icon) icon.style.transform = 'rotate(0deg)';
    }
}

// Auto-expand if filtering by specific user
<?php if ($selected_user && $selected_user !== 'all' && $selected_user !== 'anonymous'): ?>
document.addEventListener('DOMContentLoaded', function() {
    const username_md5 = '<?php echo esc_js(wp_hash($selected_user)); ?>';
    const details = document.getElementById('user-details-' + username_md5);
    if (details) {
        details.style.display = 'block';
        const header = details.previousElementSibling;
        if (header) {
            const icon = header.querySelector('.toggle-icon');
            if(icon) icon.style.transform = 'rotate(180deg)';
        }
    }
});
<?php endif; ?>
</script>
