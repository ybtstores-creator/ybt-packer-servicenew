<?php
/**
 * YBT API Response Standardizer
 *
 * Provides a consistent JSON response format for mobile API consumers.
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Response {

    /**
     * When enabled (offline-sync replay), responses are captured into
     * self::$captured and a YBT_Sync_Halt_Exception is thrown instead of
     * sending the HTTP response and dying — so the sync loop can continue
     * with the next operation.
     */
    public static $capturing = false;
    public static $captured  = null;

    /**
     * Send a success response
     */
    public static function success($data = null, $message = 'Success') {
        $response = array(
            'success' => true,
            'message' => $message,
            'timestamp' => current_time('mysql'),
        );

        if ($data !== null) {
            $response['data'] = $data;
        }

        self::emit($response);
    }

    /**
     * Send an error response
     */
    public static function error($message = 'An error occurred', $code = 'error', $status_code = 400, $details = null) {
        $response = array(
            'success' => false,
            'message' => $message,
            'code'    => $code,
            'timestamp' => current_time('mysql'),
        );

        if ($details !== null) {
            $response['details'] = $details;
        }

        self::emit($response, $status_code);
    }

    /**
     * Emit the JSON response — or capture it during sync replay.
     */
    private static function emit($response, $status_code = null) {
        if (self::$capturing) {
            self::$captured = $response;
            throw new YBT_Sync_Halt_Exception('sync-response-captured');
        }

        while (ob_get_level()) ob_end_clean();

        // Set HTTP status code
        if ($status_code !== null) {
            status_header($status_code);
        }

        wp_send_json($response);
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 'unauthorized', 401);
    }

    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Forbidden access') {
        self::error($message, 'forbidden', 403);
    }

    /**
     * Send validation error response
     */
    public static function validation_error($errors, $message = 'Validation failed') {
        self::error($message, 'validation_failed', 422, $errors);
    }
}
