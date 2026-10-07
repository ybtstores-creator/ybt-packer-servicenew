<?php
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
global $wpdb;

// Pagination with security
$per_page = 25;
$current_page = max(1, get_query_var('paged') ? intval(get_query_var('paged')) : 1);
$offset = ($current_page - 1) * $per_page;

$items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s) AND status = 'not_received'
    ORDER BY date_added DESC
    LIMIT %d OFFSET %d",
    $current_user->ID,
    $current_user->user_login,
    $per_page,
    $offset
));

$total_items = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s) AND status = 'not_received'",
    $current_user->ID,
    $current_user->user_login
));
$total_pages = ceil($total_items / $per_page);

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
            <p class="ybd-eyebrow">Stage 1 of 5 &middot; Expected arrivals</p>
            <h2>Awaiting arrival</h2>
            <p class="ybd-sub">Tracking numbers registered and awaiting warehouse arrival in China.</p>
        </div>
        <span class="ybd-count ybd-count-gold">Pending arrival: <?php echo esc_html(number_format($total_items)); ?></span>
    </div>

    <div class="ybd-panel ybd-panel-gold">
        <div class="ybd-panelhead">
            <span class="ybd-hint"><i class="fas fa-list"></i>Expected incoming parcels</span>
            <a href="<?php echo esc_url(home_url('/add-tracking/')); ?>" class="ybd-btn ybd-btn-gold">
                <i class="fas fa-plus"></i>Add new tracking
            </a>
        </div>
        <?php if ($items): ?>
            <div class="ybd-tablewrap">
                <table class="ybd-manifest">
                    <thead>
                        <tr>
                            <th>Tracking number</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th style="text-align:right;">Date added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span></td>
                                <td><span class="ybd-desc"><?php echo esc_html($item->description ?: 'No description'); ?></span></td>
                                <td>
                                    <span class="ybd-chip ybd-chip-not_received"><i class="fas fa-clock"></i>In transit to warehouse</span>
                                </td>
                                <td class="ybd-date">
                                    <strong><?php echo esc_html(date('M j, Y', strtotime($item->date_added))); ?></strong>
                                    <small><?php echo esc_html(date('h:i A', strtotime($item->date_added))); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="ybd-empty">
                <div class="ybd-emptyicon"><i class="fas fa-clock"></i></div>
                <h4>No pending incoming items</h4>
                <p>Add tracking numbers for your supplier packages so our warehouse team can instantly recognize them when they arrive.</p>
                <a href="<?php echo esc_url(home_url('/add-tracking/')); ?>" class="ybd-btn ybd-btn-emerald"><i class="fas fa-plus-circle"></i>Add tracking number</a>
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
