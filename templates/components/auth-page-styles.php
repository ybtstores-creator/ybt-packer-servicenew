<?php if (!defined('ABSPATH')) exit;
/**
 * Auth-page-specific rules (login / register / reset-password).
 * Shared tokens & atoms live in assets/css/ybt-consignment.css (enqueued);
 * this block only adds what is unique to the consignment-counter layout.
 */
?>
<style>
/* ================================================================
   YBT AUTH — "The Consignment Counter" (page-specific layer)
   ================================================================ */
.ybt-authwrap{
	min-height:100vh;
	display:flex;
	align-items:center;
	justify-content:center;
	padding:clamp(16px,3vw,40px);
}
/* ── the card ─────────────────────────────────────────────── */
.ybt-authwrap .ybt-authcard{
	background:var(--paper);
	border:1.5px solid var(--ink);
	border-radius:12px;
	box-shadow:8px 8px 0 rgba(15,31,23,.14);
	width:100%;
	max-width:960px;
	display:grid;
	grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);
	overflow:hidden;
	animation:ybt-auth-in .45s ease-out both;
}
.ybt-authwrap .ybt-authcard-solo{
	max-width:480px;
	grid-template-columns:1fr;
}
@keyframes ybt-auth-in{
	from{ opacity:0; transform:translateY(12px); }
	to{ opacity:1; transform:none; }
}

/* ── carrier's copy (brand side) ──────────────────────────── */
.ybt-authwrap .ybt-authbrand{
	position:relative;
	background:var(--emerald-deep);
	color:#fff;
	padding:clamp(26px,4vw,40px);
	display:flex;
	flex-direction:column;
	gap:22px;
	overflow:hidden;
}
.ybt-authwrap .ybt-authbrand::before{
	content:'';
	position:absolute;
	top:-90px;right:-90px;
	width:280px;height:280px;
	border-radius:50%;
	background:rgba(212,175,55,.14);
}
.ybt-authwrap .ybt-authbrand .ybt-authmark{
	display:flex;
	align-items:baseline;
	gap:10px;
}
.ybt-authwrap .ybt-authbrand .ybt-authmark strong{
	font-family:var(--display);
	font-weight:400;
	font-size:26px;
	color:#fff;
	letter-spacing:.02em;
}
.ybt-authwrap .ybt-authbrand .ybt-authmark span{
	font-family:var(--mono);
	font-size:10px;font-weight:600;
	letter-spacing:.2em;text-transform:uppercase;
	color:var(--gold);
}
.ybt-authwrap .ybt-authbrand h1{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.35rem,2.4vw,1.8rem);
	line-height:1.15;
	margin:0;
	color:#fff;
}
.ybt-authwrap .ybt-authbrand .ybt-authlede{
	margin:0;
	color:rgba(255,255,255,.85);
	font-size:14.5px;
	max-width:40ch;
}
.ybt-authwrap .ybt-authfeats{
	margin-top:auto;
	border-top:2px dashed rgba(255,255,255,.35);
	padding-top:18px;
	display:flex;
	flex-direction:column;
	gap:14px;
}
.ybt-authwrap .ybt-authfeat{
	display:flex;
	align-items:flex-start;
	gap:12px;
}
.ybt-authwrap .ybt-authfeat i{
	width:34px;height:34px;
	flex-shrink:0;
	display:flex;align-items:center;justify-content:center;
	border:1.5px solid var(--gold);
	border-radius:8px;
	color:var(--gold);
	font-size:14px;
}
.ybt-authwrap .ybt-authfeat strong{
	display:block;
	font-size:14px;
	color:#fff;
}
.ybt-authwrap .ybt-authfeat small{
	color:rgba(255,255,255,.75);
	font-size:12.5px;
}
.ybt-authwrap .ybt-authbrand .ybt-authbarcode{
	height:34px;
	background:repeating-linear-gradient(90deg, rgba(212,175,55,.9) 0 2px, transparent 2px 4px, rgba(212,175,55,.9) 4px 7px, transparent 7px 9px, rgba(212,175,55,.9) 9px 10px, transparent 10px 15px);
	border-radius:2px;
	max-width:240px;
	opacity:.9;
}
.ybt-authwrap .ybt-authstamp{
	position:absolute;
	top:18px;right:18px;
	font-family:var(--mono);
	font-size:9px;font-weight:600;
	letter-spacing:.14em;text-transform:uppercase;
	color:var(--gold);
	border:2px solid var(--gold);
	border-radius:4px;
	padding:5px 9px;
	transform:rotate(3deg);
	background:rgba(15,31,23,.18);
}

/* ── form side ────────────────────────────────────────────── */
.ybt-authwrap .ybt-authform{
	padding:clamp(26px,4vw,40px);
	display:flex;
	flex-direction:column;
}
/* base look comes from the shared sheet; only the spacing differs here */
.ybt-authwrap .ybt-authform .ybt-eyebrow{
	margin:0 0 8px;
}
.ybt-authwrap .ybt-authform h2{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.3rem,2.4vw,1.7rem);
	margin:0 0 6px;
	color:var(--ink);
}
.ybt-authwrap .ybt-authform .ybt-sub{
	margin:0 0 22px;
	color:#3d4a42;
	font-size:14px;
}
.ybt-authwrap .ybt-formlabel{
	display:block;
	font-size:10.5px;
	margin:0 0 6px;
}
.ybt-authwrap .ybt-formlabel a{
	float:right;
	font-size:10.5px;
	color:var(--emerald-deep);
	text-decoration:underline;
	text-transform:none;
	letter-spacing:0;
}
.ybt-authwrap .ybt-input{
	width:100%;
	padding:12px 14px;
	margin-bottom:16px;
}
.ybt-authwrap .ybt-input::placeholder{ color:#9aa39d; }
.ybt-authwrap .ybt-checkrow{
	display:flex;
	align-items:center;
	gap:9px;
	margin:2px 0 18px;
	font-size:14px;
	color:#3d4a42;
}
.ybt-authwrap .ybt-checkrow input{
	width:17px;height:17px;
	accent-color:var(--emerald-deep);
}
.ybt-authwrap .ybt-btn{ width:100%; padding:14px 22px; font-size:15px; }
.ybt-authwrap .ybt-swap{
	text-align:center;
	margin-top:18px;
	padding-top:16px;
	border-top:2px dashed var(--kraft-line);
	font-size:14px;
	color:#3d4a42;
}
.ybt-authwrap .ybt-swap a{
	color:var(--emerald-deep);
	font-weight:700;
	text-decoration:none;
}
.ybt-authwrap .ybt-swap a:hover{ text-decoration:underline; }
.ybt-authwrap .ybt-guest{
	display:block;
	text-align:center;
	margin-top:10px;
	font-family:var(--mono);
	font-size:12px;
	color:#5c5543;
	text-decoration:underline;
}

/* ── alerts (display default set here; colors come from the shared sheet) ── */
.ybt-authwrap .alert{ display:none; margin:0 0 18px; }

/* ── password strength meter (register page) ──────────────── */
.ybt-authwrap .progress{
	height:6px;
	background:var(--kraft);
	border:1px solid var(--kraft-line);
	border-radius:4px;
	overflow:hidden;
	margin-top:2px;
}
.ybt-authwrap .progress-bar{
	height:100%;
	width:0;
	background:var(--kraft-deep);
	transition:width .25s ease, background-color .25s ease;
}
.ybt-authwrap .progress-bar.bg-danger{ background:#c0392b; }
.ybt-authwrap .progress-bar.bg-warning{ background:var(--gold); }
.ybt-authwrap .progress-bar.bg-success{ background:var(--emerald-deep); }
.ybt-authwrap #password-feedback{
	display:block;
	font-family:var(--mono);
	font-size:11.5px;
	color:#5c5543;
	margin:5px 0 14px;
}

/* ── solo-card footer barcode (reset page) ────────────────── */
.ybt-authwrap .ybt-authfoot{
	margin-top:22px;
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:14px;
}
.ybt-authwrap .ybt-authfoot .ybt-authbarcode{
	height:28px;flex:1;max-width:none;
	background:repeating-linear-gradient(90deg, var(--kraft-deep) 0 2px, transparent 2px 4px, var(--kraft-deep) 4px 7px, transparent 7px 9px, var(--kraft-deep) 9px 10px, transparent 10px 15px);
	opacity:.7;
}
.ybt-authwrap .ybt-authfoot span{
	font-family:var(--mono);
	font-size:10px;
	letter-spacing:.14em;
	text-transform:uppercase;
	color:#5c5543;
	white-space:nowrap;
}

/* ── responsive ───────────────────────────────────────────── */
@media (max-width:850px){
	.ybt-authwrap .ybt-authcard{ grid-template-columns:1fr; }
	.ybt-authwrap .ybt-authbrand{ padding:24px 22px; gap:16px; }
	.ybt-authwrap .ybt-authbrand h1{ font-size:1.3rem; }
	.ybt-authwrap .ybt-authfeats{ display:none; }
}
</style>
