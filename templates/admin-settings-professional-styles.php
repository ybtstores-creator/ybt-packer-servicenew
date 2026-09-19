<style>
/* Professional Settings Page Styles */
.ybt-settings-header {
    background: linear-gradient(135deg, #228B22 0%, #DAA520 100%);
    color: white;
    padding: 2rem;
    margin: 0 0 2rem -20px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.ybt-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
}

.ybt-header-title h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    color: white;
}

.ybt-header-subtitle {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1rem;
}

.ybt-env-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ybt-env-production {
    background: rgba(239, 68, 68, 0.2);
    color: #fef2f2;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.ybt-env-development {
    background: rgba(245, 158, 11, 0.2);
    color: #fef3c7;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.ybt-settings-grid {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.ybt-settings-nav {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1rem;
    height: fit-content;
    position: sticky;
    top: 2rem;
}

.ybt-nav-tabs {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.ybt-nav-tab {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
    width: 100%;
}

.ybt-nav-tab:hover {
    background: #f3f4f6;
    color: #374151;
}

.ybt-nav-tab.active {
    background: #228B22;
    color: white;
}

.ybt-settings-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.ybt-tab-content {
    display: none;
    padding: 2rem;
}

.ybt-tab-content.active {
    display: block;
}

.ybt-settings-section {
    margin-bottom: 2rem;
}

.ybt-settings-section h3 {
    margin: 0 0 1.5rem 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e5e7eb;
}

.ybt-form-group {
    margin-bottom: 1.5rem;
}

.ybt-form-label {
    display: block;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.ybt-form-input,
.ybt-form-select,
.ybt-form-textarea {
    width: 100%;
    max-width: 400px;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: white;
}

.ybt-form-input:focus,
.ybt-form-select:focus,
.ybt-form-textarea:focus {
    outline: none;
    border-color: #228B22;
    box-shadow: 0 0 0 3px rgba(34, 139, 34, 0.1);
}

.ybt-form-help {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
    margin-bottom: 0;
}

.ybt-toggle-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 24px;
}

.ybt-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.ybt-toggle-label {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    transition: 0.2s;
    border-radius: 24px;
}

.ybt-toggle-label:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.2s;
    border-radius: 50%;
}

.ybt-toggle-switch input:checked + .ybt-toggle-label {
    background-color: #228B22;
}

.ybt-toggle-switch input:checked + .ybt-toggle-label:before {
    transform: translateX(24px);
}

.ybt-security-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.ybt-stat-item {
    text-align: center;
}

.ybt-stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
}

.ybt-stat-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ybt-settings-footer {
    padding: 1.5rem 2rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
}

/* Responsive Design */
@media (max-width: 768px) {
    .ybt-settings-header {
        padding: 1.5rem;
    }

    .ybt-header-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .ybt-settings-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 0 1rem;
    }

    .ybt-settings-nav {
        position: static;
    }

    .ybt-nav-tabs {
        flex-direction: row;
        overflow-x: auto;
        gap: 0.25rem;
    }

    .ybt-nav-tab {
        white-space: nowrap;
        min-width: fit-content;
    }

    .ybt-tab-content {
        padding: 1rem;
    }

    .ybt-security-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .ybt-settings-footer {
        flex-direction: column;
        padding: 1rem;
    }

    .ybt-form-input,
    .ybt-form-select,
    .ybt-form-textarea {
        max-width: none;
    }
}

/* Loading States */
.ybt-settings-form.loading {
    opacity: 0.7;
    pointer-events: none;
}

.ybt-settings-form.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 2rem;
    height: 2rem;
    margin: -1rem 0 0 -1rem;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #228B22;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Success/Error States */
.ybt-form-success {
    border-color: #10b981 !important;
    background-color: #f0fdf4 !important;
}

.ybt-form-error {
    border-color: #ef4444 !important;
    background-color: #fef2f2 !important;
}

.ybt-validation-message {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.ybt-validation-success {
    color: #059669;
}

.ybt-validation-error {
    color: #dc2626;
}

/* Badge Styles */
.ybt-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.ybt-status-active { background: #dcfce7; color: #166534; }
.ybt-status-inactive { background: #f3f4f6; color: #4b5563; }

/* Info Notice */
.ybt-info-notice {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Code Input */
.ybt-code-input {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.75rem;
    background: #1f2937 !important;
    color: #f3f4f6 !important;
}

.ybt-field-success {
    color: #166534;
    font-size: 0.75rem;
    margin-top: 0.5rem;
}

.ybt-action-group {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.ybt-btn-secondary {
    background: #1f2937;
    color: white;
}

.ybt-btn-secondary:hover {
    background: #374151;
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // Tab Navigation
    $('.ybt-nav-tab').on('click', function() {
        const tabId = $(this).data('tab');

        // Update active tab
        $('.ybt-nav-tab').removeClass('active');
        $(this).addClass('active');

        // Update active content
        $('.ybt-tab-content').removeClass('active');
        $('#' + tabId).addClass('active');

        // Save active tab to localStorage
        localStorage.setItem('ybt_active_tab', tabId);
    });

    // Restore active tab from localStorage
    const activeTab = localStorage.getItem('ybt_active_tab');
    if (activeTab) {
        $('.ybt-nav-tab[data-tab="' + activeTab + '"]').click();
    }

    // Form Validation
    $('.ybt-settings-form').on('submit', function(e) {
        let isValid = true;

        // Clear previous validation
        $('.ybt-form-error, .ybt-form-success').removeClass('ybt-form-error ybt-form-success');
        $('.ybt-validation-message').remove();

        // Validate required fields
        $('input[required], select[required], textarea[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();

            if (!value) {
                $field.addClass('ybt-form-error');
                $field.after('<div class="ybt-validation-message ybt-validation-error"><i class="fas fa-exclamation-circle"></i> This field is required</div>');
                isValid = false;
            }
        });

        // Validate email fields
        $('input[type="email"]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();

            if (value && !isValidEmail(value)) {
                $field.addClass('ybt-form-error');
                $field.after('<div class="ybt-validation-message ybt-validation-error"><i class="fas fa-exclamation-circle"></i> Please enter a valid email address</div>');
                isValid = false;
            }
        });

        // Validate number fields
        $('input[type="number"]').each(function() {
            const $field = $(this);
            const value = parseInt($field.val());
            const min = parseInt($field.attr('min'));
            const max = parseInt($field.attr('max'));

            if (!isNaN(value)) {
                if (!isNaN(min) && value < min) {
                    $field.addClass('ybt-form-error');
                    $field.after('<div class="ybt-validation-message ybt-validation-error"><i class="fas fa-exclamation-circle"></i> Value must be at least ' + min + '</div>');
                    isValid = false;
                } else if (!isNaN(max) && value > max) {
                    $field.addClass('ybt-form-error');
                    $field.after('<div class="ybt-validation-message ybt-validation-error"><i class="fas fa-exclamation-circle"></i> Value must not exceed ' + max + '</div>');
                    isValid = false;
                }
            }
        });

        if (!isValid) {
            e.preventDefault();

            // Scroll to first error
            const firstError = $('.ybt-form-error').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }

            return false;
        }

        // Show loading state
        $(this).addClass('loading');
    });

    // Real-time validation
    $('input, select, textarea').on('blur', function() {
        const $field = $(this);
        $field.removeClass('ybt-form-error ybt-form-success');
        $field.siblings('.ybt-validation-message').remove();

        // Validate based on field type
        if ($field.attr('required') && !$field.val().trim()) {
            $field.addClass('ybt-form-error');
            $field.after('<div class="ybt-validation-message ybt-validation-error"><i class="fas fa-exclamation-circle"></i> This field is required</div>');
        } else if ($field.attr('type') === 'email' && $field.val().trim() && !isValidEmail($field.val().trim())) {
            $field.addClass('ybt-form-error');
            $field.after('<div class="ybt-validation-message ybt-validation-error"><i class="fas fa-exclamation-circle"></i> Please enter a valid email address</div>');
        } else if ($field.val().trim()) {
            $field.addClass('ybt-form-success');
            $field.after('<div class="ybt-validation-message ybt-validation-success"><i class="fas fa-check-circle"></i> Valid</div>');
        }
    });

    // Toggle switch animation
    $('.ybt-toggle-switch input').on('change', function() {
        const $switch = $(this).closest('.ybt-toggle-switch');
        $switch.addClass('ybt-animate-switch');
        setTimeout(() => $switch.removeClass('ybt-animate-switch'), 200);
    });

    // Reset settings function
    window.ybtResetSettings = function() {
        if (confirm('Are you sure you want to reset all settings to their default values? This action cannot be undone.')) {
            // Show loading state
            $('.ybt-settings-form').addClass('loading');

            // Submit form with reset flag
            $('<input>').attr({
                type: 'hidden',
                name: 'ybt_reset_settings',
                value: '1'
            }).appendTo('.ybt-settings-form');

            $('.ybt-settings-form').submit();
        }
    };

    // Auto-save functionality (optional)
    let autoSaveTimeout;
    $('input, select, textarea').on('input change', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            // Show auto-save indicator
            if (!$('.ybt-auto-save-indicator').length) {
                $('.ybt-settings-footer').prepend('<div class="ybt-auto-save-indicator"><i class="fas fa-save"></i> Auto-saving...</div>');
            }

            // Remove indicator after 2 seconds
            setTimeout(() => $('.ybt-auto-save-indicator').fadeOut(() => $('.ybt-auto-save-indicator').remove()), 2000);
        }, 2000);
    });

    // Helper functions
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('.ybt-settings-form').submit();
        }

        // Escape to close any open modals or reset form
        if (e.key === 'Escape') {
            $('.ybt-form-error, .ybt-form-success').removeClass('ybt-form-error ybt-form-success');
            $('.ybt-validation-message').remove();
        }
    });

    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $($(this).attr('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });

    // Firebase (FCM) Settings Handling
    $('#ybt-save-fcm').on('click', function() {
        const $btn = $(this);
        const $json = $('#ybt-fcm-json').val().trim();
        const originalText = $btn.html();

        if (!$json) {
            alert('Please paste the Service Account JSON first.');
            return;
        }

        try {
            JSON.parse($json);
        } catch (e) {
            alert('Invalid JSON format. Please check your service account file.');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Encrypting...');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_save_fcm_settings',
                nonce: ybt_admin.nonce,
                fcm_json: $json
            },
            success: function(res) {
                $btn.prop('disabled', false).html(originalText);
                if (res.success) {
                    alert('Success! Credentials encrypted and saved.');
                    location.reload();
                } else {
                    alert('Error: ' + res.data);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                alert('Server error occurred.');
            }
        });
    });

    $('#ybt-test-fcm').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_test_fcm_connection',
                nonce: ybt_admin.nonce
            },
            success: function(res) {
                $btn.prop('disabled', false).html(originalText);
                if (res.success) {
                    alert('Connection Successful! ' + res.data);
                } else {
                    alert('Connection Failed: ' + res.data);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                alert('Server error during test.');
            }
        });
    });
});
</script>
