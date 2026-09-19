<?php
/**
 * YBT User Profile Management
 * Handles custom user meta fields and search functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class YBT_User_Profile {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // WordPress Admin Profile Hooks
        add_action('show_user_profile', array($this, 'add_custom_user_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_user_fields'));

        add_action('personal_options_update', array($this, 'save_custom_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_user_fields'));

        // Modify User Search to include Mobile Number
        add_action('pre_get_users', array($this, 'modify_user_search'));
    }

    /**
     * Add Custom fields to User Profile
     */
    public function add_custom_user_fields($user) {
        $mobile_number = get_user_meta($user->ID, 'ybt_mobile_number', true);
        $shipping_address = get_user_meta($user->ID, 'ybt_shipping_address', true);
        $shipping_phone = get_user_meta($user->ID, 'ybt_shipping_phone', true);
        ?>
        <h3>YBT Service Details</h3>
        <table class="form-table">
            <tr>
                <th><label for="ybt_mobile_number">Mobile Number</label></th>
                <td>
                    <input type="text" name="ybt_mobile_number" id="ybt_mobile_number" value="<?php echo esc_attr($mobile_number); ?>" class="regular-text" /><br />
                    <span class="description">Enter the user's mobile number for tracking and identification.</span>
                </td>
            </tr>
            <tr>
                <th><label for="ybt_shipping_address">Default Shipping Address</label></th>
                <td>
                    <textarea name="ybt_shipping_address" id="ybt_shipping_address" rows="3" class="regular-text"><?php echo esc_textarea($shipping_address); ?></textarea><br />
                    <span class="description">User's default destination address for packing requests.</span>
                </td>
            </tr>
            <tr>
                <th><label for="ybt_shipping_phone">Default Shipping Phone</label></th>
                <td>
                    <input type="text" name="ybt_shipping_phone" id="ybt_shipping_phone" value="<?php echo esc_attr($shipping_phone); ?>" class="regular-text" /><br />
                    <span class="description">User's default contact phone for shipments.</span>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Custom User Fields
     */
    public function save_custom_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (isset($_POST['ybt_mobile_number'])) {
            update_user_meta($user_id, 'ybt_mobile_number', sanitize_text_field($_POST['ybt_mobile_number']));
        }
        
        if (isset($_POST['ybt_shipping_address'])) {
            update_user_meta($user_id, 'ybt_shipping_address', sanitize_textarea_field($_POST['ybt_shipping_address']));
        }
        
        if (isset($_POST['ybt_shipping_phone'])) {
            update_user_meta($user_id, 'ybt_shipping_phone', sanitize_text_field($_POST['ybt_shipping_phone']));
        }
    }

    /**
     * Modify User Search to include mobile number meta
     */
    public function modify_user_search($query) {
        if (!is_admin()) return;

        global $pagenow;
        if ($pagenow !== 'users.php') return;

        $search = $query->query_vars['search'];
        if (empty($search)) return;

        add_action('pre_user_query', array($this, 'extend_user_search'));
    }

    /**
     * Extend SQL to search usermeta for mobile number
     */
    public function extend_user_search($user_query) {
        global $wpdb;

        $search_term = trim($user_query->query_vars['search'], '*');
        if (empty($search_term)) return;

        // Determine if we need to modify the WHERE clause
        if (strpos($user_query->query_where, 'user_login LIKE') !== false) {
            $user_query->query_from .= " LEFT JOIN {$wpdb->usermeta} AS ybt_um ON ({$wpdb->users}.ID = ybt_um.user_id AND ybt_um.meta_key = 'ybt_mobile_number')";

            $mobile_search = $wpdb->prepare("ybt_um.meta_value LIKE %s", '%' . $wpdb->esc_like($search_term) . '%');

            // Inject our mobile search into the OR group
            $user_query->query_where = preg_replace(
                '/\((user_login LIKE .*)\)/U',
                '($1 OR ' . $mobile_search . ')',
                $user_query->query_where
            );
        }

        // Remove action so it doesn't affect other queries in the same request
        remove_action('pre_user_query', array($this, 'extend_user_search'));
    }
}
