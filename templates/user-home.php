<?php
if (!defined('ABSPATH')) exit;

// Get rate per kg from settings (default: 3.5 Yuan)
$rate_per_kg = get_option('ybt_rate_per_kg', 3.5);

// Hide admin bar
add_action('wp_head', function() {
    echo '<style>
        #wpadminbar { display: none !important; }
        body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        html { margin-top: 0 !important; }
    </style>';
}, 2);
?>

<div class="ybt-theme-root animate-fade-in-up">
    <div class="container py-5">
        
        <!-- Hero Section -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-11">
                <div class="glass-card glass-card-green p-4 p-lg-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-6 text-center text-lg-start">
                            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-success bg-opacity-10 text-success fw-bold small mb-3 border border-success border-opacity-25">
                                <span class="ybt-pulse-ring"></span> Premium Parcel Consolidation
                            </div>
                            <h1 class="display-4 fw-extrabold text-dark mb-3" style="line-height: 1.15;">
                                We Pack & Forward Your Parcels Worldwide
                            </h1>
                            <p class="lead text-secondary mb-4 fs-5" style="line-height: 1.6;">
                                Receive goods from any store in China. We store, inspect, consolidate, and prep your shipments for global dispatch.
                            </p>
                            
                            <?php if (!is_user_logged_in()): ?>
                            <div class="d-flex gap-3 justify-content-center justify-content-lg-start flex-wrap">
                                <a href="<?php echo home_url('/register/'); ?>" class="btn btn-premium-primary btn-lg shadow-sm">
                                    <i class="fas fa-rocket me-2"></i>Get Started Free
                                </a>
                                <a href="<?php echo home_url('/login/'); ?>" class="btn btn-premium-gold btn-lg shadow-sm">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </a>
                            </div>
                            <?php else: ?>
                            <a href="<?php echo home_url('/dashboard/'); ?>" class="btn btn-premium-primary btn-lg shadow-sm">
                                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                            </a>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-6">
                            <div class="position-relative">
                                <img src="<?php echo plugin_dir_url(dirname(__FILE__)); ?>assets/images/repacking-process.webp" alt="Package Repacking Process" class="img-fluid rounded-4 shadow-lg w-100" style="height: 320px; object-fit: cover; border: 3px solid rgba(255,255,255,0.8);" loading="eager" fetchpriority="high" decoding="async">
                                <div class="position-absolute bottom-0 start-0 translate-middle-y ms-3 p-3 glass-card rounded-3 d-flex align-items-center gap-3 shadow-lg" style="background: #ffffff; max-width: 260px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);">
                                    <div class="rounded-circle bg-success text-white p-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                        <i class="fas fa-check fa-lg"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark small">Verified Inspection</strong>
                                        <span class="text-muted small">HD Photo Proof Available</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Rate Highlight Banner -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8">
                <div class="glass-card glass-card-gold p-4 text-center text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #047857 0%, #10b981 70%, #059669 100%) !important;">
                    <div class="row align-items-center py-2">
                        <div class="col-md-7 text-md-start mb-3 mb-md-0">
                            <span class="badge bg-warning text-dark fw-bold px-3 py-1 mb-2">OFFICIAL SHIPPING RATE</span>
                            <h3 class="fw-bold mb-1 text-white">Transparent Freight Pricing</h3>
                            <p class="text-white-50 mb-0 small">No hidden service fees. Pay per weight with full packing photos.</p>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <div class="d-inline-block bg-white bg-opacity-20 rounded-4 px-4 py-2 text-center" style="">
                                <div class="display-5 fw-extrabold text-warning mb-0" style="letter-spacing: -1px;">
                                    <?php echo esc_html($rate_per_kg); ?> <span class="fs-5 text-white">Yuan/kg</span>
                                </div>
                                <span class="text-white-50 small">Standard Consolidation</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Matrix -->
        <div class="mb-5">
            <div class="text-center max-w-2xl mx-auto mb-4">
                <h2 class="fw-bold text-dark mb-2">What We Do</h2>
                <p class="text-muted">End-to-end handling from supplier delivery to international forwarder</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="glass-card glass-card-interactive h-100 text-center p-4">
                        <div class="mb-3 mx-auto rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff;">
                            <i class="fas fa-boxes fa-xl"></i>
                        </div>
                        <h5 class="fw-bold mb-2">1. Parcel Receiving</h5>
                        <p class="text-muted small mb-0">We accept parcels from Taobao, 1688, Pinduoduo, or private vendors into our secure warehouse.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="glass-card glass-card-interactive h-100 text-center p-4">
                        <div class="mb-3 mx-auto rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background: linear-gradient(135deg, #d4af37 0%, #b59021 100%); color: #fff;">
                            <i class="fas fa-layer-group fa-xl"></i>
                        </div>
                        <h5 class="fw-bold mb-2">2. Consolidation</h5>
                        <p class="text-muted small mb-0">Combine multiple individual packages into compact, reinforced export cartons to save on volumetric weight.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="glass-card glass-card-interactive h-100 text-center p-4">
                        <div class="mb-3 mx-auto rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff;">
                            <i class="fas fa-shipping-fast fa-xl"></i>
                        </div>
                        <h5 class="fw-bold mb-2">3. Forwarding</h5>
                        <p class="text-muted small mb-0">Hand over sealed cartons directly to your preferred logistics line (DHL, Aramex, sea/air cargo).</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="glass-card glass-card-interactive h-100 text-center p-4">
                        <div class="mb-3 mx-auto rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); color: #fff;">
                            <i class="fas fa-shield-alt fa-xl"></i>
                        </div>
                        <h5 class="fw-bold mb-2">4. Quality Protection</h5>
                        <p class="text-muted small mb-0">Bubble wrap, waterproof taping, corner guards, and photo confirmation before final dispatch.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- How It Works Section -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-10">
                <div class="glass-card p-4 p-lg-5">
                    <h3 class="fw-bold text-dark text-center mb-4"><i class="fas fa-list-ol text-success me-2"></i>Simple 3-Step Process</h3>
                    <div class="row g-4">
                        <div class="col-md-4 text-center">
                            <div class="rounded-circle bg-success text-white fw-bold d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.25rem;">1</div>
                            <h5 class="fw-bold">Register & Add Tracking</h5>
                            <p class="text-muted small mb-0">Create an account to get your unique YBT ID. Submit your supplier tracking numbers as you order.</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="rounded-circle bg-warning text-dark fw-bold d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.25rem;">2</div>
                            <h5 class="fw-bold">Select & Request Packing</h5>
                            <p class="text-muted small mb-0">When your items arrive in warehouse status, select them and submit your shipping company address.</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="rounded-circle bg-success text-white fw-bold d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.25rem;">3</div>
                            <h5 class="fw-bold">Pay & Dispatch</h5>
                            <p class="text-muted small mb-0">Review packed carton photos, weight summary, and upload payment evidence for instant release.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guest Callout CTA -->
        <?php if (!is_user_logged_in()): ?>
        <div class="glass-card p-4 p-lg-5 text-center bg-white">
            <h3 class="fw-bold text-dark mb-2">Ready to Start Shipping?</h3>
            <p class="text-muted mb-4 max-w-xl mx-auto">Create your account in 30 seconds and get your warehouse receiving address immediately.</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?php echo home_url('/register/'); ?>" class="btn btn-premium-primary btn-lg shadow-sm">
                    <i class="fas fa-user-plus me-2"></i>Create Free Account
                </a>
                <a href="<?php echo home_url('/anonymous/'); ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-user-secret me-2"></i>Continue as Guest
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
