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

    /**
     * Parse the tracking `images` column into a list of URL strings.
     *
     * The column historically mixes formats: comma-separated URLs, JSON arrays,
     * and even a fused hybrid ("[\"img1.jpg\"],http://...") left by an old
     * writer. This parser handles all of them so every delete path extracts
     * the same file references.
     *
     * @param string $raw Raw column value
     * @return array List of URL/file-reference strings (may be empty)
     */
    public static function parse_images_field($raw) {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '[]') {
            return array();
        }

        $urls = array();

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $value) {
                    if (is_string($value) && trim($value) !== '') {
                        $urls[] = trim($value);
                    }
                }
            }
        }

        // Comma-separated (or fused/JSON-decode-failed) content: split on commas.
        // A well-formed single JSON array decodes above and is already complete.
        if (empty($urls) || substr_count($raw, ',') > count($urls) - 1) {
            foreach (explode(',', $raw) as $part) {
                $part = trim($part);
                // Strip wrapping quotes a legacy writer may have left.
                if (strlen($part) > 1 && $part[0] === '"' && substr($part, -1) === '"') {
                    $part = trim(substr($part, 1, -1));
                }
                if ($part !== '' && $part !== '[]' && !in_array($part, $urls, true)) {
                    $urls[] = $part;
                }
            }
        }

        return array_values($urls);
    }

    /**
     * Resolve a stored file reference to a local path candidate.
     *
     * Handles: absolute uploads URLs ("http://host/wp-content/uploads/2026/08/x.jpg"),
     * absolute path fragments ("/wp-content/uploads/..."), subdirectory-relative
     * refs ("ybt-cartons/x.jpg"), and bare filenames ("upload_123.jpg", checked
     * against the uploads root and known YBT storage subdirectories).
     *
     * Remote URLs (or anything not plausibly local) return false. The caller
     * must still verify existence + containment before deleting — use
     * delete_urls() which does both.
     *
     * @param string $url      Stored reference
     * @param string $base_url Uploads baseurl (auto-detected when null)
     * @param string $base_dir Uploads basedir (auto-detected when null)
     * @return string|false Local filesystem path candidate, or false
     */
    public static function url_to_local_path($url, $base_url = null, $base_dir = null) {
        if ($url === null) {
            return false;
        }
        $url = trim((string) $url);
        if ($url === '' || strpos($url, '..') !== false) {
            return false;
        }

        if ($base_url === null || $base_dir === null) {
            $uploads  = wp_upload_dir();
            $base_url = untrailingslashit($uploads['baseurl']);
            $base_dir = untrailingslashit($uploads['basedir']);
        }

        // 1) Exact uploads-URL match (the common case).
        if (strpos($url, $base_url) === 0) {
            return $base_dir . substr($url, strlen($base_url));
        }

        // Anything with a network scheme that ISN'T under the uploads base URL
        // is remote — never map it onto the local filesystem.
        if (preg_match('#^https?://#i', $url) || strpos($url, '//') === 0) {
            return false;
        }

        // 2) Path fragments / bare names. Drop any URL query/fragment, then any
        //    leading "wp-content/uploads/" so "2026/08/x.jpg" remains.
        $path = wp_parse_url($url, PHP_URL_PATH);
        $ref  = is_string($path) && $path !== '' ? ltrim($path, '/') : ltrim($url, '/');
        $frag = 'wp-content/uploads/';
        if (stripos($ref, $frag) === 0) {
            $ref = substr($ref, strlen($frag));
        }
        if ($ref === '') {
            return false;
        }

        // Bare filename: try the uploads root plus known YBT storage subdirs.
        if (strpos($ref, '/') === false) {
            $dirs = apply_filters('ybt_image_storage_dirs', array('', 'ybt-cartons'));
            foreach ($dirs as $dir) {
                $candidate = $base_dir . ($dir !== '' ? '/' . $dir : '') . '/' . $ref;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
            return false;
        }

        return $base_dir . '/' . $ref;
    }

    /**
     * Delete local files for one or more stored file references.
     *
     * The single deletion primitive for the plugin: every path that removes a
     * DB record which references uploaded files (single-image delete, bulk
     * delete, data-cleanup, AI prune, message delete) must go through here so
     * URL resolution, containment checking, and freed-space accounting are
     * consistent everywhere.
     *
     * @param array $refs File references (URLs, path fragments, or bare names)
     * @return array { deleted: int, freed_bytes: int }
     */
    public static function delete_urls($refs) {
        $deleted = 0;
        $freed   = 0;

        $refs = array_filter((array) $refs, function($ref) {
            return is_string($ref) && trim($ref) !== '';
        });
        if (empty($refs)) {
            return array('deleted' => 0, 'freed_bytes' => 0);
        }

        $uploads   = wp_upload_dir();
        $base_url  = untrailingslashit($uploads['baseurl']);
        $base_dir  = untrailingslashit($uploads['basedir']);
        $real_base = realpath($base_dir);
        if (!$real_base) {
            return array('deleted' => 0, 'freed_bytes' => 0);
        }

        foreach (array_unique($refs) as $ref) {
            $path = self::url_to_local_path($ref, $base_url, $base_dir);
            if (!$path) {
                continue;
            }

            // Containment check on resolved real paths: never delete anything
            // outside the uploads directory, regardless of how the URL was built.
            $real = realpath($path);
            if (!$real || strpos($real, $real_base . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }
            if (!is_file($real)) {
                continue;
            }

            $size = filesize($real);
            if (@unlink($real)) {
                $deleted++;
                $freed += (int) $size;
            }
        }

        return array('deleted' => $deleted, 'freed_bytes' => $freed);
    }
    // =========================================================================
    // UNIFIED DATA-CLEANUP ENGINE
    //
    // One code path for "which files does this data type reference, how big
    // are they, and how do we delete them". /data/preview and /data/delete
    // both consume plan_data_cleanup(): preview is the dry-run plan, delete
    // executes it, so the numbers can never drift apart. Freed-space
    // accounting and reference clearing live ONLY here.
    // =========================================================================

    /**
     * Map of YBT table => file-bearing columns. The single source of truth
     * for which columns hold stored file references.
     */
    private static $file_columns = array(
        'ybt_tracking'       => array('images'),
        'ybt_cartons'        => array('carton_image'),
        'ybt_chat'           => array('attachment'),
        'ybt_admin_messages' => array('image_url'),
    );

    /**
     * Map of cleanup type => [ [table, date-expression], ... ].
     *
     * The date expression is the row timestamp used for age filtering and it
     * intentionally differs per top-level type to match the SQL DELETE wheres:
     * a plain "tracking" cleanup ages rows by date_added, while the "uploads"
     * inventory ages by completion date first (COALESCE), exactly like the
     * former collect_ybt_file_refs(). "uploads" spans every YBT table;
     * "all_parcels" spans parcels + cartons.
     */
    private static $cleanup_type_map = array(
        'tracking' => array(
            array('ybt_tracking', 'COALESCE(date_completed, date_packed, date_received, date_added)'),
        ),
        'chat' => array(
            array('ybt_chat', 'date_created'),
        ),
        'cartons' => array(
            array('ybt_cartons', 'date_created'),
        ),
        'admin_messages' => array(
            array('ybt_admin_messages', 'date_created'),
        ),
        'uploads' => array(
            array('ybt_tracking', 'COALESCE(date_completed, date_packed, date_received, date_added)'),
            array('ybt_cartons', 'date_created'),
            array('ybt_chat', 'date_created'),
            array('ybt_admin_messages', 'date_created'),
        ),
        'all_parcels' => array(
            array('ybt_tracking', 'COALESCE(date_completed, date_packed, date_received, date_added)'),
            array('ybt_cartons', 'date_created'),
        ),
        'all_chat' => array(
            array('ybt_chat', 'date_created'),
        ),
    );

    /**
     * Get the file-bearing rows for one cleanup type.
     *
     * Returns rows in the shape { table, id, fields: {col => raw_value}, date }
     * so one query feeds the reference parser, the size accounting, and the
     * post-delete field clearing. Filters mirror the REST layer's SQL wheres
     * and are applied in PHP: age by the type's date expression, status only
     * on tracking, user_id on tracking + chat. The row count is bounded by
     * how many records actually carry files plus a hard cap.
     *
     * @param string $type Cleanup type: tracking|chat|cartons|admin_messages|uploads|all_parcels|all_chat
     * @param array  $f    Filters: older_than_days, status, user_id
     * @return array[] Rows: { table, id, fields{}, date }
     */
    public static function get_cleanup_rows($type, $f = array()) {
        global $wpdb;

        if (!isset(self::$cleanup_type_map[$type])) {
            return array();
        }

        $days    = isset($f['older_than_days']) ? max(0, (int) $f['older_than_days']) : 0;
        $status  = !empty($f['status']) ? (string) $f['status'] : '';
        $user_id = !empty($f['user_id']) ? (int) $f['user_id'] : 0;
        $cap     = (int) apply_filters('ybt_cleanup_rows_cap', 50000);

        $out    = array();
        $cutoff = $days > 0 ? strtotime("-{$days} days") : null;

        foreach (self::$cleanup_type_map[$type] as $spec) {
            list($table, $date_expr) = $spec;

            // Per-table SELECT: file column + the type's age column + any
            // filterable columns the table actually has.
            switch ($table) {
                case 'ybt_tracking':
                    $sql = "SELECT id, images, {$date_expr} AS ref_date, status, user_id
                            FROM {$wpdb->prefix}ybt_tracking
                            WHERE images IS NOT NULL AND images != '' AND images != '[]'
                            ORDER BY ref_date ASC LIMIT %d";
                    break;
                case 'ybt_chat':
                    $sql = "SELECT id, attachment, {$date_expr} AS ref_date, user_id
                            FROM {$wpdb->prefix}ybt_chat
                            WHERE attachment IS NOT NULL AND attachment != ''
                            ORDER BY ref_date ASC LIMIT %d";
                    break;
                case 'ybt_cartons':
                    $sql = "SELECT id, carton_image, {$date_expr} AS ref_date
                            FROM {$wpdb->prefix}ybt_cartons
                            WHERE carton_image IS NOT NULL AND carton_image != ''
                            ORDER BY ref_date ASC LIMIT %d";
                    break;
                case 'ybt_admin_messages':
                    $sql = "SELECT id, image_url, {$date_expr} AS ref_date
                            FROM {$wpdb->prefix}ybt_admin_messages
                            WHERE image_url IS NOT NULL AND image_url != ''
                            ORDER BY ref_date ASC LIMIT %d";
                    break;
                default:
                    continue 2;
            }

            $rows = (array) $wpdb->get_results($wpdb->prepare($sql, $cap), ARRAY_A);

            foreach ($rows as $r) {
                if ($cutoff !== null && strtotime($r['ref_date']) >= $cutoff) {
                    continue; // record is newer than the age filter
                }
                if ($status !== '' && $table === 'ybt_tracking' && (string) $r['status'] !== $status) {
                    continue;
                }
                if ($user_id > 0 && ($table === 'ybt_tracking' || $table === 'ybt_chat')
                    && (int) $r['user_id'] !== $user_id) {
                    continue;
                }
                $fields = array();
                foreach (self::$file_columns[$table] as $col) {
                    if (isset($r[$col]) && $r[$col] !== null && trim((string) $r[$col]) !== '') {
                        $fields[$col] = $r[$col];
                    }
                }
                if (empty($fields)) {
                    continue;
                }
                $out[] = array(
                    'table'  => $table,
                    'id'     => (int) $r['id'],
                    'fields' => $fields,
                    'date'   => $r['ref_date'],
                );
            }
        }

        return $out;
    }

    /**
     * Build the deletion plan for a cleanup type: parse every referenced file
     * out of the matching rows and resolve it once, verifying existence and
     * size. This is the authoritative dry-run — /data/preview reports these
     * numbers and /data/delete executes the exact same plan, so preview and
     * delete can never disagree.
     *
     * @param string $type Cleanup type
     * @param array  $f    Filters: older_than_days, status, user_id
     * @return array {
     *   files:      array[] { ref, path, size, owner }
     *   rows:       array[] { table, id, fields{}, date }
     *   count:      int
     *   size_bytes: int
     * }
     */
    public static function plan_data_cleanup($type, $f = array()) {
        return self::plan_from_rows(self::get_cleanup_rows($type, $f));
    }

    /**
     * Core planner shared by plan_data_cleanup() and plan_tracking_rows():
     * parse every reference out of the given normalized rows, resolve each
     * once, dedupe, verify existence + size, and accumulate byte totals.
     *
     * @param array[] $rows Normalized rows: { table, id, fields{}, date }
     * @return array { files, rows, count, size_bytes }
     */
    private static function plan_from_rows($rows) {
        $files        = array();
        $seen         = array();
        $size_bytes   = 0;
        $contributing = array();

        foreach ($rows as $row) {
            foreach ($row['fields'] as $col => $raw) {
                // images / carton_image can hold lists (comma, JSON, legacy
                // fused); attachment / image_url hold a single reference.
                if ($col === 'images' || $col === 'carton_image') {
                    $refs = self::parse_images_field($raw);
                } else {
                    $one  = trim((string) $raw);
                    $refs = ($one === '') ? array() : array($one);
                }
                foreach ($refs as $ref) {
                    if ($ref === '' || isset($seen[$ref])) {
                        continue; // dedupe: one physical file, one plan entry
                    }
                    $seen[$ref] = true;
                    $path = self::url_to_local_path($ref);
                    if (!$path || !is_file($path)) {
                        continue; // remote or already gone — out of scope
                    }
                    $size = (int) @filesize($path);
                    $files[] = array(
                        'ref'   => $ref,
                        'path'  => $path,
                        'size'  => $size,
                        'owner' => $row['table'] . '#' . $row['id'],
                    );
                    $size_bytes += $size;
                    // Every row that contributed a resolvable file is tracked so
                    // the executor can clear each owner's reference columns.
                    $contributing[$row['table'] . '#' . $row['id']] = $row;
                }
            }
        }

        return array(
            'files'      => $files,
            'rows'       => array_values($contributing),
            'count'      => count($files),
            'size_bytes' => $size_bytes,
        );
    }

    /**
     * Build a cleanup plan from an explicit set of ybt_tracking rows — the
     * entry point for flows that select their own row sets (bulk delete by
     * ids, AI prune by months). Accepts ARRAY_A arrays or row objects with
     * at least id + images. The returned plan carries rows_matched.
     *
     * @param array $rows ybt_tracking rows
     * @return array plan_data_cleanup()-shaped plan plus rows_matched
     */
    public static function plan_tracking_rows($rows) {
        $norm = array();
        foreach ((array) $rows as $r) {
            $is_arr = is_array($r);
            $id     = (int) ($is_arr ? (isset($r['id']) ? $r['id'] : 0) : (isset($r->id) ? $r->id : 0));
            $raw    = $is_arr ? (isset($r['images']) ? $r['images'] : '') : (isset($r->images) ? $r->images : '');
            if (!$id || $raw === null || trim((string) $raw) === '') {
                continue;
            }
            $date = $is_arr ? (isset($r['ref_date']) ? $r['ref_date'] : null) : (isset($r->ref_date) ? $r->ref_date : null);
            $norm[] = array(
                'table'  => 'ybt_tracking',
                'id'     => $id,
                'fields' => array('images' => $raw),
                'date'   => $date,
            );
        }

        $plan = self::plan_from_rows($norm);
        $plan['rows_matched'] = count($norm);
        return $plan;
    }

    /**
     * Fetch ybt_tracking rows (ARRAY_A: id, images, ref_date) for the
     * extended cleanup flows:
     *  - ids:    exact row targeting (bulk delete), chunked IN() queries
     *  - months: completion date (COALESCE) older than N months (AI prune)
     *  - neither: every row that carries images
     *
     * @param array $f { ids?: int[], months?: int }
     * @return array[] { id, images, ref_date }
     */
    public static function get_tracking_rows_for_cleanup($f = array()) {
        global $wpdb;

        $cols = "id, images, COALESCE(date_completed, date_received, date_added) AS ref_date";

        if (!empty($f['ids'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $f['ids']))));
            $out = array();
            foreach (array_chunk($ids, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '%d'));
                $out = array_merge($out, (array) $wpdb->get_results(
                    $wpdb->prepare("SELECT {$cols} FROM {$wpdb->prefix}ybt_tracking WHERE id IN ($ph)", $chunk),
                    ARRAY_A
                ));
            }
            return $out;
        }

        if (!empty($f['months'])) {
            return (array) $wpdb->get_results($wpdb->prepare(
                "SELECT {$cols} FROM {$wpdb->prefix}ybt_tracking
                 WHERE images IS NOT NULL AND images != '' AND images != '[]'
                 AND COALESCE(date_completed, date_received, date_added) < DATE_SUB(NOW(), INTERVAL %d MONTH)",
                max(1, (int) $f['months'])
            ), ARRAY_A);
        }

        return (array) $wpdb->get_results(
            "SELECT {$cols} FROM {$wpdb->prefix}ybt_tracking
             WHERE images IS NOT NULL AND images != '' AND images != '[]'",
            ARRAY_A
        );
    }

    /**
     * Execute a plan built by plan_data_cleanup(): delete the physical files
     * through the audited delete_urls() primitive, then clear the file
     * columns on rows whose file was actually deleted (or was already
     * missing). Rows whose references point elsewhere (remote URLs) are left
     * untouched. Pass $clear_fields = false when the whole rows are about to
     * be deleted anyway. Freed-space accounting and reference clearing live
     * ONLY here.
     *
     * @param array $plan         Result of plan_data_cleanup()
     * @param bool  $clear_fields Whether to blank file columns on affected rows
     * @return array { deleted_files, freed_bytes, cleared_refs }
     */
    public static function execute_data_cleanup($plan, $clear_fields = true) {
        global $wpdb;

        $refs = array();
        $owners = array();
        foreach ($plan['files'] as $file) {
            $refs[] = $file['ref'];
            $owners[$file['owner']] = true;
        }

        $res     = self::delete_urls($refs);
        $cleared = 0;

        if ($clear_fields) {
            foreach ($plan['rows'] as $row) {
                $key = $row['table'] . '#' . $row['id'];
                if (!isset($owners[$key])) {
                    continue; // none of this row's files were local/deleted
                }
                foreach (array_keys($row['fields']) as $col) {
                    $wpdb->update(
                        $wpdb->prefix . $row['table'],
                        array($col => ''),
                        array('id' => $row['id']),
                        array('%s'),
                        array('%d')
                    );
                    $cleared++;
                }
            }
        }

        return array(
            'deleted_files' => (int) $res['deleted'],
            'freed_bytes'   => (int) $res['freed_bytes'],
            'cleared_refs'  => $cleared,
        );
    }

    /**
     * Convenience wrapper: build a plan and execute it in one call. Use for
     * delete flows that don't need the plan numbers; use the split API when
     * the caller wants to show "would delete N files / X KB" first.
     *
     * @param string $type         Cleanup type
     * @param array  $f            Filters: older_than_days, status, user_id
     * @param bool   $clear_fields Whether to blank file columns on affected rows
     * @return array { deleted_files, freed_bytes, cleared_refs }
     */
    public static function run_data_cleanup($type, $f = array(), $clear_fields = true) {
        return self::execute_data_cleanup(self::plan_data_cleanup($type, $f), $clear_fields);
    }

    /**
     * Plan the pruning of parcel images for the AI/bulk flows, with three
     * selectors:
     *  - months: rows whose COALESCE(date_completed, date_received, date_added)
     *            is older than N months (mirrors the AI prune's original SQL)
     *  - ids:    explicit ybt_tracking row ids (bulk delete)
     *  - rows:   already-fetched rows (ARRAY_A or objects with id + images)
     * With no selector, every row that carries images is planned.
     *
     * Returns a plan_data_cleanup()-shaped plan plus rows_matched (rows
     * carrying images) and refs_found (references parsed, resolvable or not).
     *
     * @param array $f { rows?: array, ids?: int[], months?: int }
     * @return array
     */
    public static function plan_tracking_images($f = array()) {
        if (!empty($f['rows'])) {
            $rows = (array) $f['rows'];
        } elseif (!empty($f['ids'])) {
            $rows = self::get_tracking_rows_for_cleanup(array('ids' => $f['ids']));
        } elseif (!empty($f['months'])) {
            $rows = self::get_tracking_rows_for_cleanup(array('months' => $f['months']));
        } else {
            $rows = self::get_tracking_rows_for_cleanup();
        }

        $plan = self::plan_tracking_rows($rows);
        $plan['rows_matched'] = count($rows);

        $refs_found = 0;
        foreach ($rows as $r) {
            $is_arr = is_array($r);
            $raw    = $is_arr
                ? (isset($r['images']) ? $r['images'] : '')
                : (isset($r->images) ? $r->images : '');
            $refs_found += count(self::parse_images_field($raw));
        }
        $plan['refs_found'] = $refs_found;

        return $plan;
    }

    // =========================================================================
    // ORPHANED-FILE SCAN
    //
    // Inverse of the cleanup engine: find files living in YBT upload
    // locations that NO YBT record references. Media Library files and other
    // plugins' uploads are out of scope by construction (only the scanned
    // directories are examined and every DB reference is excluded).
    // =========================================================================

    /**
     * Uploads-relative directories scanned for orphaned files ('' = root).
     * Extend via the ybt_orphan_scan_dirs filter when new storage dirs are
     * introduced.
     *
     * @return string[]
     */
    public static function get_orphan_scan_dirs() {
        return array_values((array) apply_filters('ybt_orphan_scan_dirs', array('', 'ybt-cartons')));
    }

    /**
     * Build the exclusion set for the orphan scan from every file reference
     * stored in YBT records (same four tables the cleanup engine reads).
     *
     * Returns:
     *   refs        => normalized uploads-relative paths of every stored ref,
     *                  resolvable or not (conservative: protect even refs that
     *                  currently fail to resolve)
     *   basenames   => basenames of those refs (legacy bare-name matching)
     *   attachments => basenames of Media-Library attachment files (only
     *                  entries that exist locally), keyed by basename
     *
     * @return array
     */
    public static function build_referenced_ref_set() {
        global $wpdb;

        $norm = function($ref) {
            $ref = trim((string) $ref);
            if ($ref === '') {
                return '';
            }
            $path = wp_parse_url($ref, PHP_URL_PATH);
            $s    = is_string($path) && $path !== '' ? ltrim($path, '/') : ltrim($ref, '/');
            $frag = 'wp-content/uploads/';
            if (stripos($s, $frag) === 0) {
                $s = substr($s, strlen($frag));
            }
            return $s;
        };

        $refs      = array();
        $basenames = array();

        $queries = array(
            "SELECT images AS col FROM {$wpdb->prefix}ybt_tracking WHERE images IS NOT NULL AND images != '' AND images != '[]'",
            "SELECT carton_image AS col FROM {$wpdb->prefix}ybt_cartons WHERE carton_image IS NOT NULL AND carton_image != ''",
            "SELECT attachment AS col FROM {$wpdb->prefix}ybt_chat WHERE attachment IS NOT NULL AND attachment != ''",
            "SELECT image_url AS col FROM {$wpdb->prefix}ybt_admin_messages WHERE image_url IS NOT NULL AND image_url != ''",
        );

        foreach ($queries as $q) {
            foreach ((array) $wpdb->get_col($q) as $raw) {
                foreach (self::parse_images_field($raw) as $ref) {
                    $n = $norm($ref);
                    if ($n === '') {
                        continue;
                    }
                    $refs[]      = strtolower($n);
                    $basenames[] = strtolower(wp_basename($n));
                }
            }
        }

        $attachments = array();
        $baseurl_frag = 'wp-content/uploads/';
        $posts = (array) $wpdb->get_results(
            "SELECT p.guid, pm.meta_value AS file
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment'"
        );
        $uploads = wp_upload_dir();
        $base_dir = untrailingslashit($uploads['basedir']);
        foreach ($posts as $p) {
            $rel = '';
            if (!empty($p->file)) {
                $rel = ltrim((string) $p->file, '/');
            } elseif (!empty($p->guid) && stripos($p->guid, $baseurl_frag) !== false) {
                $rel = substr($p->guid, stripos($p->guid, $baseurl_frag) + strlen($baseurl_frag));
            }
            if ($rel === '') {
                continue;
            }
            if (is_file($base_dir . '/' . $rel)) {
                $attachments[strtolower(wp_basename($rel))] = true;
            }
        }

        return array(
            'refs'        => array_values(array_unique($refs)),
            'basenames'   => array_values(array_unique($basenames)),
            'attachments' => $attachments,
        );
    }

    /**
     * Plan the orphan purge: scan the YBT upload directories, exclude every
     * referenced file (and Media-Library attachments), apply the mandatory
     * age filter on file mtime, and measure what would be freed.
     *
     * @param array $f { older_than_days: int } (required > 0 — enforced by the REST layer)
     * @return array {
     *   files:      array[] { ref, path, size, modified, owner }
     *   count:      int
     *   size_bytes: int
     *   scanned:    int   (files examined, before exclusions/age)
     *   excluded:   int   (files skipped because they are referenced)
     * }
     */
    public static function plan_orphan_cleanup($f = array()) {
        $older_than_days = isset($f['older_than_days']) ? max(0, (int) $f['older_than_days']) : 0;
        $cutoff = $older_than_days > 0 ? strtotime("-{$older_than_days} days") : null;

        $uploads  = wp_upload_dir();
        $base_dir = untrailingslashit($uploads['basedir']);
        if (!is_dir($base_dir)) {
            return array('files' => array(), 'count' => 0, 'size_bytes' => 0, 'scanned' => 0, 'excluded' => 0);
        }

        $exc = self::build_referenced_ref_set();
        $ref_set      = array_flip($exc['refs']);
        $basename_set = array_flip($exc['basenames']);
        $attach_set   = $exc['attachments'];

        $files      = array();
        $seen       = array();
        $size_bytes = 0;
        $scanned    = 0;
        $excluded   = 0;

        foreach (self::get_orphan_scan_dirs() as $dir) {
            $abs = $base_dir . ($dir !== '' ? '/' . $dir : '');
            if (!is_dir($abs)) {
                continue;
            }
            // For the uploads root (''), scan only direct children non-recursively
            // so we NEVER traverse into year/month or other plugin subdirectories.
            // Dedicated YBT subdirectories (e.g. 'ybt-cartons') can be scanned recursively.
            if ($dir === '') {
                $it = new FilesystemIterator($abs, FilesystemIterator::SKIP_DOTS);
            } else {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS)
                );
            }
            foreach ($it as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $scanned++;
                $path = $item->getPathname();
                $real = realpath($path);
                if (!$real || strpos($real, realpath($base_dir) . DIRECTORY_SEPARATOR) !== 0) {
                    continue; // containment: stay inside uploads
                }

                $rel = ltrim(str_replace($base_dir, '', wp_normalize_path($path)), '/');
                if ($rel === '' || strtolower(basename($rel))[0] === '.') {
                    continue; // dotfiles are never touched
                }
                if (isset($ref_set[strtolower($rel)]) || isset($basename_set[strtolower(basename($rel))])) {
                    $excluded++;
                    continue; // referenced by some YBT record
                }
                if (isset($attach_set[strtolower(basename($rel))])) {
                    $excluded++;
                    continue; // Media-Library attachment file
                }

                if ($cutoff !== null && (int) @filemtime($real) >= $cutoff) {
                    continue; // file is newer than the age filter
                }

                $norm_ref = strtolower($rel);
                if (isset($seen[$norm_ref])) {
                    continue;
                }
                $seen[$norm_ref] = true;

                $size = (int) @filesize($real);
                $files[] = array(
                    'ref'      => $rel,
                    'path'     => $real,
                    'size'     => $size,
                    'modified' => (int) @filemtime($real),
                    'owner'    => 'orphan',
                );
                $size_bytes += $size;
            }
        }

        return array(
            'files'      => $files,
            'count'      => count($files),
            'size_bytes' => $size_bytes,
            'scanned'    => $scanned,
            'excluded'   => $excluded,
        );
    }

    /**
     * Execute an orphan purge plan: delete the planned files through the
     * containment-checked delete_urls() primitive and report the freed
     * space. No DB rows involved — nothing to clear.
     *
     * @param array $plan Result of plan_orphan_cleanup()
     * @return array { deleted_files, freed_bytes }
     */
    public static function execute_orphan_cleanup($plan) {
        $refs = array();
        foreach ((array) $plan['files'] as $file) {
            $refs[] = $file['ref'];
        }
        $res = self::delete_urls($refs);
        return array(
            'deleted_files' => (int) $res['deleted'],
            'freed_bytes'   => (int) $res['freed_bytes'],
        );
    }
}
