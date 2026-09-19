<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'ybt_warehouse_settings';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

$air_settings = null;

if ($table_exists) {
    $air_settings = $wpdb->get_row("SELECT * FROM $table_name WHERE shipping_type = 'air' ORDER BY date_created DESC LIMIT 1");
}
?>

<div class="wrap">
    <h1><i class="dashicons dashicons-building"></i> Warehouse Settings</h1>

    <?php if (!$table_exists): ?>
        <div class="notice notice-error">
            <p><strong>Database Error:</strong> The warehouse settings table is missing.</p>
            <p><button id="fix-db-table" class="button button-primary">Create Missing Table</button></p>
        </div>
    <?php endif; ?>

    <div class="ybt-admin-container">
        <!-- Warehouse Settings -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h3>📦 Warehouse Settings</h3>
            </div>
            <div class="card-body">
                <form class="warehouse-settings-form" data-type="air">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Status</th>
                            <td>
                                <label><input type="checkbox" class="is_active" <?php echo ($air_settings && $air_settings->is_active) ? 'checked' : ''; ?>> Enable this warehouse address</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Warehouse Address</th>
                            <td>
                                <textarea class="address" rows="4" style="width: 100%;" required><?php echo $air_settings ? esc_textarea($air_settings->address) : "YBT Warehouse\n1234 Main Street\nSuite 100\nMiami, FL 33101\nUnited States"; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Phone</th>
                            <td>
                                <input type="text" class="phone regular-text"
                                       value="<?php echo $air_settings ? esc_attr($air_settings->phone) : '+1 (555) 123-4567'; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Email</th>
                            <td>
                                <input type="email" class="email regular-text"
                                       value="<?php echo $air_settings ? esc_attr($air_settings->email) : 'warehouse@ybtpacker.com'; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Operating Hours</th>
                            <td>
                                <input type="text" class="hours regular-text"
                                       value="<?php echo $air_settings ? esc_attr($air_settings->hours) : 'Mon-Fri 9AM-6PM EST'; ?>">
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button-primary">Save Warehouse Settings</button>
                    </p>
                </form>
            </div>
        </div>


    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.warehouse-settings-form').submit(function(e) {
        e.preventDefault();

        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const originalText = btn.text();
        const shippingType = form.data('type');


        btn.prop('disabled', true).text('Saving...');

        // Use standardized ybt_admin object with fallback
        const ajaxUrl = (typeof ybt_admin !== 'undefined') ? ybt_admin.ajax_url : ajaxurl;
        const nonce = (typeof ybt_admin !== 'undefined') ? ybt_admin.nonce : '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>';

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'ybt_save_warehouse_settings',
                nonce: nonce,
                shipping_type: shippingType,
                address: form.find('.address').val(),
                phone: form.find('.phone').val(),
                email: form.find('.email').val(),
                hours: form.find('.hours').val(),
                is_active: form.find('.is_active').is(':checked') ? 1 : 0
            },
            success: function(response) {
                btn.prop('disabled', false).text(originalText);
                if (response.success) {
                    alert(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).text(originalText);
                console.error("Save error:", xhr);
                alert('System Error. Check console.');
            }
        });
    });
});
</script>
