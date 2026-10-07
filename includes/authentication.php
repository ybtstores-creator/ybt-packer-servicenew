<?php
/**
 * YBT Authentication AJAX Handlers - Refactored
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle user login
 */
function ybt_ajax_login_handler() {
    // Security: Enforce HTTPS for authentication (optionally bypassed for localhost/development)
    $enforce_https = get_option('ybt_enforce_https', '1') === '1';
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $is_local = in_array($remote_ip, array('127.0.0.1', '::1')) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    $is_https = function_exists('ybt_is_https') ? ybt_is_https() : is_ssl();
    
    if ($enforce_https && !$is_https && !$is_local) {
        wp_send_json_error('Security error: Please use HTTPS to log in.');
        return;
    }
    
    check_ajax_referer('ybt_nonce', 'nonce');

    // Rate limit login attempts: 5 attempts per 5 minutes
    if (!ybt_check_rate_limit('login_attempts', 5, 300)) {
        wp_send_json_error('Too many login attempts. Please try again in 5 minutes.');
        return;
    }

    $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';

    if (empty($username) || empty($password)) {
        wp_send_json_error('Username and password are required');
        return;
    }

    $user = wp_signon(array(
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => $remember
    ), $is_https);

    if (is_wp_error($user)) {
        if (function_exists('ybt_log_activity')) {
             ybt_log_activity(0, 'login_failed', "Failed login for $username");
        }
        wp_send_json_error('Invalid username or password.');
        return;
    }

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember, $is_https);

    if (function_exists('ybt_log_activity')) {
        ybt_log_activity($user->ID, 'login', 'User logged in successfully.');
    }

    $mobile_token = '';
    if (isset($_POST['generate_token'])) {
        $mobile_token = ybt_generate_mobile_token($user->ID);
    }

    wp_send_json_success(array(
        'message' => 'Login successful',
        'redirect' => home_url('/dashboard/'),
        'token' => $mobile_token
    ));
    return;
}
add_action('wp_ajax_nopriv_ybt_ajax_login', 'ybt_ajax_login_handler');
add_action('wp_ajax_ybt_ajax_login', 'ybt_ajax_login_handler');

/**
 * Handle user registration
 */
function ybt_register_user_handler() {
    // Security: Enforce HTTPS for registration (bypass for localhost/dev, matching
    // the ybt_ajax_login_handler policy so HTTP sites can still register).
    $enforce_https = get_option('ybt_enforce_https', '1') === '1';
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $is_local = in_array($remote_ip, array('127.0.0.1', '::1')) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    $is_https = function_exists('ybt_is_https') ? ybt_is_https() : is_ssl();
    if ($enforce_https && !$is_https && !$is_local) {
        wp_send_json_error('Security error: Please use HTTPS to register.');
        return;
    }
    
    check_ajax_referer('ybt_nonce', 'nonce');

    if (!ybt_check_rate_limit('registration', 3, 3600)) {
        wp_send_json_error('Too many registration attempts. Please try again later.');
        return;
    }

    // SECURITY: respect the site-wide registration switch; without this check
    // disabling "Anyone can register" in Settings did not close this endpoint.
    if (!get_option('users_can_register')) {
        wp_send_json_error('Registration is currently disabled.');
        return;
    }

    $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $mobile_number = isset($_POST['mobile_number']) ? sanitize_text_field(wp_unslash($_POST['mobile_number'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($email) || empty($password) || empty($mobile_number)) {
        wp_send_json_error('All fields are required');
        return;
    }

    if (function_exists('ybt_security')) {
        $pwd_check = ybt_security()->validate_password($password);
        if (!$pwd_check['valid']) {
            wp_send_json_error(implode(' ', $pwd_check['errors']));
            return;
        }
    }

    if (username_exists($username) || email_exists($email)) {
        wp_send_json_error('Username or email already exists');
        return;
    }

    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        wp_send_json_error($user_id->get_error_message());
        return;
    }

    // SECURITY: never inherit default_role — an anonymous signup must not be
    // able to land as author/editor/admin (same pin as mobile-auth.php).
    (new WP_User($user_id))->set_role('subscriber');

    // Save Mobile Number
    update_user_meta($user_id, 'ybt_mobile_number', $mobile_number);

    // Assign Custom YBT ID (the user_register hook usually already did this)
    $custom_user_id = get_user_meta($user_id, 'ybt_custom_user_id', true);
    if (empty($custom_user_id) || 'Pending' === $custom_user_id) {
        global $wpdb;
        $max_id = $wpdb->get_var("SELECT MAX(CAST(SUBSTRING(meta_value, 4) AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = 'ybt_custom_user_id'");
        $custom_user_id = 'YBT' . str_pad(($max_id ? intval($max_id) + 1 : 1), 4, '0', STR_PAD_LEFT);
        update_user_meta($user_id, 'ybt_custom_user_id', $custom_user_id);
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    if (function_exists('ybt_log_activity')) {
        ybt_log_activity($user_id, 'register', 'New user registered.');
    }

    wp_send_json_success('Registration successful');
    return;
}
add_action('wp_ajax_nopriv_ybt_register_user', 'ybt_register_user_handler');

/**
 * Handle user profile update
 */
function ybt_update_profile_handler() {
    check_ajax_referer('ybt_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
        return;
    }

    $user_id = get_current_user_id();
    $mobile_number = isset($_POST['mobile_number']) ? sanitize_text_field(wp_unslash($_POST['mobile_number'])) : '';

    // Optional: add validation for mobile number format here

    update_user_meta($user_id, 'ybt_mobile_number', $mobile_number);

    if (function_exists('ybt_log_activity')) {
        ybt_log_activity($user_id, 'profile_update', 'User updated their mobile number.');
    }

    wp_send_json_success('Profile updated successfully');
    return;
}
add_action('wp_ajax_ybt_update_profile', 'ybt_update_profile_handler');

/**
 * Handle password reset request
 */
function ybt_reset_password_request_handler() {
    // Security: Enforce HTTPS for password reset (respect enforce_https setting and localhost/proxy)
    $enforce_https = get_option('ybt_enforce_https', '1') === '1';
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $is_local = in_array($remote_ip, array('127.0.0.1', '::1')) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    $is_https = function_exists('ybt_is_https') ? ybt_is_https() : is_ssl();
    if ($enforce_https && !$is_https && !$is_local) {
        wp_send_json_error('Security error: Please use HTTPS.');
        return;
    }
    
    check_ajax_referer('ybt_nonce', 'nonce');

    // SECURITY: Rate-limit password reset requests to prevent email flooding
    if (!ybt_check_rate_limit('password_reset', 3, 900)) {
        wp_send_json_error('Too many reset requests. Please try again in 15 minutes.');
        return;
    }

    $user_login = isset($_POST['user_login']) ? sanitize_text_field($_POST['user_login']) : '';
    if (empty($user_login)) {
        wp_send_json_error('Username or email required');
        return;
    }

    $user = get_user_by('login', $user_login) ?: get_user_by('email', $user_login);

    if (!$user) {
        // Obfuscate success to prevent user enumeration
        wp_send_json_success('If that account exists, a reset link has been sent.');
        return;
    }

    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
        wp_send_json_error('Reset failed');
        return;
    }

    $reset_url = home_url("/reset-password/?key=$key&login=" . rawurlencode($user->user_login));

    // Secure HTML email with proper headers
    $site_name = get_bloginfo('name');
    $subject = "Password Reset - $site_name";
    
    $message = sprintf(
        '<html><body>
        <div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
            <h2 style="color: #10b981;">Password Reset Request</h2>
            <p>Hello %s,</p>
            <p>You requested a password reset for your account at %s.</p>
            <p><a href="%s" style="background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">Reset Your Password</a></p>
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #666;">%s</p>
            <p>This link will expire in 24 hours.</p>
            <p>If you did not request this, please ignore this email.</p>
            <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
            <p style="color: #999; font-size: 12px;">This is an automated message from %s</p>
        </div>
        </body></html>',
        esc_html($user->display_name),
        esc_html($site_name),
        esc_url($reset_url),
        esc_url($reset_url),
        esc_html($site_name)
    );

    // Set proper email headers for HTML
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    );
    
    wp_mail($user->user_email, $subject, $message, $headers);

    wp_send_json_success('Instructions sent to your email.');
    return;
}
add_action('wp_ajax_nopriv_ybt_reset_password_request', 'ybt_reset_password_request_handler');

/**
 * Password reset step 2: complete the reset started by ybt_reset_password_request.
 * Uses the WP core reset key (get_password_reset_key) issued in step 1.
 */
function ybt_reset_password_process_handler() {
    // Security: Enforce HTTPS for password reset (respect enforce_https setting and localhost/proxy)
    $enforce_https = get_option('ybt_enforce_https', '1') === '1';
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $is_local = in_array($remote_ip, array('127.0.0.1', '::1')) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    $is_https = function_exists('ybt_is_https') ? ybt_is_https() : is_ssl();
    if ($enforce_https && !$is_https && !$is_local) {
        wp_send_json_error('Security error: Please use HTTPS.');
        return;
    }

    check_ajax_referer('ybt_nonce', 'nonce');

    // SECURITY: Rate-limit reset-key attempts (keys are 20 chars, but brute-force + DoS guard)
    if (function_exists('ybt_check_rate_limit') && !ybt_check_rate_limit('password_reset_process', 5, 900)) {
        wp_send_json_error('Too many attempts. Please try again in 15 minutes.');
        return;
    }

    $key   = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
    $login = isset($_POST['login']) ? sanitize_text_field(wp_unslash($_POST['login'])) : '';
    $pass1 = isset($_POST['pass1']) ? (string) wp_unslash($_POST['pass1']) : '';
    $pass2 = isset($_POST['pass2']) ? (string) wp_unslash($_POST['pass2']) : '';

    if (empty($key) || empty($login)) {
        wp_send_json_error('This reset link is invalid. Please request a new one.');
        return;
    }

    if ($pass1 !== $pass2) {
        wp_send_json_error('Passwords do not match.');
        return;
    }

    if (class_exists('YBT_Security')) {
        $pwd_check = ybt_security()->validate_password($pass1);
        if (!$pwd_check['valid']) {
            wp_send_json_error(implode(' ', $pwd_check['errors']));
            return;
        }
    } elseif (strlen($pass1) < 8) {
        wp_send_json_error('Password must be at least 8 characters.');
        return;
    }

    $user = check_password_reset_key($key, $login);
    if (is_wp_error($user)) {
        wp_send_json_error('This reset link is invalid or has expired. Please request a new one.');
        return;
    }

    reset_password($user, $pass1);

    if (function_exists('ybt_log_activity')) {
        ybt_log_activity($user->ID, 'password_reset', 'Password reset via email reset link');
    }

    wp_send_json_success('Password updated successfully. Redirecting to sign in...');
}
add_action('wp_ajax_ybt_reset_password_process', 'ybt_reset_password_process_handler');
add_action('wp_ajax_nopriv_ybt_reset_password_process', 'ybt_reset_password_process_handler');
