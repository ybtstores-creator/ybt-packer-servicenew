<?php
if (!defined('ABSPATH')) exit;

// NOTE: Authentication redirect is handled by YBT_Core::handle_authentication_redirects()
// via 'template_redirect' hook at priority 5. Do NOT add wp_redirect() here.

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
global $wpdb;

// SECURITY FIX: Properly prepared query with pagination
$per_page = 50; // Limit to prevent overload
$items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id IS NULL OR user_id = 0 OR username = 'anonymous' OR username = 'Anonymous')
    ORDER BY date_added DESC
    LIMIT %d",
    $per_page
));

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
<style>
.ybt-anon-wrapper { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); min-height: 100vh; padding: 2rem 0; }
.ybt-header { background: linear-gradient(135deg, hsl(145, 63%, 42%) 0%, hsl(145, 63%, 35%) 100%); color: white; padding: 2rem; border-radius: 12px 12px 0 0; text-align: center; }
.text-brand-green { color: hsl(145, 63%, 42%); }
.bg-brand-gold { background-color: #D4AF37; }
.text-brand-gold { color: #D4AF37; }
.masked-tracking { font-family: monospace; font-size: 1.1rem; letter-spacing: 1px; color: #555; background: #f1f1f1; padding: 4px 8px; border-radius: 4px; border: 1px dashed #ccc; }
.btn-brand-claim { background-color: hsl(145, 63%, 42%); color: white; border: none; transition: all 0.3s; }
.btn-brand-claim:hover { background-color: hsl(145, 63%, 35%); color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(39, 175, 97, 0.3); }
</style>
<div class="ybt-anon-wrapper">
    <div class="container">
        <div class="card shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="ybt-header position-relative">
                <h2 class="mb-0 fw-bold"><i class="fas fa-user-secret me-2 text-brand-gold"></i>Unclaimed Parcels</h2>
                <p class="mb-0 opacity-75 mt-2">Parcels in our warehouse waiting for an owner</p>
                <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="btn btn-sm btn-light position-absolute top-0 start-0 m-3 rounded-pill fw-bold" style="color: hsl(145, 63%, 42%);">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-warning border-2 d-flex align-items-center mb-4" role="alert" style="border-color: #D4AF37; background-color: #fffbf0; color: #856404;">
                    <i class="fas fa-shield-alt fa-2x me-3 text-brand-gold"></i>
                    <div>
                        <strong>Security Notice:</strong> Tracking numbers are masked for security.
                        If you recognize a parcel below, please go to <a href="<?php echo esc_url(home_url('/add-tracking/')); ?>" class="fw-bold text-decoration-underline" style="color: #856404;">Manage Tracking</a> and enter the full number to claim it.
                    </div>
                </div>

                <?php if ($items): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 ps-4">Tracking Number</th>
                                <th class="py-3">Description / Details</th>
                                <th class="py-3 text-end pe-4">Date Arrived</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item):
                                // SECURITY FIX: Mask tracking number safely
                                $full_tracking = esc_html($item->tracking_number);
                                $masked = substr($full_tracking, 0, 2) . '••••••' . substr($full_tracking, -4);
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="masked-tracking" title="Full number hidden for security"><?php echo $masked; ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($item->description)): ?>
                                    <span class="text-dark fw-medium"><?php echo esc_html($item->description); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted fst-italic">No description</span>
                                    <?php endif; ?>
                                    <?php if (!empty($item->images)): ?>
                                    <i class="fas fa-image text-brand-green ms-2" title="Has image"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="text-muted">
                                        <i class="far fa-calendar-alt me-2"></i>
                                        <?php echo esc_html(date('M j, Y', strtotime($item->date_added))); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-5 text-center text-muted">
                    <div class="mb-3">
                        <span class="fa-stack fa-2x">
                            <i class="fas fa-circle fa-stack-2x text-light"></i>
                            <i class="fas fa-box-open fa-stack-1x text-secondary"></i>
                        </span>
                    </div>
                    <h4 class="fw-bold text-dark">All Caught Up!</h4>
                    <p>No unclaimed anonymous parcels found in the warehouse.</p>
                    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="btn btn-outline-secondary mt-2 rounded-pill">Return to Dashboard</a>
                </div>
                <?php endif; ?>

                <!-- CTA Footer -->
                <div class="text-center mt-5 pt-4 border-top">
                    <p class="text-muted mb-3">Recognize a parcel above?</p>
                    <a href="<?php echo esc_url(home_url('/add-tracking/')); ?>" class="btn btn-brand-claim btn-lg rounded-pill px-5 shadow-sm">
                        <i class="fas fa-search-plus me-2"></i> Go to Claim Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
