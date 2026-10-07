<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin Add Tracking — "Intake counter"
 * Consignment-note design system (ledger scope).
 * JS contract preserved: #admin-add-single-form, #admin-add-bulk-form,
 * #single-add-status, #bulk-add-status, #recent-additions-container,
 * #refresh-recent, input[name=tracking_number|username|item_count|
 * shipping_method|tracking_numbers], #user-list-single, #user-list-bulk,
 * ybt_customer_lookup auto-fill, ?edit= mode.
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

// Edit Mode Logic
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_item = null;
if ($edit_id) {
    $edit_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $edit_id));
}
$is_edit = ($edit_id && $edit_item);
?>

<div class="wrap ybt-ledger">
    <div class="ybt-ledgerhead">
        <div>
            <span class="ybd-eyebrow">Depot ledger · Intake counter</span>
            <h1><?php echo $is_edit ? 'Edit shipment entry' : 'Register new shipments'; ?></h1>
            <p><?php echo $is_edit
                ? 'Update the details of an already-registered consignment.'
                : 'Single parcels or whole batches — scan straight into the field for rapid intake.'; ?></p>
        </div>
        <?php if ($is_edit): ?>
            <a href="<?php echo admin_url('admin.php?page=ybt-add-tracking'); ?>" class="ybd-btn ybd-btn-dark">Switch to new entry</a>
        <?php else: ?>
            <span class="ybd-count">Admin entry portal</span>
        <?php endif; ?>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:18px; align-items:start;">
        <!-- SINGLE ENTRY/EDIT PANEL -->
        <div class="ybd-panel">
            <div class="ybd-panelhead">
                <span class="ybd-hint"><i class="fas fa-barcode"></i><?php echo $is_edit ? 'Edit shipment details' : 'Single entry'; ?></span>
            </div>
            <div class="ybd-panelbody">
                <form id="admin-add-single-form" enctype="multipart/form-data">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="tracking_id" value="<?php echo esc_attr($edit_id); ?>">
                    <?php endif; ?>

                    <label class="ybd-formlabel">Tracking number</label>
                    <input type="text" name="tracking_number" required class="ybd-input"
                           value="<?php echo $is_edit ? esc_attr($edit_item->tracking_number) : ''; ?>"
                           placeholder="Paste or scan tracking number" autofocus>

                    <label class="ybd-formlabel" style="margin-top:14px;">Assign user <span style="font-weight:400;">(optional)</span></label>
                    <input type="text" name="username" list="user-list-single" class="ybd-input"
                           value="<?php echo $is_edit ? esc_attr($edit_item->username) : ''; ?>"
                           placeholder="Type name or custom ID">
                    <datalist id="user-list-single">
                        <?php foreach ($all_wp_users as $u): ?>
                            <option value="<?php echo esc_attr($u->user_login); ?>"><?php echo esc_html($u->display_name . ($u->custom_id ? ' [' . $u->custom_id . ']' : '')); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                    <p style="margin:6px 0 0; font-size:12.5px; color:#3d4a42;">Leave empty to register as anonymous / unclaimed.</p>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:14px;">
                        <div>
                            <label class="ybd-formlabel">Items count</label>
                            <input type="number" name="item_count" min="1" class="ybd-input"
                                   value="<?php echo $is_edit ? esc_attr($edit_item->item_count ?: 1) : '1'; ?>">
                        </div>
                        <div>
                            <label class="ybd-formlabel">Method</label>
                            <select name="shipping_method" class="ybd-input">
                                <option value="sea" <?php if($is_edit) selected($edit_item->shipping_method, 'sea'); ?>>Sea freight</option>
                                <option value="air" <?php if($is_edit) selected($edit_item->shipping_method, 'air'); ?>>Air freight</option>
                            </select>
                        </div>
                    </div>

                    <label class="ybd-formlabel" style="margin-top:14px;">
                        <?php echo $is_edit ? 'Add more images' : 'Parcel images (scale / package)'; ?>
                    </label>
                    <input type="file" name="tracking_images[]" multiple accept="image/*" class="ybd-input" style="padding:9px 12px;">
                    <?php if ($is_edit && $edit_item->images): ?>
                        <p style="margin:6px 0 0; font-size:12.5px; color:#3d4a42;">Currently has images — adding more appends to the gallery.</p>
                    <?php endif; ?>

                    <button type="submit" class="ybd-btn ybd-btn-gold" style="width:100%; margin-top:18px; padding:13px 20px;">
                        <i class="fas fa-save"></i><?php echo $is_edit ? 'Update shipment' : 'Register shipment'; ?>
                    </button>
                    <div id="single-add-status" style="margin-top:12px;"></div>
                </form>
            </div>
        </div>

        <!-- BULK ENTRY PANEL (dimmed while editing) -->
        <div class="ybd-panel" <?php if ($is_edit) echo 'style="opacity:0.5; pointer-events:none;"'; ?>>
            <div class="ybd-panelhead">
                <span class="ybd-hint"><i class="fas fa-layer-group"></i>Batch processing</span>
            </div>
            <div class="ybd-panelbody">
                <form id="admin-add-bulk-form">
                    <label class="ybd-formlabel">Tracking list</label>
                    <textarea name="tracking_numbers" rows="8" class="ybd-input" style="font-family:var(--mono); font-size:13px;"
                              placeholder="Paste tracking numbers here…&#10;One per line." <?php if ($is_edit) echo 'disabled'; ?>></textarea>
                    <p style="margin:6px 0 0; font-size:12.5px; color:#3d4a42;"><i class="fas fa-info-circle"></i> Use this for high-volume intake of arrived shipments.</p>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:14px;">
                        <div>
                            <label class="ybd-formlabel">Batch method</label>
                            <select name="shipping_method" class="ybd-input" <?php if ($is_edit) echo 'disabled'; ?>>
                                <option value="sea">Sea freight</option>
                                <option value="air">Air freight</option>
                            </select>
                        </div>
                        <div>
                            <label class="ybd-formlabel">Assign batch to</label>
                            <input type="text" name="username" list="user-list-bulk" placeholder="Select user" class="ybd-input" <?php if ($is_edit) echo 'disabled'; ?>>
                            <datalist id="user-list-bulk">
                                <?php foreach ($all_wp_users as $u): ?>
                                    <option value="<?php echo esc_attr($u->user_login); ?>"><?php echo esc_html($u->display_name); ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <button type="submit" class="ybd-btn ybd-btn-emerald" style="width:100%; margin-top:18px; padding:13px 20px;">
                        <i class="fas fa-file-import"></i>Process batch
                    </button>
                    <div id="bulk-add-status" style="margin-top:12px;"></div>
                </form>

                <div class="ybd-note" style="margin-top:16px;">
                    <i class="fas fa-lightbulb"></i>
                    <span><strong>Pro tip:</strong> scan barcodes straight into the tracking list for rapid processing.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENTLY ADDED -->
    <div class="ybd-panel" style="margin-top:18px;">
        <div class="ybd-panelhead">
            <span class="ybd-hint"><i class="fas fa-history"></i>Daily reception log</span>
            <button id="refresh-recent" class="ybd-btn" style="padding:7px 14px; font-size:13px;">
                <i class="fas fa-sync-alt"></i>Refresh
            </button>
        </div>
        <div id="recent-additions-container">
            <p style="padding:24px; text-align:center; color:#3d4a42; font-family:var(--mono); font-size:13px;">Syncing logs…</p>
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
            shipping_method: $(this).find('select[name="shipping_method"]').val(),
            username: $(this).find('input[name="username"]').val()
        }, function(res) {
            $btn.prop('disabled', false).html(originalText);
            if (res.success) {
                $status.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + (res.data || 'Batch processed') + '</div>').fadeIn();
                $('#admin-add-bulk-form')[0].reset();
                loadRecentAdditions();
            } else {
                $status.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + (res.data || 'Batch failed') + '</div>').fadeIn();
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).html(originalText);
            $status.html('<div class="alert alert-danger">Batch processing failed: ' + error + '</div>').fadeIn();
        });
    });
});
</script>

<style>
/* intake-counter page-local layer */
.ybt-ledger input[type="file"].ybd-input{ padding:8px 10px; font-size:13.5px; }
.ybt-ledger textarea[name="tracking_numbers"]{ line-height:1.7; }
#recent-additions-container table{ border-collapse:separate; border-spacing:0; width:100%; }
#recent-additions-container th{ padding:12px 15px; }
#recent-additions-container td{ padding:12px 15px; border-top:1px solid var(--kraft-line); }
</style>
