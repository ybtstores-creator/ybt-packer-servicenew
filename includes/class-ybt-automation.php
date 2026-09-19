<?php
/**
 * YBT Automation & Notifications Engine
 * Handles status emails, daily reports, and background maintenance.
 *
 * @package YBT_Packer
 * @version 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YBT_Automation {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // 1. Status Change Emails
        add_action('ybt_tracking_updated', array($this, 'on_tracking_updated'), 10, 2);

        // 2. Scheduled Tasks
        add_action('ybt_daily_automation', array($this, 'run_daily_tasks'));
        add_action('ybt_weekly_maintenance', array($this, 'run_weekly_tasks'));

        // Register schedules on init
        add_action('init', array($this, 'register_schedules'));
    }

    /**
     * Register WP-Cron schedules
     */
    public function register_schedules() {
        if (!wp_next_scheduled('ybt_daily_automation')) {
            wp_schedule_event(time(), 'daily', 'ybt_daily_automation');
        }
        if (!wp_next_scheduled('ybt_weekly_maintenance')) {
            wp_schedule_event(time(), 'weekly', 'ybt_weekly_maintenance');
        }
    }

    /**
     * Hook: When tracking status changes
     * @param int|string $tracking_id Tracking ID or empty string
     * @param string $new_status New status value
     */
    public function on_tracking_updated($tracking_id = '', $new_status = '') {
        // Ensure notifications are enabled
        if (!get_option('ybt_email_notifications_enabled', 1)) {
            return;
        }

        // Validate tracking_id
        if (empty($tracking_id) || !is_numeric($tracking_id)) {
            return;
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
            $tracking_id
        ));

        if (!$item) return;

        // Use provided status or fallback to item status
        if (empty($new_status)) {
            $new_status = $item->status;
        }

        // Find user email
        $user_email = '';
        if ($item->user_id) {
            $user = get_user_by('ID', $item->user_id);
            if ($user && $user->user_email) {
                $user_email = $user->user_email;
            }
        }

        // Send email if we have an address
        if ($user_email) {
            $this->send_status_update_email($item, $new_status, $user_email);
        }
    }

    /**
     * Send professional HTML status update email
     */
    private function send_status_update_email($item, $status, $to_email) {
        $from_email = get_option('ybt_email_from_email', get_option('admin_email'));
        $from_name = get_option('ybt_email_from_name', get_bloginfo('name'));

        $status_label = ucfirst(str_replace('_', ' ', $status));
        $subject = sprintf('[%s] Status Update: %s for %s', get_bloginfo('name'), $status_label, $item->tracking_number);

        // Customize message based on status
        $status_icon = '📦';
        $message = "Your package's status has been updated.";
        
        switch ($status) {
            case 'received':
                $status_icon = '📥';
                $message = "Your package has arrived at our warehouse and is now being processed.";
                break;
            case 'packing':
                $status_icon = '📦';
                $message = "We are currently packing your items into cartons.";
                break;
            case 'packed':
                $status_icon = '✅';
                $message = "Packing is complete! An invoice will be generated shortly for your batch.";
                break;
            case 'completed':
                $status_icon = '🚛';
                $message = "Your package has Been completed/shipped. Thank you for using our service!";
                break;
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <div style="background:#228B22;padding:20px;text-align:center;color:#fff;">
                    <h1 style="margin:0;font-size:24px;"><?php echo $status_icon; ?> Status Updated</h1>
                </div>
                <div style="padding:30px;">
                    <p style="font-size:16px;color:#333;">Hello <strong><?php echo esc_html($item->username); ?></strong>,</p>
                    <p style="font-size:15px;color:#555;line-height:1.6;"><?php echo $message; ?></p>
                    
                    <div style="background:#f8f9fa;border-radius:6px;padding:20px;margin:20px 0;border-left:4px solid #228B22;">
                        <table style="width:100%;font-size:14px;">
                            <tr>
                                <td style="padding:5px 0;color:#888;">Tracking Number:</td>
                                <td style="padding:5px 0;font-weight:bold;text-align:right;"><?php echo esc_html($item->tracking_number); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:5px 0;color:#888;">New Status:</td>
                                <td style="padding:5px 0;font-weight:bold;text-align:right;color:#228B22;"><?php echo esc_html($status_label); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:5px 0;color:#888;">Items Count:</td>
                                <td style="padding:5px 0;font-weight:bold;text-align:right;"><?php echo intval($item->item_count); ?></td>
                            </tr>
                        </table>
                    </div>

                    <div style="text-align:center;margin-top:30px;">
                        <a href="<?php echo home_url('/dashboard/'); ?>" style="background:#228B22;color:#fff;padding:12px 25px;text-decoration:none;border-radius:4px;font-weight:bold;display:inline-block;">View in Dashboard</a>
                    </div>
                </div>
                <div style="background:#f8f9fa;padding:15px;text-align:center;color:#999;font-size:12px;border-top:1px solid #eee;">
                    © <?php echo date('Y'); ?> <?php echo esc_html(get_bloginfo('name')); ?>. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email)
        );

        wp_mail($to_email, $subject, $html, $headers);
    }

    /**
     * Run daily automation tasks
     */
    public function run_daily_tasks() {
        $this->send_admin_daily_report();
        $this->escalate_stale_parcels();
    }

    /**
     * Run weekly maintenance
     */
    public function run_weekly_tasks() {
        $this->optimize_database();
    }

    /**
     * Compile and send a daily summary to the admin
     */
    private function send_admin_daily_report() {
        global $wpdb;
        $admin_email = get_option('admin_email');
        
        $today = date('Y-m-d');
        
        // Stats
        $received_today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE DATE(date_created) = %s", $today));
        $packed_today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE status = 'packed' AND DATE(updated_at) = %s", $today));
        
        // Pipeline summary
        $summary = $wpdb->get_results("SELECT status, COUNT(*) as count FROM {$wpdb->prefix}ybt_tracking GROUP BY status");

        $subject = "📊 YBT Warehouse Daily Report — " . date('F j, Y');
        
        ob_start();
        ?>
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
            <h2>Daily Pipeline Summary</h2>
            <div style="display:flex;gap:20px;margin-bottom:20px;">
                <div style="flex:1;background:#e8f5e9;padding:15px;border-radius:8px;">
                    <div style="font-size:12px;color:#2e7d32;">RECEIVED TODAY</div>
                    <div style="font-size:24px;font-weight:bold;"><?php echo intval($received_today); ?></div>
                </div>
                <div style="flex:1;background:#e3f2fd;padding:15px;border-radius:8px;">
                    <div style="font-size:12px;color:#1565c0;">PACKED TODAY</div>
                    <div style="font-size:24px;font-weight:bold;"><?php echo intval($packed_today); ?></div>
                </div>
            </div>

            <h3>Total Inventory by Status</h3>
            <table style="width:100%;border-collapse:collapse;">
                <?php foreach ($summary as $row): ?>
                <tr>
                    <td style="padding:10px;border-bottom:1px solid #eee;"><?php echo ucfirst(str_replace('_', ' ', $row->status)); ?></td>
                    <td style="padding:10px;border-bottom:1px solid #eee;text-align:right;font-weight:bold;"><?php echo intval($row->count); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <p style="margin-top:30px;font-size:12px;color:#999;">This is an automated report from your YBT Packer Service.</p>
        </div>
        <?php
        $msg = ob_get_clean();
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $msg, $headers);
    }

    /**
     * Flag parcels sitting in "received" for too long
     */
    private function escalate_stale_parcels() {
        global $wpdb;
        $stale_days = 7;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$stale_days days"));

        $stale_items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tracking_number, username FROM {$wpdb->prefix}ybt_tracking 
             WHERE status = 'received' AND updated_at < %s",
            $cutoff_date
        ));

        if (!empty($stale_items)) {
            $admin_email = get_option('admin_email');
            $count = count($stale_items);
            
            $msg = "The following $count parcels have been in 'received' status for more than $stale_days days:\n\n";
            foreach ($stale_items as $item) {
                $msg .= "- {$item->tracking_number} ({$item->username})\n";
            }
            $msg .= "\nPlease check these items to ensure workflow isn't bottlenecked.";

            wp_mail($admin_email, "⚠️ Stale Parcels Alert ($count items)", $msg);
        }
    }

    /**
     * Optimize database tables
     */
    private function optimize_database() {
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'ybt_tracking',
            $wpdb->prefix . 'ybt_cartons',
            $wpdb->prefix . 'ybt_invoices',
            $wpdb->prefix . 'ybt_security_log'
        );

        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
    }
}

// Initialized via main plugin file
