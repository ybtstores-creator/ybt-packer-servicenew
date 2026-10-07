<?php
if (!defined('ABSPATH')) exit;

$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
$login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';
$is_reset_step = !empty($key) && !empty($login);

require YBT_PLUGIN_PATH . 'templates/components/auth-page-styles.php';
?>

<div class="ybt-authwrap">
    <div class="ybt-authcard ybt-authcard-solo">
        <div class="ybt-authform">
            <div style="text-align:center; margin-bottom:20px;">
                <div class="ybt-authmark" style="justify-content:center;">
                    <strong style="font-family:var(--display); font-weight:400; font-size:24px; color:var(--emerald-deep);">YBT</strong>
                    <span style="font-family:var(--mono); font-size:10px; font-weight:600; letter-spacing:.2em; color:#5c5543; text-transform:uppercase;">Packing service</span>
                </div>
                <p class="ybt-eyebrow" style="margin-top:14px;">Account recovery</p>
                <h2 style="margin-bottom:4px;"><?php echo $is_reset_step ? 'Set new password' : 'Forgot your password?'; ?></h2>
                <p class="ybt-sub" style="margin-bottom:0;">
                    <?php echo $is_reset_step
                        ? 'Choose a new password for your account.'
                        : 'Enter your username or email and we&rsquo;ll send you a reset link.'; ?>
                </p>
            </div>

            <!-- Message Area -->
            <div id="reset-message" class="alert" style="display:none; word-break:break-word;"></div>

            <?php if ($is_reset_step): ?>
                <form id="ybt-process-reset-form">
                    <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
                    <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">
                    <label class="ybt-formlabel" for="pass1">New password</label>
                    <input type="password" class="ybt-input" id="pass1" name="pass1" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
                    <label class="ybt-formlabel" for="pass2">Confirm new password</label>
                    <input type="password" class="ybt-input" id="pass2" name="pass2" required minlength="8" autocomplete="new-password" placeholder="Re-enter new password">
                    <button type="submit" class="ybt-btn ybt-btn-gold">Save new password</button>
                </form>
            <?php else: ?>
                <form id="ybt-request-reset-form">
                    <label class="ybt-formlabel" for="user_login">Username or email</label>
                    <input type="text" class="ybt-input" id="user_login" name="user_login" required placeholder="Enter username or email">
                    <button type="submit" class="ybt-btn ybt-btn-gold">Send reset link</button>
                </form>
            <?php endif; ?>

            <div class="ybt-swap">
                <a href="<?php echo home_url('/login/'); ?>"><i class="fas fa-arrow-left"></i> Back to sign in</a>
            </div>

            <div class="ybt-authfoot">
                <div class="ybt-authbarcode" aria-hidden="true"></div>
                <span><?php echo $is_reset_step ? 'Reset in progress' : 'Recovery desk'; ?></span>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const nonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.nonce) ? ybt_ajax.nonce : '<?php echo wp_create_nonce('ybt_nonce'); ?>';
    const ajaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
    const msg = $('#reset-message');

    function showError(xhr, status, error) {
        let errorMsg = '<strong>Connection Error:</strong> ' + error;
        if (xhr.responseText) {
            let cleanText = xhr.responseText.replace(/<[^>]*>?/gm, '');
            if(cleanText.length > 200) cleanText = cleanText.substring(0, 200) + '...';
            errorMsg += '<br><small class="text-muted">Server response: ' + cleanText + '</small>';
        }
        msg.removeClass('alert-success').addClass('alert-danger').html(errorMsg).show();
    }

    // Step 1: Request Link
    $('#ybt-request-reset-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button');
        const originalText = btn.html();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');
        msg.hide().removeClass('alert-success alert-danger');

        const fd = new FormData(this);
        fd.append('action', 'ybt_reset_password_request');
        fd.append('nonce', nonce);

        $.ajax({
            url: ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false,
            success: function(res) {
                btn.prop('disabled', false).html(originalText);
                if(res.success) {
                    msg.addClass('alert-success').html('<i class="fas fa-check-circle me-2"></i> ' + res.data).show();
                    $('#ybt-request-reset-form').slideUp();
                } else {
                    msg.addClass('alert-danger').html('<i class="fas fa-exclamation-circle me-2"></i> ' + (res.data || 'Unknown error')).show();
                }
            },
            error: function(xhr, status, error) {
                btn.prop('disabled', false).html(originalText);
                showError(xhr, status, error);
            }
        });
    });

    // Step 2: Process Reset
    $('#ybt-process-reset-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button');
        const originalText = btn.html();
        const p1 = $('input[name="pass1"]').val();
        const p2 = $('input[name="pass2"]').val();

        if(p1 !== p2) {
            msg.addClass('alert-danger').html('<i class="fas fa-exclamation-circle me-2"></i> Passwords do not match.').show();
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Updating...');
        msg.hide().removeClass('alert-success alert-danger');

        const fd = new FormData(this);
        fd.append('action', 'ybt_reset_password_process');
        fd.append('nonce', nonce);

        $.ajax({
            url: ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false,
            success: function(res) {
                if(res.success) {
                    msg.addClass('alert-success').html('<i class="fas fa-check-circle me-2"></i> ' + res.data).show();
                    $('#ybt-process-reset-form').slideUp();
                    setTimeout(function() { window.location.href = '<?php echo home_url("/login/"); ?>'; }, 2000);
                } else {
                    btn.prop('disabled', false).html(originalText);
                    msg.addClass('alert-danger').html('<i class="fas fa-exclamation-circle me-2"></i> ' + (res.data || 'Unknown error')).show();
                }
            },
            error: function(xhr, status, error) {
                btn.prop('disabled', false).html(originalText);
                showError(xhr, status, error);
            }
        });
    });
});
</script>
