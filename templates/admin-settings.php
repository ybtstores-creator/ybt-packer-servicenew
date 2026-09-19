<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Retrieve current values
$popup_enabled = get_option('ybt_popup_enabled', 0);
$popup_message = get_option('ybt_popup_message', '');
$popup_image   = get_option('ybt_popup_image', '');

// Retrieve other settings
$email_from = get_option('ybt_email_from_email', get_option('admin_email'));
$email_name = get_option('ybt_email_from_name', get_bloginfo('name'));
$email_notif = get_option('ybt_email_notifications_enabled', 1);

// Pricing Settings
$rate_per_kg = get_option('ybt_rate_per_kg', 3.5);

$sms_api_key = get_option('ybt_sms_api_key', '');
$sms_sender_id = get_option('ybt_sms_sender_id', '');
$sms_api_url = get_option('ybt_sms_api_url', '');
?>

<div class="wrap ybt-admin-wrapper">
    <h1 class="ybt-page-title"><i class="dashicons dashicons-admin-settings"></i> General Settings</h1>

    <div class="ybt-admin-container">

        <!-- Navigation Tabs -->
        <div class="nav-tab-wrapper">
            <a href="#tab-alerts" class="nav-tab nav-tab-active ybt-tab"><i class="dashicons dashicons-megaphone"></i> Site Alerts</a>
            <a href="#tab-pricing" class="nav-tab ybt-tab"><i class="dashicons dashicons-money-alt"></i> Pricing</a>
            <a href="#tab-email" class="nav-tab ybt-tab"><i class="dashicons dashicons-email"></i> Email Config</a>
            <a href="#tab-sms" class="nav-tab ybt-tab"><i class="dashicons dashicons-smartphone"></i> SMS Config</a>
            <a href="#tab-notifications" class="nav-tab ybt-tab"><i class="dashicons dashicons-bell"></i> Push Notifications</a>
        </div>

        <!-- TAB 1: SITE ALERTS / POPUP -->
        <div id="tab-alerts" class="ybt-tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>📢 Login Popup Message</h3>
                </div>
                <div class="card-body">
                    <form id="ybt-alert-settings-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Popup</th>
                                <td>
                                    <label class="ybt-switch">
                                        <input type="checkbox" name="popup_enabled" value="1" <?php checked($popup_enabled, 1); ?>>
                                        <span class="slider round"></span>
                                    </label>
                                    <p class="description">Toggle this ON to show the message below to users on their dashboard.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Message Content</th>
                                <td>
                                    <textarea name="popup_message" rows="5" class="large-text" placeholder="Enter important announcements here..."><?php echo esc_textarea($popup_message); ?></textarea>
                                    <p class="description">HTML allowed (e.g., <code>&lt;b&gt;Bold&lt;/b&gt;</code>, <code>&lt;br&gt;</code>).</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Popup Image</th>
                                <td>
                                    <?php if ($popup_image): ?>
                                        <div style="margin-bottom: 10px;">
                                            <img src="<?php echo esc_url($popup_image); ?>" style="max-width: 150px; border-radius: 4px; border: 1px solid #ccc; display:block;">
                                            <label><input type="checkbox" name="remove_popup_image" value="1"> Remove current image</label>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="popup_image" accept="image/*">
                                    <p class="description">Optional: Upload an image to display at the top of the popup.</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary button-large">Save Alert Settings</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 2: PRICING SETTINGS -->
        <div id="tab-pricing" class="ybt-tab-content" style="display:none;">
            <div class="card">
                <div class="card-header">
                    <h3>💰 Shipping Rate Configuration</h3>
                </div>
                <div class="card-body">
                    <p class="description">Set your default shipping rate per kilogram. This rate will be displayed on the customer homepage.</p>
                    <form id="ybt-pricing-settings-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Rate per Kilogram</th>
                                <td>
                                    <div style="display: flex; align-items: center; max-width: 300px;">
                                        <input type="number" name="rate_per_kg" value="<?php echo esc_attr($rate_per_kg); ?>" class="regular-text" step="0.01" min="0" placeholder="3.5" style="border-radius: 4px 0 0 4px;">
                                        <span style="background: #f0f0f0; border: 1px solid #ccc; border-left: none; padding: 8px 12px; border-radius: 0 4px 4px 0;">Yuan/kg</span>
                                    </div>
                                    <p class="description">Enter the shipping charge per kg (default: 3.5 Yuan/kg)</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary button-large">Save Pricing Settings</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 3: EMAIL SETTINGS -->
        <div id="tab-email" class="ybt-tab-content" style="display:none;">
            <div class="card">
                <div class="card-header">
                    <h3>📧 Email Configuration</h3>
                </div>
                <div class="card-body">
                    <form id="ybt-email-settings-form">
                        <table class="form-table">
                            <tr>
                                <th>Sender Name</th>
                                <td><input type="text" name="from_name" value="<?php echo esc_attr($email_name); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th>Sender Email</th>
                                <td><input type="email" name="from_email" value="<?php echo esc_attr($email_from); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th>Notifications</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_notifications" value="1" <?php checked($email_notif, 1); ?>>
                                        Send email to user when parcel status changes (Received, Packed, etc.)
                                    </label>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">Save Email Settings</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 3: SMS SETTINGS -->
        <div id="tab-sms" class="ybt-tab-content" style="display:none;">
            <div class="card">
                <div class="card-header">
                    <h3>📱 SMS Integration</h3>
                </div>
                <div class="card-body">
                    <p class="description">Configure your third-party SMS gateway details here.</p>
                    <form id="ybt-sms-settings-form">
                        <table class="form-table">
                            <tr>
                                <th>API URL</th>
                                <td><input type="url" name="api_url" value="<?php echo esc_attr($sms_api_url); ?>" class="regular-text" placeholder="https://api.smsprovider.com/send"></td>
                            </tr>
                            <tr>
                                <th>API Key</th>
                                <td><input type="text" name="api_key" value="<?php echo esc_attr($sms_api_key); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th>Sender ID</th>
                                <td><input type="text" name="sender_id" value="<?php echo esc_attr($sms_sender_id); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">Save SMS Settings</button>
                        </p>
                    </form>
                </div>
            </div>
        <!-- TAB 4: PUSH NOTIFICATIONS (FIREBASE) -->
        <div id="tab-notifications" class="ybt-tab-content" style="display:none;">
            <div class="card">
                <div class="card-header">
                    <h3>🔔 Firebase Cloud Messaging (FCM)</h3>
                </div>
                <div class="card-body">
                    <p class="notice notice-info" style="margin-top: 0;">
                        Securely configure Firebase for push notifications. Credentials are stored encrypted in the database.
                    </p>
                    <form id="ybt-fcm-settings-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Configuration Method</th>
                                <td>
                                    <?php
                                    $config_method = "Not Configured";
                                    if (getenv('YBT_FCM_SERVICE_ACCOUNT_JSON')) $config_method = "Environment Variable (Active)";
                                    else if (defined('YBT_FCM_SERVICE_ACCOUNT_JSON')) $config_method = "WP-Config Constant (Active)";
                                    else if (get_option('ybt_fcm_service_account_encrypted')) $config_method = "Encrypted Database (Active)";
                                    ?>
                                    <span class="badge" style="background: #228B22; color: #fff; padding: 4px 8px; border-radius: 4px;"><?php echo esc_html($config_method); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Service Account JSON</th>
                                <td>
                                    <textarea name="fcm_json" rows="10" class="large-text code" placeholder='{"type": "service_account", ...}'></textarea>
                                    <p class="description">Paste the contents of your <code>firebase-service-account.json</code> file here. It will be encrypted before saving.</p>
                                    <?php if (get_option('ybt_fcm_service_account_encrypted')): ?>
                                        <p style="color: #228B22; font-weight: 500;"><i class="dashicons dashicons-lock"></i> Credential already stored. Enter new JSON to overwrite.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">Save Firebase Credentials</button>
                            <button type="button" id="ybt-test-fcm" class="button button-secondary ms-2" <?php echo $config_method === "Not Configured" ? 'disabled' : ''; ?>>Test Connection</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.ybt-admin-container { max-width: 1000px; margin-top: 20px; }
.card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 4px rgba(0,0,0,0.05); margin-bottom: 20px; border-radius: 4px; }
.card-header { border-bottom: 1px solid #eee; padding: 12px 20px; background: #f9f9f9; }
.card-header h3 { margin: 0; font-size: 1.1em; color: #228B22; }
.card-body { padding: 20px; }

/* Switch CSS */
.ybt-switch { position: relative; display: inline-block; width: 50px; height: 24px; vertical-align: middle; margin-right: 10px; }
.ybt-switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: #228B22; }
input:checked + .slider:before { transform: translateX(26px); }

/* Tabs Fix */
.nav-tab-wrapper { margin-bottom: 0; border-bottom: 1px solid #ccc; }
.nav-tab { margin-bottom: -1px; }
</style>

<script>
// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';

jQuery(document).ready(function($) {
    // Tabs Logic
    $('.ybt-tab').click(function(e) {
        e.preventDefault();
        $('.ybt-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.ybt-tab-content').hide();
        $($(this).attr('href')).show();
    });

    // Save Alert Settings (Supports Image Upload via FormData)
    $('#ybt-alert-settings-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button');
        const originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        // Create FormData to handle file upload
        let fd = new FormData(this);
        fd.append('action', 'ybt_save_alert_settings');
        fd.append('nonce', ybt_admin.nonce);

        // Manually handle checkbox state because FormData omits unchecked boxes
        if (!$('input[name="popup_enabled"]').is(':checked')) {
            fd.set('popup_enabled', '0');
        } else {
            fd.set('popup_enabled', '1');
        }

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res) {
                btn.prop('disabled', false).text(originalText);
                if(res.success) {
                    alert('Popup settings saved successfully!');
                    location.reload(); // Reload to show image preview update
                } else {
                    alert('Error: ' + escapeHtml(res.data));
                }
            },
            error: function() {
                btn.prop('disabled', false).text(originalText);
                alert('A server error occurred. Please try again.');
            }
        });
    });

    // Save Pricing Settings
    $('#ybt-pricing-settings-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button');
        const originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        let data = $(this).serializeArray();
        data.push({name: 'action', value: 'ybt_save_pricing_settings'});
        data.push({name: 'nonce', value: ybt_admin.nonce});

        $.post(ybt_admin.ajax_url, data, function(r) {
            btn.prop('disabled', false).text(originalText);
            if (r.success) {
                alert('Pricing settings saved successfully!');
            } else {
                alert('Error: ' + escapeHtml(r.data));
            }
        }).fail(function() {
            btn.prop('disabled', false).text(originalText);
            alert('Server error.');
        });
    });

    // Save Email
    $('#ybt-email-settings-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button');
        btn.prop('disabled', true).text('Saving...');
        let data = $(this).serializeArray();
        data.push({name: 'action', value: 'ybt_save_email_settings'});
        data.push({name: 'nonce', value: ybt_admin.nonce});
        $.post(ybt_admin.ajax_url, data, function(r){
            btn.prop('disabled', false).text('Save Email Settings');
            if (r.success) alert('Email settings saved successfully!');
            else alert('Error: ' + escapeHtml(r.data));
        }).fail(function(){
            btn.prop('disabled', false).text('Save Email Settings');
            alert('Server error.');
        });
    });

    // Save SMS
    $('#ybt-sms-settings-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button');
        btn.prop('disabled', true).text('Saving...');
        let data = $(this).serializeArray();
        data.push({name: 'action', value: 'ybt_save_sms_settings'});
        data.push({name: 'nonce', value: ybt_admin.nonce});
        $.post(ybt_admin.ajax_url, data, function(r){
            btn.prop('disabled', false).text('Save SMS Settings');
            if (r.success) alert('SMS settings saved successfully!');
            else alert('Error: ' + escapeHtml(r.data));
        }).fail(function(){
            btn.prop('disabled', false).text('Save SMS Settings');
            alert('Server error.');
        });
    });

    // Save FCM Settings
    $('#ybt-fcm-settings-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button');
        const originalText = btn.text();
        const json = $(this).find('textarea').val().trim();

        if (!json) {
            alert('Please paste the Service Account JSON first.');
            return;
        }

        try {
            JSON.parse(json);
        } catch(e) {
            alert('Invalid JSON format. Please check your input.');
            return;
        }

        btn.prop('disabled', true).text('Encrypting & Saving...');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_save_fcm_settings',
                nonce: ybt_admin.nonce,
                fcm_json: json
            },
            success: function(res) {
                btn.prop('disabled', false).text(originalText);
                if(res.success) {
                    alert('Firebase credentials encrypted and saved securely!');
                    location.reload();
                } else {
                    alert('Error: ' + escapeHtml(res.data));
                }
            },
            error: function() {
                btn.prop('disabled', false).text(originalText);
                alert('A server error occurred.');
            }
        });
    });

    // Test FCM
    $('#ybt-test-fcm').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Testing...');
        $.post(ybt_admin.ajax_url, {
            action: 'ybt_test_fcm_connection',
            nonce: ybt_admin.nonce
        }, function(r) {
            btn.prop('disabled', false).text('Test Connection');
            if (r.success) {
                alert('Success! ' + r.data);
            } else {
                alert('Failed: ' + escapeHtml(r.data));
            }
        });
    });
});
</script>
