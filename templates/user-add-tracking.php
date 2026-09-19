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
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);
?>
<style>
:root {
    --ybt-green: #10b981;
    --ybt-green-hover: #059669;
    --ybt-green-light: #ecfdf5;
    --ybt-gold: #d4af37;
    --ybt-gold-hover: #b59021;
}

.ybt-tabs .nav-link {
    color: #64748b;
    font-weight: 700;
    transition: all 0.3s ease;
    border: 1.5px solid transparent;
}

.ybt-tabs .nav-link.active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3) !important;
}

.ybt-tabs .nav-link:hover:not(.active) {
    color: #10b981;
    background: #ecfdf5;
}

.tracking-item-row {
    background: #ffffff;
    border: 1.5px solid #e2e8f0 !important;
    border-top: 3.5px solid #d4af37 !important;
    border-radius: 14px !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}

.text-brand-green {
    color: #10b981 !important;
}

.btn-brand-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    color: #ffffff !important;
    border: none !important;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3) !important;
}
.btn-brand-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(16, 185, 129, 0.4) !important;
}

.btn-brand-success {
    background: linear-gradient(135deg, #d4af37 0%, #b59021 100%) !important;
    color: #1e293b !important;
    font-weight: 700 !important;
    border: none !important;
}
.btn-brand-success:hover {
    color: #ffffff !important;
}
.link-brand {
    color: #10b981;
    text-decoration: none;
}
.link-brand:hover {
    color: #059669;
    text-decoration: underline;
}
.alert-brand-info {
    background: #ecfdf5;
    border-left: 4px solid #10b981;
    color: #047857;
}
</style>

<div class="ybt-dashboard-wrapper animate-up">
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h2 class="mb-0" style="color: #1e293b; font-weight: 800;">
            <i class="fas fa-plus-circle me-2" style="color: #10b981;"></i>Register Parcels
        </h2>
    </div>

    <div class="glass-card mb-5">
        <div class="info-card-header bg-white border-bottom p-3">
            <span class="fw-bold text-muted"><i class="fas fa-magic me-2" style="color: #d4af37;"></i>Manage Your Tracking</span>
        </div>
        <div class="info-card-body p-0">

<!-- Tab Navigation -->
<ul class="nav nav-pills nav-fill bg-light p-3 mb-0 ybt-tabs" id="trackingTabs" role="tablist">
<li class="nav-item" role="presentation">
<button class="nav-link active rounded-pill py-3" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-pane" type="button" role="tab">
<i class="fas fa-plus-circle me-2"></i>ADD NEW
</button>
</li>
<li class="nav-item" role="presentation">
<button class="nav-link rounded-pill py-3" id="claim-tab" data-bs-toggle="tab" data-bs-target="#claim-pane" type="button" role="tab">
<i class="fas fa-search me-2"></i>CLAIM RECEIVED
</button>
</li>
</ul>

<div class="tab-content p-4 p-md-5" id="trackingTabsContent">
<!-- Add New Pane -->
<div class="tab-pane fade show active" id="add-pane" role="tabpanel">
<div class="row justify-content-center">
<div class="col-12 col-lg-10 col-xl-8">
<form id="add-tracking-form">
<?php wp_nonce_field('ybt_user_add_tracking', 'tracking_nonce'); ?>
<input type="hidden" name="action" value="ybt_user_add_tracking">
<div id="tracking-items-container">
<!-- Initial Item -->
<div class="tracking-item-row mb-4 p-4 rounded-3 position-relative">
<div class="d-flex justify-content-between align-items-center mb-3">
<h6 class="text-brand-green fw-bold mb-0"><i class="fas fa-box me-2" style="color: #d4af37;"></i>ITEM #1</h6>
</div>
<div class="row g-3">
<div class="col-md-4">
<label class="form-label fw-semibold text-secondary small">Tracking Number</label>
<div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
<span class="input-group-text bg-light border-end-0"><i class="fas fa-hashtag text-muted"></i></span>
<input type="text" class="form-control form-control-premium border-start-0" name="tracking_numbers[]" required
       placeholder="e.g. 1Z999..." autocomplete="off" maxlength="50">
</div>
</div>
<div class="col-md-5">
<label class="form-label fw-semibold text-secondary small">Description</label>
<div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
<span class="input-group-text bg-light border-end-0"><i class="fas fa-tag text-muted"></i></span>
<input type="text" class="form-control form-control-premium border-start-0" name="descriptions[]" required
       placeholder="e.g. Shoes, Clothes..." autocomplete="off" maxlength="200">
</div>
</div>
<div class="col-md-3">
<label class="form-label fw-semibold text-secondary small">Item Count</label>
<div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
<span class="input-group-text bg-light border-end-0"><i class="fas fa-boxes text-muted"></i></span>
<input type="number" class="form-control form-control-premium border-start-0" name="item_counts[]" required
       placeholder="Qty" min="1" max="9999" value="1">
</div>
</div>
</div>
</div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
<button type="button" id="add-more-btn" class="btn btn-outline-success rounded-pill fw-bold" style="border-color: #10b981; color: #10b981;">
<i class="fas fa-plus me-1"></i> Add Another Item
</button>
<div class="text-muted small">
<i class="fas fa-info-circle me-1" style="color: #d4af37;"></i> Press '+' to add more rows
</div>
</div>

<div class="d-grid">
<button type="submit" class="btn btn-brand-primary btn-lg rounded-pill shadow-lg fw-bold" id="submit-btn">
<i class="fas fa-paper-plane me-2"></i>Register All Parcels
</button>
</div>
</form>
</div>
</div>
</div>

<!-- Claim Pane -->
<div class="tab-pane fade" id="claim-pane" role="tabpanel">
<div class="row justify-content-center">
<div class="col-12 col-lg-8 col-xl-6">
<div class="alert alert-brand-info mb-4">
<i class="fas fa-info-circle me-2"></i> Use this if your tracking shows <strong>Delivered</strong> but it's not in your list yet.
</div>
<form id="claim-tracking-form">
<?php wp_nonce_field('ybt_claim_parcel', 'claim_nonce'); ?>
<input type="hidden" name="action" value="ybt_claim_parcel">
<div class="mb-4">
<label class="form-label fw-semibold text-secondary small">Received Tracking Number</label>
<div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
<span class="input-group-text bg-light border-end-0"><i class="fas fa-barcode text-muted"></i></span>
<input type="text" class="form-control form-control-premium border-start-0" id="claim_tracking_number" name="tracking_number"
       required placeholder="Paste tracking number here" maxlength="50">
</div>
</div>
<div class="d-grid mb-3">
<button type="submit" class="btn btn-brand-success btn-lg rounded-pill" id="claim-btn">
<i class="fas fa-search me-2"></i>Search & Claim
</button>
</div>
<div class="text-center">
<a href="<?php echo esc_url(home_url('/anonymous/')); ?>" class="link-brand fw-bold">
<i class="fas fa-list me-1"></i> Browse all unclaimed parcels
</a>
</div>
</form>
</div>
</div>
        </div> <!-- .info-card-body -->
    </div> <!-- .glass-card -->

    <div class="text-start mt-4">
        <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="text-decoration-none text-muted fw-bold">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
</div> <!-- .container -->
</div> <!-- .ybt-dashboard-wrapper -->

<script>
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
        <div class="tracking-item-row mb-4 p-4 rounded-3 position-relative fade-in">
        <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-brand-green fw-bold mb-0"><i class="fas fa-box me-2" style="color: #d4af37;"></i>ITEM #${safeCount}</h6>
        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle remove-row-btn" title="Remove Item">
        <i class="fas fa-times"></i>
        </button>
        </div>
        <div class="row g-3">
        <div class="col-md-4">
        <label class="form-label fw-semibold text-secondary small">Tracking Number</label>
        <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
        <span class="input-group-text bg-light border-end-0"><i class="fas fa-hashtag text-muted"></i></span>
        <input type="text" class="form-control form-control-premium border-start-0" name="tracking_numbers[]" required placeholder="e.g. 1Z999..." autocomplete="off" maxlength="50">
        </div>
        </div>
        <div class="col-md-5">
        <label class="form-label fw-semibold text-secondary small">Description</label>
        <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
        <span class="input-group-text bg-light border-end-0"><i class="fas fa-tag text-muted"></i></span>
        <input type="text" class="form-control form-control-premium border-start-0" name="descriptions[]" required placeholder="e.g. Shoes, Clothes..." autocomplete="off" maxlength="200">
        </div>
        </div>
        <div class="col-md-3">
        <label class="form-label fw-semibold text-secondary small">Item Count</label>
        <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
        <span class="input-group-text bg-light border-end-0"><i class="fas fa-boxes text-muted"></i></span>
        <input type="number" class="form-control form-control-premium border-start-0" name="item_counts[]" required placeholder="Qty" min="1" max="9999" value="1">
        </div>
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
