<?php
if (!defined('ABSPATH')) exit;

// NOTE: Authentication redirect is handled by YBT_Core::handle_authentication_redirects()
// via 'template_redirect' hook at priority 5. Do NOT add wp_redirect() here.

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
global $wpdb;

// SECURITY FIX: Properly validate and sanitize parcel IDs
$parcel_ids = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : '';
$parcel_array = array_filter(array_map('intval', explode(',', $parcel_ids)));

// Fetch only user's parcels with proper validation
if (!empty($parcel_array)) {
    $placeholders = implode(',', array_fill(0, count($parcel_array), '%d'));
    $parcels = $wpdb->get_results($wpdb->prepare(
        "SELECT tracking_number, description FROM {$wpdb->prefix}ybt_tracking
        WHERE id IN ($placeholders) AND (user_id = %d OR username = %s)",
        array_merge($parcel_array, array($user_id, $current_user->user_login))
    ));
} else {
    $parcels = array();
}
?>
<div class="received-parcels-container" style="max-width: 800px; margin: 2rem auto; padding: 0 15px;">
    <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
        <div class="card-header bg-success text-white text-center py-4">
            <h2 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Received Parcels</h2>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($parcels)): ?>
            <div class="alert alert-success mb-4">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Great news!</strong> The following parcels have been received at our warehouse:
            </div>

            <div class="list-group mb-4">
                <?php foreach ($parcels as $parcel): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="text-success"><?php echo esc_html($parcel->tracking_number); ?></strong>
                        <?php if ($parcel->description): ?>
                        <br><small class="text-muted"><?php echo esc_html($parcel->description); ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-success rounded-pill px-3 py-2">
                        <i class="fas fa-check me-1"></i>Received
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center">
                <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="btn btn-lg btn-success px-5 py-3 rounded-pill shadow-sm">
                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                </a>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-inbox fa-4x text-muted opacity-50"></i>
                </div>
                <h4 class="text-muted mb-3">No parcels received</h4>
                <p class="text-muted mb-4">We haven't received any parcels matching your request yet.</p>
                <div class="d-grid gap-3">
                    <a href="<?php echo esc_url(home_url('/add-tracking/')); ?>" class="btn btn-outline-primary py-3">
                        <i class="fas fa-plus-circle me-2"></i>Add Tracking Numbers
                    </a>
                    <a href="<?php echo esc_url(home_url('/not-received/')); ?>" class="btn btn-outline-secondary py-3">
                        <i class="fas fa-clock me-2"></i>View Pending Items
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-light text-center py-3">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Parcels typically arrive at our warehouse within 3-7 business days after shipping.
                You'll receive email notifications when status changes.
            </small>
        </div>
    </div>
</div>

<style>
.received-parcels-container { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif; }
.list-group-item { border-left: 3px solid #28a745; }
</style>
