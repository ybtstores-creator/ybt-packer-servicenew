<?php
/**
 * YBT Packer Service — AI System Operator Assistant
 * Automated Operations, System Health Diagnostics, and AI Automation Suite
 *
 * @package YBT_Packer
 * @version 3.2
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_AI_Operator {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Menu removed — AI features are now in Data Management page
        // add_action('admin_menu', array($this, 'register_ai_menu'), 25);
        add_action('wp_ajax_ybt_ai_run_diagnostics', array($this, 'ajax_run_diagnostics'));
        add_action('wp_ajax_ybt_ai_auto_repair', array($this, 'ajax_auto_repair'));
        add_action('wp_ajax_ybt_ai_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_ybt_ai_preview_prune_images', array($this, 'ajax_preview_prune_images'));
        add_action('wp_ajax_ybt_ai_prune_old_images', array($this, 'ajax_prune_old_images'));
    }

    /**
     * Register AI Operator Menu in Admin
     */
    public function register_ai_menu() {
        add_menu_page(
            '🤖 AI System Operator — YBT',
            '🤖 AI Operator',
            'manage_options',
            'ybt-ai-operator',
            array($this, 'render_ai_console'),
            'dashicons-robot',
            7
        );
    }

    /**
     * Perform full system diagnostic scan
     */
    public function run_full_diagnostics() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ybt_tracking';

        // 1. Total & Status Distribution
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as total FROM {$table_name} GROUP BY status",
            OBJECT_K
        );

        // 2. Unclaimed / Anonymous Parcels
        $unclaimed_count = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE user_id IS NULL OR user_id = 0 OR username = 'anonymous'"
        ));

        // 3. Stale Warehouse Parcels (> 14 days in 'received' without packing request)
        $stale_parcels = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'received' AND date_received < DATE_SUB(NOW(), INTERVAL 14 DAY)"
        ));

        // 4. Missing Photos Check
        $missing_photos = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status IN ('received', 'packed', 'completed') AND (images IS NULL OR images = '')"
        ));

        // 5. Images Older Than 5 Months Check
        $old_images_preview = $this->prune_images_older_than_months(5, true);

        // 6. Database Health Check
        $db_size = $wpdb->get_var(
            "SELECT SUM(data_length + index_length) / 1024 / 1024 FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name LIKE '{$wpdb->prefix}ybt_%'"
        );

        // 7. Security & HTTPS Status
        $is_ssl = is_ssl();
        $jwt_secret_configured = !empty(get_option('ybt_jwt_secret_key'));

        return array(
            'timestamp'             => current_time('mysql'),
            'status_counts'         => $status_counts,
            'unclaimed_parcels'     => $unclaimed_count,
            'stale_parcels'         => $stale_parcels,
            'missing_photos'        => $missing_photos,
            'old_images_5m_count'   => $old_images_preview['total_images_found'],
            'old_records_5m_count'  => $old_images_preview['total_records_affected'],
            'db_size_mb'            => round($db_size, 2),
            'is_ssl'                => $is_ssl,
            'jwt_configured'        => $jwt_secret_configured,
            'health_score'          => $this->calculate_health_score($stale_parcels, $missing_photos, $unclaimed_count)
        );
    }

    /**
     * Calculate overall system health score (0-100%)
     */
    private function calculate_health_score($stale, $missing_photos, $unclaimed) {
        $score = 100;
        if ($stale > 0) $score -= min(20, $stale * 2);
        if ($missing_photos > 10) $score -= 15;
        if ($unclaimed > 50) $score -= 10;
        return max(40, $score);
    }

    /**
     * Delete physical image files and clear image database fields for tracking numbers older than X months
     */
    public function prune_images_older_than_months($months = 5, $dry_run = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_tracking';
        $months = max(1, intval($months));

        // Get records with images older than $months (based on date_completed, date_received, or date_added)
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tracking_number, images, date_added, date_received, date_completed
             FROM {$table_name}
             WHERE images IS NOT NULL AND images != ''
             AND COALESCE(date_completed, date_received, date_added) < DATE_SUB(NOW(), INTERVAL %d MONTH)",
            $months
        ));

        $total_records = count($records);
        $total_images = 0;
        $deleted_files_count = 0;
        $freed_bytes = 0;
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $base_dir = $upload_dir['basedir'];

        foreach ($records as $row) {
            $raw_images = explode(',', $row->images);
            $raw_images = array_filter(array_map('trim', $raw_images));

            foreach ($raw_images as $img_url) {
                $total_images++;

                // Convert URL to local path safely
                $file_path = false;
                if (strpos($img_url, $base_url) !== false) {
                    $file_path = str_replace($base_url, $base_dir, $img_url);
                } elseif (strpos($img_url, 'wp-content/uploads') !== false) {
                    $rel_path = strstr($img_url, 'wp-content/uploads');
                    $file_path = ABSPATH . $rel_path;
                }

                if (!$dry_run && $file_path && file_exists($file_path)) {
                    $real_file = realpath($file_path);
                    $real_basedir = realpath($base_dir);
                    if ($real_file && $real_basedir && strpos($real_file, $real_basedir) === 0) {
                        $file_size = filesize($real_file);
                        if (@unlink($real_file)) {
                            $deleted_files_count++;
                            $freed_bytes += $file_size;
                        }
                    }
                }
            }

            if (!$dry_run) {
                // Clear images field in DB
                $wpdb->update(
                    $table_name,
                    array('images' => ''),
                    array('id' => $row->id)
                );
            }
        }

        if (!$dry_run && function_exists('ybt_log_error')) {
            $mb_freed = round($freed_bytes / 1024 / 1024, 2);
            ybt_log_error("Pruned images older than {$months} months: {$deleted_files_count} physical files deleted ({$mb_freed} MB freed) across {$total_records} tracking records.");
        }

        return array(
            'months'                 => $months,
            'dry_run'                => $dry_run,
            'total_records_affected' => $total_records,
            'total_images_found'     => $total_images,
            'deleted_files_count'    => $deleted_files_count,
            'freed_mb'               => round($freed_bytes / 1024 / 1024, 2)
        );
    }

    /**
     * Auto-repair system issues
     */
    public function auto_repair_system() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ybt_tracking';
        $actions_taken = array();

        // 1. Repair orphaned user IDs by matching usernames
        $repaired_users = $wpdb->query(
            "UPDATE {$table_name} t 
             JOIN {$wpdb->users} u ON t.username = u.user_login 
             SET t.user_id = u.ID 
             WHERE (t.user_id IS NULL OR t.user_id = 0) AND t.username != 'anonymous'"
        );
        if ($repaired_users) {
            $actions_taken[] = "Linked {$repaired_users} tracking records to their corresponding WordPress User IDs.";
        }

        // 2. Clear expired transients & cache
        wp_cache_flush();
        $actions_taken[] = "Flushed system cache & transients.";

        // 3. Optimize database tables
        $wpdb->query("OPTIMIZE TABLE {$table_name}");
        $actions_taken[] = "Optimized `{$table_name}` database table indexes.";

        return $actions_taken;
    }

    /**
     * AJAX: Run Diagnostics
     */
    public function ajax_run_diagnostics() {
        check_ajax_referer('ybt_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $results = $this->run_full_diagnostics();
        wp_send_json_success($results);
    }

    /**
     * AJAX: Auto Repair
     */
    public function ajax_auto_repair() {
        check_ajax_referer('ybt_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $actions = $this->auto_repair_system();
        wp_send_json_success(array(
            'message' => 'System maintenance and auto-repair completed successfully.',
            'actions' => $actions
        ));
    }

    /**
     * AJAX: Preview Prune Images (>5 Months)
     */
    public function ajax_preview_prune_images() {
        check_ajax_referer('ybt_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $months = isset($_POST['months']) ? max(1, intval($_POST['months'])) : 5;
        $result = $this->prune_images_older_than_months($months, true);
        wp_send_json_success($result);
    }

    /**
     * AJAX: Execute Prune Images (>5 Months)
     */
    public function ajax_prune_old_images() {
        check_ajax_referer('ybt_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $months = isset($_POST['months']) ? max(1, intval($_POST['months'])) : 5;
        $result = $this->prune_images_older_than_months($months, false);
        wp_send_json_success($result);
    }

    /**
     * AJAX: Generate Daily Operational Report
     */
    public function ajax_generate_report() {
        check_ajax_referer('ybt_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $diag = $this->run_full_diagnostics();
        $report = "=== YBT PACKER OPERATIONAL REPORT (" . date('Y-m-d H:i') . ") ===\n";
        $report .= "System Health Score: " . $diag['health_score'] . "%\n";
        $report .= "Database Storage: " . $diag['db_size_mb'] . " MB\n";
        $report .= "Unclaimed Parcels: " . $diag['unclaimed_parcels'] . "\n";
        $report .= "Stale Warehouse Parcels (>14 days): " . $diag['stale_parcels'] . "\n";
        $report .= "Images Older Than 5 Months: " . $diag['old_images_5m_count'] . " images in " . $diag['old_records_5m_count'] . " records\n";

        wp_send_json_success(array('report' => $report));
    }

    /**
     * Render Admin AI Operator Console Page
     */
    public function render_ai_console() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        $diag = $this->run_full_diagnostics();
        ?>
        <div class="wrap ybt-admin-modern">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <h1 class="wp-heading-inline fw-bold text-dark me-2">🤖 YBT AI System Operator Console</h1>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Active AI Agent v3.2</span>
                </div>
                <div class="d-flex gap-2">
                    <button id="btn-ai-diagnostics" class="button button-primary button-large" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                        <i class="dashicons dashicons-search me-1"></i> Run Diagnostic Scan
                    </button>
                    <button id="btn-ai-repair" class="button button-secondary button-large">
                        <i class="dashicons dashicons-admin-tools me-1"></i> Run Auto-Repair
                    </button>
                </div>
            </div>

            <!-- Health Score Header Card -->
            <div class="card mb-4" style="border-radius: 16px; border: 1px solid rgba(16, 185, 129, 0.2); background: linear-gradient(135deg, #047857 0%, #10b981 100%); color: white; padding: 24px;">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="text-white fw-bold mb-1">System Health Score: <?php echo $diag['health_score']; ?>%</h2>
                        <p class="text-white-50 mb-0">Automated AI Operations monitor active. Scanning parcel status pipelines, image storage, and database integrity.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-inline-block bg-white bg-opacity-20 rounded-circle p-4 text-center" style="width: 100px; height: 100px;">
                            <span class="display-6 fw-bold text-warning"><?php echo $diag['health_score']; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Diagnostics Grid -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card p-4 shadow-sm" style="border-radius: 12px; border-top: 4px solid #10b981;">
                        <h4 class="text-muted small text-uppercase mb-2">UNCLAIMED PARCELS</h4>
                        <div class="display-6 fw-bold text-dark"><?php echo $diag['unclaimed_parcels']; ?></div>
                        <small class="text-muted">Awaiting customer claim</small>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-4 shadow-sm" style="border-radius: 12px; border-top: 4px solid #d4af37;">
                        <h4 class="text-muted small text-uppercase mb-2">STALE IN WAREHOUSE</h4>
                        <div class="display-6 fw-bold text-dark"><?php echo $diag['stale_parcels']; ?></div>
                        <small class="text-muted">>14 days in warehouse</small>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-4 shadow-sm" style="border-radius: 12px; border-top: 4px solid #ef4444;">
                        <h4 class="text-muted small text-uppercase mb-2">IMAGES (> 5 MONTHS)</h4>
                        <div class="display-6 fw-bold text-dark" id="stat-old-images"><?php echo $diag['old_images_5m_count']; ?></div>
                        <small class="text-muted">Eligible for media cleanup</small>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card p-4 shadow-sm" style="border-radius: 12px; border-top: 4px solid #a855f7;">
                        <h4 class="text-muted small text-uppercase mb-2">DATABASE SIZE</h4>
                        <div class="display-6 fw-bold text-dark"><?php echo $diag['db_size_mb']; ?> <span class="fs-6 text-muted">MB</span></div>
                        <small class="text-muted">Optimized YBT tables</small>
                    </div>
                </div>
            </div>

            <!-- Media Maintenance Action Card -->
            <div class="card p-4 mb-4" style="border-radius: 16px; border: 1.5px solid #e2e8f0; background: #ffffff;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold text-dark mb-1"><i class="dashicons dashicons-trash me-2 text-danger"></i> Media Storage Cleanup (> 5 Months)</h3>
                        <p class="text-muted mb-0 small">Delete physical image files and clear tracking image references older than 5 months to free up server disk space.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="btn-preview-prune" class="button button-secondary button-large">
                            <i class="dashicons dashicons-visibility me-1"></i> Preview Old Images
                        </button>
                        <button id="btn-prune-images-5m" class="button button-primary button-large" style="background: #ef4444; border-color: #dc2626;">
                            <i class="dashicons dashicons-trash me-1"></i> Delete Images Older Than 5 Months
                        </button>
                    </div>
                </div>
            </div>

            <!-- AI Output Console -->
            <div class="card p-4" style="border-radius: 16px; background: #0f172a; color: #38bdf8; font-family: monospace;">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-2">
                    <span class="fw-bold text-white"><i class="dashicons dashicons-format-status me-1"></i> AI Operator System Log</span>
                    <span class="small text-muted">Real-Time Terminal</span>
                </div>
                <div id="ai-terminal-output" style="min-height: 220px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6;">[SYSTEM READY] AI System Operator Assistant initialized.
Press 'Run Diagnostic Scan', 'Preview Old Images', or 'Delete Images Older Than 5 Months' to execute tasks.</div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const nonce = '<?php echo wp_create_nonce("ybt_nonce"); ?>';
            const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
            const output = $('#ai-terminal-output');

            function log(msg) {
                const time = new Date().toLocaleTimeString();
                output.append('\n[' + time + '] ' + msg);
                output.scrollTop(output[0].scrollHeight);
            }

            $('#btn-ai-diagnostics').click(function() {
                log('Initiating full system diagnostic scan...');
                $.post(ajaxUrl, { action: 'ybt_ai_run_diagnostics', nonce: nonce }, function(res) {
                    if (res.success) {
                        log('Scan completed successfully!');
                        log('Health Score: ' + res.data.health_score + '%');
                        log('Unclaimed Parcels: ' + res.data.unclaimed_parcels);
                        log('Stale Warehouse Items (>14d): ' + res.data.stale_parcels);
                        log('Images (>5 Months Old): ' + res.data.old_images_5m_count + ' images in ' + res.data.old_records_5m_count + ' records');
                        log('Database Size: ' + res.data.db_size_mb + ' MB');
                        $('#stat-old-images').text(res.data.old_images_5m_count);
                    } else {
                        log('Error running scan: ' + (res.data || 'Unknown error'));
                    }
                });
            });

            $('#btn-ai-repair').click(function() {
                log('Executing automated system repair and optimization routines...');
                $.post(ajaxUrl, { action: 'ybt_ai_auto_repair', nonce: nonce }, function(res) {
                    if (res.success) {
                        log('Auto-Repair Finished: ' + res.data.message);
                        if (res.data.actions && res.data.actions.length > 0) {
                            res.data.actions.forEach(act => log(' -> ' + act));
                        }
                    } else {
                        log('Repair Failed: ' + (res.data || 'Unknown error'));
                    }
                });
            });

            $('#btn-preview-prune').click(function() {
                log('Scanning storage for images older than 5 months (Dry Run)...');
                $.post(ajaxUrl, { action: 'ybt_ai_preview_prune_images', nonce: nonce, months: 5 }, function(res) {
                    if (res.success) {
                        log('Scan Preview: Found ' + res.data.total_images_found + ' images across ' + res.data.total_records_affected + ' tracking records older than 5 months.');
                        $('#stat-old-images').text(res.data.total_images_found);
                    } else {
                        log('Preview error: ' + (res.data || 'Failed to calculate old images'));
                    }
                });
            });

            $('#btn-prune-images-5m').click(function() {
                if (!confirm('Are you sure you want to permanently delete physical image files and clear image references for tracking numbers older than 5 months? This cannot be undone.')) {
                    return;
                }

                log('Executing image purge for records older than 5 months...');
                const $btn = $(this);
                $btn.prop('disabled', true);

                $.post(ajaxUrl, { action: 'ybt_ai_prune_old_images', nonce: nonce, months: 5 }, function(res) {
                    $btn.prop('disabled', false);
                    if (res.success) {
                        log('PURGE SUCCESSFUL!');
                        log('Deleted physical files: ' + res.data.deleted_files_count);
                        log('Cleared records: ' + res.data.total_records_affected);
                        log('Disk space freed: ' + res.data.freed_mb + ' MB');
                        $('#stat-old-images').text(0);
                    } else {
                        log('Purge Failed: ' + (res.data || 'Error deleting images'));
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    log('Network error while deleting images.');
                });
            });
        });
        </script>
        <?php
    }
}

// Instantiate AI Operator
add_action('plugins_loaded', function() {
    if (class_exists('YBT_AI_Operator')) {
        YBT_AI_Operator::instance();
    }
});
