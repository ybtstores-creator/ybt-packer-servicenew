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

/**
 * Thrown (per operation) instead of die()ing when a replayed handler sends
 * its JSON response, so the sync loop can continue with the next operation.
 */
class YBT_Sync_Halt_Exception extends Exception {}

/**
 * wp_die_handler replacement installed only during replay: converts the
 * "print and die" into a catchable exception carrying the die message.
 */
function ybt_sync_wp_die_handler($message = '', $title = '', $args = array()) {
    if ($message instanceof WP_Error) {
        $message = $message->get_error_message();
    }
    throw new YBT_Sync_Halt_Exception(is_scalar($message) ? (string) $message : '');
}

function ybt_sync_wp_die_handler_filter($handler) {
    return 'ybt_sync_wp_die_handler';
}
// -----------------------------------------------------------------------
// Registered for both logged-in and logged-out (token covers auth)
// -----------------------------------------------------------------------
add_action('wp_ajax_ybt_mobile_sync',        'ybt_mobile_sync_handler');
add_action('wp_ajax_nopriv_ybt_mobile_sync', 'ybt_mobile_sync_handler');

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

        $result = ybt_replay_offline_operation($type, $data, $user_id);

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
 *
 * @param  string $type    Operation type key from the JS queue.
 * @param  array  $data    Fields from the queued operation.
 * @param  int    $user_id Authenticated WP user ID.
 * @return array  { success: bool, message: string }
 */
function ybt_replay_offline_operation(string $type, array $data, int $user_id): array {
    // Map JS operation type → WP AJAX action name
    $action_map = [
        'add_tracking'  => 'ybt_mobile_add_tracking',
        'claim_parcel'  => 'ybt_mobile_claim_parcel',
        'send_chat'     => 'ybt_mobile_send_chat',
        'scan_barcode'  => 'ybt_mobile_scan_barcode',
        'update_status' => 'ybt_mobile_admin_update_status',
        'upload_image'  => 'ybt_upload_image',
    ];

    if (!isset($action_map[$type])) {
        return ['success' => false, 'message' => "Unknown operation type: {$type}"];
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

    // SECURITY: Spoof $_POST for the duration of the handler call, then
    // restore in a finally block so exceptions can't leave $_POST polluted.
    $original_post = $_POST;
    $_POST = array_merge($safe_data, [
        'action'   => $wp_action,
        'user_id'  => $user_id,   // enforce authenticated user
        'security' => wp_create_nonce('ybt_nonce'),
    ]);

    // Trap the handler's "send response and die": YBT_API_Response captures
    // its own payload; raw wp_send_json() calls echo into the buffer below
    // and are stopped at the wp_die() step.
    $start_level  = ob_get_level();
    $decoded      = null;
    $error_message = null;

    if (class_exists('YBT_API_Response')) {
        YBT_API_Response::$captured  = null;
        YBT_API_Response::$capturing = true;
    }
    add_filter('wp_die_ajax_handler', 'ybt_sync_wp_die_handler_filter');
    add_filter('wp_die_handler', 'ybt_sync_wp_die_handler_filter');

    ob_start();
    try {
        // Set current user so handlers that call get_current_user_id() work correctly
        wp_set_current_user($user_id);
        do_action("wp_ajax_{$wp_action}");
    } catch (YBT_Sync_Halt_Exception $e) {
        // Expected: handler responded (or wp_die'd with an error like '-1')
        if ($e->getMessage() !== 'sync-response-captured') {
            $error_message = $e->getMessage();
        }
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }

    // Drain any buffers opened at or above our level (handler may have left some open)
    $output = '';
    while (ob_get_level() >= $start_level + 1) {
        $chunk = ob_get_clean();
        if ($chunk !== false && $chunk !== '') {
            $output = $chunk . $output;
        }
    }

    remove_filter('wp_die_ajax_handler', 'ybt_sync_wp_die_handler_filter');
    remove_filter('wp_die_handler', 'ybt_sync_wp_die_handler_filter');
    $_POST = $original_post;

    if (class_exists('YBT_API_Response')) {
        if (YBT_API_Response::$captured !== null) {
            $decoded = YBT_API_Response::$captured;
        }
        YBT_API_Response::$captured  = null;
        YBT_API_Response::$capturing = false;
    }

    if ($decoded === null) {
        if (trim($output) !== '') {
            $decoded = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Handler may have sent "0" (no handler registered) or non-JSON
                return ['success' => false, 'message' => "Handler returned non-JSON: " . substr($output, 0, 80)];
            }
        } elseif ($error_message !== null && trim((string) $error_message) !== '') {
            return ['success' => false, 'message' => trim((string) $error_message)];
        } else {
            return ['success' => false, 'message' => 'No response from handler'];
        }
    }

    $success = isset($decoded['success']) && $decoded['success'] === true;
    $message = $decoded['data']['message'] ?? ($decoded['data'] ?? ($decoded['message'] ?? ''));
    if (is_array($message)) $message = json_encode($message);

    return ['success' => $success, 'message' => (string) $message];
}

/**
 * Process base64 encoded images and convert to uploaded files
 */
function ybt_process_base64_images($images_base64, $tracking_id, $user_id = 0) {
    if (!is_array($images_base64) || empty($images_base64)) return;

    $tracking_id = intval($tracking_id);
    if ($tracking_id <= 0) return;

    global $wpdb;

    // Check ownership or admin capability before processing uploads
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, images FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
        $tracking_id
    ));

    if (!$item) return;

    $is_admin = current_user_can('manage_options') || current_user_can('ybt_manage_parcels');
    if (!$is_admin && (!empty($item->user_id) && intval($item->user_id) !== intval($user_id))) {
        if (function_exists('ybt_log_security_event')) {
            ybt_log_security_event('unauthorized_image_upload_attempt', [
                'tracking_id' => $tracking_id,
                'user_id'     => $user_id,
                'owner_id'    => $item->user_id,
            ]);
        }
        return;
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
}
