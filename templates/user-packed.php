<?php
/**
 * User Packed Items Page
 * Displays all packed items for the logged-in user
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
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: blob: https:; connect-src \'self\' https:;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);

// Pagination
$per_page = 25;
$current_page = max(1, get_query_var('paged') ? intval(get_query_var('paged')) : 1);
$offset = ($current_page - 1) * $per_page;

$items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s) AND status = 'packed'
    ORDER BY date_packed DESC
    LIMIT %d OFFSET %d",
    $user_id,
    $current_user->user_login,
    $per_page,
    $offset
));

$total_items = intval($wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s) AND status = 'packed'",
    $user_id,
    $current_user->user_login
)));
$total_pages = ceil($total_items / $per_page);

require YBT_PLUGIN_PATH . 'templates/components/status-page-styles.php';
?>

<div class="ybd-page">

    <!-- Page header -->
    <div class="ybd-pagehead">
        <div>
            <p class="ybd-eyebrow">Stage 4 of 5 &middot; Sealed cartons</p>
            <h2>Packed cartons</h2>
            <p class="ybd-sub">Consolidated export cartons packed, weighed, and ready for shipping.</p>
        </div>
        <span class="ybd-count">Total packed: <?php echo esc_html(number_format($total_items)); ?></span>
    </div>

    <div class="ybd-panel">
        <div class="ybd-panelhead">
            <span class="ybd-hint"><i class="fas fa-check-circle"></i>Sealed cartons ready for dispatch</span>
            <span class="ybd-stamp" style="font-size:9px; padding:4px 8px;">Verified weight &amp; photos</span>
        </div>
        <?php if ($items): ?>
            <div class="ybd-tablewrap">
                <table class="ybd-manifest">
                    <thead>
                        <tr>
                            <th>Tracking / reference</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Carton photos</th>
                            <th style="text-align:right;">Packed date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span>
                                    <span class="ybd-desc"><?php echo esc_html($item->description ?: 'No description'); ?></span>
                                </td>
                                <td>
                                    <span class="ybd-pcs"><i class="fas fa-cubes"></i><?php echo esc_html($item->item_count ?: '1'); ?> pcs</span>
                                </td>
                                <td>
                                    <span class="ybd-chip ybd-chip-packed"><i class="fas fa-box-open"></i>Packed &amp; sealed</span>
                                </td>
                                <td>
                                    <?php if (!empty($item->images)):
                                        $images = explode(',', $item->images);
                                        $images = array_filter(array_map('trim', $images));
                                    ?>
                                        <div class="ybd-thumbs">
                                            <?php foreach (array_slice($images, 0, 3) as $image):
                                                $image_url = esc_url($image);
                                                if (empty($image_url) || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $image_url)) continue;
                                            ?>
                                                <img src="<?php echo $image_url; ?>" alt="Carton photo" class="ybd-thumb" loading="lazy" decoding="async"
                                                     onclick="openImageFullscreen('<?php echo esc_js($image_url); ?>')">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="ybd-none">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="ybd-date">
                                    <?php $date = $item->date_packed ?: $item->date_added; ?>
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
                <div class="ybd-emptyicon"><i class="fas fa-box"></i></div>
                <h4>No packed cartons ready</h4>
                <p>Items submitted for packing will be displayed here once warehouse staff seals the cartons.</p>
                <a href="<?php echo esc_url(home_url('/received/')); ?>" class="ybd-btn ybd-btn-emerald"><i class="fas fa-warehouse"></i>Select items to pack</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="ybd-pagenav">
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

<!-- Image Viewer Modal -->
<div id="image-viewer-modal" class="ybd-modal ybd-modal-wide" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999;">
    <div style="max-width:800px;">
        <div class="ybd-modalhead">
            <h5><i class="fas fa-images"></i> Carton verification photos</h5>
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
