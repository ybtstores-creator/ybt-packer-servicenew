<?php
/**
 * YBT File Upload AJAX Handlers
 * Enhanced with security validation functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate file upload for security
 * 
 * @param array $file The file array from $_FILES
 * @param array $allowed_types Allowed file extensions
 * @param int $max_size Maximum file size in bytes (default 5MB)
 * @return true|WP_Error True on success, WP_Error on failure
 */
function ybt_validate_file_upload($file, $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp'), $max_size = 5242880) {
    // Check if file was uploaded
    if (empty($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('no_file', 'No file was uploaded');
    }
    
    // Check file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($ext) || !in_array($ext, $allowed_types, true)) {
        return new WP_Error('invalid_ext', 'Invalid file extension. Allowed: ' . implode(', ', $allowed_types));
    }
    
    // Check MIME type using WordPress function (client-provided $file['type'] must NOT be
    // passed here — the third arg overrides the allowed $mimes map and breaks detection).
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    if (!$check['ext'] || !$check['type']) {
        return new WP_Error('invalid_mime', 'Invalid file type detected');
    }
    
    // Verify MIME type matches extension
    $allowed_mime_types = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'apk' => 'application/vnd.android.package-archive',
    );
    
    $expected_mime = isset($allowed_mime_types[$ext]) ? $allowed_mime_types[$ext] : null;
    if ($expected_mime && $check['type'] !== $expected_mime) {
        return new WP_Error('mime_mismatch', 'File type does not match extension');
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $max_mb = round($max_size / 1024 / 1024, 1);
        return new WP_Error('file_too_large', 'File must be under ' . $max_mb . 'MB');
    }
    
    // Check for PHP in image files (security)
    if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
        $image_info = @getimagesize($file['tmp_name']);
        if (!$image_info) {
            return new WP_Error('invalid_image', 'File is not a valid image');
        }
    }
    
    return true;
}

/**
 * Sanitize filename for security
 * 
 * @param string $filename Original filename
 * @return string Sanitized filename
 */
function ybt_sanitize_filename($filename) {
    // Remove any directory traversal attempts
    $filename = basename($filename);
    
    // Remove special characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Remove multiple dots
    $filename = preg_replace('/\.+/', '.', $filename);
    
    // Ensure filename doesn't start with dot
    $filename = ltrim($filename, '.');
    
    return sanitize_file_name($filename);
}

// Ensure File Handler class is available
if (class_exists('YBT_File_Handler')) {
    add_action('wp_ajax_ybt_upload_image', array('YBT_File_Handler', 'ajax_upload_image'));
    // SECURITY: Removed wp_ajax_nopriv registration — file uploads require authentication
} else {
    function ybt_fallback_upload_image() {
        wp_send_json_error('File handler service unavailable.');
    }
    add_action('wp_ajax_ybt_upload_image', 'ybt_fallback_upload_image');
}

/**
 * Handle deleting an image
 */
function ybt_delete_image_handler() {
    check_ajax_referer('ybt_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $image_url = sanitize_text_field($_POST['image_url'] ?? '');
    $tracking_id = intval($_POST['tracking_id'] ?? 0);

    if (empty($image_url) || !$tracking_id) {
        wp_send_json_error('Invalid parameters');
    }

    global $wpdb;
    $item = $wpdb->get_row($wpdb->prepare("SELECT images FROM {$wpdb->prefix}ybt_tracking WHERE id = %d", $tracking_id));

    if ($item && !empty($item->images)) {
        $images = explode(',', $item->images);
        $new_images = array_filter($images, function($url) use ($image_url) {
            return trim($url) !== trim($image_url);
        });

        $wpdb->update(
            $wpdb->prefix . 'ybt_tracking',
            array('images' => implode(',', $new_images)),
            array('id' => $tracking_id)
        );

        // Physically delete file if it's on our server
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $file_path = $base_dir . '/' . basename(wp_parse_url($image_url, PHP_URL_PATH));

        // Security check: Ensure the file is within the uploads directory
        if (strpos(realpath($file_path), realpath($base_dir)) === 0 && file_exists($file_path)) {
            unlink($file_path);
        }

        wp_send_json_success('Image deleted');
    } else {
        wp_send_json_error('Image not found');
    }
}
add_action('wp_ajax_ybt_delete_image', 'ybt_delete_image_handler');
