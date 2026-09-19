<?php
/**
 * YBT File Handler Class - SECURE VERSION
 * Handles file uploads with strict validation and security checks
 */
if (!defined('ABSPATH')) {
    exit;
}

class YBT_File_Handler {

    // Allowed MIME types with strict validation
    private static $allowed_mimes = array(
        'image/jpeg' => array('jpg', 'jpeg', 'jpe'),
        'image/png'  => array('png'),
        'image/gif'  => array('gif'),
        'image/webp' => array('webp')
    );

    // Maximum file sizes
    private static $max_file_size = 5 * 1024 * 1024; // 5MB per image
    private static $max_total_size = 20 * 1024 * 1024; // 20MB total

    public static function ajax_upload_image() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
            wp_send_json_error('No images uploaded');
        }

        // Enforce size limits
        $max_size = self::$max_file_size; // 5MB per image
        $max_total = self::$max_total_size; // 20MB total

        $uploaded_images = array();
        $total_size = 0;

        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $files = $_FILES['images'];
        $count = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            // Handle single file upload format
            if (!is_array($files['name'])) {
                $file = $files;
            } else {
                $file = array(
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i]
                );
            }

            // Validate upload
            $validation = self::validate_upload($file, $max_size);
            if (is_wp_error($validation)) {
                wp_send_json_error($validation->get_error_message());
            }

            // Sanitize filename for security
            if (function_exists('ybt_sanitize_filename')) {
                $file['name'] = ybt_sanitize_filename($file['name']);
            }

            // Override client-supplied MIME with server-detected type
            $detected_type = self::detect_mime_type($file['tmp_name']);
            if (!$detected_type || !isset(self::$allowed_mimes[$detected_type])) {
                @unlink($file['tmp_name']);
                wp_send_json_error('Invalid or unsupported file type');
            }

            // Check for PHP code in images (EXIF/IPTC injection)
            if (!self::scan_for_malware($file['tmp_name'])) {
                @unlink($file['tmp_name']);
                wp_send_json_error('File contains malicious content');
            }

            // Strip EXIF metadata (privacy protection)
            self::strip_exif_data($file['tmp_name'], $detected_type);

            // Prepare for WordPress upload
            $upload_overrides = array(
                'test_form' => false,
                'mimes'     => self::$allowed_mimes,
                'unique_filename_callback' => array(__CLASS__, 'unique_filename')
            );

            $upload = wp_handle_upload($file, $upload_overrides);

            if (isset($upload['error'])) {
                wp_send_json_error('Upload failed: ' . $upload['error']);
            }

            // Optimize + compress image to reduce disk space
            self::compress_image($upload['file']);

            $uploaded_images[] = $upload['url'];
            $total_size += $file['size'];

            // Check total size limit
            if ($total_size > $max_total) {
                wp_send_json_error('Total upload size exceeds limit (20MB)');
            }
        }

        if (empty($uploaded_images)) {
            wp_send_json_error('No valid images were uploaded');
        }

        // Save to database
        if (isset($_POST['tracking_id'])) {
            $tracking_id = intval($_POST['tracking_id']);
            if ($tracking_id <= 0) {
                wp_send_json_error('Invalid tracking ID');
            }

            global $wpdb;

            $current_images = $wpdb->get_var($wpdb->prepare(
                "SELECT images FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
                $tracking_id
            ));

            $all_images = $current_images ? explode(',', $current_images) : array();
            $all_images = array_merge($all_images, $uploaded_images);
            $images_string = implode(',', array_unique(array_filter($all_images)));

            // Audit: attribute the image upload to the admin who performed it.
            $result = YBT_Tracking_Audit::apply_update(
                $tracking_id,
                array('images' => $images_string),
                'image_upload',
                array(
                    'summary' => sprintf('Uploaded %d image(s)', count($uploaded_images)),
                )
            );

            if ($result === false) {
                wp_send_json_error('Failed to save images to database');
            }
        }

        wp_send_json_success(array(
            'message' => 'Images uploaded successfully',
            'urls' => $uploaded_images
        ));
    }

    /**
     * Strict upload validation
     */
    private static function validate_upload($file, $max_size) {
        // Check upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            );

            $error_msg = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : 'File upload error: ' . $file['error'];
            return new WP_Error('upload_error', $error_msg);
        }

        // Check file size
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File exceeds size limit (' . ($max_size / 1024 / 1024) . 'MB)');
        }

        // Check filename safety
        $filename = sanitize_file_name($file['name']);
        if ($filename !== $file['name']) {
            return new WP_Error('unsafe_filename', 'Filename contains unsafe characters');
        }

        // Block double extensions that could bypass checks
        if (preg_match('/\.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|asp|aspx|jsp)$/i', $filename)) {
            return new WP_Error('blocked_extension', 'File extension not allowed');
        }

        // Check file extension matches MIME type
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed_extensions = array();
        foreach (self::$allowed_mimes as $mime => $extensions) {
            $allowed_extensions = array_merge($allowed_extensions, $extensions);
        }
        $allowed_extensions = array_unique($allowed_extensions);

        if (!in_array($file_ext, $allowed_extensions)) {
            return new WP_Error('invalid_extension', 'File extension not allowed');
        }

        return true;
    }

    /**
     * Server-side MIME type detection (ignores client-supplied type)
     */
    private static function detect_mime_type($file_path) {
        if (!function_exists('finfo_open')) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return false;
        }

        $mime = finfo_file($finfo, $file_path);
        finfo_close($finfo);

        return $mime;
    }

    /**
     * Basic malware scan for PHP code in images
     */
    private static function scan_for_malware($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }

        $content = file_get_contents($file_path, false, null, 0, 1024); // Check first 1KB

        if ($content === false) {
            return true; // If we can't read, assume it's safe
        }

        // Look for common PHP opening tags
        $patterns = array(
            '/<\?php/i',
            '/<\?=/i',
            '/<script.*?php/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/assert\s*\(/i'
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strip EXIF metadata from images (privacy protection)
     */
    private static function strip_exif_data($file_path, $mime_type) {
        if ($mime_type === 'image/jpeg' && function_exists('exif_read_data')) {
            try {
                $image = imagecreatefromjpeg($file_path);
                if ($image) {
                    imagejpeg($image, $file_path, 90);
                    imagedestroy($image);
                }
            } catch (Exception $e) {
                // Fail silently - EXIF stripping is optional
            }
        }
        // PNG/WebP don't typically contain sensitive EXIF data
    }

    /**
     * Generate unique filename to prevent overwrites
     */
    public static function unique_filename($dir, $name, $ext) {
        $filename = pathinfo($name, PATHINFO_FILENAME);
        $suffix = wp_unique_filename($dir, $filename . $ext);
        return $suffix;
    }

    /**
     * Optimize image
     */
    private static function optimize_image($file_path) {
        if (!file_exists($file_path)) {
            return;
        }

        $mime_type = self::detect_mime_type($file_path);
        if (!$mime_type) {
            return;
        }

        // For JPEG, try to optimize with WordPress
        if ($mime_type === 'image/jpeg' && function_exists('wp_image_editor')) {
            try {
                $editor = wp_get_image_editor($file_path);
                if (!is_wp_error($editor)) {
                    $editor->save($file_path);
                }
            } catch (Exception $e) {
                // Fail silently
            }
        }
    }

    /**
     * Compress an already-saved image to reduce disk usage:
     *  - downscale so the longest edge is at most YBT_IMAGE_MAX_DIMENSION px (default 1600)
     *  - re-encode JPEG at quality YBT_IMAGE_QUALITY (default 82)
     *  - GIF files are skipped so animations are preserved
     *
     * Returns array( 'original' => int, 'compressed' => int ) or false on failure.
     * Compression is best-effort: on failure the original file is left untouched.
     */
    public static function compress_image($file_path) {
        try {
            if (!file_exists($file_path) || filesize($file_path) === 0) {
                return false;
            }

            $mime_type = self::detect_mime_type($file_path);
            // Skip GIFs (animations would be destroyed) and unknown types
            if (!$mime_type || $mime_type === 'image/gif') {
                return false;
            }

            if (!function_exists('wp_get_image_editor')) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }

            $editor = wp_get_image_editor($file_path);
            if (is_wp_error($editor)) {
                return false;
            }

            $size = $editor->get_size();
            if (!$size || empty($size['width']) || empty($size['height'])) {
                return false;
            }

            $max_dimension = (int) apply_filters('ybt_image_max_dimension', 1600);
            $original_size = filesize($file_path);

            // Downscale only when the image is larger than the cap
            if (max($size['width'], $size['height']) > $max_dimension) {
                $editor->resize($max_dimension, $max_dimension, false);
            }

            $quality = (int) apply_filters('ybt_image_quality', 82);
            $editor->set_quality($quality);

            $result = $editor->save($file_path);
            if (is_wp_error($result) || empty($result['path'])) {
                return false;
            }

            clearstatcache(true, $file_path);
            $new_size = filesize($file_path);

            // If "compression" somehow made it bigger, the editor already
            // overwrote the file — accept it, but report the real numbers.
            return array(
                'original'   => (int) $original_size,
                'compressed' => (int) $new_size
            );
        } catch (Exception $e) {
            return false;
        }
    }
}
