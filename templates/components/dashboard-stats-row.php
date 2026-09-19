<?php
if (!defined('ABSPATH')) exit;

/**
 * Render the global stats horizontal row for user templates
 */
function render_dashboard_stats_row($current_user) {
    global $wpdb;
    $user_id = $current_user->ID;
    $login = $current_user->user_login;

    // Fetch counts
    $not_received = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'not_received'", $user_id, $login
    ));
    $received = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'received'", $user_id, $login
    ));
    $packing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'packing_in_progress'", $user_id, $login
    ));
    $packed = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'packed'", $user_id, $login
    ));
    $sent = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'completed'", $user_id, $login
    ));

    $stats = [
        ['count' => $not_received, 'label' => 'Awaiting', 'icon' => 'fa-clock', 'color' => '#fcc419', 'url' => home_url('/not-received/')],
        ['count' => $received, 'label' => 'Warehouse', 'icon' => 'fa-warehouse', 'color' => '#22c55e', 'url' => home_url('/received/')],
        ['count' => $packing, 'label' => 'Packing', 'icon' => 'fa-box-open', 'color' => '#f59e0b', 'url' => home_url('/packing-in-progress/')],
        ['count' => $packed, 'label' => 'Packed', 'icon' => 'fa-box', 'color' => '#3b82f6', 'url' => home_url('/packed/')],
        ['count' => $sent, 'label' => 'SENT', 'icon' => 'fa-check-double', 'color' => '#a855f7', 'url' => home_url('/completed/')]
    ];
    ?>
    <div class="ybt-stats-horizontal-row mb-5">
        <?php foreach ($stats as $stat): ?>
            <div class="ybt-stat-col-5th">
                <a href="<?php echo esc_url($stat['url']); ?>" class="text-decoration-none">
                    <div class="ybt-premium-stat clickable-stat">
                        <i class="fas <?php echo esc_attr($stat['icon']); ?> mb-3" style="color: <?php echo esc_attr($stat['color']); ?>; font-size: 1.8rem;"></i>
                        <div class="ybt-stat-number"><?php echo esc_html(number_format($stat['count'])); ?></div>
                        <div class="ybt-stat-label-caps"><?php echo esc_html($stat['label']); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
