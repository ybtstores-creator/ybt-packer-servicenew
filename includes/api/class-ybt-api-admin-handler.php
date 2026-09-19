<?php
/**
 * YBT API Admin General Handler
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Admin_Handler extends YBT_API_Handler_Base {

    /**
     * Admin Dashboard Stats
     */
    public function get_dashboard_stats() {
        $this->require_admin();

        global $wpdb;
        $total_users = count_users();
        $pending_packing = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE status = %s", 'packing_in_progress'
        )));
        $not_received = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE status = %s", 'not_received'
        )));
        
        // Count tracking items without images
        $without_images = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (images IS NULL OR images = %s) AND status != %s", '', 'not_received'
        )));

        $logs = array();
        $table_logs = $wpdb->prefix . 'ybt_activity_log';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_logs)) == $table_logs) {
            $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_logs} ORDER BY date_created DESC LIMIT 10"));
        }

        YBT_API_Response::success(array(
            'user_stats' => array(
                'total' => $total_users['total_users'],
                'roles' => $total_users['avail_roles']
            ),
            'order_stats' => array(
                'pending_packing' => $pending_packing,
                'not_received' => $not_received,
                'without_images' => $without_images
            ),
            'recent_activity' => $logs
        ));
    }

    /**
     * Get all users
     * FIXED: Use GET method for query parameters (Android app uses @Query annotation)
     */
    public function get_users() {
        $this->require_admin();

        global $wpdb;
        // FIXED: Explicitly use 'get' method since Android app sends query parameters via GET
        $search = $this->get_param('search', '', 'text', 'get');
        $offset = $this->get_param('offset', 0, 'int', 'get');
        $limit = $this->get_param('limit', 50, 'int', 'get');

        if (empty($search)) {
            $args = array(
                'order' => 'DESC',
                'orderby' => 'registered',
                'number' => $limit,
                'offset' => $offset,
            );
            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();
            $total = $user_query->get_total();
        } else {
            // FIXED: WP_User_Query silently drops 'search' when a meta_query is
            // present (searching by custom_id/mobile via meta_query AND by
            // login/email/display_name via 'search' returned zero results for
            // users that DO match the login). Use an explicit SQL search over
            // users LEFT JOIN usermeta so both criteria work and pagination is
            // correct.
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = $wpdb->prepare(
                "(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s OR um.meta_value LIKE %s)",
                $like, $like, $like, $like
            );
            $total = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT u.ID)
                 FROM {$wpdb->users} u
                 LEFT JOIN {$wpdb->usermeta} um
                   ON um.user_id = u.ID AND um.meta_key IN ('ybt_custom_user_id','ybt_mobile_number')
                 WHERE $where"
            )));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered
                 FROM {$wpdb->users} u
                 LEFT JOIN {$wpdb->usermeta} um
                   ON um.user_id = u.ID AND um.meta_key IN ('ybt_custom_user_id','ybt_mobile_number')
                 WHERE $where
                 ORDER BY u.user_registered DESC
                 LIMIT %d OFFSET %d",
                $limit, $offset
            ));
            $users = array();
            foreach ($rows as $row) {
                $u = new WP_User($row->ID);
                $u->user_registered = $row->user_registered;
                $users[] = $u;
            }
        }

        $user_list = array();
        foreach ($users as $user) {
            $user_list[] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'custom_id' => get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: 'Pending',
                'registered' => $user->user_registered,
                'role' => reset($user->roles)
            );
        }

        YBT_API_Response::success(array(
            'users' => $user_list,
            'total' => $total
        ));
    }

    /**
     * Update tracking status
     */
    public function update_status() {
        $this->require_admin();

        $tracking_id = $this->get_param('tracking_id', 0, 'int');
        $new_status = $this->get_param('status');

        if (!$tracking_id || empty($new_status)) {
            YBT_API_Response::error('ID and status required', 'missing_data');
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $tracking_id));
        if (!$item) YBT_API_Response::error('Item not found', 'not_found', 404);

        $update_data = array('status' => $new_status);
        
        if ($new_status === 'received' && empty($item->date_received)) {
            $update_data['date_received'] = current_time('mysql');
        } elseif ($new_status === 'completed' && empty($item->date_completed)) {
            $update_data['date_completed'] = current_time('mysql');
        }

        $updated = YBT_Tracking_Audit::apply_update(
            $tracking_id,
            $update_data,
            'status_change',
            array('before' => $item, 'admin_id' => $this->user_id, 'source' => 'mobile')
        );

        if ($updated !== false) {
            if (function_exists('ybt_log_activity')) {
                ybt_log_activity($this->user_id, 'admin_update_status', "Updated tracking $item->tracking_number to $new_status");
            }
            if ($item->user_id && class_exists('YBT_Notifications')) {
                YBT_Notifications::send_tracking_update($item->user_id, $item->tracking_number, $new_status);
            }
            YBT_API_Response::success(null, 'Status updated successfully');
        } else {
            YBT_API_Response::error('Update failed', 'update_failed');
        }
    }

    /**
     * Scan Barcode
     */
    public function scan_barcode() {
        $this->require_admin();

        $barcode = $this->get_param('barcode');
        if (empty($barcode)) {
            $barcode = $this->get_param('tracking_code'); // Legacy param from admin_api.php
        }

        $barcode = strtoupper(trim($barcode));
        if (empty($barcode)) YBT_API_Response::error('No barcode scanned', 'missing_barcode');

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = UPPER(%s)",
            $barcode
        ));

        if (!$item) {
            // Add as anonymous
            $result = $wpdb->insert($wpdb->prefix . 'ybt_tracking', array(
                'tracking_number' => $barcode,
                'user_id' => 0,
                'username' => 'Anonymous',
                'status' => 'received',
                'date_added' => current_time('mysql'),
                'date_received' => current_time('mysql'),
                'shipping_method' => 'sea',
                'item_count' => 1
            ));

            if ($result) {
                YBT_Tracking_Audit::log_edit($wpdb->insert_id, 'create', array(), array(
                    'summary'         => 'Tracking number created (mobile scan)',
                    'tracking_number' => $barcode,
                    'admin_id'        => $this->user_id,
                    'source'          => 'mobile',
                    'log_activity'    => false,
                ));
                if (function_exists('ybt_log_activity')) {
                    ybt_log_activity($this->user_id, 'mobile_scan_add', "Mobile scan: Added NEW anonymous parcel: $barcode");
                }
                YBT_API_Response::success(array(
                    'tracking_number' => $barcode,
                    'username' => 'Anonymous',
                    'status' => 'received',
                    'is_new' => true
                ), 'New Parcel Added as Anonymous');
            } else {
                YBT_API_Response::error('Failed to add new parcel', 'insert_failed');
            }
        }

        if ($item->status === 'not_received') {
            YBT_Tracking_Audit::apply_update(
                $item->id,
                array('status' => 'received', 'date_received' => current_time('mysql')),
                'status_change',
                array('before' => $item, 'admin_id' => $this->user_id, 'source' => 'mobile', 'log_activity' => false)
            );
            if ($item->user_id && class_exists('YBT_Notifications')) {
                YBT_Notifications::send_tracking_update($item->user_id, $item->tracking_number, 'received');
            }
            YBT_API_Response::success(array('tracking_number' => $barcode, 'status' => 'received'), 'Marked as Received');
        }

        YBT_API_Response::success(array('tracking_number' => $barcode, 'status' => $item->status), 'Item found');
    }

    /**
     * Admin: Get User Details
     * FIXED: Use GET method for query parameters (Android app uses @Query annotation)
     */
    public function get_user_details() {
        $this->require_admin();

        // FIXED: Explicitly use 'get' method since Android app sends query parameters via GET
        $target_user_id = $this->get_param('target_user_id', 0, 'int', 'get');
        if (!$target_user_id) YBT_API_Response::error('User ID required', 'missing_id');

        global $wpdb;
        $user = get_userdata($target_user_id);
        if (!$user) YBT_API_Response::error('User not found', 'not_found', 404);

        $custom_id = get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: 'Pending';

        $parcels = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE user_id = %d ORDER BY date_added DESC LIMIT 10",
            $target_user_id
        ));

        $batches = $wpdb->get_results($wpdb->prepare(
            "SELECT batch_id, COUNT(*) as item_count, MAX(date_packing_requested) as request_date, status
             FROM {$wpdb->prefix}ybt_tracking
             WHERE user_id = %d AND batch_id IS NOT NULL AND batch_id != ''
             GROUP BY batch_id
             ORDER BY request_date DESC LIMIT 5",
            $target_user_id
        ));

        YBT_API_Response::success(array(
            'profile' => array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'custom_id' => $custom_id,
                'registered' => $user->user_registered
            ),
            'recent_parcels' => $parcels,
            'recent_batches' => $batches
        ));
    }

    /**
     * Admin: Reset User Password
     */
    public function reset_password() {
        $this->require_admin();

        $target_user_id = $this->get_param('target_user_id', 0, 'int');
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

        if (!$target_user_id || empty($new_password)) {
            YBT_API_Response::error('User ID and new password required', 'missing_data');
        }

        if (strlen($new_password) < 8) {
            YBT_API_Response::error('Password must be at least 8 characters', 'weak_password');
        }

        if (!get_userdata($target_user_id)) YBT_API_Response::error('User not found', 'not_found', 404);

        wp_set_password($new_password, $target_user_id);
        YBT_API_Response::success(null, 'Password reset successfully');
    }

    /**
     * Admin: Get All Orders/Tracking
     * FIXED: Use GET method for query parameters (Android app uses @Query annotation)
     */
    public function get_orders() {
        $this->require_admin();

        global $wpdb;
        // FIXED: Explicitly use 'get' method since Android app sends query parameters via GET
        $status = $this->get_param('status', '', 'text', 'get');
        $search = $this->get_param('search', '', 'text', 'get');
        $offset = $this->get_param('offset', 0, 'int', 'get');
        $user_id_filter = $this->get_param('user_id', 0, 'int', 'get');
        $has_images = $this->get_param('has_images', '', 'text', 'get'); // New filter: 'yes' or 'no'
        $limit = 20;

        $where = "1=1";
        $params = array();

        if (!empty($status) && $status !== 'all') {
            $where .= " AND status = %s";
            $params[] = $status;
        }

        if ($user_id_filter > 0) {
            $where .= " AND user_id = %d";
            $params[] = $user_id_filter;
        }

        // Filter by images status
        if ($has_images === 'no') {
            $where .= " AND (images IS NULL OR images = '')";
        } elseif ($has_images === 'yes') {
            $where .= " AND images IS NOT NULL AND images != ''";
        }

        if (!empty($search)) {
            $where .= " AND (tracking_number LIKE %s OR description LIKE %s OR username LIKE %s)";
            $wild = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE $where ORDER BY date_added DESC LIMIT %d, %d",
            array_merge($params, array($offset, $limit))
        ));

        // Ensure ID is always returned as integer for Android app compatibility
        if (!empty($items)) {
            foreach ($items as &$item) {
                $item->id = intval($item->id);
                $item->user_id = intval($item->user_id);
                $item->item_count = intval($item->item_count);
            }
            unset($item); // Break reference
        }

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE $where",
            $params
        ));

        YBT_API_Response::success(array(
            'orders' => $items,
            'total' => intval($total)
        ));
    }

    /**
     * Admin: Add Tracking in Bulk
     */
    public function add_tracking_bulk() {
        $this->require_admin();

        $target_user_id = $this->get_param('target_user_id', 0, 'int');
        $tracking_input = $this->get_param('tracking_numbers');
        $description = $this->get_param('description', '');
        $item_count = $this->get_param('item_count', 1, 'int');
        $shipping_method = $this->get_param('shipping_method', 'sea');

        if (empty($tracking_input)) {
            YBT_API_Response::error('Tracking numbers are required', 'missing_tracking');
        }

        $tracking_input = trim($tracking_input);
        if (empty($tracking_input)) {
            YBT_API_Response::error('Tracking numbers cannot be empty', 'empty_tracking');
        }

        $target_user = null;
        if ($target_user_id > 0) {
            $target_user = get_userdata($target_user_id);
            if (!$target_user) YBT_API_Response::error('Target user not found', 'not_found', 404);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_tracking';
        $tracking_numbers = array_map('trim', explode(',', $tracking_input));
        $tracking_numbers = array_filter($tracking_numbers);
        
        if (empty($tracking_numbers)) {
            YBT_API_Response::error('No valid tracking numbers found', 'invalid_tracking');
        }
        
        $added_count = 0;
        $errors = array();
        $uploaded_images = array();

        // Handle image uploads if present
        $files = !empty($_FILES['images']) ? $_FILES['images'] : (!empty($_FILES['tracking_images']) ? $_FILES['tracking_images'] : null);
        
        if ($files) {
            $file_count = is_array($files['name']) ? count($files['name']) : 0;
            
            for ($i = 0; $i < $file_count; $i++) {
                $file_data = array(
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i]
                );
                
                if ($file_data['error'] === UPLOAD_ERR_OK) {
                    if (function_exists('ybt_security')) {
                        $upload = ybt_security()->secure_file_upload($file_data);
                    } else {
                        if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');
                        $upload = wp_handle_upload($file_data, array('test_form' => false));
                    }
                    
                    if (isset($upload['url'])) {
                        $uploaded_images[] = $upload['url'];
                    }
                }
            }
        }
        
        $images_string = !empty($uploaded_images) ? implode(',', $uploaded_images) : '';

        foreach ($tracking_numbers as $number) {
            $number = strtoupper(trim($number));
            if (empty($number)) continue;

            $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE UPPER(tracking_number) = UPPER(%s)", $number));

            if ($exists) {
                // Combine existing images with new ones if any
                $all_images = $exists->images ? explode(',', $exists->images) : array();
                if (!empty($uploaded_images)) {
                    $all_images = array_merge($all_images, $uploaded_images);
                    $all_images = array_unique(array_filter(array_map('trim', $all_images)));
                }

                $update_data = array(
                    'status' => 'received', 
                    'date_received' => current_time('mysql'),
                    'images' => implode(',', $all_images)
                );
                
                if ($target_user) {
                    $update_data['user_id'] = $target_user_id;
                    $update_data['username'] = $target_user->user_login;
                }
                if (YBT_Tracking_Audit::apply_update($exists->id, $update_data, 'mobile_update', array(
                    'before'   => $exists,
                    'admin_id' => $this->user_id,
                    'source'   => 'mobile',
                    'log_activity' => false,
                )) !== false) {
                    $added_count++;
                } else {
                    $errors[] = "Failed to update $number";
                }
            } else {
                $insert_data = array(
                    'tracking_number' => $number,
                    'description' => $description,
                    'item_count' => $item_count,
                    'status' => 'received',
                    'shipping_method' => $shipping_method,
                    'date_added' => current_time('mysql'),
                    'date_received' => current_time('mysql'),
                    'user_id' => $target_user ? $target_user_id : 0,
                    'username' => $target_user ? $target_user->user_login : 'Anonymous',
                    'images' => $images_string
                );
                if ($wpdb->insert($table_name, $insert_data)) {
                    $added_count++;
                    YBT_Tracking_Audit::log_edit($wpdb->insert_id, 'create', array(), array(
                        'summary'         => 'Tracking number created (mobile app)',
                        'tracking_number' => $number,
                        'admin_id'        => $this->user_id,
                        'source'          => 'mobile',
                        'log_activity'    => false,
                    ));
                } else {
                    $errors[] = "Failed to insert $number";
                }
            }
        }

        $message = "Successfully processed $added_count parcel(s)";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode(', ', $errors);
        }
        
        // Android expects a String for 'data' in most Bulk/Add actions
        YBT_API_Response::success($message, $message);
    }

    /**
     * Admin: Assign Parcel
     */
    public function assign_parcel() {
        $this->require_admin();

        $tracking_id = $this->get_param('tracking_id', 0, 'int');
        if (!$tracking_id) {
            $tracking_id = $this->get_param('id', 0, 'int');
        }

        $target_custom_id = $this->get_param('target_custom_id', '', 'text');
        if (empty($target_custom_id)) $target_custom_id = $this->get_param('custom_id', '', 'text');
        if (empty($target_custom_id)) $target_custom_id = $this->get_param('target_user_id', '', 'text');
        if (empty($target_custom_id)) $target_custom_id = $this->get_param('user_id', '', 'text');

        if (!$tracking_id || empty($target_custom_id)) {
            YBT_API_Response::error('Tracking ID and Target Customer ID required', 'missing_data');
        }

        global $wpdb;

        // Verify tracking item exists first
        $tracking_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
            $tracking_id
        ));

        if (!$tracking_item) {
            YBT_API_Response::error('Parcel record not found in database (ID: ' . $tracking_id . ')', 'not_found', 404);
        }

        $target_user = null;
        $target_custom_id_clean = trim($target_custom_id);

        // 1. Try finding user by YBT Custom User ID (meta_key = 'ybt_custom_user_id')
        $user_meta_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ybt_custom_user_id' AND (LOWER(meta_value) = LOWER(%s) OR meta_value = %s) LIMIT 1",
            $target_custom_id_clean,
            $target_custom_id_clean
        ));

        if (!empty($user_meta_rows)) {
            $target_user = get_userdata($user_meta_rows[0]->user_id);
        }

        // 2. Try parsing formatted YBT prefix or padded numeric ID (e.g. YBT0005 -> 5 or YBT5 -> YBT0005)
        if (!$target_user && preg_match('/^YBT0*(\d+)$/i', $target_custom_id_clean, $matches)) {
            $numeric_id = intval($matches[1]);
            $padded_meta = 'YBT' . str_pad($numeric_id, 4, '0', STR_PAD_LEFT);
            $user_meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'ybt_custom_user_id' AND (meta_value = %s OR meta_value = %s) LIMIT 1",
                $padded_meta,
                (string)$numeric_id
            ));
            if (!empty($user_meta_rows)) {
                $target_user = get_userdata($user_meta_rows[0]->user_id);
            }
            if (!$target_user) {
                $target_user = get_userdata($numeric_id);
            }
        }

        // 3. If not found by custom ID, try numeric User ID
        if (!$target_user && is_numeric($target_custom_id_clean)) {
            $target_user = get_userdata(intval($target_custom_id_clean));
        }

        // 4. If not found, try username / user_login
        if (!$target_user) {
            $target_user = get_user_by('login', $target_custom_id_clean);
        }

        // 5. If not found, try email
        if (!$target_user) {
            $target_user = get_user_by('email', $target_custom_id_clean);
        }

        if (!$target_user) {
            YBT_API_Response::error('Customer not found for identifier: ' . $target_custom_id_clean, 'not_found', 404);
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'ybt_tracking',
            array('user_id' => $target_user->ID, 'username' => $target_user->user_login),
            array('id' => $tracking_id),
            array('%d', '%s'),
            array('%d')
        );

        if ($updated !== false) {
            YBT_API_Response::success(
                array(
                    'assigned_user' => $target_user->user_login,
                    'user_id' => $target_user->ID,
                    'custom_id' => get_user_meta($target_user->ID, 'ybt_custom_user_id', true) ?: 'Pending'
                ),
                'Parcel assigned to ' . $target_user->user_login . ' successfully'
            );
        } else {
            $db_err = !empty($wpdb->last_error) ? $wpdb->last_error : 'Database update returned false';
            YBT_API_Response::error('Failed to assign parcel in database: ' . $db_err, 'update_failed');
        }
    }

    /**
     * Admin: Upload Tracking Image
     */
    public function upload_image() {
        $this->require_admin();

        $tracking_id = $this->get_param('tracking_id', 0, 'int');
        if (!$tracking_id) {
            YBT_API_Response::error('Tracking ID required', 'missing_id');
        }

        // Check if file was uploaded - handle both $_FILES and php://input
        $image_data = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_data = $_FILES['image'];
        } elseif (!empty($_FILES) && isset($_FILES['image'])) {
            YBT_API_Response::error('File upload error: code ' . $_FILES['image']['error'], 'upload_error');
        } else {
            YBT_API_Response::error('No image uploaded', 'missing_file');
        }

        global $wpdb;
        // Full row is fetched so the audit trail can compare previous values.
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $tracking_id));
        if (!$item) YBT_API_Response::error('Tracking record not found', 'not_found', 404);

        if (function_exists('ybt_security')) {
            $upload = ybt_security()->secure_file_upload($image_data);
            if (!$upload['success']) YBT_API_Response::error(implode(', ', $upload['errors']), 'upload_failed');
        } else {
            if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');
            
            // SECURITY: Validate file before upload
            if (function_exists('ybt_validate_file_upload')) {
                $validation = ybt_validate_file_upload($image_data, array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
                if (is_wp_error($validation)) {
                    YBT_API_Response::error($validation->get_error_message(), 'upload_failed');
                }
                // Sanitize filename
                if (function_exists('ybt_sanitize_filename')) {
                    $image_data['name'] = ybt_sanitize_filename($image_data['name']);
                }
            }
            
            // Fix: Use the actual tmp_name from the uploaded file
            $upload = wp_handle_upload($image_data, array('test_form' => false));
            if (isset($upload['error'])) YBT_API_Response::error($upload['error'], 'upload_failed');
        }

        $url = $upload['url'];
        $existing_images = $item->images ? explode(',', $item->images) : array();
        $existing_images[] = $url;
        $images_string = implode(',', array_unique(array_filter(array_map('trim', $existing_images))));

        $update_data = array('images' => $images_string);
        
        // Also update item count if provided (User might have updated it in the UI before upload)
        $item_count = $this->get_param('item_count', 0, 'int');
        if ($item_count > 0) {
            $update_data['item_count'] = $item_count;
        }

        $wpdb->update("{$wpdb->prefix}ybt_tracking", $update_data, array('id' => $tracking_id));

        YBT_Tracking_Audit::log_edit($tracking_id, 'image_upload', YBT_Tracking_Audit::diff($update_data, $item), array(
            'admin_id'   => $this->user_id,
            'source'     => 'mobile',
            'log_activity' => false,
        ));
        
        // Return URL directly as string to match Android's ApiResponse<String> expectation
        YBT_API_Response::success($url, 'Image uploaded successfully');
    }

    /**
     * Admin: Update Item Details
     * FIXED: The Android app sends tracking_id + weight_kg + description +
     * shipping_method (see YbtAdminApiService.updateItem). The old handler only
     * read 'id'/'item_count', so every app-side edit failed with "ID required".
     */
    public function update_item() {
        $this->require_admin();

        $id = $this->get_param('id', 0, 'int');
        if (!$id) $id = $this->get_param('tracking_id', 0, 'int');
        if (!$id) YBT_API_Response::error('ID required', 'missing_id');

        global $wpdb;
        $data = array();

        $item_count = $this->get_param('item_count', -1, 'int');
        if ($item_count >= 0) $data['item_count'] = $item_count;

        if (isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '') {
            $data['weight_kg'] = floatval(wp_unslash($_POST['weight_kg']));
        }
        if (isset($_POST['description']) && $_POST['description'] !== '') {
            $data['description'] = sanitize_textarea_field(wp_unslash($_POST['description']));
        }
        if (isset($_POST['shipping_method']) && $_POST['shipping_method'] !== '') {
            $data['shipping_method'] = sanitize_text_field(wp_unslash($_POST['shipping_method']));
        }

        if (empty($data)) YBT_API_Response::error('No data to update', 'missing_data');

        YBT_Tracking_Audit::apply_update($id, $data, 'edit', array(
            'admin_id' => $this->user_id,
            'source'   => 'mobile',
        ));

        YBT_API_Response::success(null, 'Item updated successfully');
    }

    /**
     * Admin: Get Tracking Edit History
     *
     * Returns the audit trail for one tracking number: which admin made each
     * edit, when, what changed (old -> new), via which source, and from which
     * IP. Backed by the YBT_Tracking_Audit history table (ybt_tracking_edits).
     *
     * Params: tracking_id (int, required) — also accepts tracking_number (string).
     *         limit (int, optional, default 100, max 500).
     */
    public function get_tracking_history() {
        $this->require_admin();

        // The Android app sends these as GET query parameters (@Query), so read
        // both methods: prefer POST, fall back to GET.
        $tracking_id = (int) ($this->get_param('tracking_id', 0, 'int', 'post') ?: $this->get_param('tracking_id', 0, 'int', 'get'));
        $tracking_number = trim((string) ($this->get_param('tracking_number', '', 'text', 'post') ?: $this->get_param('tracking_number', '', 'text', 'get')));

        global $wpdb;

        if ($tracking_id <= 0 && $tracking_number !== '') {
            $tracking_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = UPPER(%s)",
                $tracking_number
            ));
        }

        if ($tracking_id <= 0) {
            YBT_API_Response::error('Tracking ID or tracking number required', 'missing_id');
        }

        $limit = max(1, min(500, (int) ($this->get_param('limit', 100, 'int', 'post') ?: $this->get_param('limit', 100, 'int', 'get'))));

        if (!class_exists('YBT_Tracking_Audit')) {
            YBT_API_Response::error('Audit module unavailable', 'audit_missing', 500);
        }

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT id, tracking_number, last_edited_by, last_edited_by_name, last_edited_at, edit_count
               FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
            $tracking_id
        ));

        if (!$item) {
            YBT_API_Response::error('Tracking record not found', 'not_found', 404);
        }

        $entries = YBT_Tracking_Audit::get_history($tracking_id, $limit);

        YBT_API_Response::success(array(
            'tracking_id'           => (int) $item->id,
            'tracking_number'       => $item->tracking_number,
            'last_edited_by'        => isset($item->last_edited_by) ? (int) $item->last_edited_by : 0,
            'last_edited_by_name'   => isset($item->last_edited_by_name) ? $item->last_edited_by_name : '',
            'last_edited_at'        => isset($item->last_edited_at) ? $item->last_edited_at : '',
            'edit_count'            => isset($item->edit_count) ? (int) $item->edit_count : 0,
            'total'                 => YBT_Tracking_Audit::count_history($tracking_id),
            'entries'               => $entries,
        ), 'Edit history loaded');
    }

    /**
     * Admin: Delete Tracking Item
     */
    public function delete_tracking() {
        $this->require_admin();

        $id = $this->get_param('id', 0, 'int');
        if (!$id) $id = $this->get_param('tracking_id', 0, 'int');

        if (!$id) YBT_API_Response::error('ID required', 'missing_id');

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $id));
        if (!$item) YBT_API_Response::error('Tracking record not found', 'not_found', 404);

        $deleted = $wpdb->delete("{$wpdb->prefix}ybt_tracking", array('id' => $id));

        if ($deleted) {
            YBT_Tracking_Audit::log_edit($id, 'delete', array(), array(
                'summary'         => 'Deleted tracking number',
                'tracking_number' => $item->tracking_number,
                'admin_id'        => $this->user_id,
                'source'          => 'mobile',
                'log_activity'    => false,
            ));
            if (function_exists('ybt_log_activity')) {
                ybt_log_activity($this->user_id, 'admin_delete_tracking', "Deleted tracking: {$item->tracking_number}");
            }
            YBT_API_Response::success(null, 'Tracking deleted successfully');
        } else {
            YBT_API_Response::error('Failed to delete tracking', 'delete_failed');
        }
    }
}
