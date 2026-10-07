<?php if (!defined('ABSPATH')) exit;
/**
 * Status-page-specific rules for the user status pages
 * (received / packed / completed / not-received / packing-in-progress).
 * Shared tokens & atoms live in assets/css/ybt-consignment.css (enqueued);
 * this block only adds what is unique to the status pages.
 */
?>
<style>
/* pagehead / count / panelhead atoms live in the shared sheet
   (ybt-consignment.css) — three page families use them now */

/* ── table details specific to status rows ────────────────── */
.ybd-page table.ybd-manifest th{ padding:12px 14px; }
.ybd-page table.ybd-manifest td{ padding:13px 14px; }
.ybd-page .ybd-date strong{ display:block; font-weight:600; color:var(--ink); }
.ybd-page .ybd-date small{ font-size:11.5px; }

/* ── photo thumbs ─────────────────────────────────────────── */
.ybd-page .ybd-thumbs{ display:flex; gap:8px; align-items:center; }
.ybd-page .ybd-thumb{
	width:46px;height:46px;
	border-radius:8px;
	border:1.5px solid var(--kraft-line);
	object-fit:cover;
	cursor:pointer;
	transition:transform .15s ease, border-color .15s ease;
	padding:0;
	background:#fbf8f1;
}
.ybd-page .ybd-thumb:hover{ transform:scale(1.08); border-color:var(--emerald); }
.ybd-page .ybd-morethumb{
	width:46px;height:46px;
	border-radius:8px;
	border:1.5px dashed var(--kraft-deep);
	background:var(--kraft);
	color:var(--emerald-deep);
	font-family:var(--mono);
	font-size:12px;font-weight:600;
	cursor:pointer;
}
.ybd-page .ybd-morethumb:hover{ border-color:var(--emerald); }
.ybd-page .ybd-none{ color:#8b968f; font-size:13px; }

/* ── pagination ───────────────────────────────────────────── */
.ybd-page .ybd-pagenav{
	display:flex;
	justify-content:center;
	margin-top:22px;
}
.ybd-page .ybd-pagenav ul.page-numbers{
	list-style:none;
	display:flex;
	gap:6px;
	margin:0;padding:0;
}
.ybd-page .ybd-pagenav .page-numbers{
	display:inline-flex;
	align-items:center;justify-content:center;
	min-width:36px;height:36px;
	padding:0 10px;
	font-family:var(--mono);
	font-size:13px;font-weight:600;
	color:var(--emerald-deep);
	background:var(--paper);
	border:1.5px solid var(--kraft-deep);
	border-radius:8px;
	text-decoration:none;
}
.ybd-page .ybd-pagenav .page-numbers.current{
	background:var(--emerald-deep);
	color:#fff;
	border-color:var(--emerald-deep);
}
.ybd-page .ybd-pagenav .page-numbers:hover:not(.current):not(.dots){
	border-color:var(--emerald);
	background:var(--emerald-tint);
}
.ybd-page .ybd-pagenav span.page-numbers.dots{ border:none; background:none; }

/* ── floating selection badge (packing flow) ──────────────── */
.ybd-page .ybd-selbadge{
	font-family:var(--mono);
	font-size:13px;font-weight:600;
	color:var(--ink);
	background:var(--gold);
	border:1.5px solid var(--ink);
	border-radius:8px;
	padding:10px 16px;
	box-shadow:4px 4px 0 rgba(15,31,23,.3);
	display:flex;
	align-items:center;
	gap:10px;
}
.ybd-page .ybd-selbadge .ybd-selnum{
	background:var(--ink);
	color:#fff;
	border-radius:5px;
	padding:1px 8px;
}

/* info note + checkboxes come from the shared sheet (ybt-consignment.css) */

/* ── modals ── NOTE: modals render OUTSIDE .ybd-page, so these are NOT scoped under it ── */
.ybd-modal,
.ybd-modal *,
.ybd-modal *::before,
.ybd-modal *::after{ box-sizing:border-box; }
.ybd-modal{
	display:none;
	position:fixed;
	top:0;left:0;width:100%;height:100%;
	background:rgba(15,31,23,.75);
	z-index:9999;
	align-items:center;
	justify-content:center;
}
.ybd-modal > div{
	background:var(--paper,#ffffff);
	border:1.5px solid var(--ink,#0f1f17);
	border-radius:12px;
	box-shadow:6px 6px 0 rgba(15,31,23,.3);
	padding:24px;
	width:100%;
	max-width:580px;
	max-height:90vh;
	overflow-y:auto;
	position:relative;
	margin:0 1rem;
}
.ybd-modal.ybd-modal-wide > div{ max-width:800px; }
.ybd-modalhead{
	display:flex;
	justify-content:space-between;
	align-items:center;
	gap:10px;
	padding-bottom:12px;
	margin-bottom:16px;
	border-bottom:2px dashed var(--kraft-line,#d9ccb0);
}
.ybd-modalhead h5{
	margin:0;
	font-family:var(--mono,'IBM Plex Mono',monospace);
	font-size:12px;font-weight:600;
	letter-spacing:.16em;text-transform:uppercase;
	color:var(--emerald-deep,#047857);
}
.ybd-modalclose{
	background:none;
	border:1.5px solid var(--kraft-deep,#b9a67f);
	border-radius:8px;
	width:32px;height:32px;
	color:var(--ink,#0f1f17);
	cursor:pointer;
	font-size:14px;
}
.ybd-modalclose:hover{ border-color:var(--emerald,#10b981); background:var(--emerald-tint,#e7f6ef); }

/* buttons & pcs inside modals come from the shared sheet —
   tokens are declared on .ybd-modal itself in ybt-consignment.css */

/* packing-request form inside the modal */
/* form labels/inputs inside modals: base look from the shared sheet
   (tokens declared on .ybd-modal); only layout spacing is page-specific */
.ybd-formlabel{
	display:block;
	margin:14px 0 5px;
}
.ybd-input,
.ybd-textarea{
	width:100%;
	padding:11px 14px;
}
</style>
