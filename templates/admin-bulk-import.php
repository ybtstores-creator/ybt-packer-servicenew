<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}
/**
 * Bulk Import — "Freight manifest upload"
 * Consignment-note design system (ledger scope). JS contract preserved:
 * #bulk-import-form, #csv-file, #import-status, #skip-duplicates,
 * #auto-assign-users, #download-template, #import-progress,
 * .progress-fill, #progress-text, #import-results, #results-content,
 * #recent-imports, ybt_bulk_import / ybt_get_recent_imports.
 */
?>

<div class="wrap ybt-ledger">
    <div class="ybt-ledgerhead">
        <div>
            <span class="ybd-eyebrow">Depot ledger · Manifest upload</span>
            <h1>Bulk import tracking numbers</h1>
            <p>Upload a CSV manifest — up to 1,000 rows per import.</p>
        </div>
        <a href="#" id="download-template" class="ybd-btn ybd-btn-dark"><i class="fas fa-download"></i>Download template</a>
    </div>

    <div style="display:grid; grid-template-columns:minmax(0, 2fr) minmax(0, 1fr); gap:18px; align-items:start;" class="ybt-import-grid">
        <!-- CSV Upload panel -->
        <div class="ybd-panel">
            <div class="ybd-panelhead">
                <span class="ybd-hint"><i class="fas fa-file-csv"></i>Upload CSV file</span>
            </div>
            <div class="ybd-panelbody">
                <form id="bulk-import-form" enctype="multipart/form-data">
                    <label class="ybd-formlabel">CSV file</label>
                    <input type="file" id="csv-file" accept=".csv" required class="ybd-input" style="padding:9px 12px;">
                    <p style="margin:6px 0 0; font-size:12.5px; color:#3d4a42;">Upload a CSV file with tracking numbers. <a href="#" id="download-template-inline">Download template</a></p>

                    <label class="ybd-formlabel" style="margin-top:14px;">Import status</label>
                    <select id="import-status" class="ybd-input">
                        <option value="not_received">Not received</option>
                        <option value="received">Received</option>
                    </select>
                    <p style="margin:6px 0 0; font-size:12.5px; color:#3d4a42;">Set the initial status for imported tracking numbers.</p>

                    <div style="margin-top:14px;">
                        <label class="ybd-check"><input type="checkbox" id="skip-duplicates" checked> Skip duplicate tracking numbers</label>
                        <label class="ybd-check"><input type="checkbox" id="auto-assign-users"> Auto-assign to users if username matches</label>
                    </div>

                    <button type="submit" class="ybd-btn ybd-btn-gold" style="width:100%; margin-top:18px; padding:13px 20px;">
                        <i class="fas fa-file-import"></i>Import tracking numbers
                    </button>
                </form>

                <div id="import-progress" style="display:none; margin-top:16px;">
                    <p style="font-family:var(--mono); font-size:12px; text-transform:uppercase; letter-spacing:1px; color:var(--gold-deep); margin:0 0 6px;">Import progress</p>
                    <div class="progress-bar" style="height:12px; background:var(--kraft); border:1px solid var(--kraft-deep); border-radius:8px; overflow:hidden;">
                        <div class="progress-fill" style="width:0%; height:100%; background:var(--emerald); transition:width .2s;"></div>
                    </div>
                    <div id="progress-text" style="margin-top:6px; font-size:12.5px; color:#3d4a42;">Preparing import…</div>
                </div>

                <div id="import-results" style="display:none; margin-top:16px;">
                    <p style="font-family:var(--mono); font-size:12px; text-transform:uppercase; letter-spacing:1px; color:var(--gold-deep); margin:0 0 6px;">Import results</p>
                    <div id="results-content"></div>
                </div>
            </div>
        </div>

        <!-- Format guide panel -->
        <div class="ybd-panel ybd-panel-gold">
            <div class="ybd-panelhead">
                <span class="ybd-hint"><i class="fas fa-book"></i>CSV format guide</span>
            </div>
            <div class="ybd-panelbody">
                <label class="ybd-formlabel">Required columns</label>
                <ul style="margin:4px 0 12px; padding-left:18px; font-size:13.5px; color:#3d4a42;">
                    <li><strong>tracking_number</strong> — the tracking number</li>
                </ul>

                <label class="ybd-formlabel">Optional columns</label>
                <ul style="margin:4px 0 12px; padding-left:18px; font-size:13.5px; color:#3d4a42;">
                    <li><strong>username</strong> — WordPress username</li>
                    <li><strong>status</strong> — not_received, received, packed, completed</li>
                    <li><strong>description</strong> — parcel description</li>
                </ul>

                <label class="ybd-formlabel">Example</label>
                <pre style="font-family:var(--mono); font-size:11.5px; background:var(--kraft); border:1px solid var(--kraft-deep); border-radius:8px; padding:10px; overflow-x:auto; line-height:1.6;">tracking_number,username,status,description
ABC123456789,john_doe,received,iPhone case
DEF987654321,jane_smith,not_received,Running shoes
GHI456789123,,received,Anonymous parcel</pre>

                <label class="ybd-formlabel">Tips</label>
                <ul style="margin:4px 0 0; padding-left:18px; font-size:13.5px; color:#3d4a42;">
                    <li>First row should contain column headers</li>
                    <li>Use commas to separate values</li>
                    <li>Leave username empty for anonymous parcels</li>
                    <li>Maximum 1000 rows per import</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recent imports -->
    <div class="ybd-panel" style="margin-top:18px;">
        <div class="ybd-panelhead">
            <span class="ybd-hint"><i class="fas fa-history"></i>Recent imports</span>
        </div>
        <div id="recent-imports">
            <p style="padding:20px; text-align:center; color:#3d4a42; font-family:var(--mono); font-size:13px;">Loading recent imports…</p>
        </div>
    </div>
</div>

<script>
// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s])) : '';

jQuery(document).ready(function($) {
    // Download template
    $('#download-template, #download-template-inline').click(function(e) {
        e.preventDefault();

        const csvContent = "tracking_number,username,status,description\nABC123456789,john_doe,received,iPhone case\nDEF987654321,jane_smith,not_received,Running shoes\nGHI456789123,,received,Anonymous parcel";
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ybt-tracking-template.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    // Bulk import form
    $('#bulk-import-form').submit(function(e) {
        e.preventDefault();

        const fileInput = $('#csv-file')[0];
        if (!fileInput.files.length) {
            alert('Please select a CSV file');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'ybt_bulk_import');
        formData.append('nonce', ybt_admin.nonce);
        formData.append('csv_file', fileInput.files[0]);
        formData.append('import_status', $('#import-status').val());
        formData.append('skip_duplicates', $('#skip-duplicates').is(':checked') ? '1' : '0');
        formData.append('auto_assign_users', $('#auto-assign-users').is(':checked') ? '1' : '0');

        // Show progress
        $('#import-progress').show();
        $('#import-results').hide();

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = evt.loaded / evt.total * 100;
                        $('.progress-fill').css('width', percentComplete + '%');
                        $('#progress-text').text('Uploading... ' + Math.round(percentComplete) + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#import-progress').hide();
                $('#import-results').show();

                if (response.success) {
                    const importedCount = parseInt(response.data.imported || 0);
                    const duplicateCount = parseInt(response.data.duplicates || 0);
                    const errorCount = parseInt(response.data.errors || 0);

                    if (duplicateCount > 0 && importedCount === 0) {
                        alert('DUPLICATES FOUND: ' + duplicateCount + ' tracking numbers already exist and were skipped.');
                    }

                    let resultsHtml = '<div class="notice notice-success"><p>' + escapeHtml(response.data.message) + '</p></div>' +
                        '<ul>' +
                        '<li>Successfully imported: ' + importedCount + '</li>' +
                        '<li>Skipped duplicates: ' + duplicateCount + '</li>' +
                        '<li>Errors: ' + errorCount + '</li>' +
                        '</ul>';

                    if (response.data.error_details && response.data.error_details.length > 0) {
                        resultsHtml += '<h4>Error Details:</h4><ul class="text-danger">';
                        response.data.error_details.forEach(function(err) {
                            resultsHtml += '<li>' + escapeHtml(err) + '</li>';
                        });
                        resultsHtml += '</ul>';
                    }

                    $('#results-content').html(resultsHtml);

                    // Reset form
                    $('#bulk-import-form')[0].reset();
                    loadRecentImports();
                } else {
                    $('#results-content').html('<div class="notice notice-error"><p>Error: ' + escapeHtml(response.data) + '</p></div>');
                }
            },
            error: function() {
                $('#import-progress').hide();
                $('#import-results').show();
                $('#results-content').html('<div class="notice notice-error"><p>An error occurred during import.</p></div>');
            }
        });
    });

    // Load recent imports
    function loadRecentImports() {
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_get_recent_imports',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // response.data likely contains HTML from server-side template
                    $('#recent-imports').html(response.data);
                } else {
                    $('#recent-imports').html('<p>' + escapeHtml(response.data || 'No recent imports found.') + '</p>');
                }
            },
            error: function() {
                $('#recent-imports').html('<p class="text-danger">Failed to load recent imports.</p>');
            }
        });
    }

    loadRecentImports();
});
</script>

<style>
@media (max-width: 960px){ .ybt-import-grid{ grid-template-columns:1fr !important; } }
#recent-imports table{ width:100%; border-collapse:separate; border-spacing:0; }
</style>
