/**
 * YBT Reusable JavaScript Components
 * Modern ES6+ components for enhanced user experience
 */

(function ($) {
    'use strict';

    /**
     * Debounce utility
     */
    const debounce = (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    /**
     * Throttle utility
     */
    const throttle = (func, limit) => {
        let inThrottle;
        return function (...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    };

    /**
     * AJAX Request Manager
     */
    class AjaxManager {
        constructor() {
            this.activeRequests = new Map();
        }

        request(action, data = {}, options = {}) {
            const requestId = options.id || action;

            // Cancel previous request if exists
            if (this.activeRequests.has(requestId)) {
                this.activeRequests.get(requestId).abort();
            }

            const xhr = $.ajax({
                url: ybt_ajax.ajax_url,
                type: options.method || 'POST',
                data: {
                    action: action,
                    nonce: ybt_ajax.nonce,
                    ...data
                },
                beforeSend: () => {
                    if (options.beforeSend) options.beforeSend();
                },
                success: (response) => {
                    this.activeRequests.delete(requestId);
                    if (options.success) options.success(response);
                },
                error: (xhr, status, error) => {
                    this.activeRequests.delete(requestId);
                    if (options.error) options.error(xhr, status, error);
                },
                complete: () => {
                    if (options.complete) options.complete();
                }
            });

            this.activeRequests.set(requestId, xhr);
            return xhr;
        }

        cancelAll() {
            this.activeRequests.forEach(xhr => xhr.abort());
            this.activeRequests.clear();
        }
    }

    /**
     * Form Validator
     */
    class FormValidator {
        constructor(form) {
            this.form = $(form);
            this.errors = {};
        }

        validate() {
            this.errors = {};
            const fields = this.form.find('[required], [data-validate]');

            fields.each((i, field) => {
                const $field = $(field);
                const value = $field.val().trim();
                const name = $field.attr('name');
                const rules = $field.data('validate') || 'required';

                if (rules.includes('required') && !value) {
                    this.addError(name, 'This field is required');
                }

                if (rules.includes('email') && value && !this.isValidEmail(value)) {
                    this.addError(name, 'Invalid email format');
                }

                if (rules.includes('phone') && value && !this.isValidPhone(value)) {
                    this.addError(name, 'Invalid phone number');
                }

                const minLength = $field.data('min-length');
                if (minLength && value.length < minLength) {
                    this.addError(name, `Minimum length is ${minLength} characters`);
                }

                const maxLength = $field.data('max-length');
                if (maxLength && value.length > maxLength) {
                    this.addError(name, `Maximum length is ${maxLength} characters`);
                }
            });

            return Object.keys(this.errors).length === 0;
        }

        addError(field, message) {
            if (!this.errors[field]) {
                this.errors[field] = [];
            }
            this.errors[field].push(message);
        }

        showErrors() {
            this.clearErrors();

            Object.keys(this.errors).forEach(field => {
                const $field = this.form.find(`[name="${field}"]`);
                const $group = $field.closest('.form-group, .mb-3');

                $field.addClass('is-invalid');
                $group.append(`<div class="invalid-feedback d-block">${this.errors[field][0]}</div>`);
            });
        }

        clearErrors() {
            this.form.find('.is-invalid').removeClass('is-invalid');
            this.form.find('.invalid-feedback').remove();
        }

        isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        isValidPhone(phone) {
            return /^[\d\s\-\(\)\+]+$/.test(phone);
        }
    }

    /**
     * Loading Indicator
     */
    class LoadingIndicator {
        static show(target = 'body', message = 'Loading...') {
            const $target = $(target);
            const overlay = `
                <div class="ybt-loading-overlay">
                    <div class="ybt-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">${message}</span>
                        </div>
                        <p class="mt-2">${message}</p>
                    </div>
                </div>
            `;

            $target.css('position', 'relative').append(overlay);
        }

        static hide(target = 'body') {
            $(target).find('.ybt-loading-overlay').remove();
        }
    }

    /**
     * Toast Notification
     */
    class Toast {
        static show(message, type = 'info', duration = 3000) {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };

            const toast = $(`
                <div class="ybt-toast toast-${type}" role="alert">
                    <i class="fas ${icons[type]}"></i>
                    <span>${message}</span>
                    <button type="button" class="btn-close" aria-label="Close"></button>
                </div>
            `);

            $('body').append(toast);

            setTimeout(() => {
                toast.addClass('show');
            }, 10);

            toast.find('.btn-close').on('click', () => {
                this.hide(toast);
            });

            if (duration > 0) {
                setTimeout(() => {
                    this.hide(toast);
                }, duration);
            }
        }

        static hide(toast) {
            $(toast).removeClass('show');
            setTimeout(() => {
                $(toast).remove();
            }, 300);
        }
    }

    /**
     * Modal Manager
     */
    class ModalManager {
        static open(content, options = {}) {
            const modal = $(`
                <div class="ybt-modal" tabindex="-1">
                    <div class="modal-dialog ${options.size || ''}">
                        <div class="modal-content">
                            ${options.title ? `<div class="modal-header">
                                <h5 class="modal-title">${options.title}</h5>
                                <button type="button" class="btn-close" data-dismiss="modal"></button>
                            </div>` : ''}
                            <div class="modal-body">${content}</div>
                            ${options.footer ? `<div class="modal-footer">${options.footer}</div>` : ''}
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);
            modal.fadeIn(200);

            modal.find('[data-dismiss="modal"], .btn-close').on('click', () => {
                this.close(modal);
            });

            modal.on('click', (e) => {
                if ($(e.target).hasClass('ybt-modal')) {
                    this.close(modal);
                }
            });

            return modal;
        }

        static close(modal) {
            $(modal).fadeOut(200, function () {
                $(this).remove();
            });
        }
    }

    /**
     * Data Table Enhancement
     */
    class DataTable {
        constructor(table, options = {}) {
            this.table = $(table);
            this.options = {
                sortable: true,
                searchable: true,
                pagination: true,
                perPage: 20,
                ...options
            };

            this.init();
        }

        init() {
            if (this.options.searchable) {
                this.addSearch();
            }

            if (this.options.sortable) {
                this.addSorting();
            }

            if (this.options.pagination) {
                this.addPagination();
            }
        }

        addSearch() {
            const searchBox = $(`
                <div class="ybt-table-search mb-3">
                    <input type="text" class="form-control" placeholder="Search...">
                </div>
            `);

            this.table.before(searchBox);

            searchBox.find('input').on('keyup', debounce((e) => {
                const value = $(e.target).val().toLowerCase();
                this.table.find('tbody tr').each(function () {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(value));
                });
            }, 300));
        }

        addSorting() {
            this.table.find('thead th').css('cursor', 'pointer').on('click', (e) => {
                const $th = $(e.currentTarget);
                const index = $th.index();
                const isAsc = $th.hasClass('sort-asc');

                this.table.find('thead th').removeClass('sort-asc sort-desc');
                $th.addClass(isAsc ? 'sort-desc' : 'sort-asc');

                this.sortTable(index, !isAsc);
            });
        }

        sortTable(column, ascending) {
            const rows = this.table.find('tbody tr').get();

            rows.sort((a, b) => {
                const aVal = $(a).find('td').eq(column).text();
                const bVal = $(b).find('td').eq(column).text();

                if (ascending) {
                    return aVal.localeCompare(bVal, undefined, { numeric: true });
                } else {
                    return bVal.localeCompare(aVal, undefined, { numeric: true });
                }
            });

            $.each(rows, (i, row) => {
                this.table.find('tbody').append(row);
            });
        }

        addPagination() {
            // Simplified pagination - full implementation would be more complex
            const perPage = this.options.perPage;
            const rows = this.table.find('tbody tr');
            const pageCount = Math.ceil(rows.length / perPage);

            if (pageCount > 1) {
                const pagination = $('<div class="ybt-pagination mt-3"></div>');

                for (let i = 1; i <= pageCount; i++) {
                    pagination.append(`<button class="btn btn-sm btn-outline-primary mx-1" data-page="${i}">${i}</button>`);
                }

                this.table.after(pagination);

                pagination.find('button').on('click', (e) => {
                    const page = $(e.target).data('page');
                    this.showPage(page, perPage);
                });

                this.showPage(1, perPage);
            }
        }

        showPage(page, perPage) {
            const rows = this.table.find('tbody tr');
            const start = (page - 1) * perPage;
            const end = start + perPage;

            rows.hide().slice(start, end).show();
        }
    }

    // Export to global scope
    window.YBT = {
        AjaxManager,
        FormValidator,
        LoadingIndicator,
        Toast,
        ModalManager,
        DataTable,
        debounce,
        throttle
    };

})(jQuery);
