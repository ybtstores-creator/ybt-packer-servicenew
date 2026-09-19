<?php
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) { echo '<div class="alert alert-warning">Please login to view profile.</div>'; return; }

$current_user = wp_get_current_user();
$user_id = get_current_user_id();
$custom_id = get_user_meta($user_id, 'ybt_custom_user_id', true) ?: 'Pending';

// ====== DASHBOARD SETTINGS ======
add_action('wp_head', function() {
    echo '<style>
        #wpadminbar { display: none !important; }
        body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        html { margin-top: 0 !important; }
    </style>';
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);
?>

<div class="ybt-dashboard-wrapper animate-fade-in-up">
    <div class="container py-4">

        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-8">
                <div class="glass-card glass-card-green">
                    <div class="info-card-header bg-white border-bottom p-4">
                        <h4 class="mb-0 text-dark fw-bold d-flex align-items-center gap-2">
                            <i class="fas fa-id-card" style="color: #10b981;"></i> My Account Profile
                        </h4>
                    </div>
                    <div class="info-card-body p-4 p-md-5">
                        <div class="row mb-4 align-items-center g-3">
                            <div class="col-auto">
                                <div class="text-white rounded-circle d-flex align-items-center justify-content-center shadow-lg" style="width: 84px; height: 84px; font-size: 2.2rem; font-weight: 800; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: 3px solid rgba(255,255,255,0.8);">
                                    <?php echo strtoupper(substr($current_user->display_name, 0, 1)); ?>
                                </div>
                            </div>
                            <div class="col">
                                <h3 class="mb-1 fw-bold text-dark"><?php echo esc_html($current_user->display_name); ?></h3>
                                <p class="text-muted mb-1"><?php echo esc_html($current_user->user_email); ?></p>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-semibold" style="border: 1px solid rgba(16, 185, 129, 0.25);">
                                    <i class="fas fa-check-circle me-1"></i>Verified Member
                                </span>
                            </div>
                        </div>

                        <hr class="text-muted opacity-20 my-4">

                        <form id="profileUpdateForm">
                            <div class="mb-4 row align-items-center">
                                <label class="col-sm-3 col-form-label fw-bold text-secondary small text-uppercase">YBT CUSTOMER ID</label>
                                <div class="col-sm-9">
                                    <div class="d-inline-block px-3 py-1.5 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                                        <span class="fw-bold fs-5" style="color: #047857; font-family: monospace; letter-spacing: 1px;"><?php echo esc_attr($custom_id); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 row align-items-center">
                                <label class="col-sm-3 col-form-label fw-bold text-secondary small text-uppercase">USERNAME</label>
                                <div class="col-sm-9">
                                    <input type="text" readonly class="form-control-plaintext text-dark fw-semibold" value="<?php echo esc_attr($current_user->user_login); ?>">
                                </div>
                            </div>
                            <div class="mb-3 row align-items-center">
                                <label class="col-sm-3 col-form-label fw-bold text-secondary small text-uppercase">EMAIL ADDRESS</label>
                                <div class="col-sm-9">
                                    <input type="text" readonly class="form-control-plaintext text-dark" value="<?php echo esc_attr($current_user->user_email); ?>">
                                </div>
                            </div>
                            <div class="mb-3 row align-items-center">
                                <label for="mobile_number" class="col-sm-3 col-form-label fw-bold text-secondary small text-uppercase">MOBILE NUMBER</label>
                                <div class="col-sm-9">
                                    <div class="input-group">
                                        <input type="text" name="mobile_number" id="mobile_number" class="form-control form-control-premium" value="<?php echo esc_attr(get_user_meta($user_id, 'ybt_mobile_number', true) ?: ''); ?>" placeholder="+1 234 567 8900">
                                        <button class="btn btn-premium-primary px-4 fw-bold" type="submit" id="saveProfileBtn">Save Changes</button>
                                    </div>
                                    <div id="profile-message" class="mt-2 small" style="display:none;"></div>
                                </div>
                            </div>
                            <div class="mb-3 row align-items-center">
                                <label class="col-sm-3 col-form-label fw-bold text-secondary small text-uppercase">MEMBER SINCE</label>
                                <div class="col-sm-9">
                                    <input type="text" readonly class="form-control-plaintext text-muted" value="<?php echo date('F j, Y', strtotime($current_user->user_registered)); ?>">
                                </div>
                            </div>
                        </form>

                        <div class="mt-5 pt-4 border-top d-flex justify-content-between align-items-center flex-wrap gap-3">
                             <a href="<?php echo home_url('/dashboard/'); ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
                                 <i class="fas fa-arrow-left me-2"></i>Dashboard
                             </a>
                             <div class="d-flex gap-2">
                                 <a href="<?php echo home_url('/reset-password/'); ?>" class="btn btn-premium-gold rounded-pill px-4 fw-bold">
                                     <i class="fas fa-key me-2"></i>Reset Password
                                 </a>
                                 <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-outline-danger rounded-pill px-4">
                                     <i class="fas fa-sign-out-alt me-2"></i>Logout
                                 </a>
                             </div>
                        </div>

                        <script>
                        var ybtNonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.nonce) ? ybt_ajax.nonce : '<?php echo wp_create_nonce('ybt_nonce'); ?>';
                        var ybtAjaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';

                        document.addEventListener('DOMContentLoaded', function() {
                            const profileForm = document.getElementById('profileUpdateForm');
                            const saveBtn = document.getElementById('saveProfileBtn');
                            const messageEl = document.getElementById('profile-message');

                            if (profileForm) {
                                profileForm.addEventListener('submit', function(e) {
                                    e.preventDefault();

                                    const mobileNumber = document.getElementById('mobile_number').value.trim();

                                    saveBtn.disabled = true;
                                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                                    messageEl.style.display = 'none';

                                    const fd = new FormData();
                                    fd.append('action', 'ybt_update_profile');
                                    fd.append('nonce', ybtNonce);
                                    fd.append('mobile_number', mobileNumber);

                                    fetch(ybtAjaxUrl, {
                                        method: 'POST',
                                        body: fd
                                    })
                                    .then(res => res.json())
                                    .then(response => {
                                        if (response.success) {
                                            messageEl.className = 'mt-2 small text-success fw-bold';
                                            messageEl.innerHTML = '<i class="fas fa-check-circle me-1"></i>' + response.data;
                                        } else {
                                            messageEl.className = 'mt-2 small text-danger fw-bold';
                                            messageEl.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>' + (response.data || 'Error updating profile');
                                        }
                                        messageEl.style.display = 'block';
                                    })
                                    .catch(err => {
                                        messageEl.className = 'mt-2 small text-danger fw-bold';
                                        messageEl.textContent = 'Network error. Please try again.';
                                        messageEl.style.display = 'block';
                                    })
                                    .finally(() => {
                                        saveBtn.disabled = false;
                                        saveBtn.textContent = 'Save Changes';
                                    });
                                });
                            }
                        });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
