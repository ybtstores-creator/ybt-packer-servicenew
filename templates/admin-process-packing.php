<?php
/**
 * Process Packing Requests Template
 *
 * This template handles the administration of packing requests, allowing
 * warehouse staff to group shipments into batches and assign cartons.
 *
 * @package YBT_Packer
 * @version 2.2
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ybt-packer'));
}

// --- 1. DATA INITIALIZATION ---
$current_user_filter = isset($_GET['user_filter']) ? sanitize_text_field(wp_unslash($_GET['user_filter'])) : '';
$current_search      = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';

$warehouse_cache = YBT_Packing::get_warehouse_cache();

/*
 * Lazy group loading: the initial page render fetches only *metadata* (counts).
 * Group rows are fetched via AJAX (ybt_get_packing_group_rows) the first time
 * a group is expanded, so the initial DOM stays tiny no matter how large the
 * packing queue grows.
 */
$packing_result = YBT_Packing::get_filtered_requests($current_user_filter, $current_search, 0);
$packing_total  = (int) $packing_result['total'];

$group_counts        = YBT_Packing::get_group_counts($current_user_filter, $current_search);
$group_counts_total  = count($group_counts);

/* Two truncation layers keep the *headers* bounded too:
 *  - group headers: sublinear cap (log scale) so thousands of users stay sane;
 *  - rows per displayed group: capped server-side by the AJAX handler, and
 *    the handler's own count is what the header shows, so what you see is
 *    always what the per-group query can actually return. */
$max_visible_groups  = min($group_counts_total, (int) apply_filters('ybt_packing_max_visible_groups', 200));
$max_header_groups   = min(max(12, (int) ceil(log($group_counts_total + 1) / log(1.2))), $max_visible_groups);

$available_users     = YBT_Packing::get_active_usernames();
$batch_management_data = YBT_Packing::get_user_batches();
?>

<div class="wrap ybt-ledger ybt-admin-packing">
    <div class="ybt-ledgerhead">
        <div>
            <span class="ybd-eyebrow">Depot ledger · Packing tables</span>
            <h1>Process packing requests</h1>
            <p>Group parcels into batches, weigh cartons, and move consignments to packed.</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="ybd-panel ybd-panel-gold mb-4">
        <div class="ybd-panelhead">
             <span class="ybd-hint"><i class="fas fa-filter"></i>Filters</span>
        </div>
        <div class="ybd-panelbody">
            <form method="GET" class="filter-form d-flex gap-3 align-items-end flex-wrap">
                <input type="hidden" name="page" value="ybt-process-packing">

                <div class="filter-group">
                    <label class="ybd-formlabel"><?php esc_html_e('User', 'ybt-packer'); ?></label>
                    <select name="user_filter" class="ybd-input" style="min-width: 200px;">
                        <option value=""><?php esc_html_e('All Users', 'ybt-packer'); ?></option>
                        <?php foreach ($available_users as $user): ?>
                            <option value="<?php echo esc_attr($user->username); ?>" <?php selected($current_user_filter, $user->username); ?>>
                                <?php echo esc_html($user->username); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="ybd-formlabel"><?php esc_html_e('Search', 'ybt-packer'); ?></label>
                    <input type="text" name="search" class="ybd-input" value="<?php echo esc_attr($current_search); ?>" placeholder="<?php esc_attr_e('Tracking number…', 'ybt-packer'); ?>">
                </div>

                <div class="filter-actions d-flex gap-2">
                    <button type="submit" class="ybd-btn ybd-btn-gold"><?php esc_html_e('Filter', 'ybt-packer'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=ybt-process-packing'); ?>" class="ybd-btn ybd-btn-dark"><?php esc_html_e('Clear', 'ybt-packer'); ?></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Packing Requests -->
    <div class="ybd-panel">
        <div class="ybd-panelhead">
             <span class="ybd-hint"><i class="fas fa-list"></i><?php printf(esc_html__('Packing Requests (%d)', 'ybt-packer'), $packing_total); ?></span>
        </div>
        <div class="ybd-panelbody">
            <?php if ($group_counts): ?>
                <!-- Cross-Page Selection Indicator -->
                <div id="selection-indicator" style="display: none; background:var(--emerald-deep); color: white; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 3px 3px 0 rgba(15,31,23,.2);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-size: 20px;">📋</span>
                            <div>
                                <strong style="font-size: 15px;"><span id="selection-count">0</span> <?php esc_html_e('Item(s) Selected', 'ybt-packer'); ?></strong>
                                <div style="font-size: 12px; opacity: 0.85; font-family:var(--mono);"><?php esc_html_e('Selections persist across pages', 'ybt-packer'); ?></div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="ybd-btn" id="view-selected-items" style="background: white; color: var(--emerald-deep); border-color: white; box-shadow:none;">
                                👁️ <?php esc_html_e('View', 'ybt-packer'); ?>
                            </button>
                            <button class="ybd-btn" id="clear-all-selections" style="background: transparent; color: white; border: 1.5px solid rgba(255,255,255,0.5); box-shadow:none;">
                                🗑️ <?php esc_html_e('Clear', 'ybt-packer'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bulk-actions mb-4 p-3 d-flex align-items-center gap-2 flex-wrap" style="background:var(--kraft); border:1.5px solid var(--kraft-deep); border-radius:10px;">
                    <span class="fw-bold me-2 small text-uppercase" style="font-family:var(--mono); font-size:11px; letter-spacing:1px; color:var(--gold-deep);"><?php esc_html_e('Actions:', 'ybt-packer'); ?></span>
                    <button class="ybd-btn ybd-btn-emerald" id="bulk-complete-selected">
                        <i class="fas fa-check"></i><?php esc_html_e('Complete Selected', 'ybt-packer'); ?>
                    </button>
                    <button class="ybd-btn ybd-btn-gold" id="attach-cartons-selected">
                         <i class="fas fa-box"></i><?php esc_html_e('Attach Cartons (Selected)', 'ybt-packer'); ?>
                    </button>
                    <div class="ms-auto d-flex gap-2">
                         <button class="ybd-btn" id="select-all-page-items" style="padding:6px 12px; font-size:12.5px;"><?php esc_html_e('check all', 'ybt-packer'); ?></button>
                         <button class="ybd-btn" id="deselect-all-page-items" style="padding:6px 12px; font-size:12.5px;"><?php esc_html_e('none', 'ybt-packer'); ?></button>
                     </div>
                </div>

                <?php if ($group_counts_total > $max_header_groups) : ?>
                    <!-- Truncation Notice: bounded rendering keeps the page fast at scale -->
                    <div class="ybd-panel ybd-panel-gold mb-4" id="packing-truncation-notice">
                        <div class="ybd-panelbody d-flex align-items-center justify-content-between flex-wrap gap-2 py-3">
                            <span class="ybd-hint" style="font-size:13px;">
                                <i class="fas fa-triangle-exclamation"></i>
                                <?php printf(
                                    esc_html__('Showing the %1$d most active of %2$d users with packing requests (%3$d items total). Filter by user or search to reach the rest.', 'ybt-packer'),
                                    (int) $max_header_groups,
                                    (int) $group_counts_total,
                                    (int) $packing_total
                                ); ?>
                            </span>
                            <button type="button" class="ybd-btn" id="packing-load-filtered"
                                    style="padding:6px 12px; font-size:12.5px;">
                                <?php esc_html_e('How to load more', 'ybt-packer'); ?>
                            </button>
                        </div>
                    </div>
                    <script>
                    (function () {
                        var btn = document.getElementById('packing-load-filtered');
                        if (btn && !btn.addEventListener) return;
                        if (btn) btn.addEventListener('click', function () {
                            var f = document.querySelector('.ybt-admin-packing .filter-form');
                            if (f) { f.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                     var s = f.querySelector('input[name="search"]'); if (s) s.focus(); }
                        });
                    })();
                    </script>
                <?php endif; ?>

                <?php $rendered_group_count = 0; ?>
                <?php foreach ($group_counts as $username => $user_item_count): ?>
                    <?php if ($rendered_group_count >= $max_header_groups) { break; } ?>
                    <?php $rendered_group_count++; ?>
                    <!-- USER GROUP CONTAINER (header only; rows load via AJAX on first expand) -->
                    <div class="ybd-panel mb-4 user-request-container"
                         data-username="<?php echo esc_attr($username); ?>"
                         data-item-count="<?php echo esc_attr((string) (int) $user_item_count); ?>"
                         data-loaded="0">
                        <!-- User Header (Interactive) -->
                        <div class="user-group-header collapsed d-flex justify-content-between align-items-center p-3 cursor-pointer select-none" style="border-bottom:1.5px dashed var(--kraft-line); background:var(--kraft);">
                            <h3 class="user-group-title m-0 d-flex align-items-center" style="font-size:15px; font-weight:700; color:var(--ink);">
                                <i class="fas fa-chevron-down me-2 toggle-icon small" style="color:var(--gold-deep);"></i>
                                <span class="ybd-trackno"><?php echo esc_html($username); ?></span>
                                <span class="ybd-count ms-2" style="font-size:12px; padding:2px 10px;">
                                    <?php printf(esc_html__('%d items', 'ybt-packer'), (int) $user_item_count); ?>
                                </span>
                            </h3>
                            <div class="user-group-actions d-flex gap-2" onclick="event.stopPropagation();">
                                <?php
                                $air_pdf_url = admin_url('admin-ajax.php?action=ybt_download_user_packing_requests&user=' . urlencode($username) . '&method=air&nonce=' . wp_create_nonce('ybt_admin_nonce'));
                                $sea_pdf_url = admin_url('admin-ajax.php?action=ybt_download_user_packing_requests&user=' . urlencode($username) . '&method=sea&nonce=' . wp_create_nonce('ybt_admin_nonce'));
                                ?>
                                <a href="<?php echo esc_url($air_pdf_url); ?>" class="ybd-btn" style="padding:6px 12px; font-size:12.5px;">
                                    <i class="fas fa-plane"></i><?php esc_html_e('Air PDF', 'ybt-packer'); ?>
                                </a>
                                <a href="<?php echo esc_url($sea_pdf_url); ?>" class="ybd-btn" style="padding:6px 12px; font-size:12.5px;">
                                    <i class="fas fa-ship"></i><?php esc_html_e('Sea PDF', 'ybt-packer'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Items Table Container (rows fetched on first expand) -->
                        <div class="user-group-content" style="display: none;">
                            <div class="user-group-loading p-4 text-center text-muted">
                                <i class="fas fa-spinner fa-spin me-2"></i><?php esc_html_e('Loading items…', 'ybt-packer'); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <script>
                /* Persisted selections are restored here — rows are AJAX-loaded, so
                   the page-load restore pass in admin-packing.js finds no checkboxes. */
                (function () {
                    function restoreSelections() {
                        var selected;
                        try { selected = JSON.parse(localStorage.getItem('ybt_selected_items') || '[]'); } catch (e) { return; }
                        if (!selected || !selected.length) return;
                        document.querySelectorAll('.item-checkbox').forEach(function (cb) {
                            if (selected.indexOf(String(cb.value)) !== -1) cb.checked = true;
                        });
                    }
                    document.addEventListener('DOMContentLoaded', restoreSelections);
                    document.addEventListener('ybt_group_rows_loaded', restoreSelections);
                })();
                </script>
            <?php else: ?>
                <div class="p-5 text-center ybd-empty">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php esc_html_e('All caught up!', 'ybt-packer'); ?></h3>
                    <p><?php esc_html_e('No active packing requests found matching your criteria.', 'ybt-packer'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->

<!-- Image Upload Modal -->
<div id="packing-image-upload-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content">
        <h3><?php esc_html_e('Upload Packing Images', 'ybt-packer'); ?></h3>
        <form id="packing-image-upload-form" enctype="multipart/form-data">
            <input type="hidden" id="packing-upload-tracking-id">
            <div class="mb-3">
                <input type="file" id="packing-upload-images" multiple accept="image/*" class="form-control">
                <div class="form-text small text-muted"><?php esc_html_e('You can select multiple images.', 'ybt-packer'); ?></div>
            </div>
            <div class="ybt-modal-actions">
                <button type="button" class="button close-modal"><?php esc_html_e('Cancel', 'ybt-packer'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('Upload', 'ybt-packer'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Image Viewer Modal -->
<div id="packing-image-viewer-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 90%; max-height: 90%; width: 800px;">
        <h3>📷 <?php esc_html_e('Parcel Images', 'ybt-packer'); ?></h3>
        <div id="packing-image-gallery" style="max-height: 70vh; overflow-y: auto; text-align: center; padding: 20px 0;"></div>
        <div class="ybt-modal-actions">
            <button type="button" class="button close-modal"><?php esc_html_e('Close', 'ybt-packer'); ?></button>
        </div>
    </div>
</div>

<!-- Carton Details Modal -->
<div id="carton-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 600px; width: 90%;">
        <h3><?php esc_html_e('Carton Details', 'ybt-packer'); ?></h3>
        <div id="cartons-container" style="max-height: 60vh; overflow-y: auto;"></div>
        <div class="ybt-modal-actions">
            <button type="button" class="button close-modal"><?php esc_html_e('Close', 'ybt-packer'); ?></button>
        </div>
    </div>
</div>

<!-- Attach Cartons Modal -->
<div id="attach-cartons-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 700px;">
        <h3 style="margin-top:0;"><?php esc_html_e('Attach Cartons to Batch', 'ybt-packer'); ?></h3>
        <p class="ybt-modal-meta">
            <?php esc_html_e('Batch', 'ybt-packer'); ?> <strong id="attach-batch-label">—</strong>
        </p>
        <form id="attach-cartons-form" enctype="multipart/form-data">
            <input type="hidden" id="attach-batch-id">

            <div class="mb-3">
                <label for="attach-cartons-count" class="form-label fw-bold"><?php esc_html_e('Number of Cartons', 'ybt-packer'); ?></label>
                <div class="d-flex gap-2">
                    <input type="number" id="attach-cartons-count" min="1" max="50" value="1" required class="form-control" style="width: 100px;">
                    <button type="button" id="generate-carton-fields" class="button button-secondary"><?php esc_html_e('Generate Fields', 'ybt-packer'); ?></button>
                </div>
            </div>

            <div id="carton-fields-container" style="max-height:400px; overflow-y:auto; margin-bottom:15px; border: 1px solid #eee; padding: 10px; border-radius: 5px;"></div>

            <div class="ybt-modal-actions">
                <button type="button" class="button close-modal"><?php esc_html_e('Cancel', 'ybt-packer'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Cartons', 'ybt-packer'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Attach Cartons Selected Modal -->
<div id="attach-cartons-selected-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 700px;">
        <h3 style="margin-top:0;"><?php esc_html_e('Attach Cartons to Selected', 'ybt-packer'); ?></h3>
        <p class="ybt-modal-meta">
            <strong id="attach-selected-count">0</strong> <?php esc_html_e('tracking number(s) selected.', 'ybt-packer'); ?>
        </p>
        <form id="attach-cartons-selected-form" enctype="multipart/form-data">
            <input type="hidden" id="attach-selected-ids">

            <div class="mb-3">
                <label for="attach-selected-cartons-count" class="form-label fw-bold"><?php esc_html_e('Number of Cartons', 'ybt-packer'); ?></label>
                <div class="d-flex gap-2">
                    <input type="number" id="attach-selected-cartons-count" min="1" max="50" value="1" required class="form-control" style="width: 100px;">
                    <button type="button" id="generate-selected-carton-fields" class="button button-secondary"><?php esc_html_e('Generate Fields', 'ybt-packer'); ?></button>
                </div>
            </div>

            <div id="selected-carton-fields-container" style="max-height:400px; overflow-y:auto; margin-bottom:15px; border: 1px solid #eee; padding: 10px; border-radius: 5px;"></div>

            <div class="ybt-modal-actions">
                <button type="button" class="button close-modal"><?php esc_html_e('Cancel', 'ybt-packer'); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Cartons', 'ybt-packer'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- View Selected Items Modal -->
<div id="view-selected-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 600px;">
        <h3 style="margin-top:0;">📋 <?php esc_html_e('Selected Tracking Numbers', 'ybt-packer'); ?></h3>
        <div id="selected-items-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 15px;">
            <p style="text-align: center; color: #666;"><?php esc_html_e('Loading...', 'ybt-packer'); ?></p>
        </div>
        <div class="ybt-modal-actions">
            <button type="button" class="button close-modal"><?php esc_html_e('Close', 'ybt-packer'); ?></button>
        </div>
    </div>
</div>

<!-- View Tracking Details Modal -->
<div id="view-tracking-details-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 800px;">
        <h3 style="margin-top:0;">📦 <?php esc_html_e('Tracking Details', 'ybt-packer'); ?></h3>
        <div id="tracking-details-content" style="max-height: 70vh; overflow-y: auto;">
            <div class="loading-spinner" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                <p class="text-muted mt-2"><?php esc_html_e('Loading details...', 'ybt-packer'); ?></p>
            </div>
        </div>
        <div class="ybt-modal-actions">
            <button type="button" class="button close-modal"><?php esc_html_e('Close', 'ybt-packer'); ?></button>
        </div>
    </div>
</div>


<?php
/**
 * Assets for this page are loaded externally:
 * CSS: assets/css/admin-packing.css
 * JS:  assets/js/admin-packing.js
 * (Enqueued in YBT_Admin::enqueue_admin_assets)
 */
