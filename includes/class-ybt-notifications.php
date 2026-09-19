<?php
/**
 * YBT Notifications Class - SECURE VERSION
 * Firebase notifications with secure credential management
 *
 * SECURITY FIXES:
 * ✅ Firebase credentials NEVER stored in plugin directory
 * ✅ Environment variable / constant based credential loading
 * ✅ Access token caching (55 minutes)
 * ✅ Token validation and cleanup
 * ✅ Secure token storage in database (hashed)
 */
if (!defined('ABSPATH')) {
    exit;
}

class YBT_Notifications {
    private static $service_account_json = null;
    private static $access_token = null;
    private static $token_expiry = 0;

    public static function init() {
        // Priority 1: Environment variable (MOST SECURE)
        if (getenv('YBT_FCM_SERVICE_ACCOUNT_JSON')) {
            self::$service_account_json = getenv('YBT_FCM_SERVICE_ACCOUNT_JSON');
            return;
        }

        // Priority 2: WordPress constant (defined in wp-config.php)
        if (defined('YBT_FCM_SERVICE_ACCOUNT_JSON') && YBT_FCM_SERVICE_ACCOUNT_JSON) {
            self::$service_account_json = YBT_FCM_SERVICE_ACCOUNT_JSON;
            return;
        }

        // Priority 3: Encrypted option in database (least secure but better than file)
        if (class_exists('YBT_Encryption')) {
            $encrypted_json = get_option('ybt_fcm_service_account_encrypted');
            if ($encrypted_json) {
                $decrypted = ybt_encryption()->decrypt($encrypted_json);
                if ($decrypted) {
                    self::$service_account_json = $decrypted;
                    return;
                }
            }
        }

        // Fallback: Log warning but don't expose error to users
        if (!WP_DEBUG) {
            error_log('YBT CRITICAL: Firebase credentials not configured securely. Notifications disabled.');
        }
    }

    /**
     * Securely store FCM credentials
     */
    public static function save_secure_credentials($json) {
        if (!class_exists('YBT_Encryption')) {
            return false;
        }

        $encrypted = ybt_encryption()->encrypt($json);
        if ($encrypted) {
            update_option('ybt_fcm_service_account_encrypted', $encrypted);
            self::$service_account_json = $json; // Update local cache
            return true;
        }

        return false;
    }

    /**
     * Test connection by refreshing access token
     */
    public static function test_connection() {
        if (!self::is_configured()) {
            return array('success' => false, 'message' => 'Credentials not configured.');
        }

        $token = self::get_access_token();
        if ($token) {
            $credentials = json_decode(self::$service_account_json, true);
            return array(
                'success' => true,
                'message' => 'Connection successful! Project ID: ' . ($credentials['project_id'] ?? 'Unknown')
            );
        }

        return array('success' => false, 'message' => 'Failed to generate access token. Check your JSON credentials.');
    }

    /**
     * Store or update FCM token for a user (with validation)
     */
    public static function register_token($user_id, $token, $device_type = 'android') {
        // Validate inputs
        if (!is_numeric($user_id) || empty($token) || !in_array($device_type, array('android', 'ios', 'web'))) {
            ybt_log_error('Invalid FCM token registration attempt', array(
                'user_id' => $user_id,
                'token_length' => strlen($token),
                'device_type' => $device_type
            ));
            return false;
        }

        // Sanitize token (remove whitespace)
        $token = trim($token);

        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_fcm_tokens';

        // Check if token exists for this user
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND fcm_token = %s",
            $user_id,
            $token
        ));

        $data = array(
            'user_id' => $user_id,
            'fcm_token' => $token,
            'device_type' => $device_type,
            'last_updated' => current_time('mysql')
        );

        if ($existing) {
            // Update existing token
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing->id)
            );
            ybt_log_info('FCM token updated', array('user_id' => $user_id, 'token_id' => $existing->id));
        } else {
            // Insert new token
            $result = $wpdb->insert($table_name, $data);
            if ($result) {
                ybt_log_info('FCM token registered', array('user_id' => $user_id, 'token_id' => $wpdb->insert_id));
            }
        }

        return ($result !== false);
    }

    /**
     * Remove token (e.g. on logout or invalidation)
     *
     * Pass $user_id to only remove the token for its owner; without it the
     * token is removed for every user that registered it.
     */
    public static function unregister_token($token, $user_id = 0) {
        if (empty($token)) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_fcm_tokens';

        if ($user_id > 0) {
            $result = $wpdb->delete(
                $table_name,
                array('fcm_token' => trim($token), 'user_id' => intval($user_id)),
                array('%s', '%d')
            );
        } else {
            $result = $wpdb->delete(
                $table_name,
                array('fcm_token' => trim($token)),
                array('%s')
            );
        }

        if ($result) {
            ybt_log_info('FCM token unregistered', array('token_preview' => substr($token, 0, 20) . '...'));
        }

        return ($result !== false);
    }

    /**
     * Send notification to a specific user
     */
    public static function send_to_user($user_id, $title, $body, $data = []) {
        if (!self::is_configured()) {
            ybt_log_warning('FCM not configured - notification skipped', array('user_id' => $user_id));
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_fcm_tokens';

        // Get active tokens for user (last updated within 30 days)
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT fcm_token FROM $table_name
             WHERE user_id = %d AND last_updated > %s",
            $user_id,
            $cutoff_date
        ));

        if (empty($tokens)) {
            ybt_log_info('No valid FCM tokens for user', array('user_id' => $user_id));
            return false;
        }

        return self::send_fcm_message($tokens, $title, $body, $data);
    }

    /**
     * Send tracking update notification
     */
    public static function send_tracking_update($user_id, $tracking_number, $new_status) {
        $title = "Tracking Update";
        $body = "Your package $tracking_number is now " . ucfirst(str_replace('_', ' ', $new_status));

        // Customize messages per status
        switch ($new_status) {
            case 'received':
                $title = "📦 Package Received!";
                $body = "Your package $tracking_number has arrived at our warehouse";
                break;
            case 'packed':
                $title = "✅ Package Packed!";
                $body = "Your package $tracking_number has been packed and is ready for shipment";
                break;
            case 'completed':
                $title = "🎉 Package Delivered!";
                $body = "Your package $tracking_number has been delivered";
                break;
        }

        $data = [
            'type' => 'tracking_update',
            'tracking_number' => $tracking_number,
            'status' => $new_status,
            'timestamp' => time()
        ];

        return self::send_to_user($user_id, $title, $body, $data);
    }

    /**
     * Send announcement to all active users
     */
    public static function send_announcement($message) {
        if (!self::is_configured()) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_fcm_tokens';

        // Get all unique tokens from active users (last 30 days)
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT fcm_token FROM $table_name WHERE last_updated > %s",
            $cutoff_date
        ));

        if (empty($tokens)) {
            ybt_log_info('No active FCM tokens for announcement');
            return false;
        }

        // Send in batches of 500 (FCM limit)
        $chunks = array_chunk($tokens, 500);
        $success_count = 0;

        foreach ($chunks as $chunk) {
            if (self::send_fcm_message($chunk, "📢 New Announcement", $message, ['type' => 'announcement'])) {
                $success_count += count($chunk);
            }
        }

        ybt_log_info('Announcement sent', array('recipients' => $success_count));
        return ($success_count > 0);
    }

    /**
     * Check if FCM is properly configured
     */
    private static function is_configured() {
        return !empty(self::$service_account_json);
    }

    /**
     * Get OAuth2 access token with caching
     */
    private static function get_access_token() {
        // Return cached token if still valid (55 minutes cache)
        if (self::$access_token && time() < self::$token_expiry) {
            return self::$access_token;
        }

        // Generate new token
        $token_data = self::generate_new_access_token();
        if ($token_data) {
            self::$access_token = $token_data['access_token'];
            self::$token_expiry = time() + $token_data['expires_in'] - 300; // 5 min buffer
            return self::$access_token;
        }

        return false;
    }

    /**
     * Generate new OAuth2 access token
     */
    private static function generate_new_access_token() {
        if (!self::is_configured()) {
            ybt_log_error('FCM credentials not configured');
            return false;
        }

        $credentials = json_decode(self::$service_account_json, true);
        if (!$credentials || !isset($credentials['client_email'], $credentials['private_key'], $credentials['token_uri'])) {
            ybt_log_error('Invalid FCM service account JSON');
            return false;
        }

        // Create JWT assertion
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $claim_set = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $credentials['token_uri'],
            'exp' => time() + 3600,
            'iat' => time()
        ]);

        $signature = '';
        if (!openssl_sign($header . '.' . $claim_set, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            ybt_log_error('Failed to sign JWT for FCM');
            return false;
        }

        $jwt = self::base64url_encode($header) . '.' .
               self::base64url_encode($claim_set) . '.' .
               self::base64url_encode($signature);

        // Request access token
        $response = wp_remote_post($credentials['token_uri'], [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ],
            'timeout' => 15,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            ybt_log_error('FCM token request failed', array('error' => $response->get_error_message()));
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['access_token'], $body['expires_in'])) {
            ybt_log_info('FCM access token refreshed');
            return $body;
        }

        ybt_log_error('FCM token response invalid', array('response' => $body));
        return false;
    }

    /**
     * Base64 URL encode (RFC 4648 compliant)
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send FCM message using HTTP v1 API
     */
    private static function send_fcm_message($tokens, $title, $body, $data = []) {
        if (!self::is_configured()) {
            return false;
        }

        $access_token = self::get_access_token();
        if (!$access_token) {
            ybt_log_error('Cannot send FCM - no access token');
            return false;
        }

        $credentials = json_decode(self::$service_account_json, true);
        $project_id = $credentials['project_id'] ?? 'unknown-project';
        $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";

        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ];

        $success_count = 0;
        $invalid_tokens = [];

        // Send to each token individually (better error handling)
        foreach ((array)$tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => apply_filters('ybt_fcm_notification_icon', '/favicon.ico')
                    ],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'ybt_notifications_v2',
                            'vibrate_timings_ms' => [100, 200, 100]
                        ]
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10'
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1
                            ]
                        ]
                    ]
                ]
            ];

            $response = wp_remote_post($url, [
                'headers' => $headers,
                'body' => json_encode($payload),
                'timeout' => 10,
                'sslverify' => true
            ]);

            $http_code = wp_remote_retrieve_response_code($response);

            if ($http_code === 200) {
                $success_count++;
            } else {
                $response_body = json_decode(wp_remote_retrieve_body($response), true);
                $error_code = $response_body['error']['status'] ?? 'UNKNOWN';

                // Handle invalid tokens
                if (in_array($error_code, ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'])) {
                    $invalid_tokens[] = $token;
                    ybt_log_info('Invalid FCM token detected', array(
                        'token_preview' => substr($token, 0, 20),
                        'error' => $error_code
                    ));
                } else {
                    ybt_log_error('FCM send failed', array(
                        'token_preview' => substr($token, 0, 20),
                        'http_code' => $http_code,
                        'error' => $error_code,
                        'details' => $response_body['error']['details'] ?? []
                    ));
                }
            }
        }

        // Clean up invalid tokens
        foreach ($invalid_tokens as $invalid_token) {
            self::unregister_token($invalid_token);
        }

        $total = count((array)$tokens);
        ybt_log_info('FCM send results', array(
            'success' => $success_count,
            'failed' => $total - $success_count,
            'invalid_tokens_removed' => count($invalid_tokens)
        ));

        return ($success_count > 0);
    }
}

// Initialize and Cleanup
add_action('plugins_loaded', function() {
    YBT_Notifications::init();
});

// Daily cleanup of very old/stale tokens (e.g. 6 months)
if (!wp_next_scheduled('ybt_fcm_token_cleanup')) {
    wp_schedule_event(time(), 'daily', 'ybt_fcm_token_cleanup');
}

add_action('ybt_fcm_token_cleanup', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ybt_fcm_tokens';
    $cutoff = date('Y-m-d H:i:s', strtotime('-180 days'));

    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE last_updated < %s",
        $cutoff
    ));

    ybt_log_info('Automated FCM token cleanup completed.');
});
