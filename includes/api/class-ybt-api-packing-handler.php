<?php
/**
 * YBT API Packing Handler
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Packing_Handler extends YBT_API_Handler_Base {

    /**
     * Request packing
     */
    public function request_packing() {
        $this->authenticate();

        global $wpdb;
        $tracking_ids = $this->get_param('tracking_ids');
        $shipping_company_name = $this->get_param('shipping_company_name');
        $shipping_company_address = $this->get_param('shipping_company_address', '', 'textarea');
        $shipping_company_phone = $this->get_param('shipping_company_phone');
        $shipping_mark = $this->get_param('shipping_mark');
        $instructions = $this->get_param('instructions', '', 'textarea');

        if (empty($tracking_ids)) {
            YBT_API_Response::error('Please select at least one item', 'missing_items');
        }

        if (empty($shipping_company_name) || empty($shipping_company_address) || empty($shipping_company_phone)) {
            YBT_API_Response::error('Please fill in all required shipping fields', 'missing_shipping_info');
        }

        $ids_array = array_filter(array_map('intval', explode(',', $tracking_ids)));
        if (empty($ids_array)) {
            YBT_API_Response::error('No valid tracking IDs provided', 'invalid_ids');
        }

        // Verify ownership and status (only 'received' items can be requested for packing)
        $placeholders = implode(',', array_fill(0, count($ids_array), '%d'));
        $items_to_check = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status, user_id, shipping_method FROM {$wpdb->prefix}ybt_tracking WHERE id IN ($placeholders)",
            $ids_array
        ));

        if (count($items_to_check) != count($ids_array)) {
            YBT_API_Response::error('Some items were not found', 'not_found');
        }

        foreach ($items_to_check as $item) {
            if ($item->user_id != $this->user_id) {
                YBT_API_Response::error("Item #{$item->id} does not belong to you", 'permission_denied');
            }
            if ($item->status !== 'received') {
                YBT_API_Response::error("Item #{$item->id} is currently '{$item->status}' and cannot be requested for packing again.", 'invalid_status');
            }
        }

        // ── Batch merge ────────────────────────────────────────────────────────
        // If this new request's items all share the same mode of shipment and the
        // customer already has an open (packing_in_progress) batch whose items all
        // use that same mode, append to that batch instead of creating a separate
        // one — the admin Android app then shows ONE consolidated batch instead of
        // two adjacent ones.
        $batch_id = null;
        $merged = false;
        $requested_methods = array();
        foreach ($items_to_check as $item) {
            $m = strtolower(trim($item->shipping_method));
            if ($m !== '') $requested_methods[$m] = true;
        }
        if (count($requested_methods) === 1) {
            $method = key($requested_methods);
            $candidate_batch_id = $wpdb->get_var($wpdb->prepare(
                "SELECT batch_id
                 FROM {$wpdb->prefix}ybt_tracking
                 WHERE user_id = %d
                   AND status = 'packing_in_progress'
                   AND batch_id IS NOT NULL AND batch_id != ''
                 GROUP BY batch_id
                 HAVING COUNT(*) > 0
                    AND COUNT(CASE WHEN LOWER(TRIM(shipping_method)) = %s THEN 1 END) = COUNT(*)
                 ORDER BY MAX(date_packing_requested) DESC
                 LIMIT 1",
                $this->user_id, $method
            ));
            if (!empty($candidate_batch_id)) {
                $batch_id = $candidate_batch_id;
                $merged = true;
            }
        }
        if (empty($batch_id)) {
            $batch_id = 'BATCH_' . date('Ymd_His') . '_' . wp_generate_password(6, false);
        }

        $final_address = $shipping_company_address;
        if (!empty($instructions)) {
            $final_address .= "\n\n--- Special Instructions ---\n" . $instructions;
        }

        if ($merged) {
            // Keep the batch coherent: the appended items adopt the shipping
            // details that were already set on the batch (first request's info),
            // so the admin does not see mixed company/address/mark values.
            $existing_batch_info = $wpdb->get_row($wpdb->prepare(
                "SELECT shipping_company_name, shipping_address, shipping_company_phone, shipping_mark
                 FROM {$wpdb->prefix}ybt_tracking
                 WHERE batch_id = %s AND shipping_company_name IS NOT NULL AND shipping_company_name != ''
                 LIMIT 1",
                $batch_id
            ));
            if ($existing_batch_info) {
                $shipping_company_name = $existing_batch_info->shipping_company_name;
                $final_address = $existing_batch_info->shipping_address;
                $shipping_company_phone = $existing_batch_info->shipping_company_phone;
                $shipping_mark = $existing_batch_info->shipping_mark;
            }
        }

        $updated_count = 0;
        foreach ($ids_array as $tracking_id) {
            $result = $wpdb->update(
                $wpdb->prefix . 'ybt_tracking',
                array(
                    'status' => 'packing_in_progress',
                    'batch_id' => $batch_id,
                    'shipping_company_name' => $shipping_company_name,
                    'shipping_address' => $final_address,
                    'shipping_company_phone' => $shipping_company_phone,
                    'shipping_mark' => $shipping_mark,
                    'date_packing_requested' => current_time('mysql')
                ),
                array('id' => $tracking_id)
            );
            if ($result !== false) $updated_count++;
        }

        if ($updated_count > 0) {
            if (function_exists('ybt_log_activity')) {
                $log_note = $merged ? "Requested packing for $updated_count items via mobile (merged into batch $batch_id)" : "Requested packing for $updated_count items via mobile";
                ybt_log_activity($this->user_id, 'request_packing', $log_note);
            }

            YBT_API_Response::success(array(
                'batch_id' => $batch_id,
                'items_count' => $updated_count,
                'merged' => $merged
            ), $merged ? "Items added to existing packing batch $batch_id" : "Packing request submitted successfully");
        } else {
            YBT_API_Response::error('Failed to submit packing request', 'update_failed');
        }
    }

    /**
     * Customer: Get packed batches (packing_in_progress or packed)
     * Returns tracking items grouped by batch_id with shipping details
     */
    public function get_packed_batches() {
        $this->authenticate();

        global $wpdb;
        $user = get_userdata($this->user_id);
        $table = $wpdb->prefix . 'ybt_tracking';

        $batches = $wpdb->get_results($wpdb->prepare(
            "SELECT batch_id,
                    MIN(status) as batch_status,
                    COUNT(*) as item_count,
                    MAX(shipping_company_name) as shipping_company_name,
                    MAX(shipping_address) as shipping_address,
                    MAX(shipping_mark) as shipping_mark,
                    MAX(date_packing_requested) as request_date,
                    MAX(payment_evidence) as payment_evidence,
                    SUM(weight_kg) as total_weight,
                    SUM(payment_amount) as total_payment
             FROM $table
             WHERE (user_id = %d OR username = %s)
               AND batch_id IS NOT NULL AND batch_id != ''
               AND status IN ('packing_in_progress', 'packed')
             GROUP BY batch_id
             ORDER BY request_date DESC",
            $this->user_id, $user->user_login
        ));

        // For each batch, get the individual items
        $result = array();
        foreach ($batches as $batch) {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT id, tracking_number, description, shipping_method, item_count,
                        status, weight_kg, payment_amount, images,
                        date_packing_requested, date_packed
                 FROM $table
                 WHERE batch_id = %s ORDER BY date_added ASC",
                $batch->batch_id
            ));
            $result[] = array(
                'batch_id' => $batch->batch_id,
                'status' => $batch->batch_status,
                'item_count' => intval($batch->item_count),
                'shipping_company_name' => $batch->shipping_company_name,
                'shipping_address' => $batch->shipping_address,
                'shipping_mark' => $batch->shipping_mark,
                'request_date' => $batch->request_date,
                'payment_evidence' => $batch->payment_evidence,
                'total_weight' => floatval($batch->total_weight),
                'total_payment' => floatval($batch->total_payment),
                'items' => $items
            );
        }

        YBT_API_Response::success(array('batches' => $result));
    }

    /**
     * Upload payment evidence
     */
    public function upload_payment() {
        $this->authenticate();

        $tracking_id = $this->get_param('tracking_id', 0, 'int');
        $batch_id = $this->get_param('batch_id');

        if (!$tracking_id && empty($batch_id)) {
            YBT_API_Response::error('Invalid tracking ID or Batch ID', 'missing_id');
        }

        if (empty($_FILES['payment_evidence'])) {
            YBT_API_Response::error('No file uploaded', 'missing_file');
        }

        global $wpdb;

        // Verify ownership and get target batch
        if (!empty($batch_id)) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s AND user_id = %d",
                $batch_id, $this->user_id
            ));
            if ($count == 0) YBT_API_Response::error('Batch not found or access denied', 'permission_denied');
        } else {
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d AND user_id = %d",
                $tracking_id, $this->user_id
            ));
            if (!$item) YBT_API_Response::error('Item not found or access denied', 'permission_denied');
            if (!empty($item->batch_id)) $batch_id = $item->batch_id;
        }

        // Handle upload
        if (function_exists('ybt_security')) {
            $upload = ybt_security()->secure_file_upload($_FILES['payment_evidence']);
            if (!$upload['success']) {
                YBT_API_Response::error(implode(', ', $upload['errors']), 'upload_failed');
            }
        } else {
            if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload = wp_handle_upload($_FILES['payment_evidence'], array('test_form' => false));
            if (isset($upload['error'])) YBT_API_Response::error($upload['error'], 'upload_failed');
        }

        $uploaded_url = isset($upload['url']) ? $upload['url'] : '';

        if (!empty($batch_id)) {
            $wpdb->update(
                "{$wpdb->prefix}ybt_tracking",
                array('payment_evidence' => $uploaded_url),
                array('batch_id' => $batch_id, 'user_id' => $this->user_id)
            );
            YBT_API_Response::success(null, 'Payment evidence applied to batch');
        } else {
            $wpdb->update(
                "{$wpdb->prefix}ybt_tracking",
                array('payment_evidence' => $uploaded_url),
                array('id' => $tracking_id)
            );
            YBT_API_Response::success(null, 'Payment evidence uploaded');
        }
    }

    /**
     * Admin: Get all packing requests
     * FIXED: Use GET method for query parameters (Android app uses @Query annotation)
     */
    public function admin_get_packing_requests() {
        $this->require_admin();

        global $wpdb;
        // FIXED: Explicitly use 'get' method since Android app sends query parameters via GET
        $tracking_number = $this->get_param('tracking_number', '', 'text', 'get');

        $where_clause = "WHERE status = 'packing_in_progress' AND batch_id IS NOT NULL AND batch_id != ''";
        if (!empty($tracking_number)) {
            // Join with a subquery to find batches containing the tracking number
            $where_clause .= $wpdb->prepare(" AND batch_id IN (SELECT DISTINCT batch_id FROM {$wpdb->prefix}ybt_tracking WHERE tracking_number LIKE %s)", '%' . $wpdb->esc_like($tracking_number) . '%');
        }

        $batches = $wpdb->get_results("
            SELECT
                batch_id,
                username,
                COUNT(*) as item_count,
                MAX(date_packing_requested) as request_date,
                MAX(shipping_address) as shipping_address,
                MAX(shipping_mark) as shipping_mark,
                MAX(shipping_company_name) as shipping_company_name
            FROM {$wpdb->prefix}ybt_tracking
            $where_clause
            GROUP BY batch_id, username
            ORDER BY request_date DESC
        ");

        foreach ($batches as &$batch) {
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s LIMIT 1", $batch->batch_id));
            $batch->custom_id = get_user_meta($user_id, 'ybt_custom_user_id', true) ?: 'Pending';
            $batch->carton_count = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_cartons WHERE batch_id = %s",
                $batch->batch_id
            )));
        }

        YBT_API_Response::success(array('batches' => $batches));
    }

    /**
     * Admin: Get batch details
     * FIXED: Use GET method for query parameters (Android app uses @Query annotation)
     */
    public function admin_get_batch_details() {
        $this->require_admin();

        // FIXED: Explicitly use 'get' method since Android app sends query parameters via GET
        $batch_id = $this->get_param('batch_id', '', 'text', 'get');
        if (empty($batch_id)) YBT_API_Response::error('Batch ID required', 'missing_batch_id');

        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s", $batch_id));
        $cartons = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_cartons WHERE batch_id = %s ORDER BY carton_number ASC", $batch_id));

        YBT_API_Response::success(array(
            'items' => $items,
            'cartons' => $cartons
        ));
    }

    /**
     * Admin: Save cartons and complete packing
     */
    public function admin_save_cartons() {
        $this->require_admin();

        $batch_id = $this->get_param('batch_id');
        $carton_data_json = isset($_POST['carton_data']) ? wp_unslash($_POST['carton_data']) : '';

        if (empty($batch_id) || empty($carton_data_json)) {
            YBT_API_Response::error('Batch ID and carton data required', 'missing_data');
        }

        $carton_list = json_decode($carton_data_json, true);
        if (!is_array($carton_list)) {
            YBT_API_Response::error('Invalid carton data format', 'invalid_json');
        }

        global $wpdb;
        $cartons_table = $wpdb->prefix . 'ybt_cartons';

        $wpdb->delete($cartons_table, array('batch_id' => $batch_id));

        $total_weight = 0;
        $total_payment = 0;
        $currency = 'CNY';

        if (!function_exists('wp_handle_upload')) require_once(ABSPATH . 'wp-admin/includes/file.php');

        foreach ($carton_list as $index => $c) {
            $carton_number = $index + 1;
            $weight = floatval($c['weight']);
            $payment = floatval($c['payment']);
            if (!empty($c['currency'])) $currency = sanitize_text_field($c['currency']);

            $image_urls = array();
            $file_key = 'carton_images_' . $index;
            if (!empty($_FILES[$file_key])) {
                $files = $_FILES[$file_key];
                if (is_array($files['name'])) {
                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $file = array(
                                'name' => $files['name'][$i],
                                'type' => $files['type'][$i],
                                'tmp_name' => $files['tmp_name'][$i],
                                'error' => $files['error'][$i],
                                'size' => $files['size'][$i]
                            );
                            
                            // SECURITY: Validate file before upload
                            if (function_exists('ybt_validate_file_upload')) {
                                $validation = ybt_validate_file_upload($file, array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
                                if (is_wp_error($validation)) {
                                    continue; // Skip invalid files
                                }
                                // Sanitize filename
                                if (function_exists('ybt_sanitize_filename')) {
                                    $file['name'] = ybt_sanitize_filename($file['name']);
                                }
                            }
                            
                            $upload = wp_handle_upload($file, array('test_form' => false));
                            if (!isset($upload['error'])) {
                                // Compress to reduce disk space (best-effort)
                                if (!empty($upload['file']) && class_exists('YBT_File_Handler')) {
                                    YBT_File_Handler::compress_image($upload['file']);
                                }
                                $image_urls[] = $upload['url'];
                            }
                        }
                    }
                } else {
                    // SECURITY: Validate file before upload
                    if (function_exists('ybt_validate_file_upload')) {
                        $validation = ybt_validate_file_upload($files, array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5242880);
                        if (!is_wp_error($validation)) {
                            // Sanitize filename
                            if (function_exists('ybt_sanitize_filename')) {
                                $files['name'] = ybt_sanitize_filename($files['name']);
                            }
                            $upload = wp_handle_upload($files, array('test_form' => false));
                            if (!isset($upload['error'])) {
                                // Compress to reduce disk space (best-effort)
                                if (!empty($upload['file']) && class_exists('YBT_File_Handler')) {
                                    YBT_File_Handler::compress_image($upload['file']);
                                }
                                $image_urls[] = $upload['url'];
                            }
                        }
                    } else {
                        $upload = wp_handle_upload($files, array('test_form' => false));
                        if (!isset($upload['error'])) {
                            // Compress to reduce disk space (best-effort)
                            if (!empty($upload['file']) && class_exists('YBT_File_Handler')) {
                                YBT_File_Handler::compress_image($upload['file']);
                            }
                            $image_urls[] = $upload['url'];
                        }
                    }
                }
            }

            $wpdb->insert($cartons_table, array(
                'batch_id' => $batch_id,
                'carton_number' => $carton_number,
                'weight_kg' => $weight,
                'payment_amount' => $payment,
                'payment_currency' => $currency,
                'carton_image' => implode(',', $image_urls),
                'date_created' => current_time('mysql')
            ));

            $total_weight += $weight;
            $total_payment += $payment;
        }

        // Audit: snapshot the batch BEFORE it is marked packed so each item's
        // previous status/values are preserved in the history.
        $audit_batch_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s",
            $batch_id
        ));

        $packed_data = array(
            'status' => 'packed',
            'cartons' => count($carton_list),
            'weight_kg' => $total_weight,
            'payment_amount' => $total_payment,
            'payment_currency' => $currency,
            'date_packed' => current_time('mysql')
        );

        $wpdb->update(
            "{$wpdb->prefix}ybt_tracking",
            $packed_data,
            array('batch_id' => $batch_id)
        );

        // Audit: attribute the packing to this admin, per tracking number.
        if ($audit_batch_rows) {
            foreach ($audit_batch_rows as $audit_row) {
                YBT_Tracking_Audit::log_after_raw_update(
                    $audit_row->id,
                    $audit_row,
                    $packed_data,
                    'packing',
                    array('admin_id' => $this->user_id, 'source' => 'mobile', 'log_activity' => false)
                );
            }
        }

        // Fire tracking_updated hook for each item so invoice system auto-generates
        $tracking_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s",
            $batch_id
        ));
        foreach ($tracking_ids as $tid) {
            do_action('ybt_tracking_updated', intval($tid), 'packed');
        }

        // Also trigger bulk operation hook as a safety net for any missed batches
        do_action('ybt_bulk_operation_complete');

        if (function_exists('ybt_log_activity')) {
            ybt_log_activity($this->user_id, 'admin_complete_packing', "Completed packing for batch $batch_id");
        }

        $user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s LIMIT 1", $batch_id));
        if ($user_id && class_exists('YBT_Notifications')) {
            YBT_Notifications::send_tracking_update($user_id, 'Packing Completed', "Your parcel batch $batch_id has been packed!");
        }

        YBT_API_Response::success($batch_id, 'Packing completed successfully');
    }
}
