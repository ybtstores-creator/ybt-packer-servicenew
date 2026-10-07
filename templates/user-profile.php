<?php
/**
 * My Account Profile — "Member docket" (consignment-note system)
 * JS contract: #profileUpdateForm, #saveProfileBtn, #profile-message, #mobile_number.
 */
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) { echo '<div class="alert alert-warning">Please login to view profile.</div>'; return; }

$current_user = wp_get_current_user();
$user_id = get_current_user_id();
$custom_id = get_user_meta($user_id, 'ybt_custom_user_id', true) ?: 'Pending';

// ====== DASHBOARD SETTINGS ======
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
   MEMBER DOCKET — page-specific layer
   Shared tokens & atoms come from assets/css/ybt-consignment.css.
   ================================================================ */
.ybt-docket{
	margin:clamp(16px,3vw,30px) auto;
	max-width:760px;
	padding:clamp(18px,3vw,32px);
	display:flex;
	flex-direction:column;
	gap:22px;
}
/* docket masthead: monogram + name + verified chip */
.ybt-docket .ybt-dockhead{
	display:flex;
	align-items:center;
	gap:16px;
	flex-wrap:wrap;
}
.ybt-docket .ybt-dockhead .ybt-monogram{
	width:64px;height:64px;
	flex:0 0 64px;
	display:flex;align-items:center;justify-content:center;
	border-radius:50%;
	background:var(--ink);
	color:var(--gold);
	font-family:var(--display);
	font-size:1.7rem;
	border:2px solid var(--gold);
	box-shadow:4px 4px 0 rgba(15,31,23,.15);
}
.ybt-docket .ybt-dockhead .ybt-whoami h2{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.25rem,2.6vw,1.6rem);
	margin:0;
}
.ybt-docket .ybt-dockhead .ybt-whoami p{
	margin:2px 0 0;
	font-family:var(--mono);
	font-size:12.5px;
	color:#5c5543;
}
.ybt-docket .ybt-dockhead .ybt-chip-verified{
	margin-left:auto;
	display:inline-flex;align-items:center;gap:7px;
	font-family:var(--mono);
	font-size:11px;font-weight:600;
	letter-spacing:.08em;text-transform:uppercase;
	color:var(--emerald-deep);
	background:var(--emerald-tint);
	border:1.5px solid var(--emerald);
	border-radius:6px;
	padding:6px 12px;
}
/* the docket rows */
.ybt-docket .ybt-dockrows{
	border:1.5px solid var(--kraft-line);
	border-radius:10px;
	background:#fbf8f1;
	overflow:hidden;
}
.ybt-docket .ybt-dockrow{
	display:grid;
	grid-template-columns:180px 1fr;
	gap:12px;
	align-items:center;
	padding:14px 18px;
}
.ybt-docket .ybt-dockrow + .ybt-dockrow{ border-top:1.5px dashed var(--kraft-line); }
.ybt-docket .ybt-dockrow label{ margin:0; }
.ybt-docket .ybt-dockrow .ybt-static{
	font-family:var(--sans);
	font-weight:600;
	color:var(--ink);
	font-size:15px;
	padding:0;
	background:none;
	border:none;
}
.ybt-docket .ybt-dockrow .ybt-static.muted{ color:#5c5543; font-weight:500; }
/* the one editable row: input + gold save share a line */
.ybt-docket .ybt-dockrow .ybt-editline{ display:flex; gap:10px; align-items:stretch; flex-wrap:wrap; }
.ybt-docket .ybt-dockrow .ybt-editline .ybt-input{ flex:1 1 220px; }
.ybt-docket .ybt-dockrow .ybt-input{ padding:10px 14px; }
/* the ID plate */
.ybt-docket .ybt-plate{
	display:inline-flex;align-items:center;gap:10px;
	font-family:var(--mono);
	font-weight:600;
	font-size:16px;
	letter-spacing:.08em;
	color:var(--emerald-deep);
	background:var(--emerald-tint);
	border:1.5px solid var(--emerald);
	border-radius:8px;
	padding:7px 14px;
}
/* dashed rule + action row */
.ybt-docket .ybt-dockrule{ border:none; border-top:2px dashed var(--kraft-line); margin:0; }
.ybt-docket .ybt-dockactions{
	display:flex;
	justify-content:space-between;
	align-items:center;
	gap:12px;
	flex-wrap:wrap;
}
.ybt-docket .ybt-dockactions .ybt-logout{ color:#9c2020; border-color:#c95656; }
.ybt-docket .ybt-dockactions .ybt-logout:hover{ background:#fdeeee; }
.ybt-docket .ybt-actions-right{ display:flex; gap:10px; flex-wrap:wrap; }

/* runtime message line (toggled by JS) */
.ybt-docket .ybt-profilemsg{ display:none; font-family:var(--sans); font-size:13.5px; font-weight:600; }
.ybt-docket .ybt-profilemsg.text-success{ display:inline-flex; align-items:center; gap:6px; color:var(--emerald-deep); }
.ybt-docket .ybt-profilemsg.text-danger{ display:inline-flex; align-items:center; gap:6px; color:#9c2020; }

@media (max-width:620px){
	.ybt-docket .ybt-dockrow{ grid-template-columns:1fr; gap:6px; }
	.ybt-docket .ybt-dockhead .ybt-chip-verified{ margin-left:0; }
}
</style>

<div class="ybt-dashboard-enhanced" style="background:none;border:none;padding:0;">
<div class="ybt-docket">

	<!-- docket masthead -->
	<div class="ybt-dockhead">
		<div class="ybt-monogram"><?php echo esc_html(strtoupper(substr($current_user->display_name, 0, 1))); ?></div>
		<div class="ybt-whoami">
			<h2><?php echo esc_html($current_user->display_name); ?></h2>
			<p><?php echo esc_html($current_user->user_email); ?></p>
		</div>
		<span class="ybt-chip-verified"><i class="fas fa-check-circle"></i>Verified member</span>
	</div>

	<!-- docket rows -->
	<div class="ybt-dockrows">
		<div class="ybt-dockrow">
			<label class="ybd-formlabel" for="mobile_number">YBT customer ID</label>
			<div><span class="ybt-plate"><i class="fas fa-barcode"></i><?php echo esc_html($custom_id); ?></span></div>
		</div>
		<div class="ybt-dockrow">
			<label class="ybd-formlabel">Username</label>
			<div><span class="ybt-static"><?php echo esc_html($current_user->user_login); ?></span></div>
		</div>
		<div class="ybt-dockrow">
			<label class="ybd-formlabel">Email address</label>
			<div><span class="ybt-static"><?php echo esc_html($current_user->user_email); ?></span></div>
		</div>
		<div class="ybt-dockrow">
			<label class="ybd-formlabel" for="mobile_number">Mobile number</label>
			<div class="ybt-editline">
				<input type="text" name="mobile_number" id="mobile_number" class="ybt-input" value="<?php echo esc_attr(get_user_meta($user_id, 'ybt_mobile_number', true) ?: ''); ?>" placeholder="+1 234 567 8900">
				<button type="submit" form="profileUpdateForm" class="ybd-btn ybd-btn-gold" id="saveProfileBtn">Save changes</button>
			</div>
			<div id="profile-message" class="ybt-profilemsg" style="display:none;"></div>
		</div>
		<div class="ybt-dockrow">
			<label class="ybd-formlabel">Member since</label>
			<div><span class="ybt-static muted"><?php echo esc_html(date('F j, Y', strtotime($current_user->user_registered))); ?></span></div>
		</div>
	</div>

	<form id="profileUpdateForm" style="display:none;"></form>

	<div class="ybt-dockactions">
		<a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="ybd-btn ybd-btn-emerald"><i class="fas fa-arrow-left"></i>Dashboard</a>
		<div class="ybt-actions-right">
			<a href="<?php echo esc_url(home_url('/reset-password/')); ?>" class="ybd-btn ybd-btn-gold"><i class="fas fa-key"></i>Reset password</a>
			<a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="ybd-btn ybd-btn-emerald ybt-logout"><i class="fas fa-sign-out-alt"></i>Logout</a>
		</div>
	</div>
</div>
</div>

<script>
var ybtNonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.nonce) ? ybt_ajax.nonce : '<?php echo wp_create_nonce('ybt_nonce'); ?>';
var ybtAjaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';

document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileUpdateForm');
    const saveBtn = document.getElementById('saveProfileBtn');
    const messageEl = document.getElementById('profile-message');

    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const mobileNumber = document.getElementById('mobile_number').value.trim();

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            messageEl.style.display = 'none';

            const fd = new FormData();
            fd.append('action', 'ybt_update_profile');
            fd.append('nonce', ybtNonce);
            fd.append('mobile_number', mobileNumber);

            fetch(ybtAjaxUrl, {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    messageEl.className = 'ybt-profilemsg text-success';
                    messageEl.innerHTML = '<i class="fas fa-check-circle"></i>' + response.data;
                } else {
                    messageEl.className = 'ybt-profilemsg text-danger';
                    messageEl.innerHTML = '<i class="fas fa-exclamation-circle"></i>' + (response.data || 'Error updating profile');
                }
                messageEl.style.display = 'inline-flex';
            })
            .catch(err => {
                messageEl.className = 'ybt-profilemsg text-danger';
                messageEl.textContent = 'Network error. Please try again.';
                messageEl.style.display = 'inline-flex';
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save changes';
            });
        });
    }
});
</script>
