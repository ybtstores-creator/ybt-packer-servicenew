<?php
/**
 * YBT API Response Standardizer
 *
 * Provides a consistent JSON response format for mobile API consumers.
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Response {

    /**
     * Optional capture sink used while replaying offline sync operations.
     *
     * When set to a callable, the full response array is handed to it instead
     * of being sent with wp_send_json() — which would terminate the request
     * via wp_die() and abort the remaining queued operations. The sink is
     * expected to throw (see ybt_replay_offline_operation()) so the replayed
     * handler unwinds immediately.
     *
     * @var callable|null
     */
    public static $response_sink = null;

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

        if (self::$response_sink !== null) {
            call_user_func(self::$response_sink, $response);
            return;
        }

        while (ob_get_level()) ob_end_clean();

        wp_send_json($response);
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

        if (self::$response_sink !== null) {
            call_user_func(self::$response_sink, $response);
            return;
        }

        while (ob_get_level()) ob_end_clean();

        // Set HTTP status code
        status_header($status_code);

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
