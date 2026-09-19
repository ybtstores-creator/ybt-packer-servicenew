<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

global $wpdb;
$per_page = 50;
$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$offset = ($page - 1) * $per_page;

$total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_activity_log");
$activities = $wpdb->get_results($wpdb->prepare(
    "SELECT a.*, u.user_login FROM {$wpdb->prefix}ybt_activity_log a
     LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
     ORDER BY a.date_created DESC LIMIT %d OFFSET %d",
    $per_page, $offset
));

/**
 * Tracking edit trail — which admin edited which tracking number, and what changed.
 * Backed by the ybt_tracking_edits audit table.
 */
$audit_available  = class_exists('YBT_Tracking_Audit');
$edit_admin       = isset($_GET['edit_admin']) ? sanitize_text_field(wp_unslash($_GET['edit_admin'])) : '';
$edit_page        = isset($_GET['edit_page']) ? max(1, intval($_GET['edit_page'])) : 1;
$edit_per_page    = 50;
$tracking_edits   = $audit_available ? YBT_Tracking_Audit::get_recent_edits($edit_per_page, ($edit_page - 1) * $edit_per_page, $edit_admin) : array();
$editing_admins   = $audit_available ? YBT_Tracking_Audit::get_editing_admins() : array();
$total_tracking_edits = 0;
if ($audit_available) {
    $edit_table = $wpdb->prefix . 'ybt_tracking_edits';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $edit_table)) === $edit_table) {
        $total_tracking_edits = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$edit_table}");
    }
}
$total_edit_pages = $edit_per_page > 0 ? ceil($total_tracking_edits / $edit_per_page) : 1;
?>
<div class="wrap ybt-admin-wrapper">
    <h1 class="ybt-page-title">Activity Log</h1>

    <div class="ybt-card">
    <table class="ybt-table">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($activities): ?>
                <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td><?php echo esc_html(date('M j, Y g:i A', strtotime($activity->date_created))); ?></td>
                        <td><?php echo esc_html($activity->user_login ?: 'Guest'); ?></td>
                        <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $activity->action))); ?></strong></td>
                        <td><?php echo esc_html($activity->details); ?></td>
                        <td><?php echo esc_html($activity->ip_address); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No activity logs found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    $total_pages = ceil($total / $per_page);
    if ($total_pages > 1):
    ?>
    <div class="tablenav">
        <div class="tablenav-pages">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $page
            ));
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($audit_available): ?>
    <div class="ybt-card" style="margin-top: 30px;">
        <h2 style="margin-top:0;">🕓 Tracking Edit Trail <span class="text-muted" style="font-size:14px;font-weight:400;">(<?php echo number_format($total_tracking_edits); ?> recorded edits)</span></h2>
        <p class="description" style="margin-top:-6px;">
            Every change made to a tracking number, showing <strong>which administrator</strong> made it,
            <strong>what changed</strong> (old → new), and <strong>when</strong>.
        </p>

        <form method="get" style="margin-bottom: 12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="page" value="ybt-activity-log">
            <label for="edit_admin" style="font-weight:600;">Filter by admin:</label>
            <select name="edit_admin" id="edit_admin">
                <option value="">All administrators</option>
                <?php foreach ($editing_admins as $admin_name => $edit_total): ?>
                    <option value="<?php echo esc_attr($admin_name); ?>" <?php selected($edit_admin, $admin_name); ?>>
                        <?php echo esc_html($admin_name . ' (' . $edit_total . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-primary">Apply</button>
            <?php if ($edit_admin !== ''): ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ybt-activity-log')); ?>">Clear</a>
            <?php endif; ?>
        </form>

        <table class="ybt-table">
            <thead>
                <tr>
                    <th style="width:150px;">Date/Time</th>
                    <th style="width:170px;">Administrator</th>
                    <th style="width:150px;">Tracking Number</th>
                    <th style="width:150px;">Action</th>
                    <th>What Changed (old → new)</th>
                    <th style="width:110px;">Source</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tracking_edits): ?>
                    <?php foreach ($tracking_edits as $edit): ?>
                        <tr>
                            <td><?php echo esc_html(date('M j, Y g:i A', strtotime($edit->date_created))); ?></td>
                            <td>
                                <span class="badge bg-light border text-dark" style="font-weight:600;">
                                    <i class="fas fa-user-shield text-primary me-1"></i><?php echo esc_html($edit->admin_name ?: 'System'); ?>
                                </span>
                            </td>
                            <td><strong><?php echo esc_html($edit->tracking_number ?: ('#' . $edit->tracking_id)); ?></strong></td>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $edit->action))); ?></strong></td>
                            <td>
                                <?php if (!empty($edit->changes) && is_array($edit->changes)): ?>
                                    <ul style="margin:0; padding-left:16px;">
                                        <?php foreach ($edit->changes as $change): ?>
                                            <li>
                                                <?php echo esc_html($change['label'] ?? $change['field'] ?? 'Field'); ?>:
                                                <span style="color:#b32d2e; text-decoration: line-through;"><?php echo esc_html($change['from'] ?? '—'); ?></span>
                                                →
                                                <span style="color:#1d7a3c; font-weight:600;"><?php echo esc_html($change['to'] ?? '—'); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <?php echo esc_html($edit->summary ?: '—'); ?>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo esc_html($edit->source ?: 'admin'); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No tracking edits recorded yet. Changes made from the Tracking Management page will appear here.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_edit_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('edit_page', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_edit_pages,
                    'current' => $edit_page,
                    'add_args' => $edit_admin !== '' ? array('edit_admin' => $edit_admin) : array(),
                ));
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (current_user_can('manage_options')): ?>
    <div class="postbox" style="margin-top: 30px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); background: #fff;">
        <div class="postbox-header" style="border-bottom: 1px solid #ccd0d4; padding: 10px 15px; background: #f6f7f7;">
            <h2 class="hndle" style="margin: 0; font-size: 14px; line-height: 1.4;">🛠️ Activity Log Troubleshooting & Debugging</h2>
        </div>
        <div class="inside" style="padding: 15px; margin: 0;">
            <table class="form-table" style="margin: 0;">
                <tr>
                    <th scope="row" style="width: 200px;">WordPress Debug Status</th>
                    <td>
                        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                            <span style="color: #2271b1; font-weight: 600;">✅ Enabled</span>
                        <?php else: ?>
                            <span style="color: #d63638; font-weight: 600;">❌ Disabled</span>
                            <p class="description">Set <code>define('WP_DEBUG', true);</code> in <code>wp-config.php</code> to see detailed errors.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Error Logging</th>
                    <td>
                        <div style="margin-bottom: 5px;">
                            <strong>PHP Error Log:</strong> <code><?php echo esc_html(ini_get('error_log') ?: 'Not set'); ?></code>
                        </div>
                        <div>
                            <strong>WP Debug Log:</strong>
                            <?php if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG): ?>
                                <span style="color: green;">Active</span> (<code>wp-content/debug.log</code>)
                            <?php else: ?>
                                <span style="color: #646970;">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Database Table Status</th>
                    <td>
                        <?php
                        $table_name = $wpdb->prefix . 'ybt_activity_log';
                        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
                        if ($table_exists):
                            $table_info = $wpdb->get_results("DESCRIBE $table_name");
                            $columns = wp_list_pluck($table_info, 'Field');
                            $required = array('id', 'date_created', 'user_id', 'action', 'details', 'ip_address');
                            $missing = array_diff($required, $columns);
                        ?>
                            <div style="color: green; font-weight: 600; margin-bottom: 5px;">✅ Table <code><?php echo esc_html($table_name); ?></code> exists.</div>
                            <div class="small-text">
                                <strong>Structure Check:</strong>
                                <?php if (empty($missing)): ?>
                                    <span style="color: green;">All required columns present.</span>
                                <?php else: ?>
                                    <span style="color: #d63638;">Missing columns: <?php echo implode(', ', $missing); ?></span>
                                <?php endif; ?>
                                <br>
                                <small style="color: #646970;">Detected Columns: <?php echo implode(', ', $columns); ?></small>
                            </div>
                        <?php else: ?>
                            <div style="color: #d63638; font-weight: 600;">❌ Table <code><?php echo esc_html($table_name); ?></code> is missing!</div>
                            <p class="description">Try deactivating and reactivating the plugin to trigger table creation.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Connection Test</th>
                    <td>
                        <?php
                        // Simple connectivity test
                        $db_test = $wpdb->get_var("SELECT 1");
                        if ($db_test == 1):
                            $user_test = $wpdb->get_row("SELECT ID, user_login FROM {$wpdb->users} LIMIT 1");
                        ?>
                            <div style="color: green; font-weight: 600;">✅ Database Connection OK</div>
                            <?php if ($user_test): ?>
                                <p class="description">Successfully queried <code><?php echo esc_html($wpdb->users); ?></code> table (Found user: <strong><?php echo esc_html($user_test->user_login); ?></strong>).</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="color: #d63638; font-weight: 600;">❌ Database connection problematic.</div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Data Integrity</th>
                    <td>
                        <strong>Row Count:</strong> <?php echo number_format($total); ?> records.<br>
                        <?php if ($total > 0 && !$activities): ?>
                            <span style="color: #d63638;">⚠️ Warning: Count is <?php echo $total; ?> but query returned no results. Check pagination/offset logic.</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
