<?php
if (!defined('ABSPATH')) exit;

$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
$login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';
$is_reset_step = !empty($key) && !empty($login);
?>

<div class="auth-split-wrapper">
    <div class="glass-card p-4 p-md-5 mx-auto animate-fade-in-up" style="max-width: 480px; width: 100%;">
        <div class="text-center mb-4">
            <img src="<?php echo ybt_get_logo_url(); ?>" alt="YBT Packer" style="max-height: 70px;" loading="eager" fetchpriority="high" decoding="async">
            <h1 class="h4 mt-3 fw-bold text-dark">Account Recovery</h1>
            <p class="text-muted small">YBT Parcel Consolidation Service</p>
        </div>

        <!-- Message Area -->
        <div id="reset-message" class="alert rounded-3 mb-4" style="display:none; word-break: break-word;"></div>

        <?php if ($is_reset_step): ?>
            <h2 class="h5 fw-bold text-center mb-3">Set New Password</h2>
            <form id="ybt-process-reset-form">
                <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
                <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary small">NEW PASSWORD</label>
                    <input type="password" class="form-control form-control-premium" name="pass1" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary small">CONFIRM NEW PASSWORD</label>
                    <input type="password" class="form-control form-control-premium" name="pass2" required minlength="8" autocomplete="new-password" placeholder="Re-enter new password">
                </div>
                <button type="submit" class="btn btn-premium-primary w-100 py-3 mb-3 fw-bold">Save New Password</button>
            </form>
        <?php else: ?>
            <h2 class="h5 fw-bold text-center mb-1">Forgot Your Password?</h2>
            <p class="text-muted text-center mb-4 small">Enter your username or email address and we'll send you a password reset link.</p>
            <form id="ybt-request-reset-form">
                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary small">USERNAME OR EMAIL</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" class="form-control form-control-premium border-start-0" name="user_login" required placeholder="Enter username or email">
                    </div>
                </div>
                <button type="submit" class="btn btn-premium-primary w-100 py-3 mb-3 fw-bold">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <div class="text-center pt-2">
            <a href="<?php echo home_url('/login/'); ?>" class="text-success text-decoration-none fw-semibold small">
                <i class="fas fa-arrow-left me-1"></i> Back to Sign In
            </a>
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
