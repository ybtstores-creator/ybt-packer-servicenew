<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}
?>

<div class="wrap">
    <h1><i class="dashicons dashicons-upload"></i> Bulk Import Tracking Numbers</h1>

    <div class="ybt-admin-container">
        <!-- CSV Upload -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Upload CSV File</h3>
                    </div>
                    <div class="card-body">
                        <form id="bulk-import-form" enctype="multipart/form-data">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">CSV File</th>
                                    <td>
                                        <input type="file" id="csv-file" accept=".csv" required>
                                        <p class="description">
                                            Upload a CSV file with tracking numbers.
                                            <a href="#" id="download-template">Download template</a>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Import Status</th>
                                    <td>
                                        <select id="import-status">
                                            <option value="not_received">Not Received</option>
                                            <option value="received">Received</option>
                                        </select>
                                        <p class="description">Set the initial status for imported tracking numbers</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Options</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="skip-duplicates" checked>
                                            Skip duplicate tracking numbers
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" id="auto-assign-users">
                                            Auto-assign to users if username matches
                                        </label>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button-primary">Import Tracking Numbers</button>
                            </p>
                        </form>

                        <div id="import-progress" style="display: none;">
                            <h4>Import Progress</h4>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%"></div>
                            </div>
                            <div id="progress-text">Preparing import...</div>
                        </div>

                        <div id="import-results" style="display: none;">
                            <h4>Import Results</h4>
                            <div id="results-content"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CSV Format Guide -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3>CSV Format Guide</h3>
                    </div>
                    <div class="card-body">
                        <h4>Required Columns:</h4>
                        <ul>
                            <li><strong>tracking_number</strong> - The tracking number</li>
                        </ul>

                        <h4>Optional Columns:</h4>
                        <ul>
                            <li><strong>username</strong> - WordPress username</li>
                            <li><strong>status</strong> - not_received, received, packed, completed</li>
                            <li><strong>description</strong> - Parcel description</li>
                        </ul>

                        <h4>Example CSV:</h4>
                        <pre>tracking_number,username,status,description
ABC123456789,john_doe,received,iPhone case
DEF987654321,jane_smith,not_received,Running shoes
GHI456789123,,received,Anonymous parcel</pre>

                        <div class="csv-tips">
                            <h4>Tips:</h4>
                            <ul>
                                <li>First row should contain column headers</li>
                                <li>Use commas to separate values</li>
                                <li>Leave username empty for anonymous parcels</li>
                                <li>Maximum 1000 rows per import</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Imports -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Imports</h3>
                    </div>
                    <div class="card-body">
                        <div id="recent-imports">
                            <p>Loading recent imports...</p>
                        </div>
                    </div>
                </div>
            </div>
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

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';

jQuery(document).ready(function($) {
    // Download template
    $('#download-template').click(function(e) {
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

    // Load on page load
    loadRecentImports();
});
</script>

<style>
.csv-tips {
    background: #f0f8ff;
    padding: 15px;
    border-radius: 5px;
    margin-top: 15px;
}

.csv-tips h4 {
    color: #0073aa;
    margin-top: 0;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #228B22, #DAA520);
    transition: width 0.3s ease;
}

#progress-text {
    text-align: center;
    font-weight: bold;
    color: #666;
}

pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    overflow-x: auto;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -15px;
}

.col-md-4, .col-md-8, .col-md-12 {
    padding: 0 15px;
}

.col-md-4 {
    flex: 0 0 33.333333%;
    max-width: 33.333333%;
}

.col-md-8 {
    flex: 0 0 66.666667%;
    max-width: 66.666667%;
}

.col-md-12 {
    flex: 0 0 100%;
    max-width: 100%;
}

.mt-4 {
    margin-top: 1.5rem;
}

.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    background: linear-gradient(135deg, #228B22, #DAA520);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 15px 20px;
}

.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
}
</style>
