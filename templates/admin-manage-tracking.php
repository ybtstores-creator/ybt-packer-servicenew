<?php
if (!defined('ABSPATH')) exit;

/**
 * Tracking Management Template
 * Enhanced for Security, Performance, and Maintainability.
 */

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ybt-packer'));
}

global $wpdb;

/**
 * 1. ASSET ENQUEUEING
 */

/**
 * 2. USER DATA PREPARATION
 */
$all_wp_users = get_users(array('fields' => array('ID', 'user_login', 'display_name')));
foreach ($all_wp_users as $user) {
    // Escaping meta value for safety
    $user->custom_id = esc_attr(get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: '');
}

/**
 * 3. REQUEST PARAMETERS & SANITIZATION
 */
$status_filter          = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
$user_filter            = isset($_GET['user']) ? sanitize_text_field(wp_unslash($_GET['user'])) : '';
$shipping_method_filter = isset($_GET['shipping_method']) ? sanitize_text_field(wp_unslash($_GET['shipping_method'])) : '';
$search                 = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
$date_from              = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
$date_to                = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
$date_type              = isset($_GET['date_type']) ? sanitize_text_field(wp_unslash($_GET['date_type'])) : 'date_added';
$edited_by_filter       = isset($_GET['edited_by']) ? sanitize_text_field(wp_unslash($_GET['edited_by'])) : '';

// Admins who have edited tracking numbers (for the "Edited By" filter).
$editing_admins = class_exists('YBT_Tracking_Audit') ? YBT_Tracking_Audit::get_editing_admins() : array();

/**
 * 4. QUERY BUILDING
 */
$where_conditions = array();
$where_values     = array();

// Status Filter
if ($status_filter) {
    $where_conditions[] = "status = %s";
    $where_values[]     = $status_filter;
}

// User Filter
if ($user_filter) {
    if ($user_filter === 'anonymous') {
        $where_conditions[] = "(user_id IS NULL AND (username IS NULL OR username = '' OR username = 'N/A'))";
    } else {
        $where_conditions[] = "username = %s";
        $where_values[]     = $user_filter;
    }
}

// Shipping Method Filter
if ($shipping_method_filter) {
    if ($shipping_method_filter === 'anonymous') {
        $where_conditions[] = "(shipping_method IS NULL OR shipping_method = '')";
    } else {
        $where_conditions[] = "shipping_method = %s";
        $where_values[]     = $shipping_method_filter;
    }
}

// Date Logic (Whitelisted column names)
$date_column = ($date_type === 'date_received') ? 'date_received' : 'date_added';

if ($date_from) {
    $where_conditions[] = "DATE($date_column) >= %s";
    $where_values[]     = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE($date_column) <= %s";
    $where_values[]     = $date_to;
}

// Search Logic
if ($search) {
    $where_conditions[] = "(tracking_number LIKE %s OR username LIKE %s OR user_id IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ybt_custom_user_id' AND meta_value LIKE %s))";
    $like_search = '%' . $wpdb->esc_like($search) . '%';
    $where_values[] = $like_search;
    $where_values[] = $like_search;
    $where_values[] = $like_search;
}

// Admin attribution filter ("Edited By")
if ($edited_by_filter) {
    if ($edited_by_filter === 'never') {
        $where_conditions[] = "(last_edited_by_name IS NULL OR last_edited_by_name = '')";
    } else {
        $where_conditions[] = "last_edited_by_name = %s";
        $where_values[]     = $edited_by_filter;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

/**
 * 5. PAGINATION CONFIGURATION
 */
$per_page     = 25;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset       = ($current_page - 1) * $per_page;

/**
 * 6. TOTAL COUNT & LIST RETRIEVAL
 */
$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking $where_clause";
$total_items = !empty($where_values) ? $wpdb->get_var($wpdb->prepare($count_query, $where_values)) : $wpdb->get_var($count_query);
$total_pages = ceil($total_items / $per_page);

/**
 * 7. SORTING & FINAL QUERY EXECTION
 */
$sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_added';
$allowed_sorts = array('tracking_number' => 'tracking_number', 'username' => 'username', 'date_added' => 'date_added', 'date_received' => 'date_received', 'status' => 'status', 'last_edited_at' => 'last_edited_at');

// Sort Mapping
$sort_field = isset($allowed_sorts[$sort]) ? $allowed_sorts[$sort] : 'date_added';

if ($sort_field === 'username') {
    $query = "SELECT * FROM {$wpdb->prefix}ybt_tracking $where_clause ORDER BY COALESCE(username, 'zzz') ASC";
} else {
    $query = "SELECT * FROM {$wpdb->prefix}ybt_tracking $where_clause ORDER BY $sort_field DESC";
}

$query .= " LIMIT %d OFFSET %d";
$where_values[] = $per_page;
$where_values[] = $offset;

$tracking_items = $wpdb->get_results($wpdb->prepare($query, $where_values));

/**
 * 8. BATCH PROCESSING DATA
 */
$batch_counts = array();
$batch_ids = array();
if ($tracking_items) {
    foreach ($tracking_items as $batch_item) {
        if (!empty($batch_item->batch_id)) {
            $batch_ids[$batch_item->batch_id] = $batch_item->batch_id;
            $batch_counts[$batch_item->batch_id] = ($batch_counts[$batch_item->batch_id] ?? 0) + 1;
        }
    }
}

// Weight/carton photos for the rows on this page, in one query keyed by batch.
$weight_images_by_batch = array();
if ($batch_ids) {
    $batch_list = array_values($batch_ids);
    $batch_placeholders = implode(',', array_fill(0, count($batch_list), '%s'));
    $photo_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT batch_id, carton_image FROM {$wpdb->prefix}ybt_cartons
         WHERE batch_id IN ($batch_placeholders) AND carton_image IS NOT NULL AND carton_image != ''
         ORDER BY carton_number ASC",
        $batch_list
    ));
    foreach ($photo_rows as $photo_row) {
        if (isset($weight_images_by_batch[$photo_row->batch_id])) {
            continue;
        }
        $photo_urls = array_values(array_filter(array_map('trim', explode(',', $photo_row->carton_image))));
        if (!empty($photo_urls)) {
            $weight_images_by_batch[$photo_row->batch_id] = $photo_urls[0];
        }
    }
}
?>

<div class="wrap ybt-admin-wrapper ybt-ledger">
	<!-- ledger head -->
	<div class="ybt-ledgerhead">
		<div>
			<p class="ybd-eyebrow">Depot ledger &middot; Consignment register</p>
			<h1><?php _e('Tracking management', 'ybt-packer'); ?></h1>
			<p class="ybd-sub">The full consignment register — filter, edit, pack, and dispatch.</p>
		</div>
		<span class="ybd-count"><?php echo number_format($total_items); ?> in register</span>
	</div>

	<!-- 2. SMART FILTERS SECTION -->
	<div class="ybd-panel ybd-panel-gold" style="margin-bottom:20px;">
		<div class="ybd-panelhead">
			<span class="ybd-hint"><i class="fas fa-search"></i>Smart filters</span>
			<div class="d-flex gap-2">
				<button type="button" id="generate-pdf" class="ybd-btn ybd-btn-emerald" style="padding:6px 12px;font-size:12.5px;"><i class="fas fa-file-pdf"></i>PDF</button>
				<button type="button" id="quick-export" class="ybd-btn ybd-btn-emerald" style="padding:6px 12px;font-size:12.5px;"><i class="fas fa-file-csv"></i>CSV</button>
			</div>
		</div>
		<div class="ybd-panelbody">
		<form method="GET" class="filter-form">
            <input type="hidden" name="page" value="ybt-manage-tracking">
            <input type="hidden" name="paged" value="1">

            <div class="row g-3">
                <!-- Row 1 -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark mb-1">Status:</label>
                    <select name="status" class="w-100 form-control">
                        <option value="">All Statuses</option>
                        <option value="not_received" <?php selected($status_filter, 'not_received'); ?>>Not Received</option>
                        <option value="received" <?php selected($status_filter, 'received'); ?>>Received</option>
                        <option value="packing_in_progress" <?php selected($status_filter, 'packing_in_progress'); ?>>Packing</option>
                        <option value="packed" <?php selected($status_filter, 'packed'); ?>>Packed</option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark mb-1">Shipping Method:</label>
                    <select name="shipping_method" class="w-100 form-control">
                        <option value="">All Methods</option>
                        <option value="air" <?php selected($shipping_method_filter, 'air'); ?>>Air</option>
                        <option value="sea" <?php selected($shipping_method_filter, 'sea'); ?>>Sea</option>
                        <option value="anonymous" <?php selected($shipping_method_filter, 'anonymous'); ?>>Anonymous</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-dark mb-1">User:</label>
                    <div class="input-group">
                        <select name="user" class="w-100 form-control">
                            <option value="" selected>All Users</option>
                            <option value="anonymous" <?php selected($user_filter, 'anonymous'); ?>>Anonymous</option>
                            <?php foreach ($all_wp_users as $u): ?>
                                <option value="<?php echo esc_attr($u->user_login); ?>" <?php selected($user_filter, $u->user_login); ?>>
                                    <?php echo esc_html($u->display_name . ($u->custom_id ? ' [' . $u->custom_id . ']' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2 -->
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-dark mb-1">Date Type:</label>
                    <select name="date_type" class="w-100 form-control">
                        <option value="date_added" <?php selected($date_type, 'date_added'); ?>>Date Added</option>
                        <option value="date_received" <?php selected($date_type, 'date_received'); ?>>Date Received</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-dark mb-1">From:</label>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="form-control w-100">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-dark mb-1">To:</label>
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="form-control w-100">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark mb-1">Search:</label>
                    <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Partial tracking number or username" class="form-control w-100">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-dark mb-1">Edited By (Admin):</label>
                    <select name="edited_by" class="w-100 form-control">
                        <option value="">All Admins</option>
                        <?php foreach ($editing_admins as $admin_name => $edit_total): ?>
                            <option value="<?php echo esc_attr($admin_name); ?>" <?php selected($edited_by_filter, $admin_name); ?>>
                                <?php echo esc_html($admin_name . ' (' . $edit_total . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="never" <?php selected($edited_by_filter, 'never'); ?>>— Never edited —</option>
                    </select>
                </div>
            </div>

			<div class="d-flex justify-content-between align-items-center mt-4 border-top pt-3">
				<div>
					<button type="submit" class="ybd-btn ybd-btn-gold"><i class="fas fa-search"></i>Filter</button>
					<a href="<?php echo admin_url('admin.php?page=ybt-manage-tracking'); ?>" class="ybd-btn ybd-btn-emerald"><i class="fas fa-trash-alt"></i>Clear</a>
				</div>
				<button type="button" id="attach-cartons-selected" class="ybd-btn ybd-btn-dark">
					<i class="fas fa-box-open"></i>Attach cartons (selected)
				</button>
			</div>
		</form>
		</div>
	</div>

	<!-- 3. TRACKING ITEMS TABLE -->
	<div class="ybd-panel">
		<!-- Cross-Page Selection Indicator -->
		<div id="selection-indicator" style="display: none; background: var(--ink); color: #fff; padding: 12px 20px; border-bottom: 2px dashed var(--kraft-line);">
			<div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
				<div style="display: flex; align-items: center; gap: 15px;">
					<span style="font-size: 18px;">📋</span>
					<div>
						<strong style="font-size: 15px;"><span id="selection-count">0</span> item(s) selected</strong>
						<div style="font-size: 12px; opacity: 0.85; font-family: var(--mono);">Selections persist across pages</div>
					</div>
				</div>
				<div style="display: flex; gap: 8px;">
					<button class="button button-small" id="view-selected-items" style="background: #fff; color: var(--ink); border: 1px solid #fff;">View</button>
					<button class="button button-small" id="clear-all-selections" style="background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.5);">Clear</button>
				</div>
			</div>
		</div>

		<div class="ybd-panelhead">
			<span class="ybd-hint"><i class="fas fa-list-alt"></i>Tracking list (<?php echo number_format($total_items); ?>)</span>

			<div class="bulk-controls d-flex align-items-center gap-2">
                <select id="bulk-action-selector" style="max-width: 150px;">
                    <option value="">Bulk Actions</option>
                    <option value="assign_user">Assign to User</option>
                    <option value="received">Mark Received</option>
                    <option value="packed">Mark Packed</option>
                    <option value="completed">Mark Completed</option>
                    <option value="delete">Delete Selected</option>
                </select>

                <div id="bulk-assign-user-container" style="display:none;">
                    <input type="text" id="bulk-assign-username" list="user-list-assign" placeholder="Username..." style="width: 120px;">
                    <datalist id="user-list-assign">
                        <?php foreach ($all_wp_users as $u): ?>
                            <option value="<?php echo esc_attr($u->user_login); ?>"><?php echo esc_html($u->display_name); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <button type="button" class="button button-primary" id="apply-bulk-action">Apply</button>
            </div>
        </div>

		<div class="ybd-tablewrap">
		<?php if ($tracking_items): ?>
			<table class="ybd-manifest">
                    <thead>
                        <tr>
								<th width="30px" class="text-center"><input type="checkbox" id="select-all-header" class="ybd-check"></th>
								<th><a href="<?php echo esc_url(add_query_arg('sort', 'tracking_number')); ?>" class="ybt-sortlink">Tracking number <i class="fas fa-sort small opacity-50"></i></a></th>
								<th><a href="<?php echo esc_url(add_query_arg('sort', 'username')); ?>" class="ybt-sortlink">User <i class="fas fa-sort small opacity-50"></i></a></th>
								<th><a href="<?php echo esc_url(add_query_arg('sort', 'status')); ?>" class="ybt-sortlink">Status <i class="fas fa-sort small opacity-50"></i></a></th>
								<th>Items</th>
								<th>Payment</th>
								<th><a href="<?php echo esc_url(add_query_arg('sort', 'date_added')); ?>" class="ybt-sortlink">Date <i class="fas fa-sort small opacity-50"></i></a></th>
								<th><a href="<?php echo esc_url(add_query_arg('sort', 'last_edited_at')); ?>" class="ybt-sortlink" title="Administrator who last edited this tracking number">Edited by <i class="fas fa-sort small opacity-50"></i></a></th>
								<th>Images</th>
								<th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tracking_items as $item): ?>
                            <tr data-id="<?php echo esc_attr($item->id); ?>">
                                <td class="text-center"><input type="checkbox" class="item-checkbox" value="<?php echo esc_attr($item->id); ?>" data-user-id="<?php echo esc_attr($item->user_id); ?>"></td>
                                <td>
                                    <span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span>
                                    <?php if ($item->cartons || $item->payment_amount): ?>
                                        <span class="ybt-parcelmeta">📦
                                        <?php echo esc_html($item->cartons); ?> ctns |
                                        <?php echo esc_html($item->payment_amount . ' ' . $item->payment_currency); ?>
                                        </span>
                                        <?php
                                        $weight_image = !empty($item->batch_id) ? ($weight_images_by_batch[$item->batch_id] ?? '') : '';
                                        if ($weight_image): ?>
                                            <a href="<?php echo esc_url($weight_image); ?>" target="_blank" class="ybt-sortlink" title="Weight Image">📷</a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($item->shipping_method === 'air'): ?>
                                        <span class="ybt-method ybt-method-air" title="Air">AIR</span>
                                    <?php elseif ($item->shipping_method === 'sea'): ?>
                                        <span class="ybt-method ybt-method-sea" title="Sea">SEA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo empty($item->username) ? 'text-muted fst-italic' : 'fw-bold'; ?>">
                                        <?php echo esc_html($item->username ?: 'Anonymous'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    // Shared chip atom: ybd-chip ybd-chip-<status>
                                    $s_chip = 'ybd-chip';
                                    if (in_array($item->status, array('not_received','received','packing_in_progress','packed','completed'), true)) {
                                        $s_chip .= ' ybd-chip-' . $item->status;
                                    }
                                    ?>
                                    <span class="<?php echo esc_attr($s_chip); ?>">
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $item->status))); ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="number" class="item-count-input form-control p-1 text-center"
                                           data-id="<?php echo esc_attr($item->id); ?>"
                                           value="<?php echo esc_attr($item->item_count ?: '1'); ?>"
                                           min="1" max="99" style="width: 50px; display: inline-block;">
                                </td>
                                <td>
                                    <?php if (!empty($item->payment_evidence)): ?>
                                        <button class="button button-small view-images" data-images="<?php echo esc_attr($item->payment_evidence); ?>" style="color:#228B22;">
                                            <i class="fas fa-receipt"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="ybd-date"><?php echo esc_html(date('M j, Y', strtotime($item->date_added))); ?></td>
                                <td style="min-width: 150px;">
                                    <?php
                                    $editor = class_exists('YBT_Tracking_Audit')
                                        ? YBT_Tracking_Audit::describe_last_editor($item)
                                        : array('has_editor' => false, 'name' => '', 'edited_ago' => '', 'count' => 0, 'label' => '');
                                    ?>
                                    <?php if ($editor['has_editor']): ?>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="ybt-editorbadge" title="Last edited by <?php echo esc_attr($editor['name']); ?>">
                                                <i class="fas fa-user-shield"></i><?php echo esc_html($editor['name']); ?>
                                            </span>
                                        </div>
                                        <div class="text-muted" style="font-size: 11px; line-height: 1.4;">
                                            <?php echo esc_html($editor['edited_ago']); ?>
                                            <?php if ($editor['count'] > 0): ?>
                                                · <span title="<?php echo esc_attr($editor['label']); ?>"><?php echo esc_html($editor['label']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="button button-small view-edit-history p-0 px-1 mt-1"
                                                style="font-size: 10px;"
                                                data-id="<?php echo esc_attr($item->id); ?>"
                                                data-tracking="<?php echo esc_attr($item->tracking_number); ?>">
                                            <i class="fas fa-history"></i> History
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic" style="font-size: 11px;">Not edited</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item->images): ?>
                                        <?php
                                        $images = array_filter(array_map('trim', explode(',', $item->images)));
                                        ?>
                                        <div class="d-flex gap-1 mb-1">
                                            <?php foreach (array_slice($images, 0, 2) as $image): ?>
                                                <img src="<?php echo esc_url($image); ?>"
                                                     style="width: 28px; height: 28px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid #ddd;"
                                                     onclick="openImageFullscreen('<?php echo esc_js($image); ?>')">
                                            <?php endforeach; ?>
                                        </div>
                                        <button class="button button-small view-images p-0 px-1" data-images="<?php echo esc_attr($item->images); ?>" style="font-size: 10px;">
                                            View All
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=ybt-add-tracking&edit=' . $item->id)); ?>" class="button button-small" title="Full Edit">✏️</a>

                                        <?php if ($item->status === 'not_received'): ?>
                                            <button class="button button-small move-to-received" data-id="<?php echo esc_attr($item->id); ?>" title="Mark Received">📥</button>
                                        <?php elseif (in_array($item->status, ['received', 'packing_in_progress'])): ?>
                                            <button class="button button-small move-to-packed" data-id="<?php echo esc_attr($item->id); ?>" title="Mark Packed">📦</button>
                                        <?php elseif ($item->status === 'packed'): ?>
                                            <button class="button button-small move-to-completed" data-id="<?php echo esc_attr($item->id); ?>" title="Mark Completed">✅</button>
                                        <?php endif; ?>

                                        <button class="button button-small upload-images" data-id="<?php echo esc_attr($item->id); ?>" title="Upload Image">📷</button>
                                        <button class="button button-small button-link-delete delete-item text-danger border-danger" data-id="<?php echo esc_attr($item->id); ?>" title="Delete">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="ybt-pager p-3 text-center border-top">
                    <?php
                    $pagination_args = array(
                        'page' => 'ybt-manage-tracking',
                        'status' => $status_filter,
                        'user' => $user_filter,
                        'shipping_method' => $shipping_method_filter,
                        'search' => $search,
                        'sort' => $sort,
                        'date_type' => $date_type,
                        'date_from' => $date_from,
                        'date_to' => $date_to
                    );
                    $base_url = add_query_arg($pagination_args, admin_url('admin.php'));

                    if ($current_page > 1) {
                        $prev_url = add_query_arg('paged', $current_page - 1, $base_url);
                        echo '<a href="' . esc_url($prev_url) . '" class="ybt-pager-btn">«</a> ';
                    }

                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1) {
                        $first_url = add_query_arg('paged', 1, $base_url);
                        echo '<a href="' . esc_url($first_url) . '" class="ybt-pager-btn">1</a> ';
                        if ($start_page > 2) echo '<span class="ybt-pager-btn ybt-pager-dots">...</span> ';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $current_page) {
                            echo '<span class="ybt-pager-btn ybt-pager-cur">' . $i . '</span> ';
                        } else {
                            $page_url = add_query_arg('paged', $i, $base_url);
                            echo '<a href="' . esc_url($page_url) . '" class="ybt-pager-btn">' . $i . '</a> ';
                        }
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span class="ybt-pager-btn ybt-pager-dots">...</span> ';
                        $last_url = add_query_arg('paged', $total_pages, $base_url);
                        echo '<a href="' . esc_url($last_url) . '" class="ybt-pager-btn">' . $total_pages . '</a> ';
                    }

                    if ($current_page < $total_pages) {
                        $next_url = add_query_arg('paged', $current_page + 1, $base_url);
                        echo '<a href="' . esc_url($next_url) . '" class="ybt-pager-btn">»</a>';
                    }
                    ?>
                </div>
                <?php endif; ?>
		<?php else: ?>
			<div class="ybd-empty">
				<div class="ybd-emptyicon"><i class="fas fa-search"></i></div>
				<h4>No matching parcels</h4>
				<p>No tracking numbers found matching your criteria.</p>
			</div>
		<?php endif; ?>
		</div>
	</div>
</div>

<!-- Modals (Preserved Functionality with clean styles) -->
<div id="image-upload-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content">
        <h3>Upload Images</h3>
        <form id="image-upload-form">
            <input type="hidden" id="upload-tracking-id">
            <input type="file" id="upload-images" multiple accept="image/*" class="form-control mb-3">
            <div class="ybt-modal-actions">
                <button type="button" class="button close-modal">Cancel</button>
                <button type="submit" class="button button-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<div id="image-viewer-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 800px; max-height: 90vh;">
        <h3>📷 Parcel Images</h3>
        <div id="image-gallery" style="max-height: 70vh; overflow-y: auto; text-align: center; padding: 15px;"></div>
        <div class="ybt-modal-actions">
            <button type="button" class="button close-modal">Close</button>
        </div>
    </div>
</div>

<div id="attach-cartons-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content">
        <h3 style="margin-top:0;">Attach Cartons to Batch</h3>
        <p class="ybt-modal-meta">Batch <strong id="attach-batch-label">—</strong></p>
        <form id="attach-cartons-form">
            <input type="hidden" id="attach-batch-id">
            <label>Number of Cartons</label>
            <input type="number" id="attach-cartons" min="1" required class="form-control mb-2">
            <label>Total Weight (KG)</label>
            <input type="number" id="attach-weight" min="0.1" step="0.1" required class="form-control mb-2">
            <label>Payment Amount</label>
            <input type="number" id="attach-payment" min="0.01" step="0.01" required class="form-control mb-2">
            <label>Currency</label>
            <select id="attach-currency" required class="form-control mb-2">
                <option value="">Select currency</option>
                <option value="CNY">Chinese Yuan (CNY)</option>
                <option value="NGN">Nigerian Naira (NGN)</option>
            </select>
            <label>Weight/Scale Image</label>
            <input type="file" id="attach-weight-image" accept="image/*" class="form-control mb-3">
            <div class="ybt-modal-actions">
                <button type="button" class="button close-modal">Cancel</button>
                <button type="submit" class="button button-primary">Attach</button>
            </div>
        </form>
    </div>
</div>

<div id="attach-cartons-selected-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 500px;">
        <h3 style="margin-top:0;">Attach Cartons to Selected</h3>
        <p class="ybt-modal-meta"><strong id="attach-selected-count">0</strong> tracking number(s) selected.</p>
        <form id="attach-cartons-selected-form" enctype="multipart/form-data">
            <input type="hidden" id="attach-selected-ids">
            <label>Number of Cartons</label>
            <input type="number" id="attach-selected-cartons" min="1" max="50" value="1" required class="form-control mb-2">
            <label>Total Weight (KG)</label>
            <input type="number" id="attach-selected-weight" min="0.1" step="0.1" required class="form-control mb-2">
            <label>Total Payment Amount</label>
            <input type="number" id="attach-selected-payment" min="0.01" step="0.01" required class="form-control mb-2">
            <label>Currency</label>
            <select id="attach-selected-currency" required class="form-control mb-3">
                <option value="CNY">Chinese Yuan (CNY)</option>
                <option value="NGN">Nigerian Naira (NGN)</option>
            </select>

            <button type="button" id="generate-tracking-carton-fields" class="button button-secondary mb-3 w-100">Generate Image Fields</button>
            <div id="tracking-carton-fields-container" style="max-height:300px; overflow-y:auto; margin-bottom:15px; border:1px solid #eee; padding:10px; border-radius:4px;"></div>

            <div class="ybt-modal-actions">
                <button type="button" class="button close-modal">Cancel</button>
                <button type="submit" class="button button-primary">Save Cartons</button>
            </div>
        </form>
    </div>
</div>

<div id="image-edit-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content">
        <h3>Replace Image</h3>
        <form id="image-edit-form">
            <input type="hidden" id="edit-tracking-id">
            <input type="hidden" id="edit-old-image-url">
            <div class="text-center mb-3">
                <img id="current-image-preview" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <label>Select New Image:</label>
            <input type="file" id="edit-new-image" accept="image/*" required class="form-control mb-3">
            <div class="ybt-modal-actions">
                <button type="button" class="button close-modal">Cancel</button>
                <button type="submit" class="button button-primary">Replace</button>
            </div>
        </form>
    </div>
</div>

<div id="view-selected-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 600px;">
        <h3>📋 Selected Items</h3>
        <div id="selected-items-list" style="max-height: 400px; overflow-y: auto; margin-bottom: 15px;"></div>
        <div class="ybt-modal-actions">
            <button type="button" class="button close-modal">Close</button>
        </div>
    </div>
</div>

<div id="edit-history-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 780px; max-height: 90vh; display: flex; flex-direction: column;">
        <h3 style="margin-top: 0;">🕓 Edit History — <span id="edit-history-tracking" style="color: #2271b1;"></span></h3>
        <div class="ybt-modal-meta" id="edit-history-meta">Loading…</div>
        <div id="edit-history-body" style="overflow-y: auto; flex: 1 1 auto; max-height: 62vh; padding-right: 4px;"></div>
        <div class="ybt-modal-actions" style="margin-top: 12px;">
            <button type="button" class="button close-modal">Close</button>
        </div>
    </div>
</div>

<style>
/* ================================================================
   TRACKING REGISTER — page-specific layer (consignment-note system)
   Shared tokens & atoms come from assets/css/ybt-consignment.css
   (scope root: .ybt-ledger). Modals render outside the scope, so
   tokens are declared on .ybt-modal-content in the shared sheet.
   ================================================================ */
/* sort links + parcel meta inside manifest cells */
.ybt-ledger .ybt-sortlink{ color:var(--ink); text-decoration:none; }
.ybt-ledger .ybt-sortlink:hover{ color:var(--emerald-deep); }
.ybt-ledger .ybt-parcelmeta{ display:block; font-family:var(--mono); font-size:11.5px; font-weight:600; color:var(--emerald-deep); margin-top:3px; }
.ybt-ledger .ybt-method{
	display:inline-block;
	font-family:var(--mono);
	font-size:10px;font-weight:600;
	letter-spacing:.08em;
	border-radius:4px;
	padding:1px 6px;
	margin-left:4px;
	vertical-align:middle;
}
.ybt-ledger .ybt-method-air{ background:var(--gold-tint); color:var(--gold-deep); border:1px solid var(--gold); }
.ybt-ledger .ybt-method-sea{ background:var(--emerald-tint); color:var(--emerald-deep); border:1px solid var(--emerald); }
.ybt-ledger .ybt-editorbadge{
	display:inline-flex;align-items:center;gap:5px;
	font-family:var(--mono);
	font-size:11px;font-weight:600;
	color:var(--ink);
	background:#fbf8f1;
	border:1.5px solid var(--kraft-deep);
	border-radius:5px;
	padding:2px 8px;
}
.ybt-ledger .ybt-editorbadge i{ color:var(--gold-deep); }
/* filter grid */
.ybt-ledger .filter-form label{ font-family:var(--mono); font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:#5c5543; margin-bottom:4px; display:block; }
.ybt-ledger .filter-form .form-control{ border:1.5px solid var(--kraft-deep); border-radius:8px; background:#fbf8f1; color:var(--ink); font-size:14px; }
.ybt-ledger .filter-form .form-control:focus{ border-color:var(--emerald); box-shadow:0 0 0 3px rgba(16,185,129,.22); outline:none; }
/* pagination — emerald ticket squares */
.ybt-ledger .ybt-pager{ border-top:2px dashed var(--kraft-line); }
.ybt-pager-btn{
	display:inline-flex;align-items:center;justify-content:center;
	min-width:34px;height:34px;
	margin:0 2px;
	font-family:var(--mono);
	font-size:13px;font-weight:600;
	color:var(--emerald-deep);
	background:var(--paper);
	border:1.5px solid var(--emerald);
	border-radius:7px;
	text-decoration:none;
}
.ybt-pager-btn:hover{ background:var(--emerald-tint); color:var(--emerald-deep); }
.ybt-pager-cur{ background:var(--emerald-deep); color:#fff; border-color:var(--emerald-deep); }
.ybt-pager-dots{ border-color:transparent; background:transparent; color:#8b968f; }
/* modals — consignment-note chrome (render outside .ybt-ledger) */
.ybt-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,31,23,0.55); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
.ybt-modal-content { background: #fff; border: 1.5px solid #0f1f17; border-radius: 12px; padding: 20px; width: 100%; max-width: 450px; box-shadow: 6px 6px 0 rgba(15,31,23,.25); }
.ybt-modal-content h3{ font-family: var(--display,'Archivo Black',sans-serif); font-weight: 400; font-size: 16px; margin: 0 0 12px; color: #0f1f17; }
.ybt-modal-content label{ font-family: var(--mono,monospace); font-size: 10px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: #5c5543; display: block; margin-bottom: 4px; }
.ybt-modal-content .form-control{ border: 1.5px solid #b9a67f; border-radius: 8px; background: #fbf8f1; color: #0f1f17; font-size: 14px; }
.ybt-modal-content .form-control:focus{ border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.22); outline: none; }
.ybt-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }
.ybt-modal-meta { background: #faf3dc; border: 1px solid #d4af37; border-radius: 8px; padding: 10px 12px; font-size: 13px; color: #4a4232; margin-bottom: 15px; }
.ybt-modal-content .close-modal{ border: 2px solid #10b981; background: transparent; color: #047857; border-radius: 8px; padding: 9px 18px; font-weight: 700; font-size: 14.5px; }
.ybt-modal-content .close-modal:hover{ background: #e7f6ef; }
.ybt-modal-content .button-primary{
	background: #d4af37; color: #0f1f17; border: none; border-radius: 8px;
	padding: 11px 20px; font-weight: 700; font-size: 14.5px;
	box-shadow: 3px 3px 0 rgba(138,109,31,.45); text-shadow: none;
}
.ybt-modal-content .button-primary:hover{ background: #dfbc48; box-shadow: 4px 4px 0 rgba(138,109,31,.5); }
</style>

<script>
// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';
const escapeHtmlAttr = (u) => u ? String(u).replace(/[^\w-]/g, s => `&#${s.charCodeAt(0)};`) : '';

jQuery(document).ready(function($) {
    const STORAGE_KEY = 'ybt_manage_tracking_selected_ids';

    // LocalStorage Selection
    function loadSelectedIds() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch (e) { return []; }
    }
    function saveSelectedIds(ids) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
        updateSelectionCounter();
    }
    function clearSelectedIds() {
        localStorage.removeItem(STORAGE_KEY);
        updateSelectionCounter();
        $('.item-checkbox, #select-all-header').prop('checked', false);
    }
    function updateSelectionCounter() {
        const count = loadSelectedIds().length;
        $('#selection-count, #attach-selected-count').text(count);
        count > 0 ? $('#selection-indicator').slideDown(200) : $('#selection-indicator').slideUp(200);
    }
    function toggleSelection(id, isChecked) {
        let selectedIds = loadSelectedIds();
        const idNum = parseInt(id);
        if (isNaN(idNum)) return;
        if (isChecked) {
            if (!selectedIds.includes(idNum)) selectedIds.push(idNum);
        } else {
            selectedIds = selectedIds.filter(i => i !== idNum);
        }
        saveSelectedIds(selectedIds);
    }
    function restoreCheckboxes() {
        const selectedIds = loadSelectedIds();
        $('.item-checkbox').each(function() { if (selectedIds.includes(parseInt($(this).val()))) $(this).prop('checked', true); });
        updateSelectionCounter();
    }
    restoreCheckboxes();

    // Checkbox Listeners
    $(document).on('change', '.item-checkbox', function() {
        toggleSelection($(this).val(), $(this).is(':checked'));
        checkSelectAllHeader();
    });

    $('#select-all-header').change(function() {
        const isChecked = $(this).prop('checked');
        const checkboxes = $('.item-checkbox');
        checkboxes.prop('checked', isChecked);

        let selectedIds = loadSelectedIds();
        checkboxes.each(function() {
            const id = parseInt($(this).val());
            if (isNaN(id)) return;
            if (isChecked) {
                if (!selectedIds.includes(id)) selectedIds.push(id);
            } else {
                selectedIds = selectedIds.filter(i => i !== id);
            }
        });
        saveSelectedIds(selectedIds);
    });

    function checkSelectAllHeader() {
        const total = $('.item-checkbox').length;
        const checked = $('.item-checkbox:checked').length;
        $('#select-all-header').prop('checked', total > 0 && total === checked);
    }

    $('#clear-all-selections').click(function() { if(confirm('Clear selections?')) clearSelectedIds(); });

    // Inline Edits
    $('.item-count-input').change(function() {
        const id = $(this).data('id');
        const val = $(this).val();
        if (!id || isNaN(val)) return;
        $.post(ybt_admin.ajax_url, {
            action: 'ybt_update_item_details',
            nonce: ybt_admin.nonce,
            tracking_id: id,
            field: 'item_count',
            value: val
        });
    });

    // Bulk Actions
    $('#bulk-action-selector').change(function() { $(this).val() === 'assign_user' ? $('#bulk-assign-user-container').show() : $('#bulk-assign-user-container').hide(); });
    $('#apply-bulk-action').click(function() {
        const action = $('#bulk-action-selector').val();
        const selected = loadSelectedIds();
        if (selected.length === 0) { alert('Select items first.'); return; }

        if (action === 'assign_user') {
            const username = $('#bulk-assign-username').val();
            if (!username) { alert('Enter a username.'); return; }
            if (confirm('Assign ' + selected.length + ' items to "' + username + '"?')) {
                $.post(ybt_admin.ajax_url, {
                    action: 'ybt_bulk_assign_user',
                    nonce: ybt_admin.nonce,
                    tracking_ids: selected.join(','),
                    username: username
                }, function(res) {
                    if(res.success) { alert(escapeHtml(res.data)); clearSelectedIds(); location.reload(); } else alert(escapeHtml(res.data));
                });
            }
            return;
        }

        if (action === 'delete') {
            if(confirm('Delete ' + selected.length + ' items?')) {
                $.post(ybt_admin.ajax_url, {
                    action: 'ybt_bulk_delete_items',
                    nonce: ybt_admin.nonce,
                    tracking_ids: selected.join(',')
                }, function(res) {
                    if(res.success) { alert('Deleted'); clearSelectedIds(); location.reload(); } else alert(escapeHtml(res.data));
                });
            }
            return;
        }

        let status = '';
        if (action === 'received') status = 'received';
        else if (action === 'packed') status = 'packed';
        else if (action === 'completed') status = 'completed';

        if(status && confirm('Update status for ' + selected.length + ' items?')) {
            $.post(ybt_admin.ajax_url, {
                action: 'ybt_bulk_update_status',
                nonce: ybt_admin.nonce,
                tracking_ids: selected.join(','),
                status: status
            }, function(res) {
                if(res.success) { clearSelectedIds(); location.reload(); } else alert(res.data);
            });
        }
    });

    // Single Actions
    $('.move-to-received').click(function() { updateStatus($(this).data('id'), 'received'); });
    $('.move-to-packed').click(function() { updateStatus($(this).data('id'), 'packed'); });
    $('.move-to-completed').click(function() { updateStatus($(this).data('id'), 'completed'); });
    $('.delete-item').click(function() {
        const itemId = $(this).data('id');
        console.log('Delete button clicked, item ID:', itemId);
        
        if (!itemId) {
            alert('Error: Invalid tracking ID. Please refresh the page.');
            return;
        }
        
        if(confirm('Delete item #' + itemId + '?')) {
            $.post(ybt_admin.ajax_url, {
                action: 'ybt_delete_tracking_item',
                nonce: ybt_admin.nonce,
                id: itemId
            }, function(res) {
                console.log('Delete response:', res);
                if(res.success) {
                    alert('Deleted successfully');
                    location.reload();
                } else {
                    alert('Error: ' + escapeHtml(res.data || 'Unknown error'));
                }
            }).fail(function(xhr) {
                console.error('Delete AJAX failed:', xhr.responseText);
                alert('Server error occurred while deleting item.');
            });
        }
    });
    function updateStatus(id, status) {
        if(confirm('Mark as ' + status + '?')) {
            $.post(ybt_admin.ajax_url, {
                action: 'ybt_update_status',
                nonce: ybt_admin.nonce,
                tracking_id: id, status: status
            }, function(res) {
                if(res.success) location.reload(); else alert(escapeHtml(res.data));
            });
        }
    }

    // Export/PDF
    $('#quick-export').click(function() { window.location.href = ybt_admin.ajax_url + '?action=ybt_export_data&nonce=' + ybt_admin.nonce; });
    $('#generate-pdf').click(function() {
        const params = new URLSearchParams({ action: 'ybt_admin_generate_tracking_pdf', nonce: ybt_admin.nonce, status: $('select[name="status"]').val(), user: $('select[name="user"]').val(), shipping_method: $('select[name="shipping_method"]').val(), search: $('input[name="search"]').val(), date_from: $('input[name="date_from"]').val(), date_to: $('input[name="date_to"]').val(), date_type: $('select[name="date_type"]').val(), autoprint: '1' });
        window.open(ybt_admin.ajax_url + '?' + params.toString(), '_blank');
    });

    // Image & Carton Modals
    $('.close-modal').click(function() { $('.ybt-modal-overlay').hide(); });
    $('.upload-images').click(function() { $('#upload-tracking-id').val($(this).data('id')); $('#image-upload-modal').css('display', 'flex').show(); });

    // ---------------------------------------------------------------------
    // EDIT HISTORY — shows which admin worked on each edit of a tracking no.
    // ---------------------------------------------------------------------
    function renderEditHistoryEntry(entry) {
        const adminName = escapeHtml(entry.admin_name || 'System');
        const actionLabel = escapeHtml((entry.action || 'edit').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
        const when = entry.date_created ? escapeHtml(entry.date_created) : '';
        const summary = escapeHtml(entry.summary || '');
        const source = entry.source ? escapeHtml(entry.source) : '';
        const ip = entry.ip_address ? escapeHtml(entry.ip_address) : '';

        let changesHtml = '';
        if (entry.changes && entry.changes.length) {
            changesHtml = '<table class="table table-sm mb-0 mt-2" style="font-size:12px;"><tbody>';
            entry.changes.forEach(c => {
                changesHtml += '<tr>'
                    + '<td style="width:32%;color:#646970;">' + escapeHtml(c.label || c.field || '') + '</td>'
                    + '<td style="color:#b32d2e;text-decoration:line-through;">' + escapeHtml(c.from != null ? c.from : '—') + '</td>'
                    + '<td style="color:#1d7a3c;font-weight:600;">' + escapeHtml(c.to != null ? c.to : '—') + '</td>'
                    + '</tr>';
            });
            changesHtml += '</tbody></table>';
        }

        return '<div style="border-left:3px solid #d4af37;background:#faf3dc;border-radius:0 8px 8px 0;padding:10px 12px;margin-bottom:10px;">'
            + '<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">'
            +   '<strong><i class="fas fa-user-shield" style="color:#8a6d1f;margin-right:4px;"></i>' + adminName + '</strong>'
            +   '<span style="font-size:12px;color:#5c5543;font-family:monospace;">' + when + '</span>'
            + '</div>'
            + '<div style="margin-top:4px;font-size:12px;">'
            +   '<span style="font-family:monospace;font-size:11px;font-weight:600;background:#fbf8f1;border:1px solid #b9a67f;border-radius:5px;padding:2px 8px;color:#0f1f17;">' + actionLabel + '</span> '
            +   '<span>' + summary + '</span>'
            + '</div>'
            + changesHtml
            + '<div style="font-size:11px;color:#8c8f94;margin-top:6px;">'
            +   (source ? 'via ' + source : '') + (ip ? ' · IP ' + ip : '')
            + '</div>'
            + '</div>';
    }

    function openEditHistory(id, trackingNumber) {
        $('#edit-history-tracking').text(trackingNumber || ('#' + id));
        $('#edit-history-meta').text('Loading…');
        $('#edit-history-body').html('');
        $('#edit-history-modal').css('display', 'flex').show();

        $.post(ybt_admin.ajax_url, {
            action: 'ybt_get_tracking_edit_history',
            nonce: ybt_admin.nonce,
            tracking_id: id
        }, function(res) {
            if (!res || !res.success) {
                $('#edit-history-meta').text('Could not load history.');
                $('#edit-history-body').html('<p class="text-danger">' + escapeHtml((res && res.data) || 'Unknown error') + '</p>');
                return;
            }

            const data = res.data;
            const entries = data.entries || [];
            $('#edit-history-meta').html('Tracking <strong>' + escapeHtml(data.tracking_number || '') + '</strong> · '
                + entries.length + ' recorded edit(s) · showing newest first');

            if (!entries.length) {
                $('#edit-history-body').html('<p class="text-muted">No admin edits recorded for this tracking number yet.</p>');
                return;
            }

            $('#edit-history-body').html(entries.map(renderEditHistoryEntry).join(''));
        }).fail(function() {
            $('#edit-history-meta').text('Server error while loading history.');
        });
    }

    $(document).on('click', '.view-edit-history', function(e) {
        e.preventDefault();
        openEditHistory($(this).data('id'), $(this).data('tracking'));
    });

    // Close the history modal when clicking the dark backdrop.
    $('#edit-history-modal').on('click', function(e) {
        if (e.target === this) $(this).hide();
    });

    $('#image-upload-form').submit(function(e) {
        e.preventDefault();
        const fd = new FormData(); fd.append('action', 'ybt_upload_image'); fd.append('nonce', ybt_admin.nonce); fd.append('tracking_id', $('#upload-tracking-id').val());
        const files = $('#upload-images')[0].files; for(let i=0; i<files.length; i++) fd.append('images[]', files[i]);
        $.ajax({ url: ybt_admin.ajax_url, type: 'POST', data: fd, processData:false, contentType:false, success: function(res) { if(res.success) { alert('Uploaded'); location.reload(); } else alert(escapeHtml(res.data)); }});
    });

    $('.view-images').click(function() {
        const imagesRaw = $(this).data('images');
        if (!imagesRaw) return;
        const images = String(imagesRaw).split(',');
        const tId = $(this).closest('tr').data('id');
        let html = '';
        images.forEach(img => {
            const trimmedImg = img.trim();
            if(trimmedImg) {
                const safeImg = escapeHtml(trimmedImg);
                const safeId = escapeHtml(tId);
                html += `
                    <div class="mb-3 p-2 border rounded">
                        <img src="${safeImg}" style="max-width:100%;max-height:300px;display:block;margin:0 auto 10px;">
                        <div class="text-center gap-2">
                            <a href="${safeImg}" target="_blank" class="button button-small">Download</a>
                            <button class="button button-small edit-image" data-tracking-id="${safeId}" data-image-url="${safeImg}">Edit</button>
                            <button class="button button-small button-link-delete delete-single-image text-danger" data-tracking-id="${safeId}" data-image-url="${safeImg}">Delete</button>
                        </div>
                    </div>`;
            }
        });
        $('#image-gallery').html(html || '<p class="text-muted">No images found.</p>');
        $('#image-viewer-modal').css('display', 'flex').show();
    });

    $(document).on('click', '.edit-image', function() {
        $('#edit-tracking-id').val($(this).data('tracking-id')); $('#edit-old-image-url').val($(this).data('image-url')); $('#current-image-preview').attr('src', $(this).data('image-url')); $('#image-edit-modal').css('display', 'flex').show();
    });

    $('#image-edit-form').submit(function(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'ybt_edit_image');
        fd.append('nonce', ybt_admin.nonce);
        fd.append('tracking_id', $('#edit-tracking-id').val());
        fd.append('old_image_url', $('#edit-old-image-url').val());
        fd.append('new_image', $('#edit-new-image')[0].files[0]);
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: fd,
            processData:false,
            contentType:false,
            success: function(res) {
                if(res.success) { alert('Updated successfully.'); location.reload(); } else alert('Error: ' + escapeHtml(res.data));
            },
            error: function() { alert('A server error occurred during image edit.'); }
        });
    });

    $(document).on('click', '.delete-single-image', function() {
        if(confirm('Delete image?')) {
            const btn = $(this);
            $.post(ybt_admin.ajax_url, {
                action: 'ybt_delete_image',
                nonce: ybt_admin.nonce,
                tracking_id: btn.data('tracking-id'),
                image_url: btn.data('image-url')
            }, function(res) {
                if(res.success) {
                    btn.closest('div.mb-3').fadeOut(function() {
                        $(this).remove();
                        if($('#image-gallery').children().length === 0) location.reload();
                    });
                } else alert('Error: ' + escapeHtml(res.data));
            }).fail(function() { alert('Failed to delete image.'); });
        }
    });

    $('#attach-cartons-selected').click(function() {
        const ids = loadSelectedIds();
        if(ids.length===0) { alert('Select items'); return; }
        $('#attach-selected-ids').val(ids.join(',')); $('#attach-selected-count').text(ids.length); $('#attach-cartons-selected-modal').css('display','flex').show();
    });

    $('#generate-tracking-carton-fields').click(function() {
        const c = parseInt($('#attach-selected-cartons').val()); if(!c) return;
        const container = $('#tracking-carton-fields-container');
        container.empty();
        for(let i=1; i<=c; i++) {
            const div = $('<div>').addClass('p-2 mb-2 border rounded bg-light');
            div.append($('<strong>').text('Carton ' + i));
            div.append('<br>');
            div.append($('<label>').addClass('small').text('Images'));
            div.append($('<input>').attr({type: 'file', name: 'carton_image_' + i + '[]', multiple: true}).addClass('form-control'));
            container.append(div);
        }
    });

    $('#attach-cartons-selected-form').submit(function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'ybt_attach_cartons_to_ids_v2');
        fd.append('nonce', ybt_admin.nonce);
        fd.append('tracking_ids', $('#attach-selected-ids').val());

        const c = parseInt($('#attach-selected-cartons').val());
        const w = parseFloat($('#attach-selected-weight').val());
        const p = parseFloat($('#attach-selected-payment').val());
        const cur = $('#attach-selected-currency').val();

        if (c > 0 && !isNaN(w) && !isNaN(p)) {
            const wPer = (w/c).toFixed(2);
            const pPer = (p/c).toFixed(2);
            const cartonData = [];
            for(let i=0; i<c; i++) {
                cartonData.push({
                    weight: wPer,
                    amount: pPer,
                    currency: cur
                });
            }
            fd.append('carton_data', JSON.stringify(cartonData));
        }

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: fd,
            processData:false,
            contentType:false,
            success: function(res) {
                if(res.success) { alert('Attached successfully!'); clearSelectedIds(); location.reload(); } else alert('Error: ' + escapeHtml(res.data));
            },
            error: function() { alert('A server error occurred during attachment.'); }
        });
    });

    // View Selected
    $('#view-selected-items').click(function() {
        const ids = loadSelectedIds();
        if(ids.length === 0) {
            $('#selected-items-list').html('<p class="text-center text-muted">None selected</p>');
        } else {
            $('#selected-items-list').html('<p class="text-center">Loading...</p>');
            $.post(ybt_admin.ajax_url, {
                action: 'ybt_get_tracking_details_batch',
                nonce: ybt_admin.nonce,
                tracking_ids: ids.join(',')
            }, function(res) {
                if (res.success) {
                    let table = $('<table>').addClass('table admin-table');
                    table.append('<thead><tr><th>Tracking</th><th>User</th><th>Action</th></tr></thead>');
                    let tbody = $('<tbody>');
                    res.data.forEach(function(i) {
                        let tr = $('<tr>');
                        tr.append($('<td>').append($('<strong>').text(i.tracking_number)));
                        tr.append($('<td>').text(i.username));
                        tr.append($('<td>').append($('<button>').addClass('button button-small remove-selection text-danger').attr('data-id', i.id).text('Remove')));
                        tbody.append(tr);
                    });
                    table.append(tbody);
                    $('#selected-items-list').empty().append(table);
                } else {
                    $('#selected-items-list').html('<p class="text-danger text-center">Failed to load details.</p>');
                }
            }).fail(function() { $('#selected-items-list').html('<p class="text-danger text-center">Server error.</p>'); });
        }
        $('#view-selected-modal').css('display', 'flex').show();
    });

    $(document).on('click', '.remove-selection', function() {
        const id = $(this).data('id');
        toggleSelection(id, false);
        $('input.item-checkbox[value="' + id + '"]').prop('checked', false);
        $(this).closest('tr').fadeOut(function() { $(this).remove(); });
        if(loadSelectedIds().length===0) $('.close-modal').click();
    });

    // Initialize DataTable with search functionality
    if (typeof YBT !== 'undefined' && YBT.DataTable) {
        const trackingTable = document.querySelector('table.admin-table');
        if (trackingTable) {
            new YBT.DataTable(trackingTable, {
                searchable: true,
                sortable: true,
                pagination: true,
                perPage: 25
            });
        }
    }
});
</script>
