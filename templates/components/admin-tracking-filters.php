<?php
if (!defined('ABSPATH')) exit;

function render_tracking_filters() {
    if (!current_user_can('manage_options')) return;

    global $wpdb;

    // Sanitize GET Parameters
    $status_val = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $shipping_val = isset($_GET['shipping_method']) ? sanitize_text_field($_GET['shipping_method']) : '';
    $user_val = isset($_GET['username']) ? sanitize_text_field($_GET['username']) : '';
    $search_val = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $images_val = isset($_GET['has_images']) ? sanitize_text_field($_GET['has_images']) : '';

    // Fetch users securely for admin use
    $users = array();
    if (current_user_can('manage_options')) {
        $users = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT username FROM {$wpdb->prefix}ybt_tracking WHERE username IS NOT NULL AND username != %s ORDER BY username", ''));
    }
?>
<div class="ybt-card mb-4">
    <div class="ybt-card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters & Search</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label" for="statusFilter">Status Filter</label>
                <select id="statusFilter" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="not_received" <?php selected($status_val, 'not_received'); ?>>Not Received</option>
                    <option value="received" <?php selected($status_val, 'received'); ?>>Received</option>
                    <option value="packing_in_progress" <?php selected($status_val, 'packing_in_progress'); ?>>Packing in Progress</option>
                    <option value="packed" <?php selected($status_val, 'packed'); ?>>Packed</option>
                    <option value="completed" <?php selected($status_val, 'completed'); ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="shippingMethodFilter">Shipping Method</label>
                <select id="shippingMethodFilter" class="form-select">
                    <option value="">All Methods</option>
                    <option value="air" <?php selected($shipping_val, 'air'); ?>>Air</option>
                    <option value="sea" <?php selected($shipping_val, 'sea'); ?>>Sea</option>
                    <option value="anonymous" <?php selected($shipping_val, 'anonymous'); ?>>Anonymous/Unknown</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="userFilter">User Filter</label>
                <select id="userFilter" class="form-select">
                    <option value="">All Users</option>
                    <option value="anonymous" <?php selected($user_val, 'anonymous'); ?>>Anonymous</option>
                    <?php if (current_user_can('manage_options')): ?>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->username); ?>" <?php selected($user_val, $user->username); ?>>
                                <?php echo esc_html($user->username); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="imagesFilter">Images Filter</label>
                <select id="imagesFilter" class="form-select">
                    <option value="">All Items</option>
                    <option value="no" <?php selected($images_val, 'no'); ?>>Without Images</option>
                    <option value="yes" <?php selected($images_val, 'yes'); ?>>With Images</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="searchInput">Search</label>
                <input type="text" id="searchInput" class="form-control" value="<?php echo esc_attr($search_val); ?>" placeholder="Search tracking numbers or usernames...">
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 text-end">
                <button type="button" class="btn btn-outline-secondary me-2" onclick="clearFilters()">
                    <i class="fas fa-times me-1"></i>Clear
                </button>
                <button type="button" class="btn btn-primary px-4" onclick="applyFilters()">
                    <i class="fas fa-search me-1"></i>Apply Filters
                </button>
            </div>
        </div>
    </div>
</div>
<?php
}
