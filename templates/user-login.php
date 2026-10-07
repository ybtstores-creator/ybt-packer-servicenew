<?php
if (!defined('ABSPATH')) exit;

// Redirect if already logged in
if (is_user_logged_in()) {
    echo '<script>window.location.href="' . home_url('/dashboard/') . '";</script>';
    exit;
}

// Hide admin bar
add_action('wp_head', function() {
    echo '<style>
        #wpadminbar { display: none !important; }
        body.admin-bar { margin-top: 0 !important; }
        html { margin-top: 0 !important; }
    </style>';
}, 2);

require YBT_PLUGIN_PATH . 'templates/components/auth-page-styles.php';
?>

<div class="ybt-authwrap">
    <div class="ybt-authcard">

        <!-- Carrier's copy: brand side -->
        <div class="ybt-authbrand">
            <span class="ybt-authstamp">Forwarding service</span>
            <div class="ybt-authmark">
                <strong>YBT</strong>
                <span>Packing service</span>
            </div>
            <h1>Global consolidation, made simple.</h1>
            <p class="ybt-authlede">Streamline your international shipments. Fast receiving, photo verification, and professional repacking.</p>
            <div class="ybt-authbarcode" aria-hidden="true"></div>

            <div class="ybt-authfeats">
                <div class="ybt-authfeat">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>100% insured storage</strong>
                        <small>Free warehouse holding up to 30 days</small>
                    </div>
                </div>
                <div class="ybt-authfeat">
                    <i class="fas fa-boxes"></i>
                    <div>
                        <strong>Real-time photo proof</strong>
                        <small>Inspect packages before dispatch</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer copy: form side -->
        <div class="ybt-authform">
            <p class="ybt-eyebrow">Access &middot; Consignment desk</p>
            <h2>Welcome back</h2>
            <p class="ybt-sub">Enter your credentials to access your dashboard.</p>

            <div id="login-message" class="alert" style="display:none;"></div>

            <form id="login-form" method="POST">
                <label class="ybt-formlabel" for="username">Username or email</label>
                <input type="text" class="ybt-input" id="username" name="username" required autofocus placeholder="Enter your username">

                <label class="ybt-formlabel" for="password">Password <a href="<?php echo home_url('/reset-password/'); ?>">Forgot?</a></label>
                <input type="password" class="ybt-input" id="password" name="password" required placeholder="Enter password">

                <div class="ybt-checkrow">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me on this device</label>
                </div>

                <button type="submit" class="ybt-btn ybt-btn-gold">
                    <i class="fas fa-sign-in-alt"></i>Sign in to dashboard
                </button>
            </form>

            <div class="ybt-swap">
                New to YBT Packing Service? <a href="<?php echo home_url('/register/'); ?>">Create account</a>
            </div>
            <a href="<?php echo home_url('/anonymous/'); ?>" class="ybt-guest">Continue as guest</a>
        </div>
    </div>
</div>

<script>
// DOM Safety: ybt_ajax fallback for caching
var ybtNonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.nonce) ? ybt_ajax.nonce : '<?php echo wp_create_nonce('ybt_nonce'); ?>';
var ybtAjaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('login-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]');
        var msg = document.getElementById('login-message');
        var originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...';
        msg.style.display = 'none';
        msg.className = 'alert';

        var fd = new FormData(this);
        fd.append('action', 'ybt_ajax_login');
        fd.append('nonce', ybtNonce);

        fetch(ybtAjaxUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
        .then(r => {
            if (!r.ok) {
                throw new Error('HTTP error! status: ' + r.status);
            }
            return r.json();
        })
        .then(d => {
            if (d.success) {
                msg.className = 'alert alert-success rounded-3';
                msg.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + (d.data && d.data.message ? d.data.message : 'Success! Redirecting...');
                msg.style.display = 'block';

                const redirectUrl = (d.data && d.data.redirect) ? d.data.redirect : '<?php echo home_url('/dashboard/'); ?>';
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 500);
            } else {
                msg.className = 'alert alert-danger rounded-3';
                msg.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + ((typeof d.data === 'string') ? d.data : (d.data && d.data.message ? d.data.message : 'Login failed'));
                msg.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(e => {
            console.error('Login error:', e);
            msg.className = 'alert alert-danger rounded-3';
            msg.innerHTML = '<i class="fas fa-wifi me-2"></i>Network error. Please check your connection and try again.';
            msg.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
});
</script>
