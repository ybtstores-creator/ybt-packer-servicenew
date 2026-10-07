<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

$api_key = get_option('ybt_sms_api_key', '');
$sender_id = get_option('ybt_sms_sender_id', '');
$api_url = get_option('ybt_sms_api_url', '');
?>

<div class="wrap">
    <h1><i class="dashicons dashicons-email-alt"></i> SMS Settings</h1>

    <div class="ybt-admin-container">
        <div class="card">
            <div class="card-header">
                <h3>SMS API Configuration</h3>
            </div>
            <div class="card-body">
                <form id="sms-settings-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_key">API Key</label>
                            </th>
                            <td>
                                <input type="text" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="Enter your SMS API key">
                                <p class="description">Your SMS service provider API key</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="sender_id">Sender ID</label>
                            </th>
                            <td>
                                <input type="text" id="sender_id" name="sender_id" value="<?php echo esc_attr($sender_id); ?>" class="regular-text" placeholder="YBT-PACKING">
                                <p class="description">Sender ID that will appear in SMS messages</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api_url">API URL</label>
                            </th>
                            <td>
                                <input type="url" id="api_url" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" placeholder="https://api.smsservice.com/send">
                                <p class="description">SMS service provider API endpoint URL</p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">Save SMS Settings</button>
                    </p>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>SMS Configuration Guide</h3>
            </div>
            <div class="card-body">
                <h4>Supported SMS Providers:</h4>
                <ul>
                    <li><strong>Twilio:</strong> Use Twilio API credentials and endpoint</li>
                    <li><strong>Nexmo/Vonage:</strong> Use Nexmo API key and endpoint</li>
                    <li><strong>TextMagic:</strong> Use TextMagic API credentials</li>
                    <li><strong>Custom Provider:</strong> Configure with your provider's API details</li>
                </ul>

                <h4>How SMS Notifications Work:</h4>
                <ul>
                    <li>SMS notifications are sent when parcel status changes</li>
                    <li>Users receive notifications for: Received, Packed, Completed status updates</li>
                    <li>Phone numbers are taken from user contact information</li>

                    <li>Messages include tracking number and new status</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#sms-settings-form').submit(function(e) {
        e.preventDefault();

        const formData = {
            action: 'ybt_save_sms_settings',
            nonce: '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>',
            api_key: $('#api_key').val(),
            sender_id: $('#sender_id').val(),
            api_url: $('#api_url').val()
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('SMS settings saved successfully!');
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error saving SMS settings');
            }
        });
    });
});
</script>
