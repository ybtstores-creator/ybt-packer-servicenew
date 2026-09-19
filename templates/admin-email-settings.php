<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

$from_email = get_option('ybt_email_from_email', get_option('admin_email'));
$from_name = get_option('ybt_email_from_name', 'YBT Packer Service');
$notifications_enabled = get_option('ybt_email_notifications_enabled', 1);
?>

<div class="wrap">
    <h1><i class="dashicons dashicons-email-alt"></i> Email Settings</h1>

    <div class="ybt-admin-container">
        <div class="card">
            <div class="card-header">
                <h3>Email Notification Configuration</h3>
            </div>
            <div class="card-body">
                <form id="email-settings-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_notifications">Enable Email Notifications</label>
                            </th>
                            <td>
                                <input type="checkbox" id="enable_notifications" name="enable_notifications" <?php checked($notifications_enabled, 1); ?>>
                                <p class="description">Enable automatic email notifications for status updates</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="from_email">From Email</label>
                            </th>
                            <td>
                                <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text" required>
                                <p class="description">Email address that notifications will be sent from</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="from_name">From Name</label>
                            </th>
                            <td>
                                <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text" required>
                                <p class="description">Name that will appear as the sender</p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Email Settings</button>
                    </p>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Email Notification Information</h3>
            </div>
            <div class="card-body">
                <h4>When are emails sent?</h4>
                <ul>
                    <li>When parcel status changes from "Not Received" to "Received"</li>
                    <li>When parcel status changes from "Received" to "Packed"</li>
                    <li>When parcel status changes from "Packed" to "Completed"</li>
                </ul>

                <h4>Email Content:</h4>
                <ul>
                    <li>Personalized greeting with user's name</li>
                    <li>Tracking number and new status</li>
                    <li>Professional HTML formatted message</li>
                </ul>

                <h4>Requirements:</h4>
                <ul>
                    <li>Users must have valid email addresses in their profiles</li>
                    <li>WordPress mail function must be properly configured</li>
                    <li>Email notifications must be enabled above</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#email-settings-form').submit(function(e) {
        e.preventDefault();

        const formData = {
            action: 'ybt_save_email_settings',
            nonce: '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>',
            from_email: $('#from_email').val(),
            from_name: $('#from_name').val(),
            enable_notifications: $('#enable_notifications').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Email settings saved successfully!');
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error saving email settings');
            }
        });
    });
});
</script>
