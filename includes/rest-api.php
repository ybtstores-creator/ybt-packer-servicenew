<?php
/**
 * YBT REST API Integration
 * WordPress REST API endpoints with authentication, rate limiting, and security
 * 
 * FIXED: PHP 8.0+ compatibility, table existence checks, encryption safety,
 *        proper init hook, CORS handling, and defensive error handling
 */

if (!defined('ABSPATH')) {
	exit('Direct access denied.');
}

class YBT_REST_API {

	private $namespace = 'ybt/v1';
	private $version = '1.0';
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Register hooks
	 * FIXED: Use WordPress standard rest_api_init hook
	 */
	private function __construct() {
		// Add CORS headers for REST API endpoints
		add_filter('rest_pre_serve_request', array($this, 'add_cors_headers'), 10, 3);
	}

	/**
	 * Add CORS headers for REST API responses
	 * FIXED: Implement proper origin validation instead of wildcard
	 */
	public function add_cors_headers($served, $result, $request) {
		// Get the origin from the request
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
		
		// Whitelist of allowed origins (mobile apps and web frontend)
		$allowed_origins = array(
			get_option('siteurl'), // WordPress site URL
			home_url(), // Home URL
		);
		
		// Add custom allowed origins from settings if configured
		$custom_origins = get_option('ybt_allowed_cors_origins', '');
		if (!empty($custom_origins)) {
			$custom_origins_array = array_filter(array_map('trim', explode(',', $custom_origins)));
			$allowed_origins = array_merge($allowed_origins, $custom_origins_array);
		}
		
		// Check if origin is in whitelist
		if (!empty($origin) && in_array($origin, $allowed_origins, true)) {
			header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
		}
		// SECURITY: Do NOT emit ACAO for rejected origins.
		// Same-origin requests don't need CORS headers.
		
		header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
		header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With, X-API-Key, X-YBT-Signature, X-YBT-Timestamp, X-YBT-Token, X-YBT-Current-Version');
		
		// Handle preflight OPTIONS request
		if ($request->get_method() === 'OPTIONS') {
			status_header(200);
			exit;
		}
		
		return $served;
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Tracking endpoints - Collection
		register_rest_route($this->namespace, '/tracking', array(
			'methods'             => 'GET',
			'callback'            => array($this, 'get_tracking_items'),
			'permission_callback' => array($this, 'check_api_permission'),
			'args'                => $this->get_collection_params()
		));

		// Tracking endpoints - Single item
		register_rest_route($this->namespace, '/tracking/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array($this, 'get_tracking_item'),
			'permission_callback' => array($this, 'check_api_permission'),
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					},
					'sanitize_callback' => 'absint'
				)
			)
		));

		// Tracking endpoints - Create
		register_rest_route($this->namespace, '/tracking', array(
			'methods'             => 'POST',
			'callback'            => array($this, 'create_tracking_item'),
			'permission_callback' => array($this, 'check_api_permission'),
			'args'                => $this->get_tracking_schema()
		));

		// Tracking endpoints - Update
		register_rest_route($this->namespace, '/tracking/(?P<id>\d+)', array(
			'methods'             => 'PUT',
			'callback'            => array($this, 'update_tracking_item'),
			'permission_callback' => array($this, 'check_api_permission'),
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					},
					'sanitize_callback' => 'absint'
				),
				'status' => array(
					'required'          => true,
					'enum'              => array('not_received', 'received', 'packing_in_progress', 'packed', 'completed'),
					'sanitize_callback' => 'sanitize_text_field'
				)
			)
		));

		// Tracking endpoints - Delete
		register_rest_route($this->namespace, '/tracking/(?P<id>\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array($this, 'delete_tracking_item'),
			'permission_callback' => array($this, 'check_api_permission'),
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					},
					'sanitize_callback' => 'absint'
				)
			)
		));

		// Stats endpoint
		register_rest_route($this->namespace, '/stats', array(
			'methods'             => 'GET',
			'callback'            => array($this, 'get_stats'),
			'permission_callback' => array($this, 'check_api_permission')
		));

		// Webhook endpoint
		register_rest_route($this->namespace, '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array($this, 'handle_webhook'),
			'permission_callback' => array($this, 'verify_webhook_signature')
		));

		// Customer lookup by username
		register_rest_route($this->namespace, '/customer-lookup', array(
			'methods'             => 'GET',
			'callback'            => array($this, 'customer_lookup'),
			'permission_callback' => array($this, 'check_api_permission'),
			'args'                => array(
				'username' => array(
					'required'          => true,
					'description'       => 'Username to search for',
					'sanitize_callback' => 'sanitize_text_field'
				)
			)
		));
	}

	/**
	 * Get tracking items with pagination and filtering
	 * FIXED: Added table existence check and defensive error handling
	 */
	public function get_tracking_items($request) {
		global $wpdb;

		// FIXED: Check table exists before querying
		if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
			return new WP_Error('db_not_ready', 'Database table not initialized', array('status' => 503));
		}

		$page     = max(1, intval($request->get_param('page') ?: 1));
		$per_page = max(1, min(100, intval($request->get_param('per_page') ?: 20))); // Cap at 100
		$status   = $request->get_param('status');
		$search   = $request->get_param('search');

		$offset = ($page - 1) * $per_page;
		$where  = '1=1';
		$params = array();

		if ($status) {
			$where .= ' AND status = %s';
			$params[] = sanitize_text_field($status);
		}

		if ($search) {
			$where .= ' AND (tracking_number LIKE %s OR description LIKE %s)';
			$wild = '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%';
			$params[] = $wild;
			$params[] = $wild;
		}

		$sql = "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE {$where} ORDER BY date_added DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		$items = $wpdb->get_results($wpdb->prepare($sql, $params));

		if ($items === null) {
			return new WP_Error('db_error', 'Failed to retrieve tracking items', array('status' => 500));
		}

		// Get total count for pagination headers
		$count_params = array_slice($params, 0, -2);
		$total = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE {$where}",
			$count_params
		));

		$response = new WP_REST_Response($items);
		$response->header('X-WP-Total', (int) $total);
		$response->header('X-WP-TotalPages', (int) ceil($total / $per_page));

		return $response;
	}

	/**
	 * Get single tracking item
	 * FIXED: Added table existence check
	 */
	public function get_tracking_item($request) {
		global $wpdb;

		// FIXED: Check table exists before querying
		if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
			return new WP_Error('db_not_ready', 'Database table not initialized', array('status' => 503));
		}

		$id = intval($request->get_param('id'));

		$item = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
			$id
		));

		if (!$item) {
			return new WP_Error('not_found', 'Tracking item not found', array('status' => 404));
		}

		return new WP_REST_Response($item);
	}

	/**
	 * Create tracking item
	 * FIXED: Added table existence check and improved validation
	 */
	public function create_tracking_item($request) {
		global $wpdb;

		// FIXED: Check table exists before querying
		if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
			return new WP_Error('db_not_ready', 'Database table not initialized', array('status' => 503));
		}

		$tracking_number = sanitize_text_field($request->get_param('tracking_number'));
		$user_id         = intval($request->get_param('user_id') ?: 0);
		$description     = sanitize_textarea_field($request->get_param('description') ?: '');
		$status          = sanitize_text_field($request->get_param('status') ?: 'not_received');

		// Validate required field
		if (empty($tracking_number)) {
			return new WP_Error('missing_param', 'Tracking number is required', array('status' => 400));
		}

		// Validate status enum
		$valid_statuses = array('not_received', 'received', 'packing_in_progress', 'packed', 'completed');
		if (!in_array($status, $valid_statuses, true)) {
			return new WP_Error('invalid_param', 'Invalid status value', array('status' => 400));
		}

		// Check if tracking number already exists
		$existing = $wpdb->get_row($wpdb->prepare(
			"SELECT id, user_id, username, status FROM {$wpdb->prefix}ybt_tracking WHERE tracking_number = %s",
			$tracking_number
		));

		if ($existing) {
			// Check if it's an anonymous/unclaimed parcel
			$is_anonymous = (
				empty($existing->user_id) || 
				$existing->user_id == 0 || 
				strtolower($existing->username ?? '') === 'anonymous'
			);

			if ($is_anonymous) {
				// Claim the anonymous parcel for this user
				$user = get_userdata($user_id);
				$username = $user ? $user->user_login : '';

				$result = $wpdb->update(
					$wpdb->prefix . 'ybt_tracking',
					array(
						'user_id'      => $user_id,
						'username'     => $username,
						'description'  => $description,
						'status'       => $status,
						'date_added'   => current_time('mysql')
					),
					array('id' => $existing->id),
					array('%d', '%s', '%s', '%s', '%s'),
					array('%d')
				);

				if ($result === false) {
					return new WP_Error('db_error', 'Failed to claim tracking item', array('status' => 500));
				}

				return new WP_REST_Response(array(
					'id'      => $existing->id,
					'message' => 'Tracking item claimed successfully'
				), 200);

			} else {
				// Admin can override existing items
				if (current_user_can('manage_options')) {
					$update_data = array(
						'status'        => $status,
						'date_received' => current_time('mysql')
					);

					// Only update owner if valid user provided
					if (!empty($user_id) && $user_id > 0) {
						$user = get_userdata($user_id);
						if ($user) {
							$update_data['user_id']   = $user_id;
							$update_data['username']  = $user->user_login;
						}
					}

					$wpdb->update(
						$wpdb->prefix . 'ybt_tracking',
						$update_data,
						array('id' => $existing->id),
						array('%s', '%s', '%d', '%s'),
						array('%d')
					);

					return new WP_REST_Response(array(
						'id'          => $existing->id,
						'message'     => 'Tracking updated and marked as ' . $status,
						'assigned_to' => $existing->username
					), 200);
				}

				// Non-admin cannot create duplicate
				return new WP_Error('duplicate', 'Tracking number already exists', array('status' => 400));
			}
		}

		// Insert new tracking item
		$result = $wpdb->insert(
			$wpdb->prefix . 'ybt_tracking',
			array(
				'tracking_number' => $tracking_number,
				'user_id'         => $user_id,
				'description'     => $description,
				'status'          => $status,
				'date_added'      => current_time('mysql')
			),
			array('%s', '%d', '%s', '%s', '%s')
		);

		if ($result === false) {
			return new WP_Error('db_error', 'Failed to create tracking item', array('status' => 500));
		}

		$item_id = $wpdb->insert_id;

		// Trigger action for extensibility
		do_action('ybt_tracking_created', $item_id);

		return new WP_REST_Response(array(
			'id'      => $item_id,
			'message' => 'Tracking item created successfully'
		), 201);
	}

	/**
	 * Update tracking item status
	 * FIXED: Added table existence check
	 */
	public function update_tracking_item($request) {
		global $wpdb;

		// FIXED: Check table exists before querying
		if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
			return new WP_Error('db_not_ready', 'Database table not initialized', array('status' => 503));
		}

		$id     = intval($request->get_param('id'));
		$status = sanitize_text_field($request->get_param('status'));

		// Validate status
		$valid_statuses = array('not_received', 'received', 'packing_in_progress', 'packed', 'completed');
		if (!in_array($status, $valid_statuses, true)) {
			return new WP_Error('invalid_param', 'Invalid status value', array('status' => 400));
		}

		// Build update data with timestamp based on status
		$update_data = array('status' => $status);
		if ($status === 'received') {
			$update_data['date_received'] = current_time('mysql');
		} elseif ($status === 'packed') {
			$update_data['date_packed'] = current_time('mysql');
		} elseif ($status === 'completed') {
			$update_data['date_completed'] = current_time('mysql');
		}

		$result = YBT_Tracking_Audit::apply_update(
			$id,
			$update_data,
			'status_change',
			array('format' => null, 'where_format' => array('%d'), 'source' => 'rest')
		);

		if ($result === false) {
			return new WP_Error('db_error', 'Failed to update tracking item', array('status' => 500));
		}

		if ($result === 0) {
			return new WP_Error('not_found', 'Tracking item not found', array('status' => 404));
		}

		do_action('ybt_tracking_updated', $id);

		return new WP_REST_Response(array('message' => 'Tracking item updated successfully'));
	}

	/**
	 * Delete tracking item
	 * FIXED: Added table existence check
	 */
	public function delete_tracking_item($request) {
		global $wpdb;

		// FIXED: Check table exists before querying
		if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
			return new WP_Error('db_not_ready', 'Database table not initialized', array('status' => 503));
		}

		$id = intval($request->get_param('id'));

		// Audit: capture the tracking number before the row disappears.
		$audit_item = $wpdb->get_row($wpdb->prepare(
			"SELECT tracking_number FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
			$id
		));

		$result = $wpdb->delete(
			$wpdb->prefix . 'ybt_tracking',
			array('id' => $id),
			array('%d')
		);

		if ($result === false) {
			return new WP_Error('db_error', 'Failed to delete tracking item', array('status' => 500));
		}

		if ($result === 0) {
			return new WP_Error('not_found', 'Tracking item not found', array('status' => 404));
		}

		YBT_Tracking_Audit::log_edit($id, 'delete', array(), array(
			'summary'         => 'Deleted tracking number',
			'tracking_number' => $audit_item ? $audit_item->tracking_number : null,
			'source'          => 'rest',
			'log_activity'    => false,
		));

		do_action('ybt_tracking_deleted', $id);

		return new WP_REST_Response(array('message' => 'Tracking item deleted successfully'));
	}

	/**
	 * Get statistics
	 * FIXED: Added ybt_cache() safety check and table existence check
	 */
	public function get_stats($request) {
		global $wpdb;

		// FIXED: Check table exists before querying
		if (function_exists('ybt_table_exists') && !ybt_table_exists($wpdb->prefix . 'ybt_tracking')) {
			return array(
				'total' => 0,
				'by_status' => array(
					'not_received' => 0,
					'received' => 0,
					'packing_in_progress' => 0,
					'packed' => 0,
					'completed' => 0
				)
			);
		}

		// FIXED: Check if caching layer is available
		if (!function_exists('ybt_cache')) {
			// Fallback: direct query without caching
			return $this->get_stats_direct();
		}

		return ybt_cache()->remember('api_stats', array($this, 'get_stats_direct'), 300, 'api');
	}

	/**
	 * Direct stats query (used as fallback or cache callback)
	 * NOTE: public — YBT_Cache::remember() invokes the callback via call_user_func()
	 * from outside this class, so a private method fatals on PHP 8+.
	 */
	public function get_stats_direct() {
		global $wpdb;
		
		return array(
			'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking"),
			'by_status' => array(
				'not_received'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE status = 'not_received'"),
				'received'            => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE status = 'received'"),
				'packing_in_progress' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE status = 'packing_in_progress'"),
				'packed'              => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE status = 'packed'"),
				'completed'           => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE status = 'completed'")
			)
		);
	}

	/**
	 * Handle webhook
	 */
	public function handle_webhook($request) {
		$data = $request->get_json_params();

		if (!is_array($data)) {
			return new WP_Error('invalid_payload', 'Webhook payload must be JSON', array('status' => 400));
		}

		do_action('ybt_webhook_received', $data);

		return new WP_REST_Response(array('message' => 'Webhook received'));
	}

	/**
	 * Check API permission
	 * FIXED: Added encryption class safety check and rate limiting
	 */
	public function check_api_permission($request) {
		// Check for API key in header
		$api_key = $request->get_header('X-API-Key');

		if ($api_key) {
			$stored_key_hash = get_option('ybt_api_key_hash');

			if (!empty($stored_key_hash)) {
				// FIXED: Safety check for encryption class
				if (function_exists('ybt_encryption') && class_exists('YBT_Encryption')) {
					$provided_key_hash = ybt_encryption()->hash_api_key($api_key);
				} elseif (function_exists('hash')) {
					// Fallback: simple hash comparison
					$provided_key_hash = hash('sha256', $api_key);
				} else {
					// Last resort: direct comparison (less secure)
					$provided_key_hash = $api_key;
				}

				if (hash_equals($stored_key_hash, $provided_key_hash)) {
					// FIXED: Add rate limiting for API key requests (non-admin)
					if (!current_user_can('manage_options') && function_exists('ybt_check_rate_limit')) {
						$ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';
						if (!ybt_check_rate_limit('rest_api_key', 100, 300)) {
							return new WP_Error('rate_limit', 'Too many requests', array('status' => 429));
						}
					}
					// SECURITY: Bind API key to a specific user and require admin
					// privileges, matching includes/admin-rest-api.php. The key is
					// assigned to whoever rotates it (see ybt_rotate_api_key_handler).
					$key_user_id = (int) get_option('ybt_api_key_user_id', 0);
					if ($key_user_id <= 0) {
						return new WP_Error('rest_forbidden', 'API key not bound to any user. Rotate the API key to bind it.', array('status' => 403));
					}
					$key_user = get_userdata($key_user_id);
					if (!$key_user || !user_can($key_user->ID, 'manage_options')) {
						return new WP_Error('rest_forbidden', 'API key user lacks admin privileges', array('status' => 403));
					}
					wp_set_current_user($key_user->ID);
					return true;
				}
			}
		}

		// Fallback to WordPress authentication
		if (is_user_logged_in() && current_user_can('manage_options')) {
			return true;
		}

		return new WP_Error('forbidden', 'Authentication required', array('status' => 401));
	}

	/**
	 * Verify webhook signature
	 */
	public function verify_webhook_signature($request) {
		$signature = $request->get_header('X-YBT-Signature');
		$timestamp = $request->get_header('X-YBT-Timestamp');
		$nonce = $request->get_header('X-YBT-Nonce');

		if (empty($signature)) {
			return new WP_Error('missing_signature', 'X-YBT-Signature header required', array('status' => 401));
		}

		$secret = get_option('ybt_webhook_secret');
		if (empty($secret)) {
			error_log('YBT SECURITY: Webhook secret not configured');
			return new WP_Error('not_configured', 'Webhook not configured', array('status' => 500));
		}

		// Validate timestamp to prevent replay attacks
		if (!empty($timestamp)) {
			$request_time = intval($timestamp);
			if (abs(time() - $request_time) > 300) {
				return new WP_Error('expired', 'Request timestamp expired', array('status' => 401));
			}
		}

		// SECURITY: Idempotency check — reject duplicate nonces within the window
		if (!empty($nonce)) {
			$nonce_key = 'ybt_webhook_nonce_' . md5($nonce);
			if (get_transient($nonce_key)) {
				error_log('YBT SECURITY: Duplicate webhook nonce rejected');
				return new WP_Error('duplicate', 'Duplicate webhook request', array('status' => 409));
			}
			set_transient($nonce_key, 1, 600); // 10-minute dedup window
		}

		$body = $request->get_body();
		$expected_signature = hash_hmac('sha256', $body, $secret);

		if (!hash_equals($expected_signature, $signature)) {
			error_log('YBT SECURITY: Invalid webhook signature');
			return new WP_Error('invalid_signature', 'Invalid signature', array('status' => 401));
		}

		return true;
	}

	/**
	 * Get collection parameters for pagination/filtering
	 */
	private function get_collection_params() {
		return array(
			'page' => array(
				'description'       => 'Current page number',
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => function($param) {
					return is_numeric($param) && $param >= 1;
				}
			),
			'per_page' => array(
				'description'       => 'Items per page',
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => function($param) {
					return is_numeric($param) && $param >= 1 && $param <= 100;
				}
			),
			'status' => array(
				'description'       => 'Filter by status',
				'type'              => 'string',
				'enum'              => array('not_received', 'received', 'packing_in_progress', 'packed', 'completed'),
				'sanitize_callback' => 'sanitize_text_field'
			),
			'search' => array(
				'description'       => 'Search tracking number or description',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field'
			)
		);
	}

	/**
	 * Get tracking schema for validation
	 */
	private function get_tracking_schema() {
		return array(
			'tracking_number' => array(
				'description'       => 'Unique tracking number',
				'type'              => 'string',
				'required'          => true,
				'minLength'         => 1,
				'maxLength'         => 255,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function($param) {
					return !empty($param) && preg_match('/^[A-Za-z0-9\-]+$/', $param);
				}
			),
			'user_id' => array(
				'description'       => 'User ID to assign tracking to',
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'sanitize_callback' => 'absint'
			),
			'description' => array(
				'description'       => 'Item description',
				'type'              => 'string',
				'maxLength'         => 500,
				'sanitize_callback' => 'sanitize_textarea_field'
			),
			'status' => array(
				'description'       => 'Tracking status',
				'type'              => 'string',
				'default'           => 'not_received',
				'enum'              => array('not_received', 'received', 'packing_in_progress', 'packed', 'completed'),
				'sanitize_callback' => 'sanitize_text_field'
			)
		);
	}

	/**
	 * Customer lookup by username
	 * Returns user details when admin types username in add tracking form
	 */
	public function customer_lookup($request) {
		$username = sanitize_text_field($request->get_param('username'));

		if (empty($username)) {
			return new WP_Error('missing_username', 'Username is required', array('status' => 400));
		}

		// Search for user by username (partial match)
		$users = get_users(array(
			'search'         => '*' . sanitize_text_field($username) . '*',
			'search_columns' => array('user_login', 'display_name'),
			'number'         => 10,
		));

		if (empty($users)) {
			return new WP_Error('not_found', 'No users found', array('status' => 404));
		}

		$results = array();
		foreach ($users as $user) {
			// Get additional user meta if available
			$custom_user_id = get_user_meta($user->ID, 'ybt_custom_user_id', true);
			$phone = get_user_meta($user->ID, 'billing_phone', true);
			$address = get_user_meta($user->ID, 'billing_address_1', true);
			$city = get_user_meta($user->ID, 'billing_city', true);

			$results[] = array(
				'id'             => $user->ID,
				'username'        => $user->user_login,
				'display_name'   => $user->display_name,
				'email'          => $user->user_email,
				'custom_user_id' => $custom_user_id ?: '',
				'phone'          => $phone ?: '',
				'address'        => $address ?: '',
				'city'           => $city ?: '',
			);
		}

		return new WP_REST_Response(array(
			'success' => true,
			'users'    => $results
		), 200);
	}
}

// FIXED: Initialize REST API through WordPress standard hook
// Note: Singleton pattern prevents multiple instantiations
add_action('rest_api_init', function() {
	$api = YBT_REST_API::instance();
	$api->register_routes();
});