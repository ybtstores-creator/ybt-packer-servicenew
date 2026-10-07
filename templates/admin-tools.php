<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}
?>

<div class="wrap ybt-admin-wrapper">
    <h1 class="ybt-page-title"><i class="dashicons dashicons-admin-tools"></i> Tools & Data Management</h1>

    <div class="ybt-admin-container">

        <div class="row">
            <!-- EXPORT SECTION -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3><i class="dashicons dashicons-download"></i> Export Site Data</h3>
                    </div>
                    <div class="card-body">
                        <p>Download a complete JSON backup of your system. This includes:</p>
                        <ul style="list-style: disc; margin-left: 20px; color: #666;">
                            <li>Tracking Numbers & Statuses</li>
                            <li>Chat History</li>
                            <li>Carton Configurations</li>
                            <li>Settings (Warehouse, Payment)</li>
                        </ul>
                        <button id="btn-export-site" class="button button-primary button-large" style="margin-top: 15px;">
                            Download Backup File
                        </button>
                    </div>
                </div>
            </div>

            <!-- IMPORT SECTION -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3><i class="dashicons dashicons-upload"></i> Import Site Data</h3>
                    </div>
                    <div class="card-body">
                        <p>Restore data from a previously exported JSON file.</p>
                        <div class="notice notice-warning inline">
                            <p><strong>Warning:</strong> This action adds to existing data. It tries not to create duplicates, but be careful.</p>
                        </div>
                        <form id="form-import-site" style="margin-top: 15px;">
                            <input type="file" name="import_file" accept=".json" required style="margin-bottom: 10px; width: 100%;">
                            <button type="submit" class="button button-secondary">
                                Upload & Import Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top: 20px;">
            <!-- DANGER ZONE: RESET -->
            <div class="col-md-6">
                <div class="card h-100" style="border-left: 4px solid #dc3545;">
                    <div class="card-header">
                        <h3 style="color: #dc3545;"><i class="dashicons dashicons-trash"></i> Reset All Data</h3>
                    </div>
                    <div class="card-body">
                        <p style="color: #dc3545; font-weight: bold;">⚠️ DANGER ZONE</p>
                        <p>This will completely <strong>DELETE</strong> all:</p>
                        <ul style="list-style: disc; margin-left: 20px; margin-bottom: 15px;">
                            <li>Tracking Numbers</li>
                            <li>Chat Messages</li>
                            <li>Activity Logs</li>
                            <li>Carton Data</li>
                        </ul>
                        <p><em>Users and Settings will NOT be deleted.</em></p>
                        <button id="btn-reset-all" class="button button-link-delete" style="border: 1px solid #dc3545; padding: 5px 10px;">
                            Reset Everything
                        </button>
                    </div>
                </div>
            </div>

            <!-- USER RESET -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3><i class="dashicons dashicons-id"></i> Reset Specific User</h3>
                    </div>
                    <div class="card-body">
                        <p>Wipe all data associated with a specific username (Tracking & Chat).</p>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <input type="text" id="reset-user-input" placeholder="Enter Username" class="regular-text">
                            <button id="btn-reset-user" class="button">Reset User</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAINTENANCE TOOLS -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3><i class="dashicons dashicons-hammer"></i> Maintenance Tools</h3>
            </div>
            <div class="card-body">
                <table class="widefat fixed striped" style="border:none;">
                    <tbody>
                        <tr>
                            <td><strong>Assign User IDs</strong><br><span class="description">Assigns unique IDs to users with 'Pending' or missing IDs.</span></td>
                            <td style="text-align: right;"><button id="btn-assign-ids" class="button">Assign IDs</button></td>
                        </tr>
                        <tr>
                            <td><strong>Fix Database Tables</strong><br><span class="description">Re-runs the table creation script. Use if you see SQL errors.</span></td>
                            <td style="text-align: right;"><button id="btn-fix-db" class="button">Run Fix</button></td>
                        </tr>
                        <tr>
                            <td><strong>Fix Null Shipping Methods</strong><br><span class="description">Sets any blank shipping methods to 'Sea' default.</span></td>
                            <td style="text-align: right;"><button id="btn-fix-nulls" class="button">Run Fix</button></td>
                        </tr>
                        <tr>
                            <td><strong>Fix Packed Items Date</strong><br><span class="description">Updates date_packed for existing packed items that are missing the timestamp.</span></td>
                            <td style="text-align: right;"><button id="btn-fix-packed-date" class="button">Run Fix</button></td>
                        </tr>
                        <tr>
                            <td><strong>Optimize Images</strong><br><span class="description">Cleans up unused image references in the database.</span></td>
                            <td style="text-align: right;"><button id="btn-opt-img" class="button">Run Optimization</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Use wp_localize_script data from enqueue_admin_assets (class-ybt-admin.php)
    var ajaxUrl = typeof ybt_admin !== 'undefined' ? ybt_admin.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = typeof ybt_admin !== 'undefined' ? ybt_admin.nonce : '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>';

    // 1. Export Data
    $('#btn-export-site').click(function() {
        window.location.href = ajaxUrl + '?action=ybt_site_export&nonce=' + nonce;
    });

    // 2. Import Data
    $('#form-import-site').submit(function(e) {
        e.preventDefault();
        if(!confirm('Are you sure? This will import data into the database.')) return;

        const btn = $(this).find('button');
        const originalText = btn.text();
        btn.prop('disabled', true).text('Importing...');

        var fd = new FormData(this);
        fd.append('action', 'ybt_site_import');
        fd.append('nonce', nonce);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(r) {
                alert(r.data);
                btn.prop('disabled', false).text(originalText);
                if(r.success) location.reload();
            },
            error: function() {
                alert('Import failed due to server error.');
                btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // 3. Reset All Data
    $('#btn-reset-all').click(function() {
        if(confirm('DANGER: Are you sure you want to delete ALL tracking data, chats, and logs? This cannot be undone.')) {
            $.post(ajaxUrl, { action: 'ybt_reset_data', nonce: nonce, confirm_truncate: 'YES_I_AM_SURE' }, function(r) {
                alert(r.data);
                location.reload();
            });
        }
    });

    // 4. Reset Specific User
    $('#btn-reset-user').click(function() {
        const u = $('#reset-user-input').val();
        if(!u) { alert('Please enter a username.'); return; }

        if(confirm('Delete all tracking and chat data for user: ' + u + '?')) {
            $.post(ajaxUrl, { action: 'ybt_reset_user_data', nonce: nonce, username: u }, function(r) {
                alert(r.data);
                $('#reset-user-input').val('');
            });
        }
    });

    // 5. Maintenance Buttons
    $('#btn-assign-ids').click(function() {
        const btn = $(this);
        if(!confirm('Assign unique IDs to all users without IDs?')) return;
        btn.prop('disabled', true).text('Assigning...');
        $.post(ajaxUrl, {action:'ybt_assign_missing_user_ids', nonce:nonce}, function(r) {
            alert(r.data);
            btn.prop('disabled', false).text('Assign IDs');
        });
    });

    $('#btn-fix-db').click(function() {
        const btn = $(this);
        if(!confirm('Re-run the table creation script? This is non-destructive.')) return;
        btn.prop('disabled', true).text('Running...');
        $.post(ajaxUrl, {action:'ybt_recreate_tables', nonce:nonce}, function(r) {
            alert(r.data);
            btn.prop('disabled', false).text('Run Fix');
        });
    });

    $('#btn-fix-nulls').click(function() {
        const btn = $(this);
        if(!confirm('Set all blank shipping methods to Sea?')) return;
        btn.prop('disabled', true).text('Running...');
        $.post(ajaxUrl, {action:'ybt_fix_null_shipping', nonce:nonce}, function(r) {
            alert(r.data);
            btn.prop('disabled', false).text('Run Fix');
        });
    });

    $('#btn-fix-packed-date').click(function() {
        const btn = $(this);
        if(!confirm('This will update date_packed for all packed items that are missing the timestamp. Continue?')) return;
        btn.prop('disabled', true).text('Running...');
        $.post(ajaxUrl, {action:'ybt_fix_packed_items_date', nonce:nonce}, function(r) {
            if (r.success) {
                alert(typeof r.data === 'string' ? r.data : (r.data.message || 'Fix completed successfully'));
            } else {
                alert('Error: ' + (typeof r.data === 'string' ? r.data : 'Unknown error'));
            }
            btn.prop('disabled', false).text('Run Fix');
        });
    });

    $('#btn-opt-img').click(function() {
        const btn = $(this);
        if(!confirm('Clean up unused image references in the database? This is non-destructive.')) return;
        btn.prop('disabled', true).text('Running...');
        $.post(ajaxUrl, {action:'ybt_optimize_existing_images', nonce:nonce}, function(r) {
            alert(r.data);
            btn.prop('disabled', false).text('Run Optimization');
        });
    });
});
</script>

<style>
    /* Minimal CSS for Tools Page */
    .ybt-admin-container { max-width: 1200px; margin-top: 20px; }
    .card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 4px rgba(0,0,0,0.05); margin-bottom: 20px; border-radius: 4px; }
    .card-header { border-bottom: 1px solid #eee; padding: 10px 20px; background: #f9f9f9; }
    .card-header h3 { margin: 0; color: #23282d; font-size: 1.1em; }
    .card-body { padding: 20px; }
    .h-100 { height: 100%; }

    /* Grid (If main CSS fails) */
    .row { display: flex; flex-wrap: wrap; margin: 0 -10px; }
    .col-md-6 { width: 50%; padding: 0 10px; box-sizing: border-box; }
    @media (max-width: 768px) { .col-md-6 { width: 100%; } }
</style>
