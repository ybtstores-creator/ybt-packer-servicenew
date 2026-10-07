<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
?>
<div class="wrap ybt-admin-wrapper">
    <h1 class="ybt-page-title"><i class="fas fa-shield-alt"></i> Security Dashboard</h1>

    <div id="security-loading" style="text-align:center;padding:50px;">
        <p>Loading security data...</p>
    </div>

    <div id="security-content" style="display:none;">
        <div class="ybt-alert ybt-alert--info">
            <p><strong>Security Status:</strong> <span id="security-status">Monitoring...</span></p>
        </div>

        <div class="ybt-stats-grid">
            <div class="ybt-stat-card">
                <div class="ybt-stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="ybt-stat-content">
                    <div class="ybt-stat-label">Failed Logins (24h)</div>
                    <div class="ybt-stat-value" id="stat-failed-logins">0</div>
                </div>
            </div>
            <div class="ybt-stat-card">
                <div class="ybt-stat-icon warning">
                    <i class="fas fa-user-secret"></i>
                </div>
                <div class="ybt-stat-content">
                    <div class="ybt-stat-label">Suspicious IPs</div>
                    <div class="ybt-stat-value" id="stat-suspicious-ips">0</div>
                </div>
            </div>
            <div class="ybt-stat-card">
                <div class="ybt-stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="ybt-stat-content">
                    <div class="ybt-stat-label">Total Users</div>
                    <div class="ybt-stat-value" id="stat-total-users">0</div>
                </div>
            </div>
            <div class="ybt-stat-card">
                <div class="ybt-stat-icon info">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="ybt-stat-content">
                    <div class="ybt-stat-label">Admin Users</div>
                    <div class="ybt-stat-value" id="stat-admin-users">0</div>
                </div>
            </div>
        </div>

        <div class="ybt-grid ybt-grid--2col" style="gap: 20px; margin: 20px 0;">
            <div class="ybt-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Suspicious IPs</h3>
                <div id="suspicious-ips-list"></div>
            </div>

            <div style="background:#fff;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                <h3><i class="fas fa-bell"></i> Recent Security Events</h3>
                <div id="security-events-list"></div>
            </div>
        </div>

        <div class="ybt-card">
            <div class="ybt-card-header">
                <h3 class="ybt-card-title"><i class="fas fa-history"></i> Login Attempts Log</h3>
            </div>
            <?php
            $login_attempts = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}ybt_login_attempts ORDER BY attempt_time DESC LIMIT 50"
            );
            ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Username</th>
                        <th>IP Address</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($login_attempts): ?>
                        <?php foreach ($login_attempts as $attempt): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($attempt->attempt_time)); ?></td>
                                <td><?php echo esc_html($attempt->username); ?></td>
                                <td><?php echo esc_html($attempt->ip_address); ?></td>
                                <td>
                                    <?php if ($attempt->success): ?>
                                        <span class="dashicons dashicons-yes" style="color:#00a32a;"></span> Success
                                    <?php else: ?>
                                        <span class="dashicons dashicons-no" style="color:#d63638;"></span> Failed
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No login attempts recorded</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="ybt-card">
            <div class="ybt-card-header">
                <h3 class="ybt-card-title"><i class="fas fa-cog"></i> Security Settings</h3>
            </div>
            <div class="ybt-alert ybt-alert--warning">
                <p><strong>Active Security Features:</strong></p>
                <ul>
                    <li>✓ Rate limiting on all AJAX endpoints</li>
                    <li>✓ Login attempt tracking and blocking (5 attempts per 15 minutes)</li>
                    <li>✓ IP-based security monitoring</li>
                    <li>✓ Strong password requirements (8+ chars, uppercase, lowercase, numbers)</li>
                    <li>✓ Username validation (3-20 chars, alphanumeric only)</li>
                    <li>✓ CSRF protection via nonces</li>
                    <li>✓ SQL injection prevention</li>
                    <li>✓ XSS protection via sanitization</li>
                    <li>✓ CORS whitelist protection (replaced wildcard)</li>
                </ul>
            </div>
        </div>

        <!-- CORS Settings -->
        <div class="ybt-card">
            <div class="ybt-card-header">
                <h3 class="ybt-card-title"><i class="fas fa-lock"></i> SSL & HTTPS Enforcement</h3>
            </div>
            <p>Configure how the plugin handles secure connections. For production sites, it is highly recommended to enable strict enforcement.</p>
            
            <form id="ybt-ssl-settings-form" method="post">
                <?php wp_nonce_field('ybt_ssl_settings', 'ybt_ssl_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ybt_enforce_https">Enforce Strict HTTPS</label>
                        </th>
                        <td>
                            <label class="ybt-switch">
                                <input type="checkbox" id="ybt_enforce_https" name="ybt_enforce_https" 
                                       <?php checked(get_option('ybt_enforce_https', '1'), '1'); ?>>
                                <span class="ybt-slider round"></span>
                            </label>
                            <p class="description">
                                Automatically redirects all users to the HTTPS version of the site and requires SSL for login.<br>
                                <strong>Note:</strong> If you are on <code>localhost</code> and your server doesn't support HTTPS, keep this disabled to avoid character/login issues.
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" id="ybt-save-ssl-settings">
                        Update Security Protocol
                    </button>
                    <span id="ybt-ssl-message" style="margin-left:10px;"></span>
                </p>
            </form>
        </div>

        <style>
            .ybt-switch { position: relative; display: inline-block; width: 50px; height: 24px; vertical-align: middle; }
            .ybt-switch input { opacity: 0; width: 0; height: 0; }
            .ybt-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
            .ybt-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
            input:checked + .ybt-slider { background-color: #10b981; }
            input:focus + .ybt-slider { box-shadow: 0 0 1px #10b981; }
            input:checked + .ybt-slider:before { transform: translateX(26px); }
        </style>

        <!-- CORS Settings -->
        <div class="ybt-card">
            <div class="ybt-card-header">
                <h3 class="ybt-card-title"><i class="fas fa-exchange-alt"></i> CORS Settings (Mobile App Connectivity)</h3>
            </div>
            <p>Configure allowed origins for mobile app and cross-origin API access. The mobile app must connect from an allowed origin.</p>
            
            <form id="ybt-cors-settings-form" method="post">
                <?php wp_nonce_field('ybt_cors_settings', 'ybt_cors_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ybt_allowed_origins">Allowed Origins (comma-separated)</label>
                        </th>
                        <td>
                            <input type="text" id="ybt_allowed_origins" name="ybt_allowed_origins" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr(get_option('ybt_allowed_cors_origins', '')); ?>"
                                   placeholder="https://ybtpackingservice.com, https://yourdomain.com">
                            <p class="description">
                                Enter allowed origins for cross-origin requests. Separate multiple origins with commas.<br>
                                <strong>Examples:</strong><br>
                                • Mobile app: https://ybtpackingservice.com<br>
                                • Local development: http://localhost:8080, http://127.0.0.1:8080<br>
                                • Android emulator: http://10.0.2.2
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ybt_mobile_api_url">Mobile App API Base URL</label>
                        </th>
                        <td>
                            <input type="url" id="ybt_mobile_api_url" name="ybt_mobile_api_url" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr(get_option('ybt_mobile_api_url', get_option('siteurl'))); ?>"
                                   placeholder="https://yourwordpresssite.com">
                            <p class="description">
                                The base URL for the mobile app to connect to. This is used to generate APIendpoints.
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" id="ybt-save-cors-settings">
                        Save CORS Settings
                    </button>
                    <span id="ybt-cors-message" style="margin-left:10px;"></span>
                </p>
            </form>
        </div>

        <!-- Mobile App Connection Test -->
        <div class="ybt-card">
            <div class="ybt-card-header">
                <h3 class="ybt-card-title"><i class="fas fa-mobile-alt"></i> Mobile App Connectivity</h3>
            </div>
            <p>Test if your mobile app can connect to the server.</p>
            
            <div style="margin-top:15px;">
                <button type="button" class="ybt-btn ybt-btn--primary" id="ybt-test-api-connection">
                    <i class="fas fa-plug"></i> Test API Connection
                </button>
                <button type="button" class="ybt-btn ybt-btn--secondary" id="ybt-test-login">
                    <i class="fas fa-sign-in-alt"></i> Test Login Endpoint
                </button>
            </div>
            
            <div id="ybt-api-test-results" style="margin-top:20px;display:none;">
                <div class="ybt-alert ybt-alert--info" id="ybt-api-test-notice">
                    <p id="ybt-api-test-message"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Save SSL settings
    $('#ybt-ssl-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var nonce = $('#ybt_ssl_nonce').val();
        var enforceHttps = $('#ybt_enforce_https').is(':checked') ? '1' : '0';
        
        $('#ybt-save-ssl-settings').prop('disabled', true).text('Updating...');
        
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_save_ssl_settings',
                nonce: nonce,
                enforce_https: enforceHttps
            },
            success: function(response) {
                if (response.success) {
                    $('#ybt-ssl-message').html('<span class="text-success"><i class="fas fa-check"></i> Security protocol updated!</span>');
                } else {
                    $('#ybt-ssl-message').html('<span class="text-danger"><i class="fas fa-times"></i> Error: ' + response.data + '</span>');
                }
                setTimeout(function() {
                    $('#ybt-ssl-message').html('');
                    $('#ybt-save-ssl-settings').prop('disabled', false).text('Update Security Protocol');
                }, 3000);
            },
            error: function() {
                $('#ybt-ssl-message').html('<span class="text-danger"><i class="fas fa-times"></i> Connection error</span>');
                $('#ybt-save-ssl-settings').prop('disabled', false).text('Update Security Protocol');
            }
        });
    });

    // Save CORS settings
    $('#ybt-cors-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var nonce = $('#ybt_cors_nonce').val();
        var allowedOrigins = $('#ybt_allowed_origins').val();
        var mobileApiUrl = $('#ybt_mobile_api_url').val();
        
        $('#ybt-save-cors-settings').prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_save_cors_settings',
                nonce: nonce,
                allowed_origins: allowedOrigins,
                mobile_api_url: mobileApiUrl
            },
            success: function(response) {
                if (response.success) {
                    $('#ybt-cors-message').html('<span class="text-success"><i class="fas fa-check"></i> Settings saved successfully!</span>');
                } else {
                    $('#ybt-cors-message').html('<span class="text-danger"><i class="fas fa-times"></i> Error: ' + response.data + '</span>');
                }
                setTimeout(function() {
                    $('#ybt-cors-message').html('');
                    $('#ybt-save-cors-settings').prop('disabled', false).text('Save CORS Settings');
                }, 3000);
            },
            error: function() {
                $('#ybt-cors-message').html('<span class="text-danger"><i class="fas fa-times"></i> Connection error</span>');
                $('#ybt-save-cors-settings').prop('disabled', false).text('Save CORS Settings');
            }
        });
    });
    
    // Test API Connection
    $('#ybt-test-api-connection').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Testing...');
        
        $('#ybt-api-test-results').show();
        $('#ybt-api-test-notice').removeClass('ybt-alert--error ybt-alert--success ybt-alert--warning').addClass('ybt-alert--info');
        $('#ybt-api-test-message').html('Testing connection to server...');
        
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_test_api_connection',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#ybt-api-test-notice').removeClass('ybt-alert--info').addClass('ybt-alert--success');
                    $('#ybt-api-test-message').html('<i class="fas fa-check-circle"></i> ' + response.data.message);
                } else {
                    $('#ybt-api-test-notice').removeClass('ybt-alert--info').addClass('ybt-alert--error');
                    $('#ybt-api-test-message').html('<i class="fas fa-times-circle"></i> Error: ' + response.data);
                }
                btn.prop('disabled', false).text('Test API Connection');
            },
            error: function() {
                $('#ybt-api-test-notice').removeClass('ybt-alert--info').addClass('ybt-alert--error');
                $('#ybt-api-test-message').html('<i class="fas fa-times-circle"></i> Connection failed - check server');
                btn.prop('disabled', false).text('Test API Connection');
            }
        });
    });
    
    // Test Login Endpoint
    $('#ybt-test-login').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Testing...');
        
        $('#ybt-api-test-results').show();
        $('#ybt-api-test-notice').removeClass('notice-error notice-success notice-warning').addClass('notice-info');
        $('#ybt-api-test-message').html('Testing login endpoint...');
        
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_test_login_endpoint',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#ybt-api-test-notice').removeClass('ybt-alert--info').addClass('ybt-alert--success');
                    $('#ybt-api-test-message').html('<i class="fas fa-check-circle"></i> Login endpoint working! Response: ' + response.data.message);
                } else {
                    $('#ybt-api-test-notice').removeClass('ybt-alert--info').addClass('ybt-alert--warning');
                    $('#ybt-api-test-message').html('<i class="fas fa-exclamation-circle"></i> Login endpoint responded: ' + response.data);
                }
                btn.prop('disabled', false).text('Test Login Endpoint');
            },
            error: function(xhr) {
                $('#ybt-api-test-notice').removeClass('ybt-alert--info').addClass('ybt-alert--error');
                $('#ybt-api-test-message').html('<i class="fas fa-times-circle"></i> Error: ' + xhr.status + ' - ' + xhr.statusText);
                btn.prop('disabled', false).text('Test Login Endpoint');
            }
        });
    });

    // Security monitoring function
    function loadSecurityData() {
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_check_security',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;

                    $('#stat-failed-logins').text(data.failed_logins_24h);
                    $('#stat-suspicious-ips').text(data.suspicious_ips.length);
                    $('#stat-total-users').text(data.total_users);
                    $('#stat-admin-users').text(data.admin_users);

                    var statusText = 'All systems secure';
                    if (data.failed_logins_24h > 50) {
                        statusText = '<span class="text-danger">High alert - Many failed logins detected</span>';
                    } else if (data.suspicious_ips.length > 0) {
                        statusText = '<span style="color:#f0b849;">Warning - Suspicious activity detected</span>';
                    } else {
                        statusText = '<span style="color:#00a32a;">All systems secure</span>';
                    }
                    $('#security-status').html(statusText);

                    var suspiciousHtml = '<ul class="ybt-list">';
                    if (data.suspicious_ips.length > 0) {
                        data.suspicious_ips.forEach(function(ip) {
                            suspiciousHtml += '<li class="ybt-list-item"><strong>' + ip.ip_address + '</strong>: ' + ip.attempts + ' failed attempts</li>';
                        });
                    } else {
                        suspiciousHtml += '<li class="text-muted">No suspicious activity detected</li>';
                    }
                    suspiciousHtml += '</ul>';
                    $('#suspicious-ips-list').html(suspiciousHtml);

                    var eventsHtml = '<ul class="ybt-list">';
                    if (data.recent_security_events.length > 0) {
                        data.recent_security_events.forEach(function(event) {
                            var severityClass = event.severity === 'critical' ? 'text-danger' : 'text-warning';
                            eventsHtml += '<li class="ybt-list-item">';
                            eventsHtml += '<span class="' + severityClass + '" style="font-weight:bold;">' + event.event_type.toUpperCase() + '</span><br>';
                            eventsHtml += '<small>' + event.description + '</small><br>';
                            eventsHtml += '<small class="text-muted">' + new Date(event.date_created).toLocaleString() + '</small>';
                            eventsHtml += '</li>';
                        });
                    } else {
                        eventsHtml += '<li class="text-muted">No recent security events</li>';
                    }
                    eventsHtml += '</ul>';
                    $('#security-events-list').html(eventsHtml);

                    $('#security-loading').hide();
                    $('#security-content').show();
                }
            },
            error: function(xhr, status, error) {
                console.log('Security check error:', status, error);
                console.log('Response:', xhr.responseText);
                $('#security-status').html('<span class="text-danger">Error loading security data: ' + error + '</span>');
                $('#security-loading').hide();
                $('#security-content').show();
            }
        });
    }
});
</script>
