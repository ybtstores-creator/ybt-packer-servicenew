<?php
/**
 * Admin Invoices Management Template
 * Lists all auto-generated invoices with filtering, actions, and detail view.
 *
 * @package YBT_Packer
 * @version 1.0
 */
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ybt-packer'));
}

wp_enqueue_style('ybt-admin-dashboard-enhanced', YBT_PLUGIN_URL . 'assets/css/admin-dashboard-enhanced.css', array(), YBT_VERSION);
?>

<div class="wrap ybt-admin-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="wp-heading-inline" style="font-size: 2rem; color: var(--text-main);">
            <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>
            <?php _e('Invoices', 'ybt-packer'); ?>
        </h1>
        <div class="d-flex gap-2">
            <button id="refresh-invoices" class="button button-secondary">
                <i class="fas fa-sync-alt me-1"></i> <?php _e('Refresh', 'ybt-packer'); ?>
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="invoice-summary-cards">
        <div class="col-md-3">
            <div class="glass-card p-3 text-center">
                <div class="h3 mb-1 fw-bold" id="stat-total" style="color: #1e293b;">—</div>
                <div class="small text-muted text-uppercase fw-bold" style="letter-spacing: 1px;">Total Invoices</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-3 text-center">
                <div class="h3 mb-1 fw-bold" id="stat-draft" style="color: #f59e0b;">—</div>
                <div class="small text-muted text-uppercase fw-bold" style="letter-spacing: 1px;">Draft</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-3 text-center">
                <div class="h3 mb-1 fw-bold" id="stat-sent" style="color: #3b82f6;">—</div>
                <div class="small text-muted text-uppercase fw-bold" style="letter-spacing: 1px;">Sent</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-3 text-center">
                <div class="h3 mb-1 fw-bold" id="stat-paid" style="color: #22c55e;">—</div>
                <div class="small text-muted text-uppercase fw-bold" style="letter-spacing: 1px;">Paid</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="glass-card mb-4">
        <div class="info-card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fas fa-filter me-2 text-muted"></i>Filters</span>
        </div>
        <div class="info-card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Status:</label>
                    <select id="filter-status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted">Search:</label>
                    <input type="text" id="filter-search" class="form-control" placeholder="Invoice #, username, or batch ID...">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button class="button button-primary" id="apply-filters"><i class="fas fa-search me-1"></i> Filter</button>
                    <button class="button" id="clear-filters"><i class="fas fa-times me-1"></i> Clear</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice List -->
    <div class="glass-card">
        <div class="info-card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fas fa-list-alt me-2 text-muted"></i>Invoice List <span id="invoice-total-display" class="text-muted fw-normal">(0)</span></span>
        </div>
        <div class="info-card-body p-0">
            <div id="invoices-loading" class="text-center py-5">
                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                <p class="text-muted mt-2">Loading invoices...</p>
            </div>
            <div id="invoices-empty" class="text-center py-5" style="display:none;">
                <i class="fas fa-file-invoice fa-3x mb-3 text-muted opacity-25"></i>
                <h4 class="text-muted">No Invoices Yet</h4>
                <p class="text-muted small">Invoices are automatically generated when batches are marked as packed.</p>
            </div>
            <table id="invoices-table" class="table admin-table mb-0" style="width:100%;display:none;">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>User</th>
                        <th>Batch</th>
                        <th class="text-center">Items</th>
                        <th class="text-center">Cartons</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoices-tbody"></tbody>
            </table>
            <div id="invoices-pagination" class="p-3 text-center border-top" style="display:none;"></div>
        </div>
    </div>
</div>

<!-- Invoice Detail Modal -->
<div id="invoice-detail-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin-top:0;">🧾 Invoice Details</h3>
        <div id="invoice-detail-content"></div>
        <div class="ybt-modal-actions">
            <button type="button" class="button close-modal">Close</button>
        </div>
    </div>
</div>

<style>
.ybt-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.65); z-index: 9999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px); }
.ybt-modal-content { background: #fff; border-radius: 12px; padding: 24px; width: 100%; max-width: 450px; box-shadow: 0 10px 40px rgba(0,0,0,0.25); }
.ybt-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.ybt-admin-wrapper .form-control { border: 1px solid #ccd0d4; border-radius: 4px; padding: 6px 12px; width: 100%; box-sizing: border-box; }

.inv-status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.inv-status-draft { background: #fef3c7; color: #92400e; }
.inv-status-sent { background: #dbeafe; color: #1e40af; }
.inv-status-paid { background: #d1fae5; color: #065f46; }
.inv-status-cancelled { background: #fee2e2; color: #991b1b; text-decoration: line-through; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.detail-item { background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
.detail-item .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 600; }
.detail-item .value { font-size: 16px; font-weight: 700; color: #1e293b; margin-top: 4px; }
.detail-item .value.green { color: #228B22; }

.detail-tracking-list { margin: 12px 0; }
.detail-tracking-list .tag {
    display: inline-block;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    margin: 2px 4px 2px 0;
    font-weight: 500;
}
</style>

<script>
jQuery(document).ready(function($) {
    const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
    const nonce = '<?php echo wp_create_nonce("ybt_admin_nonce"); ?>';
    let currentPage = 1;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s]));
    }

    function loadInvoices(page) {
        currentPage = page || 1;
        $('#invoices-loading').show();
        $('#invoices-table, #invoices-empty, #invoices-pagination').hide();

        $.post(ajaxUrl, {
            action: 'ybt_get_invoices',
            nonce: nonce,
            page: currentPage,
            status: $('#filter-status').val(),
            search: $('#filter-search').val()
        }, function(res) {
            $('#invoices-loading').hide();

            if (!res.success || !res.data.invoices.length) {
                $('#invoices-empty').show();
                $('#invoice-total-display').text('(0)');
                updateStats([], 0);
                return;
            }

            const invoices = res.data.invoices;
            const total = res.data.total;
            const pages = res.data.pages;

            $('#invoice-total-display').text('(' + total + ')');
            updateStats(invoices, total);

            let html = '';
            invoices.forEach(function(inv) {
                const statusClass = 'inv-status-' + inv.status;
                const dateFormatted = new Date(inv.date_created).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                html += '<tr data-id="' + inv.id + '">';
                html += '<td><strong style="color:#228B22;">' + escapeHtml(inv.invoice_number) + '</strong></td>';
                html += '<td>' + escapeHtml(inv.username || 'Anonymous') + '</td>';
                html += '<td><small class="text-muted">' + escapeHtml(inv.batch_id) + '</small></td>';
                html += '<td class="text-center">' + (inv.total_items || 0) + '</td>';
                html += '<td class="text-center">' + (inv.total_cartons || 0) + '</td>';
                html += '<td class="text-right fw-bold">' + escapeHtml(inv.currency) + ' ' + parseFloat(inv.grand_total || 0).toFixed(2) + '</td>';
                html += '<td><span class="inv-status-badge ' + statusClass + '">' + escapeHtml(inv.status) + '</span></td>';
                html += '<td class="small text-muted">' + dateFormatted + '</td>';
                html += '<td class="text-end">';
                html += '<div class="d-flex justify-content-end gap-1">';
                html += '<button class="button button-small view-invoice" data-id="' + inv.id + '" title="View">👁️</button>';
                html += '<a href="' + ajaxUrl + '?action=ybt_generate_invoice_pdf&nonce=' + nonce + '&invoice_id=' + inv.id + '" target="_blank" class="button button-small" title="Print">🖨️</a>';
                if (inv.status !== 'cancelled') {
                    html += '<button class="button button-small email-invoice" data-id="' + inv.id + '" title="Email to User">📧</button>';
                    html += '<button class="button button-small button-link-delete cancel-invoice text-danger" data-id="' + inv.id + '" title="Cancel">✕</button>';
                }
                html += '</div>';
                html += '</td>';
                html += '</tr>';
            });

            $('#invoices-tbody').html(html);
            $('#invoices-table').show();

            // Pagination
            if (pages > 1) {
                let pagHtml = '';
                for (let i = 1; i <= pages; i++) {
                    pagHtml += '<button class="button ' + (i === currentPage ? 'button-primary' : 'button-light') + ' invoice-page-btn" data-page="' + i + '">' + i + '</button> ';
                }
                $('#invoices-pagination').html(pagHtml).show();
            }
        });
    }

    function updateStats(invoices, total) {
        // For a proper count, load stats from all invoices (not just current page)
        $.post(ajaxUrl, {
            action: 'ybt_get_invoices',
            nonce: nonce,
            page: 1,
            status: '',
            search: ''
        }, function(res) {
            if (!res.success) return;
            let draft = 0, sent = 0, paid = 0;
            (res.data.invoices || []).forEach(function(inv) {
                if (inv.status === 'draft') draft++;
                else if (inv.status === 'sent') sent++;
                else if (inv.status === 'paid') paid++;
            });
            $('#stat-total').text(res.data.total || 0);
            $('#stat-draft').text(draft);
            $('#stat-sent').text(sent);
            $('#stat-paid').text(paid);
        });
    }

    // View Invoice Detail
    $(document).on('click', '.view-invoice', function() {
        const id = $(this).data('id');
        $.post(ajaxUrl, {
            action: 'ybt_view_invoice',
            nonce: nonce,
            invoice_id: id
        }, function(res) {
            if (!res.success) { alert(res.data); return; }
            const inv = res.data;
            const trackingItems = (inv.line_items || []).filter(i => i.type === 'tracking');
            const cartonItems = (inv.line_items || []).filter(i => i.type === 'carton');

            let html = '<div class="detail-grid">';
            html += detailItem('Invoice #', inv.invoice_number, 'green');
            html += detailItem('Status', '<span class="inv-status-badge inv-status-' + inv.status + '">' + inv.status + '</span>');
            html += detailItem('User', inv.username || 'Anonymous');
            html += detailItem('Batch', inv.batch_id);
            html += detailItem('Items', inv.total_items);
            html += detailItem('Cartons', inv.total_cartons);
            html += detailItem('Weight', parseFloat(inv.total_weight).toFixed(2) + ' KG');
            html += detailItem('Total', inv.currency + ' ' + parseFloat(inv.grand_total).toFixed(2), 'green');
            html += '</div>';

            if (inv.shipping_address) {
                html += '<div style="background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:20px;">';
                html += '<div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;font-weight:600;margin-bottom:4px;">Shipping Address</div>';
                html += '<div style="font-size:14px;color:#1e293b;">' + escapeHtml(inv.shipping_address).replace(/\n/g, '<br>') + '</div>';
                html += '</div>';
            }

            if (trackingItems.length) {
                html += '<p style="font-weight:600;font-size:13px;margin-bottom:8px;">📦 Tracking Numbers:</p>';
                html += '<div class="detail-tracking-list">';
                trackingItems.forEach(function(ti) {
                    html += '<span class="tag">' + escapeHtml(ti.tracking_number) + '</span>';
                });
                html += '</div>';
            }

            if (cartonItems.length) {
                html += '<p style="font-weight:600;font-size:13px;margin:16px 0 8px;">📋 Carton Breakdown:</p>';
                html += '<table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr><th style="text-align:left;padding:6px;background:#f1f5f9;border-bottom:1px solid #e2e8f0;">Carton</th><th style="text-align:right;padding:6px;background:#f1f5f9;border-bottom:1px solid #e2e8f0;">Weight</th><th style="text-align:right;padding:6px;background:#f1f5f9;border-bottom:1px solid #e2e8f0;">Amount</th></tr></thead><tbody>';
                cartonItems.forEach(function(ci) {
                    html += '<tr><td style="padding:6px;border-bottom:1px solid #f1f5f9;">Carton ' + ci.carton_number + '</td>';
                    html += '<td style="padding:6px;text-align:right;border-bottom:1px solid #f1f5f9;">' + parseFloat(ci.weight_kg || 0).toFixed(2) + ' KG</td>';
                    html += '<td style="padding:6px;text-align:right;border-bottom:1px solid #f1f5f9;font-weight:600;">' + escapeHtml(ci.currency || 'CNY') + ' ' + parseFloat(ci.amount || 0).toFixed(2) + '</td></tr>';
                });
                html += '</tbody></table>';
            }

            // Action Buttons  
            html += '<div style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;">';
            html += '<a href="' + ajaxUrl + '?action=ybt_generate_invoice_pdf&nonce=' + nonce + '&invoice_id=' + inv.id + '" target="_blank" class="button button-primary">🖨️ Print</a>';
            if (inv.status !== 'cancelled') {
                html += '<button class="button email-invoice" data-id="' + inv.id + '">📧 Email</button>';
                html += '<button class="button regenerate-invoice" data-id="' + inv.id + '">🔄 Regenerate</button>';
            }
            html += '</div>';

            $('#invoice-detail-content').html(html);
            $('#invoice-detail-modal').css('display', 'flex').show();
        });
    });

    function detailItem(label, value, color) {
        return '<div class="detail-item"><div class="label">' + label + '</div><div class="value ' + (color || '') + '">' + value + '</div></div>';
    }

    // Email Invoice
    $(document).on('click', '.email-invoice', function() {
        const id = $(this).data('id');
        if (!confirm('Send invoice to user via email?')) return;
        $.post(ajaxUrl, { action: 'ybt_email_invoice', nonce: nonce, invoice_id: id }, function(res) {
            alert(res.success ? 'Invoice emailed!' : (res.data || 'Failed'));
            if (res.success) loadInvoices(currentPage);
        });
    });

    // Cancel Invoice
    $(document).on('click', '.cancel-invoice', function() {
        const id = $(this).data('id');
        if (!confirm('Cancel this invoice?')) return;
        $.post(ajaxUrl, { action: 'ybt_delete_invoice', nonce: nonce, invoice_id: id }, function(res) {
            if (res.success) loadInvoices(currentPage);
            else alert(res.data || 'Failed');
        });
    });

    // Regenerate Invoice
    $(document).on('click', '.regenerate-invoice', function() {
        const id = $(this).data('id');
        if (!confirm('Cancel current invoice and generate a new one?')) return;
        $.post(ajaxUrl, { action: 'ybt_regenerate_invoice', nonce: nonce, invoice_id: id }, function(res) {
            if (res.success) {
                alert('Invoice regenerated!');
                $('.close-modal').click();
                loadInvoices(1);
            } else alert(res.data || 'Failed');
        });
    });

    // Filters
    $('#apply-filters').click(function() { loadInvoices(1); });
    $('#clear-filters').click(function() {
        $('#filter-status').val('');
        $('#filter-search').val('');
        loadInvoices(1);
    });
    $('#refresh-invoices').click(function() { loadInvoices(currentPage); });

    // Pagination
    $(document).on('click', '.invoice-page-btn', function() {
        loadInvoices($(this).data('page'));
    });

    // Modal close
    $('.close-modal').click(function() { $('.ybt-modal-overlay').hide(); });

    // Initial load
    loadInvoices(1);
});
</script>
