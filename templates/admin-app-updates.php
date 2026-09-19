<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Current saved values
$current_version  = get_option('ybt_app_latest_version', '');
$current_url      = get_option('ybt_app_download_url', '');
$current_msg      = get_option('ybt_app_update_message', '');
$update_enabled   = get_option('ybt_app_update_enabled', 0);
$apk_dir          = YBT_PLUGIN_PATH . 'downloads/';
$apk_files        = glob($apk_dir . '*.apk') ?: [];
?>

<div class="wrap">
    <h1><i class="dashicons dashicons-smartphone" style="color:#10b981;"></i> App Update Manager</h1>
    <p class="description">Upload a new APK and push the update notification to users on their dashboard. Users will see a banner with a download link.</p>

    <div id="ybt-app-update-notice"></div>

    <div class="ybt-admin-container" style="max-width: 860px;">

        <!-- ===== Current Status Card ===== -->
        <div class="card mb-4" style="border-left: 4px solid #10b981;">
            <div class="card-header" style="background:#f0fdf4; border-bottom: 1px solid #bbf7d0;">
                <h3 style="color:#065f46; margin:0;">📱 Current Live Version</h3>
            </div>
            <div class="card-body" style="padding: 20px;">
                <?php if ($current_version && $current_url): ?>
                    <div class="d-flex flex-wrap gap-4 align-items-center">
                        <div>
                            <small class="text-muted d-block">Version</small>
                            <strong style="font-size:1.4rem; color:#065f46;"><?php echo esc_html($current_version); ?></strong>
                        </div>
                        <div class="flex-grow-1">
                            <small class="text-muted d-block">Download URL</small>
                            <a href="<?php echo esc_url($current_url); ?>" target="_blank" style="word-break:break-all;">
                                <?php echo esc_html($current_url); ?>
                            </a>
                        </div>
                        <div>
                            <small class="text-muted d-block">Banner Visible to Users</small>
                            <span class="badge <?php echo $update_enabled ? 'bg-success' : 'bg-secondary'; ?> px-3 py-2" style="font-size:0.95rem;">
                                <?php echo $update_enabled ? '✅ Active' : '❌ Hidden'; ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($current_msg): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <small class="text-muted">Changelog shown to users:</small>
                            <div><?php echo nl2br(esc_html($current_msg)); ?></div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-0"><em>No update has been pushed yet. Fill in the form below to push your first update.</em></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Push Update Form ===== -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>🚀 Push New App Update</h3>
            </div>
            <div class="card-body" style="padding: 20px;">
                <form id="ybt-app-update-form" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Show Update Banner</th>
                            <td>
                                <label class="ybt-switch">
                                    <input type="checkbox" name="update_enabled" value="1" <?php checked($update_enabled, 1); ?>>
                                    <span class="slider round"></span>
                                </label>
                                <p class="description">Turn this ON to show the update banner on the user dashboard. Turn OFF to hide it.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">App Version <span style="color:red;">*</span></th>
                            <td>
                                <input type="text" name="app_version" class="regular-text"
                                       value="<?php echo esc_attr($current_version); ?>"
                                       placeholder="e.g. 2.0.0" required>
                                <p class="description">Enter the new version number (e.g. 2.0.0). This is shown in the banner.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">APK File</th>
                            <td>
                                <input type="file" name="apk_file" accept=".apk">
                                <p class="description">Upload a new APK file (max 50MB). The download URL will be auto-filled. You can also manually set the URL below.</p>
                                <?php if (!empty($apk_files)): ?>
                                    <div class="mt-2 p-3 bg-light rounded">
                                        <strong>Existing APK files:</strong>
                                        <ul class="mb-0 mt-1">
                                            <?php foreach ($apk_files as $f):
                                                $fname = basename($f);
                                                $furl  = YBT_PLUGIN_URL . 'downloads/' . $fname;
                                                $fsize = round(filesize($f) / 1048576, 1);
                                            ?>
                                                <li>
                                                    <a href="<?php echo esc_url($furl); ?>" target="_blank"><?php echo esc_html($fname); ?></a>
                                                    <span class="text-muted">(<?php echo $fsize; ?> MB)</span>
                                                    <button type="button" class="btn btn-xs btn-link ybt-use-apk-url"
                                                            data-url="<?php echo esc_attr($furl); ?>"
                                                            style="font-size:0.8rem; padding:0 6px;">
                                                        Use this
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Download URL</th>
                            <td>
                                <input type="url" name="download_url" id="ybt-download-url" class="large-text"
                                       value="<?php echo esc_attr($current_url); ?>"
                                       placeholder="https://yoursite.com/.../ybt-packer.apk">
                                <p class="description">Direct download link to the APK. Auto-filled when you upload above, or paste a custom URL.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">What's New / Changelog</th>
                            <td>
                                <textarea name="update_message" rows="4" class="large-text"
                                          placeholder="e.g. - Bug fixes&#10;- Improved tracking display&#10;- Faster load times"><?php echo esc_textarea($current_msg); ?></textarea>
                                <p class="description">This is shown to users as a subtitle under the version number. Keep it short and user-friendly.</p>
                            </td>
                        </tr>
                    </table>

                    <div class="d-flex gap-3 mt-3">
                        <button type="submit" class="button button-primary button-large">
                            <i class="dashicons dashicons-upload" style="margin-top:3px;"></i> Save &amp; Push Update
                        </button>
                        <button type="button" id="ybt-hide-update-btn" class="button button-secondary button-large">
                            <i class="dashicons dashicons-hidden" style="margin-top:3px;"></i> Hide Update Banner
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== REST API Info ===== -->
        <div class="card">
            <div class="card-header" style="background:#f8faff;">
                <h3>🔗 Android App — Auto-Update Check API</h3>
            </div>
            <div class="card-body" style="padding: 20px;">
                <p>Your Android app can call this REST endpoint to check for updates automatically:</p>
                <div style="background:#1e293b; color:#e2e8f0; padding:14px 18px; border-radius:8px; font-family:monospace; font-size:0.92rem; word-break:break-all;">
                    GET <?php echo esc_html(rest_url('ybt/v1/app-version')); ?>
                </div>
                <p class="mt-3 mb-1"><strong>Sample Response:</strong></p>
                <pre style="background:#f1f5f9; border-radius:8px; padding:14px; font-size:0.87rem;">{
  "latest_version": "2.0.0",
  "download_url": "https://yoursite.com/.../ybt-packer-v2.0.apk",
  "message": "Bug fixes and performance improvements",
  "update_available": true
}</pre>
                <p class="description">In your Android app, compare the <code>latest_version</code> field to the app's current version string and redirect the user to <code>download_url</code> if an update is available.</p>
            </div>
        </div>

    </div><!-- .ybt-admin-container -->
</div><!-- .wrap -->

<style>
.ybt-admin-container .card { background:#fff; border:1px solid #ccd0d4; box-shadow:0 1px 4px rgba(0,0,0,.05); margin-bottom:20px; border-radius:6px; }
.ybt-admin-container .card-header { border-bottom:1px solid #eee; padding:12px 20px; background:#f9f9f9; }
.ybt-admin-container .card-header h3 { margin:0; font-size:1.1em; color:#1a5c3a; }
.ybt-admin-container .card-body { padding:20px; }
.ybt-switch { position:relative; display:inline-block; width:50px; height:24px; vertical-align:middle; margin-right:10px; }
.ybt-switch input { opacity:0; width:0; height:0; }
.slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#ccc; transition:.4s; border-radius:34px; }
.slider:before { position:absolute; content:""; height:16px; width:16px; left:4px; bottom:4px; background-color:white; transition:.4s; border-radius:50%; }
input:checked + .slider { background-color:#10b981; }
input:checked + .slider:before { transform:translateX(26px); }
#ybt-app-update-notice .notice { margin:10px 0; }
</style>

<script>
jQuery(document).ready(function($) {

    // "Use this" shortcut to fill URL field from existing APK
    $('.ybt-use-apk-url').click(function() {
        $('#ybt-download-url').val($(this).data('url'));
    });

    // Hide Update Banner shortcut button
    $('#ybt-hide-update-btn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Hiding...');
        $.post(ajaxurl, {
            action: 'ybt_save_app_update',
            nonce: '<?php echo wp_create_nonce("ybt_admin_nonce"); ?>',
            update_enabled: 0,
            app_version: '<?php echo esc_js($current_version); ?>',
            download_url: '<?php echo esc_js($current_url); ?>',
            update_message: ''
        }, function(r) {
            btn.prop('disabled', false).text('Hide Update Banner');
            showNotice(r.success, r.success ? '✅ Update banner hidden from users.' : '❌ ' + r.data);
        });
    });

    // Main form submit
    $('#ybt-app-update-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Saving...');

        const fd = new FormData(this);
        fd.append('action', 'ybt_save_app_update');
        fd.append('nonce', '<?php echo wp_create_nonce("ybt_admin_nonce"); ?>');

        // Handle toggle (FormData skips unchecked boxes)
        if (!$('input[name="update_enabled"]').is(':checked')) {
            fd.set('update_enabled', '0');
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(r) {
                btn.prop('disabled', false).text('Save & Push Update');
                if (r.success) {
                    showNotice(true, '✅ ' + r.data.message);
                    if (r.data.download_url) {
                        $('#ybt-download-url').val(r.data.download_url);
                    }
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotice(false, '❌ ' + r.data);
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Save & Push Update');
                showNotice(false, '❌ Server error. Please try again.');
            }
        });
    });

    function showNotice(success, msg) {
        const cls = success ? 'notice-success' : 'notice-error';
        $('#ybt-app-update-notice').html(`<div class="notice ${cls} is-dismissible"><p>${msg}</p></div>`);
        $('html, body').animate({ scrollTop: 0 }, 400);
    }
});
</script>
