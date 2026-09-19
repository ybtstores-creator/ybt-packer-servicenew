<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

global $wpdb;

$messages = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ybt_admin_messages ORDER BY date_created DESC");
?>

<div class="wrap">
    <h1><i class="dashicons dashicons-email-alt"></i> Admin Messages & Popup</h1>

    <div class="ybt-admin-container">
        <!-- Popup Alert Management -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Site-Wide Popup Alert</h3>
            </div>
            <div class="card-body">
                <form id="popup-settings-form" enctype="multipart/form-data">
                    <div style="margin-bottom:15px;">
                        <label><input type="checkbox" id="popup-enabled" <?php echo get_option('ybt_popup_enabled') ? 'checked' : ''; ?>> Enable Popup</label>
                    </div>
                    <textarea id="popup-message" placeholder="Enter popup message..." rows="4" style="width:100%;margin-bottom:10px;"><?php echo esc_textarea(get_option('ybt_popup_message')); ?></textarea>
                    <input type="text" id="popup-url" placeholder="Video/File URL (optional - YouTube, PDF, images, etc.)" style="width:100%;margin-bottom:10px;padding:8px;" value="<?php echo esc_attr(get_option('ybt_popup_url')); ?>">
                    <input type="file" id="popup-image" accept="image/*" style="width:100%;margin-bottom:10px;padding:8px;">
                    <?php if (get_option('ybt_popup_image')): ?>
                        <div style="margin-bottom:10px;">
                            <img src="<?php echo esc_url(get_option('ybt_popup_image')); ?>" style="max-width:200px;">
                            <button type="button" id="remove-popup-image" class="button">Remove Image</button>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="button button-primary">Save Popup Settings</button>
                </form>
            </div>
        </div>

        <!-- Add Message -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Send Message to All Users</h3>
            </div>
            <div class="card-body">
                <form id="add-message-form" enctype="multipart/form-data">
                    <textarea id="message-text" placeholder="Enter your message..." rows="4" style="width: 100%; margin-bottom: 10px;"></textarea>
                    <input type="text" id="video-url" placeholder="Video/File URL (optional - YouTube, PDF, images, etc.)" style="width: 100%; margin-bottom: 10px; padding: 8px;" />
                    <input type="file" id="message-image" accept="image/*" style="width: 100%; margin-bottom: 10px; padding: 8px;" />
                    <small style="display: block; margin-bottom: 10px; color: #666;">Upload an image to display with the message (optional)</small>
                    <button type="submit" class="button button-primary">Send Message</button>
                </form>
            </div>
        </div>

        <!-- Messages List -->
        <div class="card">
            <div class="card-header">
                <h3>Active Messages (<?php echo count($messages); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if ($messages): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Message</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                                <tr data-id="<?php echo $message->id; ?>">
                                    <td>
                                        <?php echo esc_html($message->message); ?>
                                        <?php if (!empty($message->video_url)): ?>
                                            <br><small>📎 <a href="<?php echo esc_url($message->video_url); ?>" target="_blank" style="color: #228B22; text-decoration: none; font-weight: 500;">View Attachment</a></small>
                                        <?php endif; ?>
                                        <?php if (!empty($message->image_url)): ?>
                                            <br><img src="<?php echo esc_url($message->image_url); ?>" style="max-width: 100px; margin-top: 5px;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($message->date_created)); ?></td>
                                    <td>
                                        <button class="button button-small delete-message" data-id="<?php echo $message->id; ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No messages found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
    // Popup Settings
    $('#popup-settings-form').submit(function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('action', 'ybt_save_alert_settings');
        formData.append('nonce', ybt_admin.nonce);
        formData.append('popup_enabled', $('#popup-enabled').is(':checked') ? '1' : '0');
        formData.append('popup_message', $('#popup-message').val());
        formData.append('popup_url', $('#popup-url').val());
        const imageFile = $('#popup-image')[0].files[0];
        if (imageFile) formData.append('popup_image', imageFile);

        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(r) {
                if(r.success) {
                    alert('Popup settings saved!');
                    location.reload();
                } else {
                    alert('Error: ' + escapeHtml(r.data || 'Unknown error'));
                    btn.prop('disabled', false).text('Save Popup Settings');
                }
            },
            error: function() {
                alert('A server error occurred.');
                btn.prop('disabled', false).text('Save Popup Settings');
            }
        });
    });

    $('#remove-popup-image').click(function() {
        if(confirm('Remove popup image?')) {
            $.post(ybt_admin.ajax_url, {
                action: 'ybt_save_alert_settings',
                nonce: ybt_admin.nonce,
                remove_popup_image: '1'
            }, function(r) {
                if(r.success) location.reload();
                else alert('Error: ' + escapeHtml(r.data));
            }).fail(function(){ alert('Server error.'); });
        }
    });

    // Dashboard Messages
    $('#add-message-form').submit(function(e) {
        e.preventDefault();

        const message = $('#message-text').val().trim();
        const videoUrl = $('#video-url').val().trim();
        const imageFile = $('#message-image')[0].files[0];

        if (!message) {
            alert('Please enter a message');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'ybt_add_message');
        formData.append('nonce', ybt_admin.nonce);
        formData.append('message', message);
        formData.append('video_url', videoUrl);
        if (imageFile) {
            formData.append('message_image', imageFile);
        }

        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Sending...');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                btn.prop('disabled', false).text('Send Message');
                if (response.success) {
                    alert('Message sent successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + escapeHtml(response.data));
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Send Message');
                alert('Server error.');
            }
        });
    });

    $('.delete-message').click(function() {
        const id = $(this).data('id');

        if (confirm('Delete this message?')) {
            $.ajax({
                url: ybt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ybt_delete_message',
                    nonce: ybt_admin.nonce,
                    message_id: id
                },
                success: function(response) {
                    if (response.success) {
                        $('tr[data-id="' + id + '"]').fadeOut();
                        alert('Message deleted successfully!');
                    } else {
                        alert('Error: ' + escapeHtml(response.data));
                    }
                },
                error: function() {
                    alert('Server error.');
                }
            });
        }
    });
});
</script>

<style>
.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    background: linear-gradient(135deg, #228B22, #DAA520);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 15px 20px;
}

.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
}

.mb-4 {
    margin-bottom: 1.5rem;
}
</style>
