<?php
/**
 * YBT API Authentication Handler
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Auth_Handler extends YBT_API_Handler_Base {

    /**
     * Mobile Login
     */
    public function login() {
        // Security: Rate limit login attempts (5 per 15 minutes)
        if (function_exists('ybt_check_rate_limit') && !ybt_check_rate_limit('api_login', 5, 900)) {
            YBT_API_Response::error('Too many login attempts. Please try again later.', 'rate_limited', 429);
            return;
        }

        // Security: Enforce HTTPS for API authentication (except local development/emulator).
        // Host is matched WITHOUT its port so dev setups (localhost:8080 Docker,
        // 10.0.2.2:8080 Android emulator) work, and the Host fallback is only
        // honoured from loopback/private source IPs so remote clients can't
        // spoof "Host: localhost" past the HTTPS requirement.
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        $host        = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        $is_loopback = in_array($remote_addr, ['127.0.0.1', '::1'], true);
        $is_remote   = (bool) filter_var($remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $is_local    = $is_loopback
            || (in_array($host, ['localhost', '127.0.0.1', '10.0.2.2'], true) && !$is_remote)
            || (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local');

        $enforce_https = get_option('ybt_enforce_https', '1') === '1';
        $is_https = function_exists('ybt_is_https') ? ybt_is_https() : is_ssl();

        if ($enforce_https && !$is_https && !$is_local && php_sapi_name() !== 'cli') {
            YBT_API_Response::error('Security error: Please use HTTPS', 'https_required', 403);
            return;
        }
        
        $username = $this->get_param('username');
        // SECURITY: Apply wp_unslash and cap password length to prevent DoS
        $password = isset($_POST['password']) ? substr(wp_unslash($_POST['password']), 0, 4096) : '';

        if (empty($username) || empty($password)) {
            YBT_API_Response::error('Username and password required', 'missing_credentials', 400);
            return;
        }

        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true
        );

        $user = wp_signon($creds, $is_https);

        if (is_wp_error($user)) {
            error_log('YBT DEBUG: wp_signon failed: ' . $user->get_error_message() . ' codes: ' . implode(',', $user->get_error_codes()));
            $this->log('Login failed', 'warning', array('username' => $username, 'error' => $user->get_error_message()));
            YBT_API_Response::error('Invalid credentials or user does not exist', 'invalid_login', 401);
            return;
        }

        // Clear rate limit on successful login
        if (function_exists('ybt_clear_rate_limit')) {
            ybt_clear_rate_limit('api_login');
        }

        $this->log('Login successful', 'info', array('username' => $username, 'user_id' => $user->ID));

        // Generate JWT token
        $token = YBT_API_Helper::generate_token($user->ID);

        // Register FCM Token if provided
        $fcm_token = $this->get_param('fcm_token');
        if (!empty($fcm_token) && class_exists('YBT_Notifications')) {
            YBT_Notifications::register_token($user->ID, $fcm_token, 'android');
        }

        $custom_user_id = get_user_meta($user->ID, 'ybt_custom_user_id', true) ?: 'Pending';

        YBT_API_Response::success(array(
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'custom_user_id' => $custom_user_id,
            'role' => user_can($user->ID, 'manage_options') ? 'admin' : 'user',
            'is_admin' => user_can($user->ID, 'manage_options'),
            'token' => $token
        ), 'Login successful');
    }

    /**
     * FIX REMOVED: any prior version that allowed a failed login to continue
     * into the success path has been corrected above by returning after the
     * wp_signon() failure branch.
     */

    /**
     * Mobile Registration
     */
    public function register() {
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        $host        = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        $is_loopback = in_array($remote_addr, ['127.0.0.1', '::1'], true);
        $is_remote   = (bool) filter_var($remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $is_local    = $is_loopback
            || (in_array($host, ['localhost', '127.0.0.1', '10.0.2.2'], true) && !$is_remote)
            || (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local');

        $enforce_https = get_option('ybt_enforce_https', '1') === '1';
        $is_https = function_exists('ybt_is_https') ? ybt_is_https() : is_ssl();

        if ($enforce_https && !$is_https && !$is_local && php_sapi_name() !== 'cli') {
            YBT_API_Response::error('Security error: Please use HTTPS', 'https_required', 403);
            return;
        }

        $username = $this->get_param('username');
        $email = $this->get_param('email', '', 'email');
        $password = isset($_POST['password']) ? substr(wp_unslash($_POST['password']), 0, 4096) : '';
        // Mobile number is optional: the app's registration screen does not
        // collect it, so requiring it here breaks all app registrations.
        $mobile_number = $this->get_param('mobile_number');

        if (empty($username) || empty($email) || empty($password)) {
            YBT_API_Response::error('All fields are required', 'missing_fields');
        }

        if (strlen($username) < 3) {
            YBT_API_Response::error('Username must be at least 3 characters', 'invalid_username');
        }

        if (!validate_username($username)) {
            YBT_API_Response::error('Invalid username format', 'invalid_username');
        }

        if (username_exists($username)) {
            YBT_API_Response::error('Username already exists', 'username_taken');
        }

        if (!is_email($email)) {
            YBT_API_Response::error('Invalid email address', 'invalid_email');
        }

        if (email_exists($email)) {
            YBT_API_Response::error('Email already exists', 'email_taken');
        }

        if (function_exists('ybt_security')) {
            $pwd_check = ybt_security()->validate_password($password);
            if (!$pwd_check['valid']) {
                YBT_API_Response::error(implode(' ', $pwd_check['errors']), 'weak_password');
            }
        } elseif (strlen($password) < 8) {
            YBT_API_Response::error('Password must be at least 8 characters', 'weak_password');
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            YBT_API_Response::error($user_id->get_error_message(), 'registration_failed');
        }

        $user = new WP_User($user_id);
        $user->set_role('subscriber');

        // Save Mobile Number (when given) and Custom YBT ID (Alignment with web registration)
        if (!empty($mobile_number)) {
            update_user_meta($user_id, 'ybt_mobile_number', $mobile_number);
        }
        
        // Assign Custom YBT ID (the user_register hook usually already did this)
        $custom_user_id = get_user_meta($user_id, 'ybt_custom_user_id', true);
        if (empty($custom_user_id) || 'Pending' === $custom_user_id) {
            global $wpdb;
            $max_id = $wpdb->get_var("SELECT MAX(CAST(SUBSTRING(meta_value, 4) AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = 'ybt_custom_user_id'");
            $custom_user_id = 'YBT' . str_pad(($max_id ? intval($max_id) + 1 : 1), 4, '0', STR_PAD_LEFT);
            update_user_meta($user_id, 'ybt_custom_user_id', $custom_user_id);
        }

        if (function_exists('ybt_log_activity')) {
            ybt_log_activity($user_id, 'register', "User registered via mobile app");
        }

        $this->log('New user registered', 'info', array('user_id' => $user_id, 'username' => $username, 'custom_id' => $custom_user_id));

        YBT_API_Response::success(array('custom_user_id' => $custom_user_id), 'Registration successful! Please login.');
    }

    /**
     * Update FCM Token
     */
    public function update_fcm() {
        $this->authenticate();

        $fcm_token = $this->get_param('fcm_token');
        $device_type = $this->get_param('device_type', 'android');

        if (empty($fcm_token)) {
            YBT_API_Response::error('FCM token required', 'missing_token');
        }

        if (class_exists('YBT_Notifications')) {
            YBT_Notifications::register_token($this->user_id, $fcm_token, $device_type);
            YBT_API_Response::success(null, 'FCM token updated');
        } else {
            YBT_API_Response::error('Notification system not available', 'system_error', 503);
        }
    }

    /**
     * Unregister FCM Token (Logout)
     */
    public function unregister_fcm() {
        $fcm_token = $this->get_param('fcm_token');

        if (empty($fcm_token)) {
            YBT_API_Response::error('FCM token required', 'missing_token');
        }

        // The app wipes its stored JWT before making this call, so a token is not
        // always available. When one is supplied, scope the delete to that user:
        // an unscoped delete lets one logout drop another account's row for the
        // same device token.
        $token_data = YBT_API_Helper::validate_token();
        $owner_id = $token_data ? intval($token_data['user_id']) : 0;

        if (class_exists('YBT_Notifications')) {
            YBT_Notifications::unregister_token($fcm_token, $owner_id);
            YBT_API_Response::success(null, 'FCM token unregistered');
        } else {
            YBT_API_Response::error('Notification system not available', 'system_error', 503);
        }
    }
}
