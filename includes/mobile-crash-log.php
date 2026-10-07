<?php
/**
 * YBT Mobile Crash Log endpoint.
 *
 * Receives uncaught-exception reports from the Android apps so field crashes
 * become diagnosable. Uses the same admin-ajax action pattern as the other
 * mobile endpoints; auth is OPTIONAL (a crashing app may not have a session,
 * but reports it can still identify are more useful).
 *
 * Request (POST wp-admin/admin-ajax.php):
 *   action       = ybt_mobile_crash_log
 *   app          = packtracker | ybt (client app id)
 *   app_version  = "2.3 (6)"            (client versionName (versionCode))
 *   android_ver  = "15"                  (Build.VERSION.RELEASE)
 *   device       = "MuMu Player 12"      (Build.MANUFACTURER + MODEL)
 *   is_admin     = 0|1
 *   username     = login name or "(anonymous)"
 *   stack_trace  = full stack, truncated by client to <=16000 chars
 *   custom_data  = optional short context blob (<=2000 chars)
 *
 * Response: { success: true, data: { logged: N } }
 */

if (!defined('ABSPATH')) exit;

/**
 * Ensure the crash-log table exists. Checked once per process via a cached
 * flag (no activation hook needed; the DB bootstrap's table whitelist is
 * deliberately untouched).
 */
function ybt_crash_log_ensure_table() {
    global $wpdb;
    if (get_option('ybt_crash_logs_table_ok') === '1') return;

    $table = $wpdb->prefix . 'ybt_crash_logs';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        reported_at DATETIME NOT NULL,
        app VARCHAR(40) NOT NULL DEFAULT '',
        app_version VARCHAR(40) NOT NULL DEFAULT '',
        android_ver VARCHAR(20) NOT NULL DEFAULT '',
        device VARCHAR(120) NOT NULL DEFAULT '',
        user_id BIGINT UNSIGNED NULL,
        username VARCHAR(120) NOT NULL DEFAULT '',
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        stack_trace TEXT NOT NULL,
        custom_data TEXT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        KEY reported_at (reported_at),
        KEY app (app),
        PRIMARY KEY  (id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('ybt_crash_logs_table_ok', '1');
}

/**
 * Endpoint handler.
 */
function ybt_mobile_crash_log_handler() {
    ybt_clear_output_buffers();
    ybt_crash_log_ensure_table();

    global $wpdb;

    // ---- Rate limit: 10 reports / 5 min / IP (crash loops must not DDoS us) ----
    $ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '0.0.0.0';
    $rate_key = 'ybt_crash_rate_' . wp_hash($ip);
    $hits = (int) (get_transient($rate_key) ?: 0);
    if ($hits >= 10) {
        status_header(429);
        wp_send_json(['success' => false, 'message' => 'Rate limit exceeded']);
        return;
    }
    set_transient($rate_key, $hits + 1, 5 * MINUTE_IN_SECONDS);

    // ---- Auth is optional; associate the report when we can ----
    $user_id   = null;
    $username  = '(anonymous)';
    $is_admin  = 0;
    $token = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.*)$/i', wp_unslash($_SERVER['HTTP_AUTHORIZATION']), $m)) {
        $token = trim($m[1]);
    } elseif (!empty($_REQUEST['token'])) {
        $token = sanitize_text_field(wp_unslash($_REQUEST['token']));
    }
    if ($token !== '' && function_exists('ybt_validate_mobile_token')) {
        $token_data = ybt_validate_mobile_token($token);
        if (is_array($token_data) && !empty($token_data['user_id'])) {
            $user_id  = (int) $token_data['user_id'];
            $u        = get_userdata($user_id);
            $username = $u ? $u->user_login : '(unknown)';
            $is_admin = $u && in_array('administrator', (array) $u->roles, true) ? 1 : 0;
        }
    }

    // ---- Collect + sanitize fields ----
    $app         = substr(sanitize_text_field(wp_unslash($_POST['app'] ?? '')), 0, 40);
    $app_version = substr(sanitize_text_field(wp_unslash($_POST['app_version'] ?? '')), 0, 40);
    $android_ver = substr(sanitize_text_field(wp_unslash($_POST['android_ver'] ?? '')), 0, 20);
    $device      = substr(sanitize_text_field(wp_unslash($_POST['device'] ?? '')), 0, 120);
    $stack       = substr((string) wp_unslash($_POST['stack_trace'] ?? ''), 0, 16000);
    $custom      = substr((string) wp_unslash($_POST['custom_data'] ?? ''), 0, 2000);

    if (trim($stack) === '') {
        wp_send_json(['success' => false, 'message' => 'stack_trace required']);
        return;
    }

    $ok = (bool) $wpdb->insert(
        $wpdb->prefix . 'ybt_crash_logs',
        [
            'reported_at' => current_time('mysql'),
            'app'         => $app,
            'app_version' => $app_version,
            'android_ver' => $android_ver,
            'device'      => $device,
            'user_id'     => $user_id,
            'username'    => substr($username, 0, 120),
            'is_admin'    => $is_admin,
            'stack_trace' => $stack,
            'custom_data' => $custom,
            'ip'          => substr($ip, 0, 45),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s']
    );

    if (!$ok) {
        status_header(500);
        wp_send_json(['success' => false, 'message' => 'Insert failed']);
        return;
    }

    wp_send_json(['success' => true, 'data' => ['logged' => 1]]);
}
add_action('wp_ajax_ybt_mobile_crash_log', 'ybt_mobile_crash_log_handler');
add_action('wp_ajax_nopriv_ybt_mobile_crash_log', 'ybt_mobile_crash_log_handler');
