<?php
/**
 * YBT Mobile Sync - Server-side AJAX handler
 * Receives the offline operation queue from offline-sync.js
 * and replays each operation against the appropriate action handler.
 *
 * POST params:
 *   operations  JSON array of { type, data, local_id, timestamp }
 *   security    WP nonce (ybt_nonce)
 *   Authorization header: Bearer <jwt_token>
 */

if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------
// Registered for both logged-in and logged-out (token covers auth)
// -----------------------------------------------------------------------
add_action('wp_ajax_ybt_mobile_sync',        'ybt_mobile_sync_handler');
add_action('wp_ajax_nopriv_ybt_mobile_sync', 'ybt_mobile_sync_handler');

/**
 * Thrown to unwind a replayed handler out of its normal request-terminating
 * response path (wp_send_json() / wp_die()). Never escapes the replay loop.
 */
class YBT_Sync_Replay_Terminated extends Exception {}

/**
 * wp_die_*_handler filter callback: return the name of the replacement die
 * handler used while replaying offline operations.
 */
function ybt_sync_replay_die_handler_name($handler) {
    return 'ybt_sync_replay_die_handler';
}

/**
 * Replacement wp_die() handler used while replaying offline operations.
 *
 * The stock handlers end the whole PHP request (die()), which would abort the
 * remaining queued operations and prevent the sync receipt from ever being
 * sent. Instead we throw: handlers that reply via wp_send_json*() have already
 * echoed their JSON payload into the output buffer by the time wp_die() runs,
 * so the replay loop can capture it and continue with the next operation.
 */
function ybt_sync_replay_die_handler($message = '', $title = '', $args = array()) {
    throw new YBT_Sync_Replay_Terminated(is_scalar($message) ? (string) $message : '');
}

function ybt_mobile_sync_handler() {
    // --- Auth: accept WP nonce OR JWT Bearer token ---
    $authenticated = false;
    $user_id       = 0;

    // Try nonce first (web-app context)
    if (!empty($_POST['security']) && wp_verify_nonce($_POST['security'], 'ybt_nonce')) {
        $authenticated = true;
        $user_id       = get_current_user_id();
    }

    // Try JWT Bearer token (Android app context)
    if (!$authenticated) {
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (empty($auth_header) && function_exists('getallheaders')) {
            $headers     = getallheaders();
            $auth_header = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
        }
        if (preg_match('/Bearer\s+(.+)/i', $auth_header, $m)) {
            $token = trim($m[1]);
            $payload = false;
            if (function_exists('ybt_validate_mobile_token')) {
                $payload = ybt_validate_mobile_token($token);
            } elseif (class_exists('YBT_API_Helper')) {
                $payload = YBT_API_Helper::validate_token_string($token);
            }
            if ($payload && !empty($payload['user_id'])) {
                $authenticated = true;
                $user_id       = intval($payload['user_id']);
            }
        }
    }

    if (!$authenticated || !$user_id) {
        wp_send_json_error('Authentication required', 401);
        return;
    }

    // --- Parse operations ---
    $raw = isset($_POST['operations']) ? stripslashes($_POST['operations']) : '';
    if (empty($raw)) {
        wp_send_json_error('No operations provided');
        return;
    }

    $operations = json_decode($raw, true);
    if (!is_array($operations)) {
        wp_send_json_error('Invalid operations format');
        return;
    }

    // Rate limit: max 200 operations per sync call
    if (count($operations) > 200) {
        wp_send_json_error('Too many operations (max 200 per request)');
        return;
    }

    $results   = [];
    $processed = 0;
    $failed    = 0;

    foreach ($operations as $op) {
        $type     = sanitize_key($op['type'] ?? '');
        $data     = is_array($op['data']) ? $op['data'] : [];
        $local_id = sanitize_text_field($op['local_id'] ?? '');

        // Timeline events should carry when the scan happened on-device, not
        // when the queued op was replayed after reconnect.
        if (class_exists('YBT_Tracking_Events')) {
            $client_ts = sanitize_text_field($op['timestamp'] ?? '');
            YBT_Tracking_Events::$forced_occurred = $client_ts !== ''
                ? preg_replace('/\.\d+/', '', $client_ts) // strtotime rejects ISO fractional seconds
                : null;
        }

        try {
            $result = ybt_replay_offline_operation($type, $data, $user_id);
        } finally {
            if (class_exists('YBT_Tracking_Events')) {
                YBT_Tracking_Events::$forced_occurred = null;
            }
        }

        if ($result['success']) {
            $processed++;
        } else {
            $failed++;
        }

        $results[] = [
            'local_id' => $local_id,
            'type'     => $type,
            'success'  => $result['success'],
            'message'  => $result['message'] ?? '',
        ];
    }

    // A replayed handler may have set an error status header mid-loop;
    // the sync request itself completed, so answer with 200 + per-op results.
    if (!headers_sent()) {
        status_header(200);
    }

    wp_send_json_success([
        'processed' => $processed,
        'failed'    => $failed,
        'total'     => count($operations),
        'results'   => $results,
    ]);
}

/**
 * Replay a single offline operation by spoofing $_POST and calling
 * the appropriate registered AJAX action handler via do_action().
 *
 * Supported operation types map to WP AJAX actions:
 *   add_tracking        → ybt_mobile_add_tracking
 *   claim_parcel        → ybt_mobile_claim_parcel
 *   send_chat           → ybt_mobile_send_chat
 *   scan_barcode        → ybt_mobile_scan_barcode
 *   update_status       → ybt_mobile_admin_update_status
 *   upload_image        → base64 images stored directly (no action dispatch)
 *
 * @param  string $type    Operation type key from the JS queue.
 * @param  array  $data    Fields from the queued operation.
 * @param  int    $user_id Authenticated WP user ID.
 * @return array  { success: bool, message: string }
 */
function ybt_replay_offline_operation(string $type, array $data, int $user_id): array {
    // upload_image: the offline queue stores images as base64 (offline-sync.js),
    // so the base64 handler below IS the operation. The multipart
    // ybt_upload_image endpoint cannot be replayed from a sync batch — there is
    // no $_FILES in a replayed request — and dispatching it would only ever hit
    // its check_ajax_referer() with an empty upload. Handled directly here.
    if ($type === 'upload_image') {
        if (empty($data['images_base64']) || !is_array($data['images_base64'])) {
            return ['success' => false, 'message' => 'No image data in operation (images_base64 required)'];
        }
        $previous_user = get_current_user_id();
        wp_set_current_user($user_id); // ybt_process_base64_images() checks current_user_can()
        try {
            $uploaded = ybt_process_base64_images($data['images_base64'], $data['tracking_id'] ?? 0, $user_id);
        } finally {
            wp_set_current_user($previous_user);
        }
        if ($uploaded === false) {
            return ['success' => false, 'message' => 'Upload rejected: tracking item missing or not the parcel owner'];
        }
        $count = count($uploaded);
        return $count > 0
            ? ['success' => true,  'message' => sprintf('%d image(s) uploaded', $count)]
            : ['success' => false, 'message' => 'No valid images were uploaded'];
    }

    // Map JS operation type → WP AJAX action name
    // (upload_image is handled above and never dispatched to an action.)
    $action_map = [
        'add_tracking'  => 'ybt_mobile_add_tracking',
        'claim_parcel'  => 'ybt_mobile_claim_parcel',
        'send_chat'     => 'ybt_mobile_send_chat',
        'scan_barcode'  => 'ybt_mobile_scan_barcode',
        'update_status' => 'ybt_mobile_admin_update_status',
    ];

    if (!isset($action_map[$type])) {
        return ['success' => false, 'message' => "Unknown operation type: {$type}"];
    }

    // SECURITY: Privilege check — prevent unauthorized replay of admin-only operations
    $admin_actions = ['scan_barcode', 'update_status'];
    if (in_array($type, $admin_actions, true) && !user_can($user_id, 'manage_options')) {
        return ['success' => false, 'message' => "Unauthorized: operation requires administrator privileges"];
    }

    $wp_action = $action_map[$type];

    // Handle base64 images if present
    if (!empty($data['images_base64']) && is_array($data['images_base64'])) {
        ybt_process_base64_images($data['images_base64'], $data['tracking_id'] ?? 0, $user_id);
        unset($data['images_base64']);
    }

    // Sanitize each field in data before injecting into $_POST
    $safe_data = [];
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            // Recursively sanitize arrays
            $safe_data[sanitize_key($k)] = array_map('sanitize_text_field', $v);
        } elseif (is_string($v)) {
            $safe_data[sanitize_key($k)] = sanitize_text_field($v);
        } elseif (is_float($v)) {
            $safe_data[sanitize_key($k)] = floatval($v);
        } else {
            $safe_data[sanitize_key($k)] = intval($v);
        }
    }

    // Set the current user first: handlers that call get_current_user_id()
    // must see the authenticated user, and the spoofed nonce below is minted
    // for this user (wp_create_nonce() is user-bound, so creating it earlier
    // would fail verification inside the handler).
    $previous_user = get_current_user_id();
    wp_set_current_user($user_id);

    // SECURITY: Spoof $_POST (and $_REQUEST, which check_ajax_referer()
    // reads) for the duration of the handler call, then restore both in a
    // finally block so nothing can leave the request state polluted.
    $original_post    = $_POST;
    $original_request = $_REQUEST;
    $_POST = array_merge($safe_data, [
        'action'   => $wp_action,
        'user_id'  => $user_id,   // enforce authenticated user
        'security' => wp_create_nonce('ybt_nonce'),
    ]);
    $_REQUEST = array_merge($_REQUEST, $_POST);

    // Replayed handlers are written to *terminate* their request with
    // wp_send_json() / wp_die(), which would kill the whole sync batch after
    // the first operation. While replaying we intercept both termination
    // styles:
    //  - class-based handlers reply through YBT_API_Response::$response_sink,
    //    which captures the structured response directly;
    //  - handlers that call wp_send_json*() directly are caught by swapping
    //    the wp_die() handler for one that throws instead of exiting PHP —
    //    their JSON payload is already echoed into the output buffer below.
    $previous_sink = class_exists('YBT_API_Response') ? YBT_API_Response::$response_sink : null;
    $sink_payload  = null;
    $die_message   = '';
    $failure       = null;

    if (class_exists('YBT_API_Response')) {
        YBT_API_Response::$response_sink = function ($response) use (&$sink_payload) {
            $sink_payload = $response;
            throw new YBT_Sync_Replay_Terminated('');
        };
    }
    add_filter('wp_die_ajax_handler', 'ybt_sync_replay_die_handler_name');
    add_filter('wp_die_json_handler', 'ybt_sync_replay_die_handler_name');
    add_filter('wp_die_handler',      'ybt_sync_replay_die_handler_name');

    // Capture output instead of sending HTTP response
    $base_level = ob_get_level();
    ob_start();
    try {
        do_action("wp_ajax_{$wp_action}");
    } catch (YBT_Sync_Replay_Terminated $e) {
        // Expected path: the handler finished by sending its response.
        $die_message = $e->getMessage();
    } catch (Throwable $e) {
        $failure = $e->getMessage();
    } finally {
        // Guarantee restoration even if something unexpected happens
        if (class_exists('YBT_API_Response')) {
            YBT_API_Response::$response_sink = $previous_sink;
        }
        remove_filter('wp_die_ajax_handler', 'ybt_sync_replay_die_handler_name');
        remove_filter('wp_die_json_handler', 'ybt_sync_replay_die_handler_name');
        remove_filter('wp_die_handler',      'ybt_sync_replay_die_handler_name');
        $_POST    = $original_post;
        $_REQUEST = $original_request;
        wp_set_current_user($previous_user);
    }

    // Collect output left in any buffers the handler opened (or leaked).
    $output = '';
    while (ob_get_level() > $base_level) {
        $output = ob_get_clean() . $output;
    }

    if ($failure !== null) {
        return ['success' => false, 'message' => $failure];
    }

    // Structured response captured from YBT_API_Response — use it directly.
    if (is_array($sink_payload)) {
        $success = isset($sink_payload['success']) && $sink_payload['success'] === true;
        $message = $sink_payload['message'] ?? ($sink_payload['data'] ?? '');
        if (is_array($message)) $message = json_encode($message);
        return ['success' => $success, 'message' => (string) $message];
    }

    // wp_send_json()-style handler: parse the JSON it echoed before dying.
    if (empty($output)) {
        if ($die_message === '-1') {
            // check_ajax_referer() failure path (wp_die(-1, 403))
            return ['success' => false, 'message' => 'Security check failed (nonce verification)'];
        }
        return ['success' => false, 'message' => $die_message !== '' ? $die_message : 'No response from handler'];
    }

    $decoded = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Handler may have sent "0" (no handler registered) or non-JSON
        return ['success' => false, 'message' => "Handler returned non-JSON: " . substr($output, 0, 80)];
    }

    $success = isset($decoded['success']) && $decoded['success'] === true;
    $message = $decoded['data']['message'] ?? ($decoded['data'] ?? ($decoded['message'] ?? ''));
    if (is_array($message)) $message = json_encode($message);

    return ['success' => $success, 'message' => (string) $message];
}

/**
 * Process base64 encoded images and convert to uploaded files
 *
 * @return array|false List of uploaded image URLs (empty when nothing valid),
 *                     or false when the upload was rejected (missing tracking
 *                     item or not the parcel owner).
 */
function ybt_process_base64_images($images_base64, $tracking_id, $user_id = 0) {
    if (!is_array($images_base64) || empty($images_base64)) return [];

    $tracking_id = intval($tracking_id);
    if ($tracking_id <= 0) return false;

    global $wpdb;

    // Check ownership or admin capability before processing uploads
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, images FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
        $tracking_id
    ));

    if (!$item) return false;

    $is_admin = current_user_can('manage_options') || current_user_can('ybt_manage_parcels');
    if (!$is_admin && (!empty($item->user_id) && intval($item->user_id) !== intval($user_id))) {
        if (function_exists('ybt_log_security_event')) {
            ybt_log_security_event('unauthorized_image_upload_attempt', [
                'tracking_id' => $tracking_id,
                'user_id'     => $user_id,
                'owner_id'    => $item->user_id,
            ]);
        }
        return false;
    }

    $uploaded_urls = [];
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    foreach ($images_base64 as $base64_data) {
        if (empty($base64_data) || !is_string($base64_data)) continue;

        // Cap raw base64 string length (~7MB string ≈ ~5MB binary)
        if (strlen($base64_data) > 7 * 1024 * 1024) continue;

        // Extract base64 data
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64_data, $matches)) {
            $data = base64_decode($matches[2], true);
        } else {
            $data = base64_decode($base64_data, true);
        }

        if (!$data || strlen($data) > 5 * 1024 * 1024) continue;

        // Validate image binary using getimagesizefromstring
        if (!function_exists('getimagesizefromstring')) {
            continue;
        }

        $image_info = @getimagesizefromstring($data);
        if (!$image_info || !isset($image_info['mime'])) {
            continue;
        }

        $mime = strtolower($image_info['mime']);
        if (!isset($allowed_mimes[$mime])) {
            continue;
        }

        $ext = $allowed_mimes[$mime];

        // Create temp file securely
        $upload_dir = wp_upload_dir();
        $filename = 'ybt_' . time() . '_' . wp_generate_password(12, false) . '.' . $ext;
        $filepath = $upload_dir['path'] . '/' . $filename;

        if (file_put_contents($filepath, $data)) {
            // Compress to reduce disk space (best-effort, never blocks the sync)
            if (class_exists('YBT_File_Handler')) {
                YBT_File_Handler::compress_image($filepath);
            }
            $uploaded_urls[] = $upload_dir['url'] . '/' . $filename;
        }
    }

    // Update tracking item with new images
    if (!empty($uploaded_urls)) {
        $existing = $item->images;
        $all_images = $existing ? explode(',', $existing) : [];
        $all_images = array_merge($all_images, $uploaded_urls);
        $all_images = array_filter(array_map('trim', $all_images));

        $wpdb->update(
            $wpdb->prefix . 'ybt_tracking',
            ['images' => implode(',', $all_images)],
            ['id' => $tracking_id]
        );
    }

    return $uploaded_urls;
}
