<?php
if (!defined('ABSPATH')) exit;

// NOTE: Authentication redirect is handled by YBT_Core::handle_authentication_redirects()
// via 'template_redirect' hook at priority 5. Do NOT add wp_redirect() here.

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
global $wpdb;

// Load Modern Navbar Component
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);

// Get user's pending items for packing request
$pending_items = $wpdb->get_results($wpdb->prepare(
    "SELECT id, tracking_number, description, item_count
    FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s)
    AND status = 'received'
    ORDER BY date_received DESC",
    $user_id,
    $current_user->user_login
));
?>
<style>
/* ================================================================
   PACKING REQUEST — "The consignment counter" (page-specific layer)
   Shared tokens & atoms come from assets/css/ybt-consignment.css
   (.ybd-page scope). JS contract (assets/packing-request.js) is
   untouched: .item-checkbox, #select-all-btn, #deselect-all-btn,
   #selected-count, #packing-request-form, #submit-packing-btn,
   #packing-request-alert, input names, ybt_nonce_field.
   ================================================================ */
.ybt-packingform .ybd-packinggrid{
	display:grid;
	grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);
	gap:18px;
	align-items:start;
}
.ybt-packingform .ybd-itemrow{
	display:flex;
	align-items:center;
	gap:14px;
	padding:13px 16px;
	background:#fbf8f1;
	border:1px solid var(--kraft-line);
	border-radius:10px;
	margin-bottom:10px;
	transition:border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
}
.ybt-packingform .ybd-itemrow:hover{
	border-color:var(--emerald);
	background:var(--emerald-tint);
}
.ybt-packingform .ybd-itemrow .ybd-check{ flex-shrink:0; }
.ybt-packingform .ybd-itemrow .ybd-trackno{
	font-size:14px;
	color:var(--emerald-deep);
}
.ybt-packingform .ybd-itemrow .ybd-desc{
	font-size:13px;
	margin-top:2px;
}
.ybt-packingform .ybd-itemrow .ybd-pcs{ margin-left:auto; flex-shrink:0; }
.ybt-packingform .ybd-selbar{
	display:flex;
	align-items:center;
	gap:10px;
	flex-wrap:wrap;
	margin-top:14px;
}
.ybt-packingform .ybd-selcount{
	font-family:var(--mono);
	font-size:12px;
	font-weight:600;
	color:var(--emerald-deep);
	margin-left:auto;
}
.ybt-packingform .ybd-formgrid{
	display:grid;
	grid-template-columns:repeat(2,minmax(0,1fr));
	gap:14px 16px;
}
.ybt-packingform .ybd-formgrid .ybd-span2{ grid-column:1 / -1; }
.ybt-packingform .ybd-actions{
	margin-top:20px;
	padding-top:18px;
	border-top:2px dashed var(--kraft-line);
	display:flex;
	justify-content:flex-end;
}
.ybt-packingform .ybd-btn[disabled]{
	opacity:.55;
	cursor:not-allowed;
	transform:none;
	box-shadow:none;
}
/* alerts the packing-request.js injects into #packing-request-alert */
.ybt-packingform .alert{
	border-radius:8px;
	padding:11px 14px;
	margin:0 0 14px;
	font-size:14px;
	border:1.5px solid transparent;
	display:block;
}
.ybt-packingform .alert.alert-success{
	background:var(--emerald-tint);
	color:var(--emerald-deep);
	border:1px solid #b5e8d2;
}
.ybt-packingform .alert.alert-danger{
	background:#fdeeee;
	color:#9c2020;
	border:1px solid #f3c9c9;
}
@media (max-width:900px){
	.ybt-packingform .ybd-packinggrid{ grid-template-columns:1fr; }
	.ybt-packingform .ybd-formgrid{ grid-template-columns:1fr; }
	.ybt-packingform .ybd-actions{ justify-content:stretch; }
	.ybt-packingform .ybd-actions .ybd-btn{ width:100%; }
}
</style>

<div class="ybd-page ybt-packingform">

	<!-- ── page head ─────────────────────────────────────────── -->
	<div class="ybd-pagehead">
		<div>
			<p class="ybd-eyebrow">Stage 3 of 5 &middot; Consignment counter</p>
			<h2>Request packing</h2>
			<p class="ybd-sub">Pick the parcels to pack together, then tell us where the sealed box is going.</p>
		</div>
		<span class="ybd-count ybd-count-gold"><?php echo count($pending_items); ?> awaiting packing</span>
	</div>

	<div id="packing-request-alert" aria-live="polite"></div>

	<div class="ybd-packinggrid">

		<!-- ── item selection ──────────────────────────────────── -->
		<div class="ybd-panel">
			<div class="ybd-panelhead">
				<span class="ybd-hint"><i class="fas fa-boxes-stacked"></i> Select items to pack together</span>
			</div>
			<div class="ybd-panelbody">
				<?php if ($pending_items): ?>
				<div id="items-container">
					<?php foreach ($pending_items as $item): ?>
					<label class="ybd-itemrow" for="item-<?php echo esc_attr($item->id); ?>">
						<input type="checkbox" class="ybd-check item-checkbox"
						       id="item-<?php echo esc_attr($item->id); ?>"
						       value="<?php echo esc_attr($item->id); ?>"
						       data-tracking="<?php echo esc_attr($item->tracking_number); ?>">
						<span>
							<span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span>
							<span class="ybd-desc"><?php echo esc_html($item->description ?: 'No description'); ?></span>
						</span>
						<span class="ybd-pcs"><?php echo esc_html($item->item_count); ?> pcs</span>
					</label>
					<?php endforeach; ?>
				</div>

				<div class="ybd-selbar">
					<button type="button" id="select-all-btn" class="ybd-btn ybd-btn-emerald">
						<i class="fas fa-check-double"></i> Select all
					</button>
					<button type="button" id="deselect-all-btn" class="ybd-btn ybd-btn-emerald">
						<i class="fas fa-square"></i> Clear
					</button>
					<span id="selected-count" class="ybd-selcount">0 items selected</span>
				</div>
				<?php else: ?>
				<div class="ybd-empty">
					<div class="ybd-emptyicon"><i class="fas fa-box-open"></i></div>
					<h4>Nothing at the counter</h4>
					<p>You have no received parcels ready for packing. Register a tracking number first &mdash; items appear here once the warehouse scans them in.</p>
					<a class="ybd-btn ybd-btn-emerald" href="<?php echo esc_url(home_url('/add-tracking/')); ?>">Register tracking numbers</a>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- ── forwarding details ──────────────────────────────── -->
		<div class="ybd-panel ybd-panel-gold">
			<div class="ybd-panelhead">
				<span class="ybd-hint"><i class="fas fa-truck-fast"></i> Forwarding address &amp; instructions</span>
			</div>
			<div class="ybd-panelbody">
				<div class="ybd-note" style="margin-top:0;">
					<i class="fas fa-circle-info"></i>
					<span><strong>How it works.</strong> We pack your items, photograph every step, and log weight and cost before dispatch &mdash; then hand the sealed box to your carrier.</span>
				</div>

				<form id="packing-request-form" style="margin-top:18px;">
					<?php wp_nonce_field('ybt_nonce', 'ybt_nonce_field'); ?>
					<div class="ybd-formgrid">
						<div class="ybd-span2">
							<label class="ybd-formlabel" for="pr-shipping-company">Logistics company / receiver name</label>
							<input type="text" class="ybd-input" id="pr-shipping-company" name="shipping_company" required placeholder="e.g. DHL Express / Agent Name">
						</div>
						<div>
							<label class="ybd-formlabel" for="pr-contact-phone">Contact phone number</label>
							<input type="text" class="ybd-input" id="pr-contact-phone" name="contact_phone" required placeholder="Receiver phone number">
						</div>
						<div>
							<label class="ybd-formlabel" for="pr-shipping-mark">Shipping mark / marks &amp; numbers</label>
							<input type="text" class="ybd-input" id="pr-shipping-mark" name="shipping_mark" placeholder="e.g. YBT-MARK-1234">
						</div>
						<div class="ybd-span2">
							<label class="ybd-formlabel" for="pr-shipping-address">Delivery / warehouse address</label>
							<textarea class="ybd-textarea" id="pr-shipping-address" name="shipping_address" rows="3" required placeholder="Full delivery address for final shipment"></textarea>
						</div>
						<div class="ybd-span2">
							<label class="ybd-formlabel" for="pr-custom-notes">Special packing instructions</label>
							<input type="text" class="ybd-input" id="pr-custom-notes" name="custom_notes" placeholder="e.g. Double bubble wrap, remove shoeboxes">
						</div>
					</div>

					<div class="ybd-actions">
						<button type="submit" class="ybd-btn ybd-btn-gold" id="submit-packing-btn" <?php echo !$pending_items ? 'disabled' : ''; ?>>
							<i class="fas fa-paper-plane"></i> Submit packing request
						</button>
					</div>
				</form>
			</div>
		</div>

	</div>
</div>
