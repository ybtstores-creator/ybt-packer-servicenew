<?php
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) {
    echo '<div class="alert alert-warning">Please login to add tracking numbers.</div>';
    return;
}
global $wpdb;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

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
   ADD TRACKING — "The intake desk" (page-specific layer)
   Shared tokens & atoms come from assets/css/ybt-consignment.css
   (.ybd-page scope). JS contracts untouched:
   #add-tracking-form (tracking_numbers[]/descriptions[]/item_counts[],
   tracking_nonce), #claim-tracking-form (claim_tracking_number,
   claim_nonce), #add-more-btn, .tracking-item-row, .remove-row-btn,
   #submit-btn, #claim-btn.
   ================================================================ */
.ybd-page .ybt-tabs{
	list-style:none;
	margin:0 0 18px;
	padding:4px;
	display:flex;
	gap:6px;
	background:var(--kraft);
	border:1.5px solid var(--kraft-line);
	border-radius:10px;
}
.ybd-page .ybt-tabs button{
	flex:1;
	display:inline-flex;
	align-items:center;
	justify-content:center;
	gap:8px;
	font-family:var(--mono);
	font-size:12.5px;
	font-weight:600;
	letter-spacing:.08em;
	text-transform:uppercase;
	color:#5c5543;
	background:transparent;
	border:1.5px solid transparent;
	border-radius:7px;
	padding:11px 14px;
	cursor:pointer;
	transition:color .15s ease, background-color .15s ease, border-color .15s ease;
}
.ybd-page .ybt-tabs button:hover{ color:var(--emerald-deep); background:var(--emerald-tint); }
.ybd-page .ybt-tabs button.active{
	color:var(--ink);
	background:var(--paper);
	border-color:var(--emerald);
	box-shadow:3px 3px 0 rgba(16,185,129,.18);
}
.ybd-page .tracking-item-row{
	background:var(--paper);
	border:1.5px solid var(--ink);
	border-radius:12px;
	box-shadow:6px 6px 0 rgba(15,31,23,.12);
	padding:18px;
	margin-bottom:16px;
	animation:ybt-row-in .3s ease-out both;
}
@keyframes ybt-row-in{
	from{ opacity:0; transform:translateY(6px); }
	to{ opacity:1; transform:none; }
}
.ybd-page .tracking-item-row .ybt-rowhead{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:10px;
	margin-bottom:12px;
}
.ybd-page .tracking-item-row .ybt-rowhead h6{
	margin:0;
	font-family:var(--mono);
	font-size:11px;
	font-weight:600;
	letter-spacing:.16em;
	text-transform:uppercase;
	color:var(--emerald-deep);
	display:flex;
	align-items:center;
	gap:8px;
}
.ybd-page .tracking-item-row .ybt-rowhead h6 i{ color:var(--gold-deep); }
.ybd-page .remove-row-btn{
	width:30px;height:30px;
	display:inline-flex;
	align-items:center;
	justify-content:center;
	background:none;
	border:1.5px solid var(--kraft-deep);
	border-radius:8px;
	color:#9c2020;
	cursor:pointer;
	transition:border-color .15s ease, background-color .15s ease;
}
.ybd-page .remove-row-btn:hover{ border-color:#9c2020; background:#fdeeee; }
.ybd-page .ybt-fieldrow{
	display:grid;
	grid-template-columns:2fr 3fr 1.2fr;
	gap:12px;
}
.ybd-page .ybt-intake-actions{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:12px;
	flex-wrap:wrap;
	margin:18px 0;
}
.ybd-page .ybt-hint{
	font-family:var(--mono);
	font-size:11px;
	letter-spacing:.06em;
	color:#8b968f;
	display:inline-flex;
	align-items:center;
	gap:6px;
}
.ybd-page .ybt-hint i{ color:var(--gold-deep); }
.ybd-page .ybt-submitrow{
	margin-top:6px;
	padding-top:18px;
	border-top:2px dashed var(--kraft-line);
}
.ybd-page .ybt-submitrow .ybd-btn{ width:100%; padding:14px 22px; font-size:15px; }
.ybd-page .ybt-backlink{
	display:inline-flex;
	align-items:center;
	gap:8px;
	margin-top:18px;
	font-family:var(--mono);
	font-size:12.5px;
	font-weight:600;
	color:#5c5543;
	text-decoration:none;
}
.ybd-page .ybt-backlink:hover{ color:var(--emerald-deep); }
/* pane visibility: works without Bootstrap CSS/JS (JS below re-implements toggling) */
.ybd-page .tab-pane{ display:none; }
.ybd-page .tab-pane.active{ display:block; }
@media (max-width:820px){
	.ybd-page .ybt-fieldrow{ grid-template-columns:1fr; }
}
</style>

<div class="ybd-page">

	<!-- page head -->
	<div class="ybd-pagehead">
		<div>
			<p class="ybd-eyebrow">Stage 1 of 5 &middot; Intake desk</p>
			<h2>Register parcels</h2>
			<p class="ybd-sub">Add the tracking numbers from your online orders. We take it from the moment the courier delivers.</p>
		</div>
		<span class="ybd-count">Intake open &middot; Mon&ndash;Sat</span>
	</div>

	<div class="ybd-panel">

		<!-- tab strip -->
		<div class="ybt-tabs" role="tablist">
			<button type="button" class="active" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-pane" role="tab" aria-selected="true">
				<i class="fas fa-plus-circle"></i> Add new
			</button>
			<button type="button" id="claim-tab" data-bs-toggle="tab" data-bs-target="#claim-pane" role="tab" aria-selected="false">
				<i class="fas fa-barcode"></i> Claim received
			</button>
		</div>

		<div class="tab-content" id="trackingTabsContent">

		<!-- add new pane -->
		<div class="tab-pane fade show active" id="add-pane" role="tabpanel">
		<form id="add-tracking-form">
		<?php wp_nonce_field('ybt_user_add_tracking', 'tracking_nonce'); ?>
		<input type="hidden" name="action" value="ybt_user_add_tracking">

		<div id="tracking-items-container">
			<!-- Initial item row -->
			<div class="tracking-item-row">
				<div class="ybt-rowhead">
					<h6><i class="fas fa-box"></i> Item #1</h6>
				</div>
				<div class="ybt-fieldrow">
					<div>
						<label class="ybd-formlabel" for="tn-1">Tracking number</label>
						<input type="text" class="ybd-input" id="tn-1" name="tracking_numbers[]" required placeholder="e.g. 1Z999..." autocomplete="off" maxlength="50">
					</div>
					<div>
						<label class="ybd-formlabel" for="desc-1">Description</label>
						<input type="text" class="ybd-input" id="desc-1" name="descriptions[]" required placeholder="e.g. Shoes, Clothes..." autocomplete="off" maxlength="200">
					</div>
					<div>
						<label class="ybd-formlabel" for="qty-1">Item count</label>
						<input type="number" class="ybd-input" id="qty-1" name="item_counts[]" required placeholder="Qty" min="1" max="9999" value="1">
					</div>
				</div>
			</div>
		</div>

		<div class="ybt-intake-actions">
			<button type="button" id="add-more-btn" class="ybd-btn ybd-btn-emerald">
				<i class="fas fa-plus"></i> Add another item
			</button>
			<span class="ybt-hint"><i class="fas fa-keyboard"></i> Press '+' to add more rows</span>
		</div>

		<div class="ybt-submitrow">
			<button type="submit" class="ybd-btn ybd-btn-gold" id="submit-btn">
				<i class="fas fa-paper-plane"></i> Register all parcels
			</button>
		</div>
		</form>
		</div>

		<!-- claim pane -->
		<div class="tab-pane fade" id="claim-pane" role="tabpanel">
		<div class="ybd-note" style="margin-top:0;">
			<i class="fas fa-circle-info"></i>
			<span><strong>Already delivered?</strong> If your tracking shows delivered but the parcel isn't in your list yet, claim it here with the tracking number.</span>
		</div>

		<form id="claim-tracking-form" style="margin-top:18px;">
		<?php wp_nonce_field('ybt_claim_parcel', 'claim_nonce'); ?>
		<input type="hidden" name="action" value="ybt_claim_parcel">

		<div style="max-width:520px;">
			<label class="ybd-formlabel" for="claim_tracking_number">Received tracking number</label>
			<input type="text" class="ybd-input" id="claim_tracking_number" name="tracking_number" required placeholder="Paste tracking number here" maxlength="50">

			<button type="submit" class="ybd-btn ybd-btn-gold" id="claim-btn" style="width:100%;margin-top:16px;">
				<i class="fas fa-magnifying-glass"></i> Search &amp; claim
			</button>

			<p style="text-align:center;margin:14px 0 0;">
				<a href="<?php echo esc_url(home_url('/anonymous/')); ?>" class="ybd-ghostlink">
					<i class="fas fa-list"></i> Browse all unclaimed parcels
				</a>
			</p>
		</div>
		</form>
		</div>

		</div><!-- /.tab-content -->
	</div><!-- /.ybd-panel -->

	<a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="ybt-backlink">
		<i class="fas fa-arrow-left"></i> Back to dashboard
	</a>
</div><!-- /.ybd-page -->

<script>
(function(){
	// self-contained tab switching (no Bootstrap dependency)
	document.addEventListener('DOMContentLoaded', function(){
		var tabs = document.querySelectorAll('.ybt-tabs [data-bs-toggle="tab"]');
		tabs.forEach(function(tab){
			tab.addEventListener('click', function(){
				tabs.forEach(function(t){
					t.classList.remove('active');
					t.setAttribute('aria-selected', 'false');
				});
				tab.classList.add('active');
				tab.setAttribute('aria-selected', 'true');
				document.querySelectorAll('.ybd-page .tab-pane').forEach(function(p){ p.classList.remove('active', 'show', 'in'); });
				var pane = document.querySelector(tab.getAttribute('data-bs-target'));
				if (pane) { pane.classList.add('active'); }
			});
		});
	});
})();
jQuery(document).ready(function($) {
    const addNonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.add_tracking_nonce) ? ybt_ajax.add_tracking_nonce : '<?php echo wp_create_nonce('ybt_user_add_tracking'); ?>';
    const claimNonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.claim_nonce) ? ybt_ajax.claim_nonce : '<?php echo wp_create_nonce('ybt_claim_parcel'); ?>';
    const ajaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';

    // Add New Handler
    $('#add-tracking-form').submit(function(e) {
        e.preventDefault();
        const $btn = $(this).find('#submit-btn');
        const originalText = $btn.html();

        const trackingNumbers = $('input[name="tracking_numbers[]"]').map(function() {
            return $(this).val().trim().toUpperCase();
        }).get();

        const invalid = trackingNumbers.find(num => !/^[A-Z0-9 \-\.]{5,60}$/.test(num));
        if (invalid) {
            alert('Invalid tracking number format: "' + invalid + '". Please use alphanumeric characters.');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');

        const formData = new FormData(this);
        formData.append('nonce', addNonce);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if(res.success) {
                    const redirectUrl = res.data.includes('Claimed')
                        ? '<?php echo esc_url(home_url('/received/')); ?>'
                        : '<?php echo esc_url(home_url('/not-received/')); ?>';

                    const toast = $('<div class="position-fixed top-0 end-0 p-3" style="z-index: 9999"><div class="toast show align-items-center text-white bg-success border-0"><div class="d-flex"><div class="toast-body">' + res.data + '</div></div></div></div>');
                    $('body').append(toast);
                    setTimeout(() => { window.location.href = redirectUrl; }, 2000);
                } else {
                    alert('Server Error: ' + $('<div>').text(res.data).html());
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Connection Error: Unable to reach the server (Status: ' + xhr.status + '). Please check your internet or retry.');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Dynamic Fields Logic
    let itemCount = 1;
    $('#add-more-btn').click(function() {
        itemCount++;
        const safeCount = $('<div>').text(itemCount).html();

        const newRow = `
        <div class="tracking-item-row">
        <div class="ybt-rowhead">
        <h6><i class="fas fa-box"></i> Item #${safeCount}</h6>
        <button type="button" class="remove-row-btn" title="Remove Item" aria-label="Remove Item">
        <i class="fas fa-times"></i>
        </button>
        </div>
        <div class="ybt-fieldrow">
        <div>
        <label class="ybd-formlabel">Tracking number</label>
        <input type="text" class="ybd-input" name="tracking_numbers[]" required placeholder="e.g. 1Z999..." autocomplete="off" maxlength="50">
        </div>
        <div>
        <label class="ybd-formlabel">Description</label>
        <input type="text" class="ybd-input" name="descriptions[]" required placeholder="e.g. Shoes, Clothes..." autocomplete="off" maxlength="200">
        </div>
        <div>
        <label class="ybd-formlabel">Item count</label>
        <input type="number" class="ybd-input" name="item_counts[]" required placeholder="Qty" min="1" max="9999" value="1">
        </div>
        </div>
        </div>
        `;
        $(newRow).hide().appendTo('#tracking-items-container').slideDown(300);
    });

    // Delegated event for remove button
    $(document).on('click', '.remove-row-btn', function() {
        if ($('.tracking-item-row').length > 1) {
            $(this).closest('.tracking-item-row').slideUp(300, function() {
                $(this).remove();
            });
        } else {
            alert('At least one item is required.');
        }
    });

    // Claim Handler
    $('#claim-tracking-form').submit(function(e) {
        e.preventDefault();
        const $btn = $(this).find('#claim-btn');
        const originalText = $btn.text();
        const trackingNum = $('#claim_tracking_number').val().trim().toUpperCase();

        if (!/^[A-Z0-9 \-\.]{5,60}$/.test(trackingNum)) {
            alert('Invalid tracking number format. Please check for typos.');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Searching...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'ybt_claim_parcel',
                nonce: claimNonce,
                tracking_number: trackingNum
            },
            success: function(res) {
                if(res.success) {
                    const toast = $('<div class="position-fixed top-0 end-0 p-3" style="z-index: 9999"><div class="toast show align-items-center text-white bg-success border-0"><div class="d-flex"><div class="toast-body">' + res.data + '</div></div></div></div>');
                    $('body').append(toast);
                    setTimeout(() => { window.location.href = '<?php echo esc_url(home_url('/received/')); ?>'; }, 2000);
                } else {
                    alert('Server Error: ' + $('<div>').text(res.data).html());
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Connection Error: Unable to reach the server (Status: ' + xhr.status + '). Please check your internet or retry.');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
