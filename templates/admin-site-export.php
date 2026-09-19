<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get counts for display
$tracking_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking");
$messages_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_admin_messages");
$chat_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_chat");
$cartons_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_cartons");
$payment_settings_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_payment_settings");
$batch_payments_count = $wpdb->get_var("SELECT COUNT(DISTINCT batch_id) FROM {$wpdb->prefix}ybt_tracking WHERE payment_evidence IS NOT NULL AND payment_evidence != '' AND batch_id IS NOT NULL");
$warehouse_settings_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_warehouse_settings");
?>

<div class="wrap">
    <h1>Site Export</h1>

    <div class="card">
        <h2>Export Site Data</h2>
        <p>Export all YBT Packer Service data to a JSON file for backup or migration purposes.</p>

        <div class="export-summary">
            <h3>Data Summary</h3>
            <ul>
                <li><strong>Tracking Numbers:</strong> <?php echo $tracking_count; ?></li>
                <li><strong>Admin Messages:</strong> <?php echo $messages_count; ?></li>
                <li><strong>Chat Messages:</strong> <?php echo $chat_count; ?></li>
                <li><strong>Cartons:</strong> <?php echo $cartons_count; ?></li>
                <li><strong>Payment Settings:</strong> <?php echo $payment_settings_count; ?></li>
                <li><strong>Batch Payments:</strong> <?php echo $batch_payments_count; ?></li>
                <li><strong>Warehouse Settings:</strong> <?php echo $warehouse_settings_count; ?></li>
            </ul>
        </div>

        <div class="export-actions">
            <button id="export-site-data" class="button button-primary">Export Site Data</button>
            <div id="export-status" style="margin-top: 10px;"></div>
        </div>
    </div>

    <div class="card">
        <h3>Export Instructions</h3>
        <ol>
            <li>Click "Export Site Data" to download a JSON file containing all your YBT data</li>
            <li>Save the file in a secure location as a backup</li>
            <li>Use this file with the Site Import feature to restore data on another site</li>
        </ol>

        <div class="notice notice-warning">
            <p><strong>Note:</strong> The export includes all sensitive data. Keep the export file secure and do not share it publicly.</p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#export-site-data').click(function() {
        var button = $(this);
        var status = $('#export-status');

        button.prop('disabled', true).text('Exporting...');
        status.html('<div class="notice notice-info"><p>Preparing export...</p></div>');

        // Create a form and submit it to trigger download
        var form = $('<form>', {
            'method': 'POST',
            'action': ajaxurl
        });

        form.append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'ybt_site_export'
        }));

        form.append($('<input>', {
            'type': 'hidden',
            'name': 'nonce',
            'value': '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>'
        }));

        $('body').append(form);
        form.submit();
        form.remove();

        // Reset button after a delay
        setTimeout(function() {
            button.prop('disabled', false).text('Export Site Data');
            status.html('<div class="notice notice-success"><p>Export file should have downloaded. Check your downloads folder.</p></div>');
        }, 2000);
    });
});
</script>

<style>
.export-summary ul {
    list-style-type: disc;
    margin-left: 20px;
}

.export-summary li {
    margin-bottom: 5px;
}

.export-actions {
    margin-top: 20px;
}

.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}
</style>
