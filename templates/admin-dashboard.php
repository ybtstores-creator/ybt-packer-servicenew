<?php
/**
 * Professional Admin Dashboard Template - Enhanced
 * YBT Packing Service v2.1
 *
 * Performance optimizations:
 * - Uses cached stats from ybt_get_dashboard_stats()
 * - Cached recent activity queries
 * - Lazy loading for images
 *
 * Redesigned on the consignment-note system (.ybt-ledger scope).
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

// Chip class per status (the five real statuses, shared atom)
if (!function_exists('ybt_admin_ledger_chip')) {
    function ybt_admin_ledger_chip($status) {
        $allowed = array('not_received', 'received', 'packing_in_progress', 'packed', 'completed');
        return in_array($status, $allowed, true) ? 'ybd-chip ybd-chip-' . $status : 'ybd-chip';
    }
}
?>

<style>
/* ================================================================
   ADMIN CONSOLE — "The depot ledger" (page-specific layer)
   Shared tokens & atoms come from assets/css/ybt-consignment.css
   (scope root: .ybt-ledger).
   ================================================================ */
.ybt-admin-wrapper .ybt-ledger{ padding:clamp(16px,2.5vw,30px); }
/* tally strip: the six figures */
.ybt-ledger .ybt-tally{
	display:grid;
	grid-template-columns:repeat(6,1fr);
	gap:14px;
	margin-bottom:clamp(18px,2.5vw,26px);
}
.ybt-ledger .ybt-tallycard{
	background:var(--paper);
	border:1.5px solid var(--kraft-line);
	border-radius:10px;
	padding:14px 16px;
}
.ybt-ledger .ybt-tallycard.ybt-tally-ink{ border-color:var(--ink); box-shadow:4px 4px 0 rgba(15,31,23,.12); }
.ybt-ledger .ybt-tallycard .ybt-tallylabel{
	font-family:var(--mono);
	font-size:10px;font-weight:600;
	letter-spacing:.14em;text-transform:uppercase;
	color:#5c5543;
	display:flex;align-items:center;gap:7px;
	margin-bottom:6px;
}
.ybt-ledger .ybt-tallycard .ybt-tallylabel i{ font-size:12px; }
.ybt-ledger .ybt-tallycard .ybt-tallyno{
	font-family:var(--mono);
	font-weight:600;
	font-size:clamp(1.35rem,2.2vw,1.75rem);
	color:var(--ink);
	line-height:1;
}
.ybt-ledger .ybt-tally-not_received .ybt-tallylabel{ color:var(--kraft-deep); }
.ybt-ledger .ybt-tally-not_received .ybt-tallyno{ color:#5c5543; }
.ybt-ledger .ybt-tally-received .ybt-tallylabel{ color:var(--emerald); }
.ybt-ledger .ybt-tally-received .ybt-tallyno{ color:var(--emerald-deep); }
.ybt-ledger .ybt-tally-packing_in_progress .ybt-tallylabel{ color:var(--gold); }
.ybt-ledger .ybt-tally-packing_in_progress .ybt-tallyno{ color:var(--gold-deep); }
.ybt-ledger .ybt-tally-packed .ybt-tallylabel{ color:var(--emerald); }
.ybt-ledger .ybt-tally-packed .ybt-tallyno{ color:var(--emerald); }
.ybt-ledger .ybt-tally-completed .ybt-tallylabel{ color:var(--emerald-deep); }
.ybt-ledger .ybt-tally-completed .ybt-tallyno{ color:var(--emerald-deep); }
/* two-column working area */
.ybt-ledger .ybt-ledgergrid{
	display:grid;
	grid-template-columns:1.9fr 1fr;
	gap:18px;
	align-items:start;
}
.ybt-ledger .ybt-ledgercol{ display:flex; flex-direction:column; gap:18px; min-width:0; }
/* two-column working area */
.ybt-ledger .ybt-qastack{ display:flex; flex-direction:column; gap:10px; }
.ybt-ledger .ybt-qastack .ybd-btn{ width:100%; }
/* system tools list */
.ybt-ledger .ybt-toollist{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:4px; }
.ybt-ledger .ybt-toollist a{
	display:flex;align-items:center;gap:10px;
	padding:10px 12px;
	border-radius:8px;
	color:var(--ink);
	font-weight:600;
	font-size:14px;
	text-decoration:none;
}
.ybt-ledger .ybt-toollist a i{ color:#5c5543; width:16px; text-align:center; }
.ybt-ledger .ybt-toollist a:hover{ background:#fbf8f1; }
.ybt-ledger .ybt-toollist a:hover i{ color:var(--emerald-deep); }
/* activity manifest cells */
.ybt-ledger .ybt-who{ display:flex; align-items:center; gap:9px; }
.ybt-ledger .ybt-who .ybt-av {
	width:26px;height:26px;flex:0 0 26px;
	display:flex;align-items:center;justify-content:center;
	border-radius:50%;
	background:var(--kraft);
	border:1px solid var(--kraft-deep);
	font-family:var(--mono);
	font-size:10.5px;font-weight:600;
	color:#5c5543;
}
.ybt-ledger .ybt-who .anon{ color:#8b968f; font-style:italic; }
.ybt-ledger .ybt-actdesc{ display:block; color:#5a6a61; font-size:12px; margin-top:2px; }
/* db-repair notice in-system */
.ybt-ledger .ybt-dbalert{
	display:flex;align-items:flex-start;gap:14px;
	background:#fdeeee;
	border:1px solid #f3c9c9;
	border-left-width:5px;
	border-radius:0 10px 10px 0;
	padding:16px 18px;
	margin-bottom:18px;
}
.ybt-ledger .ybt-dbalert i{ color:#9c2020; font-size:20px; margin-top:2px; }
.ybt-ledger .ybt-dbalert strong{ display:block; color:#9c2020; margin-bottom:2px; }
@media (max-width:1200px){
	.ybt-ledger .ybt-tally{ grid-template-columns:repeat(3,1fr); }
}
@media (max-width:992px){
	.ybt-ledger .ybt-ledgergrid{ grid-template-columns:1fr; }
}
@media (max-width:560px){
	.ybt-ledger .ybt-tally{ grid-template-columns:repeat(2,1fr); }
}
</style>

<div class="ybt-ledger">

	<!-- head -->
	<div class="ybt-ledgerhead">
		<div>
			<p class="ybd-eyebrow">Depot ledger &middot; YBT management console</p>
			<h1><?php _e('Operations overview', 'ybt-packer'); ?></h1>
			<p><?php _e('Every parcel in the pipeline, from expected arrival to dispatch.', 'ybt-packer'); ?></p>
		</div>
		<button class="ybd-btn ybd-btn-dark" onclick="location.reload()">
			<i class="fas fa-sync-alt"></i><?php _e('Refresh data', 'ybt-packer'); ?>
		</button>
	</div>

	<?php if ($stats_error): ?>
	<div class="ybt-dbalert" id="ybt-db-notice">
		<i class="fas fa-triangle-exclamation"></i>
		<div>
			<strong><?php _e('Database status alert:', 'ybt-packer'); ?></strong>
			<span><?php echo esc_html($error_message); ?></span>
			<?php if ($tables_missing): ?>
				<div style="margin-top:10px;">
					<button id="ybt-fix-db" class="ybd-btn ybd-btn-gold">
						<i class="fas fa-tools"></i><?php _e('Attempt database repair / setup', 'ybt-packer'); ?>
					</button>
					<span id="ybt-fix-status" style="margin-left:10px;font-weight:700;"></span>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- tally strip -->
	<div class="ybt-tally">
		<div class="ybt-tallycard ybt-tally-not_received">
			<span class="ybt-tallylabel"><i class="fas fa-clock"></i><?php _e('Pending', 'ybt-packer'); ?></span>
			<span class="ybt-tallyno"><?php echo number_format($stats['not_received']); ?></span>
		</div>
		<div class="ybt-tallycard ybt-tally-received">
			<span class="ybt-tallylabel"><i class="fas fa-warehouse"></i><?php _e('Received', 'ybt-packer'); ?></span>
			<span class="ybt-tallyno"><?php echo number_format($stats['received']); ?></span>
		</div>
		<div class="ybt-tallycard ybt-tally-packing_in_progress">
			<span class="ybt-tallylabel"><i class="fas fa-cogs"></i><?php _e('Packing', 'ybt-packer'); ?></span>
			<span class="ybt-tallyno"><?php echo number_format($stats['packing_in_progress']); ?></span>
		</div>
		<div class="ybt-tallycard ybt-tally-packed">
			<span class="ybt-tallylabel"><i class="fas fa-box"></i><?php _e('Ready', 'ybt-packer'); ?></span>
			<span class="ybt-tallyno"><?php echo number_format($stats['packed']); ?></span>
		</div>
		<div class="ybt-tallycard ybt-tally-completed">
			<span class="ybt-tallylabel"><i class="fas fa-check-circle"></i><?php _e('Sent', 'ybt-packer'); ?></span>
			<span class="ybt-tallyno"><?php echo number_format($stats['completed']); ?></span>
		</div>
		<div class="ybt-tallycard ybt-tally-ink">
			<span class="ybt-tallylabel"><i class="fas fa-chart-pie"></i><?php _e('Total', 'ybt-packer'); ?></span>
			<span class="ybt-tallyno"><?php echo number_format($stats['total']); ?></span>
		</div>
	</div>

	<div class="ybt-ledgergrid">
		<!-- activity manifest -->
		<div class="ybt-ledgercol">
			<div class="ybd-panel">
				<div class="ybd-panelhead">
					<span class="ybd-hint"><i class="fas fa-history"></i><?php _e('Recent activity log', 'ybt-packer'); ?></span>
					<a href="<?php echo admin_url('admin.php?page=ybt-manage-tracking'); ?>" class="ybd-ghostlink"><?php _e('View all', 'ybt-packer'); ?></a>
				</div>
				<?php if ($recent_activity): ?>
				<div class="ybd-tablewrap">
					<table class="ybd-manifest">
						<thead>
							<tr>
								<th><?php _e('Status', 'ybt-packer'); ?></th>
								<th><?php _e('Tracking #', 'ybt-packer'); ?></th>
								<th><?php _e('User', 'ybt-packer'); ?></th>
								<th><?php _e('Added', 'ybt-packer'); ?></th>
								<th style="text-align:right;"><?php _e('Actions', 'ybt-packer'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($recent_activity as $item): ?>
							<tr>
								<td><span class="<?php echo ybt_admin_ledger_chip($item->status); ?>"><?php echo esc_html(str_replace('_', ' ', ucfirst($item->status))); ?></span></td>
								<td>
									<span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span>
									<?php if($item->description): ?>
										<span class="ybt-actdesc"><?php echo esc_html(wp_trim_words($item->description, 5)); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($item->user_display_name): ?>
									<div class="ybt-who">
										<span class="ybt-av"><?php echo esc_html(strtoupper(substr($item->user_display_name, 0, 1))); ?></span>
										<?php echo esc_html($item->user_display_name); ?>
									</div>
									<?php else: ?>
									<span class="anon">Anonymous</span>
									<?php endif; ?>
								</td>
								<td class="ybd-date"><?php echo esc_html(human_time_diff(strtotime($item->date_added), current_time('timestamp')) . ' ago'); ?></td>
								<td style="text-align:right;">
									<a href="<?php echo admin_url('admin.php?page=ybt-manage-tracking&edit=' . $item->id); ?>" class="ybd-btn ybd-btn-emerald" style="padding:5px 10px;font-size:12.5px;" title="Edit">
										<i class="fas fa-edit"></i>
									</a>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php else: ?>
				<div class="ybd-empty">
					<div class="ybd-emptyicon"><i class="fas fa-inbox"></i></div>
					<h4>Nothing logged yet</h4>
					<p>No recent activity found.</p>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- side column -->
		<div class="ybt-ledgercol">
			<div class="ybd-panel">
				<div class="ybd-panelhead">
					<span class="ybd-hint"><i class="fas fa-bolt"></i><?php _e('Quick actions', 'ybt-packer'); ?></span>
				</div>
				<div class="ybd-panelbody">
					<div class="ybt-qastack">
						<a href="<?php echo admin_url('admin.php?page=ybt-add-tracking'); ?>" class="ybd-btn ybd-btn-gold">
							<i class="fas fa-plus-circle"></i><?php _e('Add new package', 'ybt-packer'); ?>
						</a>
						<a href="<?php echo admin_url('admin.php?page=ybt-process-packing'); ?>" class="ybd-btn ybd-btn-emerald">
							<i class="fas fa-box-open"></i><?php _e('Process packing requests', 'ybt-packer'); ?>
						</a>
						<a href="<?php echo admin_url('admin.php?page=ybt-payment-evidence'); ?>" class="ybd-btn ybd-btn-emerald">
							<i class="fas fa-file-invoice-dollar"></i><?php _e('Verify payments', 'ybt-packer'); ?>
						</a>
						<button class="ybd-btn ybd-btn-dark" onclick="ybtExportData()">
							<i class="fas fa-download"></i><?php _e('Export all data', 'ybt-packer'); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="ybd-panel">
				<div class="ybd-panelhead">
					<span class="ybd-hint"><i class="fas fa-sliders-h"></i><?php _e('System tools', 'ybt-packer'); ?></span>
				</div>
				<div class="ybd-panelbody">
					<ul class="ybt-toollist">
						<li><a href="<?php echo admin_url('admin.php?page=ybt-settings'); ?>"><i class="fas fa-cog"></i><?php _e('Plugin settings', 'ybt-packer'); ?></a></li>
						<li><a href="<?php echo admin_url('admin.php?page=ybt-user-summary'); ?>"><i class="fas fa-users"></i><?php _e('User profiles', 'ybt-packer'); ?></a></li>
						<li><a href="<?php echo admin_url('admin.php?page=ybt-messages'); ?>"><i class="fas fa-bullhorn"></i><?php _e('Broadcast message', 'ybt-packer'); ?></a></li>
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

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s])) : '';

jQuery(document).ready(function($) {
    // Fix whitespace at top
    setTimeout(function() {
        const firstElement = $('#wpbody-content').children().first();
        if (firstElement.length && firstElement.offset && firstElement.offset().top > 50) {
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
