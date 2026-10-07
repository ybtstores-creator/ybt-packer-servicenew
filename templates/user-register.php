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

require YBT_PLUGIN_PATH . 'templates/components/auth-page-styles.php';
?>

<div class="ybt-authwrap">
    <div class="ybt-authcard">

        <!-- Carrier's copy: brand side -->
        <div class="ybt-authbrand">
            <span class="ybt-authstamp">Join the network</span>
            <div class="ybt-authmark">
                <strong>YBT</strong>
                <span>Packing service</span>
            </div>
            <h1>Start consolidating, free today.</h1>
            <p class="ybt-authlede">Get your dedicated China warehouse address instantly. Combine parcels, cut shipping costs by up to 70%.</p>
            <div class="ybt-authbarcode" aria-hidden="true"></div>

            <div class="ybt-authfeats">
                <div class="ybt-authfeat">
                    <i class="fas fa-id-card"></i>
                    <div>
                        <strong>Instant custom user ID</strong>
                        <small>Assigned immediately upon registration</small>
                    </div>
                </div>
                <div class="ybt-authfeat">
                    <i class="fas fa-bell"></i>
                    <div>
                        <strong>Automated SMS &amp; push alerts</strong>
                        <small>Notified the moment your parcel arrives</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer copy: form side -->
        <div class="ybt-authform">
            <p class="ybt-eyebrow">Registration &middot; Consignment desk</p>
            <h2>Create account</h2>
            <p class="ybt-sub">Fill in your details to register your warehouse account.</p>

            <div id="register-message" class="alert" style="display:none;"></div>

            <form id="registerForm" method="post">
                <label class="ybt-formlabel" for="username">Username</label>
                <input type="text" class="ybt-input" id="username" name="username" required placeholder="Choose a username">

                <label class="ybt-formlabel" for="email">Email address</label>
                <input type="email" class="ybt-input" id="email" name="email" required placeholder="name@example.com">

                <label class="ybt-formlabel" for="mobile_number">Mobile number</label>
                <input type="text" class="ybt-input" id="mobile_number" name="mobile_number" required placeholder="+1 234 567 8900">

                <label class="ybt-formlabel" for="password">Password</label>
                <input type="password" class="ybt-input" id="password" name="password" required placeholder="At least 8 characters" style="margin-bottom:8px;">
                <div class="progress">
                    <div id="password-strength" class="progress-bar" style="width:0%;"></div>
                </div>
                <small id="password-feedback"></small>

                <label class="ybt-formlabel" for="confirm_password" style="margin-top:14px;">Confirm password</label>
                <input type="password" class="ybt-input" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">

                <button type="submit" class="ybt-btn ybt-btn-gold" id="registerBtn">
                    <i class="fas fa-user-plus"></i>Create free account
                </button>
            </form>

            <div class="ybt-swap">
                Already registered? <a href="<?php echo esc_url( home_url('/login/') ); ?>">Sign in</a>
            </div>
            <a href="<?php echo esc_url( home_url('/anonymous/') ); ?>" class="ybt-guest">Continue as guest</a>
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
