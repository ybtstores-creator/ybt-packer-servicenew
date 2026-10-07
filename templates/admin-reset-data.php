<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}
?>

<div class="wrap">
    <h1><i class="dashicons dashicons-warning"></i> Reset Data</h1>

    <div class="ybt-admin-container">
        <div class="card">
            <div class="card-header">
                <h3>⚠️ Danger Zone</h3>
            </div>
            <div class="card-body">
                <div class="notice notice-error">
                    <p><strong>WARNING:</strong> This action will permanently delete ALL data from the YBT Packing Service plugin. This cannot be undone!</p>
                </div>

                <h4>What will be deleted:</h4>
                <ul>
                    <li>All tracking numbers and their data</li>
                    <li>All admin messages</li>
                    <li>All chat conversations</li>
                    <li>All carton information</li>
                    <li>All payment settings</li>
                    <li>All batch payment records</li>
                    <li>All warehouse settings</li>
                </ul>

                <div class="reset-confirmation">
                    <label>
                        <input type="checkbox" id="confirm-reset">
                        I understand this action cannot be undone and will delete all data
                    </label>
                </div>

                <button type="button" class="button button-primary button-large" id="reset-data-btn" disabled>
                    Reset All Data
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Reset User Data</h3>
            </div>
            <div class="card-body">
                <p>Reset data for a specific user only.</p>

                <div class="user-reset-form">
                    <label for="reset-user">Select User:</label>
                    <select id="reset-user">
                        <option value="">Choose a user...</option>
                        <?php
                        global $wpdb;
                        $users = $wpdb->get_results("SELECT DISTINCT username FROM {$wpdb->prefix}ybt_tracking WHERE username IS NOT NULL ORDER BY username");
                        foreach ($users as $user) {
                            echo '<option value="' . esc_attr($user->username) . '">' . esc_html($user->username) . '</option>';
                        }
                        ?>
                    </select>

                    <button type="button" class="button button-secondary" id="reset-user-btn" disabled>
                        Reset User Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// DOM Safety: Check for ybt_admin object (caching-safe fallback)
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

jQuery(document).ready(function($) {
    // Enable/disable reset button based on checkbox
    $('#confirm-reset').change(function() {
        $('#reset-data-btn').prop('disabled', !$(this).is(':checked'));
    });

    // Enable/disable user reset button based on selection
    $('#reset-user').change(function() {
        $('#reset-user-btn').prop('disabled', $(this).val() === '');
    });

    // Reset data button
    $('#reset-data-btn').click(function() {
        if (!confirm('Are you absolutely sure you want to delete ALL data? This cannot be undone!')) {
            return;
        }

        if (!confirm('This is your final warning. All tracking numbers, messages, and settings will be permanently deleted. Continue?')) {
            return;
        }

        $(this).prop('disabled', true).text('Resetting...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ybt_reset_data',
                nonce: ybt_admin.nonce,
                confirm_truncate: 'YES_I_AM_SURE'
            },
            success: function(response) {
                if (response.success) {
                    alert('All data has been reset successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $('#reset-data-btn').prop('disabled', false).text('Reset All Data');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $('#reset-data-btn').prop('disabled', false).text('Reset All Data');
            }
        });
    });

    // Reset user data button
    $('#reset-user-btn').click(function() {
        const username = $('#reset-user').val();

        if (!confirm('Are you sure you want to delete all data for user "' + username + '"? This cannot be undone!')) {
            return;
        }

        $(this).prop('disabled', true).text('Resetting...');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_reset_user_data',
                nonce: ybt_admin.nonce,
                username: username
            },
            success: function(response) {
                if (response.success) {
                    alert('User data has been reset successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $('#reset-user-btn').prop('disabled', false).text('Reset User Data');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $('#reset-user-btn').prop('disabled', false).text('Reset User Data');
            }
        });
    });
});
</script>

<style>
.reset-confirmation {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 15px;
    margin: 20px 0;
    border-radius: 5px;
}

.reset-confirmation label {
    font-weight: bold;
    color: #856404;
}

#reset-data-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.card-header {
    background: #dc3545;
    color: white;
}
</style>
