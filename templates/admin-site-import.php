<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Site Import</h1>

    <div class="card">
        <h2>Import Site Data</h2>
        <p>Import YBT Packer Service data from a previously exported JSON file.</p>

        <form id="import-form" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="import_file">Import File</label>
                    </th>
                    <td>
                        <input type="file" id="import_file" name="import_file" accept=".json" required>
                        <p class="description">Select a JSON export file from YBT Packer Service</p>
                    </td>
                </tr>
            </table>

            <div class="import-actions">
                <button type="submit" class="button button-primary">Import Data</button>
                <div id="import-status" style="margin-top: 10px;"></div>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Import Instructions</h3>
        <ol>
            <li>Select a JSON export file created by the YBT Site Export feature</li>
            <li>Click "Import Data" to begin the import process</li>
            <li>Wait for the import to complete - this may take a few moments</li>
            <li>Review the import results to ensure all data was imported correctly</li>
        </ol>

        <div class="notice notice-warning">
            <p><strong>Warning:</strong> Importing data will add to your existing data. It will not replace or delete existing records. If you want to replace all data, use the Reset Data feature first.</p>
        </div>
    </div>

    <div class="card">
        <h3>Import Safety</h3>
        <ul>
            <li>Always backup your current data before importing</li>
            <li>Test imports on a staging site first</li>
            <li>Verify the import file is from a trusted source</li>
            <li>Check that the file format matches YBT export format</li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#import-form').submit(function(e) {
        e.preventDefault();

        var form = $(this);
        var button = form.find('button[type="submit"]');
        var status = $('#import-status');
        var fileInput = $('#import_file')[0];

        if (!fileInput.files.length) {
            status.html('<div class="notice notice-error"><p>Please select a file to import.</p></div>');
            return;
        }

        var file = fileInput.files[0];
        if (!file.name.toLowerCase().endsWith('.json')) {
            status.html('<div class="notice notice-error"><p>Please select a valid JSON file.</p></div>');
            return;
        }

        button.prop('disabled', true).text('Importing...');
        status.html('<div class="notice notice-info"><p>Importing data, please wait...</p></div>');

        var formData = new FormData();
        formData.append('action', 'ybt_site_import');
        formData.append('nonce', '<?php echo wp_create_nonce('ybt_admin_nonce'); ?>');
        formData.append('import_file', file);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                button.prop('disabled', false).text('Import Data');

                if (response.success) {
                    status.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    form[0].reset();
                } else {
                    status.html('<div class="notice notice-error"><p>Import failed: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                button.prop('disabled', false).text('Import Data');
                status.html('<div class="notice notice-error"><p>Import failed due to a server error.</p></div>');
            }
        });
    });
});
</script>

<style>
.import-actions {
    margin-top: 20px;
}

.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.card ul {
    list-style-type: disc;
    margin-left: 20px;
}

.card li {
    margin-bottom: 5px;
}
</style>
