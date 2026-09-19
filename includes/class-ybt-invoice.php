<?php
/**
 * YBT Auto-Invoice Generator
 * 
 * Automatically generates invoices when batches are marked as "packed".
 * Hooks into bulk and single status updates to detect batch completion.
 *
 * @package YBT_Packer
 * @version 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class YBT_Invoice {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Hook into status change actions to auto-generate invoices
        add_action('ybt_tracking_updated', array($this, 'on_tracking_updated'), 10, 2);
        add_action('ybt_bulk_operation_complete', array($this, 'on_bulk_operation_complete'));

        // AJAX handlers
        add_action('wp_ajax_ybt_get_invoices', array($this, 'ajax_get_invoices'));
        add_action('wp_ajax_ybt_view_invoice', array($this, 'ajax_view_invoice'));
        add_action('wp_ajax_ybt_generate_invoice_pdf', array($this, 'ajax_generate_invoice_pdf'));
        add_action('wp_ajax_ybt_regenerate_invoice', array($this, 'ajax_regenerate_invoice'));
        add_action('wp_ajax_ybt_delete_invoice', array($this, 'ajax_delete_invoice'));
        add_action('wp_ajax_ybt_email_invoice', array($this, 'ajax_email_invoice'));
        add_action('wp_ajax_ybt_generate_invoice_for_batch', array($this, 'ajax_generate_invoice_for_batch'));
    }

    /**
     * Create the invoices table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_invoices';
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

    /**
     * Generate a unique invoice number
     */
    private function generate_invoice_number() {
        $prefix = get_option('ybt_invoice_prefix', 'INV');
        $date_part = date('Ymd');
        
        global $wpdb;
        $table = $wpdb->prefix . 'ybt_invoices';
        
        // Get the last invoice number for today
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT invoice_number FROM {$table} WHERE invoice_number LIKE %s ORDER BY id DESC LIMIT 1",
            $prefix . '-' . $date_part . '%'
        ));

        if ($last) {
            $parts = explode('-', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . '-' . $date_part . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Hook: When a single tracking item is updated
     */
    public function on_tracking_updated($tracking_id, $new_status = '') {
        if (empty($new_status)) {
            return;
        }

        // Only generate invoice when status changes to 'packed'
        if ($new_status !== 'packed') {
            return;
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
            $tracking_id
        ));

        if (!$item || empty($item->batch_id)) {
            return;
        }

        // Check if all items in this batch are now packed
        $unpacked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s AND status != 'packed' AND status != 'completed'",
            $item->batch_id
        ));

        if ($unpacked == 0) {
            $this->generate_invoice_for_batch($item->batch_id);
        }
    }

    /**
     * Hook: When a bulk operation completes, check for newly packed batches
     */
    public function on_bulk_operation_complete() {
        // Find batches where all items are packed but no invoice exists yet
        global $wpdb;
        $tracking_table = $wpdb->prefix . 'ybt_tracking';
        $invoice_table = $wpdb->prefix . 'ybt_invoices';

        // Check if invoices table exists, create if not
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $invoice_table)) !== $invoice_table) {
            self::create_table();
        }

        $packed_batches = $wpdb->get_col(
            "SELECT DISTINCT batch_id FROM {$tracking_table} 
             WHERE batch_id IS NOT NULL 
             AND status = 'packed'
             AND batch_id NOT IN (SELECT batch_id FROM {$invoice_table} WHERE status != 'cancelled')"
        );

        foreach ($packed_batches as $batch_id) {
            // Verify ALL items in this batch are packed
            $unpacked = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tracking_table} WHERE batch_id = %s AND status NOT IN ('packed', 'completed')",
                $batch_id
            ));

            if ($unpacked == 0) {
                $this->generate_invoice_for_batch($batch_id);
            }
        }
    }

    /**
     * Generate invoice for a specific batch
     */
    public function generate_invoice_for_batch($batch_id) {
        global $wpdb;
        $tracking_table = $wpdb->prefix . 'ybt_tracking';
        $cartons_table = $wpdb->prefix . 'ybt_cartons';
        $invoice_table = $wpdb->prefix . 'ybt_invoices';

        // Check if invoices table exists, create if not
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $invoice_table)) !== $invoice_table) {
            self::create_table();
        }

        // Check if invoice already exists for this batch (non-cancelled)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$invoice_table} WHERE batch_id = %s AND status != 'cancelled'",
            $batch_id
        ));

        if ($existing) {
            return $existing; // Invoice already exists
        }

        // Get all tracking items in this batch
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tracking_table} WHERE batch_id = %s ORDER BY tracking_number ASC",
            $batch_id
        ));

        if (empty($items)) {
            return false;
        }

        // Get carton data for this batch
        $cartons = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$cartons_table} WHERE batch_id = %s ORDER BY carton_number ASC",
            $batch_id
        ));

        // Determine user from first item
        $username = $items[0]->username ?: 'Anonymous';
        $user_id = $items[0]->user_id ?: 0;
        
        // Extract shipping address from items
        $shipping_address = '';
        foreach ($items as $item) {
            if (!empty($item->shipping_address)) {
                $shipping_address = $item->shipping_address;
                break;
            }
        }

        // Calculate totals
        $total_items = count($items);
        $total_cartons = count($cartons);
        $total_weight = 0;
        $subtotal = 0;
        $currency = 'CNY';

        // Build line items array
        $line_items = array();

        // Add tracking items
        foreach ($items as $item) {
            $line_items[] = array(
                'type' => 'tracking',
                'tracking_number' => $item->tracking_number,
                'description' => $item->description ?: 'Package',
                'item_count' => $item->item_count ?: 1,
                'shipping_method' => $item->shipping_method ?: 'sea',
                'weight' => $item->weight_kg ?: 0,
            );
        }

        // Add carton details
        foreach ($cartons as $carton) {
            $total_weight += floatval($carton->weight_kg);
            $subtotal += floatval($carton->payment_amount);
            $currency = $carton->payment_currency ?: 'CNY';

            $line_items[] = array(
                'type' => 'carton',
                'carton_number' => $carton->carton_number,
                'weight_kg' => $carton->weight_kg,
                'amount' => $carton->payment_amount,
                'currency' => $carton->payment_currency,
                'image' => $carton->carton_image,
            );
        }

        // Get exchange rate from settings
        $exchange_rate = floatval(get_option('ybt_exchange_rate', 1));

        // Generate invoice
        $invoice_number = $this->generate_invoice_number();

        $invoice_data = array(
            'invoice_number' => $invoice_number,
            'batch_id' => $batch_id,
            'user_id' => $user_id,
            'username' => $username,
            'total_items' => $total_items,
            'total_cartons' => $total_cartons,
            'total_weight' => $total_weight,
            'subtotal' => $subtotal,
            'currency' => $currency,
            'exchange_rate' => $exchange_rate > 0 ? $exchange_rate : 1,
            'deduction' => 0,
            'deduction_currency' => $currency,
            'grand_total' => $subtotal,
            'status' => 'draft',
            'notes' => '',
            'shipping_address' => $shipping_address,
            'line_items' => wp_json_encode($line_items),
            'generated_by' => get_current_user_id(),
            'date_created' => current_time('mysql'),
        );

        $result = $wpdb->insert($invoice_table, $invoice_data);

        if ($result) {
            $invoice_id = $wpdb->insert_id;

            // Log the event
            if (function_exists('ybt_log_security_event')) {
                ybt_log_security_event('INVOICE_AUTO_GENERATED', array(
                    'invoice_id' => $invoice_id,
                    'invoice_number' => $invoice_number,
                    'batch_id' => $batch_id,
                    'username' => $username,
                    'total' => $subtotal . ' ' . $currency,
                ));
            }

            // Send notification
            $this->send_invoice_notification($invoice_id);

            return $invoice_id;
        }

        return false;
    }

    /**
     * Send notification about new invoice
     */
    private function send_invoice_notification($invoice_id) {
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_invoices WHERE id = %d",
            $invoice_id
        ));

        if (!$invoice) {
            return false;
        }

        // Check if email notifications are enabled
        $notifications_enabled = get_option('ybt_email_notifications_enabled', 1);

        // Try to find user email
        $user_email = '';
        $user = null;

        if ($invoice->user_id) {
            $user = get_user_by('ID', $invoice->user_id);
            if ($user && $user->user_email) {
                $user_email = $user->user_email;
            }
        }

        // Fallback: try to find user by username if no user_id
        if (empty($user_email) && !empty($invoice->username) && $invoice->username !== 'Anonymous') {
            $user = get_user_by('login', $invoice->username);
            if ($user && $user->user_email) {
                $user_email = $user->user_email;
            }
        }

        // Send push notification (FCM)
        if ($invoice->user_id && class_exists('YBT_Notifications')) {
            try {
                YBT_Notifications::send_to_user(
                    $invoice->user_id,
                    '🧾 Invoice Generated',
                    sprintf('Invoice %s has been generated for your batch. Total: %s %s',
                        $invoice->invoice_number,
                        number_format($invoice->grand_total, 2),
                        $invoice->currency
                    ),
                    array(
                        'type' => 'invoice',
                        'invoice_id' => $invoice_id,
                        'invoice_number' => $invoice->invoice_number,
                    )
                );
            } catch (Exception $e) {
                error_log('YBT Invoice: FCM notification failed - ' . $e->getMessage());
            }
        }

        // Send email notification
        if (!empty($user_email) && $notifications_enabled) {
            return $this->send_invoice_email($invoice, $user_email);
        }

        return false;
    }

    /**
     * Send the invoice email to a specific email address
     */
    public function send_invoice_email($invoice, $to_email) {
        if (empty($to_email) || !is_email($to_email)) {
            return array('success' => false, 'error' => 'Invalid email address: ' . $to_email);
        }

        // Get email settings from plugin configuration
        $from_email = get_option('ybt_email_from_email', get_option('admin_email'));
        $from_name = get_option('ybt_email_from_name', get_bloginfo('name'));

        $subject = sprintf('[%s] Invoice %s — Your Packing Invoice', 
            get_bloginfo('name'), 
            $invoice->invoice_number
        );

        // Build email body
        $message = $this->build_invoice_email_html($invoice);

        // Set headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        );

        // Send the email
        $sent = wp_mail($to_email, $subject, $message, $headers);

        if ($sent) {
            // Log success
            if (function_exists('ybt_log_security_event')) {
                ybt_log_security_event('INVOICE_EMAIL_SENT', array(
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'to_email' => $to_email,
                ));
            }
            return array('success' => true, 'email' => $to_email);
        } else {
            error_log('YBT Invoice: wp_mail() failed sending invoice ' . $invoice->invoice_number . ' to ' . $to_email);
            return array('success' => false, 'error' => 'wp_mail() failed. Check your WordPress email/SMTP configuration.');
        }
    }

    /**
     * Build invoice email HTML
     */
    private function build_invoice_email_html($invoice) {
        $line_items = json_decode($invoice->line_items, true) ?: array();
        $carton_items = array_filter($line_items, function($i) { return $i['type'] === 'carton'; });
        $tracking_items = array_filter($line_items, function($i) { return $i['type'] === 'tracking'; });

        // Build printable invoice URL
        $print_url = admin_url('admin-ajax.php?action=ybt_generate_invoice_pdf&nonce=' . wp_create_nonce('ybt_admin_nonce') . '&invoice_id=' . $invoice->id);

        // Payment settings for bank details
        global $wpdb;
        $payment_settings = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ybt_payment_settings WHERE is_active = 1 LIMIT 1");

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f6f9;font-family:'Inter',Arial,sans-serif;">
        <div style="max-width:600px;margin:30px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);overflow:hidden;">
            <!-- Header -->
            <div style="background:linear-gradient(135deg,#228B22,#2ecc71);padding:30px 24px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:22px;">🧾 Invoice Generated</h1>
                <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;"><?php echo esc_html($invoice->invoice_number); ?></p>
            </div>

            <!-- Body -->
            <div style="padding:24px;">
                <p style="color:#333;font-size:14px;line-height:1.6;">
                    Hello <strong><?php echo esc_html($invoice->username); ?></strong>,<br>
                    An invoice has been automatically generated for your packing batch. Please find the details below.
                </p>

                <!-- Summary Card -->
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:20px 0;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <tr>
                            <td style="padding:6px 0;color:#64748b;">Invoice #</td>
                            <td style="padding:6px 0;text-align:right;font-weight:600;color:#1e293b;"><?php echo esc_html($invoice->invoice_number); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;color:#64748b;">Batch ID</td>
                            <td style="padding:6px 0;text-align:right;font-weight:600;color:#1e293b;"><?php echo esc_html($invoice->batch_id); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;color:#64748b;">Items</td>
                            <td style="padding:6px 0;text-align:right;font-weight:600;color:#1e293b;"><?php echo intval($invoice->total_items); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;color:#64748b;">Cartons</td>
                            <td style="padding:6px 0;text-align:right;font-weight:600;color:#1e293b;"><?php echo intval($invoice->total_cartons); ?></td>
                        </tr>
                        <?php if (!empty($invoice->shipping_address)): ?>
                        <tr>
                            <td style="padding:6px 0;color:#64748b;">Shipping Address</td>
                            <td style="padding:6px 0;text-align:right;font-weight:600;color:#1e293b;"><?php echo nl2br(esc_html($invoice->shipping_address)); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="padding:6px 0;color:#64748b;">Total Weight</td>
                            <td style="padding:6px 0;text-align:right;font-weight:600;color:#1e293b;"><?php echo number_format($invoice->total_weight, 2); ?> KG</td>
                        </tr>
                        <tr style="border-top:2px solid #e2e8f0;">
                            <td style="padding:12px 0 6px;color:#1e293b;font-weight:700;font-size:15px;">Total Due</td>
                            <td style="padding:12px 0 6px;text-align:right;font-weight:700;font-size:15px;color:#228B22;">
                                <?php echo esc_html($invoice->currency); ?> <?php echo number_format($invoice->grand_total, 2); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Tracking Numbers -->
                <div style="margin:20px 0;">
                    <p style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:13px;">📦 Tracking Numbers:</p>
                    <?php foreach ($tracking_items as $ti): ?>
                        <span style="display:inline-block;background:#e8f5e9;color:#2e7d32;padding:4px 10px;border-radius:4px;font-size:12px;margin:2px 4px 2px 0;font-weight:500;">
                            <?php echo esc_html($ti['tracking_number']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <!-- Carton Breakdown -->
                <?php if (!empty($carton_items)): ?>
                <div style="margin:20px 0;">
                    <p style="font-weight:600;color:#1e293b;margin-bottom:8px;font-size:13px;">📋 Carton Details:</p>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <tr style="background:#f1f5f9;">
                            <td style="padding:8px;font-weight:600;color:#64748b;">Carton</td>
                            <td style="padding:8px;font-weight:600;color:#64748b;text-align:right;">Weight</td>
                            <td style="padding:8px;font-weight:600;color:#64748b;text-align:right;">Amount</td>
                        </tr>
                        <?php foreach ($carton_items as $ci): ?>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;">Carton <?php echo intval($ci['carton_number']); ?></td>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;"><?php echo number_format(floatval($ci['weight_kg']), 2); ?> KG</td>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:600;"><?php echo esc_html($ci['currency'] ?: 'CNY'); ?> <?php echo number_format(floatval($ci['amount']), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Payment Information -->
                <?php if ($payment_settings): ?>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin:20px 0;">
                    <p style="font-weight:700;color:#166534;margin:0 0 10px;font-size:13px;">💳 Payment Information</p>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <tr>
                            <td style="padding:4px 0;color:#15803d;font-weight:600;">Bank:</td>
                            <td style="padding:4px 0;color:#15803d;"><?php echo esc_html($payment_settings->bank_name); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0;color:#15803d;font-weight:600;">Account Name:</td>
                            <td style="padding:4px 0;color:#15803d;"><?php echo esc_html($payment_settings->account_name); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0;color:#15803d;font-weight:600;">Account Number:</td>
                            <td style="padding:4px 0;color:#15803d;"><?php echo esc_html($payment_settings->account_number); ?></td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <p style="color:#64748b;font-size:12px;margin-top:24px;">
                    Generated on <?php echo date('F j, Y \a\t g:i A', strtotime($invoice->date_created)); ?>
                </p>
            </div>

            <!-- Footer -->
            <div style="background:#f8fafc;padding:16px 24px;text-align:center;border-top:1px solid #e2e8f0;">
                <p style="color:#94a3b8;font-size:11px;margin:0;">
                    <?php echo esc_html(get_bloginfo('name')); ?> — Automated Invoice System
                </p>
            </div>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Get invoices list
     */
    public function ajax_get_invoices() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ybt_invoices';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            wp_send_json_success(array('invoices' => array(), 'total' => 0));
            return;
        }

        $page = intval($_POST['page'] ?? 1);
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        $status_filter = sanitize_text_field($_POST['status'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');

        $where = array();
        $values = array();

        if ($status_filter) {
            $where[] = "status = %s";
            $values[] = $status_filter;
        }

        if ($search) {
            $where[] = "(invoice_number LIKE %s OR username LIKE %s OR batch_id LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $count_query = "SELECT COUNT(*) FROM {$table} {$where_clause}";
        $total = !empty($values) ? $wpdb->get_var($wpdb->prepare($count_query, $values)) : $wpdb->get_var($count_query);

        $values[] = $per_page;
        $values[] = $offset;
        $query = "SELECT * FROM {$table} {$where_clause} ORDER BY date_created DESC LIMIT %d OFFSET %d";
        $invoices = $wpdb->get_results($wpdb->prepare($query, $values));

        wp_send_json_success(array(
            'invoices' => $invoices,
            'total' => intval($total),
            'pages' => ceil($total / $per_page),
        ));
    }

    /**
     * AJAX: View single invoice
     */
    public function ajax_view_invoice() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        if (!$invoice_id) {
            wp_send_json_error('Invalid invoice ID');
        }

        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_invoices WHERE id = %d",
            $invoice_id
        ));

        if (!$invoice) {
            wp_send_json_error('Invoice not found');
        }

        $invoice->line_items = json_decode($invoice->line_items, true) ?: array();
        wp_send_json_success($invoice);
    }

    /**
     * AJAX: Generate printable invoice HTML
     */
    public function ajax_generate_invoice_pdf() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ybt_admin_nonce')) {
            wp_die('Invalid security token');
        }
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $invoice_id = intval($_GET['invoice_id'] ?? 0);
        if (!$invoice_id) {
            wp_die('Invalid invoice ID');
        }

        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_invoices WHERE id = %d",
            $invoice_id
        ));

        if (!$invoice) {
            wp_die('Invoice not found');
        }

        $line_items = json_decode($invoice->line_items, true) ?: array();
        $carton_items = array_filter($line_items, function($i) { return $i['type'] === 'carton'; });
        $tracking_items = array_filter($line_items, function($i) { return $i['type'] === 'tracking'; });

        // Payment settings
        $payment_settings = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ybt_payment_settings WHERE is_active = 1 LIMIT 1");

        include YBT_PLUGIN_PATH . 'templates/admin-invoice-print.php';
        exit;
    }

    /**
     * AJAX: Regenerate invoice
     */
    public function ajax_regenerate_invoice() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        if (!$invoice_id) {
            wp_send_json_error('Invalid invoice ID');
        }

        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_invoices WHERE id = %d",
            $invoice_id
        ));

        if (!$invoice) {
            wp_send_json_error('Invoice not found');
        }

        // Cancel old invoice
        $wpdb->update(
            $wpdb->prefix . 'ybt_invoices',
            array('status' => 'cancelled'),
            array('id' => $invoice_id)
        );

        // Generate new one
        $new_id = $this->generate_invoice_for_batch($invoice->batch_id);

        if ($new_id) {
            wp_send_json_success(array('message' => 'Invoice regenerated', 'new_invoice_id' => $new_id));
        } else {
            wp_send_json_error('Failed to regenerate invoice');
        }
    }

    /**
     * AJAX: Delete invoice
     */
    public function ajax_delete_invoice() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        if (!$invoice_id) {
            wp_send_json_error('Invalid invoice ID');
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ybt_invoices',
            array('status' => 'cancelled'),
            array('id' => $invoice_id)
        );

        wp_send_json_success('Invoice cancelled');
    }

    /**
     * AJAX: Email invoice to user
     */
    public function ajax_email_invoice() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        if (!$invoice_id) {
            wp_send_json_error('Invalid invoice ID');
        }

        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_invoices WHERE id = %d",
            $invoice_id
        ));

        if (!$invoice) {
            wp_send_json_error('Invoice not found');
        }

        // Find the user's email
        $user_email = '';

        if ($invoice->user_id) {
            $user = get_user_by('ID', $invoice->user_id);
            if ($user && $user->user_email) {
                $user_email = $user->user_email;
            }
        }

        // Fallback: look up by username
        if (empty($user_email) && !empty($invoice->username) && $invoice->username !== 'Anonymous') {
            $user = get_user_by('login', $invoice->username);
            if ($user && $user->user_email) {
                $user_email = $user->user_email;
            }
        }

        if (empty($user_email)) {
            wp_send_json_error('No email found for user "' . esc_html($invoice->username) . '". Please ensure the user has an email address in their profile.');
        }

        // Send the email
        $result = $this->send_invoice_email($invoice, $user_email);

        if ($result['success']) {
            // Update status to sent
            $wpdb->update(
                $wpdb->prefix . 'ybt_invoices',
                array('status' => 'sent', 'date_sent' => current_time('mysql')),
                array('id' => $invoice_id)
            );

            wp_send_json_success('Invoice ' . $invoice->invoice_number . ' emailed to ' . $user_email);
        } else {
            wp_send_json_error('Failed to send email: ' . $result['error']);
        }
    }

    /**
     * AJAX: Manually generate invoice for a batch
     */
    public function ajax_generate_invoice_for_batch() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $batch_id = sanitize_text_field($_POST['batch_id'] ?? '');
        if (empty($batch_id)) {
            wp_send_json_error('Batch ID is required');
        }

        $invoice_id = $this->generate_invoice_for_batch($batch_id);

        if ($invoice_id) {
            global $wpdb;
            $invoice = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ybt_invoices WHERE id = %d",
                $invoice_id
            ));

            wp_send_json_success(array(
                'message' => 'Invoice generated successfully',
                'invoice' => $invoice,
            ));
        } else {
            wp_send_json_error('Failed to generate invoice. The batch may already have an active invoice.');
        }
    }
}

// Initialize
add_action('plugins_loaded', function() {
    YBT_Invoice::instance();
}, 25);
