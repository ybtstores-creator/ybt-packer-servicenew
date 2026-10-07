<?php if (!defined('ABSPATH')) exit; ?>
<section class="ybt-homepage" id="ybtHome">
	<style>
	/* ================================================================
	   YBT HOMEPAGE — "The Consignment Note" (page-specific layer)
	   Shared tokens & atoms come from assets/css/ybt-consignment.css.
	   ================================================================ */

	/* ── the waybill (hero document) ─────────────────────────── */
	.ybt-homepage .yb-waybill{
		background:var(--paper);
		border:1.5px solid var(--ink);
		border-radius:12px;
		overflow:hidden;
		box-shadow:6px 6px 0 rgba(15,31,23,.12);
		animation:yb-settle .5s ease-out both;
	}
	@keyframes yb-settle{
		from{ opacity:0; transform:translateY(10px); }
		to{ opacity:1; transform:none; }
	}
	.ybt-homepage .yb-waybill-head{
		display:flex;
		align-items:center;
		justify-content:space-between;
		gap:12px;
		flex-wrap:wrap;
		padding:14px 22px;
		border-bottom:2px dashed var(--kraft-line);
		background:linear-gradient(0deg,var(--emerald-tint),var(--paper) 70%);
	}
	.ybt-homepage .yb-brandmark{
		display:flex;
		align-items:baseline;
		gap:10px;
	}
	.ybt-homepage .yb-brandmark strong{
		font-family:var(--display);
		font-size:22px;
		letter-spacing:.02em;
		color:var(--emerald-deep);
	}
	.ybt-homepage .yb-brandmark span{
		font-family:var(--mono);
		font-size:11px;
		letter-spacing:.2em;
		color:#5c5543;
		text-transform:uppercase;
	}
	.ybt-homepage .yb-waybill-body{
		display:grid;
		grid-template-columns:minmax(0,1.5fr) minmax(220px,1fr);
	}
	.ybt-homepage .yb-waybill-main{ padding:clamp(22px,4vw,40px); }
	.ybt-homepage .yb-waybill-aside{
		border-left:2px dashed var(--kraft-line);
		padding:clamp(18px,3vw,28px);
		display:flex;
		flex-direction:column;
		gap:14px;
		background:var(--kraft);
	}
	.ybt-homepage .yb-waybill h1{
		font-family:var(--display);
		font-weight:400; /* Archivo Black carries its own weight */
		font-size:clamp(1.9rem,4.6vw,3.2rem);
		line-height:1.06;
		letter-spacing:-.01em;
		margin:0 0 14px;
		color:var(--ink);
		text-wrap:balance;
	}
	.ybt-homepage .yb-waybill h1 em{
		font-style:normal;
		color:var(--emerald-deep);
	}
	.ybt-homepage .yb-waybill-main p{
		margin:0 0 22px;
		color:#3d4a42;
		max-width:52ch;
	}
	.ybt-homepage .yb-fields{
		display:grid;
		grid-template-columns:repeat(2,1fr);
		gap:10px 18px;
		margin:0 0 26px;
		padding:14px 16px;
		border:1px solid var(--kraft-line);
		border-radius:8px;
		background:#fbf8f1;
	}
	.ybt-homepage .yb-fields div{
		display:flex;
		flex-direction:column;
		gap:2px;
	}
	.ybt-homepage .yb-fields .yb-mono{
		font-size:13px;
		font-weight:600;
		color:var(--ink);
	}
	.ybt-homepage .yb-actions{
		display:flex;
		gap:12px;
		flex-wrap:wrap;
		align-items:center;
	}
	/* button sizes for the hero (base look comes from the shared sheet) */
	.ybt-homepage .yb-btn{ font-size:15px; padding:13px 24px; }
	.ybt-homepage .yb-btn-emerald{ padding:11px 22px; }
	.ybt-homepage .yb-barcode{
		height:42px;
		background:
			repeating-linear-gradient(90deg,
				var(--ink) 0 2px, transparent 2px 4px,
				var(--ink) 4px 7px, transparent 7px 9px,
				var(--ink) 9px 10px, transparent 10px 15px);
		border-radius:2px;
	}
	.ybt-homepage .yb-waybill-aside .yb-barcode{ margin-top:auto; }
	.ybt-homepage .yb-waybill-aside .yb-ref{
		font-family:var(--mono);
		font-size:12px;
		letter-spacing:.08em;
		color:#5c5543;
	}

	/* ── manifest (stats as ruled lines) ──────────────────────── */
	.ybt-homepage .yb-section{ margin-top:clamp(26px,4vw,40px); }
	.ybt-homepage .yb-panel{ padding:clamp(20px,3.4vw,32px); }
	.ybt-homepage .yb-panel > .yb-lede{ margin:0 0 22px; color:#3d4a42; }
	.ybt-homepage .yb-manifest{
		list-style:none;
		margin:0 0 20px;
		padding:0;
		font-family:var(--mono);
		font-size:14px;
		max-width:560px;
	}
	.ybt-homepage .yb-manifest li{
		display:flex;
		align-items:baseline;
		gap:10px;
		padding:9px 0;
		border-bottom:1px solid var(--kraft-line);
	}
	.ybt-homepage .yb-manifest .dots{
		flex:1;
		border-bottom:2px dotted var(--kraft-deep);
		transform:translateY(-4px);
	}
	.ybt-homepage .yb-manifest strong{
		font-weight:600;
		color:var(--emerald-deep);
		white-space:nowrap;
	}
	.ybt-homepage .yb-quote{
		margin:0;
		padding:14px 18px;
		border-left:4px solid var(--gold);
		background:var(--gold-tint);
		border-radius:0 8px 8px 0;
		font-style:italic;
		color:#4a4232;
	}
	.ybt-homepage .yb-quote footer{
		margin-top:8px;
		font-style:normal;
		font-family:var(--mono);
		font-size:12px;
		color:#6b6249;
	}

	/* ── routing: the real 5-stage lifecycle ──────────────────── */
	.ybt-homepage .yb-route{
		list-style:none;
		margin:0;
		padding:0;
		display:grid;
		grid-template-columns:repeat(5,1fr);
		position:relative;
	}
	.ybt-homepage .yb-route::before{
		content:'';
		position:absolute;
		top:19px;
		left:calc(10% + 19px);
		right:calc(10% + 19px);
		height:2px;
		background:repeating-linear-gradient(90deg,var(--emerald) 0 6px,transparent 6px 10px);
	}
	.ybt-homepage .yb-route li{
		position:relative;
		padding:0 10px;
		text-align:center;
	}
	.ybt-homepage .yb-route .num{
		width:40px;
		height:40px;
		margin:0 auto 12px;
		display:flex;
		align-items:center;
		justify-content:center;
		border-radius:50%;
		background:var(--paper);
		border:2px solid var(--emerald);
		color:var(--emerald-deep);
		font-family:var(--mono);
		font-weight:600;
		font-size:14px;
		position:relative;
		z-index:1;
	}
	.ybt-homepage .yb-route .name{
		display:block;
		font-weight:700;
		font-size:14px;
		margin-bottom:4px;
		color:var(--ink);
	}
	.ybt-homepage .yb-route p{
		margin:0;
		font-size:13px;
		color:#5a6a61;
	}

	/* ── services / pricing grids ─────────────────────────────── */
	.ybt-homepage .yb-grid{
		display:grid;
		grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
		gap:14px;
	}
	.ybt-homepage .yb-tile{
		border:1px solid var(--kraft-line);
		border-radius:8px;
		background:#fbf8f1;
		padding:18px;
	}
	.ybt-homepage .yb-tile h3{
		margin:8px 0 6px;
		font-size:15px;
		font-weight:700;
		color:var(--ink);
	}
	.ybt-homepage .yb-tile p{
		margin:0;
		font-size:14px;
		color:#5a6a61;
	}
	.ybt-homepage .yb-tag{
		display:inline-block;
		font-family:var(--mono);
		font-size:10px;
		font-weight:600;
		letter-spacing:.12em;
		text-transform:uppercase;
		padding:3px 8px;
		border-radius:4px;
		border:1px solid var(--emerald);
		color:var(--emerald-deep);
		background:var(--emerald-tint);
	}
	.ybt-homepage .yb-tile-gold .yb-tag{
		border-color:var(--gold);
		color:var(--gold-deep);
		background:var(--gold-tint);
	}
	.ybt-homepage .yb-price{
		font-family:var(--mono);
		font-size:30px;
		font-weight:600;
		color:var(--emerald-deep);
		margin:10px 0 0;
	}
	.ybt-homepage .yb-price small{
		font-family:var(--sans);
		font-size:12px;
		color:#5a6a61;
		font-weight:500;
	}
	.ybt-homepage .yb-tier{
		position:relative;
		border:1px solid var(--kraft-line);
		border-radius:8px;
		background:#fbf8f1;
		padding:20px;
	}
	.ybt-homepage .yb-tier ul{
		margin:12px 0 0;
		padding-left:18px;
		font-size:14px;
		color:#3d4a42;
	}
	.ybt-homepage .yb-tier-top{
		border:1.5px solid var(--gold);
		background:var(--gold-tint);
	}
	.ybt-homepage .yb-tier-top .yb-price{ color:var(--gold-deep); }
	.ybt-homepage .yb-tier .yb-stamp{
		position:absolute;
		top:14px;
		right:14px;
		font-size:9px;
		padding:4px 8px;
	}

	/* ── FAQ fine print ───────────────────────────────────────── */
	.ybt-homepage .yb-faq details{
		border:1px solid var(--kraft-line);
		border-radius:8px;
		background:#fbf8f1;
		padding:14px 18px;
		margin-bottom:10px;
	}
	.ybt-homepage .yb-faq summary{
		cursor:pointer;
		font-weight:700;
		font-size:15px;
		color:var(--ink);
	}
	.ybt-homepage .yb-faq p{ margin:10px 0 0; font-size:14px; color:#3d4a42; }

	/* ── auth: two label panels ───────────────────────────────── */
	.ybt-homepage .yb-access{
		display:grid;
		grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
		gap:16px;
	}
	.ybt-homepage .yb-access-panel{
		background:var(--paper);
		border:1.5px solid var(--ink);
		border-radius:12px;
		padding:24px;
	}
	.ybt-homepage .yb-access-panel h3{
		margin:0 0 4px;
		font-family:var(--mono);
		font-size:12px;
		font-weight:600;
		letter-spacing:.18em;
		text-transform:uppercase;
		color:var(--emerald-deep);
	}
	.ybt-homepage .yb-access-panel label{
		display:block;
		font-size:13px;
		font-weight:600;
		margin:12px 0 4px;
		color:#33413a;
	}
	.ybt-homepage .yb-input{ width:100%; padding:11px 14px; }
	.ybt-homepage .yb-access-panel .yb-btn{ width:100%; margin-top:16px; }
	.ybt-homepage .yb-check{ display:flex; gap:8px; align-items:center; margin-top:12px; font-size:14px; }
	.ybt-homepage .yb-check input{ accent-color:var(--emerald-deep); width:16px; height:16px; }

	/* alerts: display/colors come from the shared sheet (classes locked by the JS) */

	/* ── footer CTA ───────────────────────────────────────────── */
	.ybt-homepage .yb-footer-cta{
		margin-top:clamp(26px,4vw,40px);
		background:var(--emerald-deep);
		color:#fff;
		border-radius:12px;
		padding:clamp(22px,3.6vw,36px);
		display:flex;
		flex-direction:column;
		gap:18px;
		box-shadow:6px 6px 0 rgba(15,31,23,.12);
	}
	.ybt-homepage .yb-footer-cta h2{
		font-family:var(--display);
		font-weight:400;
		font-size:clamp(1.2rem,2.6vw,1.6rem);
		margin:0;
		color:#fff;
	}
	.ybt-homepage .yb-footer-cta p{ margin:6px 0 0; color:rgba(255,255,255,.85); }
	.ybt-homepage .yb-footer-cta a{ color:var(--gold); font-weight:700; }
	.ybt-homepage .yb-footer-cta .yb-barcode{ background:repeating-linear-gradient(90deg, var(--gold) 0 2px, transparent 2px 4px, var(--gold) 4px 7px, transparent 7px 9px, var(--gold) 9px 10px, transparent 10px 15px); opacity:.9; max-width:280px; }

	/* ── responsive ───────────────────────────────────────────── */
	@media (max-width:900px){
		.ybt-homepage .yb-route{
			grid-template-columns:1fr;
			gap:18px;
		}
		.ybt-homepage .yb-route::before{
			top:0; bottom:0; left:19px; right:auto;
			width:2px; height:auto;
			background:repeating-linear-gradient(180deg,var(--emerald) 0 6px,transparent 6px 10px);
		}
		.ybt-homepage .yb-route li{
			display:grid;
			grid-template-columns:40px 1fr;
			column-gap:14px;
			text-align:left;
			padding:0;
		}
		.ybt-homepage .yb-route .num{ margin:0; }
	}
	@media (max-width:760px){
		.ybt-homepage .yb-waybill-body{ grid-template-columns:1fr; }
		.ybt-homepage .yb-waybill-aside{
			border-left:none;
			border-top:2px dashed var(--kraft-line);
		}
		.ybt-homepage .yb-fields{ grid-template-columns:1fr; }
	}
	</style>

	<!-- ══ THE WAYBILL ═════════════════════════════════════════ -->
	<div class="yb-waybill">
		<div class="yb-waybill-head">
			<div class="yb-brandmark">
				<strong>YBT</strong>
				<span>Packing Service · Consignment Note</span>
			</div>
			<span class="yb-stamp">Premium packing</span>
		</div>
		<div class="yb-waybill-body">
			<div class="yb-waybill-main">
				<p class="yb-eyebrow">Warehouse intake · Every parcel photographed at every step</p>
				<h1>We receive. We pack. <em>You ship with proof.</em></h1>
				<p>Add a tracking number the moment you order online. When your parcel reaches our warehouse we inspect it, pack it properly, and consolidate your boxes — with timestamped photos and weight logs before anything is sealed.</p>
				<div class="yb-fields">
					<div>
						<span class="yb-label">Origin</span>
						<span class="yb-mono">YBT Warehouse</span>
					</div>
					<div>
						<span class="yb-label">Destination</span>
						<span class="yb-mono">Your doorstep</span>
					</div>
					<div>
						<span class="yb-label">Service</span>
						<span class="yb-mono">Pack + Consolidate</span>
					</div>
					<div>
						<span class="yb-label">Weight</span>
						<span class="yb-mono">Measured at intake</span>
					</div>
				</div>
				<div class="yb-actions">
					<a class="yb-btn yb-btn-gold" href="javascript:void(0)" onclick="if(typeof openPackingRequestModal === 'function'){ openPackingRequestModal(); }else{ location.href='/?s=packing'; }">Request packing</a>
					<a class="yb-btn yb-btn-emerald" href="#ybtAccess">Create an account</a>
				</div>
			</div>
			<div class="yb-waybill-aside">
				<div>
					<span class="yb-label">Ref</span>
					<div class="yb-ref">YBT-2026-WB-000417</div>
				</div>
				<div>
					<span class="yb-label">Status</span>
					<div class="yb-ref" style="color:var(--emerald-deep);font-weight:600;">ACCEPTED — PACKING TODAY</div>
				</div>
				<div style="border-top:2px dashed var(--kraft-line);padding-top:12px;">
					<span class="yb-label">Handling</span>
					<div class="yb-ref" style="margin-top:4px;line-height:2;">
						⊘ Fragile — double boxed<br>
						↑ This side up<br>
						⚖ Weight logged at intake
					</div>
				</div>
				<div class="yb-barcode" aria-hidden="true"></div>
			</div>
		</div>
	</div>

	<!-- ══ MANIFEST (proof, as ruled lines) ════════════════════ -->
	<div class="yb-section">
		<div class="yb-panel">
			<p class="yb-eyebrow">Manifest · This year</p>
			<h2>Trusted by global shoppers</h2>
			<p class="yb-lede">Thousands of forwarding customers depend on us to consolidate and prep their parcels every month.</p>
			<ul class="yb-manifest">
				<li><span>Boxes packed this year</span><span class="dots" aria-hidden="true"></span><strong>15,000+</strong></li>
				<li><span>Average turnaround</span><span class="dots" aria-hidden="true"></span><strong>24 hrs</strong></li>
				<li><span>On-time dispatch rate</span><span class="dots" aria-hidden="true"></span><strong>98%</strong></li>
			</ul>
			<blockquote class="yb-quote">
				“YBT keeps my reselling business on schedule. Their packing photos and notifications are the most reliable I’ve used.”
				<footer>— Amira, EU fashion seller</footer>
			</blockquote>
		</div>
	</div>

	<!-- ══ ROUTING (the real lifecycle) ════════════════════════ -->
	<div class="yb-section">
		<div class="yb-panel">
			<p class="yb-eyebrow">Routing · Every parcel follows the same line</p>
			<h2>How your parcel moves</h2>
			<p class="yb-lede">These are the exact five statuses you will see in your dashboard and the mobile app — nothing happens off the record.</p>
			<ol class="yb-route">
				<li>
					<span class="num" aria-hidden="true">1</span>
					<div>
						<span class="name">Not received</span>
						<p>You add the tracking number after ordering online.</p>
					</div>
				</li>
				<li>
					<span class="num" aria-hidden="true">2</span>
					<div>
						<span class="name">Received</span>
						<p>The courier delivers it and our team scans it in.</p>
					</div>
				</li>
				<li>
					<span class="num" aria-hidden="true">3</span>
					<div>
						<span class="name">Packing in progress</span>
						<p>You select the parcels and request packing.</p>
					</div>
				</li>
				<li>
					<span class="num" aria-hidden="true">4</span>
					<div>
						<span class="name">Packed</span>
						<p>Boxed, sealed and photographed, ready to ship.</p>
					</div>
				</li>
				<li>
					<span class="num" aria-hidden="true">5</span>
					<div>
						<span class="name">Completed</span>
						<p>Handed to your carrier with the paperwork attached.</p>
					</div>
				</li>
			</ol>
		</div>
	</div>

	<!-- ══ WHAT WE HANDLE ══════════════════════════════════════ -->
	<div class="yb-section">
		<div class="yb-panel">
			<p class="yb-eyebrow">Cargo classes</p>
			<h2>What we can handle</h2>
			<div class="yb-grid" style="margin-top:18px;">
				<div class="yb-tile yb-tile-gold">
					<span class="yb-tag">Fragile</span>
					<h3>Fragile &amp; luxury</h3>
					<p>Padded wrapping, double-boxing, and photo proof before sealing.</p>
				</div>
				<div class="yb-tile">
					<span class="yb-tag">Bulk</span>
					<h3>Bulk consolidation</h3>
					<p>Combine dozens of parcels into export-ready boxes with itemized manifests.</p>
				</div>
				<div class="yb-tile">
					<span class="yb-tag">Regulated</span>
					<h3>Regulated goods</h3>
					<p>Battery, cosmetics, and apparel compliance checks aligned with carrier rules.</p>
				</div>
				<div class="yb-tile yb-tile-gold">
					<span class="yb-tag">Custom</span>
					<h3>Custom instructions</h3>
					<p>Personal notes, gift cards, or repackaging in branded material on request.</p>
				</div>
			</div>
		</div>
	</div>

	<!-- ══ SERVICE CLASSES (pricing) ═══════════════════════════ -->
	<div class="yb-section">
		<div class="yb-panel">
			<p class="yb-eyebrow">Service classes</p>
			<h2>Simple pricing</h2>
			<p class="yb-lede">Final quotes depend on size and materials. Use these classes as a quick guide and request precise pricing from your dashboard.</p>
			<div class="yb-grid" style="margin-top:18px;">
				<div class="yb-tier">
					<span class="yb-label">Class A</span>
					<p class="yb-price">$8 <small>per parcel</small></p>
					<ul>
						<li>Up to 5kg</li>
						<li>Bubble wrap + filler</li>
						<li>Status photos</li>
					</ul>
				</div>
				<div class="yb-tier yb-tier-top">
					<span class="yb-stamp">Most picked</span>
					<span class="yb-label">Class B</span>
					<p class="yb-price">$15 <small>per large box</small></p>
					<ul>
						<li>Up to 20kg</li>
						<li>Custom dividers</li>
						<li>Insurance-ready labeling</li>
					</ul>
				</div>
				<div class="yb-tier">
					<span class="yb-label">Class C</span>
					<p class="yb-price">$25 <small>per order</small></p>
					<ul>
						<li>Same-day turnaround</li>
						<li>Dedicated agent</li>
						<li>Email progress updates</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<!-- ══ FINE PRINT (FAQ) ════════════════════════════════════ -->
	<div class="yb-section">
		<div class="yb-panel">
			<p class="yb-eyebrow">Fine print</p>
			<h2>Questions, answered</h2>
			<div class="yb-faq" style="margin-top:18px;">
				<details>
					<summary>Can I ship with any carrier?</summary>
					<p>Yes. We provide neutral packaging and paperwork so you can hand parcels to DHL, FedEx, Aramex, or local freight forwarders.</p>
				</details>
				<details>
					<summary>Do you store parcels?</summary>
					<p>Included storage covers 30 days after arrival. Extended storage is available for a small weekly fee inside your dashboard.</p>
				</details>
				<details>
					<summary>What proof do I receive?</summary>
					<p>We attach timestamped photos, weight logs, and packing material lists to every request before marking it packed.</p>
				</details>
				<details>
					<summary>How do I reach support?</summary>
					<p>Use the in-app chat or email support@ybtpacker.com for urgent help.</p>
				</details>
			</div>
		</div>
	</div>

	<!-- ══ ACCESS (login + register) ═══════════════════════════ -->
	<div class="yb-section" id="ybtAccess">
		<div class="yb-panel">
			<p class="yb-eyebrow">Access</p>
			<h2>Your account</h2>
			<p class="yb-lede">Log in to see your received parcels, or create an account to add your first tracking number.</p>
			<div class="yb-access" style="margin-top:18px;">
				<div class="yb-access-panel">
					<h3>Log in</h3>
					<form id="ybt-home-login-form" method="POST">
						<label for="home-login-username">Username</label>
						<input type="text" id="home-login-username" class="yb-input" autocomplete="username" required>
						<label for="home-login-password">Password</label>
						<input type="password" id="home-login-password" class="yb-input" autocomplete="current-password" required>
						<div class="yb-check">
							<input type="checkbox" id="home-login-remember">
							<label for="home-login-remember" style="margin:0;font-weight:500;">Remember me</label>
						</div>
						<button type="submit" class="yb-btn yb-btn-gold">Log in</button>
						<div id="home-login-message" class="ybt-alert"></div>
					</form>
				</div>
				<div class="yb-access-panel">
					<h3>Register</h3>
					<form id="ybt-home-register-form" method="POST">
						<label for="home-register-username">Username</label>
						<input type="text" id="home-register-username" class="yb-input" autocomplete="username" required>
						<label for="home-register-email">Email</label>
						<input type="email" id="home-register-email" class="yb-input" autocomplete="email" required>
						<label for="home-register-password">Password</label>
						<input type="password" id="home-register-password" class="yb-input" autocomplete="new-password" required>
						<label for="home-register-confirm">Confirm password</label>
						<input type="password" id="home-register-confirm" class="yb-input" autocomplete="new-password" required>
						<button type="submit" class="yb-btn yb-btn-emerald">Create account</button>
						<div id="home-register-message" class="ybt-alert"></div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- ══ FOOTER CTA ══════════════════════════════════════════ -->
	<div class="yb-footer-cta">
		<div>
			<h2>Need a custom packing plan?</h2>
			<p>Email <a href="mailto:support@ybtpacker.com">support@ybtpacker.com</a> or use the in-app chat for instant answers.</p>
		</div>
		<div class="yb-barcode" aria-hidden="true"></div>
		<div class="yb-mono" style="font-size:12px;color:rgba(255,255,255,.75);">Business hours: Mon–Sat, 8am–10pm GMT</div>
	</div>

	<script>
	(function(){
		var ajaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
		var nonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.nonce) ? ybt_ajax.nonce : '<?php echo wp_create_nonce('ybt_nonce'); ?>';

		// Login
		var loginForm = document.getElementById('ybt-home-login-form');
		if (loginForm) {
			loginForm.addEventListener('submit', function(e){
				e.preventDefault();
				var u = document.getElementById('home-login-username').value;
				var p = document.getElementById('home-login-password').value;
				var r = document.getElementById('home-login-remember').checked ? '1' : '0';
				var msg = document.getElementById('home-login-message');
				msg.style.display = 'none'; msg.className = 'ybt-alert'; msg.textContent = '';

				var fd = new FormData();
				fd.append('action', 'ybt_ajax_login');
				fd.append('nonce', nonce);
				fd.append('username', u);
				fd.append('password', p);
				fd.append('remember', r);

				fetch(ajaxUrl, { method:'POST', body: fd, credentials: 'same-origin' })
					.then(function(res){ return res.json(); })
					.then(function(data){
						if (data && data.success) {
							window.location.href = '<?php echo esc_url( home_url('/dashboard/') ); ?>';
						} else {
							msg.classList.add('ybt-alert-error');
							msg.textContent = data && data.data ? data.data : 'Login failed';
							msg.style.display = 'block';
						}
					})
					.catch(function(){
						msg.classList.add('ybt-alert-error');
						msg.textContent = 'Network error. Please try again.';
						msg.style.display = 'block';
					});
			});
		}

		// Register
		var regForm = document.getElementById('ybt-home-register-form');
		if (regForm) {
			regForm.addEventListener('submit', function(e){
				e.preventDefault();
				var u = document.getElementById('home-register-username').value;
				var em = document.getElementById('home-register-email').value;
				var p = document.getElementById('home-register-password').value;
				var c = document.getElementById('home-register-confirm').value;
				var msg = document.getElementById('home-register-message');
				msg.style.display = 'none'; msg.className = 'ybt-alert'; msg.textContent = '';

				var fd = new URLSearchParams();
				fd.append('action', 'ybt_register_user');
				fd.append('nonce', nonce);
				fd.append('username', u);
				fd.append('email', em);
				fd.append('password', p);
				fd.append('confirm_password', c);

				fetch(ajaxUrl, { method:'POST', headers: { 'Content-Type':'application/x-www-form-urlencoded' }, body: fd.toString(), credentials: 'same-origin' })
					.then(function(res){ return res.json(); })
					.then(function(data){
						if (data && data.success) {
							msg.classList.add('ybt-alert-success');
							msg.textContent = data.data || 'Registration successful! Redirecting...';
							msg.style.display = 'block';
							setTimeout(function(){ window.location.href = '<?php echo esc_url( home_url('/dashboard/') ); ?>'; }, 500);
						} else {
							msg.classList.add('ybt-alert-error');
							msg.textContent = data && data.data ? data.data : 'Registration failed';
							msg.style.display = 'block';
						}
					})
					.catch(function(){
						msg.classList.add('ybt-alert-error');
						msg.textContent = 'Network error. Please try again.';
						msg.style.display = 'block';
					});
			});
		}
	})();
	</script>
</section>
