<?php
/**
 * YBT API Tracking Handler
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Tracking_Handler extends YBT_API_Handler_Base {

    /**
     * Get user dashboard stats
     */
    public function get_dashboard_stats() {
        $this->authenticate();

        global $wpdb;
        $user = get_userdata($this->user_id);

        $stats = array(
            'not_received' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'not_received'",
                $this->user_id, $user->user_login
            ))),
            'received' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'received'",
                $this->user_id, $user->user_login
            ))),
            'packed' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'packed'",
                $this->user_id, $user->user_login
            ))),
            'completed' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'completed'",
                $this->user_id, $user->user_login
            )))
        );

        $custom_user_id = get_user_meta($this->user_id, 'ybt_custom_user_id', true) ?: 'Pending';

        YBT_API_Response::success(array(
            'stats' => $stats,
            'user_info' => array(
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'custom_user_id' => $custom_user_id,
                'role' => $this->is_admin ? 'admin' : 'user',
                'is_admin' => $this->is_admin
            )
        ));
    }

    /**
     * Get tracking items
     * FIXED: Use GET method for query parameters (Android app uses @Query annotation)
     */
    public function get_tracking_items() {
        $this->authenticate();

        global $wpdb;
        $user = get_userdata($this->user_id);
        // Query-style params arrive via GET (Android @Query, links) or POST
        // (form/admin-ajax callers) — accept both, matching the legacy
        // ybt_mobile_tracking handler this endpoint replaced.
        $status = $this->get_query_param('status', 'all');
        $search = $this->get_query_param('search', '');

        // Normalize invalid status values instead of silently returning nothing
        $valid_statuses = array('all', 'not_received', 'received', 'packing_in_progress', 'packed', 'completed');
        if ($status !== 'all' && !in_array($status, $valid_statuses, true)) {
            $status = 'all';
        }

        $where = "(user_id = %d OR username = %s)";
        $params = array($this->user_id, $user->user_login);

        if (!empty($search)) {
            $where .= " AND (tracking_number LIKE %s OR description LIKE %s)";
            $wild = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $wild;
            $params[] = $wild;
        }

        if ($status !== 'all') {
            $where .= " AND status = %s";
            $params[] = $status;
        }

        $limit = $this->get_query_param('limit', 25, 'int');
        $offset = $this->get_query_param('offset', 0, 'int');

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE $where ORDER BY date_added DESC LIMIT %d OFFSET %d",
            array_merge($params, array($limit, $offset))
        ));

        // Enrich items with carton images
        foreach ($items as &$item) {
            $item->carton_images = '';
            if (!empty($item->batch_id)) {
                $carton_images = $wpdb->get_col($wpdb->prepare(
                    "SELECT carton_image FROM {$wpdb->prefix}ybt_cartons WHERE batch_id = %s",
                    $item->batch_id
                ));

                if (!empty($carton_images)) {
                    $all_images = array();
                    foreach ($carton_images as $img_str) {
                        if (!empty($img_str)) {
                            $parts = explode(',', $img_str);
                            foreach ($parts as $part) {
                                if (!empty(trim($part))) $all_images[] = trim($part);
                            }
                        }
                    }
                    if (!empty($all_images)) {
                        $item->carton_images = implode(',', array_unique($all_images));
                    }
                }
            }
        }

        YBT_API_Response::success(array('items' => $items));
    }

    /**
     * Add tracking number
     */
    public function add_tracking() {
        $this->authenticate();

        global $wpdb;
        $user = get_userdata($this->user_id);

        $tracking_number = strtoupper(trim($this->get_param('tracking_number')));
        $description = $this->get_param('description');
        $shipping_method = $this->get_param('shipping_method', 'sea');
        $item_count = $this->get_param('item_count', 1, 'int');

        if (empty($tracking_number)) {
            YBT_API_Response::error('Tracking number required', 'missing_tracking');
            return;
        }

        // Check if exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, username FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = UPPER(%s)",
            $tracking_number
        ));

        if ($existing) {
            // Check if it's an anonymous parcel
            if (empty($existing->user_id) || $existing->user_id == 0 || strtolower($existing->username ?? '') === 'anonymous') {
                $wpdb->update(
                    $wpdb->prefix . 'ybt_tracking',
                    array(
                        'user_id' => $this->user_id,
                        'username' => $user->user_login,
                        'description' => $description,
                        'shipping_method' => $shipping_method,
                        'item_count' => $item_count,
                    ),
                    array('id' => $existing->id)
                );

                if (class_exists('YBT_Tracking_Events')) {
                    YBT_Tracking_Events::record($existing->id, 'claimed', array(
                        'tracking_number' => $tracking_number,
                        'user_id'         => $this->user_id,
                        'actor_id'        => $this->user_id,
                        'actor_name'      => $user->display_name ?: $user->user_login,
                        'actor_role'      => 'customer',
                        'source'          => 'mobile',
                    ));
                }

                if (function_exists('ybt_log_activity')) {
                    ybt_log_activity($this->user_id, 'claim_tracking', "Claimed anonymous tracking: $tracking_number");
                }
                YBT_API_Response::success(null, 'Tracking number claimed successfully');
                return;
            } else {
                YBT_API_Response::error('Tracking number already exists', 'tracking_exists');
                return;
            }
        }

        $result = $wpdb->insert($wpdb->prefix . 'ybt_tracking', array(
            'tracking_number' => $tracking_number,
            'user_id' => $this->user_id,
            'username' => $user->user_login,
            'description' => $description,
            'shipping_method' => $shipping_method,
            'item_count' => $item_count,
            'status' => 'not_received',
            'date_added' => current_time('mysql')
        ));

        if ($result) {
            if ($wpdb->insert_id && class_exists('YBT_Tracking_Events')) {
                YBT_Tracking_Events::record($wpdb->insert_id, 'created', array(
                    'tracking_number' => $tracking_number,
                    'user_id'         => $this->user_id,
                    'status_to'       => 'not_received',
                    'actor_id'        => $this->user_id,
                    'actor_name'      => $user->display_name ?: $user->user_login,
                    'actor_role'      => 'customer',
                    'source'          => 'mobile',
                ));
            }
            if (function_exists('ybt_log_activity')) {
                ybt_log_activity($this->user_id, 'add_tracking', "Added tracking: $tracking_number");
            }
            YBT_API_Response::success(null, 'Tracking number added successfully');
        } else {
            YBT_API_Response::error('Failed to add tracking number', 'insert_failed');
        }
    }

    /**
     * Claim parcel
     */
    public function claim_parcel() {
        $this->authenticate();

        // Rate limit parcel claims (10 attempts per 10 minutes)
        if (function_exists('ybt_check_rate_limit') && !ybt_check_rate_limit('claim_parcel_' . $this->user_id, 10, 600)) {
            YBT_API_Response::error('Too many parcel claim attempts. Please try again later.', 'rate_limited', 429);
            return;
        }

        global $wpdb;
        $user = get_userdata($this->user_id);
        $tracking_number = strtoupper(trim($this->get_param('tracking_number')));

        if (empty($tracking_number)) {
            YBT_API_Response::error('Tracking number required', 'missing_tracking');
            return;
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE UPPER(tracking_number) = UPPER(%s)",
            $tracking_number
        ));

        if (!$existing) {
            YBT_API_Response::error('Parcel not found in our records', 'not_found', 404);
            return;
        }

        if (!empty($existing->user_id) && $existing->user_id != 0 && strtolower($existing->username ?? '') !== 'anonymous') {
            if ($existing->user_id == $this->user_id) {
                YBT_API_Response::error('Parcel is already in your list', 'already_owned');
                return;
            } else {
                YBT_API_Response::error('Parcel is already assigned to another user', 'assigned_elsewhere');
                return;
            }
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'ybt_tracking',
            array(
                'user_id' => $this->user_id,
                'username' => $user->user_login,
                'date_added' => current_time('mysql'),
            ),
            array('id' => $existing->id)
        );

        if ($result !== false) {
            if (class_exists('YBT_Tracking_Events')) {
                YBT_Tracking_Events::record($existing->id, 'claimed', array(
                    'tracking_number' => $tracking_number,
                    'user_id'         => $this->user_id,
                    'status_from'     => $existing->status,
                    'actor_id'        => $this->user_id,
                    'actor_name'      => $user->display_name ?: $user->user_login,
                    'actor_role'      => 'customer',
                    'source'          => 'mobile',
                ));
            }
            if (function_exists('ybt_log_activity')) {
                ybt_log_activity($this->user_id, 'claim_parcel', "Claimed parcel: $tracking_number");
            }

            YBT_API_Response::success(array(
                'id' => $existing->id,
                'status' => $existing->status,
                'tracking_number' => $existing->tracking_number
            ), "Parcel claimed successfully!");
        } else {
            YBT_API_Response::error('Failed to claim parcel', 'update_failed');
        }
    }

    /**
     * Mobile Search Tracking
     */
    public function search_tracking() {
        $this->authenticate();

        global $wpdb;
        $user = get_userdata($this->user_id);
        $query = $this->get_param('query', '', 'text', 'get');

        if (empty($query)) {
            YBT_API_Response::error('Query required', 'missing_query');
            return;
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking
            WHERE (user_id = %d OR username = %s)
            AND (tracking_number LIKE %s OR description LIKE %s)
            ORDER BY date_added DESC",
            $this->user_id, $user->user_login, '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%'
        ));

        YBT_API_Response::success(array('items' => $items));
    }

    /**
     * Mobile Dashboard Info - Get bank details, warehouse address, and announcements
     */
    public function get_dashboard_info() {
        $this->authenticate();

        global $wpdb;
        $custom_user_id = get_user_meta($this->user_id, 'ybt_custom_user_id', true) ?: 'Pending';

        // Fetch payment settings
        $payment_settings = null;
        $table_payment = $wpdb->prefix . 'ybt_payment_settings';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_payment)) == $table_payment) {
            $payment_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_payment} WHERE is_active = 1 ORDER BY date_created DESC LIMIT 1"));
            if ($payment_data) {
                $payment_settings = array(
                    'bank_name' => $payment_data->bank_name,
                    'account_name' => $payment_data->account_name,
                    'account_number' => $payment_data->account_number,
                    'qr_code_image' => $payment_data->qr_code_image ?: null
                );
            }
        }

        // Fetch warehouse address
        $warehouse_address = null;
        $table_warehouse = $wpdb->prefix . 'ybt_warehouse_settings';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_warehouse)) == $table_warehouse) {
            $warehouse_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_warehouse} WHERE shipping_type = 'air' AND is_active = 1 ORDER BY date_created DESC LIMIT 1"));
            if ($warehouse_data) {
                $formatted_address = ybt_smart_format_address($warehouse_data->address, $custom_user_id);

                $warehouse_address = array(
                    'address' => $formatted_address,
                    'phone' => $warehouse_data->phone,
                    'hours' => $warehouse_data->hours
                );
            }
        }

        // Fetch latest announcement
        $announcement = null;
        $table_messages = $wpdb->prefix . 'ybt_admin_messages';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_messages)) == $table_messages) {
            $message_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_messages} ORDER BY date_created DESC LIMIT 1"));
            if ($message_data) {
                $announcement = array(
                    'message' => $message_data->message,
                    'image_url' => $message_data->image_url ?: null,
                    'video_url' => $message_data->video_url ?: null,
                    'date_created' => $message_data->date_created
                );
            }
        }

        // Fetch user profile info
        $user = get_userdata($this->user_id);
        $user_profile = null;
        if ($user) {
            $user_profile = array(
                'username'         => $user->user_login,
                'display_name'     => $user->display_name,
                'email'            => $user->user_email,
                'custom_user_id'   => $custom_user_id,
                'shipping_address' => get_user_meta($this->user_id, 'ybt_shipping_address', true) ?: '',
                'shipping_phone'   => get_user_meta($this->user_id, 'ybt_shipping_phone', true) ?: ''
            );
        }

        YBT_API_Response::success(array(
            'payment_settings'  => $payment_settings,
            'warehouse_address' => $warehouse_address,
            'announcement'      => $announcement,
            'user_profile'      => $user_profile
        ));
    }
}
