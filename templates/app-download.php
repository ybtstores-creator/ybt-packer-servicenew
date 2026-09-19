<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
?>

<div class="ybt-dashboard-wrapper animate-fade-in-up">
    <div class="container py-4" style="max-width: 900px;">

        <div class="text-center mb-4">
            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold small border border-success border-opacity-25 mb-2">
                OFFICIAL MOBILE APP
            </span>
            <h1 class="fw-extrabold text-dark display-6 mb-2">YBT Packer Mobile App</h1>
            <p class="text-muted lead fs-6">Manage your shipments, scan tracking, and view warehouse photos on Android</p>
        </div>

        <!-- Download Hero Glass Card -->
        <div class="glass-card glass-card-green p-4 p-md-5 mb-4 text-center text-white" style="background: linear-gradient(135deg, #047857 0%, #10b981 60%, #059669 100%) !important;">
            <div class="rounded-circle bg-white bg-opacity-20 d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 72px; height: 72px;">
                <i class="fab fa-android fa-3x text-warning"></i>
            </div>
            <h2 class="fw-bold text-white mb-2">Download YBT Mobile for Android</h2>
            <p class="text-white-50 max-w-md mx-auto mb-4">Get push notifications the moment your parcel arrives in our China warehouse.</p>

            <a href="<?php echo esc_url( YBT_PLUGIN_URL . 'downloads/ybt-packer-v1.0.apk' ); ?>"
               download
               class="btn btn-premium-gold btn-lg px-5 py-3 shadow-lg fs-5">
                <i class="fas fa-download me-2"></i>Download APK (v1.0)
            </a>

            <div class="mt-4 pt-2 text-white-50 small">
                <i class="fas fa-shield-alt text-warning me-1"></i> Verified Safe • Version 1.0 • Android 5.0+ Supported
            </div>
        </div>

        <!-- Features Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="glass-card h-100 p-4">
                    <h4 class="fw-bold text-dark mb-3"><i class="fas fa-star me-2" style="color: #d4af37;"></i>App Features</h4>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2.5 text-secondary">
                        <li class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-success"></i> <strong>Real-time Tracking Updates</strong></li>
                        <li class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-success"></i> <strong>Warehouse Photo Proof Viewer</strong></li>
                        <li class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-success"></i> <strong>Carton Packing Requests</strong></li>
                        <li class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-success"></i> <strong>Payment Receipt Uploads</strong></li>
                        <li class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-success"></i> <strong>Live Customer Support Chat</strong></li>
                    </ul>
                </div>
            </div>

            <div class="col-md-6">
                <div class="glass-card glass-card-gold h-100 p-4">
                    <h4 class="fw-bold text-dark mb-3"><i class="fas fa-list-ol me-2" style="color: #10b981;"></i>How to Install APK</h4>
                    <ol class="ps-3 mb-0 text-secondary small d-flex flex-column gap-2" style="line-height: 1.6;">
                        <li><strong>Download File:</strong> Tap the download button above to get <code>ybt-packer-v1.0.apk</code>.</li>
                        <li><strong>Enable Unknown Sources:</strong> Go to Android Settings → Security/Privacy → Enable <em>"Install Unknown Apps"</em>.</li>
                        <li><strong>Install App:</strong> Open Downloads folder, tap the APK file, and press Install.</li>
                        <li><strong>Launch & Sign In:</strong> Log in with your YBT account credentials.</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Security Note -->
        <div class="alert alert-brand-info d-flex align-items-center gap-3">
            <i class="fas fa-lock fa-2x" style="color: #10b981;"></i>
            <div>
                <strong class="d-block text-dark">100% Official APK Guarantee</strong>
                <span class="text-muted small">This is the verified official Android build signed by YBT Packer Service. Always download directly from this site.</span>
            </div>
        </div>

    </div>
</div>
