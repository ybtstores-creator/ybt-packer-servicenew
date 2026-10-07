<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
?>

<style>
/* ================================================================
   MOBILE APP — "dispatch notice" (consignment-note system)
   ================================================================ */
.ybt-appdl{
	margin:clamp(16px,3vw,30px) auto;
	max-width:880px;
}
.ybt-appdl .ybt-dlhead{
	text-align:center;
	margin-bottom:20px;
}
.ybt-appdl .ybt-dlhead h2{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.3rem,3vw,1.8rem);
	margin:0 0 4px;
}
.ybt-appdl .ybt-dlhead p{ margin:0; color:#3d4a42; font-size:14.5px; }
/* the carrier panel */
.ybt-appdl .ybt-carrier{
	position:relative;
	display:flex;
	flex-direction:column;
	align-items:center;
	gap:8px;
	text-align:center;
	padding:clamp(22px,4vw,36px);
	margin-bottom:20px;
}
.ybt-appdl .ybt-carrier .ybt-apkicon{
	width:72px;height:72px;
	display:flex;align-items:center;justify-content:center;
	border-radius:50%;
	background:var(--kraft);
	border:1.5px dashed var(--kraft-deep);
	font-size:30px;
	color:var(--gold-deep);
	margin-bottom:6px;
}
.ybt-appdl .ybt-carrier h3{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.15rem,2.4vw,1.5rem);
	margin:0 0 4px;
}
.ybt-appdl .ybt-carrier p{ margin:0 0 14px; color:#3d4a42; font-size:14.5px; max-width:52ch; }
.ybt-appdl .ybt-stamp-corner{
	position:absolute;
	top:14px;right:14px;
}
/* meta strip under the button */
.ybt-appdl .ybt-dlmeta{
	display:flex;align-items:center;gap:14px;flex-wrap:wrap;justify-content:center;
	font-family:var(--mono);
	font-size:11.5px;font-weight:600;
	letter-spacing:.08em;text-transform:uppercase;
	color:#5c5543;
	margin-top:16px;
}
.ybt-appdl .ybt-dlmeta span{ display:inline-flex;align-items:center;gap:6px; }
.ybt-appdl .ybt-dlmeta i{ color:var(--gold-deep); }
/* feature + install doc */
.ybt-appdl .ybt-appdoc{
	display:grid;
	grid-template-columns:1fr 1fr;
	gap:20px;
	margin-bottom:20px;
}
.ybt-appdl .ybt-appdoc ul,
.ybt-appdl .ybt-appdoc ol{
	margin:0;padding:0 0 0 20px;
	color:#4a4232;
	font-size:14px;
	display:flex;flex-direction:column;gap:9px;
}
.ybt-appdl .ybt-appdoc code{
	font-family:var(--mono);
	font-size:12.5px;
	background:var(--kraft);
	border:1px solid var(--kraft-line);
	border-radius:4px;
	padding:1px 6px;
}
@media (max-width:720px){
	.ybt-appdl .ybt-appdoc{ grid-template-columns:1fr; }
}
</style>

<div class="ybt-dashboard-enhanced ybt-appdl">

	<!-- head -->
	<div class="ybt-dlhead">
		<p class="ybd-eyebrow">Dispatch notice &middot; Official mobile app</p>
		<h2>YBT Packing Service Mobile for Android</h2>
		<p>Scan tracking, view warehouse photos, and get notified the moment your parcel arrives in China.</p>
	</div>

	<!-- carrier panel with stamp + barcode -->
	<div class="ybd-panel ybt-carrier">
		<span class="ybd-stamp ybt-stamp-corner">Official build</span>
		<div class="ybt-apkicon"><i class="fab fa-android"></i></div>
		<h3>Download the app</h3>
		<p>Push notifications the moment your parcel arrives, photo proof for every carton, and packing requests in one place.</p>
		<a href="<?php echo esc_url(YBT_PLUGIN_URL . 'downloads/ybt-packing-service-v2.4.apk'); ?>" download class="ybd-btn ybd-btn-gold" style="padding:13px 28px;font-size:15.5px;">
			<i class="fas fa-download"></i>Download APK (v2.4)
		</a>
		<div class="ybt-dlmeta">
			<span><i class="fas fa-shield-alt"></i>Verified safe</span>
			<span><i class="fas fa-file-archive"></i>28.9&nbsp;MB</span>
			<span><i class="fas fa-code-branch"></i>Version 2.4</span>
			<span><i class="fas fa-mobile-alt"></i>Android 7.0+</span>
		</div>
	</div>

	<!-- features + install doc -->
	<div class="ybt-appdoc">
		<div class="ybd-panel">
			<div class="ybd-panelhead">
				<span class="ybd-hint"><i class="fas fa-star"></i>App features</span>
			</div>
			<div class="ybd-panelbody">
				<ul>
					<li><strong>Real-time tracking updates</strong></li>
					<li><strong>Warehouse photo proof viewer</strong></li>
					<li><strong>Carton packing requests</strong></li>
					<li><strong>Payment receipt uploads</strong></li>
					<li><strong>Live customer support chat</strong></li>
				</ul>
			</div>
		</div>

		<div class="ybd-panel ybd-doc-gold">
			<div class="ybd-panelhead">
				<span class="ybd-hint"><i class="fas fa-list-ol"></i>How to install</span>
			</div>
			<div class="ybd-panelbody">
				<ol>
					<li><strong>Download the file</strong> — tap the button above to get <code>ybt-packing-service-v2.4.apk</code>.</li>
					<li><strong>Allow unknown apps</strong> — Android Settings &rarr; Security/Privacy &rarr; allow installs from your browser.</li>
					<li><strong>Install</strong> — open Downloads, tap the APK, press Install.</li>
					<li><strong>Sign in</strong> — use your YBT account credentials.</li>
				</ol>
			</div>
		</div>
	</div>

	<!-- official-build note -->
	<div class="ybd-note">
		<i class="fas fa-lock"></i>
		<div>
			<strong>100% official APK guarantee</strong>
			<span>This is the verified official Android build signed by YBT Packing Service. Always download directly from this site.
			<br>Verify the download: SHA-256 <code>cb98109feda347617f2fde4d505f6c921e56912e5e546d3b3af0ac928a328efb</code> (v2.4, versionCode 7, package com.packtracker).</span>
		</div>
	</div>
</div>
