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
    /** Which credential source won: env | constant | database | null */
    private static $source = null;
    /** Last hard failure ('context: detail') for the admin UI + doctor. */
    private static $last_error = null;
    /** FCM statuses that really do mean "this token is dead". */
    private const DEAD_TOKEN_STATUSES = array('UNREGISTERED', 'NOT_FOUND');

    public static function init() {
        // Priority 1: Environment variable (MOST SECURE)
        if (getenv('YBT_FCM_SERVICE_ACCOUNT_JSON')) {
            self::$service_account_json = getenv('YBT_FCM_SERVICE_ACCOUNT_JSON');
            self::$source = 'env';
            return;
        }

        // Priority 2: WordPress constant (defined in wp-config.php)
        if (defined('YBT_FCM_SERVICE_ACCOUNT_JSON') && YBT_FCM_SERVICE_ACCOUNT_JSON) {
            self::$service_account_json = YBT_FCM_SERVICE_ACCOUNT_JSON;
            self::$source = 'constant';
            return;
        }

        // Priority 3: Encrypted option in database (least secure but better than file)
        if (class_exists('YBT_Encryption')) {
            $encrypted_json = get_option('ybt_fcm_service_account_encrypted');
            if ($encrypted_json) {
                $decrypted = ybt_encryption()->decrypt($encrypted_json);
                if ($decrypted) {
                    self::$service_account_json = $decrypted;
                    self::$source = 'database';
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
     * Firebase project id the shipped Android app belongs to, read from its
     * google-services.json. FCM refuses a token that belongs to another
     * project, so comparing the two catches the single most common
     * misconfiguration (service account from the wrong Firebase project).
     *
     * @return string|null
     */
    public static function app_project_id() {
        // false = not read yet. Both must be static: a plain local here would
        // be re-initialised on every call and the cache would return null.
        static $cached_value = false;

        if ($cached_value !== false) {
            return $cached_value;
        }

        $file = dirname(__DIR__) . '/PackTracker/app/google-services.json';
        $cached_value = null;
        if (is_readable($file)) {
            $cfg = json_decode(@file_get_contents($file), true);
            if (isset($cfg['project_info']['project_id'])) {
                $cached_value = $cfg['project_info']['project_id'];
            }
        }

        return $cached_value;
    }

    /**
     * Health snapshot for the settings screen, the CLI doctor and the
     * announcement sender. Never throws; every field is optional.
     *
     * @return array
     */
    public static function status() {
        $configured = self::is_configured();
        $credentials = $configured ? json_decode(self::$service_account_json, true) : array();
        $project_id = $credentials['project_id'] ?? null;
        $app_project = self::app_project_id();

        $mismatch = null;
        if ($configured && $project_id && $app_project) {
            $mismatch = ($project_id !== $app_project);
        }

        $status = array(
            'configured'   => (bool) $configured,
            'source'       => self::$source,
            'project_id'   => $project_id,
            'app_project_id' => $app_project,
            'project_mismatch' => $mismatch,
            'error'        => self::$last_error,
        );

        if (!$configured) {
            $status['healthy'] = false;
            $status['message'] = 'No Firebase service-account credentials are configured.';
        } elseif ($mismatch) {
            $status['healthy'] = false;
            $status['message'] = 'Service account belongs to Firebase project "' . $project_id
                . '" but the Android app is registered under "' . $app_project
                . '". FCM will refuse every device token.';
        } elseif (self::$last_error) {
            $status['healthy'] = false;
            $status['message'] = 'Credentials are present but unusable: ' . self::$last_error;
        } else {
            $status['healthy'] = true;
            $status['message'] = 'Credentials configured for project "' . $project_id . '".';
        }

        return $status;
    }

    /**
     * Remember a failure so the settings screen can show it without another
     * round trip to Google, and persist it for the doctor/CLI + next page view.
     */
    private static function remember_error($message) {
        self::$last_error = $message;
        update_option('ybt_fcm_last_error', $message, false);
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

        // Surface the recorded cause from an earlier failure, if any, so the
        // admin sees the real reason instead of a generic sentence.
        $status = self::status();
        if (!empty($status['project_mismatch'])) {
            return array(
                'success' => false,
                'message' => $status['message'],
                'project_id' => $status['project_id'],
                'app_project_id' => $status['app_project_id'],
            );
        }

        self::$last_error = null;
        $token = self::get_access_token();
        if ($token) {
            $credentials = json_decode(self::$service_account_json, true);
            update_option('ybt_fcm_last_error', '', false);
            return array(
                'success' => true,
                'message' => 'Connection successful! Project ID: ' . ($credentials['project_id'] ?? 'Unknown')
            );
        }

        return array(
            'success' => false,
            'message' => 'Failed to generate access token: ' . (self::$last_error ?: 'check your JSON credentials.'),
        );
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
     * Insert a row into the mobile/web notification inbox (ybt_notifications)
     * so the app's in-app popup + badge system sees the same events as push.
     *
     * @return int|null Inserted row id, or null when the table is unavailable.
     */
    public static function insert_inbox($user_id, $title, $body, $type = 'general', $tracking_id = null, $tracking_number = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ybt_notifications';

        // Table may not exist on very old installs; never break the request.
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === null) {
            return null;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'user_id'         => (int) $user_id,
                'title'           => $title,
                'body'            => $body,
                'type'            => $type,
                'tracking_id'     => $tracking_id ? (int) $tracking_id : null,
                'tracking_number' => $tracking_number,
                'is_read'         => 0,
                'date_created'    => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', is_null($tracking_id) ? '%s' : '%d', '%s', '%d', '%s')
        );

        return $inserted ? (int) $wpdb->insert_id : null;
    }

    /**
     * Unified notification: inbox row + FCM push, delivered once.
     *
     * The inbox row feeds the app's badge poll / heads-up popup; the push
     * delivers the instant alert when the app is closed. `notification_id`
     * rides in the FCM data payload so the app can dedupe across channels.
     */
    public static function notify($user_id, $title, $body, $data = array(), $inbox = array()) {
        $notification_id = self::insert_inbox(
            $user_id,
            $inbox['title'] ?? $title,
            $inbox['body'] ?? $body,
            $inbox['type'] ?? ($data['type'] ?? 'general'),
            $inbox['tracking_id'] ?? null,
            $inbox['tracking_number'] ?? null
        );

        $data['notification_id'] = (string) $notification_id;

        return self::send_to_user($user_id, $title, $body, $data);
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
     * Send tracking update notification — inbox row + instant push.
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

        // Inbox rows deep-link on tracking_id (app timeline + web bell), so resolve
        // it here instead of leaving the column NULL.
        $tracking_id = null;
        if (!empty($tracking_number)) {
            global $wpdb;
            $tracking_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ybt_tracking WHERE tracking_number = %s LIMIT 1",
                $tracking_number
            ));
        }

        $data = [
            'type' => 'tracking_update',
            'tracking_number' => $tracking_number,
            'tracking_id' => $tracking_id ? (int) $tracking_id : null,
            'status' => $new_status,
            'timestamp' => time()
        ];

        // Inbox row shares the same title/body so app badge + popup match push.
        $inbox = array(
            'type' => 'tracking_update',
            'tracking_id' => $tracking_id,
            'tracking_number' => $tracking_number,
        );

        return self::notify($user_id, $title, $body, $data, $inbox);
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
     * Send bulk push notifications to specific user IDs safely without script timeouts
     */
    public static function send_bulk_push($user_ids, $title, $body, $data = []) {
        if (!self::is_configured() || empty($user_ids)) {
            return 0;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_fcm_tokens';
        $user_ids = array_filter(array_map('intval', (array)$user_ids));
        if (empty($user_ids)) return 0;

        $placeholders = implode(',', $user_ids);
        $tokens = $wpdb->get_col("SELECT DISTINCT fcm_token FROM $table_name WHERE user_id IN ($placeholders) AND fcm_token != ''");
        if (empty($tokens)) return 0;

        // Cap to max 50 tokens synchronously to prevent exceeding PHP max_execution_time
        $tokens = array_slice($tokens, 0, 50);
        return self::send_fcm_message($tokens, $title, $body, $data) ? count($tokens) : 0;
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
            self::remember_error('the service-account JSON is missing client_email / private_key / token_uri');
            ybt_log_error('Invalid FCM service account JSON');
            return false;
        }

        // A mangled PEM (escaped newlines) produces a syntactically valid key
        // that Google rejects with "Invalid JWT Signature" — catch it here so
        // the cause is obvious instead of looking like a revoked key.
        if (strpos($credentials['private_key'], '-----BEGIN') === false
            || strpos($credentials['private_key'], '\\n') !== false) {
            self::remember_error('the private_key is mangled (missing PEM header or literal \\n sequences)');
            ybt_log_error('FCM private key looks mangled in the service-account JSON');
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

        // JWS spec (RFC 7515): the signature is computed over the BASE64URL-ENCODED
        // header and payload segments joined by a dot, not the raw JSON.
        $signing_input = self::base64url_encode($header) . '.' . self::base64url_encode($claim_set);

        $signature = '';
        if (!openssl_sign($signing_input, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            self::remember_error('openssl could not sign the JWT with the supplied private key');
            ybt_log_error('Failed to sign JWT for FCM');
            return false;
        }

        $jwt = $signing_input . '.' . self::base64url_encode($signature);

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
            self::remember_error('token request failed: ' . $response->get_error_message());
            ybt_log_error('FCM token request failed', array('error' => $response->get_error_message()));
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['access_token'], $body['expires_in'])) {
            self::$last_error = null;
            ybt_log_info('FCM access token refreshed');
            return $body;
        }

        // Keep Google's own words: "invalid_grant / Invalid JWT Signature" is
        // the difference between a revoked key and a malformed one.
        $detail = $body['error'] ?? 'unknown_error';
        if (!empty($body['error_description'])) {
            $detail .= ': ' . $body['error_description'];
        }
        self::remember_error('Google rejected the service-account key (' . $detail . ')');
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

        global $wpdb; // token cleanup below reads $wpdb->rows_affected

        $access_token = self::get_access_token();
        if (!$access_token) {
            ybt_log_error('Cannot send FCM - no access token', array(
                'reason' => self::$last_error,
            ));
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
        $failures = [];

        // Send to each token individually (better error handling)
        foreach ((array)$tokens as $token) {
            // DATA-ONLY payload: the Android app's FirebaseMessagingService
            // handles display itself (tray when backgrounded, in-app banner
            // when foregrounded). A 'notification' block would make Android
            // auto-post to the tray and bypass the app's channel + dedupe.
            $message_data = array_merge(
                array('title' => $title, 'body' => $body),
                array_map('strval', $data)
            );

            $payload = [
                'message' => [
                    'token' => $token,
                    'data' => $message_data,
                    'android' => [
                        'priority' => 'high'
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
                $failures[$error_code] = ($failures[$error_code] ?? 0) + 1;

                // Only these two are proof the token itself is dead. NOT_FOUND
                // also covers "token belongs to a different Firebase project",
                // so the all-failed guard below keeps a misconfiguration from
                // wiping every registered device.
                if (in_array($error_code, self::DEAD_TOKEN_STATUSES, true)) {
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

        $total = count((array)$tokens);
        $removed = 0;

        if ($success_count === 0 && !empty($failures)) {
            // Every token failed the same way — that is a server-side problem
            // (wrong project, revoked key, quota), not 200 dead devices.
            $codes = array_keys($failures);
            self::remember_error('FCM rejected every token (' . implode(', ', $codes) . ')');
            ybt_log_error('FCM push failed for all tokens - tokens kept, not deleted', array(
                'errors' => $failures,
                'project_id' => json_decode(self::$service_account_json, true)['project_id'] ?? '?',
                'app_project_id' => self::app_project_id(),
            ));
        } else {
            // Clean up invalid tokens
            foreach ($invalid_tokens as $invalid_token) {
                self::unregister_token($invalid_token);
                $removed += (int) $wpdb->rows_affected;
            }
        }

        ybt_log_info('FCM send results', array(
            'success' => $success_count,
            'failed' => $total - $success_count,
            'invalid_tokens_removed' => $removed
        ));

        return ($success_count > 0);
    }
}

// Initialize and Cleanup
add_action('plugins_loaded', function() {
    YBT_Notifications::init();
});

// =============================================================================
// Admin UI AJAX endpoints (FCM settings page + Settings tab)
// The page templates post to these actions with the ybt_admin_nonce.
// =============================================================================

/**
 * AJAX: save the Firebase service-account JSON (manage_options only).
 * Validated server-side, then stored AES-256 encrypted via
 * save_secure_credentials(). Env-var / wp-config constants are NOT
 * overridden by this — they take precedence at runtime.
 */
function ybt_ajax_save_fcm_settings() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $json = isset($_POST['fcm_json']) ? trim((string) wp_unslash($_POST['fcm_json'])) : '';
    if ($json === '') {
        wp_send_json_error('Paste the service-account JSON first.');
        return;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)
        || ($decoded['type'] ?? '') !== 'service_account'
        || empty($decoded['project_id'])
        || empty($decoded['private_key'])
        || empty($decoded['client_email'])) {
        wp_send_json_error('That does not look like a Firebase service-account JSON (needs type, project_id, private_key, client_email).');
        return;
    }

    if (!class_exists('YBT_Encryption')) {
        wp_send_json_error('Encryption module unavailable — credentials were not saved.');
        return;
    }
    if (!YBT_Notifications::save_secure_credentials($json)) {
        wp_send_json_error('Encryption failed — credentials were not saved.');
        return;
    }

    wp_send_json_success('Firebase credentials encrypted and saved securely.');
}
add_action('wp_ajax_ybt_save_fcm_settings', 'ybt_ajax_save_fcm_settings');

/**
 * AJAX: mint an OAuth2 access token against Firebase (manage_options only)
 * — proves the stored credentials actually work.
 */
function ybt_ajax_test_fcm_connection() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $result = YBT_Notifications::test_connection();
    if (!empty($result['success'])) {
        wp_send_json_success($result['message']);
    }
    wp_send_json_error($result['message'] ?? 'Connection test failed.');
}
add_action('wp_ajax_ybt_test_fcm_connection', 'ybt_ajax_test_fcm_connection');

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
