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
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);
?>

<div class="ybt-dashboard-wrapper animate-fade-in-up">
    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-clock" style="color: #d4af37;"></i> Awaiting Arrival
                </h2>
                <p class="text-muted small mb-0">Tracking numbers registered and awaiting warehouse arrival in China</p>
            </div>
            <div class="badge bg-warning bg-opacity-10 text-dark rounded-pill px-4 py-2 fs-6" style="border: 1px solid rgba(212, 175, 55, 0.3);">
                <i class="fas fa-hourglass-half me-2" style="color: #d4af37;"></i>Pending Arrival: <?php echo esc_html(number_format($total_items)); ?>
            </div>
        </div>

        <div class="glass-card glass-card-gold">
            <div class="info-card-header bg-white border-bottom p-3.5 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-bold text-secondary"><i class="fas fa-list me-2" style="color: #10b981;"></i>Expected Incoming Parcels</span>
                <a href="<?php echo esc_url(home_url('/add-tracking/')); ?>" class="btn btn-premium-primary rounded-pill px-4">
                    <i class="fas fa-plus me-1"></i> Add New Tracking
                </a>
            </div>
            <div class="info-card-body p-0">
                <?php if ($items): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light text-secondary small text-uppercase" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th class="ps-4 py-3">Tracking Number</th>
                                    <th class="py-3">Description</th>
                                    <th class="py-3">Status</th>
                                    <th class="text-end pe-4 py-3">Date Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold fs-6" style="color: #047857; font-family: monospace; letter-spacing: 0.5px;"><?php echo esc_html($item->tracking_number); ?></div>
                                        </td>
                                        <td>
                                            <div class="text-dark fw-medium"><?php echo esc_html($item->description ?: 'No description'); ?></div>
                                        </td>
                                        <td>
                                            <span class="status-pill status-pill-awaiting">
                                                <i class="fas fa-clock me-1"></i>In Transit to Warehouse
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="text-dark fw-semibold"><?php echo esc_html(date('M j, Y', strtotime($item->date_added))); ?></div>
                                            <div class="text-muted small"><?php echo esc_html(date('h:i A', strtotime($item->date_added))); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <div class="rounded-circle bg-warning bg-opacity-10 text-dark p-4 d-inline-flex mb-3">
                            <i class="fas fa-clock fa-3x" style="color: #d4af37;"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">No Pending Incoming Items</h4>
                        <p class="text-muted max-w-md mx-auto">Add tracking numbers for your supplier packages so our warehouse team can instantly recognize them when they arrive.</p>
                        <a href="<?php echo esc_url(home_url('/add-tracking/')); ?>" class="btn btn-premium-primary rounded-pill mt-2">
                            <i class="fas fa-plus-circle me-2"></i>Add Tracking Number
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
