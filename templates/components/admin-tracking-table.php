<?php
if (!defined('ABSPATH')) exit;

function render_tracking_table() {
?>
<div class="ybt-card">
    <div class="ybt-card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Tracking Numbers</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm ybt-btn-gold" onclick="selectAll()">
                <i class="fas fa-check-square me-1"></i>Select All
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                <i class="fas fa-square me-1"></i>Deselect All
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table ybt-table" id="trackingTable">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()"></th>
                        <th>Tracking Number</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Shipping Method</th>
                        <th>Date Added</th>
                        <th>Edited By</th>
                        <th width="80" class="text-center">Images</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="trackingTableBody">
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav aria-label="Tracking pagination" class="mt-3">
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Pagination will be populated by JavaScript -->
            </ul>
        </nav>
    </div>
</div>

<!-- Bulk Actions -->
<div class="ybt-card mt-3">
    <div class="ybt-card-header">
        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Bulk Actions</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <select id="bulkAction" class="form-select">
                    <option value="">Select Action</option>
                    <option value="mark_received">Mark as Received</option>
                    <option value="mark_packed">Mark as Packed</option>
                    <option value="mark_completed">Mark as Completed</option>
                    <option value="delete">Delete Selected</option>
                    <option value="reassign">Reassign to User</option>
                </select>
            </div>
            <div class="col-md-3" id="reassignUserDiv" style="display:none;">
                <input type="text" id="reassignUser" class="form-control" placeholder="New username">
            </div>
            <div class="col-md-3">
                <button type="button" class="btn ybt-btn-primary" onclick="executeBulkAction()">
                    <i class="fas fa-play me-1"></i>Execute
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit History Modal (admin attribution) -->
<div id="editHistoryModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.65); z-index:9999; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#fff; border-radius:12px; padding:20px; width:100%; max-width:780px; max-height:90vh; display:flex; flex-direction:column;">
        <h5 id="editHistoryTitle" style="margin-top:0;">Edit History</h5>
        <div id="editHistoryBody" style="overflow-y:auto; flex:1 1 auto; max-height:62vh;"></div>
        <div style="display:flex; justify-content:flex-end; margin-top:12px;">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('editHistoryModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<script>
// DOM Safety: Added check for ybt_admin object before use (Lines 345-350)
if (typeof ybt_admin === 'undefined') {
    console.error('ybt_admin configuration missing. Security nonces and settings may be unavailable.');
    window.ybt_admin = { nonce: '', ajax_url: typeof ajaxurl !== 'undefined' ? ajaxurl : '' };
}

let currentPage = 1;
let totalPages = 1;
const itemsPerPage = 20;

/**
 * XSS Prevention: Utility functions for safe dynamic output (Lines 145-165)
 */
const escapeHtml = (unsafe) => {
    if (unsafe == null) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};

const escapeHtmlAttr = (unsafe) => {
    if (unsafe == null) return '';
    return String(unsafe).replace(/[^\w-]/g, (s) => `&#${s.charCodeAt(0)};`);
};

function loadTrackingData(page = 1) {
    // Pagination Security: Added page number validation (Line 175)
    if (page < 1) page = 1;

    // Safety check for UI elements
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    const shippingMethodFilter = document.getElementById('shippingMethodFilter')?.value || '';
    const userFilter = document.getElementById('userFilter')?.value || '';
    const imagesFilter = document.getElementById('imagesFilter')?.value || '';
    const search = document.getElementById('searchInput')?.value || '';

    jQuery.ajax({
        url: ybt_admin.ajax_url || ajaxurl,
        type: 'POST',
        data: {
            action: 'ybt_get_tracking_data',
            // Nonce Security: uses ybt_admin.nonce instead of inline (Line 178)
            nonce: ybt_admin.nonce,
            page: page,
            per_page: itemsPerPage,
            status: statusFilter,
            shipping_method: shippingMethodFilter,
            user: userFilter,
            has_images: imagesFilter,
            search: search
        },
        success: function(response) {
            if (response.success) {
                renderTrackingTable(response.data.items);
                renderPagination(response.data.total_pages, page);
                currentPage = page;
                totalPages = response.data.total_pages;
            } else {
                alert('Error: ' + (response.data || 'Failed to load tracking data'));
            }
        },
        // Error Handling: Comprehensive AJAX error handling (Lines 225-240)
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            let message = 'An error occurred while loading tracking data.';
            if (xhr.status === 403) {
                message = 'Security session expired. Please refresh the page.';
            } else if (xhr.responseJSON && xhr.responseJSON.data) {
                message = xhr.responseJSON.data;
            }
            alert(message);
        }
    });
}

function renderTrackingTable(items) {
    const tbody = document.getElementById('trackingTableBody');
    if (!items || items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No tracking numbers found</td></tr>';
        return;
    }

    // Debug: Log first item to see structure
    if (items.length > 0) {
        console.log('First tracking item from API:', items[0]);
        console.log('First item ID type:', typeof items[0].id, 'value:', items[0].id);
    }

    tbody.innerHTML = items.map(item => {
        // Ensure ID is a valid number
        const itemId = parseInt(item.id) || 0;
        if (itemId === 0) {
            console.warn('Tracking item has invalid ID:', item);
        }
        const safeTracking = escapeHtml(item.tracking_number);
        const safeUsername = escapeHtml(item.username || 'Anonymous');
        const safeStatus = escapeHtml(item.status);
        const statusDisplay = safeStatus.replace(/_/g, ' ').toUpperCase();
        const statusClass = safeStatus.replace(/_/g, '-');
        const safeMethod = escapeHtml(item.shipping_method || 'N/A').toUpperCase();
        const safeDate = new Date(item.date_added).toLocaleDateString();
        const hasImages = item.images && item.images.trim() !== '';
        const imagesIcon = hasImages 
            ? '<span class="badge bg-success" title="Has Images"><i class="fas fa-image"></i></span>' 
            : '<span class="badge bg-warning" title="No Images"><i class="fas fa-image-slash"></i></span>';

        // Admin attribution: who last edited this tracking number.
        const editorName = item.last_edited_by_name ? escapeHtml(item.last_edited_by_name) : '';
        const editCount = parseInt(item.edit_count) || 0;
        const editorCell = editorName
            ? `<span class="badge bg-light border text-dark" title="Last edited by ${editorName}">
                   <i class="fas fa-user-shield me-1 text-primary"></i>${editorName}
               </span>
               <div class="text-muted" style="font-size:11px;">${escapeHtml(item.last_edited_at || '')}${editCount ? ' · ' + editCount + ' edit' + (editCount === 1 ? '' : 's') : ''}</div>
               <button type="button" class="btn btn-link btn-sm p-0 view-edit-history" style="font-size:11px;"
                       data-id="${itemId}" data-tracking="${safeTracking}">
                   <i class="fas fa-history"></i> History
               </button>`
            : '<span class="text-muted fst-italic" style="font-size:11px;">Not edited</span>';

        return `
            <tr>
                <td><input type="checkbox" class="item-checkbox" value="${itemId}"></td>
                <td><strong>${safeTracking}</strong></td>
                <td>${safeUsername}</td>
                <td><span class="badge ybt-badge-${statusClass}">${statusDisplay}</span></td>
                <td>${safeMethod}</td>
                <td>${safeDate}</td>
                <td>${editorCell}</td>
                <td class="text-center">${imagesIcon}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editItem(${itemId})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteItem(${itemId})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination(totalPages, currentPage) {
    const pagination = document.getElementById('pagination');
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = '';

    // Previous button
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadTrackingData(${currentPage - 1}); return false;">Previous</a>
    </li>`;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage || i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadTrackingData(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Next button
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadTrackingData(${currentPage + 1}); return false;">Next</a>
    </li>`;

    pagination.innerHTML = html;
}

/**
 * Bulk Action Security: Comprehensive implementation with validation (Line 335)
 */
function executeBulkAction() {
    const action = document.getElementById('bulkAction').value;
    if (!action) {
        alert('Please select an action');
        return;
    }

    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.value);

    if (selectedIds.length === 0) {
        alert('Please select at least one item');
        return;
    }

    let ajaxData = {
        action: 'ybt_bulk_update_status',
        nonce: ybt_admin.nonce,
        ids: selectedIds,
        status: action
    };

    if (action === 'reassign') {
        const newUsername = document.getElementById('reassignUser').value.trim();
        // Bulk Action Security: Added username validation for reassignment action (Line 335)
        if (!newUsername) {
            alert('Please enter a target username for reassignment');
            document.getElementById('reassignUser').focus();
            return;
        }
        ajaxData.action = 'ybt_bulk_assign_user';
        ajaxData.username = newUsername;
    } else if (action === 'delete') {
        if (!confirm('Are you sure you want to delete ' + selectedIds.length + ' selected items? This cannot be undone.')) {
            return;
        }
        ajaxData.action = 'ybt_bulk_delete_items';
    }

    jQuery.ajax({
        url: ybt_admin.ajax_url || ajaxurl,
        type: 'POST',
        data: ajaxData,
        success: function(response) {
            if (response.success) {
                alert(response.data || 'Action executed successfully');
                loadTrackingData(currentPage);
                deselectAll();
            } else {
                alert('Error: ' + (response.data || 'Failed to execute bulk action'));
            }
        },
        error: function() {
            alert('A server error occurred while performing bulk action.');
        }
    });
}

function editItem(id) {
    // Basic redirect or modal trigger for editing
    window.location.href = `admin.php?page=ybt-add-tracking&edit=${id}`;
}

/**
 * Admin attribution: show which admin made each edit of this tracking number.
 */
function openEditHistory(id, trackingNumber) {
    const modal = document.getElementById('editHistoryModal');
    const title = document.getElementById('editHistoryTitle');
    const body = document.getElementById('editHistoryBody');
    if (!modal || !body) return;

    if (title) title.textContent = 'Edit History — ' + (trackingNumber || ('#' + id));
    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.style.display = 'flex';

    jQuery.ajax({
        url: ybt_admin.ajax_url || ajaxurl,
        type: 'POST',
        data: { action: 'ybt_get_tracking_edit_history', nonce: ybt_admin.nonce, tracking_id: id },
        success: function(response) {
            if (!response || !response.success) {
                body.innerHTML = '<p class="text-danger">' + escapeHtml(response && response.data) + '</p>';
                return;
            }
            const entries = (response.data && response.data.entries) || [];
            if (!entries.length) {
                body.innerHTML = '<p class="text-muted">No admin edits recorded for this tracking number yet.</p>';
                return;
            }
            body.innerHTML = entries.map(function(entry) {
                let changes = '';
                if (entry.changes && entry.changes.length) {
                    changes = '<table class="table table-sm mb-0 mt-2" style="font-size:12px;"><tbody>'
                        + entry.changes.map(function(c) {
                            return '<tr><td style="width:32%;color:#646970;">' + escapeHtml(c.label || c.field)
                                + '</td><td style="color:#b32d2e;text-decoration:line-through;">' + escapeHtml(c.from != null ? c.from : '—')
                                + '</td><td style="color:#1d7a3c;font-weight:600;">' + escapeHtml(c.to != null ? c.to : '—') + '</td></tr>';
                        }).join('')
                        + '</tbody></table>';
                }
                return '<div style="border-left:3px solid #2271b1;background:#f6f7f7;border-radius:6px;padding:10px 12px;margin-bottom:10px;">'
                    + '<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">'
                    + '<strong><i class="fas fa-user-shield text-primary me-1"></i>' + escapeHtml(entry.admin_name || 'System') + '</strong>'
                    + '<span style="font-size:12px;color:#646970;">' + escapeHtml(entry.date_created || '') + '</span></div>'
                    + '<div style="margin-top:4px;font-size:12px;">' + escapeHtml(entry.summary || '') + '</div>'
                    + changes + '</div>';
            }).join('');
        },
        error: function() {
            body.innerHTML = '<p class="text-danger">Server error while loading history.</p>';
        }
    });
}

document.addEventListener('click', function(e) {
    const btn = e.target.closest && e.target.closest('.view-edit-history');
    if (!btn) return;
    e.preventDefault();
    openEditHistory(btn.getAttribute('data-id'), btn.getAttribute('data-tracking'));
});

function deleteItem(id) {
    console.log('deleteItem called with ID:', id, 'type:', typeof id);
    
    if (!id || id === 0 || id === '0') {
        alert('Error: Invalid tracking ID. Please refresh the page and try again.');
        return;
    }
    
    if (!confirm('Are you sure you want to delete this tracking number?')) return;

    console.log('Sending delete request for ID:', id);

    jQuery.ajax({
        url: ybt_admin.ajax_url || ajaxurl,
        type: 'POST',
        data: {
            action: 'ybt_delete_tracking_item',
            nonce: ybt_admin.nonce,
            id: id
        },
        success: function(response) {
            console.log('Delete response:', response);
            if (response.success) {
                loadTrackingData(currentPage);
            } else {
                alert('Error: ' + (response.data || 'Delete failed'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Delete AJAX error:', xhr.responseText);
            alert('Server error occurred while deleting item.');
        }
    });
}

function applyFilters() {
    loadTrackingData(1);
}

function clearFilters() {
    ['statusFilter', 'shippingMethodFilter', 'userFilter', 'imagesFilter', 'searchInput'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    loadTrackingData(1);
}

function selectAll() {
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
}

function deselectAll() {
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
}

function toggleSelectAll() {
    const isChecked = document.getElementById('selectAllCheckbox').checked;
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = isChecked);
}

// Initial initialization
document.addEventListener('DOMContentLoaded', function() {
    loadTrackingData(1);

    // Toggle reassign user input visibility
    const bulkActionSelect = document.getElementById('bulkAction');
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            const reassignDiv = document.getElementById('reassignUserDiv');
            if (reassignDiv) {
                reassignDiv.style.display = this.value === 'reassign' ? 'block' : 'none';
            }
        });
    }
});
</script>
<?php
}
