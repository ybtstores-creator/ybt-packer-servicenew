<?php
/**
 * YBT Validation Class
 * Centralized input validation and sanitization
 *
 * Version: 1.1
 * Description: Provides comprehensive validation methods for various input types
 *              including tracking numbers, emails, dates, and more.
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_Validator {

    private static $instance = null;
    private $errors = array();

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Validate tracking number
     *
     * @param mixed $value The tracking number to validate
     * @param string $field_name The field name for error reporting
     * @return string|false Validated tracking number or false if invalid
     */
    public function tracking_number($value, $field_name = 'tracking_number') {
        $value = sanitize_text_field($value);

        if (empty($value)) {
            $this->add_error($field_name, 'Tracking number is required');
            return false;
        }

        if (strlen($value) > 50) {
            $this->add_error($field_name, 'Tracking number must be less than 50 characters');
            return false;
        }

        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $value)) {
            $this->add_error($field_name, 'Tracking number contains invalid characters. Only letters, numbers, hyphens, and underscores are allowed.');
            return false;
        }

        return $value;
    }

    /**
     * Validate email address
     *
     * @param mixed $value The email to validate
     * @param string $field_name The field name for error reporting
     * @return string|false Validated email or false if invalid
     */
    public function email($value, $field_name = 'email') {
        $value = sanitize_email($value);

        if (empty($value)) {
            $this->add_error($field_name, 'Email is required');
            return false;
        }

        if (!is_email($value)) {
            $this->add_error($field_name, 'Invalid email format');
            return false;
        }

        return $value;
    }

    /**
     * Validate username
     *
     * @param mixed $value The username to validate
     * @param string $field_name The field name for error reporting
     * @return string|false Validated username or false if invalid
     */
    public function username($value, $field_name = 'username') {
        $value = sanitize_user($value);

        if (empty($value)) {
            $this->add_error($field_name, 'Username is required');
            return false;
        }

        if (strlen($value) < 3) {
            $this->add_error($field_name, 'Username must be at least 3 characters');
            return false;
        }

        if (strlen($value) > 60) {
            $this->add_error($field_name, 'Username must be less than 60 characters');
            return false;
        }

        return $value;
    }

    /**
     * Validate status
     *
     * @param mixed $value The status to validate
     * @param string $field_name The field name for error reporting
     * @return string|false Validated status or false if invalid
     */
    public function status($value, $field_name = 'status') {
        $allowed_statuses = array(
            'not_received',
            'received',
            'packing_in_progress',
            'packed',
            'completed'
        );

        $value = sanitize_text_field($value);

        if (empty($value)) {
            $this->add_error($field_name, 'Status is required');
            return false;
        }

        if (!in_array($value, $allowed_statuses)) {
            $this->add_error($field_name, 'Invalid status value. Allowed values: ' . implode(', ', $allowed_statuses));
            return false;
        }

        return $value;
    }

    /**
     * Validate integer
     *
     * @param mixed $value The integer to validate
     * @param string $field_name The field name for error reporting
     * @param int|null $min Minimum allowed value
     * @param int|null $max Maximum allowed value
     * @return int|false Validated integer or false if invalid
     */
    public function integer($value, $field_name = 'value', $min = null, $max = null) {
        if (!is_numeric($value)) {
            $this->add_error($field_name, 'Value must be a number');
            return false;
        }

        $value = intval($value);

        if ($min !== null && $value < $min) {
            $this->add_error($field_name, "Value must be at least {$min}");
            return false;
        }

        if ($max !== null && $value > $max) {
            $this->add_error($field_name, "Value must be at most {$max}");
            return false;
        }

        return $value;
    }

    /**
     * Validate date
     *
     * @param mixed $value The date to validate
     * @param string $field_name The field name for error reporting
     * @param string $format Expected date format (default: Y-m-d)
     * @return string|false Validated date or false if invalid
     */
    public function date($value, $field_name = 'date', $format = 'Y-m-d') {
        $value = sanitize_text_field($value);

        if (empty($value)) {
            $this->add_error($field_name, 'Date is required');
            return false;
        }

        $date = DateTime::createFromFormat($format, $value);

        if (!$date || $date->format($format) !== $value) {
            $this->add_error($field_name, 'Invalid date format. Expected format: ' . $format);
            return false;
        }

        return $value;
    }

    /**
     * Validate phone number
     *
     * @param mixed $value The phone number to validate
     * @param string $field_name The field name for error reporting
     * @return string|false Validated phone number or false if invalid
     */
    public function phone($value, $field_name = 'phone') {
        $value = sanitize_text_field($value);

        if (empty($value)) {
            $this->add_error($field_name, 'Phone number is required');
            return false;
        }

        // Remove common formatting characters
        $cleaned = preg_replace('/[\s\-\(\)\.\+]/', '', $value);

        if (!preg_match('/^\+?[0-9]{8,15}$/', $cleaned)) {
            $this->add_error($field_name, 'Invalid phone number format. Must be 8-15 digits, optionally starting with +');
            return false;
        }

        return $value;
    }

    /**
     * Validate URL
     *
     * @param mixed $value The URL to validate
     * @param string $field_name The field name for error reporting
     * @return string|false Validated URL or false if invalid
     */
    public function url($value, $field_name = 'url') {
        $value = esc_url_raw($value);

        if (empty($value)) {
            $this->add_error($field_name, 'URL is required');
            return false;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->add_error($field_name, 'Invalid URL format');
            return false;
        }

        return $value;
    }

    /**
     * Validate array values
     *
     * @param mixed $value The array to validate
     * @param string $field_name The field name for error reporting
     * @param array $allowed_values Array of allowed values
     * @return array|false Validated array or false if invalid
     */
    public function array_values($value, $field_name = 'array', $allowed_values = array()) {
        if (!is_array($value)) {
            $this->add_error($field_name, 'Value must be an array');
            return false;
        }

        if (!empty($allowed_values)) {
            foreach ($value as $item) {
                if (!in_array($item, $allowed_values)) {
                    $this->add_error($field_name, 'Array contains invalid values. Allowed values: ' . implode(', ', $allowed_values));
                    return false;
                }
            }
        }

        return array_map('sanitize_text_field', $value);
    }

    /**
     * Sanitize HTML content
     *
     * @param mixed $value The HTML content to sanitize
     * @param array|null $allowed_tags Allowed HTML tags
     * @return string Sanitized HTML content
     */
    public function html($value, $allowed_tags = null) {
        if (empty($value)) {
            return '';
        }

        if ($allowed_tags === null) {
            $allowed_tags = wp_kses_allowed_html('post');
        }

        return wp_kses($value, $allowed_tags);
    }

    /**
     * Add validation error
     *
     * @param string $field Field name
     * @param string $message Error message
     */
    private function add_error($field, $message) {
        $this->errors[$field] = $message;
    }

    /**
     * Get validation errors
     *
     * @return array Array of validation errors
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Check if validation has errors
     *
     * @return bool True if there are errors, false otherwise
     */
    public function has_errors() {
        return !empty($this->errors);
    }

    /**
     * Clear all validation errors
     */
    public function clear_errors() {
        $this->errors = array();
    }

    /**
     * Get first error message
     *
     * @return string|null First error message or null if no errors
     */
    public function first_error() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * Get formatted error messages for display
     *
     * @return array Formatted error messages with field names
     */
    public function get_formatted_errors() {
        $formatted_errors = array();
        foreach ($this->errors as $field => $message) {
            $formatted_errors[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . $message;
        }
        return $formatted_errors;
    }
}

// Helper function
function ybt_validator() {
    return YBT_Validator::getInstance();
}

// Initialize validator (optional)
// This would typically be called from your main plugin file
// add_action('plugins_loaded', function() {
//     ybt_validator(); // Initialize the validator
// });
