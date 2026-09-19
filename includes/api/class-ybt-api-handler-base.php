<?php
/**
 * YBT API Base Handler
 */

if (!defined('ABSPATH')) exit;

abstract class YBT_API_Handler_Base {

    protected $user_id = 0;
    protected $is_admin = false;
    protected $token_data = null;

    /**
     * Authenticate the request
     */
    protected function authenticate() {
        $this->token_data = YBT_API_Helper::validate_token();

        if (!$this->token_data) {
            YBT_API_Response::unauthorized();
            exit;
        }

        $this->user_id = intval($this->token_data['user_id']);
        $this->is_admin = user_can($this->user_id, 'manage_options');
    }

    /**
     * Check if user is admin
     */
    protected function require_admin() {
        $this->authenticate();
        if (!$this->is_admin) {
            // Generic rejection: never confirm to the caller that an admin
            // area exists (no 'admin' wording in the response).
            YBT_API_Response::forbidden();
            exit;
        }
    }

    /**
     * Standard input getter
     */
    protected function get_param($key, $default = '', $type = 'text', $method = 'post') {
        return YBT_API_Helper::get_input($key, $default, $type, $method);
    }

    /**
     * Log helper
     */
    protected function log($message, $level = 'info', $context = array()) {
        $context['user_id'] = $this->user_id;
        YBT_API_Helper::log($message, $level, $context);
    }
}
