<?php
/**
 * YBT User Dashboard — "The Consignment Desk"
 * Shows tracking stats, announcements, warehouse, payments, and recent activity
 *
 * Performance optimizations:
 * - Consolidated database queries
 * - Transient caching for stats
 * - Lazy loading for images
 */

if (!defined('ABSPATH')) exit;

// NOTE: Authentication redirect is handled by YBT_Core::handle_authentication_redirects()
// via 'template_redirect' hook at priority 5. Do NOT add wp_redirect() here.

global $wpdb;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$username = $current_user->user_login;
$custom_user_id = get_user_meta($user_id, 'ybt_custom_user_id', true) ?: 'Pending';

// App Update Check (cached by WordPress options)
$app_latest_version  = get_option('ybt_app_latest_version', '');
$app_download_url    = get_option('ybt_app_download_url', '');
$app_update_message  = get_option('ybt_app_update_message', '');

// ====== OPTIMIZED DATA FETCHING ======
// Single consolidated query instead of 6 separate queries
$cache_key = 'ybt_user_stats_' . $user_id . '_' . wp_hash($username);
$user_stats = ybt_cache()->remember($cache_key, function() use ($wpdb, $user_id, $username) {
    return $wpdb->get_row($wpdb->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'not_received' THEN 1 ELSE 0 END) as not_received,
            SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received,
            SUM(CASE WHEN status = 'packing_in_progress' THEN 1 ELSE 0 END) as packing_in_progress,
            SUM(CASE WHEN status = 'packed' THEN 1 ELSE 0 END) as packed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM {$wpdb->prefix}ybt_tracking
        WHERE user_id = %d OR username = %s
    ", $user_id, $username), ARRAY_A);
}, 300, 'user_dashboard'); // Cache for 5 minutes

// Extract stats with fallbacks
$stats = $user_stats ?: array_fill_keys(['total', 'not_received', 'received', 'packing_in_progress', 'packed', 'completed'], 0);
$not_received = (int) ($stats['not_received'] ?? 0);
$received = (int) ($stats['received'] ?? 0);
$packing_in_progress = (int) ($stats['packing_in_progress'] ?? 0);
$packed = (int) ($stats['packed'] ?? 0);
$completed = (int) ($stats['completed'] ?? 0);

$warehouse_settings = ybt_cache()->remember('ybt_warehouse_settings_latest', function() use ($wpdb) {
    return $wpdb->get_row("
        SELECT * FROM {$wpdb->prefix}ybt_warehouse_settings
        WHERE is_active = 1
        ORDER BY date_created DESC LIMIT 1
    ");
}, 3600, 'settings');

$payment_settings = ybt_cache()->remember('ybt_payment_settings', function() use ($wpdb) {
    return $wpdb->get_row("
        SELECT * FROM {$wpdb->prefix}ybt_payment_settings
        WHERE is_active = 1 ORDER BY date_created DESC LIMIT 1
    ");
}, 3600, 'settings');

// Admin Messages (cached for 10 minutes)
$admin_messages = ybt_cache()->remember('ybt_admin_messages_latest', function() use ($wpdb) {
    return $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}ybt_admin_messages
        ORDER BY date_created DESC LIMIT 3
    ");
}, 600, 'content');

// Recent Activity (cached for 2 minutes, per user)
$recent_activity = ybt_cache()->remember('ybt_user_recent_' . $user_id, function() use ($wpdb, $user_id, $username) {
    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ybt_tracking
        WHERE (user_id = %d OR username = %s)
        ORDER BY date_added DESC
        LIMIT 5
    ", $user_id, $username));
}, 120, 'user_dashboard');

// Add inline CSS to ensure clean top
add_action('wp_head', function() {
    echo '<style>
        /* Completely hide WordPress admin bar */
        #wpadminbar { 
            display: none !important;
        }
        
        /* Remove WordPress admin bar margins from body */
        body.admin-bar {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        html {
            margin-top: 0 !important;
        }
    </style>';
    
    // Add CSP meta tag to allow CDN resources
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: blob: https:; connect-src \'self\' https:;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);
?>

<style>
/* ================================================================
   YBT USER DASHBOARD — "The Consignment Desk" (page-specific layer)
   Shared tokens & atoms come from assets/css/ybt-consignment.css.
   ================================================================ */

.ybt-dashboard-enhanced .ybd-section{ margin-top:clamp(20px,3vw,30px); }
.ybt-dashboard-enhanced .ybd-panel{ padding:clamp(18px,3vw,28px); }
.ybt-dashboard-enhanced .ybd-panel > h2{ margin:0 0 6px; }

/* ── header: the desk strip ───────────────────────────────── */
.ybt-dashboard-enhanced .ybd-desk{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:16px;
	flex-wrap:wrap;
	background:var(--paper);
	border:1.5px solid var(--ink);
	border-radius:12px;
	padding:18px 22px;
	box-shadow:6px 6px 0 rgba(15,31,23,.12);
	animation:ybd-settle .5s ease-out both;
}
@keyframes ybd-settle{
	from{ opacity:0; transform:translateY(8px); }
	to{ opacity:1; transform:none; }
}
.ybt-dashboard-enhanced .ybd-desk .ybd-who strong{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.25rem,2.6vw,1.7rem);
	color:var(--ink);
	display:block;
	letter-spacing:-.01em;
}
.ybt-dashboard-enhanced .ybd-desk .ybd-who span{
	font-family:var(--mono);
	font-size:12px;
	color:#5c5543;
	letter-spacing:.06em;
}
.ybt-dashboard-enhanced .ybd-desk-right{
	display:flex;
	align-items:center;
	gap:12px;
	flex-wrap:wrap;
}
.ybt-dashboard-enhanced .ybd-consignee{
	font-family:var(--mono);
	font-size:12px;
	font-weight:600;
	letter-spacing:.08em;
	color:var(--emerald-deep);
	border:1.5px solid var(--emerald);
	background:var(--emerald-tint);
	border-radius:6px;
	padding:8px 12px;
	white-space:nowrap;
}
/* search sizing only — base look comes from the shared sheet */
.ybt-dashboard-enhanced .ybd-search{
	font-size:14px;
	padding:9px 14px;
	width:min(220px,60vw);
}
.ybt-dashboard-enhanced #searchResults{
	background:var(--paper);
	border:1.5px solid var(--ink);
	border-radius:10px;
	padding:12px 14px;
	margin-top:10px;
	box-shadow:6px 6px 0 rgba(15,31,23,.12);
	font-size:14px;
}

/* ── app update banner ────────────────────────────────────── */
.ybt-dashboard-enhanced .ybd-update{
	margin-bottom:18px;
	background:var(--emerald-deep);
	color:#fff;
	border-radius:12px;
	padding:14px 18px;
	display:flex;
	align-items:center;
	gap:14px;
	flex-wrap:wrap;
	box-shadow:6px 6px 0 rgba(15,31,23,.12);
}
.ybt-dashboard-enhanced .ybd-update-icon{
	font-size:1.4rem;
	color:var(--gold);
}
.ybt-dashboard-enhanced .ybd-update strong{ color:#fff; }
.ybt-dashboard-enhanced .ybd-update-msg{ font-size:.85rem; opacity:.85; width:100%; }
.ybt-dashboard-enhanced .ybd-update-btn{
	background:var(--gold);
	color:var(--ink);
	border:none;
	padding:8px 18px;
	border-radius:8px;
	font-weight:700;
	white-space:nowrap;
	text-decoration:none;
	display:inline-block;
}
.ybt-dashboard-enhanced .ybd-update-btn:hover{ background:#dfbc48; color:var(--ink); }
.ybt-dashboard-enhanced .ybd-update-close{
	background:none;border:none;color:rgba(255,255,255,.65);
	font-size:1rem;cursor:pointer;padding:4px 8px;
}
.ybt-dashboard-enhanced .ybd-update-close:hover{ color:#fff; }

/* ── stock tally (stats) ──────────────────────────────────── */
.ybt-dashboard-enhanced .ybd-tally{
	display:grid;
	grid-template-columns:repeat(6,1fr);
	border:1.5px solid var(--ink);
	border-radius:12px;
	background:var(--paper);
	box-shadow:6px 6px 0 rgba(15,31,23,.12);
	overflow:hidden;
}
.ybt-dashboard-enhanced .ybd-tally-cell{
	position:relative;
	padding:18px 14px;
	border-right:1px solid var(--kraft-line);
	text-align:center;
}
.ybt-dashboard-enhanced .ybd-tally-cell:last-child{ border-right:none; }
.ybt-dashboard-enhanced .ybd-tally-num{
	font-family:var(--mono);
	font-size:clamp(1.5rem,3vw,2.1rem);
	font-weight:600;
	color:var(--ink);
	display:block;
	line-height:1.1;
}
.ybt-dashboard-enhanced .ybd-tally-label{
	font-family:var(--mono);
	font-size:10px;font-weight:600;
	letter-spacing:.12em;text-transform:uppercase;
	color:#5c5543;
	display:block;
	margin-top:6px;
}
.ybt-dashboard-enhanced .ybd-tally-cell.ybd-is-gold .ybd-tally-num{ color:var(--gold-deep); }
.ybt-dashboard-enhanced .ybd-tally-cell.ybd-is-green .ybd-tally-num{ color:var(--emerald-deep); }
.ybt-dashboard-enhanced .ybd-tally-cell.ybd-is-quiet .ybd-tally-num{ color:#8b968f; }
.ybt-dashboard-enhanced .ybd-tick{
	display:block;
	width:26px;height:3px;
	margin:8px auto 0;
	border-radius:2px;
	background:var(--kraft-line);
}
.ybd-is-gold .ybd-tick{ background:var(--gold); }
.ybd-is-green .ybd-tick{ background:var(--emerald); }

/* ── notice board (announcements) ─────────────────────────── */
.ybt-dashboard-enhanced .ybd-notice{
	background:var(--gold-tint);
	border:1px solid var(--gold);
	border-left-width:5px;
	border-radius:0 10px 10px 0;
	padding:16px 18px;
	margin-bottom:12px;
}
.ybt-dashboard-enhanced .ybd-notice-head{
	display:flex;
	justify-content:space-between;
	align-items:baseline;
	gap:10px;
	margin-bottom:8px;
}
.ybt-dashboard-enhanced .ybd-notice-head .ybd-mono{
	font-size:11px;font-weight:600;
	letter-spacing:.14em;text-transform:uppercase;
	color:var(--gold-deep);
}
.ybt-dashboard-enhanced .ybd-notice p{
	margin:0 0 4px;
	color:#4a4232;
	line-height:1.6;
}
.ybt-dashboard-enhanced .ybd-notice img{
	max-width:150px;float:right;margin-left:1rem;
	border-radius:8px;border:1px solid var(--kraft-line);
	cursor:zoom-in;
}
.ybt-dashboard-enhanced .ybd-notice .ybd-notice-file{
	display:inline-block;
	font-family:var(--mono);
	font-size:12px;font-weight:600;
	color:var(--gold-deep);
	text-decoration:underline;
}

/* ── routing line (live) ──────────────────────────────────── */
.ybt-dashboard-enhanced .ybd-route{
	list-style:none;
	margin:0;
	padding:0;
	display:grid;
	grid-template-columns:repeat(5,1fr);
	position:relative;
}
.ybt-dashboard-enhanced .ybd-route::before{
	content:'';
	position:absolute;
	top:19px;
	left:calc(10% + 19px);
	right:calc(10% + 19px);
	height:2px;
	background:repeating-linear-gradient(90deg,var(--emerald) 0 6px,transparent 6px 10px);
}
.ybt-dashboard-enhanced .ybd-route li{
	position:relative;
	padding:0 8px;
	text-align:center;
}
.ybt-dashboard-enhanced .ybd-route .num{
	width:40px;height:40px;
	margin:0 auto 10px;
	display:flex;align-items:center;justify-content:center;
	border-radius:50%;
	background:var(--paper);
	border:2px solid var(--kraft-deep);
	color:#8b968f;
	font-family:var(--mono);
	font-weight:600;font-size:14px;
	position:relative;z-index:1;
}
.ybt-dashboard-enhanced .ybd-route li.ybd-live .num{
	border-color:var(--emerald);
	color:var(--emerald-deep);
	background:var(--emerald-tint);
}
.ybt-dashboard-enhanced .ybd-route .name{
	display:block;
	font-weight:700;font-size:13.5px;
	color:var(--ink);
}
.ybt-dashboard-enhanced .ybd-route .cnt{
	display:inline-block;
	margin-top:6px;
	font-family:var(--mono);
	font-size:12px;font-weight:600;
	color:var(--emerald-deep);
	background:var(--emerald-tint);
	border:1px solid var(--emerald);
	border-radius:6px;
	padding:2px 10px;
}
.ybt-dashboard-enhanced .ybd-route li:not(.ybd-live) .cnt{
	color:#5c5543;
	background:#fbf8f1;
	border-color:var(--kraft-line);
}
.ybt-dashboard-enhanced .ybd-sync{
	text-align:center;
	font-family:var(--mono);
	font-size:11px;
	letter-spacing:.08em;
	color:#8b968f;
	margin-top:18px;
}

/* ── origin & settlement cards ────────────────────────────── */
.ybt-dashboard-enhanced .ybd-duo{
	display:grid;
	grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
	gap:16px;
	align-items:stretch;
}
.ybt-dashboard-enhanced .ybd-doc{
	padding:clamp(18px,2.6vw,24px);
	display:flex;
	flex-direction:column;
	gap:14px;
}
.ybt-dashboard-enhanced .ybd-doc-head{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:10px;
	padding-bottom:12px;
	border-bottom:2px dashed var(--kraft-line);
}
.ybt-dashboard-enhanced .ybd-doc-head h3{
	margin:0;
	font-family:var(--mono);
	font-size:12px;font-weight:600;
	letter-spacing:.18em;text-transform:uppercase;
	color:var(--emerald-deep);
}
.ybt-dashboard-enhanced .ybd-doc-gold .ybd-doc-head h3{ color:var(--gold-deep); }
.ybt-dashboard-enhanced .ybd-copycard{
	border:1.5px dashed var(--kraft-deep);
	border-radius:10px;
	background:#fbf8f1;
	padding:16px;
	cursor:pointer;
	user-select:none;
	transition:border-color .15s ease, background-color .15s ease, transform .15s ease;
}
.ybt-dashboard-enhanced .ybd-copycard:hover{
	border-color:var(--emerald);
	background:var(--emerald-tint);
	transform:translateY(-2px);
}
.ybt-dashboard-enhanced .ybd-copycard:active{ transform:scale(.98); }
.ybt-dashboard-enhanced .ybd-copycard .ybd-addr{
	font-family:var(--mono);
	font-size:14px;
	line-height:1.7;
	color:var(--ink);
	pointer-events:none;
}
.ybt-dashboard-enhanced .ybd-copyhint{
	font-family:var(--mono);
	font-size:11px;font-weight:600;
	letter-spacing:.1em;text-transform:uppercase;
	color:var(--emerald-deep);
	display:inline-flex;align-items:center;gap:6px;
}
.ybt-dashboard-enhanced .ybd-doc .ybd-meta{
	display:flex;gap:16px;flex-wrap:wrap;
	font-size:13px;color:#3d4a42;
}
.ybt-dashboard-enhanced .ybd-doc .ybd-meta i{ color:var(--emerald-deep); }
.ybt-dashboard-enhanced .ybd-kv{
	display:flex;flex-direction:column;gap:2px;
	padding:8px 0;
	border-bottom:1px solid var(--kraft-line);
}
.ybt-dashboard-enhanced .ybd-kv:last-of-type{ border-bottom:none; }
.ybt-dashboard-enhanced .ybd-kv .ybd-val{
	font-family:var(--mono);
	font-size:14px;font-weight:600;
	color:var(--ink);
	display:flex;align-items:center;gap:10px;
}
.ybt-dashboard-enhanced .ybd-copybtn{
	font-family:var(--mono);
	font-size:11px;font-weight:600;
	background:var(--gold-tint);
	color:var(--gold-deep);
	border:1.5px solid var(--gold);
	border-radius:6px;
	padding:4px 10px;
	cursor:pointer;
	display:inline-flex;align-items:center;gap:6px;
}
.ybt-dashboard-enhanced .ybd-copybtn:hover{ background:var(--gold); color:var(--ink); }
.ybt-dashboard-enhanced .ybd-qr{
	text-align:center;
	background:var(--gold-tint);
	border:1px solid var(--gold);
	border-radius:10px;
	padding:14px;
}
.ybt-dashboard-enhanced .ybd-qr img{ max-height:120px;border-radius:8px; }
.ybt-dashboard-enhanced .ybd-qr .ybd-mono{
	font-size:11px;font-weight:600;
	letter-spacing:.12em;text-transform:uppercase;
	color:var(--gold-deep);
	margin-top:8px;
}

/* ── manifest row actions ─────────────────────────────────── */
.ybt-dashboard-enhanced .ybd-viewbtn{
	background:none;
	border:1.5px solid var(--kraft-deep);
	border-radius:8px;
	color:var(--emerald-deep);
	width:34px;height:34px;
	cursor:pointer;
	display:inline-flex;align-items:center;justify-content:center;
	transition:border-color .15s ease, background-color .15s ease;
}
.ybt-dashboard-enhanced .ybd-viewbtn:hover{ border-color:var(--emerald); background:var(--emerald-tint); }
.ybt-dashboard-enhanced .ybd-ghostlink{
	font-family:var(--mono);
	font-size:12px;font-weight:600;
	letter-spacing:.08em;text-transform:uppercase;
	color:var(--emerald-deep);
	text-decoration:underline;
}
.ybt-dashboard-enhanced .ybd-ghostlink:hover{ color:var(--emerald); }

/* ── responsive ───────────────────────────────────────────── */
@media (max-width:1000px){
	.ybt-dashboard-enhanced .ybd-tally{ grid-template-columns:repeat(3,1fr); }
	.ybt-dashboard-enhanced .ybd-tally-cell:nth-child(3n){ border-right:none; }
	.ybt-dashboard-enhanced .ybd-tally-cell:nth-child(-n+3){ border-bottom:1px solid var(--kraft-line); }
}
@media (max-width:760px){
	.ybt-dashboard-enhanced .ybd-route{
		grid-template-columns:1fr;
		gap:16px;
	}
	.ybt-dashboard-enhanced .ybd-route::before{
		top:0;bottom:0;left:19px;right:auto;
		width:2px;height:auto;
		background:repeating-linear-gradient(180deg,var(--emerald) 0 6px,transparent 6px 10px);
	}
	.ybt-dashboard-enhanced .ybd-route li{
		display:grid;
		grid-template-columns:40px 1fr;
		column-gap:14px;
		text-align:left;
		padding:0;
	}
	.ybt-dashboard-enhanced .ybd-route .num{ margin:0; }
}
@media (max-width:560px){
	.ybt-dashboard-enhanced .ybd-tally{ grid-template-columns:repeat(2,1fr); }
	.ybt-dashboard-enhanced .ybd-tally-cell{ border-bottom:1px solid var(--kraft-line); }
	.ybt-dashboard-enhanced .ybd-tally-cell:nth-child(2n){ border-right:none; }
	.ybt-dashboard-enhanced .ybd-tally-cell:nth-child(n+5){ border-bottom:none; }
}
</style>

<div class="ybt-dashboard-enhanced">
<div class="container" style="padding-top: 1rem !important;">

  <!-- ====== APP UPDATE BANNER ====== -->
  <?php if (!empty($app_latest_version) && !empty($app_download_url)): ?>
  <div class="ybd-update" id="ybtUpdateBanner">
    <div class="ybd-update-icon"><i class="fas fa-mobile-alt"></i></div>
    <div class="flex-grow-1">
      <strong>App update available &mdash; v<?php echo esc_html($app_latest_version); ?></strong>
      <?php if (!empty($app_update_message)): ?>
        <div class="ybd-update-msg"><?php echo esc_html($app_update_message); ?></div>
      <?php endif; ?>
    </div>
    <a href="<?php echo esc_url($app_download_url); ?>" class="ybd-update-btn" download>Download update</a>
    <button class="ybd-update-close" onclick="document.getElementById('ybtUpdateBanner').style.display='none'" title="Dismiss" aria-label="Dismiss">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <?php endif; ?>

  <!-- ====== THE DESK (header) ====== -->
  <div class="ybd-desk">
    <div class="ybd-who">
      <p class="ybd-eyebrow">Consignment desk</p>
      <strong>Your parcels</strong>
      <span>Tracking for <?php echo esc_html($current_user->display_name); ?></span>
    </div>
    <div class="ybd-desk-right">
      <span class="ybd-consignee">Consignee <?php echo esc_html($custom_user_id); ?></span>
      <input type="text" id="quickSearch" class="ybd-search" placeholder="Trace a parcel&hellip;" aria-label="Search parcels">
    </div>
  </div>
  <div id="searchResults" style="display:none;"></div>

  <!-- ====== STOCK TALLY (stats) ====== -->
  <div class="ybd-section">
    <div class="ybd-tally" role="list" aria-label="Parcel tally">
      <div class="ybd-tally-cell" role="listitem">
        <span class="ybd-tally-num"><?php echo esc_html($stats['total'] ?? 0); ?></span>
        <span class="ybd-tally-label">Total</span>
        <span class="ybd-tick" aria-hidden="true"></span>
      </div>
      <div class="ybd-tally-cell ybd-is-quiet" role="listitem">
        <span class="ybd-tally-num"><?php echo esc_html($not_received); ?></span>
        <span class="ybd-tally-label">Not received</span>
        <span class="ybd-tick" aria-hidden="true"></span>
      </div>
      <div class="ybd-tally-cell ybd-is-green" role="listitem">
        <span class="ybd-tally-num"><?php echo esc_html($received); ?></span>
        <span class="ybd-tally-label">Received</span>
        <span class="ybd-tick" aria-hidden="true"></span>
      </div>
      <div class="ybd-tally-cell ybd-is-gold" role="listitem">
        <span class="ybd-tally-num"><?php echo esc_html($packing_in_progress); ?></span>
        <span class="ybd-tally-label">Packing</span>
        <span class="ybd-tick" aria-hidden="true"></span>
      </div>
      <div class="ybd-tally-cell ybd-is-green" role="listitem">
        <span class="ybd-tally-num"><?php echo esc_html($packed); ?></span>
        <span class="ybd-tally-label">Packed</span>
        <span class="ybd-tick" aria-hidden="true"></span>
      </div>
      <div class="ybd-tally-cell ybd-is-green" role="listitem">
        <span class="ybd-tally-num"><?php echo esc_html($completed); ?></span>
        <span class="ybd-tally-label">Completed</span>
        <span class="ybd-tick" aria-hidden="true"></span>
      </div>
    </div>
  </div>

  <!-- ====== NOTICE BOARD (announcements) ====== -->
  <?php if (!empty($admin_messages)): ?>
  <div class="ybd-section">
    <p class="ybd-eyebrow">Notice board &middot; From the warehouse</p>
    <?php foreach ($admin_messages as $msg):
      $msg->message = nl2br(esc_html($msg->message));
      $msg_date = date('M j, Y g:i A', strtotime($msg->date_created));
    ?>
      <div class="ybd-notice">
        <div class="ybd-notice-head">
          <span class="ybd-mono">Notice</span>
          <span class="ybd-mono" style="color:#8b8265;"><?php echo $msg_date; ?></span>
        </div>
        <?php if (!empty($msg->image_url)): ?>
          <img src="<?php echo esc_url($msg->image_url); ?>"
               alt="Notice image"
               loading="lazy"
               decoding="async"
               onclick="window.openImageFullscreen('<?php echo esc_url($msg->image_url); ?>')" />
        <?php endif; ?>
        <p><?php echo $msg->message; ?></p>
        <?php if (!empty($msg->video_url)): ?>
          <a href="<?php echo esc_url($msg->video_url); ?>" target="_blank" rel="noopener" class="ybd-notice-file">View attachment</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ====== ROUTING (live journey) ====== -->
  <div class="ybd-section">
    <div class="ybd-panel">
      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:22px;">
        <div>
          <p class="ybd-eyebrow" style="margin-bottom:6px;">Routing &middot; Live</p>
          <h2 class="ybd-h-display">Where your parcels are</h2>
        </div>
        <span class="ybd-stamp" style="font-size:9px;padding:4px 8px;">On the record</span>
      </div>
      <ol class="ybd-route">
        <?php
        $journey_steps = [
          ['label' => 'Not received',         'count' => $not_received],
          ['label' => 'Received',             'count' => $received],
          ['label' => 'Packing in progress',  'count' => $packing_in_progress],
          ['label' => 'Packed',               'count' => $packed],
          ['label' => 'Completed',            'count' => $completed],
        ];
        foreach ($journey_steps as $i => $step):
          $live  = $step['count'] > 0 ? ' ybd-live' : '';
          $count = $step['count'] > 0 ? '<span class="cnt">' . (int) $step['count'] . '</span>' : '';
        ?>
          <li class="<?php echo trim($live); ?>">
            <span class="num" aria-hidden="true"><?php echo $i + 1; ?></span>
            <div>
              <span class="name"><?php echo esc_html($step['label']); ?></span>
              <?php echo $count; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="ybd-sync">Data synced as of <?php echo date('H:i'); ?></div>
    </div>
  </div>

  <!-- ====== ORIGIN & SETTLEMENT ====== -->
  <div class="ybd-section">
    <div class="ybd-duo">
      <!-- Warehouse: click-to-copy waybill -->
      <div class="ybd-doc">
        <div class="ybd-doc-head">
          <h3>Origin &middot; Consolidation hub</h3>
        </div>
        <?php if ($warehouse_settings && !empty($warehouse_settings->address)):
          $phone = esc_html($warehouse_settings->phone ?: 'N/A');
          $hours = esc_html($warehouse_settings->hours ?: 'N/A');
          $addr = ybt_smart_format_address($warehouse_settings->address, $custom_user_id);
          $clipboard_text = htmlspecialchars($addr . "\nPhone: " . $phone, ENT_QUOTES, 'UTF-8');
        ?>
          <div class="ybd-copycard"
               id="warehouseAddressCard"
               data-clipboard-text="<?php echo $clipboard_text; ?>"
               title="Click to copy address"
               role="button"
               tabindex="0">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;">
              <span class="ybd-label">Warehouse address</span>
              <span class="ybd-copyhint">
                <i class="far fa-copy"></i>
                <span class="copy-hint-txt">Click to copy</span>
              </span>
            </div>
            <div class="ybd-addr">
              <?php echo nl2br(esc_html($addr)); ?>
            </div>
          </div>
          <div class="ybd-meta">
            <small class="d-flex align-items-center gap-2">
              <i class="fas fa-phone"></i>
              <?php echo $phone; ?>
            </small>
            <small class="d-flex align-items-center gap-2">
              <i class="far fa-clock"></i>
              <?php echo $hours; ?>
            </small>
          </div>
        <?php else: ?>
          <div class="ybd-empty">
            <i class="fas fa-warehouse fa-3x opacity-25"></i>
            <p>No warehouse set</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Settlement: bank details -->
      <div class="ybd-doc ybd-doc-gold">
        <div class="ybd-doc-head">
          <h3>Settlement &middot; Bank details</h3>
        </div>
        <?php if ($payment_settings):
          $bank = esc_html($payment_settings->bank_name);
          $name = esc_html($payment_settings->account_name);
          $acc = esc_html($payment_settings->account_number);
          $qr = esc_url($payment_settings->qr_code_image ?: '');
        ?>
          <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start;">
            <div class="flex-grow-1" style="min-width:200px;">
              <div class="ybd-kv">
                <span class="ybd-label">Bank</span>
                <span class="ybd-val"><?php echo $bank; ?></span>
              </div>
              <div class="ybd-kv">
                <span class="ybd-label">Account name</span>
                <span class="ybd-val"><?php echo $name; ?></span>
              </div>
              <div class="ybd-kv">
                <span class="ybd-label">Account number</span>
                <span class="ybd-val">
                  <?php echo $acc; ?>
                  <button class="ybd-copybtn copy-btn" type="button"
                          data-clipboard-text="<?php echo htmlspecialchars($acc, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-copy"></i> Copy
                  </button>
                </span>
              </div>
            </div>
            <?php if ($qr): ?>
              <div class="ybd-qr">
                <img src="<?php echo esc_url($qr); ?>"
                     alt="QR code for payment"
                     loading="lazy"
                     decoding="async" />
                <div class="ybd-mono">Scan to pay</div>
                <a href="<?php echo esc_url($qr); ?>" class="ybd-ghostlink" download>
                  <i class="fas fa-download"></i> Download QR
                </a>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="ybd-empty">
            <i class="fas fa-credit-card fa-3x opacity-25"></i>
            <p>No payment configured</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ====== MANIFEST (recent activity) ====== -->
  <div class="ybd-section">
    <div class="ybd-panel">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <div>
          <p class="ybd-eyebrow" style="margin-bottom:6px;">Manifest &middot; Latest entries</p>
          <h2 class="ybd-h-display">Recent activity</h2>
        </div>
        <a href="<?php echo esc_url(home_url('/tracking/received')); ?>" class="ybd-ghostlink">Audit all</a>
      </div>
      <?php if (!empty($recent_activity)): ?>
        <div class="ybd-tablewrap">
          <table class="ybd-manifest">
            <thead>
              <tr>
                <th>Tracking #</th>
                <th>Status</th>
                <th>Date</th>
                <th><span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);">View</span></th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Bootstrap class names are kept for the details-modal script below.
              $status_colors = [
                  'not_received'       => ['bg' => 'secondary', 'text' => 'white'],
                  'received'           => ['bg' => 'warning',   'text' => 'dark'],
                  'packing_in_progress'=> ['bg' => 'primary',   'text' => 'white'],
                  'packed'             => ['bg' => 'info',      'text' => 'dark'],
                  'completed'          => ['bg' => 'success',   'text' => 'white'],
              ];
              foreach ($recent_activity as $item):
                $status   = $item->status;
                $colors   = $status_colors[$status] ?? ['bg' => 'secondary', 'text' => 'white'];
                $bg_class = $colors['bg'];
                $tx_class = $colors['text'];
                $text     = ucwords(str_replace('_', ' ', $status));
                $date     = $item->date_added ? date('M j, Y', strtotime($item->date_added)) : '-';
              ?>
                <tr>
                  <td><span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span></td>
                  <td><span class="ybd-chip ybd-chip-<?php echo esc_attr($status); ?>"><?php echo esc_html($text); ?></span></td>
                  <td><span class="ybd-date"><?php echo esc_html($date); ?></span></td>
                  <td>
                      <button type="button"
                              class="ybd-viewbtn view-parcel-details"
                              aria-label="View details for <?php echo esc_attr($item->tracking_number); ?>"
                              data-parcel='<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8"); ?>'
                              data-status-text="<?php echo esc_attr($text); ?>"
                              data-bg-class="<?php echo esc_attr($bg_class); ?>"
                              data-tx-class="<?php echo esc_attr($tx_class); ?>">
                          <i class="fas fa-chevron-right"></i>
                      </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="ybd-empty"><i class="fas fa-inbox fa-3x opacity-25"></i><p>No recent activity &mdash; add a tracking number to start a consignment.</p></div>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>

<!-- ====== IMAGE FULLSCREEN MODAL (INLINE FOR SIMPLICITY) ====== -->
<script>
function openImageFullscreen(src) {
  if (!src || !/\.(jpg|jpeg|png|gif|webp)$/i.test(src)) {
    alert('Invalid image');
    return;
  }
  const modal = $('<div>').css({
    position: 'fixed',
    top: 0,
    left: 0,
    width: '100%',
    height: '100%',
    background: 'rgba(0,0,0,0.9)',
    zIndex: 99999,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center'
  });
  const modalContent = $('<div>').css({
    maxWidth: '90%',
    maxHeight: '90%',
    position: 'relative'
  });
  const img = $('<img>').attr('src', src).css({
    maxWidth: '100%',
    maxHeight: '100%'
  });
  const closeButton = $('<button>').html('&times;').css({
    position: 'absolute',
    top: '10px',
    right: '10px',
    background: 'none',
    border: 'none',
    color: 'white',
    fontSize: '24px',
    cursor: 'pointer'
  }).click(function() {
    modal.remove();
  });
  modalContent.append(img);
  modalContent.append(closeButton);
  modal.append(modalContent);
  $('body').append(modal);
}
</script>

<!-- ====== COPY TOAST ====== -->
<div id="ybtCopyToast" role="status" aria-live="polite">
  <i class="fas fa-check-circle me-2"></i> Address copied!
</div>

<!-- ====== COPY FUNCTIONS ====== -->
<script>
function ybtShowCopyToast(msg) {
  const t = document.getElementById('ybtCopyToast');
  if (!t) return;
  
  // Update content
  t.innerHTML = '<i class="fas fa-check-circle me-2"></i> ' + (msg || 'Copied!');
  
  // Show toast
  t.style.opacity = '1';
  t.style.pointerEvents = 'auto';
  t.style.transform = 'translateX(-50%) translateY(0)';
  
  // Auto-hide
  clearTimeout(t._timer);
  t._timer = setTimeout(() => {
    t.style.opacity = '0';
    t.style.pointerEvents = 'none';
    t.style.transform = 'translateX(-50%) translateY(20px)';
  }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
  console.log('Dashboard script loaded');

  // Fallback copy function for buttons
  var copyBtnFallback = function(btn, text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      ybtShowCopyToast('Copied!');
      var orig = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check text-success"></i>';
      setTimeout(function() { btn.innerHTML = orig; }, 2000);
    } catch(e) {
      alert('Copy failed. Please copy manually.');
    }
    document.body.removeChild(ta);
  };

  // ---- Warehouse address card: click entire card to copy ----
  var addrCard = document.getElementById('warehouseAddressCard');
  if (addrCard) {
    addrCard.addEventListener('click', function() {
      var text = this.getAttribute('data-clipboard-text');
      var hint = this.querySelector('.copy-hint-txt');
      
      if (!text) return;

      // Decode entities
      var decoder = document.createElement('textarea');
      decoder.innerHTML = text;
      text = decoder.value;

      var performCopy = function(txt) {
          if (navigator.clipboard && navigator.clipboard.writeText) {
              return navigator.clipboard.writeText(txt);
          } else {
              return new Promise(function(resolve, reject) {
                  var textArea = document.createElement("textarea");
                  textArea.value = txt;
                  textArea.style.position = "fixed";
                  textArea.style.left = "-999999px";
                  textArea.style.top = "-999999px";
                  document.body.appendChild(textArea);
                  textArea.focus();
                  textArea.select();
                  try {
                      var successful = document.execCommand('copy');
                      document.body.removeChild(textArea);
                      if (successful) resolve(); else reject();
                  } catch (err) {
                      document.body.removeChild(textArea);
                      reject(err);
                  }
              });
          }
      };

      performCopy(text).then(function() {
          ybtShowCopyToast('Address copied to clipboard!');
          if (hint) {
              var originalText = hint.textContent;
              hint.textContent = 'Copied!';
              setTimeout(function() {
                  hint.textContent = originalText;
              }, 2500);
          }
      }).catch(function(err) {
          console.error('Copy failed:', err);
          alert('Could not copy automatically. Please select the text and copy manually.');
      });
    });

    addrCard.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });
  }

  // ---- Generic copy buttons (e.g. account number) - Use event delegation ----
  document.addEventListener('click', function(e) {
    if (e.target.closest('.copy-btn')) {
      var btn = e.target.closest('.copy-btn');
      var text = btn.dataset.clipboardText;

      // Decode HTML entities
      if (text) {
        var txt = document.createElement('textarea');
        txt.innerHTML = text;
        text = txt.value;
      }

      if (!text) return;

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
          ybtShowCopyToast('Copied!');
          var orig = btn.innerHTML;
          btn.innerHTML = '<i class="fas fa-check"></i>';
          setTimeout(function() { btn.innerHTML = orig; }, 2000);
        }).catch(function() {
          copyBtnFallback(btn, text);
        });
      } else {
        copyBtnFallback(btn, text);
      }
    }
  });

  // Quick Search (Basic, AJAX recommended to be moved to external JS)
  var timeout;
  var quickSearchInput = document.getElementById('quickSearch');
  if (quickSearchInput) {
    quickSearchInput.addEventListener('input', function() {
      clearTimeout(timeout);
      var q = this.value.trim();
      if (q.length < 3) {
        var searchResults = document.getElementById('searchResults');
        if (searchResults) { searchResults.style.display = 'none'; }
        return;
      }
      timeout = setTimeout(function() {
        console.log('Search for:', q);
      }, 500);
    });
  }
});
</script>

<!-- ====== PARCEL DETAILS MODAL ====== -->
<div id="parcelDetailsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,31,23,0.75); z-index:9999; align-items:center; justify-content:center;">
    <div style="width:100%; max-width:600px; margin:0 1rem; background:#ffffff; border:1.5px solid #0f1f17; border-radius:12px; position:relative; max-height:90vh; overflow-y:auto; padding:1.6rem; box-shadow:6px 6px 0 rgba(15,31,23,.3);">
        <button onclick="jQuery('#parcelDetailsModal').fadeOut(200)" aria-label="Close" style="position:absolute; top:14px; right:14px; background:none; border:none; color:#5c5543; font-size:24px; cursor:pointer;">&times;</button>

        <div style="margin-bottom:1.2rem; padding-bottom:0.9rem; border-bottom:2px dashed #d9ccb0;">
            <p style="margin:0 0 4px; font-family:'IBM Plex Mono',monospace; font-size:10px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:#8a6d1f;">Consignment details</p>
            <h4 style="margin:0 0 10px; font-family:'IBM Plex Mono',monospace; font-size:1.3rem; font-weight:600; color:#0f1f17;" id="m-tracking-number">Tracking Details</h4>
            <span id="m-status-badge" class="badge rounded-pill px-3 py-2" style="font-family:'IBM Plex Mono',monospace; font-size:12px;"></span>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <label class="ybd-m-label">Description</label>
                <div id="m-description" class="ybd-m-val">-</div>
            </div>
            <div class="col-md-6">
                <label class="ybd-m-label">Items count</label>
                <div id="m-count" class="ybd-m-val">-</div>
            </div>
            <div class="col-md-6">
                <label class="ybd-m-label">Registered date</label>
                <div id="m-date-added" class="ybd-m-val">-</div>
            </div>
            <div class="col-md-6" id="m-date-received-container">
                <label class="ybd-m-label">Received date</label>
                <div id="m-date-received" class="ybd-m-val">-</div>
            </div>
        </div>

        <div id="m-images-container" class="mt-4 pt-3" style="border-top:2px dashed #d9ccb0;">
            <label class="ybd-m-label" style="margin-bottom:.5rem;">Parcel images</label>
            <div id="m-images" class="d-flex flex-wrap gap-2"></div>
        </div>

        <!-- Parcel timeline: populated by assets/js/ybt-timeline.js via the
             ybt_get_parcel_timeline AJAX action (includes/notifications-web.php). -->
        <div id="m-timeline" class="mt-4 pt-3" style="border-top:2px dashed #d9ccb0;">
            <label class="ybd-m-label" style="margin-bottom:.5rem;">Parcel timeline</label>
            <ul class="ybt-timeline-list"></ul>
        </div>

        <div class="d-grid mt-4 pt-2">
            <button type="button" onclick="jQuery('#parcelDetailsModal').fadeOut(200)" style="background:#0f1f17; color:#fff; border:1.5px solid #0f1f17; border-radius:8px; padding:11px 20px; font-weight:700; cursor:pointer;">Close</button>
        </div>
    </div>
</div>
<style>
.ybd-m-label{
	display:block;
	font-family:'IBM Plex Mono',monospace;
	font-size:10px; font-weight:600;
	letter-spacing:.14em; text-transform:uppercase;
	color:#5c5543;
	margin-bottom:2px;
}
.ybd-m-val{
	font-family:'IBM Plex Sans',sans-serif;
	font-weight:600;
	color:#0f1f17;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var $ = jQuery;

  // Handle Parcel Details Modal
  $('.view-parcel-details').on('click', function() {
    var data = $(this).data('parcel');
    var statusText = $(this).data('status-text');
    var bgClass = $(this).data('bg-class');
    var txClass = $(this).data('tx-class');

    $('#m-tracking-number').text(data.tracking_number);
    $('#m-status-badge').text(statusText)
      .removeClassRegex(/^bg-/)
      .removeClassRegex(/^text-/)
      .addClass('bg-' + bgClass)
      .addClass('text-' + txClass);

    $('#m-description').text(data.description || 'No description');
    $('#m-count').text(data.item_count || '0');
    $('#m-date-added').text(data.date_added || '-');

    if (data.date_received) {
      $('#m-date-received-container').show();
      $('#m-date-received').text(data.date_received);
    } else {
      $('#m-date-received-container').hide();
    }

    // Handle Images
    var imagesHtml = $('#m-images');
    imagesHtml.empty();
    if (data.images) {
      var images = data.images.split(',');
      for (var i = 0; i < images.length; i++) {
        var trimmed = images[i].trim();
        if (trimmed) {
          imagesHtml.append('<img src="' + trimmed + '" class="rounded shadow-sm border" style="width:80px; height:80px; object-fit:cover; cursor:pointer;" onclick="openImageFullscreen(\'' + trimmed + '\')">');
        }
      }
      $('#m-images-container').show();
    } else {
      $('#m-images-container').hide();
    }

    $('#parcelDetailsModal').css('display', 'flex').hide().fadeIn(200);
  });

  // Helper to remove classes matching a regex
  $.fn.removeClassRegex = function(regex) {
    return this.each(function() {
      var el = $(this);
      var classes = el.attr('class').split(/\s+/);
      var toRemove = [];
      for (var i = 0; i < classes.length; i++) {
        if (regex.test(classes[i])) {
          toRemove.push(classes[i]);
        }
      }
      el.removeClass(toRemove.join(' '));
    });
  };

  // Close modals on outside click
  $('#parcelDetailsModal, #image-viewer-modal').click(function(e) {
    if (e.target === this) $(this).fadeOut(200);
  });
});
</script>
