<?php
if (!defined('ABSPATH')) exit;

// Get rate per kg from settings (default: 3.5 Yuan)
$rate_per_kg = get_option('ybt_rate_per_kg', 3.5);

// Hide admin bar
add_action('wp_head', function() {
    echo '<style>
        #wpadminbar { display: none !important; }
        body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        html { margin-top: 0 !important; }
    </style>';
}, 2);
?>

<style>
/* ================================================================
   PUBLIC LANDING — "The Consignment Hall" (page-specific layer)
   Shared tokens & atoms come from assets/css/ybt-consignment.css.
   Scope root: .ybt-homepage
   ================================================================ */
.ybt-homepage{
	margin:clamp(14px,3vw,36px) auto;
	max-width:1120px;
}
/* ── hero: the waybill ─────────────────────────────────────── */
.ybt-homepage .ybh-hero{
	display:grid;
	grid-template-columns:1.05fr .95fr;
	gap:clamp(20px,4vw,40px);
	align-items:center;
	padding:clamp(20px,4vw,40px);
}
.ybt-homepage .ybh-hero-copy h1{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.7rem,4.4vw,2.7rem);
	line-height:1.14;
	margin:0 0 14px;
}
.ybt-homepage .ybh-hero-copy h1 em{ font-style:normal; color:var(--emerald-deep); }
.ybt-homepage .ybh-hero-copy .ybh-lede{
	margin:0 0 22px;
	color:#3d4a42;
	font-size:clamp(15px,1.6vw,17px);
	max-width:46ch;
}
.ybt-homepage .ybh-heroactions{ display:flex; gap:12px; flex-wrap:wrap; }
.ybt-homepage .ybh-heroshot{ position:relative; }
.ybt-homepage .ybh-heroshot img{
	width:100%;
	height:320px;
	object-fit:cover;
	display:block;
	border:1.5px solid var(--ink);
	border-radius:10px;
	box-shadow:6px 6px 0 rgba(15,31,23,.12);
}
.ybt-homepage .ybh-heroshot .ybh-prooftag{
	position:absolute;
	left:14px;bottom:14px;
	display:inline-flex;align-items:center;gap:9px;
	background:var(--paper);
	border:1.5px solid var(--ink);
	border-radius:8px;
	padding:8px 12px;
	box-shadow:3px 3px 0 rgba(15,31,23,.15);
}
.ybt-homepage .ybh-heroshot .ybh-prooftag i{
	width:28px;height:28px;
	display:flex;align-items:center;justify-content:center;
	border-radius:50%;
	background:var(--emerald);
	color:#fff;
	font-size:12px;
}
.ybt-homepage .ybh-heroshot .ybh-prooftag strong{ display:block; font-size:12.5px; color:var(--ink); }
.ybt-homepage .ybh-heroshot .ybh-prooftag span{ display:block; font-family:var(--mono); font-size:10.5px; color:#5c5543; }
/* ── rate banner ───────────────────────────────────────────── */
.ybt-homepage .ybh-rate{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:18px;
	flex-wrap:wrap;
	background:var(--paper);
	border:1.5px solid var(--ink);
	border-radius:10px;
	box-shadow:5px 5px 0 rgba(15,31,23,.1);
	margin-top:clamp(18px,3vw,28px);
	padding:clamp(18px,3vw,26px) clamp(18px,3vw,30px);
}
.ybt-homepage .ybh-rate .ybh-ratecopy h3{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.05rem,2vw,1.3rem);
	margin:8px 0 4px;
}
.ybt-homepage .ybh-rate .ybh-ratecopy p{ margin:0; color:#3d4a42; font-size:14px; }
.ybt-homepage .ybh-ratetag{
	font-family:var(--mono);
	font-size:26px;font-weight:600;
	color:var(--emerald-deep);
	background:var(--paper);
	border:1.5px solid var(--emerald);
	border-radius:10px;
	padding:12px 18px;
	white-space:nowrap;
	box-shadow:3px 3px 0 rgba(4,120,87,.2);
}
.ybt-homepage .ybh-ratetag small{
	display:block;
	font-size:10px;font-weight:600;
	letter-spacing:.1em;text-transform:uppercase;
	color:#5c5543;
	margin-top:2px;
}
/* ── services: four cargo tiles ────────────────────────────── */
.ybt-homepage .ybh-sectionhead{ text-align:center; margin:clamp(26px,4vw,40px) 0 18px; }
.ybt-homepage .ybh-sectionhead h2{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.2rem,2.6vw,1.55rem);
	margin:0 0 4px;
}
.ybt-homepage .ybh-sectionhead p{ margin:0; color:#5a6a61; font-size:14px; }
.ybt-homepage .ybh-tiles{
	display:grid;
	grid-template-columns:repeat(4,1fr);
	gap:16px;
}
.ybt-homepage .ybh-tile{
	background:var(--paper);
	border:1.5px solid var(--ink);
	border-radius:10px;
	box-shadow:5px 5px 0 rgba(15,31,23,.1);
	padding:20px 18px;
}
.ybt-homepage .ybh-tile .ybh-tilestep{
	font-family:var(--mono);
	font-size:10.5px;font-weight:600;
	letter-spacing:.14em;text-transform:uppercase;
	color:var(--gold-deep);
	display:block;
	margin-bottom:8px;
}
.ybt-homepage .ybh-tile .ybh-tileicon{
	width:46px;height:46px;
	display:flex;align-items:center;justify-content:center;
	border-radius:10px;
	background:var(--emerald-tint);
	border:1.5px solid var(--emerald);
	color:var(--emerald-deep);
	font-size:19px;
	margin-bottom:10px;
}
.ybt-homepage .ybh-tile.gold .ybh-tileicon{ background:var(--gold-tint); border-color:var(--gold); color:var(--gold-deep); }
.ybt-homepage .ybh-tile h3{
	font-family:var(--sans);
	font-weight:700;
	font-size:15px;
	margin:0 0 6px;
	color:var(--ink);
}
.ybt-homepage .ybh-tile p{ margin:0; color:#5a6a61; font-size:13.5px; }
/* ── how it works: three steps ─────────────────────────────── */
.ybt-homepage .ybh-steps{
	display:grid;
	grid-template-columns:repeat(3,1fr);
	gap:16px;
	margin-top:18px;
}
.ybt-homepage .ybh-step{
	background:var(--paper);
	border:1.5px solid var(--kraft-line);
	border-radius:10px;
	padding:22px 18px;
	text-align:center;
	position:relative;
}
.ybt-homepage .ybh-step .ybh-stepno{
	width:42px;height:42px;
	margin:0 auto 12px;
	display:flex;align-items:center;justify-content:center;
	border-radius:50%;
	font-family:var(--mono);
	font-weight:600;
	font-size:16px;
	background:var(--emerald-tint);
	border:1.5px solid var(--emerald);
	color:var(--emerald-deep);
}
.ybt-homepage .ybh-step.gold .ybh-stepno{ background:var(--gold-tint); border-color:var(--gold); color:var(--gold-deep); }
.ybt-homepage .ybh-step h3{
	font-family:var(--sans);
	font-weight:700;
	font-size:15px;
	margin:0 0 6px;
	color:var(--ink);
}
.ybt-homepage .ybh-step p{ margin:0; color:#5a6a61; font-size:13.5px; }
/* ── guest CTA ─────────────────────────────────────────────── */
.ybt-homepage .ybh-cta{
	text-align:center;
	margin-top:clamp(26px,4vw,40px);
	padding:clamp(24px,4vw,38px);
	background:var(--paper);
	border:1.5px solid var(--ink);
	border-radius:12px;
	box-shadow:6px 6px 0 rgba(15,31,23,.12);
}
.ybt-homepage .ybh-cta h3{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.15rem,2.4vw,1.45rem);
	margin:0 0 6px;
}
.ybt-homepage .ybh-cta p{ margin:0 auto 18px; color:#5a6a61; font-size:14.5px; max-width:52ch; }
.ybt-homepage .ybh-cta .ybh-ctarow{ display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
/* ── responsive ────────────────────────────────────────────── */
@media (max-width:920px){
	.ybt-homepage .ybh-hero{ grid-template-columns:1fr; }
	.ybt-homepage .ybh-tiles{ grid-template-columns:repeat(2,1fr); }
	.ybt-homepage .ybh-steps{ grid-template-columns:1fr; }
}
@media (max-width:560px){
	.ybt-homepage .ybh-tiles{ grid-template-columns:1fr; }
	.ybt-homepage .ybh-heroshot img{ height:230px; }
}
</style>

<div class="ybt-homepage">

	<!-- hero: the waybill -->
	<section class="ybh-hero">
		<div class="ybh-hero-copy">
			<p class="yb-eyebrow">YBT Packing Service &middot; China warehouse &rarr; worldwide</p>
			<h1>We pack &amp; forward your parcels, <em>worldwide</em></h1>
			<p class="ybh-lede">Receive goods from any store in China. We store, inspect, consolidate, and prep your shipments for global dispatch.</p>
			<div class="ybh-heroactions">
				<?php if (!is_user_logged_in()): ?>
				<a href="<?php echo home_url('/register/'); ?>" class="yb-btn yb-btn-gold"><i class="fas fa-rocket"></i>Get started free</a>
				<a href="<?php echo home_url('/login/'); ?>" class="yb-btn yb-btn-emerald"><i class="fas fa-sign-in-alt"></i>Sign in</a>
				<?php else: ?>
				<a href="<?php echo home_url('/dashboard/'); ?>" class="yb-btn yb-btn-gold"><i class="fas fa-tachometer-alt"></i>Go to dashboard</a>
				<?php endif; ?>
			</div>
		</div>
		<div class="ybh-heroshot">
			<img src="<?php echo plugin_dir_url(dirname(__FILE__)); ?>assets/images/repacking-process.webp" alt="Package Repacking Process" loading="eager" fetchpriority="high" decoding="async">
			<div class="ybh-prooftag">
				<i class="fas fa-check"></i>
				<div>
					<strong>Verified inspection</strong>
					<span>HD photo proof available</span>
				</div>
			</div>
		</div>
	</section>

	<!-- rate banner -->
	<section class="ybh-rate">
		<div class="ybh-ratecopy">
			<p class="yb-eyebrow" style="margin-bottom:0;">Official shipping rate</p>
			<h3>Transparent freight pricing</h3>
			<p>No hidden service fees. Pay per weight with full packing photos.</p>
		</div>
		<div class="ybh-ratetag">
			<?php echo esc_html($rate_per_kg); ?> Yuan/kg
			<small>Standard consolidation</small>
		</div>
	</section>

	<!-- services: four cargo tiles -->
	<section>
		<div class="ybh-sectionhead">
			<p class="yb-eyebrow">The route</p>
			<h2>What we do</h2>
			<p>End-to-end handling from supplier delivery to international forwarder.</p>
		</div>
		<div class="ybh-tiles">
			<div class="ybh-tile">
				<span class="ybh-tilestep">Stop 1</span>
				<div class="ybh-tileicon"><i class="fas fa-boxes"></i></div>
				<h3>Parcel receiving</h3>
				<p>We accept parcels from Taobao, 1688, Pinduoduo, or private vendors into our secure warehouse.</p>
			</div>
			<div class="ybh-tile gold">
				<span class="ybh-tilestep">Stop 2</span>
				<div class="ybh-tileicon"><i class="fas fa-layer-group"></i></div>
				<h3>Consolidation</h3>
				<p>Combine multiple packages into compact, reinforced export cartons to save on volumetric weight.</p>
			</div>
			<div class="ybh-tile">
				<span class="ybh-tilestep">Stop 3</span>
				<div class="ybh-tileicon"><i class="fas fa-shipping-fast"></i></div>
				<h3>Forwarding</h3>
				<p>Hand over sealed cartons directly to your preferred logistics line (DHL, Aramex, sea/air cargo).</p>
			</div>
			<div class="ybh-tile gold">
				<span class="ybh-tilestep">Stop 4</span>
				<div class="ybh-tileicon"><i class="fas fa-shield-alt"></i></div>
				<h3>Quality protection</h3>
				<p>Bubble wrap, waterproof taping, corner guards, and photo confirmation before final dispatch.</p>
			</div>
		</div>
	</section>

	<!-- how it works: three steps -->
	<section>
		<div class="ybh-sectionhead">
			<p class="yb-eyebrow">Shipper's instructions</p>
			<h2>Simple 3-step process</h2>
		</div>
		<div class="ybh-steps">
			<div class="ybh-step">
				<div class="ybh-stepno">1</div>
				<h3>Register &amp; add tracking</h3>
				<p>Create an account to get your unique YBT ID. Submit your supplier tracking numbers as you order.</p>
			</div>
			<div class="ybh-step gold">
				<div class="ybh-stepno">2</div>
				<h3>Select &amp; request packing</h3>
				<p>When your items arrive in warehouse status, select them and submit your shipping company address.</p>
			</div>
			<div class="ybh-step">
				<div class="ybh-stepno">3</div>
				<h3>Pay &amp; dispatch</h3>
				<p>Review packed carton photos, weight summary, and upload payment evidence for instant release.</p>
			</div>
		</div>
	</section>

	<!-- guest CTA -->
	<?php if (!is_user_logged_in()): ?>
	<section class="ybh-cta">
		<h3>Ready to start shipping?</h3>
		<p>Create your account in 30 seconds and get your warehouse receiving address immediately.</p>
		<div class="ybh-ctarow">
			<a href="<?php echo home_url('/register/'); ?>" class="yb-btn yb-btn-gold"><i class="fas fa-user-plus"></i>Create free account</a>
			<a href="<?php echo home_url('/anonymous/'); ?>" class="yb-btn yb-btn-emerald"><i class="fas fa-user-secret"></i>Continue as guest</a>
		</div>
	</section>
	<?php endif; ?>

</div>
