<?php
/**
 * YBT Mobile API Auth Handlers
 * FIXED: Password authentication, undefined $ip, rate limit consistency, security logging
 */
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Mobile Login Handler
 * FIXED: Now properly authenticates password before issuing token
 */
function ybt_mobile_login_handler() {
	ybt_clear_output_buffers();

	// Define $ip first for logging and rate limiting
	$ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';

	// Rate limiting for login attempts (5 attempts per 15 minutes)
	if (!ybt_check_rate_limit('mobile_login', 5, 900)) {
		if (function_exists('ybt_log_security_event')) {
			ybt_log_security_event('MOBILE_LOGIN_RATE_LIMIT_EXCEEDED', array('ip' => $ip));
		}
		error_log("YBT SECURITY: Too many login attempts from IP: $ip");
		wp_send_json(['success' => false, 'message' => 'Too many failed attempts. Please try again later.']);
		return;
	}

	// Sanitize and validate input
	$username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
	$password = isset($_POST['password']) ? str_replace(array("\r", "\n"), '', wp_unslash($_POST['password'])) : '';

	// Check for required credentials
	if (empty($username) || empty($password)) {
		wp_send_json(['success' => false, 'message' => 'Credentials required']);
		return; // IMPORTANT: Added return to stop execution
	}

	// 🔐 CRITICAL FIX: Authenticate user with password verification
	// wp_authenticate() verifies both that the user exists AND that the password is correct
	$user = wp_authenticate($username, $password);
	if (is_wp_error($user)) {
		if (function_exists('ybt_log_security_event')) {
			ybt_log_security_event('MOBILE_LOGIN_FAILED', array('ip' => $ip, 'username' => $username));
		}
		// Generic message to prevent username enumeration
		wp_send_json(['success' => false, 'message' => 'Invalid username or password']);
		return;
	}

	// Clear login attempts on successful login using helper function for consistency
	ybt_clear_rate_limit('mobile_login');

	// Generate JWT token for authenticated user
	$token = ybt_generate_mobile_token($user->ID);

	// Optional FCM Token registration for push notifications
	if (!empty($_POST['fcm_token']) && class_exists('YBT_Notifications')) {
		YBT_Notifications::register_token($user->ID, sanitize_text_field($_POST['fcm_token']), 'android');
	}

	// Log successful login
	if (function_exists('ybt_log_security_event')) {
		ybt_log_security_event('MOBILE_LOGIN_SUCCESS', array(
			'user_id' => $user->ID,
			'username' => $user->user_login,
			'ip' => $ip
		));
	}
	error_log("YBT: Mobile login successful for user ID: {$user->ID} from IP: $ip");

	// Build role string — Android uses @SerializedName("role") for isAdmin() check
	$is_admin = user_can( $user->ID, 'manage_options' );
	$role_str = $is_admin ? 'admin' : 'user';

	// Fetch extra profile fields
	$custom_user_id = get_user_meta( $user->ID, 'ybt_custom_user_id', true );

	wp_send_json([
		'success' => true,
		'data' => [
			'user_id'        => $user->ID,
			'username'       => $user->user_login,
			'email'          => $user->user_email,
			'display_name'   => $user->display_name,
			'custom_user_id' => $custom_user_id ? $custom_user_id : 'Pending',
			'role'           => $role_str,
			'is_admin'       => $is_admin,
			'token'          => $token,
		]
	]);
}

/**
 * Mobile Registration Handler
 */
function ybt_mobile_register_handler() {
	ybt_clear_output_buffers();

	// Define $ip for logging and rate limiting
	$ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';

	// Rate limiting for registration attempts (3 attempts per 15 minutes)
	if (!ybt_check_rate_limit('mobile_reg', 3, 900)) {
		if (function_exists('ybt_log_security_event')) {
			ybt_log_security_event('MOBILE_REG_RATE_LIMIT_EXCEEDED', array('ip' => $ip));
		}
		error_log("YBT SECURITY: Too many registration attempts from IP: $ip");
		wp_send_json(['success' => false, 'message' => 'Too many registration attempts. Please try again later.']);
		return;
	}

	// Sanitize and validate input
	$username = sanitize_user(wp_unslash($_POST['username'] ?? ''));
	$email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
	$password = isset($_POST['password']) ? str_replace(array("\r", "\n"), '', wp_unslash($_POST['password'])) : '';

	// Check for required fields
	if (empty($username) || empty($email) || empty($password)) {
		wp_send_json(['success' => false, 'message' => 'All fields are required']);
		return;
	}

	// Validate email format
	if (!is_email($email)) {
		wp_send_json(['success' => false, 'message' => 'Invalid email address']);
		return;
	}

	// Validate username length and format
	if (strlen($username) < 3 || strlen($username) > 60) {
		wp_send_json(['success' => false, 'message' => 'Username must be between 3 and 60 characters']);
		return;
	}

	if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
		wp_send_json(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores']);
		return;
	}

	// Validate password strength
	if (function_exists('ybt_security')) {
		$pwd_check = ybt_security()->validate_password($password);
		if (!$pwd_check['valid']) {
			wp_send_json(['success' => false, 'message' => implode(' ', $pwd_check['errors'])]);
			return;
		}
	} elseif (strlen($password) < 8) {
		wp_send_json(['success' => false, 'message' => 'Password must be at least 8 characters long']);
		return;
	}

	// Check if user or email already exists
	if (username_exists($username) || email_exists($email)) {
		wp_send_json(['success' => false, 'message' => 'User already exists']);
		return;
	}

	// Create user with enhanced security
	$user_id = wp_create_user($username, $password, $email);
	if (is_wp_error($user_id)) {
		wp_send_json(['success' => false, 'message' => 'Registration failed. Please try again.']);
		return;
	}

	// Clear registration attempts on success using helper function
	ybt_clear_rate_limit('mobile_reg');

	// Set default role for mobile users
	$user = new WP_User($user_id);
	$user->set_role('subscriber');

	// Save mobile number if provided (aligned with REST registration)
	if (isset($_POST['mobile_number'])) {
		update_user_meta($user_id, 'ybt_mobile_number', sanitize_text_field(wp_unslash($_POST['mobile_number'])));
	}

	// Assign a custom YBT user ID (aligned with REST registration)
	$custom_user_id = get_user_meta($user_id, 'ybt_custom_user_id', true);
	if (empty($custom_user_id) || 'Pending' === $custom_user_id) {
		global $wpdb;
		$max_id = $wpdb->get_var("SELECT MAX(CAST(SUBSTRING(meta_value, 4) AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = 'ybt_custom_user_id'");
		$custom_user_id = 'YBT' . str_pad(($max_id ? intval($max_id) + 1 : 1), 4, '0', STR_PAD_LEFT);
		update_user_meta($user_id, 'ybt_custom_user_id', $custom_user_id);
	}

	// Log successful registration
	if (function_exists('ybt_log_security_event')) {
		ybt_log_security_event('MOBILE_REG_SUCCESS', array(
			'user_id' => $user_id,
			'username' => $username,
			'email' => $email,
			'ip' => $ip
		));
	}
	error_log("YBT: Mobile registration successful for user ID: $user_id from IP: $ip");

	wp_send_json([
		'success' => true,
		'message' => 'Registered successfully',
		'data'    => array('custom_user_id' => $custom_user_id)
	]);
}

/**
 * Note: AJAX actions for 'ybt_mobile_login' and 'ybt_mobile_register'
 * are now centrally registered and handled by YBT_API_Router (YBT_API_Auth_Handler).
 * The functions below are retained for backwards compatibility with any direct callers.
 */


/**
 * Utility to clear output buffers for clean JSON output
 */
if (!function_exists('ybt_clear_output_buffers')) {
	function ybt_clear_output_buffers() {
		while (ob_get_level()) {
			ob_end_clean();
		}
	}
}