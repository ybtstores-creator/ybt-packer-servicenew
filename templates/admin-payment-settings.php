<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('Unauthorized');

global $wpdb;
$table_name = $wpdb->prefix . 'ybt_payment_settings';
$settings = $wpdb->get_row("SELECT * FROM $table_name WHERE is_active = 1 ORDER BY date_created DESC LIMIT 1");

// Enqueue Enhanced CSS
?>
<div class="wrap ybt-admin-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="wp-heading-inline" style="font-size: 2rem; color: var(--text-main);"><i class="fas fa-university me-2 text-primary"></i> <?php _e('Payment Settings', 'ybt-packer'); ?></h1>
    </div>

    <div class="ybt-admin-container" style="max-width: 800px;">
        <div class="glass-card">
            <div class="info-card-header bg-white">
                <span><i class="fas fa-credit-card me-2 text-primary"></i>Company Receiving Account</span>
            </div>
            <div class="info-card-body p-4">
                <form id="ybt-payment-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" value="<?php echo $settings ? esc_attr($settings->bank_name) : ''; ?>" placeholder="e.g. United Bank for Africa (UBA)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1">Account Display Name</label>
                        <input type="text" name="account_name" class="form-control" value="<?php echo $settings ? esc_attr($settings->account_name) : ''; ?>" placeholder="e.g. YBT Solutions Ltd">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1">Account Number</label>
                        <input type="text" name="account_number" class="form-control" value="<?php echo $settings ? esc_attr($settings->account_number) : ''; ?>" placeholder="e.g. 2100998877">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-dark mb-1">Payment QR Code (Optional)</label>
                        <div class="p-3 border rounded bg-light">
                            <?php if($settings && $settings->qr_code_image): ?>
                                <div class="mb-3">
                                    <p class="small text-muted mb-2">Current QR Code:</p>
                                    <img src="<?php echo esc_url($settings->qr_code_image); ?>" style="max-width:200px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 4px 6px rgba(0,0,0,0.05);" class="bg-white p-2">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="qr_code" accept="image/*" class="form-control">
                            <p class="description mt-2">Upload a QR code (e.g. WeChat, Alipay, or Bank Transfer QR) to help users pay faster.</p>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="button button-primary button-large px-4" id="save-payment-btn">
                            <i class="fas fa-save me-2"></i> Save Payment Details
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="glass-card mt-4" style="border-left: 4px solid #17a2b8;">
            <div class="info-card-body p-3 small text-muted">
                <i class="fas fa-info-circle me-1 text-info"></i> These details are shown to users in the **Mobile App** and on the **User Dashboard** when they request shipping.
            </div>
        </div>
    </div>
</div>

<style>
.ybt-admin-wrapper .form-control { border: 1px solid #ccd0d4; border-radius: 6px; padding: 10px 15px; width: 100%; }
.ybt-admin-wrapper .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 1px var(--primary-color); }
</style>

<script>
jQuery(document).ready(function($) {
    // DOM Safety: Check for ybt_admin object
    if (typeof ybt_admin === 'undefined') {
        window.ybt_admin = {
            nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
            ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
        };
    }

    $('#ybt-payment-form').submit(function(e){
        e.preventDefault();

        const btn = $('#save-payment-btn');
        const originalText = btn.html();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        var fd = new FormData(this);
        fd.append('action', 'ybt_save_payment_settings');
        fd.append('nonce', ybt_admin.nonce);

        // Use either local ajaxurl or ybt_admin object
        const finalAjaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : ybt_admin.ajax_url;

        $.ajax({
            url: finalAjaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(r){
                if(r.success) {
                    alert('Success: Payment details have been updated.');
                    location.reload();
                } else {
                    alert('Error: ' + (r.data || 'Unknown error occurred.'));
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('Server Error: Connection failed. Please check your internet or server logs.');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
