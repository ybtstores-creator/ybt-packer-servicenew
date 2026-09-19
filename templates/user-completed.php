<?php
/**
 * User Completed Items Page
 * Displays all completed/shipped items for the logged-in user
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

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

// Pagination with security validation
$per_page = 25;
$current_page = max(1, intval(get_query_var('paged', 1)));
$offset = ($current_page - 1) * $per_page;

$items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s) AND status = 'completed'
    ORDER BY date_completed DESC
    LIMIT %d OFFSET %d",
    $user_id,
    $current_user->user_login,
    $per_page,
    $offset
));

$total_items = intval($wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s) AND status = 'completed'",
    $user_id,
    $current_user->user_login
)));
$total_pages = ceil($total_items / $per_page);
?>

<div class="ybt-dashboard-wrapper animate-fade-in-up">
    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-shipping-fast" style="color: #10b981;"></i> Shipped & Completed
                </h2>
                <p class="text-muted small mb-0">Consolidated shipments dispatched to your forwarder or destination address</p>
            </div>
            <div class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2 fs-6" style="border: 1px solid rgba(16, 185, 129, 0.25);">
                <i class="fas fa-check-double me-2" style="color: #d4af37;"></i>Dispatched: <?php echo esc_html(number_format($total_items)); ?>
            </div>
        </div>

        <div class="glass-card glass-card-green mb-4">
            <div class="info-card-header bg-white border-bottom p-3.5 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-bold text-secondary"><i class="fas fa-paper-plane me-2" style="color: #d4af37;"></i>Completed Dispatch Records</span>
                <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1 rounded-pill" style="border: 1px solid rgba(16, 185, 129, 0.25);">
                    <i class="fas fa-check me-1"></i>Finalized Shipments
                </span>
            </div>
            <div class="info-card-body p-0">
                <?php if ($items): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light text-secondary small text-uppercase" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th class="ps-4 py-3">Tracking / Reference</th>
                                    <th class="py-3">Carton Info</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Dispatch Photos</th>
                                    <th class="text-end pe-4 py-3">Shipped Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold fs-6" style="color: #047857; font-family: monospace; letter-spacing: 0.5px;"><?php echo esc_html($item->tracking_number); ?></div>
                                            <div class="text-muted small"><?php echo esc_html($item->description ?: 'No description'); ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($item->carton_length) && !empty($item->carton_width) && !empty($item->carton_height)): ?>
                                                <div class="fw-bold text-dark mb-1">
                                                    <?php echo esc_html($item->carton_length . ' × ' . $item->carton_width . ' × ' . $item->carton_height . ' cm'); ?>
                                                </div>
                                                <div class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">
                                                    <?php echo esc_html($item->carton_weight ? $item->carton_weight . ' kg' : 'Standard'); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">Standard Shipment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-pill status-pill-completed">
                                                <i class="fas fa-check-circle me-1"></i> Dispatched
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($item->images)):
                                                $images = explode(',', $item->images);
                                                $images = array_filter(array_map('trim', $images));
                                                $image_count = count($images);
                                            ?>
                                                <div class="d-flex gap-2">
                                                    <?php foreach (array_slice($images, 0, 3) as $image):
                                                        $image_url = esc_url($image);
                                                        if (empty($image_url) || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $image_url)) continue;
                                                    ?>
                                                        <div class="position-relative" style="width: 44px; height: 44px;">
                                                            <img src="<?php echo $image_url; ?>" class="rounded-3 shadow-sm border border-success border-opacity-25" style="width:100%; height:100%; object-fit:cover; cursor:pointer;" onclick="openImageFullscreen('<?php echo esc_js($image_url); ?>')">
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small opacity-50">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php $date = $item->date_completed ?: $item->date_added; ?>
                                            <div class="text-dark fw-semibold"><?php echo esc_html(date('M j, Y', strtotime($date))); ?></div>
                                            <div class="text-muted small"><?php echo esc_html(date('h:i A', strtotime($date))); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <div class="rounded-circle bg-success bg-opacity-10 text-success p-4 d-inline-flex mb-3">
                            <i class="fas fa-shipping-fast fa-3x"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">No Completed Shipments Yet</h4>
                        <p class="text-muted max-w-md mx-auto">Once your packed cartons are handed over to your logistics company, your completed tracking history will appear here.</p>
                        <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="btn btn-premium-primary rounded-pill mt-2">
                            <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-5 d-flex justify-content-center">
            <?php
            echo paginate_links(array(
                'base'      => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                'format'    => 'page/%#%/',
                'current'   => $current_page,
                'total'     => $total_pages,
                'prev_text' => '<i class="fas fa-chevron-left"></i>',
                'next_text' => '<i class="fas fa-chevron-right"></i>',
                'type'      => 'list',
                'end_size'  => 1,
                'mid_size'  => 1
            ));
            ?>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Image Viewer Modal -->
<div id="image-viewer-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.75); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card" style="padding:24px; max-width:90%; max-height:90%; overflow-y:auto; width:800px; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-images me-2" style="color: #10b981;"></i>Dispatch Verification Photos</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" onclick="jQuery('#image-viewer-modal').hide()"><i class="fas fa-times"></i></button>
        </div>
        <div id="image-gallery" class="text-center d-flex flex-wrap justify-content-center gap-3"></div>
    </div>
</div>

<script>
window.openImageFullscreen = function(src) {
    if (!src) return;
    const safeSrc = src.replace(/"/g, '&quot;');
    const modal = jQuery('<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);z-index:99999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;"><img src="' + safeSrc + '" style="max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 0 50px rgba(0,0,0,0.5);"></div>');
    jQuery('body').append(modal.hide().fadeIn(200));
    modal.click(function() { jQuery(this).fadeOut(200, function(){ jQuery(this).remove(); }); });
};
</script>
