<?php
if (!defined('ABSPATH')) exit;

// NOTE: Authentication redirect is handled by YBT_Core::handle_authentication_redirects()
// via 'template_redirect' hook at priority 5. Do NOT add wp_redirect() here.

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
global $wpdb;

// SECURITY FIX: Properly prepared query with pagination
$per_page = 50; // Limit to prevent overload
$items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id IS NULL OR user_id = 0 OR username = 'anonymous' OR username = 'Anonymous')
    ORDER BY date_added DESC
    LIMIT %d",
    $per_page
));

// ====== DASHBOARD SETTINGS + CSP (unchanged) ======
add_action('wp_head', function() {
    echo '<style>
        #wpadminbar { display: none !important; }
        body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        html { margin-top: 0 !important; }
    </style>';
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: blob: https:; connect-src \'self\' https:;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);
?>

<style>
/* ================================================================
   UNCLAIMED PARCELS REGISTER — page-specific layer
   Shared tokens & atoms come from assets/css/ybt-consignment.css.
   ================================================================ */
.ybt-anon{
	margin:clamp(16px,3vw,30px) auto;
	max-width:900px;
	padding:0;
}
/* masked tracking chip */
.ybt-anon .ybt-masked{
	font-family:var(--mono);
	font-size:13px;
	font-weight:600;
	letter-spacing:.06em;
	color:#5c5543;
	background:#fbf8f1;
	padding:4px 10px;
	border-radius:6px;
	border:1.5px dashed var(--kraft-deep);
	white-space:nowrap;
}
/* description cell */
.ybt-anon .ybt-anondesc{ color:#3d4a42; font-size:13px; }
.ybt-anon .ybt-anondesc .none{ color:#8b968f; font-style:italic; }
.ybt-anon .ybt-anondesc i{ color:var(--gold-deep); margin-left:6px; }
.ybt-anon .ybt-anondate{
	font-family:var(--mono);
	font-size:12.5px;
	color:#5c5543;
	white-space:nowrap;
}
.ybt-anon .ybt-anondate i{ margin-right:6px; color:var(--kraft-deep); }
/* image indicator only when description empty */
</style>

<div class="ybt-dashboard-enhanced ybt-anon">

	<!-- register head -->
	<div class="ybd-pagehead">
		<div>
			<p class="ybd-eyebrow">Lost luggage office &middot; Unclaimed parcels</p>
			<h2>Parcels waiting for an owner</h2>
			<p class="ybd-sub">These parcels reached our warehouse without a matching account. Recognize one? Claim it with the full tracking number.</p>
		</div>
		<span class="ybd-count<?php echo $items ? '' : ' ybd-count-gold'; ?>"><?php echo count($items); ?> unclaimed</span>
	</div>

	<!-- security note -->
	<div class="ybd-note">
		<i class="fas fa-shield-alt"></i>
		<div>
			<strong>Tracking numbers are masked</strong>
			<span>Enter the full number on the claim page to match a parcel to your account — nothing is exposed to other visitors.</span>
		</div>
	</div>

	<!-- register panel -->
	<div class="ybd-panel" style="margin-top:20px;">
		<div class="ybd-panelhead">
			<span class="ybd-hint"><i class="fas fa-box-open"></i>Warehouse register</span>
			<a class="ybd-ghostlink" href="<?php echo esc_url(home_url('/dashboard/')); ?>"><i class="fas fa-arrow-left"></i> Dashboard</a>
		</div>

		<?php if ($items): ?>
		<div class="ybd-tablewrap">
			<table class="ybd-manifest">
				<thead>
					<tr>
						<th>Tracking number</th>
						<th>Description / details</th>
						<th style="text-align:right;">Date arrived</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($items as $item):
						// SECURITY FIX: Mask tracking number safely
						$full_tracking = esc_html($item->tracking_number);
						$masked = substr($full_tracking, 0, 2) . '&bull;&bull;&bull;&bull;&bull;&bull;' . substr($full_tracking, -4);
					?>
					<tr>
						<td><span class="ybt-masked" title="Full number hidden for security"><?php echo $masked; ?></span></td>
						<td class="ybt-anondesc">
							<?php if (!empty($item->description)): ?>
								<?php echo esc_html($item->description); ?><?php if (!empty($item->images)): ?><i class="fas fa-image" title="Has image"></i><?php endif; ?>
							<?php else: ?>
								<span class="none">No description</span><?php if (!empty($item->images)): ?><i class="fas fa-image" title="Has image"></i><?php endif; ?>
							<?php endif; ?>
						</td>
						<td class="ybt-anondate" style="text-align:right;"><i class="far fa-calendar-alt"></i><?php echo esc_html(date('M j, Y', strtotime($item->date_added))); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- claim CTA -->
		<div style="padding:18px;border-top:2px dashed var(--kraft-line);text-align:center;">
			<p style="margin:0 0 12px;color:#5a6a61;font-size:14px;">Recognize a parcel above?</p>
			<a href="<?php echo esc_url(home_url('/add-tracking/')); ?>" class="ybd-btn ybd-btn-gold"><i class="fas fa-search-plus"></i>Go to claim page</a>
		</div>
		<?php else: ?>

		<!-- empty state -->
		<div class="ybd-empty">
			<div class="ybd-emptyicon"><i class="fas fa-box-open"></i></div>
			<h4>All caught up</h4>
			<p>No unclaimed anonymous parcels in the warehouse right now.</p>
			<a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="ybd-btn ybd-btn-emerald">Return to dashboard</a>
		</div>
		<?php endif; ?>
	</div>
</div>
