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
?>

<div class="auth-split-wrapper">
    <div class="auth-split-container glass-card">
        <!-- Left Side: Brand & Visuals -->
        <div class="auth-banner-side">
            <div>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <img src="<?php echo ybt_get_logo_url(); ?>" alt="YBT Logo" style="max-height: 60px; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.15));" loading="eager" fetchpriority="high" decoding="async">
                    <div>
                        <h2 class="h4 mb-0 fw-bold text-white">YBT PACKING</h2>
                        <span class="badge bg-warning text-dark fw-bold px-2 py-1">FORWARDING SERVICE</span>
                    </div>
                </div>
                <h1 class="display-6 fw-bold mb-3">Global Consolidation Made Simple</h1>
                <p class="text-white-50 lead fs-6">Streamline your international shipments. Fast receiving, photo verification, and professional repacking.</p>
            </div>

            <div class="mt-4 pt-4 border-top border-white border-opacity-25">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-shield-alt text-warning"></i>
                    </div>
                    <div>
                        <strong class="d-block text-white">100% Insured Storage</strong>
                        <small class="text-white-50">Free warehouse holding up to 30 days</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-boxes text-warning"></i>
                    </div>
                    <div>
                        <strong class="d-block text-white">Real-Time Photo Proof</strong>
                        <small class="text-white-50">Inspect packages before dispatch</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="auth-form-side">
            <div class="mb-4">
                <h2 class="h3 fw-bold text-dark mb-1">Welcome Back</h2>
                <p class="text-muted small">Enter your credentials to access your dashboard</p>
            </div>

            <div id="login-message" class="alert mb-4" style="display:none;"></div>

            <form id="login-form" method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary small">USERNAME OR EMAIL</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" class="form-control form-control-premium border-start-0" id="username" name="username" required autofocus placeholder="Enter your username">
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-semibold text-secondary small mb-0">PASSWORD</label>
                        <a href="<?php echo home_url('/reset-password/'); ?>" class="small text-success text-decoration-none fw-semibold">Forgot?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" class="form-control form-control-premium border-start-0" id="password" name="password" required placeholder="Enter password">
                    </div>
                </div>

                <div class="mb-4 d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label text-muted small" for="remember">Remember me on this device</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-premium-primary w-100 py-3 mb-3 fw-bold">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In to Dashboard
                </button>
            </form>

            <div class="text-center pt-2">
                <p class="text-muted small mb-2">New to YBT Packer? <a href="<?php echo home_url('/register/'); ?>" class="text-success fw-bold text-decoration-none">Create Account</a></p>
                <p class="mb-0"><a href="<?php echo home_url('/anonymous/'); ?>" class="text-muted small text-decoration-underline">Continue as Guest</a></p>
            </div>
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
