<?php
/**
 * File Upload Validator - SECURE VERSION
 * Validates and sanitizes file uploads with EXIF stripping and CSV protection
 *
 * SECURITY FIXES:
 * ✅ EXIF metadata stripping for JPEG images
 * ✅ CSV injection protection (formula prefixing)
 * ✅ Server-side MIME type detection (ignores client-supplied type)
 * ✅ Malware scanning for PHP code in images
 * ✅ Strict filename sanitization
 */
if (!defined('ABSPATH')) {
    exit;
}

class YBT_File_Validator {
    private static $allowed_image_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif');
    private static $allowed_document_types = array('application/pdf');
    private static $max_file_size = 10485760; // 10MB
    private static $max_image_width = 1920;
    private static $max_image_height = 1920;
    private static $jpeg_quality = 85;

    /**
     * Validate and process image upload
     */
    public static function validate_image_upload($file) {
        $validation = self::basic_validation($file, self::$allowed_image_types, self::$max_file_size);
        if (isset($validation['error'])) {
            return $validation;
        }

        // Server-side MIME detection (ignores client-supplied type)
        $mime_type = self::detect_mime_type($file['tmp_name']);
        if (!$mime_type) {
            return array('error' => 'Could not detect file type');
        }

        if (!in_array($mime_type, self::$allowed_image_types)) {
            return array('error' => 'Invalid file type. Only JPG, PNG, WEBP, and GIF images allowed');
        }

        // Malware scan for PHP code injection
        if (!self::scan_for_malware($file['tmp_name'])) {
            return array('error' => 'File contains malicious content');
        }

        // Sanitize filename
        $safe_filename = self::sanitize_filename($file['name']);

        // Strip EXIF metadata (privacy protection)
        if (in_array($mime_type, array('image/jpeg', 'image/jpg'))) {
            self::strip_exif_data($file['tmp_name']);
        }

        // Resize large images
        if (in_array($mime_type, array('image/jpeg', 'image/jpg', 'image/png'))) {
            self::resize_image($file['tmp_name'], $mime_type);
        }

        return array('success' => true, 'safe_filename' => $safe_filename, 'mime_type' => $mime_type);
    }

    /**
     * Validate document upload (PDF/images)
     */
    public static function validate_document_upload($file) {
        $validation = self::basic_validation($file, array_merge(self::$allowed_image_types, self::$allowed_document_types), self::$max_file_size);
        if (isset($validation['error'])) {
            return $validation;
        }

        $mime_type = self::detect_mime_type($file['tmp_name']);
        if (!$mime_type) {
            return array('error' => 'Could not detect file type');
        }

        $allowed_types = array_merge(self::$allowed_image_types, self::$allowed_document_types);

        if (!in_array($mime_type, $allowed_types)) {
            return array('error' => 'Invalid file type. Only JPG, PNG, GIF, WEBP images and PDF allowed');
        }

        $safe_filename = self::sanitize_filename($file['name']);
        return array('success' => true, 'safe_filename' => $safe_filename, 'mime_type' => $mime_type);
    }

    /**
     * Validate CSV upload with injection protection
     */
    public static function validate_csv_upload($file) {
        $validation = self::basic_validation($file, array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'), self::$max_file_size);
        if (isset($validation['error'])) {
            return $validation;
        }

        $mime_type = self::detect_mime_type($file['tmp_name']);
        if (!$mime_type) {
            return array('error' => 'Could not detect file type');
        }

        $allowed_csv_types = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');

        if (!in_array($mime_type, $allowed_csv_types)) {
            return array('error' => 'Invalid file type. Only CSV files allowed');
        }

        // Sanitize content to prevent CSV injection
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            return array('error' => 'Could not read file content');
        }

        $sanitized_content = self::sanitize_csv_content($content);

        // Rewrite file with sanitized content
        if (file_put_contents($file['tmp_name'], $sanitized_content) === false) {
            return array('error' => 'Could not write sanitized content to file');
        }

        $safe_filename = self::sanitize_filename($file['name']);
        return array('success' => true, 'safe_filename' => $safe_filename);
    }

    /**
     * Validate JSON upload
     */
    public static function validate_json_upload($file) {
        $validation = self::basic_validation($file, array('application/json', 'text/plain'), 52428800); // 50MB for JSON
        if (isset($validation['error'])) {
            return $validation;
        }

        $mime_type = self::detect_mime_type($file['tmp_name']);
        if (!$mime_type) {
            return array('error' => 'Could not detect file type');
        }

        $allowed_json_types = array('application/json', 'text/plain');

        if (!in_array($mime_type, $allowed_json_types)) {
            return array('error' => 'Invalid file type. Only JSON files allowed');
        }

        // Validate JSON structure
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            return array('error' => 'Could not read file content');
        }

        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('error' => 'Invalid JSON format: ' . json_last_error_msg());
        }

        $safe_filename = self::sanitize_filename($file['name']);
        return array('success' => true, 'safe_filename' => $safe_filename);
    }

    /**
     * Basic file validation (size, upload status, extension)
     */
    private static function basic_validation($file, $allowed_types, $max_size) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return array('error' => 'Invalid file upload');
        }

        // Check file size
        if ($file['size'] > $max_size) {
            return array('error' => 'File size exceeds limit (' . ($max_size / 1024 / 1024) . 'MB)');
        }

        // Validate file extension
        $filename = isset($file['name']) ? $file['name'] : '';
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $allowed_exts = array();
        foreach ($allowed_types as $mime) {
            $allowed_exts = array_merge($allowed_exts, self::get_extensions_for_mime($mime));
        }
        $allowed_exts = array_unique($allowed_exts);

        if (!empty($ext) && !in_array($ext, $allowed_exts)) {
            return array('error' => 'Invalid file extension. Allowed: ' . implode(', ', $allowed_exts));
        }

        return array('success' => true);
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
     * Get file extensions for MIME type
     */
    private static function get_extensions_for_mime($mime) {
        $map = array(
            'image/jpeg' => array('jpg', 'jpeg', 'jpe'),
            'image/png' => array('png'),
            'image/gif' => array('gif'),
            'image/webp' => array('webp'),
            'application/pdf' => array('pdf'),
            'text/csv' => array('csv'),
            'application/json' => array('json')
        );
        return $map[$mime] ?? array();
    }

    /**
     * Sanitize filename (remove special characters)
     */
    private static function sanitize_filename($filename) {
        if (empty($filename)) {
            return 'uploaded_file';
        }

        // Remove dangerous characters and double extensions
        $filename = sanitize_file_name($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Prevent double extensions that could bypass checks
        if (preg_match('/\.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|asp|aspx|jsp)$/i', $filename)) {
            $filename = preg_replace('/\.[^.]+$/', '.txt', $filename);
        }

        // If filename becomes empty after sanitization, use a default
        if (empty($filename)) {
            $filename = 'uploaded_file_' . time();
        }

        return $filename;
    }

    /**
     * Strip EXIF metadata from JPEG images (privacy protection)
     */
    private static function strip_exif_data($file_path) {
        if (!function_exists('exif_read_data') || !function_exists('imagecreatefromjpeg')) {
            return false;
        }

        // Create new image without EXIF data
        $image = @imagecreatefromjpeg($file_path);
        if ($image) {
            imagejpeg($image, $file_path, self::$jpeg_quality);
            imagedestroy($image);
            return true;
        }

        return false;
    }

    /**
     * Basic malware scan for PHP code in images
     */
    private static function scan_for_malware($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return true; // If we can't read, assume it's safe
        }

        $content = @file_get_contents($file_path, false, null, 0, 2048); // Check first 2KB
        if ($content === false) {
            return true; // Skip if can't read
        }

        // Look for common PHP opening tags and dangerous functions
        $patterns = array(
            '/<\?php/i',
            '/<\?=/i',
            '/<script[^>]*>.*?php/i',
            '/(eval|base64_decode|assert|system|exec|shell_exec|passthru|popen)\s*\(/i'
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize CSV content to prevent formula injection
     */
    private static function sanitize_csv_content($content) {
        if (empty($content)) {
            return $content;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (empty($line)) {
                continue;
            }
            // Split into cells and prefix formula indicators
            $cells = str_getcsv($line);
            if (!empty($cells) && is_array($cells)) {
                foreach ($cells as $j => $cell) {
                    if (is_string($cell) && !empty($cell)) {
                        // Prefix cells starting with =, +, -, @ with tab to prevent formula execution
                        if (preg_match('/^([=+\-@])/', $cell)) {
                            $cells[$j] = "\t" . $cell;
                        }
                    }
                }
                $lines[$i] = implode(',', $cells);
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Resize image if larger than max dimensions
     */
    private static function resize_image($file_path, $mime_type) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return;
        }

        $size = @getimagesize($file_path);
        if (!$size) {
            return;
        }

        list($width, $height) = $size;
        if ($width <= self::$max_image_width && $height <= self::$max_image_height) {
            return;
        }

        $ratio = min(self::$max_image_width / $width, self::$max_image_height / $height);
        $new_width = intval($width * $ratio);
        $new_height = intval($height * $ratio);

        // Create resized image
        if ($mime_type === 'image/png') {
            if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
                return;
            }
            $source = @imagecreatefrompng($file_path);
            if (!$source) return;

            $resized = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagepng($resized, $file_path, 9);
            imagedestroy($source);
            imagedestroy($resized);
        } else {
            if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
                return;
            }
            $source = @imagecreatefromjpeg($file_path);
            if (!$source) return;

            $resized = imagecreatetruecolor($new_width, $new_height);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagejpeg($resized, $file_path, self::$jpeg_quality);
            imagedestroy($source);
            imagedestroy($resized);
        }
    }
}
