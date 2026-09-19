/**
 * YBT Packer Service - Modern Admin JavaScript
 * Version: 2.0
 * Description: Modern interactions and toast notifications
 */

(function($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {

        // ========================================
        // TOAST NOTIFICATIONS
        // ========================================
        var YBTToast = {
            container: null,

            init: function() {
                // Create container if not exists
                if ($('#ybt-toast-container').length === 0) {
                    $('body').append('<div id="ybt-toast-container" class="ybt-toast-container"></div>');
                }
                this.container = $('#ybt-toast-container');
            },
            
            show: function(options) {
                this.init();
                
                var defaults = {
                    type: 'info',
                    title: '',
                    message: '',
                    duration: 5000
                };
                var settings = $.extend({}, defaults, options);
                
                var icons = {
                    success: 'fas fa-check-circle',
                    error: 'fas fa-times-circle',
                    warning: 'fas fa-exclamation-triangle',
                    info: 'fas fa-info-circle'
                };
                
                var toast = $(
                    '<div class="ybt-toast ' + settings.type + '">' +
                        '<i class="ybt-toast-icon ' + icons[settings.type] + '"></i>' +
                        '<div class="ybt-toast-content">' +
                            (settings.title ? '<div class="ybt-toast-title">' + settings.title + '</div>' : '') +
                            '<div class="ybt-toast-message">' + settings.message + '</div>' +
                        '</div>' +
                        '<button class="ybt-toast-close">&times;</button>' +
                    '</div>'
                );
                
                this.container.append(toast);
                
                // Close button
                toast.find('.ybt-toast-close').on('click', function() {
                    toast.fadeOut(300, function() { $(this).remove(); });
                });
                
                // Auto close
                if (settings.duration > 0) {
                    setTimeout(function() {
                        toast.fadeOut(300, function() { $(this).remove(); });
                    }, settings.duration);
                }
            },
            
            success: function(message, title) {
                this.show({ type: 'success', title: title || 'Success', message: message });
            },
            
            error: function(message, title) {
                this.show({ type: 'error', title: title || 'Error', message: message });
            },
            
            warning: function(message, title) {
                this.show({ type: 'warning', title: title || 'Warning', message: message });
            },
            
            info: function(message, title) {
                this.show({ type: 'info', title: title || 'Info', message: message });
            }
        };
        
        // ========================================
        // FORM VALIDATION
        // ========================================
        $('.ybt-form-input[required]').on('blur', function() {
            if (!$(this).val()) {
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Clear error on input
        $('.ybt-form-input.error').on('input', function() {
            $(this).removeClass('error');
        });
        
        // ========================================
        // TABLE ROW HIGHLIGHT
        // ========================================
        $('.ybt-table tr').on('click', function(e) {
            // Don't trigger if clicking on actions
            if ($(e.target).closest('.actions').length) return;
            
            $(this).toggleClass('selected');
        });
        
        // ========================================
        // BULK SELECT
        // ========================================
        $('.ybt-bulk-select-all').on('change', function() {
            var checked = $(this).prop('checked');
            $(this).closest('.ybt-table-container')
                .find('.ybt-bulk-select')
                .prop('checked', checked);
            
            // Toggle bulk actions bar
            if (checked) {
                $('.ybt-bulk-actions').slideDown(200);
            } else {
                $('.ybt-bulk-actions').slideUp(200);
            }
        });
        
        $('.ybt-bulk-select').on('change', function() {
            var checkedCount = $(this).closest('.ybt-table-container')
                .find('.ybt-bulk-select:checked').length;
            
            if (checkedCount > 0) {
                $('.ybt-bulk-actions').slideDown(200);
            } else {
                $('.ybt-bulk-actions').slideUp(200);
            }
        });
        
        // ========================================
        // TABS
        // ========================================
        $('.ybt-tab').on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).data('target') || $(this).attr('href');
            
            // Update active tab
            $(this).siblings('.ybt-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show target content
            $(target).siblings('.ybt-tab-content').removeClass('active');
            $(target).addClass('active');
        });
        
        // ========================================
        // STAT CARDS ANIMATION
        // ========================================
        $('.ybt-stat-card').each(function(index) {
            $(this).css('animation-delay', (index * 0.1) + 's');
            $(this).addClass('ybt-fade-in');
        });
        
        // ========================================
        // TOGGLE SWITCHES
        // ========================================
        $('.ybt-toggle input').on('change', function() {
            var toggle = $(this).closest('.ybt-toggle');
            var label = toggle.data('label-on');
            var labelOff = toggle.data('label-off');
            
            if ($(this).prop('checked') && label) {
                toggle.after('<span class="ybt-toggle-label">' + label + '</span>');
            } else if (labelOff) {
                toggle.after('<span class="ybt-toggle-label">' + labelOff + '</span>');
            }
        });
        
        // ========================================
        // SKELETON LOADING
        // ========================================
        window.YBTSkeleton = {
            show: function(element) {
                $(element).addClass('ybt-skeleton');
            },
            hide: function(element) {
                $(element).removeClass('ybt-skeleton');
            }
        };
        
        // ========================================
        // AJAX FORM SUBMISSION
        // ========================================
        $(document).on('submit', '.ybt-ajax-form', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = form.find('[type="submit"]');
            var originalText = submitBtn.html();
            
            // Show loading
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            
            $.ajax({
                url: form.attr('action') || ybt_admin.ajax_url,
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        YBTToast.success(response.data.message || 'Saved successfully!');
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        }
                    } else {
                        YBTToast.error(response.data || 'An error occurred');
                    }
                },
                error: function() {
                    YBTToast.error('Network error. Please try again.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // ========================================
        // CONFIRM DIALOG
        // ========================================
        window.YBTConfirm = function(options) {
            var defaults = {
                title: 'Confirm',
                message: 'Are you sure?',
                confirmText: 'Yes',
                cancelText: 'Cancel',
                type: 'warning',
                onConfirm: function() {}
            };
            var settings = $.extend({}, defaults, options);
            
            var icon = '';
            switch(settings.type) {
                case 'danger': icon = 'fas fa-exclamation-triangle text-danger'; break;
                case 'warning': icon = 'fas fa-exclamation-triangle text-warning'; break;
                default: icon = 'fas fa-question-circle text-info';
            }
            
            var dialog = $(
                '<div class="ybt-modal-overlay">' +
                    '<div class="ybt-modal ybt-fade-in">' +
                        '<div class="ybt-modal-header">' +
                            '<h3>' + settings.title + '</h3>' +
                            '<button class="ybt-modal-close">&times;</button>' +
                        '</div>' +
                        '<div class="ybt-modal-body">' +
                            '<div class="ybt-modal-icon">' +
                                '<i class="' + icon + '"></i>' +
                            '</div>' +
                            '<p>' + settings.message + '</p>' +
                        '</div>' +
                        '<div class="ybt-modal-footer">' +
                            '<button class="ybt-btn ybt-btn-outline ybt-modal-cancel">' + settings.cancelText + '</button>' +
                            '<button class="ybt-btn ybt-btn-' + settings.type + ' ybt-modal-confirm">' + settings.confirmText + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
            
            $('body').append(dialog);
            
            // Close handlers
            dialog.find('.ybt-modal-close, .ybt-modal-cancel').on('click', function() {
                dialog.fadeOut(200, function() { $(this).remove(); });
            });
            
            dialog.find('.ybt-modal-confirm').on('click', function() {
                dialog.fadeOut(200, function() { 
                    $(this).remove(); 
                    settings.onConfirm();
                });
            });
            
            // Close on overlay click
            dialog.on('click', function(e) {
                if (e.target === dialog[0]) {
                    dialog.find('.ybt-modal-close').trigger('click');
                }
            });
        };
        
        // ========================================
        // SMOOTH SCROLL
        // ========================================
        $('.ybt-smooth-scroll').on('click', function(e) {
            e.preventDefault();
            var target = $($(this).attr('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });
        
        // ========================================
        // AUTO-HIDE ALERTS
        // ========================================
        setTimeout(function() {
            $('.ybt-alert.ybt-auto-hide').each(function() {
                $(this).slideUp(300, function() { $(this).remove(); });
            });
        }, 5000);
        
    });
    
})(jQuery);
