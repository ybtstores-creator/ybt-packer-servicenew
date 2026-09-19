<?php
/**
 * YBT API Helper Methods
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Helper {

    /**
     * Generate JWT token for mobile authentication
     */
    public static function generate_token($user_id, $expiry_days = 14) {
        $issued_at = time();
        $expiration = $issued_at + ($expiry_days * 86400);

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = [
            'user_id' => $user_id,
            'iat' => $issued_at,
            'exp' => $expiration
        ];

        $secret_key = self::get_secret_key();
        
        $base64_header = self::base64url_encode(json_encode($header));
        $base64_payload = self::base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $secret_key, true);
        $base64_signature = self::base64url_encode($signature);

        return $base64_header . '.' . $base64_payload . '.' . $base64_signature;
    }

    /**
     * Validate and decode JWT token
     *
     * @return array|false Payload on success, false on failure
     */
    public static function validate_token() {
        $auth_header = '';

        // 1. Check Authorization header (standard)
        if (isset($_SERVER['Authorization'])) {
            $auth_header = $_SERVER['Authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $auth_header = $value;
                    break;
                }
            }
        }

        // 2. Fallback: Check for 'token' in POST body (useful if headers are stripped)
        // SECURITY: Only accept token via POST body, NEVER via GET (tokens in URLs leak to logs/referers)
        if (empty($auth_header)) {
            if (!empty($_POST['token'])) {
                $auth_header = 'Bearer ' . sanitize_text_field(wp_unslash($_POST['token']));
            }
        }

        if (empty($auth_header)) {
            return false;
        }

        // Remove "Bearer " prefix if present
        $token = preg_replace('/^Bearer\s+/i', '', $auth_header);

        $token_parts = explode('.', $token);
        if (count($token_parts) !== 3) {
            return false;
        }

        list($base64_header, $base64_payload, $base64_signature) = $token_parts;

        $secret_key = self::get_secret_key();
        $signature = self::base64url_decode($base64_signature);
        $expected_signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $secret_key, true);

        if (!hash_equals($signature, $expected_signature)) {
            return false;
        }

        $payload = json_decode(self::base64url_decode($base64_payload), true);
        if (!$payload) {
            return false;
        }

        // Check expiration
        if (isset($payload['exp']) && time() > $payload['exp']) {
            return false;
        }

        return $payload;
    }

    /**
     * Validate a raw token string and return payload
     */
    public static function validate_token_string($token) {
        if (empty($token)) return false;

        $token_parts = explode('.', $token);
        if (count($token_parts) !== 3) {
            return false;
        }

        list($base64_header, $base64_payload, $base64_signature) = $token_parts;

        $secret_key = self::get_secret_key();
        $signature = self::base64url_decode($base64_signature);
        $expected_signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $secret_key, true);

        if (!hash_equals($signature, $expected_signature)) {
            return false;
        }

        $payload = json_decode(self::base64url_decode($base64_payload), true);
        if (!$payload) {
            return false;
        }

        // Check expiration
        if (isset($payload['exp']) && time() > $payload['exp']) {
            return false;
        }

        return $payload;
    }

    /**
     * Base64URL Encode (Standard for JWT)
     */
    public static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL Decode
     */
    public static function base64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get secret key for JWT (Harmonized with main plugin)
     */
    private static function get_secret_key() {
        if (function_exists('ybt_get_jwt_secret_key')) {
            return ybt_get_jwt_secret_key();
        }
        // SECURITY: single source of truth is ybt_get_jwt_secret_key(); never
        // fall back to a core salt or a hardcoded string.
        YBT_API_Response::error('Server configuration error: JWT secret not configured', 'server_error', 500);
        exit;
    }

    /**
     * Sanitize and validate input
     * SECURITY FIXED: Use explicit $_POST/$_GET instead of $_REQUEST
     */
    public static function get_input($key, $default = '', $type = 'text', $method = 'post') {
        // SECURITY: Use explicit request method instead of combined $_REQUEST
        if ($method === 'get') {
            $value = isset($_GET[$key]) ? wp_unslash($_GET[$key]) : $default;
        } else {
            $value = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
        }

        if (function_exists('ybt_security')) {
            return ybt_security()->sanitize_input($value, $type);
        }

        // Fallback sanitization
        switch ($type) {
            case 'int': return intval($value);
            case 'float': return floatval($value);
            case 'email': return sanitize_email($value);
            case 'textarea': return sanitize_textarea_field($value);
            case 'url': return esc_url_raw($value);
            default: return sanitize_text_field($value);
        }
    }

    /**
     * Log message with structure
     */
    public static function log($message, $level = 'info', $context = array()) {
        if (function_exists('ybt_logger')) {
            switch (strtolower($level)) {
                case 'critical': ybt_logger()->critical($message, $context); break;
                case 'error':    ybt_logger()->error($message, $context); break;
                case 'warning':  ybt_logger()->warning($message, $context); break;
                case 'debug':    ybt_logger()->debug($message, $context); break;
                default:         ybt_logger()->info($message, $context); break;
            }
            return;
        }

        error_log(sprintf('[YBT-API][%s] %s %s', strtoupper($level), $message, !empty($context) ? json_encode($context) : ''));
    }
}
