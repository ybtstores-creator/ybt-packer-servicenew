<?php
if (!defined('ABSPATH')) {
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
                        <span class="badge bg-warning text-dark fw-bold px-2 py-1">JOIN THE NETWORK</span>
                    </div>
                </div>
                <h1 class="display-6 fw-bold mb-3">Start Consolidating Free Today</h1>
                <p class="text-white-50 lead fs-6">Get your dedicated China warehouse address instantly. Combine parcels, cut shipping costs by up to 70%.</p>
            </div>

            <div class="mt-4 pt-4 border-top border-white border-opacity-25">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-id-card text-warning"></i>
                    </div>
                    <div>
                        <strong class="d-block text-white">Instant Custom User ID</strong>
                        <small class="text-white-50">Assigned immediately upon registration</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-bell text-warning"></i>
                    </div>
                    <div>
                        <strong class="d-block text-white">Automated SMS & Push Alerts</strong>
                        <small class="text-white-50">Notified the moment your parcel arrives</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Registration Form -->
        <div class="auth-form-side">
            <div class="mb-4">
                <h2 class="h3 fw-bold text-dark mb-1">Create Account</h2>
                <p class="text-muted small">Fill in your details to register your warehouse account</p>
            </div>

            <div id="register-message" class="alert mb-4" style="display:none;"></div>

            <form id="registerForm" method="post">
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold text-secondary small">USERNAME</label>
                    <input type="text" class="form-control form-control-premium" id="username" name="username" required placeholder="Choose a username">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold text-secondary small">EMAIL ADDRESS</label>
                    <input type="email" class="form-control form-control-premium" id="email" name="email" required placeholder="name@example.com">
                </div>

                <div class="mb-3">
                    <label for="mobile_number" class="form-label fw-semibold text-secondary small">MOBILE NUMBER</label>
                    <input type="text" class="form-control form-control-premium" id="mobile_number" name="mobile_number" required placeholder="+1 234 567 8900">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold text-secondary small">PASSWORD</label>
                    <input type="password" class="form-control form-control-premium" id="password" name="password" required placeholder="At least 8 characters">
                    <div class="progress mt-2" style="height: 6px; border-radius: 3px;">
                        <div id="password-strength" class="progress-bar" style="width: 0%;"></div>
                    </div>
                    <small id="password-feedback" class="text-muted small d-block mt-1"></small>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label fw-semibold text-secondary small">CONFIRM PASSWORD</label>
                    <input type="password" class="form-control form-control-premium" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                </div>

                <button type="submit" class="btn btn-premium-primary w-100 py-3 mb-3 fw-bold" id="registerBtn">
                    <i class="fas fa-user-plus me-2"></i>Create Free Account
                </button>
            </form>

            <div class="text-center pt-2">
                <p class="text-muted small mb-2">Already registered? <a href="<?php echo esc_url( home_url('/login/') ); ?>" class="text-success fw-bold text-decoration-none">Sign In</a></p>
                <p class="mb-0"><a href="<?php echo esc_url( home_url('/anonymous/') ); ?>" class="text-muted small text-decoration-underline">Continue as Guest</a></p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var passwordInput = document.getElementById('password');
    var strengthBar = document.getElementById('password-strength');
    var feedbackEl = document.getElementById('password-feedback');
    var form = document.getElementById('registerForm');
    var registerBtn = document.getElementById('registerBtn');
    var messageEl = document.getElementById('register-message');

    if(!form) return;

    function updateStrength(password) {
        var strength = 0;
        if (password.length >= 8) strength += 25;
        if (/[a-z]/.test(password)) strength += 25;
        if (/[A-Z]/.test(password)) strength += 25;
        if (/[0-9]/.test(password)) strength += 25;
        strengthBar.style.width = strength + '%';
        if (strength < 50) {
            strengthBar.className = 'progress-bar bg-danger';
            feedbackEl.textContent = 'Weak (add numbers & uppercase)';
        } else if (strength < 75) {
            strengthBar.className = 'progress-bar bg-warning';
            feedbackEl.textContent = 'Medium (good security)';
        } else {
            strengthBar.className = 'progress-bar bg-success';
            feedbackEl.textContent = 'Strong password';
        }
    }

    passwordInput.addEventListener('input', function() {
        updateStrength(this.value);
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var username = document.getElementById('username').value.trim();
        var email = document.getElementById('email').value.trim();
        var mobile_number = document.getElementById('mobile_number').value.trim();
        var password = document.getElementById('password').value;
        var confirm_password = document.getElementById('confirm_password').value;

        registerBtn.disabled = true;
        registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
        messageEl.style.display = 'none';

        var ajaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.nonce) ? ybt_ajax.nonce : '<?php echo wp_create_nonce('ybt_nonce'); ?>';

        var fd = new FormData();
        fd.append('action', 'ybt_register_user');
        fd.append('nonce', nonce);
        fd.append('username', username);
        fd.append('email', email);
        fd.append('mobile_number', mobile_number);
        fd.append('password', password);
        fd.append('confirm_password', confirm_password);

        fetch(ajaxUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function(res) {
            return res.json();
        }).then(function(response) {
            if (response.success) {
                messageEl.className = 'alert alert-success rounded-3';
                messageEl.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + (response.data || 'Registration successful! Redirecting...');
                messageEl.style.display = 'block';
                setTimeout(function() {
                    window.location.href = '<?php echo esc_url( home_url('/dashboard/') ); ?>';
                }, 1000);
            } else {
                messageEl.className = 'alert alert-danger rounded-3';
                messageEl.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + (response.data || 'Registration failed');
                messageEl.style.display = 'block';
                registerBtn.disabled = false;
                registerBtn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Create Free Account';
            }
        }).catch(function() {
            messageEl.className = 'alert alert-danger rounded-3';
            messageEl.innerHTML = '<i class="fas fa-wifi me-2"></i>Registration failed. Please check your network and try again.';
            messageEl.style.display = 'block';
            registerBtn.disabled = false;
            registerBtn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Create Free Account';
        });
    });
});
</script>
