<?php
/**
 * YBT App Update: AJAX + REST API handlers
 */
if (!defined('ABSPATH')) exit;

// ==============================================================================
// AJAX: Save app update (admin)
// ==============================================================================

add_action('wp_ajax_ybt_save_app_update', 'ybt_save_app_update_handler');
function ybt_save_app_update_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $version        = sanitize_text_field($_POST['app_version'] ?? '');
    $download_url   = esc_url_raw($_POST['download_url'] ?? '');
    $update_message = sanitize_textarea_field($_POST['update_message'] ?? '');
    $update_enabled = isset($_POST['update_enabled']) ? intval($_POST['update_enabled']) : 0;

    if (empty($version)) {
        wp_send_json_error('App version is required.');
    }

    // Handle optional APK file upload with strict security validation
    if (!empty($_FILES['apk_file']['name'])) {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        // SECURITY: Validate APK file strictly
        $apk_file = $_FILES['apk_file'];
        
        // Check file extension
        $apk_ext = strtolower(pathinfo($apk_file['name'], PATHINFO_EXTENSION));
        if ($apk_ext !== 'apk') {
            wp_send_json_error('Only APK files are allowed');
        }
        
        // Verify MIME type.
        //
        // wp_check_filetype_and_ext()'s 3rd arg is an *allow-list array* of
        // ext => mime (null = get_allowed_mime_types()), NOT the client's claimed
        // MIME string. Passing $apk_file['type'] fed a string into
        // wp_check_filetype(), which iterated a non-array and rejected every APK.
        //
        // A valid APK is a ZIP and the apk MIME type is not in WordPress' default
        // allowed list, so whitelist it for the duration of the check + upload —
        // otherwise the "mime must be allowed" step inside
        // wp_check_filetype_and_ext() (and again in wp_handle_upload) rejects it.
        $allowed_apk_mimes = array(
            'apk' => 'application/vnd.android.package-archive'
        );
        $apk_mime_whitelist = function ($mimes) use ($allowed_apk_mimes) {
            return array_merge($mimes, $allowed_apk_mimes);
        };
        add_filter('upload_mimes', $apk_mime_whitelist);

        $check = wp_check_filetype_and_ext($apk_file['tmp_name'], $apk_file['name'], $allowed_apk_mimes);
        if (!$check['ext'] || $check['type'] !== 'application/vnd.android.package-archive') {
            remove_filter('upload_mimes', $apk_mime_whitelist);
            wp_send_json_error('Invalid APK file type. File must be a valid Android package.');
        }
        
        // Check file size (max 100MB for APK)
        $max_apk_size = 100 * 1024 * 1024; // 100MB
        if ($apk_file['size'] > $max_apk_size) {
            remove_filter('upload_mimes', $apk_mime_whitelist);
            wp_send_json_error('APK file must be under 100MB');
        }
        
        // Sanitize filename
        $apk_file['name'] = ybt_sanitize_filename($apk_file['name']);
        if (!strpos($apk_file['name'], '.apk')) {
            $apk_file['name'] .= '.apk';
        }
        
        add_filter('upload_dir', 'ybt_apk_upload_dir');
        $uploaded = wp_handle_upload($apk_file, array(
            'test_form' => false,
            'mimes'     => $allowed_apk_mimes,
        ));
        remove_filter('upload_dir', 'ybt_apk_upload_dir');
        remove_filter('upload_mimes', $apk_mime_whitelist);

        if (isset($uploaded['error'])) {
            wp_send_json_error('APK upload failed: ' . $uploaded['error']);
        }
        $download_url = $uploaded['url'];
    }

    if (empty($download_url)) {
        wp_send_json_error('A download URL is required. Upload an APK or paste a URL.');
    }

    update_option('ybt_app_latest_version', $version);
    update_option('ybt_app_download_url',   $download_url);
    update_option('ybt_app_update_message', $update_message);
    update_option('ybt_app_update_enabled', $update_enabled);

    if (function_exists('ybt_log_activity')) {
        ybt_log_activity(get_current_user_id(), 'admin_app_update', "Pushed app update v{$version}");
    }

    wp_send_json_success([
        'message'      => "App update v{$version} saved!" . ($update_enabled ? ' Banner VISIBLE to users.' : ' Banner is HIDDEN.'),
        'download_url' => $download_url,
    ]);
}

/**
 * Redirect APK file uploads to the plugin's /downloads/ folder.
 */
function ybt_apk_upload_dir($dirs) {
    $dirs['subdir']  = '';
    $dirs['path']    = YBT_PLUGIN_PATH . 'downloads';
    $dirs['url']     = YBT_PLUGIN_URL  . 'downloads';
    $dirs['basedir'] = YBT_PLUGIN_PATH . 'downloads';
    $dirs['baseurl'] = YBT_PLUGIN_URL  . 'downloads';
    return $dirs;
}

// ==============================================================================
// REST API: GET /wp-json/ybt/v1/app-version (public — used by Android app)
// ==============================================================================

add_action('rest_api_init', function () {
    register_rest_route('ybt/v1', '/app-version', [
        'methods'             => 'GET',
        'callback'            => 'ybt_rest_app_version',
        'permission_callback' => '__return_true', // Public endpoint — no auth needed
    ]);
});

function ybt_rest_app_version(WP_REST_Request $request) {
    $latest_version  = get_option('ybt_app_latest_version', '');
    $download_url    = get_option('ybt_app_download_url', '');
    $message         = get_option('ybt_app_update_message', '');
    $enabled         = (bool) get_option('ybt_app_update_enabled', 0);

    // Android app passes its current version as ?current_version=1.0
    $current_version  = sanitize_text_field($request->get_param('current_version') ?? '');
    $update_available = $enabled && $latest_version && version_compare($latest_version, $current_version, '>');

    return rest_ensure_response([
        'latest_version'   => $latest_version,
        'download_url'     => $download_url,
        'message'          => $message,
        'update_available' => $update_available,
        'update_enabled'   => $enabled,
    ]);
}
