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

$warehouse_cache      = YBT_Packing::get_warehouse_cache();
$packing_requests     = YBT_Packing::get_filtered_requests($current_user_filter, $current_search);
$grouped_requests     = YBT_Packing::group_requests($packing_requests);
$available_users      = YBT_Packing::get_active_usernames();
$batch_management_data = YBT_Packing::get_user_batches();
?>

<div class="wrap ybt-admin-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="wp-heading-inline text-main display-6">
            <i class="fas fa-box-open me-2 text-primary"></i>
            <?php esc_html_e('Process Packing Requests', 'ybt-packer'); ?>
        </h1>
    </div>

    <!-- Filter Section -->
    <div class="glass-card mb-4">
        <div class="info-card-header bg-white d-flex justify-content-between align-items-center">
             <span><i class="fas fa-filter me-2 text-muted"></i><?php esc_html_e('Filters', 'ybt-packer'); ?></span>
        </div>
        <div class="info-card-body">
            <form method="GET" class="filter-form d-flex gap-3 align-items-end flex-wrap">
                <input type="hidden" name="page" value="ybt-process-packing">

                <div class="filter-group">
                    <label class="form-label small fw-bold text-muted text-uppercase"><?php esc_html_e('User', 'ybt-packer'); ?></label>
                    <select name="user_filter" class="form-select" style="min-width: 200px;">
                        <option value=""><?php esc_html_e('All Users', 'ybt-packer'); ?></option>
                        <?php foreach ($available_users as $user): ?>
                            <option value="<?php echo esc_attr($user->username); ?>" <?php selected($current_user_filter, $user->username); ?>>
                                <?php echo esc_html($user->username); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label small fw-bold text-muted text-uppercase"><?php esc_html_e('Search', 'ybt-packer'); ?></label>
                    <input type="text" name="search" class="form-control" value="<?php echo esc_attr($current_search); ?>" placeholder="<?php esc_attr_e('Tracking number...', 'ybt-packer'); ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'ybt-packer'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=ybt-process-packing'); ?>" class="button button-link"><?php esc_html_e('Clear', 'ybt-packer'); ?></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Packing Requests -->
    <div class="glass-card">
        <div class="info-card-header bg-white d-flex justify-content-between align-items-center">
             <span><i class="fas fa-list me-2 text-primary"></i><?php printf(esc_html__('Packing Requests (%d)', 'ybt-packer'), count($packing_requests)); ?></span>
        </div>
        <div class="info-card-body">
            <?php if ($grouped_requests): ?>
                <!-- Cross-Page Selection Indicator -->
                <div id="selection-indicator" style="display: none; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(34, 139, 34, 0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-size: 20px;">📋</span>
                            <div>
                                <strong style="font-size: 16px;"><span id="selection-count">0</span> <?php esc_html_e('Item(s) Selected', 'ybt-packer'); ?></strong>
                                <div style="font-size: 12px; opacity: 0.9;"><?php esc_html_e('Selections persist across pages', 'ybt-packer'); ?></div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="button" id="view-selected-items" style="background: white; color: var(--primary-color); border: none; font-weight: 600;">
                                👁️ <?php esc_html_e('View', 'ybt-packer'); ?>
                            </button>
                            <button class="button" id="clear-all-selections" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4);">
                                🗑️ <?php esc_html_e('Clear', 'ybt-packer'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bulk-actions mb-4 p-3 bg-light rounded border d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold text-muted me-2 small text-uppercase"><?php esc_html_e('Actions:', 'ybt-packer'); ?></span>
                    <button class="button button-primary" id="bulk-complete-selected">
                        <i class="fas fa-check me-1"></i> <?php esc_html_e('Complete Selected', 'ybt-packer'); ?>
                    </button>
                    <button class="button button-secondary" id="attach-cartons-selected">
                         <i class="fas fa-box me-1"></i> <?php esc_html_e('Attach Cartons (Selected)', 'ybt-packer'); ?>
                    </button>
                    <div class="ms-auto d-flex gap-2">
                         <button class="button button-small" id="select-all-page-items"><?php esc_html_e('check all', 'ybt-packer'); ?></button>
                         <button class="button button-small" id="deselect-all-page-items"><?php esc_html_e('none', 'ybt-packer'); ?></button>
                     </div>
                </div>

                <?php foreach ($grouped_requests as $username => $items): ?>
                    <!-- USER GROUP CONTAINER -->
                    <div class="glass-card mb-4 border-0 shadow-sm user-request-container">
                        <!-- User Header (Interactive) -->
                        <div class="user-group-header collapsed d-flex justify-content-between align-items-center bg-light p-3 border-bottom cursor-pointer select-none">
                            <h3 class="user-group-title m-0 fs-6 text-dark d-flex align-items-center">
                                <i class="fas fa-chevron-down me-2 toggle-icon text-muted small"></i>
                                👤 <?php echo esc_html($username); ?>
                                <span class="badge bg-white text-dark border ms-2 small fw-normal">
                                    <?php printf(esc_html__('%d items', 'ybt-packer'), count($items)); ?>
                                </span>
                            </h3>
                            <div class="user-group-actions d-flex gap-2" onclick="event.stopPropagation();">
                                <?php
                                $air_pdf_url = admin_url('admin-ajax.php?action=ybt_download_user_packing_requests&user=' . urlencode($username) . '&method=air&nonce=' . wp_create_nonce('ybt_admin_nonce'));
                                $sea_pdf_url = admin_url('admin-ajax.php?action=ybt_download_user_packing_requests&user=' . urlencode($username) . '&method=sea&nonce=' . wp_create_nonce('ybt_admin_nonce'));
                                ?>
                                <a href="<?php echo esc_url($air_pdf_url); ?>" class="button button-small button-secondary">
                                    <i class="fas fa-plane me-1"></i> <?php esc_html_e('Air PDF', 'ybt-packer'); ?>
                                </a>
                                <a href="<?php echo esc_url($sea_pdf_url); ?>" class="button button-small button-secondary">
                                    <i class="fas fa-ship me-1"></i> <?php esc_html_e('Sea PDF', 'ybt-packer'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Items Table Container (Collapsible) -->
                        <div class="user-group-content" style="display: none;">
                        <table class="table admin-table mb-0 border-0">
                            <thead>
                                <tr>
                                    <th class="check-column text-center" style="width: 40px;">
                                        <input type="checkbox" class="user-select-all" data-user="<?php echo esc_attr($username); ?>">
                                    </th>
                                    <th><?php esc_html_e('Warehouse Info', 'ybt-packer'); ?></th>
                                    <th><?php esc_html_e('Tracking #', 'ybt-packer'); ?></th>
                                    <th><?php esc_html_e('Description', 'ybt-packer'); ?></th>
                                    <th><?php esc_html_e('Date', 'ybt-packer'); ?></th>
                                    <th><?php esc_html_e('Method', 'ybt-packer'); ?></th>
                                    <th><?php esc_html_e('Images', 'ybt-packer'); ?></th>
                                    <th><?php esc_html_e('Count', 'ybt-packer'); ?></th>
                                    <th><?php esc_html_e('Complete', 'ybt-packer'); ?></th>
                                    <th><?php esc_html_e('Actions', 'ybt-packer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr data-id="<?php echo esc_attr($item->id); ?>" data-user="<?php echo esc_attr($username); ?>">
                                        <td style="text-align: center;"><input type="checkbox" class="item-checkbox" value="<?php echo esc_attr($item->id); ?>" data-user="<?php echo esc_attr($username); ?>"></td>
                                        <td>
                                            <?php
                                            $method_key = in_array($item->shipping_method, array('air', 'sea')) ? $item->shipping_method : 'sea';
                                            $warehouse_info = isset($warehouse_cache[$method_key]) ? $warehouse_cache[$method_key] : null;
                                            if ($warehouse_info): ?>
                                                <div style="font-size: 11px; line-height: 1.2;">
                                                    <strong><?php esc_html_e('Warehouse:', 'ybt-packer'); ?></strong><br><span class="text-muted"><?php echo nl2br(esc_html($warehouse_info->address)); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small"><?php esc_html_e('N/A', 'ybt-packer'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo esc_html($item->tracking_number); ?></strong></td>
                                        <td><?php echo esc_html($item->description ?: __('No description', 'ybt-packer')); ?></td>
                                        <td class="small text-muted">
                                            <?php
                                            $req_date = $item->date_packing_requested ?: $item->date_added;
                                            echo esc_html(date('M j', strtotime($req_date)));
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge-glass" style="font-size: 0.75em;">
                                                <?php echo ($item->shipping_method === 'air') ? '✈️ ' . esc_html__('Air', 'ybt-packer') : '🚢 ' . esc_html__('Sea', 'ybt-packer'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($item->images): ?>
                                                <?php
                                                $images = array_filter(array_map('trim', explode(',', $item->images)));
                                                ?>
                                                <div class="image-thumbnails" style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                    <?php foreach (array_slice($images, 0, 3) as $image): ?>
                                                        <img src="<?php echo esc_url($image); ?>"
                                                             style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid #ddd;"
                                                             onclick="openImageFullscreen('<?php echo esc_js($image); ?>')"
                                                             title="Click to view">
                                                    <?php endforeach; ?>
                                                    <?php if (count($images) > 3): ?>
                                                        <span style="font-size: 11px; color: #666; align-self: center;">+<?php echo count($images) - 3; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <button class="button button-small view-images mt-1" data-images="<?php echo esc_attr($item->images); ?>" style="font-size: 10px; padding: 2px 6px; height: auto;">
                                                    <?php esc_html_e('View All', 'ybt-packer'); ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="number" class="item-count-input" data-id="<?php echo esc_attr($item->id); ?>"
                                                   value="<?php echo esc_attr($item->item_count ?: '1'); ?>"
                                                   min="1" style="width: 50px; padding: 2px;">
                                        </td>
                                        <td>
                                            <label class="complete-checkbox-label">
                                                <input type="checkbox" class="complete-checkbox" data-id="<?php echo esc_attr($item->id); ?>"
                                                       <?php checked($item->is_complete, 1); ?>>
                                                <span class="checkmark"></span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="button button-small view-tracking-details" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('View Details', 'ybt-packer'); ?>">
                                                    👁️
                                                </button>
                                                <button class="button button-small button-primary mark-packed" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Mark Packed', 'ybt-packer'); ?>">
                                                    ✓
                                                </button>
                                                <button class="button button-small upload-packing-images" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Upload Images', 'ybt-packer'); ?>">
                                                    📷
                                                </button>
                                                 <button class="button button-small attach-cartons"
                                                     data-id="<?php echo esc_attr($item->id); ?>"
                                                     data-batch="<?php echo esc_attr($item->batch_id); ?>" title="<?php esc_attr_e('Attach Cartons', 'ybt-packer'); ?>">
                                                     📦
                                                 </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-5 text-center text-muted empty-state-container">
                    <i class="fas fa-check-circle fa-4x mb-4 opacity-25 text-success"></i>
                    <h4><?php esc_html_e('All Caught Up!', 'ybt-packer'); ?></h4>
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
