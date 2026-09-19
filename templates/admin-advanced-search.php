<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'your-text-domain')); // Added text domain for translation
}

// Enqueue necessary scripts and styles
function ybt_advanced_search_enqueue_scripts($hook) {
    if ($hook != 'toplevel_page_your-menu-slug') { // Replace with your actual menu slug
        return;
    }

    wp_enqueue_style('wp-components'); // For buttons and form controls
    wp_enqueue_style('ybt-admin-styles', plugin_dir_url(__FILE__) . 'admin-styles.css', array(), '1.0.0');
    wp_enqueue_script('ybt-admin-script', plugin_dir_url(__FILE__) . 'admin-script.js', array('jquery'), '1.0.0', true);

    wp_localize_script('ybt-admin-script', 'ybt_admin', array(
        'nonce' => wp_create_nonce('ybt_admin_nonce'),
        'ajax_url' => admin_url('admin-ajax.php'),
        'loading_text' => __('Searching...', 'your-text-domain'),
        'no_results_text' => __('No results found matching your criteria.', 'your-text-domain'),
        'error_text' => __('An error occurred. Please try again.', 'your-text-domain')
    ));
}
add_action('admin_enqueue_scripts', 'ybt_advanced_search_enqueue_scripts');

// AJAX handler for advanced search
function ybt_handle_advanced_search() {
    // Verify nonce
    check_ajax_referer('ybt_admin_nonce', 'nonce');

    // Get and sanitize input data
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
    $tracking = isset($_POST['tracking']) ? sanitize_text_field($_POST['tracking']) : '';

    // Validate date range if both dates are provided
    if (!empty($date_from) && !empty($date_to)) {
        if (strtotime($date_from) > strtotime($date_to)) {
            wp_send_json_error(__('Invalid date range. "Date From" must be before "Date To".', 'your-text-domain'));
        }
    }

    // Here you would typically query your database or perform your search logic
    // For demonstration, I'm using mock data
    $mock_data = array();

    // Generate some mock results based on search criteria
    $num_results = 5; // Normally this would come from your actual query

    for ($i = 0; $i < $num_results; $i++) {
        $mock_data[] = array(
            'tracking_number' => 'TRK' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
            'username' => 'user_' . rand(1, 100) . '@example.com',
            'status' => $status ? $status : array_rand(array_flip(array(
                'not_received', 'received', 'packing_in_progress', 'packed', 'completed'
            ))),
            'date_added' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            'date_received' => (rand(0, 1) ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' days')) : '-'),
            'description' => 'Package containing various items ' . ($i + 1)
        );
    }

    // Filter mock data based on search criteria (simplified for demo)
    $filtered_data = array();

    foreach ($mock_data as $item) {
        $include = true;

        // Date from filter
        if (!empty($date_from) && $item['date_added'] < $date_from . ' 00:00:00') {
            $include = false;
        }

        // Date to filter
        if (!empty($date_to) && $item['date_added'] > $date_to . ' 23:59:59') {
            $include = false;
        }

        // Status filter
        if (!empty($status) && $item['status'] !== $status) {
            $include = false;
        }

        // Username filter
        if (!empty($username) && stripos($item['username'], $username) === false) {
            $include = false;
        }

        // Tracking number filter
        if (!empty($tracking) && stripos($item['tracking_number'], $tracking) === false) {
            $include = false;
        }

        if ($include) {
            $filtered_data[] = $item;
        }
    }

    if (!empty($filtered_data)) {
        wp_send_json_success($filtered_data);
    } else {
        wp_send_json_error(__('No results found matching your criteria.', 'your-text-domain'));
    }
}

add_action('wp_ajax_ybt_advanced_search', 'ybt_handle_advanced_search');

// Main function to display the admin page
function ybt_display_advanced_search_page() {
    ?>
    <div class="wrap">
        <h1>🔍 Advanced Search</h1>

        <div class="card" style="max-width: 1200px; margin: 0 auto; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
            <form id="advanced-search-form">
                <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
                    <div class="col-md-3" style="flex: 0 0 calc(25% - 15px); min-width: 200px;">
                        <label for="date_from" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Date From', 'your-text-domain'); ?></label>
                        <input type="date" id="date_from" name="date_from" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    </div>
                    <div class="col-md-3" style="flex: 0 0 calc(25% - 15px); min-width: 200px;">
                        <label for="date_to" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Date To', 'your-text-domain'); ?></label>
                        <input type="date" id="date_to" name="date_to" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    </div>
                    <div class="col-md-2" style="flex: 0 0 calc(16.666% - 14px); min-width: 150px;">
                        <label for="status" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Status', 'your-text-domain'); ?></label>
                        <select id="status" name="status" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;">
                            <option value=""><?php _e('All', 'your-text-domain'); ?></option>
                            <option value="not_received"><?php _e('Not Received', 'your-text-domain'); ?></option>
                            <option value="received"><?php _e('Received', 'your-text-domain'); ?></option>
                            <option value="packing_in_progress"><?php _e('Packing', 'your-text-domain'); ?></option>
                            <option value="packed"><?php _e('Packed', 'your-text-domain'); ?></option>
                            <option value="completed"><?php _e('Completed', 'your-text-domain'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-2" style="flex: 0 0 calc(16.666% - 14px); min-width: 150px;">
                        <label for="username" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Username', 'your-text-domain'); ?></label>
                        <input type="text" id="username" name="username" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;" placeholder="<?php _e('Search username', 'your-text-domain'); ?>">
                    </div>
                    <div class="col-md-2" style="flex: 0 0 calc(16.666% - 14px); min-width: 150px;">
                        <label for="tracking" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Tracking #', 'your-text-domain'); ?></label>
                        <input type="text" id="tracking" name="tracking" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;" placeholder="<?php _e('Search tracking', 'your-text-domain'); ?>">
                    </div>
                </div>
                <div class="row mt-3" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
                    <div class="col-md-12" style="flex: 1 1 100%;">
                        <button type="submit" class="button button-primary" style="padding: 8px 16px; background: #007cba; color: white; border: none; border-radius: 4px; margin-right: 10px;">
                            <?php _e('Search', 'your-text-domain'); ?>
                        </button>
                        <button type="button" class="button button-secondary" style="padding: 8px 16px; background: #f1f1f1; color: #333; border: 1px solid #ccd0d4; border-radius: 4px; margin-right: 10px;" onclick="document.getElementById('advanced-search-form').reset();">
                            <?php _e('Clear', 'your-text-domain'); ?>
                        </button>
                        <button type="button" class="button button-secondary" style="padding: 8px 16px; background: #f1f1f1; color: #333; border: 1px solid #ccd0d4; border-radius: 4px;" id="export-results">
                            <?php _e('Export Results', 'your-text-domain'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div id="search-results" style="margin-top: 20px;"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#advanced-search-form').submit(function(e) {
            e.preventDefault();

            var $resultsDiv = $('#search-results');
            $resultsDiv.html('<p class="info" style="padding: 20px; text-align: center; color: #666;">' + ybt_admin.loading_text + '</p>');

            $.ajax({
                url: ybt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ybt_advanced_search',
                    nonce: ybt_admin.nonce,
                    date_from: $('#date_from').val(),
                    date_to: $('#date_to').val(),
                    status: $('#status').val(),
                    username: $('#username').val(),
                    tracking: $('#tracking').val()
                },
                success: function(response) {
                    if (response.success && response.data) {
                        displayResults(response.data);
                    } else {
                        var errorMsg = response.data || ybt_admin.error_text;
                        $resultsDiv.html('<div class="notice notice-error" style="padding: 15px; border-left: 4px solid #dc3232; background: #fdfdfd; border: 1px solid #f5c6cb; color: #822727;"><p>' + errorMsg + '</p></div>');
                    }
                },
                error: function() {
                    $resultsDiv.html('<div class="notice notice-error" style="padding: 15px; border-left: 4px solid #dc3232; background: #fdfdfd; border: 1px solid #f5c6cb; color: #822727;"><p>' + ybt_admin.error_text + '</p></div>');
                }
            });
        });

        function displayResults(data) {
            if (!data || data.length === 0) {
                $('#search-results').html('<p style="padding: 20px; text-align: center; color: #666;">' + ybt_admin.no_results_text + '</p>');
                return;
            }

            var html = '<div class="card" style="margin-top:20px; padding:0; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;"><table class="wp-list-table widefat striped mb-0" style="width: 100%; border-collapse: collapse; margin: 0;">';
            html += '<thead style="background: #f1f1f1;"><tr>';
            html += '<th style="padding: 12px 8px; text-align: left; border: 1px solid #ccd0d4; font-weight: 600;"><?php _e('Tracking #', 'your-text-domain'); ?></th>';
            html += '<th style="padding: 12px 8px; text-align: left; border: 1px solid #ccd0d4; font-weight: 600;"><?php _e('Username', 'your-text-domain'); ?></th>';
            html += '<th style="padding: 12px 8px; text-align: left; border: 1px solid #ccd0d4; font-weight: 600;"><?php _e('Status', 'your-text-domain'); ?></th>';
            html += '<th style="padding: 12px 8px; text-align: left; border: 1px solid #ccd0d4; font-weight: 600;"><?php _e('Date Added', 'your-text-domain'); ?></th>';
            html += '<th style="padding: 12px 8px; text-align: left; border: 1px solid #ccd0d4; font-weight: 600;"><?php _e('Date Received', 'your-text-domain'); ?></th>';
            html += '<th style="padding: 12px 8px; text-align: left; border: 1px solid #ccd0d4; font-weight: 600;"><?php _e('Description', 'your-text-domain'); ?></th>';
            html += '</tr></thead><tbody>';

            $.each(data, function(index, item) {
                html += '<tr>';
                html += '<td style="padding: 12px 8px; border: 1px solid #ccd0d4;"><strong>' + escapeHtml(item.tracking_number) + '</strong></td>';
                html += '<td style="padding: 12px 8px; border: 1px solid #ccd0d4;">' + escapeHtml(item.username || 'Anonymous') + '</td>';
                html += '<td style="padding: 12px 8px; border: 1px solid #ccd0d4;"><span class="badge-glass" style="background: #e1e5e9; color: #444; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: normal;">' + escapeHtml(String(item.status || '').replace(/_/g, ' ')) + '</span></td>';
                html += '<td style="padding: 12px 8px; border: 1px solid #ccd0d4;">' + escapeHtml(item.date_added || '-') + '</td>';
                html += '<td style="padding: 12px 8px; border: 1px solid #ccd0d4;">' + escapeHtml(item.date_received || '-') + '</td>';
                html += '<td style="padding: 12px 8px; border: 1px solid #ccd0d4;">' + escapeHtml(item.description || '-') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            $('#search-results').html(html);
        }

        // Simple HTML escape function
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });
    </script>
    <?php
}

// Call the function to display the page (this would normally be called from your menu registration)
ybt_display_advanced_search_page();
