<?php
/**
 * YBT Packer Service — Firebase Cloud Messaging (FCM) Credentials Settings
 */
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

$config_method = "Not Configured";
$status_badge = '<span class="badge bg-danger" style="background:#dc2626; color:#fff; padding:6px 12px; border-radius:20px; font-weight:600;"><i class="dashicons dashicons-warning me-1"></i> Not Configured</span>';

if (getenv('YBT_FCM_SERVICE_ACCOUNT_JSON')) {
    $config_method = "Environment Variable (Active)";
    $status_badge = '<span class="badge bg-success" style="background:#16a34a; color:#fff; padding:6px 12px; border-radius:20px; font-weight:600;"><i class="dashicons dashicons-yes me-1"></i> Active via Env Var</span>';
} else if (defined('YBT_FCM_SERVICE_ACCOUNT_JSON')) {
    $config_method = "WP-Config Constant (Active)";
    $status_badge = '<span class="badge bg-success" style="background:#16a34a; color:#fff; padding:6px 12px; border-radius:20px; font-weight:600;"><i class="dashicons dashicons-yes me-1"></i> Active via wp-config.php</span>';
} else if (get_option('ybt_fcm_service_account_encrypted')) {
    $config_method = "Encrypted Database (Active)";
    $status_badge = '<span class="badge bg-success" style="background:#10b981; color:#fff; padding:6px 12px; border-radius:20px; font-weight:600;"><i class="dashicons dashicons-lock me-1"></i> Encrypted in Database</span>';
}
?>

<div class="wrap ybt-admin-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <h1 class="ybt-page-title mb-0"><i class="dashicons dashicons-bell" style="color:#10b981;"></i> Firebase Push Notifications (FCM)</h1>
        <div><?php echo $status_badge; ?></div>
    </div>

    <div class="ybt-admin-container" style="max-width: 900px;">
        <div class="card p-4 shadow-sm" style="border-radius:12px; background:#fff; border:1px solid #cbd5e1;">
            <div class="card-body">
                <div class="notice notice-info p-3 mb-4" style="background:#ecfdf5; border-left:4px solid #10b981; border-radius:8px; color:#047857;">
                    <h4 style="margin:0 0 6px; font-weight:700; color:#047857;"><i class="dashicons dashicons-shield me-1"></i> Secure Firebase Service Account Configuration</h4>
                    <p style="margin:0; font-size:0.95rem;">Paste the raw JSON contents of your <code>firebase-service-account.json</code> file below. Credentials will be encrypted with AES-256 before saving to the database.</p>
                </div>

                <form id="ybt-fcm-credentials-form">
                    <div class="mb-4">
                        <label for="fcm_json_input" class="form-label fw-bold d-block mb-2" style="font-size:1.05rem; color:#1e293b;">
                            <i class="dashicons dashicons-code-standards me-1" style="color:#10b981;"></i> Service Account JSON Key
                        </label>
                        <textarea id="fcm_json_input" name="fcm_json" rows="12" class="large-text code" style="width:100%; font-family: monospace; background:#0f172a; color:#38bdf8; border-radius:10px; padding:16px; font-size:0.9rem; line-height:1.6; border:1.5px solid #334155;" placeholder='{
  "type": "service_account",
  "project_id": "ybtpackingservice-6ef6d",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...",
  "client_email": "firebase-adminsdk-...@ybtpackingservice-6ef6d.iam.gserviceaccount.com",
  ...
}'></textarea>
                        <p class="description mt-2" style="color:#64748b;">
                            <i class="dashicons dashicons-info me-1"></i> Download this JSON file from <strong>Firebase Console &rarr; Project Settings &rarr; Service Accounts &rarr; Generate New Private Key</strong>.
                        </p>
                    </div>

                    <div class="d-flex align-items-center gap-3 pt-2">
                        <button type="submit" id="btn-save-fcm" class="button button-primary button-large" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border:none; padding:8px 24px; font-weight:700; border-radius:8px;">
                            <i class="dashicons dashicons-lock me-1"></i> Encrypt & Save Firebase Credentials
                        </button>
                        <button type="button" id="btn-test-fcm" class="button button-secondary button-large" style="border-radius:8px; padding:8px 20px;" <?php echo $config_method === "Not Configured" ? 'disabled' : ''; ?>>
                            <i class="dashicons dashicons-yes me-1"></i> Test Connection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Safety check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

jQuery(document).ready(function($) {
    $('#ybt-fcm-credentials-form').submit(function(e) {
        e.preventDefault();
        const json = $('#fcm_json_input').val().trim();
        const btn = $('#btn-save-fcm');
        const originalText = btn.html();

        if (!json) {
            alert('Please paste your firebase-service-account.json content into the box.');
            return;
        }

        try {
            JSON.parse(json);
        } catch(err) {
            alert('Invalid JSON format! Please check that you copied the complete content of firebase-service-account.json.');
            return;
        }

        btn.prop('disabled', true).html('<i class="dashicons dashicons-update spin me-1"></i> Encrypting & Saving...');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_save_fcm_settings',
                nonce: ybt_admin.nonce,
                fcm_json: json
            },
            success: function(res) {
                btn.prop('disabled', false).html(originalText);
                if (res.success) {
                    alert('Success! Firebase credentials encrypted and saved securely.');
                    location.reload();
                } else {
                    alert('Error: ' + (res.data || 'Failed to save credentials.'));
                }
            },
            error: function() {
                btn.prop('disabled', false).html(originalText);
                alert('A server error occurred. Please try again.');
            }
        });
    });

    $('#btn-test-fcm').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="dashicons dashicons-update spin me-1"></i> Testing...');

        $.post(ybt_admin.ajax_url, {
            action: 'ybt_test_fcm_connection',
            nonce: ybt_admin.nonce
        }, function(res) {
            btn.prop('disabled', false).html('<i class="dashicons dashicons-yes me-1"></i> Test Connection');
            if (res.success) {
                alert('Success! ' + res.data);
            } else {
                alert('Connection Test Failed: ' + (res.data || 'Unknown error'));
            }
        });
    });
});
</script>
