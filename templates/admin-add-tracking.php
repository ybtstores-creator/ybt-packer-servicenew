<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin Add Tracking Template
 * Modernized with Glassmorphism and specialized for high-volume entry.
 */

if (!current_user_can('manage_options')) {
    wp_die(__('Insufficient permissions', 'ybt-packer'));
}

global $wpdb;

// Fetch all users for dropdown attribution
$all_wp_users = get_users(array('fields' => array('ID', 'user_login', 'display_name')));
foreach ($all_wp_users as $user) {
    $user->custom_id = get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: '';
}

// Enqueue Enhanced CSS
wp_enqueue_style('ybt-admin-dashboard-enhanced', YBT_PLUGIN_URL . 'assets/css/admin-dashboard-enhanced.css', array(), YBT_VERSION);

// Edit Mode Logic
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_item = null;
if ($edit_id) {
    $edit_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $edit_id));
}
$is_edit = ($edit_id && $edit_item);
?>

<div class="wrap ybt-admin-wrapper animate-up">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="wp-heading-inline" style="font-size: 2rem; color: #1e293b; font-weight: 800;">
            <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-plus-circle'; ?> text-primary me-2"></i>
            <?php echo $is_edit ? 'Edit Shipment Entry' : 'Register New Shipments'; ?>
        </h1>
        <?php if ($is_edit): ?>
            <a href="<?php echo admin_url('admin.php?page=ybt-add-tracking'); ?>" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-plus me-1"></i> Switch to New Entry
            </a>
        <?php else: ?>
            <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-4 py-2 border">
                Admin Entry Portal
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- SINGLE ENTRY/EDIT CARD -->
        <div class="col-12 col-xl-5">
            <div class="bg-white p-4 border rounded-4 shadow-sm h-100">
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                        <i class="fas <?php echo $is_edit ? 'fa-pen' : 'fa-barcode'; ?>"></i>
                    </div>
                    <h3 class="h5 mb-0 fw-bold"><?php echo $is_edit ? 'Edit Shipment Details' : 'Single Entry'; ?></h3>
                </div>

                <form id="admin-add-single-form" enctype="multipart/form-data">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="tracking_id" value="<?php echo esc_attr($edit_id); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase letter-spacing-1">Tracking Number</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-hashtag text-muted"></i></span>
                            <input type="text" name="tracking_number" required
                                   value="<?php echo $is_edit ? esc_attr($edit_item->tracking_number) : ''; ?>"
                                   placeholder="Paste or scan tracking number" class="form-control border-start-0 ps-0" autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase letter-spacing-1">Assign User (Optional)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" name="username" list="user-list-single"
                                   value="<?php echo $is_edit ? esc_attr($edit_item->username) : ''; ?>"
                                   placeholder="Type name or Custom ID" class="form-control border-start-0 ps-0">
                            <datalist id="user-list-single">
                                <?php foreach ($all_wp_users as $u): ?>
                                    <option value="<?php echo esc_attr($u->user_login); ?>"><?php echo esc_html($u->display_name . ($u->custom_id ? ' [' . $u->custom_id . ']' : '')); ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-text small opacity-75">Leave empty to register as Anonymous/Unclaimed.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted text-uppercase letter-spacing-1">Items Count</label>
                            <input type="number" name="item_count"
                                   value="<?php echo $is_edit ? esc_attr($edit_item->item_count ?: 1) : '1'; ?>"
                                   min="1" class="form-control form-control-lg">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted text-uppercase letter-spacing-1">Method</label>
                            <select name="shipping_method" class="form-select form-select-lg">
                                <option value="sea" <?php if($is_edit) selected($edit_item->shipping_method, 'sea'); ?>>Sea Freight</option>
                                <option value="air" <?php if($is_edit) selected($edit_item->shipping_method, 'air'); ?>>Air Freight</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase letter-spacing-1">
                            <?php echo $is_edit ? 'Add More Images' : 'Parcel Images (Scale/Package)'; ?>
                        </label>
                        <input type="file" name="tracking_images[]" multiple accept="image/*" class="form-control">
                        <?php if ($is_edit && $edit_item->images): ?>
                            <div class="mt-2 small text-muted">Currentlly has images. Adding more will append to existing gallery.</div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 shadow-sm rounded-3">
                        <i class="fas fa-save me-2"></i> <?php echo $is_edit ? 'Update Shipment' : 'Register Shipment'; ?>
                    </button>
                    <div id="single-add-status" class="mt-3"></div>
                </form>
            </div>
        </div>

        <!-- BULK ENTRY CARD (Shown only when NOT editing) -->
        <div class="col-12 col-xl-7" <?php if ($is_edit) echo 'style="opacity: 0.5; pointer-events: none;"'; ?>>
            <div class="bg-white p-4 border rounded-4 shadow-sm h-100">
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-3">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h3 class="h5 mb-0 fw-bold">Batch Processing</h3>
                </div>

                <form id="admin-add-bulk-form">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase letter-spacing-1">Tracking List</label>
                        <textarea name="tracking_numbers" rows="8" class="form-control p-3 bg-light" placeholder="Paste tracking numbers here...&#10;One per line." <?php if ($is_edit) echo 'disabled'; ?>></textarea>
                        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> Use this for high-volume intake of arrived shipments.</div>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase letter-spacing-1">Batch Method</label>
                            <select name="shipping_method" class="form-select" <?php if ($is_edit) echo 'disabled'; ?>>
                                <option value="sea">Sea Freight</option>
                                <option value="air">Air Freight</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase letter-spacing-1">Assign Batch To</label>
                            <input type="text" name="username" list="user-list-bulk" placeholder="Select user" class="form-control" <?php if ($is_edit) echo 'disabled'; ?>>
                            <datalist id="user-list-bulk">
                                <?php foreach ($all_wp_users as $u): ?>
                                    <option value="<?php echo esc_attr($u->user_login); ?>"><?php echo esc_html($u->display_name); ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success btn-lg w-100 py-2 shadow-sm rounded-3">
                                <i class="fas fa-file-import me-2"></i> Process Batch
                            </button>
                        </div>
                    </div>
                    <div id="bulk-add-status" class="mt-3"></div>
                </form>

                <div class="mt-4 p-3 bg-light rounded-3 border">
                   <div class="d-flex align-items-center text-muted small">
                       <i class="fas fa-lightbulb me-2 text-warning"></i>
                       <span>Pro Tip: You can scan barcodes directly into the Daily Shipment Intake field for rapid processing.</span>
                   </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENTLY ADDED SECTION -->
    <div class="bg-white p-4 border rounded-4 shadow-sm mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="h5 mb-0 fw-bold"><i class="fas fa-history text-muted me-2"></i> Daily Reception Log</h3>
            <button id="refresh-recent" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>

        <div id="recent-additions-container">
            <div class="text-center py-5">
                <div class="spinner-border text-primary opacity-50" role="status"></div>
                <p class="mt-2 text-muted">Syncing logs...</p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Use localized data if available, otherwise use inline values
    const ajaxUrl = (typeof ybt_admin !== 'undefined' && ybt_admin.ajax_url) ? ybt_admin.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = (typeof ybt_admin !== 'undefined' && ybt_admin.nonce) ? ybt_admin.nonce : '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>';

    // Customer Auto-fill on Username Input
    let customerLookupTimeout = null;
    const usernameInput = $('input[name="username"]').first();
    
    // Store customer data for auto-fill
    let customerDataCache = {};
    
    // Listen for typing in username field
    if (usernameInput.length) {
        usernameInput.on('input change', function() {
            const value = $(this).val();
            
            // Clear previous timeout
            if (customerLookupTimeout) {
                clearTimeout(customerLookupTimeout);
            }
            
            // Don't search if too short
            if (value.length < 2) {
                return;
            }
            
            // Debounce the lookup
            customerLookupTimeout = setTimeout(function() {
                // Check if we already have this user's data
                if (customerDataCache[value]) {
                    autoFillCustomerData(customerDataCache[value]);
                    return;
                }
                
                // Show loading indicator
                usernameInput.after('<span class="customer-lookup-loading ms-2"><i class="fas fa-spinner fa-spin text-muted"></i> Searching...</span>');
                
                // AJAX call to lookup customer
                $.post(ajaxUrl, {
                    action: 'ybt_customer_lookup',
                    nonce: nonce,
                    username: value
                }, function(res) {
                    $('.customer-lookup-loading').remove();
                    
                    if (res.success && res.data.users && res.data.users.length > 0) {
                        // Cache the results
                        res.data.users.forEach(function(user) {
                            customerDataCache[user.username] = user;
                        });
                        
                        // Auto-fill with first match if exact
                        const exactMatch = res.data.users.find(u => u.username.toLowerCase() === value.toLowerCase());
                        if (exactMatch) {
                            autoFillCustomerData(exactMatch);
                        }
                    }
                }).fail(function() {
                    $('.customer-lookup-loading').remove();
                });
            }, 300); // 300ms debounce
        });
    }
    
    // Function to auto-fill customer data
    function autoFillCustomerData(user) {
        // If there's an email field, fill it
        const emailInput = $('input[name="email"]');
        if (emailInput.length && user.email) {
            emailInput.val(user.email);
        }
        
        // If there's a phone field, fill it
        const phoneInput = $('input[name="phone"]');
        if (phoneInput.length && user.phone) {
            phoneInput.val(user.phone);
        }
        
        // If there's a custom_user_id field, fill it
        const customIdInput = $('input[name="custom_user_id"]');
        if (customIdInput.length && user.custom_user_id) {
            customIdInput.val(user.custom_user_id);
        }
        
        // Show notification
        usernameInput.after('<span class="customer-filled ms-2 text-success"><i class="fas fa-check-circle"></i> ' + user.display_name + '</span>');
        setTimeout(function() {
            $('.customer-filled').fadeOut(function() { $(this).remove(); });
        }, 3000);
    }

    // Load Recent Additions
    function loadRecentAdditions() {
        const container = $('#recent-additions-container');

        $.post(ajaxUrl, {
            action: 'ybt_get_recent_additions',
            nonce: nonce
        }, function(res) {
            if (res.success) {
                container.html(res.data);
            } else {
                container.html('<div class="alert alert-warning">Failed to load logs.</div>');
            }
        }).fail(function(xhr, status, error) {
            console.error('YBT: Recent additions failed:', status, error);
            container.html('<div class="alert alert-danger">Connection error: ' + error + '</div>');
        });
    }
    loadRecentAdditions();

    $('#refresh-recent').click(function() {
        $(this).find('i').addClass('fa-spin');
        loadRecentAdditions();
        setTimeout(() => { $(this).find('i').removeClass('fa-spin'); }, 600);
    });

    // Single Entry Handler
    $('#admin-add-single-form').submit(function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        const $status = $('#single-add-status');
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
        $status.hide();

        const formData = new FormData(this);
        formData.append('action', 'ybt_add_tracking');
        formData.append('nonce', nonce);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                console.log('Single entry response:', res);
                $btn.prop('disabled', false).html(originalText);
                if(res.success) {
                    $status.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + (res.data || 'Success') + '</div>').fadeIn();
                    <?php if ($is_edit): ?>
                        $status.append('<p class="text-sm mt-2">Redirecting to management console...</p>');
                        setTimeout(() => { window.location.href = '<?php echo admin_url('admin.php?page=ybt-manage-tracking'); ?>'; }, 1500);
                    <?php else: ?>
                        $('#admin-add-single-form')[0].reset();
                        $('input[name="tracking_number"]').focus();
                        loadRecentAdditions();
                    <?php endif; ?>
                } else {
                    $status.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + (res.data || 'Unknown error') + '</div>').fadeIn();
                }
            },
            error: function(xhr, status, error) {
                console.error('YBT: Single tracking failed:', status, error);
                console.error('Response text:', xhr.responseText);
                $btn.prop('disabled', false).html(originalText);
                $status.html('<div class="alert alert-danger">Connection error: ' + error + '</div>').fadeIn();
            }
        });
    });

    // Bulk Entry Handler
    $('#admin-add-bulk-form').submit(function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        const $status = $('#bulk-add-status');
        const originalText = $btn.html();

        const trackingText = $('textarea[name="tracking_numbers"]').val().trim();
        if (!trackingText) return;

        const lines = trackingText.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        if (lines.length === 0) return;

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing ' + lines.length + ' items...');
        $status.hide();

        $.post(ajaxUrl, {
            action: 'ybt_add_multiple_tracking',
            nonce: nonce,
            tracking_numbers: lines,
            username: $('#admin-add-bulk-form input[name="username"]').val(),
            shipping_method: $('#admin-add-bulk-form select[name="shipping_method"]').val()
        }, function(res) {
            console.log('Bulk entry response:', res);
            $btn.prop('disabled', false).html(originalText);
            if(res.success) {
                let msg = res.data.message || 'Batch processed successfully.';
                $status.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + msg + '</div>').fadeIn();
                $('#admin-add-bulk-form')[0].reset();
                loadRecentAdditions();
            } else {
                console.error('Bulk error:', res.data);
                $status.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + (res.data || 'Unknown error') + '</div>').fadeIn();
            }
        }).fail(function(xhr, status, error) {
            console.error('YBT: Bulk tracking failed:', status, error);
            console.error('Response text:', xhr.responseText);
            $btn.prop('disabled', false).html(originalText);
            $status.html('<div class="alert alert-danger">Batch processing failed: ' + error + '</div>').fadeIn();
        });
    });
});
</script>

<style>
.ybt-admin-wrapper .form-control, .ybt-admin-wrapper .form-select {
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}
.ybt-admin-wrapper .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}
.letter-spacing-1 { letter-spacing: 0.5px; }
.animate-up { animation: fadeInUp 0.4s ease-out; }
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
#recent-additions-container table { border-collapse: separate; border-spacing: 0; }
#recent-additions-container th { padding: 12px 15px; }
#recent-additions-container td { padding: 12px 15px; border-top: 1px solid #f1f5f9; }
</style>
