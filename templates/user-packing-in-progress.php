<?php
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
global $wpdb;

$items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s)
    AND status = 'packing_in_progress'
    ORDER BY date_packing_requested DESC",
    $user_id,
    $current_user->user_login
));

// ====== DASHBOARD SETTINGS ======
add_action('wp_head', function() {
    echo '<style>
        #wpadminbar { display: none !important; }
        body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        html { margin-top: 0 !important; }
    </style>';
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: blob: https:; connect-src \'self\' https:;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);

require YBT_PLUGIN_PATH . 'templates/components/status-page-styles.php';
?>

<div class="ybd-page">

    <!-- Page header -->
    <div class="ybd-pagehead">
        <div>
            <p class="ybd-eyebrow">Stage 4 begins &middot; On the packing tables</p>
            <h2>Packing in progress</h2>
            <p class="ybd-sub">Parcels currently on warehouse packing tables being consolidated into cartons.</p>
        </div>
        <span class="ybd-count ybd-count-gold">In progress: <?php echo count($items); ?></span>
    </div>

    <div class="ybd-panel ybd-panel-gold">
        <div class="ybd-panelhead">
            <span class="ybd-hint"><i class="fas fa-list-check"></i>Active consolidation sessions</span>
            <a href="<?php echo esc_url(home_url('/received/')); ?>" class="ybd-btn ybd-btn-gold">
                <i class="fas fa-plus"></i>Request more packing
            </a>
        </div>
        <?php if ($items): ?>
            <div class="ybd-tablewrap">
                <table class="ybd-manifest">
                    <thead>
                        <tr>
                            <th>Tracking number</th>
                            <th>Description</th>
                            <th>Items</th>
                            <th>Photos</th>
                            <th>Batch ID</th>
                            <th>Status</th>
                            <th style="text-align:right;">Date requested</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span></td>
                                <td><span class="ybd-desc"><?php echo esc_html($item->description ?: 'No description'); ?></span></td>
                                <td>
                                    <span class="ybd-pcs"><i class="fas fa-boxes"></i><?php echo esc_html($item->item_count); ?> pcs</span>
                                </td>
                                <td>
                                    <?php if ($item->images):
                                        $images = explode(',', $item->images);
                                        $images = array_filter(array_map('trim', $images));
                                    ?>
                                        <div class="ybd-thumbs">
                                            <?php foreach (array_slice($images, 0, 2) as $image):
                                                $image_url = esc_url($image);
                                                if (empty($image_url) || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $image_url)) continue;
                                            ?>
                                                <img src="<?php echo $image_url; ?>" alt="Parcel photo" class="ybd-thumb" loading="lazy" decoding="async"
                                                     onclick="openImageFullscreen('<?php echo esc_js($image_url); ?>')">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="ybd-none">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($item->batch_id): ?>
                                        <span class="ybd-batch"><?php echo esc_html($item->batch_id); ?></span>
                                    <?php else: ?>
                                        <span class="ybd-none">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ybd-chip ybd-chip-packing_in_progress"><i class="fas fa-spinner fa-spin"></i>Packing in progress</span>
                                </td>
                                <td class="ybd-date">
                                    <?php $date = $item->date_packing_requested ?: $item->date_added; ?>
                                    <strong><?php echo esc_html(date('M j, Y', strtotime($date))); ?></strong>
                                    <small><?php echo esc_html(date('h:i A', strtotime($date))); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="ybd-empty">
                <div class="ybd-emptyicon"><i class="fas fa-box-open"></i></div>
                <h4>Nothing on the tables right now</h4>
                <p>Items you submit for consolidation will appear here while warehouse staff inspect, measure, and seal your cartons.</p>
                <a href="<?php echo esc_url(home_url('/received/')); ?>" class="ybd-btn ybd-btn-emerald"><i class="fas fa-warehouse"></i>View received items to pack</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Information note -->
    <div class="ybd-note">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>What happens next?</strong>
            <span>Our warehouse staff is packing your selected parcels into export cartons. Once completed, your items will automatically move to the <strong>Packed</strong> page with complete weights, carton dimensions, and photos.</span>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div id="image-viewer-modal" class="ybd-modal ybd-modal-wide" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999;">
    <div style="max-width:800px;">
        <div class="ybd-modalhead">
            <h5><i class="fas fa-images"></i> Parcel verification photos</h5>
            <button type="button" class="ybd-modalclose" onclick="jQuery('#image-viewer-modal').hide()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div id="image-gallery" style="display:flex; flex-wrap:wrap; gap:12px; justify-content:center;"></div>
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
