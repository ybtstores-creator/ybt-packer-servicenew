<?php
/**
 * Professional Admin Settings Page
 * YBT Packer Service v2.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'ybt-packer'));
}

$config = ybt_config();
$security = ybt_security();

// Handle form submission
if (isset($_POST['ybt_save_settings']) && wp_verify_nonce($_POST['ybt_settings_nonce'], 'ybt_save_settings')) {
    $settings_updated = false;

    // General Settings
    if (isset($_POST['general'])) {
        foreach ($_POST['general'] as $key => $value) {
            $config->set($key, sanitize_text_field($value), 'general');
            $settings_updated = true;
        }
    }

    // Security Settings
    if (isset($_POST['security'])) {
        foreach ($_POST['security'] as $key => $value) {
            $config->set($key, is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value), 'security');
            $settings_updated = true;
        }
    }

    // Performance Settings
    if (isset($_POST['performance'])) {
        foreach ($_POST['performance'] as $key => $value) {
            $config->set($key, sanitize_text_field($value), 'performance');
            $settings_updated = true;
        }
    }

    if ($settings_updated) {
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'ybt-packer') . '</p></div>';
    }
}

$current_env = $config->get_current_environment();
$security_stats = $security->get_security_stats();
?>

<div class="wrap ybt-admin-wrap">
    <div class="ybt-settings-header">
        <div class="ybt-header-content">
            <div class="ybt-header-title">
                <h1><i class="fas fa-cogs"></i> <?php _e('System Settings', 'ybt-packer'); ?></h1>
                <p class="ybt-header-subtitle"><?php _e('Configure your YBT Packer Service', 'ybt-packer'); ?></p>
            </div>
            <div class="ybt-header-info">
                <div class="ybt-env-badge ybt-env-<?php echo esc_attr($current_env); ?>">
                    <i class="fas fa-server"></i>
                    <?php echo esc_html(ucfirst($current_env)); ?> Mode
                </div>
            </div>
        </div>
    </div>

    <div class="ybt-admin-container">
        <form method="post" action="" class="ybt-settings-form">
            <?php wp_nonce_field('ybt_save_settings', 'ybt_settings_nonce'); ?>

            <div class="ybt-settings-grid">
                <!-- Navigation Tabs -->
                <div class="ybt-settings-nav">
                    <div class="ybt-nav-tabs">
                        <button type="button" class="ybt-nav-tab active" data-tab="general">
                            <i class="fas fa-cog"></i>
                            <?php _e('General', 'ybt-packer'); ?>
                        </button>
                        <button type="button" class="ybt-nav-tab" data-tab="security">
                            <i class="fas fa-shield-alt"></i>
                            <?php _e('Security', 'ybt-packer'); ?>
                        </button>
                        <button type="button" class="ybt-nav-tab" data-tab="performance">
                            <i class="fas fa-tachometer-alt"></i>
                            <?php _e('Performance', 'ybt-packer'); ?>
                        </button>
                        <button type="button" class="ybt-nav-tab" data-tab="email">
                            <i class="fas fa-envelope"></i>
                            <?php _e('Email', 'ybt-packer'); ?>
                        </button>
                        <button type="button" class="ybt-nav-tab" data-tab="api">
                            <i class="fas fa-code"></i>
                            <?php _e('API', 'ybt-packer'); ?>
                        </button>
                        <button type="button" class="ybt-nav-tab" data-tab="notifications">
                            <i class="fas fa-bell"></i>
                            <?php _e('Push Notifications', 'ybt-packer'); ?>
                        </button>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="ybt-settings-content">

                    <!-- General Settings -->
                    <div class="ybt-tab-content active" id="general">
                        <div class="ybt-settings-section">
                            <h3><?php _e('General Configuration', 'ybt-packer'); ?></h3>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label"><?php _e('Debug Mode', 'ybt-packer'); ?></label>
                                <div class="ybt-toggle-switch">
                                    <input type="checkbox" id="debug" name="general[debug]" value="1" <?php checked($config->get('debug')); ?>>
                                    <label for="debug" class="ybt-toggle-label"></label>
                                </div>
                                <p class="ybt-form-help"><?php _e('Enable debug mode for development and troubleshooting.', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label" for="cache_duration"><?php _e('Cache Duration (seconds)', 'ybt-packer'); ?></label>
                                <input type="number" id="cache_duration" name="general[cache_duration]"
                                       value="<?php echo esc_attr($config->get('cache_duration', 3600)); ?>"
                                       class="ybt-form-input" min="60" max="86400">
                                <p class="ybt-form-help"><?php _e('How long to cache data (60-86400 seconds).', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label" for="max_file_size"><?php _e('Maximum File Size (bytes)', 'ybt-packer'); ?></label>
                                <input type="number" id="max_file_size" name="general[max_file_size]"
                                       value="<?php echo esc_attr($config->get('max_file_size', 10485760)); ?>"
                                       class="ybt-form-input" min="1048576">
                                <p class="ybt-form-help"><?php _e('Maximum allowed file upload size.', 'ybt-packer'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="ybt-tab-content" id="security">
                        <div class="ybt-settings-section">
                            <h3><?php _e('Security Configuration', 'ybt-packer'); ?></h3>

                            <!-- Security Stats -->
                            <div class="ybt-security-stats">
                                <div class="ybt-stat-item">
                                    <div class="ybt-stat-number"><?php echo number_format($security_stats['total_security_events']); ?></div>
                                    <div class="ybt-stat-label"><?php _e('Total Events', 'ybt-packer'); ?></div>
                                </div>
                                <div class="ybt-stat-item">
                                    <div class="ybt-stat-number"><?php echo number_format($security_stats['critical_events_today']); ?></div>
                                    <div class="ybt-stat-label"><?php _e('Critical Today', 'ybt-packer'); ?></div>
                                </div>
                                <div class="ybt-stat-item">
                                    <div class="ybt-stat-number"><?php echo number_format($security_stats['failed_logins_today']); ?></div>
                                    <div class="ybt-stat-label"><?php _e('Failed Logins', 'ybt-packer'); ?></div>
                                </div>
                                <div class="ybt-stat-item">
                                    <div class="ybt-stat-number"><?php echo number_format($security_stats['blocked_ips']); ?></div>
                                    <div class="ybt-stat-label"><?php _e('Blocked IPs', 'ybt-packer'); ?></div>
                                </div>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label"><?php _e('Enable Rate Limiting', 'ybt-packer'); ?></label>
                                <div class="ybt-toggle-switch">
                                    <input type="checkbox" id="enable_rate_limiting" name="security[enable_rate_limiting]" value="1"
                                           <?php checked($config->get('enable_rate_limiting')); ?>>
                                    <label for="enable_rate_limiting" class="ybt-toggle-label"></label>
                                </div>
                                <p class="ybt-form-help"><?php _e('Limit the number of requests per hour from each IP.', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label" for="max_login_attempts"><?php _e('Max Login Attempts', 'ybt-packer'); ?></label>
                                <input type="number" id="max_login_attempts" name="security[max_login_attempts]"
                                       value="<?php echo esc_attr($config->get('max_login_attempts', 5)); ?>"
                                       class="ybt-form-input" min="3" max="20">
                                <p class="ybt-form-help"><?php _e('Maximum failed login attempts before IP blocking.', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label" for="lockout_duration"><?php _e('Lockout Duration (seconds)', 'ybt-packer'); ?></label>
                                <input type="number" id="lockout_duration" name="security[lockout_duration]"
                                       value="<?php echo esc_attr($config->get('lockout_duration', 900)); ?>"
                                       class="ybt-form-input" min="300" max="86400">
                                <p class="ybt-form-help"><?php _e('How long to block IPs after failed attempts.', 'ybt-packer'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Settings -->
                    <div class="ybt-tab-content" id="performance">
                        <div class="ybt-settings-section">
                            <h3><?php _e('Performance Optimization', 'ybt-packer'); ?></h3>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label"><?php _e('Enable Compression', 'ybt-packer'); ?></label>
                                <div class="ybt-toggle-switch">
                                    <input type="checkbox" id="enable_compression" name="performance[enable_compression]" value="1"
                                           <?php checked($config->get('enable_compression')); ?>>
                                    <label for="enable_compression" class="ybt-toggle-label"></label>
                                </div>
                                <p class="ybt-form-help"><?php _e('Compress CSS and JavaScript files.', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label"><?php _e('Database Optimization', 'ybt-packer'); ?></label>
                                <div class="ybt-toggle-switch">
                                    <input type="checkbox" id="database_optimization" name="performance[database_optimization]" value="1"
                                           <?php checked($config->get('database_optimization')); ?>>
                                    <label for="database_optimization" class="ybt-toggle-label"></label>
                                </div>
                                <p class="ybt-form-help"><?php _e('Optimize database queries and indexing.', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label"><?php _e('Query Cache', 'ybt-packer'); ?></label>
                                <div class="ybt-toggle-switch">
                                    <input type="checkbox" id="query_cache" name="performance[query_cache]" value="1"
                                           <?php checked($config->get('query_cache')); ?>>
                                    <label for="query_cache" class="ybt-toggle-label"></label>
                                </div>
                                <p class="ybt-form-help"><?php _e('Cache database query results.', 'ybt-packer'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="ybt-tab-content" id="email">
                        <div class="ybt-settings-section">
                            <h3><?php _e('Email Configuration', 'ybt-packer'); ?></h3>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label"><?php _e('Enable SMTP', 'ybt-packer'); ?></label>
                                <div class="ybt-toggle-switch">
                                    <input type="checkbox" id="smtp_enabled" name="email[smtp_enabled]" value="1"
                                           <?php checked($config->get('smtp_enabled')); ?>>
                                    <label for="smtp_enabled" class="ybt-toggle-label"></label>
                                </div>
                                <p class="ybt-form-help"><?php _e('Use SMTP for sending emails instead of PHP mail().', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label" for="from_email"><?php _e('From Email', 'ybt-packer'); ?></label>
                                <input type="email" id="from_email" name="email[from_email]"
                                       value="<?php echo esc_attr($config->get('from_email', get_option('admin_email'))); ?>"
                                       class="ybt-form-input">
                                <p class="ybt-form-help"><?php _e('Email address for outgoing messages.', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label" for="from_name"><?php _e('From Name', 'ybt-packer'); ?></label>
                                <input type="text" id="from_name" name="email[from_name]"
                                       value="<?php echo esc_attr($config->get('from_name', get_bloginfo('name'))); ?>"
                                       class="ybt-form-input">
                                <p class="ybt-form-help"><?php _e('Name for outgoing messages.', 'ybt-packer'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- API Settings -->
                    <div class="ybt-tab-content" id="api">
                        <div class="ybt-settings-section">
                            <h3><?php _e('API Configuration', 'ybt-packer'); ?></h3>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label"><?php _e('Enable REST API', 'ybt-packer'); ?></label>
                                <div class="ybt-toggle-switch">
                                    <input type="checkbox" id="enable_rest_api" name="api[enable_rest_api]" value="1"
                                           <?php checked($config->get('enable_rest_api')); ?>>
                                    <label for="enable_rest_api" class="ybt-toggle-label"></label>
                                </div>
                                <p class="ybt-form-help"><?php _e('Enable REST API endpoints for external integrations.', 'ybt-packer'); ?></p>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label" for="rate_limit_per_minute"><?php _e('API Rate Limit (per minute)', 'ybt-packer'); ?></label>
                                <input type="number" id="rate_limit_per_minute" name="api[rate_limit_per_minute]"
                                       value="<?php echo esc_attr($config->get('rate_limit_per_minute', 60)); ?>"
                                       class="ybt-form-input" min="10" max="1000">
                                <p class="ybt-form-help"><?php _e('Maximum API requests per minute per IP.', 'ybt-packer'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Push Notifications Settings -->
                    <div class="ybt-tab-content" id="notifications">
                        <div class="ybt-settings-section">
                            <h3><?php _e('Firebase Cloud Messaging (FCM)', 'ybt-packer'); ?></h3>

                            <p class="ybt-info-notice">
                                <i class="fas fa-info-circle"></i>
                                <?php _e('Securely configure Firebase for push notifications. Credentials are stored encrypted in the database.', 'ybt-packer'); ?>
                            </p>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label"><?php _e('Configuration Method', 'ybt-packer'); ?></label>
                                <?php
                                $config_method = "Not Configured";
                                $status_class = "ybt-status-inactive";
                                if (getenv('YBT_FCM_SERVICE_ACCOUNT_JSON')) {
                                    $config_method = "Environment Variable (Active)";
                                    $status_class = "ybt-status-active";
                                } else if (defined('YBT_FCM_SERVICE_ACCOUNT_JSON')) {
                                    $config_method = "WP-Config Constant (Active)";
                                    $status_class = "ybt-status-active";
                                } else if (get_option('ybt_fcm_service_account_encrypted')) {
                                    $config_method = "Encrypted Database (Active)";
                                    $status_class = "ybt-status-active";
                                }
                                ?>
                                <span class="ybt-badge <?php echo $status_class; ?>"><?php echo esc_html($config_method); ?></span>
                            </div>

                            <div class="ybt-form-group">
                                <label class="ybt-form-label" for="ybt-fcm-json"><?php _e('Service Account JSON', 'ybt-packer'); ?></label>
                                <textarea id="ybt-fcm-json" name="fcm_json" rows="8" class="ybt-form-input ybt-code-input"
                                          placeholder='{"type": "service_account", ...}'></textarea>
                                <p class="ybt-form-help"><?php _e('Paste the contents of your firebase-service-account.json file here. It will be encrypted before saving.', 'ybt-packer'); ?></p>

                                <?php if (get_option('ybt_fcm_service_account_encrypted')): ?>
                                    <p class="ybt-field-success"><i class="fas fa-lock"></i> <?php _e('Credential already stored securely.', 'ybt-packer'); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="ybt-action-group">
                                <button type="button" id="ybt-save-fcm" class="ybt-btn ybt-btn-secondary">
                                    <i class="fas fa-save"></i> <?php _e('Encrypt & Save Credentials', 'ybt-packer'); ?>
                                </button>
                                <button type="button" id="ybt-test-fcm" class="ybt-btn ybt-btn-outline"
                                        <?php echo $config_method === "Not Configured" ? 'disabled' : ''; ?>>
                                    <i class="fas fa-plug"></i> <?php _e('Test Connection', 'ybt-packer'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ybt-settings-footer">
                <button type="submit" name="ybt_save_settings" class="ybt-btn ybt-btn-primary ybt-btn-lg">
                    <i class="fas fa-save"></i>
                    <?php _e('Save Settings', 'ybt-packer'); ?>
                </button>

                <button type="button" class="ybt-btn ybt-btn-outline ybt-btn-lg" onclick="ybtResetSettings()">
                    <i class="fas fa-undo"></i>
                    <?php _e('Reset to Defaults', 'ybt-packer'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
