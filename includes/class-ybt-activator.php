<?php
/**
 * File: includes/class-ybt-activator.php
 * Description: Handles plugin activation, deactivation, and uninstallation tasks.
 *              - Creates necessary pages with associated shortcodes.
 *              - Sets up required database tables.
 *              - Manages rewrite rules flushing.
 *              - Validates security keys on activation.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class YBT_Activator
 *
 * Fires during plugin activation, deactivation, and uninstallation.
 */
class YBT_Activator {

    /**
     * Fired during plugin activation.
     *
     * - Creates necessary pages with shortcodes.
     * - Creates database tables.
     * - Validates and sets up security keys.
     * - Flushes rewrite rules to ensure permalink structures are updated.
     */
    public static function activate() {
        self::create_pages();
        self::create_database_tables();
        self::validate_security_keys();
        // Flush rewrite rules to ensure permalink structures are updated
        flush_rewrite_rules();
    }

    /**
     * Fired during plugin deactivation.
     *
     * - Currently, only flushes rewrite rules.
     *   Consider removing or adding necessary deactivation tasks.
     */
    public static function deactivate() {
        // Flush rewrite rules if necessary
        // Typically, flushing is not required on deactivation unless you've made changes that need immediate effect
        flush_rewrite_rules();
    }

    /**
     * Fired during plugin uninstallation.
     *
     * - Flushes rewrite rules.
     *   Consider adding necessary cleanup tasks such as removing options, database tables, etc.
     */
    public static function uninstall() {
        // Flush rewrite rules if necessary
        flush_rewrite_rules();

        // TODO: Implement additional uninstallation cleanup tasks
        // For example:
        // - Delete plugin options: delete_option( 'ybt_some_option' );
        // - Drop custom database tables: global $wpdb; $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ybt_custom_table" );
        // Ensure to handle data deletion carefully and optionally back up data before deletion.
    }

    /**
     * Validate and configure security keys on activation.
     *
     * Ensures that required API keys and secrets are properly configured.
     * Generates secure defaults if missing and warns about insecure configurations.
     */
    private static function validate_security_keys() {
        // Check and set JWT secret key
        $jwt_secret = get_option('ybt_jwt_secret_key');
        if (empty($jwt_secret)) {
            // Generate a secure random key
            $jwt_secret = wp_generate_password(64, true, true);
            update_option('ybt_jwt_secret_key', $jwt_secret);
            error_log('YBT SECURITY: Generated new JWT secret key on activation');
        }

        // Check and set API key if not exists
        $api_key_hash = get_option('ybt_api_key_hash');
        if (empty($api_key_hash)) {
            $new_api_key = wp_generate_password(32, false, false);
            require_once plugin_dir_path(__FILE__) . 'class-ybt-encryption.php';
            if (class_exists('YBT_Encryption')) {
                $encryption = YBT_Encryption::getInstance();
                $api_key_hash = $encryption->hash_api_key($new_api_key);
                update_option('ybt_api_key_hash', $api_key_hash);
                update_option('ybt_api_key_display', substr($new_api_key, 0, 8) . '****'); // Store partial for display
                error_log('YBT SECURITY: Generated new API key on activation');
            }
        }

        // Check and set webhook secret if not exists
        $webhook_secret = get_option('ybt_webhook_secret');
        if (empty($webhook_secret)) {
            $webhook_secret = wp_generate_password(48, true, true);
            update_option('ybt_webhook_secret', $webhook_secret);
            error_log('YBT SECURITY: Generated new webhook secret on activation');
        }

        // Check for insecure WordPress auth keys that should not be used
        $auth_keys = array(
            'AUTH_KEY' => defined('AUTH_KEY') ? AUTH_KEY : '',
            'SECURE_AUTH_KEY' => defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '',
            'LOGGED_IN_KEY' => defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '',
            'NONCE_KEY' => defined('NONCE_KEY') ? NONCE_KEY : '',
        );

        $insecure_keys = array();
        foreach ($auth_keys as $name => $key) {
            if (!empty($key) && in_array($key, array('put your unique phrase here', 'default', '', 'changeme'))) {
                $insecure_keys[] = $name;
            }
        }

        if (!empty($insecure_keys)) {
            error_log('YBT SECURITY WARNING: Insecure WordPress keys detected: ' . implode(', ', $insecure_keys));
        }
    }

    /**
     * Automatically create necessary pages with shortcodes.
     *
     * Checks if each page exists by its slug. If not, creates the page with the specified title and content.
     * Assigns the page to the first available Administrator or defaults to user ID 1.
     */
    private static function create_pages() {
        $pages = array(
            'dashboard'           => array('title' => 'Dashboard', 'content' => '[ybt_dashboard]'),
            'login'               => array('title' => 'Login', 'content' => '[ybt_login]'),
            'register'            => array('title' => 'Register', 'content' => '[ybt_register]'),
            'reset-password'      => array('title' => 'Reset Password', 'content' => '[ybt_reset_password]'),
            'add-tracking'        => array('title' => 'Add Tracking', 'content' => '[ybt_add_tracking]'),
            'not-received'        => array('title' => 'Not Received', 'content' => '[ybt_not_received]'),
            'received'            => array('title' => 'Received', 'content' => '[ybt_received]'),
            'packing-in-progress' => array('title' => 'Packing in Progress', 'content' => '[ybt_packing_in_progress]'),
            'packed'              => array('title' => 'Packed', 'content' => '[ybt_packed]'),
            'completed'           => array('title' => 'Completed', 'content' => '[ybt_completed]'),
            'anonymous'           => array('title' => 'Anonymous Parcels', 'content' => '[ybt_anonymous]'),
            'profile'             => array('title' => 'My Profile', 'content' => '[ybt_profile]'),
            'support'             => array('title' => 'Support Chat', 'content' => '[ybt_support]')
        );


        // Determine the author ID
        $author_id = self::get_author_id();

        foreach ( $pages as $slug => $page_data ) {
            // Check if page exists by slug
            $page_check = get_page_by_path( $slug );

            if ( ! $page_check ) {
                $page = array(
                    'post_type'    => 'page',
                    'post_title'   => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status'  => 'publish',
                    'post_author'  => $author_id,
                    'post_name'    => $slug
                );

                $page_id = wp_insert_post( $page );

                if ( is_wp_error( $page_id ) ) {
                    error_log( 'YBT: Failed to create page "' . $slug . '": ' . $page_id->get_error_message() );
                } else {
                    // Optionally, you can log successful page creation
                    // error_log( 'YBT: Successfully created page "' . $slug . '" with ID ' . $page_id );
                }
            }
        }
    }


    /**
     * Retrieve the author ID for creating pages.
     *
     * - Attempts to get the current user ID.
     * - Falls back to the first Administrator.
     * - Defaults to user ID 1 (usually the super admin) if no Administrators are found.
     *
     * @return int The author ID to be used for creating pages.
     */
    private static function get_author_id() {
        $current_user = wp_get_current_user();
        if ( $current_user && $current_user->ID ) {
            return $current_user->ID;
        }


        $admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
        if ( ! empty( $admins ) ) {
            return $admins[0]->ID;
        }

        return 1; // Default to user ID 1 (super admin)
    }


    /**
     * Trigger database table creation.
     *
     * Ensures the YBT_Database class is loaded and calls its create_tables method.
     * Implements error handling and logging for database table creation.
     */
    private static function create_database_tables() {
        // Define the path to the YBT_Database class file
        $db_class_file = plugin_dir_path( __FILE__ ) . 'class-ybt-database.php';

        // Check if the file exists and include it
        if ( file_exists( $db_class_file ) ) {
            require_once $db_class_file;
        } else {
            error_log( 'YBT: class-ybt-database.php file not found at ' . $db_class_file );
            return;
        }

        // Check if the YBT_Database class exists
        if ( class_exists( 'YBT_Database' ) ) {
            try {
                YBT_Database::create_tables(true);
                // Optionally, log successful table creation
                // error_log( 'YBT: Database tables created successfully.' );
            } catch ( Exception $e ) {
                error_log( 'YBT: Failed to create database tables: ' . $e->getMessage() );
            }
        } else {
            error_log( 'YBT: YBT_Database class not found. Ensure the class is correctly defined in class-ybt-database.php.' );
        }
    }
}
