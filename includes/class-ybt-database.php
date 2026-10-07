<?php
/**
 * YBT Database Management Class - FIXED VERSION
 * Handles all database operations and table creation with security validation
 */
if (!defined('ABSPATH')) {
    exit;
}

class YBT_Database {
    // Whitelist of allowed tables for schema operations
    private static $allowed_tables = array(
        'ybt_tracking', 'ybt_admin_messages', 'ybt_chat', 'ybt_cartons',
        'ybt_payment_settings', 'ybt_notifications',
        'ybt_fcm_tokens', 'ybt_security_log', 'ybt_login_attempts',
        'ybt_activity_log', 'ybt_warehouse_settings', 'ybt_invoices',
        'ybt_tracking_edits'
    );

    public static function create_tables($is_system_upgrade = false) {
        global $wpdb;

        // Security: Verify caller has proper permissions (or is internal system upgrade / activation)
        if (!$is_system_upgrade && !current_user_can('manage_options') && !defined('WP_INSTALLING')) {
            ybt_log_security_event('UNAUTHORIZED_TABLE_CREATION_ATTEMPT', array(
                'ip' => ybt_get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'unknown'
            ));
            return false;
        }

        try {
            self::create_tracking_table();
            self::create_admin_messages_table();
            self::create_fcm_tokens_table();
            self::create_warehouse_settings_table();
            self::create_chat_table();
            self::create_cartons_table();
            self::create_payment_settings_table();
            self::create_activity_log_table();
            self::create_login_attempts_table();
            self::create_security_log_table();
            self::create_invoices_table();
            self::create_tracking_edits_table();
            self::create_notifications_table();

            // Seed default data if necessary
            self::seed_default_data();

            // Log successful creation
            ybt_log_security_event('TABLE_CREATION_SUCCESS', array(
                'tables_created' => count(self::$allowed_tables)
            ));
            return true;
        } catch (Exception $e) {
            ybt_log_security_event('TABLE_CREATION_ERROR', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    private static function create_tracking_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_tracking';

        // Validate table name against whitelist
        if (!self::validate_table_name($table_name)) {
            throw new Exception('Invalid table name');
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Use dbDelta properly (requires specific formatting)
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tracking_number varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            username varchar(100) DEFAULT NULL,
            phone varchar(20) DEFAULT NULL,
            description text,
            status enum('not_received','received','packing_in_progress','packed','completed') DEFAULT 'not_received',
            images text,
            batch_id varchar(50) DEFAULT NULL,
            item_count int(11) DEFAULT 1,
            item_type enum('unit','sets','packs') DEFAULT 'unit',
            is_complete tinyint(1) DEFAULT 0,
            payment_evidence varchar(500) DEFAULT NULL,
            shipping_method varchar(20) DEFAULT 'sea',
            shipping_address text,
            package_phone varchar(50) DEFAULT NULL,
            shipping_company_phone varchar(50) DEFAULT NULL,
            shipping_company_name varchar(255) DEFAULT NULL,
            shipping_mark varchar(255) DEFAULT NULL,
            cartons int(11) DEFAULT NULL,
            weight_kg decimal(10,2) DEFAULT NULL,
            admin_notes text,
            payment_amount decimal(10,2) DEFAULT NULL,
            payment_currency varchar(10) DEFAULT NULL,
            last_edited_by bigint(20) DEFAULT NULL,
            last_edited_by_name varchar(100) DEFAULT NULL,
            last_edited_at datetime DEFAULT NULL,
            edit_count int(11) DEFAULT 0,
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            date_received datetime DEFAULT NULL,
            date_packing_requested datetime DEFAULT NULL,
            date_packed datetime DEFAULT NULL,
            date_completed datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tracking_number (tracking_number),
            KEY idx_status (status),
            KEY idx_user_id (user_id),
            KEY idx_username (username(50)),
            KEY idx_date_added (date_added),
            KEY idx_batch_id (batch_id(50))
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Ensure proper indexes exist
        self::ensure_tracking_indexes($table_name);
    }

    /**
     * Validate table name against whitelist
     */
    private static function validate_table_name($full_table_name) {
        global $wpdb;

        // Extract base table name without prefix
        $base_name = str_replace($wpdb->prefix, '', $full_table_name);

        return in_array($base_name, self::$allowed_tables);
    }

    /**
     * Safely add column if it doesn't exist
     */
    private static function safe_add_column($table_name, $column_name, $column_definition) {
        global $wpdb;

        // Validate inputs
        if (!self::validate_table_name($table_name)) {
            throw new Exception('Invalid table name');
        }

        // Sanitize column name (alphanumeric + underscore only)
        if (!preg_match('/^[a-z0-9_]+$/', $column_name)) {
            throw new Exception('Invalid column name');
        }

        // Check if column exists
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM `{$table_name}` LIKE '{$column_name}'"
        );

        if (empty($column_exists)) {
            // Use backticks for safety
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$column_definition}");
        }
    }

    /**
     * Ensure critical indexes exist for performance
     */
    private static function ensure_tracking_indexes($table_name) {
        global $wpdb;

        // List of required indexes for performance optimization
        $required_indexes = array(
            'idx_status' => "status",
            'idx_user_id' => "user_id",
            'idx_username' => "username(50)",
            'idx_date_added' => "date_added",
            'idx_date_received' => "date_received",
            'idx_date_completed' => "date_completed",
            'idx_shipping_method' => "shipping_method(20)",
            'idx_batch_id' => "batch_id(50)",
            'idx_is_complete' => "is_complete",
            // Composite indexes for faster dashboard queries
            'idx_status_user' => "status, user_id",
            'idx_status_username' => "status, username(20)",
            'idx_user_status' => "user_id, status",
            'idx_username_status' => "username(20), status"
        );

        foreach ($required_indexes as $index_name => $columns) {
            // Validate index name (alphanumeric and underscore only)
            if (!preg_match('/^[a-z0-9_]+$/', $index_name)) {
                continue;
            }

            // Validate column names (alphanumeric, underscore, and parentheses for length)
            if (!preg_match('/^[a-z0-9_(), ]+$/', $columns)) {
                continue;
            }

            $exists = $wpdb->get_var($wpdb->prepare(
                "SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
                $index_name
            ));

            if (!$exists) {
                // Use backticks for safety
                $wpdb->query("CREATE INDEX `{$index_name}` ON `{$table_name}` ({$columns})");
            }
        }
    }

    /**
     * Safe table truncation with confirmation
     */
    public static function safe_truncate_table($table_name) {
        global $wpdb;

        if (!self::validate_table_name($table_name)) {
            throw new Exception('Invalid table name');
        }

        // Require explicit confirmation parameter
        if (!isset($_REQUEST['confirm_truncate']) || $_REQUEST['confirm_truncate'] !== 'YES_I_AM_SURE') {
            throw new Exception('Truncation requires explicit confirmation');
        }

        // Log the action
        error_log('YBT: Table truncated - ' . $table_name . ' by user ' . get_current_user_id());

        return $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$table_name}");
    }

    /**
     * Create FCM Tokens Table
     */
    /**
     * Mobile/web notification inbox (app Alerts tab, badge poll, popup).
     * Was listed in $allowed_tables but never actually created — installs
     * newer than the notifications feature were left without the table,
     * and the mobile endpoints silently returned empty results.
     */
    private static function create_notifications_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_notifications';

        if (!self::validate_table_name($table_name)) {
            throw new Exception('Invalid table name');
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            body text NULL,
            type varchar(50) NOT NULL DEFAULT 'general',
            tracking_id bigint(20) NULL,
            tracking_number varchar(100) NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_unread (user_id, is_read),
            KEY idx_user_created (user_id, date_created)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_fcm_tokens_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_fcm_tokens';

        if (!self::validate_table_name($table_name)) {
            throw new Exception('Invalid table name');
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            fcm_token varchar(500) NOT NULL,
            device_type enum('android','ios','web') DEFAULT 'android',
            last_updated datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_last_updated (last_updated)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_admin_messages_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_admin_messages';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            image_url varchar(500) DEFAULT NULL,
            video_url varchar(500) DEFAULT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_warehouse_settings_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_warehouse_settings';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            address text NOT NULL,
            phone varchar(50) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            hours varchar(255) DEFAULT NULL,
            shipping_type varchar(20) DEFAULT 'sea',
            is_active tinyint(1) DEFAULT 1,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_chat_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_chat';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            username varchar(100) DEFAULT NULL,
            message text NOT NULL,
            attachment varchar(500) DEFAULT NULL,
            is_admin tinyint(1) DEFAULT 0,
            is_read tinyint(1) DEFAULT 0,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_date_created (date_created)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_cartons_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_cartons';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            batch_id varchar(50) NOT NULL,
            carton_number int(11) NOT NULL,
            weight_kg decimal(10,2) DEFAULT NULL,
            payment_amount decimal(10,2) DEFAULT NULL,
            payment_currency varchar(10) DEFAULT 'CNY',
            carton_image text,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_batch_id (batch_id(20)),
            KEY idx_date_created (date_created)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_payment_settings_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_payment_settings';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            bank_name varchar(255) NOT NULL,
            account_name varchar(255) NOT NULL,
            account_number varchar(100) NOT NULL,
            qr_code_image varchar(500) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_activity_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_activity_log';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            details text,
            ip_address varchar(45) DEFAULT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_action (action),
            KEY idx_date_created (date_created)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Login attempts table.
     * Records every successful and failed authentication attempt (web + mobile).
     */
    private static function create_login_attempts_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_login_attempts';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            username varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            success tinyint(1) NOT NULL DEFAULT 0,
            attempt_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_attempt_time (attempt_time),
            KEY idx_ip (ip_address),
            KEY idx_user_id (user_id)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Security event log table.
     * DB-backed mirror of the file security log so admin screens can query it.
     */
    private static function create_security_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_security_log';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            description text,
            severity varchar(20) NOT NULL DEFAULT 'info',
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_severity (severity),
            KEY idx_date_created (date_created),
            KEY idx_event_type (event_type)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Tracking edit history table.
     * Records WHO edited a tracking number, WHEN, and WHAT changed (old -> new).
     */
    private static function create_tracking_edits_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_tracking_edits';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tracking_id bigint(20) NOT NULL,
            tracking_number varchar(50) DEFAULT NULL,
            admin_id bigint(20) NOT NULL DEFAULT 0,
            admin_name varchar(100) DEFAULT NULL,
            action varchar(50) NOT NULL,
            summary text,
            changes longtext,
            ip_address varchar(45) DEFAULT NULL,
            source varchar(20) DEFAULT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tracking_id (tracking_id),
            KEY idx_admin_id (admin_id),
            KEY idx_action (action),
            KEY idx_date_created (date_created)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_invoices_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_invoices';
        if (!self::validate_table_name($table_name)) throw new Exception('Invalid table name');
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            batch_id varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            username varchar(100) DEFAULT NULL,
            total_items int(11) DEFAULT 0,
            total_cartons int(11) DEFAULT 0,
            total_weight decimal(10,2) DEFAULT 0,
            subtotal decimal(12,2) DEFAULT 0,
            currency varchar(10) DEFAULT 'CNY',
            exchange_rate decimal(10,4) DEFAULT 1.0000,
            deduction decimal(12,2) DEFAULT 0,
            deduction_currency varchar(10) DEFAULT 'CNY',
            grand_total decimal(12,2) DEFAULT 0,
            status enum('draft','sent','paid','cancelled') DEFAULT 'draft',
            notes text,
            shipping_address text,
            line_items longtext,
            generated_by bigint(20) DEFAULT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            date_sent datetime DEFAULT NULL,
            date_paid datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY idx_batch_id (batch_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_date_created (date_created)
        ) {$charset_collate};";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function seed_default_data() {
        global $wpdb;

        // 1. Seed Warehouse Settings if empty
        $table_warehouse = $wpdb->prefix . 'ybt_warehouse_settings';
        $warehouse_exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_warehouse");
        if ($warehouse_exists == 0) {
            $wpdb->insert($table_warehouse, array(
                'address' => "No. 123 Warehouse St, Guangzhou, China\nID: {user_id}\nPostcode: 510000",
                'phone' => '+86 123 4567 8901',
                'hours' => '9:00 AM - 6:00 PM (GMT+8)',
                'shipping_type' => 'air',
                'is_active' => 1
            ));
        }

        // 2. Seed Payment Settings if empty
        $table_payment = $wpdb->prefix . 'ybt_payment_settings';
        $payment_exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_payment");
        if ($payment_exists == 0) {
            $wpdb->insert($table_payment, array(
                'bank_name' => 'Bank of China',
                'account_name' => 'YBT Solutions Ltd',
                'account_number' => '6222 0000 1111 2222 333',
                'is_active' => 1
            ));
        }

        // 3. Seed Admin Messages (Announcement) if empty
        $table_messages = $wpdb->prefix . 'ybt_admin_messages';
        $message_exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_messages");
        if ($message_exists == 0) {
            $wpdb->insert($table_messages, array(
                'message' => 'Welcome to YBT Packing Service! We are operational and ready to ship your parcels.',
                'date_created' => current_time('mysql')
            ));
        }
    }
}
