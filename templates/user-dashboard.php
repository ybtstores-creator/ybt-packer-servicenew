<?php
/**
 * YBT User Dashboard - Optimized Version
 * Shows tracking stats, announcements, warehouse, payments, and recent activity
 *
 * Performance optimizations:
 * - Consolidated database queries
 * - Transient caching for stats
 * - Lazy loading for images
 */

if (!defined('ABSPATH')) exit;

// NOTE: Authentication redirect is handled by YBT_Core::handle_authentication_redirects()
// via 'template_redirect' hook at priority 5. Do NOT add wp_redirect() here.

global $wpdb;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$username = $current_user->user_login;
$custom_user_id = get_user_meta($user_id, 'ybt_custom_user_id', true) ?: 'Pending';

// App Update Check (cached by WordPress options)
$app_latest_version  = get_option('ybt_app_latest_version', '');
$app_download_url    = get_option('ybt_app_download_url', '');
$app_update_message  = get_option('ybt_app_update_message', '');

// ====== OPTIMIZED DATA FETCHING ======
// Single consolidated query instead of 6 separate queries
$cache_key = 'ybt_user_stats_' . $user_id . '_' . wp_hash($username);
$user_stats = ybt_cache()->remember($cache_key, function() use ($wpdb, $user_id, $username) {
    return $wpdb->get_row($wpdb->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'not_received' THEN 1 ELSE 0 END) as not_received,
            SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received,
            SUM(CASE WHEN status = 'packing_in_progress' THEN 1 ELSE 0 END) as packing_in_progress,
            SUM(CASE WHEN status = 'packed' THEN 1 ELSE 0 END) as packed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM {$wpdb->prefix}ybt_tracking
        WHERE user_id = %d OR username = %s
    ", $user_id, $username), ARRAY_A);
}, 300, 'user_dashboard'); // Cache for 5 minutes

// Extract stats with fallbacks
$stats = $user_stats ?: array_fill_keys(['total', 'not_received', 'received', 'packing_in_progress', 'packed', 'completed'], 0);
$not_received = (int) ($stats['not_received'] ?? 0);
$received = (int) ($stats['received'] ?? 0);
$packing_in_progress = (int) ($stats['packing_in_progress'] ?? 0);
$packed = (int) ($stats['packed'] ?? 0);
$completed = (int) ($stats['completed'] ?? 0);

$warehouse_settings = ybt_cache()->remember('ybt_warehouse_settings_latest', function() use ($wpdb) {
    return $wpdb->get_row("
        SELECT * FROM {$wpdb->prefix}ybt_warehouse_settings
        WHERE is_active = 1
        ORDER BY date_created DESC LIMIT 1
    ");
}, 3600, 'settings');

$payment_settings = ybt_cache()->remember('ybt_payment_settings', function() use ($wpdb) {
    return $wpdb->get_row("
        SELECT * FROM {$wpdb->prefix}ybt_payment_settings
        WHERE is_active = 1 ORDER BY date_created DESC LIMIT 1
    ");
}, 3600, 'settings');

// Admin Messages (cached for 10 minutes)
$admin_messages = ybt_cache()->remember('ybt_admin_messages_latest', function() use ($wpdb) {
    return $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}ybt_admin_messages
        ORDER BY date_created DESC LIMIT 3
    ");
}, 600, 'content');

// Recent Activity (cached for 2 minutes, per user)
$recent_activity = ybt_cache()->remember('ybt_user_recent_' . $user_id, function() use ($wpdb, $user_id, $username) {
    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ybt_tracking
        WHERE (user_id = %d OR username = %s)
        ORDER BY date_added DESC
        LIMIT 5
    ", $user_id, $username));
}, 120, 'user_dashboard');

// Add inline CSS to ensure clean top
add_action('wp_head', function() {
    echo '<style>
        /* Completely hide WordPress admin bar */
        #wpadminbar { 
            display: none !important; 
        }
        
        /* Remove WordPress admin bar margins from body */
        body.admin-bar {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        html {
            margin-top: 0 !important;
        }
    </style>';
    
    // Add CSP meta tag to allow CDN resources
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);
?>

<div class="ybt-dashboard-enhanced animate-up">
<div class="container" style="padding-top: 1rem !important;">

  <!-- ====== APP UPDATE BANNER ====== -->
  <?php if (!empty($app_latest_version) && !empty($app_download_url)): ?>
  <div class="ybt-update-banner mb-4" id="ybtUpdateBanner">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="ybt-update-icon"><i class="fas fa-mobile-alt"></i></div>
      <div class="flex-grow-1">
        <strong>App Update Available &mdash; v<?php echo esc_html($app_latest_version); ?></strong>
        <?php if (!empty($app_update_message)): ?>
          <div class="ybt-update-msg"><?php echo esc_html($app_update_message); ?></div>
        <?php endif; ?>
      </div>
      <a href="<?php echo esc_url($app_download_url); ?>" class="btn btn-sm ybt-update-btn" download>
        <i class="fas fa-download me-1"></i> Download Update
      </a>
      <button class="btn btn-sm btn-link ybt-update-close" onclick="document.getElementById('ybtUpdateBanner').style.display='none'" title="Dismiss">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>
  <?php endif; ?>

  <!-- ====== HEADER & QUICK SEARCH ====== -->
  <div class="mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
      <div class="d-flex align-items-center gap-3">
        <div class="ybt-floating-icon" style="font-size: 3rem; color: #10b981;"><i class="fas fa-rocket"></i></div>
        <div>
          <h1 class="mb-1" style="font-weight: 800; color: #1e293b;">MY DASHBOARD</h1>
          <p class="mb-0 text-muted">Tracking excellence for <?php echo esc_html($current_user->display_name); ?></p>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3 glass-card p-2" style="background: white;">
        <div class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2" style="font-size: 1rem; border: 1px solid #10b981;">
          ID: <?php echo esc_html($custom_user_id); ?>
        </div>
        <input type="text" id="quickSearch" class="form-control border-0 bg-transparent" placeholder="Search parcels..." style="width: 200px;">
      </div>
    </div>
    <div id="searchResults" class="glass-card mt-3 shadow-sm" style="display:none; border-radius: 12px; background:white;"></div>
  </div>

  <!-- ====== STATS CARDS - MODERN DESIGN ====== -->
  <div class="ybt-stats-grid-modern animate-fade-in-up">
    <!-- Total -->
    <div class="ybt-stat-card-modern">
      <div class="ybt-stat-icon-modern all"><i class="fas fa-boxes"></i></div>
      <div class="ybt-stat-value-modern"><?php echo esc_html($stats['total'] ?? 0); ?></div>
      <div class="ybt-stat-label-modern">Total Parcels</div>
    </div>
    
    <!-- Not Received -->
    <div class="ybt-stat-card-modern">
      <div class="ybt-stat-icon-modern pending"><i class="fas fa-clock"></i></div>
      <div class="ybt-stat-value-modern"><?php echo esc_html($not_received); ?></div>
      <div class="ybt-stat-label-modern">Not Received</div>
    </div>
    
    <!-- Received -->
    <div class="ybt-stat-card-modern">
      <div class="ybt-stat-icon-modern received"><i class="fas fa-warehouse"></i></div>
      <div class="ybt-stat-value-modern"><?php echo esc_html($received); ?></div>
      <div class="ybt-stat-label-modern">Received</div>
    </div>
    
    <!-- Packing In Progress -->
    <div class="ybt-stat-card-modern">
      <div class="ybt-stat-icon-modern packing"><i class="fas fa-box-open"></i></div>
      <div class="ybt-stat-value-modern"><?php echo esc_html($packing_in_progress); ?></div>
      <div class="ybt-stat-label-modern">Packing</div>
    </div>
    
    <!-- Packed -->
    <div class="ybt-stat-card-modern">
      <div class="ybt-stat-icon-modern packed"><i class="fas fa-box"></i></div>
      <div class="ybt-stat-value-modern"><?php echo esc_html($packed); ?></div>
      <div class="ybt-stat-label-modern">Packed</div>
    </div>
    
    <!-- Completed -->
    <div class="ybt-stat-card-modern">
      <div class="ybt-stat-icon-modern completed"><i class="fas fa-check-circle"></i></div>
      <div class="ybt-stat-value-modern"><?php echo esc_html($completed); ?></div>
      <div class="ybt-stat-label-modern">Completed</div>
    </div>
  </div>

  <!-- ====== ANNOUNCEMENTS ====== -->
  <?php if (!empty($admin_messages)): ?>
  <div class="mb-5">
    <div class="d-flex align-items-center gap-2 mb-3">
      <i class="fas fa-bullhorn text-primary fa-lg"></i>
      <h5 class="mb-0 fw-bold">Global Announcements</h5>
    </div>
    <?php foreach ($admin_messages as $msg):
      $msg->message = nl2br(esc_html($msg->message));
      $msg_date = date('M j, Y g:i A', strtotime($msg->date_created));
    ?>
      <div class="ybt-glass-card border-start border-4 border-primary" style="background: #ffffff; padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);">
        <div class="d-flex justify-content-between mb-2">
          <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3">Official Broadcast</span>
          <small class="text-muted"><?php echo $msg_date; ?></small>
        </div>
        <?php if (!empty($msg->image_url)): ?>
          <img src="<?php echo esc_url($msg->image_url); ?>"
               alt="Announcement image"
               loading="lazy"
               decoding="async"
               style="max-width:150px; float:right; margin-left:1rem; border-radius:8px;"
               onclick="window.openImageFullscreen('<?php echo esc_url($msg->image_url); ?>')" />
        <?php endif; ?>
        <p style="color: #334155; line-height:1.6; margin-bottom:1rem;"><?php echo $msg->message; ?></p>
        <?php if (!empty($msg->video_url)): ?>
          <a href="<?php echo esc_url($msg->video_url); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Attachment</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ====== JOURNEY PROGRESS ====== -->
  <div class="ybt-glass-card mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5><i class="fas fa-microchip me-2" style="color: var(--neo-green);"></i>System Pulse</h5>
      <div class="d-flex align-items-center gap-2"><span class="badge bg-success bg-opacity-10 text-success small">Live</span><div class="ybt-pulse-dot"></div></div>
    </div>
    <div class="journey-container">
      <?php
      $journey_steps = [
        ['label' => 'Awaiting', 'icon' => 'fa-clock', 'count' => $not_received, 'active' => $not_received > 0, 'color' => 'var(--neo-gold)'],
        ['label' => 'Warehouse', 'icon' => 'fa-warehouse', 'count' => $received, 'active' => $received > 0, 'color' => 'var(--neo-green)'],
        ['label' => 'Packing', 'icon' => 'fa-box-open', 'count' => $packed, 'active' => $packed > 0, 'color' => '#60a5fa'],
        ['label' => 'SENT', 'icon' => 'fa-flag-checkered', 'count' => $completed, 'active' => $completed > 0, 'color' => '#a78bfa']
      ];
      foreach ($journey_steps as $step):
        $active = $step['active'] ? 'active' : '';
        $count = $step['count'] > 0 ? '<span class="journey-count-badge">' . $step['count'] . '</span>' : '';
      ?>
        <div class="journey-step <?php echo $active; ?>">
          <div class="journey-blob" style="border-color: <?php echo $step['color']; ?>;">
            <i class="fas <?php echo esc_attr($step['icon']); ?>"></i>
            <?php echo $count; ?>
          </div>
          <div class="journey-step-label"><?php echo esc_html($step['label']); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center text-muted mt-4"><small>Data synced as of <?php echo date('H:i'); ?></small></div>
  </div>

  <!-- ====== WAREHOUSE & PAYMENT - MODERN DESIGN ====== -->
  <div class="row g-4">
    <div class="col-12 col-lg-6">
      <div class="ybt-warehouse-card-modern h-100 animate-fade-in-up">
        <div class="ybt-warehouse-title-modern">
          <i class="fas fa-plane-departure" style="color: #10b981;"></i>
          <span>Consolidation Hub</span>
        </div>
        <?php if ($warehouse_settings && !empty($warehouse_settings->address)):
          $phone = esc_html($warehouse_settings->phone ?: 'N/A');
          $hours = esc_html($warehouse_settings->hours ?: 'N/A');
          $addr = ybt_smart_format_address($warehouse_settings->address, $custom_user_id);
          $clipboard_text = htmlspecialchars($addr . "\nPhone: " . $phone, ENT_QUOTES, 'UTF-8');
        ?>
          <div class="ybt-address-copy-card-modern"
               id="warehouseAddressCard"
               data-clipboard-text="<?php echo $clipboard_text; ?>"
               title="Click to copy address"
               role="button"
               tabindex="0">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <span class="ybt-status-badge-modern completed">
                <i class="fas fa-check-circle"></i> Active Warehouse
              </span>
              <span class="ybt-copy-hint-modern">
                <i class="far fa-copy"></i>
                <span class="copy-hint-txt">Click to copy</span>
              </span>
            </div>
            <div class="ybt-warehouse-address-modern">
              <?php echo nl2br(esc_html($addr)); ?>
            </div>
            <div class="mt-3 d-flex gap-3 flex-wrap">
              <small class="d-flex align-items-center gap-2">
                <i class="fas fa-phone" style="color: #10b981;"></i>
                <?php echo $phone; ?>
              </small>
              <small class="d-flex align-items-center gap-2">
                <i class="far fa-clock" style="color: #10b981;"></i>
                <?php echo $hours; ?>
              </small>
            </div>
          </div>
        <?php else: ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-warehouse fa-3x mb-3 opacity-25"></i>
            <p>No warehouse set</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="col-12 col-lg-6">
      <div class="ybt-payment-card-modern h-100 animate-fade-in-up">
        <div class="ybt-warehouse-title-modern">
          <i class="fas fa-wallet" style="color: #d4af37;"></i>
          <span>Settlement Protocols</span>
        </div>
        <?php if ($payment_settings):
          $bank = esc_html($payment_settings->bank_name);
          $name = esc_html($payment_settings->account_name);
          $acc = esc_html($payment_settings->account_number);
          $qr = esc_url($payment_settings->qr_code_image ?: '');
        ?>
          <div class="d-flex flex-wrap gap-4">
            <div class="flex-grow-1">
              <div class="ybt-payment-detail-modern">
                <span class="ybt-payment-label-modern">Bank</span>
                <span class="ybt-payment-value-modern"><?php echo $bank; ?></span>
              </div>
              <div class="ybt-payment-detail-modern">
                <span class="ybt-payment-label-modern">Account</span>
                <span class="ybt-payment-value-modern"><?php echo $name; ?></span>
              </div>
              <div class="ybt-payment-detail-modern">
                <span class="ybt-payment-label-modern">Number</span>
                <div class="d-flex align-items-center gap-2">
                  <span class="ybt-payment-value-modern"><?php echo $acc; ?></span>
                  <button class="btn btn-sm ybt-btn-modern ybt-btn-gold-modern py-1 px-3 copy-btn" 
                          data-clipboard-text="<?php echo htmlspecialchars($acc, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-copy"></i>
                  </button>
                </div>
              </div>
            </div>
            <?php if ($qr): ?>
              <div class="text-center p-3" style="background: rgba(255, 248, 225, 0.5); border-radius: 12px;">
                <img src="<?php echo esc_url($qr); ?>"
                     alt="QR Code for payment"
                     loading="lazy"
                     decoding="async"
                     style="max-height:120px; border-radius:8px;" />
                <div class="small mt-2" style="color: #b59021; font-weight: 600;">Scan to Pay</div>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-credit-card fa-3x mb-3 opacity-25"></i>
            <p>No payment configured</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ====== RECENT ACTIVITY ====== -->
  <div class="ybt-glass-card mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4 p-3 border-bottom">
      <h5><i class="fas fa-history me-2" style="color: var(--neo-green);"></i>Operational History</h5>
      <a href="<?php echo esc_url(home_url('/tracking/received')); ?>" class="btn btn-sm btn-outline-primary">Audit All</a>
    </div>
    <?php if (!empty($recent_activity)): ?>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead style="background:#f8fafc;"><tr><th>Tracking #</th><th>Status</th><th>Date</th><th>View</th></tr></thead>
          <tbody>
            <?php
            $status_colors = [
                'not_received'       => ['bg' => 'secondary', 'text' => 'white'],
                'received'           => ['bg' => 'warning',   'text' => 'dark'],
                'packing_in_progress'=> ['bg' => 'primary',   'text' => 'white'],
                'packed'             => ['bg' => 'info',      'text' => 'dark'],
                'completed'          => ['bg' => 'success',   'text' => 'white'],
            ];
            foreach ($recent_activity as $item):
              $status   = $item->status;
              $colors   = $status_colors[$status] ?? ['bg' => 'secondary', 'text' => 'white'];
              $bg_class = $colors['bg'];
              $tx_class = $colors['text'];
              $text     = ucwords(str_replace('_', ' ', $status));
              $date     = $item->date_added ? date('M j, Y', strtotime($item->date_added)) : '-';
            ?>
              <tr>
                <td><strong><?php echo esc_html($item->tracking_number); ?></strong></td>
                <td><span class="badge bg-<?php echo $bg_class; ?> text-<?php echo $tx_class; ?> px-2 py-1 rounded"><?php echo esc_html($text); ?></span></td>
                <td><?php echo esc_html($date); ?></td>
                <td>
                    <button type="button"
                            class="btn btn-sm btn-light rounded-circle view-parcel-details"
                            data-parcel='<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8"); ?>'
                            data-status-text="<?php echo esc_attr($text); ?>"
                            data-bg-class="<?php echo esc_attr($bg_class); ?>"
                            data-tx-class="<?php echo esc_attr($tx_class); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="p-5 text-center text-muted"><i class="fas fa-inbox fa-3x mb-3 opacity-25"></i><p>No recent activity</p></div>
    <?php endif; ?>
  </div>

</div>
</div>

<!-- ====== IMAGE FULLSCREEN MODAL (INLINE FOR SIMPLICITY) ====== -->
<script>
function openImageFullscreen(src) {
  if (!src || !/\.(jpg|jpeg|png|gif|webp)$/i.test(src)) {
    alert('Invalid image');
    return;
  }
  const modal = $('<div>').css({
    position: 'fixed',
    top: 0,
    left: 0,
    width: '100%',
    height: '100%',
    background: 'rgba(0,0,0,0.9)',
    zIndex: 99999,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center'
  });
  const modalContent = $('<div>').css({
    maxWidth: '90%',
    maxHeight: '90%',
    position: 'relative'
  });
  const img = $('<img>').attr('src', src).css({
    maxWidth: '100%',
    maxHeight: '100%'
  });
  const closeButton = $('<button>').html('&times;').css({
    position: 'absolute',
    top: '10px',
    right: '10px',
    background: 'none',
    border: 'none',
    color: 'white',
    fontSize: '24px',
    cursor: 'pointer'
  }).click(function() {
    modal.remove();
  });
  modalContent.append(img);
  modalContent.append(closeButton);
  modal.append(modalContent);
  $('body').append(modal);
}
</script>

<!-- ====== COPY TOAST ====== -->
<div id="ybtCopyToast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);background:#1e293b;color:#fff;padding:10px 22px;border-radius:30px;font-size:0.9rem;z-index:99999;opacity:0;pointer-events:none;transition:all 0.3s ease;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
  <i class="fas fa-check-circle me-2" style="color:#10b981;"></i> Address copied!
</div>

<!-- ====== COPY FUNCTIONS ====== -->
<style>
.ybt-address-copy-card-modern {
  background: linear-gradient(135deg,rgba(16,185,129,0.06),rgba(16,185,129,0.02));
  border: 2px dashed rgba(16,185,129,0.25);
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;
  position: relative;
  overflow: hidden;
}
.ybt-address-copy-card-modern:hover {
  border-color: #10b981;
  background: white;
  transform: translateY(-4px);
  box-shadow: 0 12px 30px rgba(16,185,129,0.15);
}
.ybt-address-copy-card-modern:active { transform: scale(0.98); }
.ybt-copy-hint-modern {
  font-size: 0.8rem;
  color: #10b981;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
}
.ybt-address-copy-card-modern:hover .ybt-copy-hint-modern { color: #059669; }
.ybt-address-copy-card:active { transform: scale(0.98); }
.ybt-copy-hint {
  font-size: 0.78rem;
  color: #10b981;
  opacity: 0.7;
  transition: opacity 0.2s;
}
.ybt-address-copy-card:hover .ybt-copy-hint { opacity: 1; }
.ybt-addr-pre {
  white-space: pre-wrap;
  margin: 0;
  font-family: inherit;
  font-size: 0.95rem;
  color: #1e293b;
  pointer-events: none;
}
.ybt-update-banner {
  background: linear-gradient(135deg,#1e3a5f,#1a5c3a);
  color: #fff;
  border-radius: 14px;
  padding: 1rem 1.3rem;
  box-shadow: 0 4px 20px rgba(16,185,129,0.2);
  border: 1px solid rgba(16,185,129,0.3);
}
.ybt-update-icon {
  font-size: 1.8rem;
  color: #10b981;
  background: rgba(16,185,129,0.15);
  border-radius: 50%;
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ybt-update-msg { font-size: 0.85rem; opacity: 0.85; margin-top: 2px; }
.ybt-update-btn {
  background: #10b981;
  color: #fff;
  border: none;
  padding: 8px 18px;
  border-radius: 8px;
  font-weight: 600;
  white-space: nowrap;
}
.ybt-update-btn:hover { background: #059669; color: #fff; }
.ybt-update-close { color: rgba(255,255,255,0.6); }
.ybt-update-close:hover { color: #fff; }
</style>
<script>
function ybtShowCopyToast(msg) {
  const t = document.getElementById('ybtCopyToast');
  if (!t) return;
  
  // Update content
  t.innerHTML = '<i class="fas fa-check-circle me-2" style="color:#10b981;"></i> ' + (msg || 'Copied!');
  
  // Show toast
  t.style.opacity = '1';
  t.style.pointerEvents = 'auto';
  t.style.transform = 'translateX(-50%) translateY(0)';
  
  // Auto-hide
  clearTimeout(t._timer);
  t._timer = setTimeout(() => {
    t.style.opacity = '0';
    t.style.pointerEvents = 'none';
    t.style.transform = 'translateX(-50%) translateY(20px)';
  }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
  console.log('Dashboard script loaded');

  // Fallback copy function for buttons
  var copyBtnFallback = function(btn, text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      ybtShowCopyToast('Copied!');
      var orig = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check text-success"></i>';
      setTimeout(function() { btn.innerHTML = orig; }, 2000);
    } catch(e) {
      alert('Copy failed. Please copy manually.');
    }
    document.body.removeChild(ta);
  };

  // Fallback copy function for address card
  var copyWithFallback = function(text, hint) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    ta.style.top = '0';
    ta.style.left = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      var successful = document.execCommand('copy');
      if (successful) {
        ybtShowCopyToast('Address copied!');
        if (hint) { hint.textContent = 'Copied!'; setTimeout(function() { hint.textContent = 'Click to copy'; }, 2000); }
      } else {
        alert('Copy failed. Please select and copy manually.');
      }
    } catch(e) {
      console.error('execCommand failed:', e);
      alert('Copy not supported. Please select and copy manually.');
    }
    document.body.removeChild(ta);
  };

  // ---- Warehouse address card: click entire card to copy ----
  var addrCard = document.getElementById('warehouseAddressCard');
  if (addrCard) {
    addrCard.addEventListener('click', function() {
      var text = this.getAttribute('data-clipboard-text');
      var hint = this.querySelector('.copy-hint-txt');
      
      if (!text) return;

      // Decode entities
      var decoder = document.createElement('textarea');
      decoder.innerHTML = text;
      text = decoder.value;

      var performCopy = function(txt) {
          if (navigator.clipboard && navigator.clipboard.writeText) {
              return navigator.clipboard.writeText(txt);
          } else {
              return new Promise(function(resolve, reject) {
                  var textArea = document.createElement("textarea");
                  textArea.value = txt;
                  textArea.style.position = "fixed";
                  textArea.style.left = "-999999px";
                  textArea.style.top = "-999999px";
                  document.body.appendChild(textArea);
                  textArea.focus();
                  textArea.select();
                  try {
                      var successful = document.execCommand('copy');
                      document.body.removeChild(textArea);
                      if (successful) resolve(); else reject();
                  } catch (err) {
                      document.body.removeChild(textArea);
                      reject(err);
                  }
              });
          }
      };

      performCopy(text).then(function() {
          ybtShowCopyToast('Address copied to clipboard!');
          if (hint) {
              var originalText = hint.textContent;
              hint.textContent = 'Copied!';
              hint.style.color = '#059669';
              setTimeout(function() {
                  hint.textContent = originalText;
                  hint.style.color = '';
              }, 2500);
          }
      }).catch(function(err) {
          console.error('Copy failed:', err);
          alert('Could not copy automatically. Please select the text and copy manually.');
      });
    });

    addrCard.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });
  }

  // ---- Generic copy buttons (e.g. account number) - Use event delegation ----
  document.addEventListener('click', function(e) {
    if (e.target.closest('.copy-btn')) {
      var btn = e.target.closest('.copy-btn');
      var text = btn.dataset.clipboardText;

      // Decode HTML entities
      if (text) {
        var txt = document.createElement('textarea');
        txt.innerHTML = text;
        text = txt.value;
      }

      if (!text) return;

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
          ybtShowCopyToast('Copied!');
          var orig = btn.innerHTML;
          btn.innerHTML = '<i class="fas fa-check text-success"></i>';
          setTimeout(function() { btn.innerHTML = orig; }, 2000);
        }).catch(function() {
          copyBtnFallback(btn, text);
        });
      } else {
        copyBtnFallback(btn, text);
      }
    }
  });

  // Quick Search (Basic, AJAX recommended to be moved to external JS)
  var timeout;
  var quickSearchInput = document.getElementById('quickSearch');
  if (quickSearchInput) {
    quickSearchInput.addEventListener('input', function() {
      clearTimeout(timeout);
      var q = this.value.trim();
      if (q.length < 3) {
        var searchResults = document.getElementById('searchResults');
        if (searchResults) { searchResults.style.display = 'none'; }
        return;
      }
      timeout = setTimeout(function() {
        console.log('Search for:', q);
      }, 500);
    });
  }
});
</script>

</div> <!-- ybt-dashboard-enhanced -->

<!-- ====== PARCEL DETAILS MODAL ====== -->
<div id="parcelDetailsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card p-4 mx-3" style="width: 100%; max-width: 600px; background: #fff; border-radius: 16px; position: relative; max-height: 90vh; overflow-y: auto;">
        <button onclick="jQuery('#parcelDetailsModal').fadeOut(200)" style="position:absolute; top:15px; right:15px; background:none; border:none; color:#64748b; font-size:24px; cursor:pointer;">&times;</button>

        <div class="modal-header-custom mb-4 pb-3 border-bottom">
            <h4 class="fw-bold mb-1" id="m-tracking-number">Tracking Details</h4>
            <span id="m-status-badge" class="badge rounded-pill px-3 py-2"></span>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <label class="text-muted small text-uppercase fw-bold d-block mb-1">Description</label>
                <div id="m-description" class="fw-medium text-dark">-</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small text-uppercase fw-bold d-block mb-1">Items count</label>
                <div id="m-count" class="fw-medium text-dark">-</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small text-uppercase fw-bold d-block mb-1">Registered Date</label>
                <div id="m-date-added" class="fw-medium text-dark">-</div>
            </div>
            <div class="col-md-6" id="m-date-received-container">
                <label class="text-muted small text-uppercase fw-bold d-block mb-1">Received Date</label>
                <div id="m-date-received" class="fw-medium text-dark">-</div>
            </div>
        </div>

        <div id="m-images-container" class="mt-4 pt-3 border-top">
            <label class="text-muted small text-uppercase fw-bold d-block mb-2">Parcel Images</label>
            <div id="m-images" class="d-flex flex-wrap gap-2"></div>
        </div>

        <div class="d-grid mt-4 pt-2">
            <button type="button" class="btn btn-light" onclick="jQuery('#parcelDetailsModal').fadeOut(200)">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var $ = jQuery;

  // Handle Parcel Details Modal
  $('.view-parcel-details').on('click', function() {
    var data = $(this).data('parcel');
    var statusText = $(this).data('status-text');
    var bgClass = $(this).data('bg-class');
    var txClass = $(this).data('tx-class');

    $('#m-tracking-number').text(data.tracking_number);
    $('#m-status-badge').text(statusText)
      .removeClassRegex(/^bg-/)
      .removeClassRegex(/^text-/)
      .addClass('bg-' + bgClass)
      .addClass('text-' + txClass);

    $('#m-description').text(data.description || 'No description');
    $('#m-count').text(data.item_count || '0');
    $('#m-date-added').text(data.date_added || '-');

    if (data.date_received) {
      $('#m-date-received-container').show();
      $('#m-date-received').text(data.date_received);
    } else {
      $('#m-date-received-container').hide();
    }

    // Handle Images
    var imagesHtml = $('#m-images');
    imagesHtml.empty();
    if (data.images) {
      var images = data.images.split(',');
      for (var i = 0; i < images.length; i++) {
        var trimmed = images[i].trim();
        if (trimmed) {
          imagesHtml.append('<img src="' + trimmed + '" class="rounded shadow-sm border" style="width:80px; height:80px; object-fit:cover; cursor:pointer;" onclick="openImageFullscreen(\'' + trimmed + '\')">');
        }
      }
      $('#m-images-container').show();
    } else {
      $('#m-images-container').hide();
    }

    $('#parcelDetailsModal').css('display', 'flex').hide().fadeIn(200);
  });

  // Helper to remove classes matching a regex
  $.fn.removeClassRegex = function(regex) {
    return this.each(function() {
      var el = $(this);
      var classes = el.attr('class').split(/\s+/);
      var toRemove = [];
      for (var i = 0; i < classes.length; i++) {
        if (regex.test(classes[i])) {
          toRemove.push(classes[i]);
        }
      }
      el.removeClass(toRemove.join(' '));
    });
  };

  // Close modals on outside click
  $('#parcelDetailsModal, #image-viewer-modal').click(function(e) {
    if (e.target === this) $(this).fadeOut(200);
  });
});
</script>
