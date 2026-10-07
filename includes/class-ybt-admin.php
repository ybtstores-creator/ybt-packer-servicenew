<?php
if (!defined('ABSPATH')) {
    exit;
}

// Advanced-search AJAX endpoint (page templates render too late to register it)
require_once YBT_PLUGIN_PATH . 'includes/ajax-advanced-search-legacy.php';

class YBT_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Register Admin Menus - Collapsed into logical groups
     *
     * Previously all 25+ items were flat under one menu.
     * Now grouped into separate top-level menus by function:
     *   YBT Packing Service (Dashboard)
     *   📦 Parcels  (tracking + packing operations)
     *   📊 Reports  (analytics & insights)
     *   ⚙️ Settings (configuration & maintenance)
     *   💬 Support  (chat & messages)
     *   🧾 Invoices
     */
    public function register_menus() {
        // =====================================================================
        // 1. MAIN MENU — DASHBOARD
        // =====================================================================
        add_menu_page(
            'YBT Packing Service — Dashboard',
            'YBT Packing Service',
            'manage_options',
            'ybt-packer',
            array($this, 'render_dashboard'),
            'dashicons-box',
            6
        );

        // =====================================================================
        // 2. 📦 PARCELS — Tracking & Packing Operations
        // =====================================================================
        add_menu_page(
            'Parcels — YBT Packing Service',
            '📦 Parcels',
            'manage_options',
            'ybt-parcels',
            array($this, 'render_add_tracking'),
            'dashicons-archive',
            10
        );

        add_submenu_page(
            'ybt-parcels',
            'Add Tracking',
            '➕ Add Tracking',
            'manage_options',
            'ybt-add-tracking',
            array($this, 'render_add_tracking')
        );

        add_submenu_page(
            'ybt-parcels',
            'Manage Tracking',
            '📋 Manage Tracking',
            'manage_options',
            'ybt-manage-tracking',
            array($this, 'render_manage_tracking')
        );

        add_submenu_page(
            'ybt-parcels',
            'Process Packing',
            '📦 Process Packing',
            'manage_options',
            'ybt-process-packing',
            array($this, 'render_process_packing')
        );

        add_submenu_page(
            'ybt-parcels',
            'Manage Cartons',
            '📦 Cartons',
            'manage_options',
            'ybt-manage-cartons',
            array($this, 'render_manage_cartons')
        );

        add_submenu_page(
            'ybt-parcels',
            'Barcode Scanner',
            '📱 Scanner',
            'manage_options',
            'ybt-barcode-scanner',
            array($this, 'render_barcode_scanner')
        );

        add_submenu_page(
            'ybt-parcels',
            'Bulk Import',
            '📥 Bulk Import',
            'manage_options',
            'ybt-bulk-import',
            array($this, 'render_bulk_import')
        );

        add_submenu_page(
            'ybt-parcels',
            'Advanced Search',
            '🔍 Search',
            'manage_options',
            'ybt-advanced-search',
            array($this, 'render_advanced_search')
        );

        // =====================================================================
        // 3. 📊 REPORTS — Analytics & Insights
        // =====================================================================
        add_menu_page(
            'Reports — YBT Packing Service',
            '📊 Reports',
            'manage_options',
            'ybt-reports',
            array($this, 'render_analytics'),
            'dashicons-chart-area',
            12
        );

        add_submenu_page(
            'ybt-reports',
            'Analytics',
            '📊 Analytics',
            'manage_options',
            'ybt-analytics',
            array($this, 'render_analytics')
        );

        add_submenu_page(
            'ybt-reports',
            'Smart Dashboard',
            '🎯 Smart Dashboard',
            'manage_options',
            'ybt-smart-dashboard',
            array($this, 'render_smart_dashboard')
        );

        add_submenu_page(
            'ybt-reports',
            'User Summary',
            '👥 User Summary',
            'manage_options',
            'ybt-user-summary',
            array($this, 'render_user_summary')
        );

        add_submenu_page(
            'ybt-reports',
            'Activity Log',
            '📝 Activity Log',
            'manage_options',
            'ybt-activity-log',
            array($this, 'render_activity_log')
        );

        // =====================================================================
        // 4. ⚙️ SETTINGS — Configuration & Maintenance
        // =====================================================================
        add_menu_page(
            'Settings — YBT Packing Service',
            '⚙️ Settings',
            'manage_options',
            'ybt-settings-group',
            array($this, 'render_settings_page'),
            'dashicons-admin-settings',
            14
        );

        add_submenu_page(
            'ybt-settings-group',
            'General Settings',
            '⚙️ General',
            'manage_options',
            'ybt-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Push Notifications (FCM)',
            '🔔 FCM Push Notifs',
            'manage_options',
            'ybt-fcm-settings',
            array($this, 'render_fcm_settings')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Warehouse Settings',
            '🏭 Warehouse',
            'manage_options',
            'ybt-warehouse-settings',
            array($this, 'render_warehouse_settings')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Payment Settings',
            '💳 Payments',
            'manage_options',
            'ybt-payment-settings',
            array($this, 'render_payment_settings')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Payment Evidence',
            '💰 Evidence',
            'manage_options',
            'ybt-payment-evidence',
            array($this, 'render_payment_evidence')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Email Settings',
            '✉️ Email',
            'manage_options',
            'ybt-email-settings',
            array($this, 'render_email_settings')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Security',
            '🔒 Security',
            'manage_options',
            'ybt-security',
            array($this, 'render_security_page')
        );

        add_submenu_page(
            'ybt-settings-group',
            'System Tools',
            '🛠️ System Tools',
            'manage_options',
            'ybt-tools',
            array($this, 'render_tools_page')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Site Export',
            '📤 Export',
            'manage_options',
            'ybt-site-export',
            array($this, 'render_site_export')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Site Import',
            '📥 Import',
            'manage_options',
            'ybt-site-import',
            array($this, 'render_site_import')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Reset Data',
            '⚠️ Reset',
            'manage_options',
            'ybt-reset-data',
            array($this, 'render_reset_data')
        );

        add_submenu_page(
            'ybt-settings-group',
            'Data Management',
            '🗑️ Data Cleanup',
            'manage_options',
            'ybt-data-cleanup',
            array($this, 'render_data_cleanup')
        );

        add_submenu_page(
            'ybt-settings-group',
            'App Updates',
            '📱 App Updates',
            'manage_options',
            'ybt-app-updates',
            array($this, 'render_app_updates')
        );

        // =====================================================================
        // 5. 💬 SUPPORT — Chat & Messages
        // =====================================================================
        add_menu_page(
            'Support — YBT Packing Service',
            '💬 Support',
            'manage_options',
            'ybt-support',
            array($this, 'render_chat_page'),
            'dashicons-format-chat',
            16
        );

        add_submenu_page(
            'ybt-support',
            'Chat',
            '💬 Chat',
            'manage_options',
            'ybt-chat',
            array($this, 'render_chat_page')
        );

        add_submenu_page(
            'ybt-support',
            'Send Notification',
            '📣 Send Notification',
            'manage_options',
            'ybt-send-notification',
            array($this, 'render_send_notification')
        );

        add_submenu_page(
            'ybt-support',
            'Messages',
            '📧 Messages',
            'manage_options',
            'ybt-messages',
            array($this, 'render_messages_page')
        );

        // =====================================================================
        // 6. 🧾 INVOICES
        // =====================================================================
        add_menu_page(
            'Invoices — YBT Packing Service',
            '🧾 Invoices',
            'manage_options',
            'ybt-invoices',
            array($this, 'render_invoices'),
            'dashicons-media-spreadsheet',
            18
        );
    }


    /**
     * Enqueue Admin Styles and Scripts
     */
    public function enqueue_admin_assets($hook) {
        // Only load on YBT admin pages
        // Main menu hook: toplevel_page_ybt-packer
        // Submenu hooks: ybt-packer_page_{submenu_slug}
        $is_ybt_page = (
            strpos($hook, 'ybt-') !== false ||
            strpos($hook, 'toplevel_page_ybt') !== false ||
            strpos($hook, 'ybt-packer_page') !== false
        );

        if (!$is_ybt_page) {
            return;
        }

        // Additional security: Check user capability
        if (!current_user_can('manage_options')) {
            return;
        }

        // 1. Shared Frameworks/Fonts (CDNs)
        wp_enqueue_style('ybt-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', array(), '5.3.2');
        wp_enqueue_script('ybt-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.2', true);
        wp_enqueue_style('ybt-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css', array(), '6.4.2');
        wp_enqueue_style('ybt-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', array(), null);

        // 2. Core Plugin Assets
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_enqueue_style('ybt-admin-style', YBT_PLUGIN_URL . 'assets/admin-style.css', array(), YBT_VERSION);
            wp_enqueue_style('ybt-admin-enhanced', YBT_PLUGIN_URL . 'assets/css/admin-dashboard-enhanced.css', array('ybt-admin-style'), YBT_VERSION);
            wp_enqueue_script('ybt-components', YBT_PLUGIN_URL . 'assets/js/ybt-components.js', array('jquery'), YBT_VERSION, true);
            wp_enqueue_script('ybt-admin-script', YBT_PLUGIN_URL . 'assets/admin-script.js', array('jquery', 'ybt-components'), YBT_VERSION, true);
        } else {
            // PRODUCTION: Use Combined & Minified Assets
            wp_enqueue_style('ybt-admin-combined', YBT_PLUGIN_URL . 'assets/ybt-admin-combined.min.css', array('ybt-bootstrap'), YBT_VERSION);
            wp_enqueue_script('ybt-admin-combined', YBT_PLUGIN_URL . 'assets/ybt-admin-combined.min.js', array('jquery'), YBT_VERSION, true);
        }

        // Modern Admin UI (new v2.0)
        wp_enqueue_style('ybt-admin-modern', YBT_PLUGIN_URL . 'assets/css/admin-modern.css', array('ybt-bootstrap'), YBT_VERSION);
        wp_enqueue_script('ybt-admin-modern', YBT_PLUGIN_URL . 'assets/js/admin-modern.js', array('jquery'), YBT_VERSION, true);

        // Consignment-note design system (shared tokens & atoms for the redesigned admin pages)
        wp_enqueue_style('ybt-consignment', YBT_PLUGIN_URL . 'assets/css/ybt-consignment.css', array('ybt-admin-modern'), YBT_VERSION . '-flat-v1');

        // Page-specific assets for Process Packing
        if (strpos($hook, 'ybt-process-packing') !== false) {
            wp_enqueue_style('ybt-admin-packing', YBT_PLUGIN_URL . 'assets/css/admin-packing.css', array(), YBT_VERSION);
            wp_enqueue_script('ybt-admin-packing', YBT_PLUGIN_URL . 'assets/js/admin-packing.js', array('jquery'), YBT_VERSION, true);
        }

        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');

        // Enhanced admin localization with version and strings
        $script_handle = (defined('WP_DEBUG') && WP_DEBUG) ? 'ybt-admin-script' : 'ybt-admin-combined';
        wp_localize_script($script_handle, 'ybt_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ybt_admin_nonce'),
            'version'  => YBT_VERSION,
            'strings'  => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'ybt-packer'),
                'processing'     => __('Processing...', 'ybt-packer'),
                'error'          => __('An error occurred. Please try again.', 'ybt-packer'),
                'success'        => __('Operation completed successfully.', 'ybt-packer'),
                'confirm_mark_packed' => __('Mark as Packed?', 'ybt-packer'),
                'saving'         => __('Saving...', 'ybt-packer'),
                'updating'       => __('Updating...', 'ybt-packer'),
                'uploading'      => __('Uploading...', 'ybt-packer'),
                'select_items_first' => __('Select items first.', 'ybt-packer'),
                'confirm_bulk_complete' => __('Mark checked items as Completed?', 'ybt-packer'),
                'no_items_selected' => __('No items selected.', 'ybt-packer'),
                'confirm_clear_selections' => __('Clear all selections?', 'ybt-packer'),
                'loading'        => __('Loading...', 'ybt-packer'),
                'carton'         => __('Carton', 'ybt-packer'),
                'weight_kg'      => __('Weight (KG)', 'ybt-packer'),
                'amount'         => __('Amount', 'ybt-packer'),
                'currency'       => __('Currency', 'ybt-packer'),
                'images'         => __('Images', 'ybt-packer'),
                'no_images'      => __('No images.', 'ybt-packer'),
                'download'       => __('Download', 'ybt-packer'),
                'cost'           => __('Cost', 'ybt-packer')
            )
        ));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[YBT Admin] Assets loaded successfully for page: ' . $hook);
        }
    }

    /**
     * Load template with error handling
     */
    private function load_template($template_file, $fallback_message = '') {
        $template_path = YBT_PLUGIN_PATH . 'templates/' . $template_file;

        if (file_exists($template_path)) {
            // Wrap content with modern UI container
            echo '<div class="ybt-admin-wrapper">';
            include $template_path;
            echo '</div>';
            return true;
        }

        // Show error
        $message = $fallback_message ?: 'Template not found: ' . $template_file;
        echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html($message) . '</p></div></div>';
        return false;
    }

    /**
     * Render page header with modern UI
     */
    public static function render_page_header($title, $icon = 'fa-cube', $actions = '') {
        ?>
        <div class="ybt-page-header ybt-d-flex ybt-items-center ybt-justify-between ybt-mb-6">
            <div class="ybt-d-flex ybt-items-center ybt-gap-4">
                <div class="ybt-page-icon">
                    <i class="fas <?php echo esc_attr($icon); ?>"></i>
                </div>
                <div>
                    <h1 class="ybt-page-title"><?php echo esc_html($title); ?></h1>
                </div>
            </div>
            <?php if ($actions): ?>
            <div class="ybt-page-actions">
                <?php echo $actions; ?>
            </div>
            <?php endif; ?>
        </div>
        <style>
        .ybt-page-header {
            background: white;
            padding: 20px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .ybt-page-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        .ybt-page-title {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: #111827;
        }
        .ybt-page-actions {
            display: flex;
            gap: 12px;
        }
        </style>
        <?php
    }

    /**
     * Render stat card (for dashboard)
     */
    public static function render_stat_card($icon, $label, $value, $trend = '', $color = 'primary') {
        $colors = array(
            'primary' => array('bg' => '#EFF6FF', 'icon' => '#3B82F6'),
            'success' => array('bg' => '#D1FAE5', 'icon' => '#10B981'),
            'warning' => array('bg' => '#FEF3C7', 'icon' => '#F59E0B'),
            'danger' => array('bg' => '#FEE2E2', 'icon' => '#EF4444'),
            'info' => array('bg' => '#CFFAFE', 'icon' => '#06B6D4'),
        );
        $c = $colors[$color] ?: $colors['primary'];
        ?>
        <div class="ybt-stat-card">
            <div class="ybt-stat-icon" style="background: <?php echo $c['bg']; ?>; color: <?php echo $c['icon']; ?>;">
                <i class="fas <?php echo esc_attr($icon); ?>"></i>
            </div>
            <div class="ybt-stat-content">
                <div class="ybt-stat-label"><?php echo esc_html($label); ?></div>
                <div class="ybt-stat-value"><?php echo esc_html($value); ?></div>
                <?php if ($trend): ?>
                <div class="ybt-stat-trend <?php echo strpos($trend, '+') !== false ? 'up' : 'down'; ?>">
                    <?php echo esc_html($trend); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render modern card
     */
    public static function render_card($title, $icon = '', $content = '', $footer = '') {
        ?>
        <div class="ybt-card">
            <?php if ($title): ?>
            <div class="ybt-card-header">
                <h3 class="ybt-card-title">
                    <?php if ($icon): ?><i class="fas <?php echo esc_attr($icon); ?>"></i><?php endif; ?>
                    <?php echo esc_html($title); ?>
                </h3>
            </div>
            <?php endif; ?>
            <div class="ybt-card-body">
                <?php echo $content; ?>
            </div>
            <?php if ($footer): ?>
            <div class="ybt-card-footer">
                <?php echo $footer; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Methods
     */
    public function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-dashboard.php');
    }

    public function render_add_tracking() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-add-tracking.php');
    }

    public function render_manage_tracking() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-manage-tracking.php');
    }

    public function render_process_packing() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-process-packing.php');
    }

    public function render_manage_cartons() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-manage-cartons.php', 'Manage Cartons template not found.');
    }

    public function render_chat_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-chat.php');
    }

    public function render_messages_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-messages.php');
    }

    public function render_send_notification() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-send-notification.php');
    }

    public function render_warehouse_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-warehouse-settings.php');
    }

    public function render_payment_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-payment-settings.php');
    }

    public function render_tools_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-tools.php');
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }

        // Use standard template loading like other pages
        $this->load_template('admin-settings.php');
    }

    public function render_fcm_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-fcm-settings.php');
    }

    public function render_user_summary() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-user-summary.php');
    }

    public function render_payment_evidence() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-payment-evidence.php');
    }

    public function render_activity_log() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-activity-log.php');
    }

    public function render_analytics() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-analytics.php');
    }

    public function render_security_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-security.php');
    }

    public function render_bulk_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-bulk-import.php');
    }

    public function render_site_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-site-export.php');
    }

    public function render_site_import() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-site-import.php');
    }

    public function render_email_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-email-settings.php');
    }

    public function render_reset_data() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-reset-data.php');
    }

    public function render_data_cleanup() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-data-cleanup.php');
    }

    public function render_advanced_search() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-advanced-search.php');
    }

    public function render_barcode_scanner() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-barcode-scanner.php');
    }

    public function render_smart_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-smart-dashboard.php');
    }

    public function render_app_updates() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-app-updates.php', 'App Updates template not found.');
    }

    public function render_invoices() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ybt-packer'));
        }
        $this->load_template('admin-invoices.php', 'Invoices template not found.');
    }
}
