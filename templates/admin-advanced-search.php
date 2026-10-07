<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'ybt-packer'));
}
/**
 * Advanced Search — "Records room"
 * Consignment-note design system (ledger scope). JS contract preserved:
 * #advanced-search-form, #date_from, #date_to, #status, #username,
 * #tracking, #search-results, #export-results, ybt_advanced_search.
 */
?>

<div class="wrap ybt-ledger">
    <div class="ybt-ledgerhead">
        <div>
            <span class="ybd-eyebrow">Depot ledger · Records room</span>
            <h1>Advanced search</h1>
            <p>Query the full consignment register by date, status, customer, or tracking number.</p>
        </div>
        <button type="button" id="export-results" class="ybd-btn ybd-btn-dark"><i class="fas fa-file-export"></i>Export results</button>
    </div>

    <div class="ybd-panel ybd-panel-gold">
        <div class="ybd-panelhead">
            <span class="ybd-hint"><i class="fas fa-filter"></i>Search criteria</span>
        </div>
        <div class="ybd-panelbody">
            <form id="advanced-search-form">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
                    <div>
                        <label class="ybd-formlabel" for="date_from">Date from</label>
                        <input type="date" id="date_from" name="date_from" class="ybd-input">
                    </div>
                    <div>
                        <label class="ybd-formlabel" for="date_to">Date to</label>
                        <input type="date" id="date_to" name="date_to" class="ybd-input">
                    </div>
                    <div>
                        <label class="ybd-formlabel" for="status">Status</label>
                        <select id="status" name="status" class="ybd-input">
                            <option value="">All</option>
                            <option value="not_received">Not received</option>
                            <option value="received">Received</option>
                            <option value="packing_in_progress">Packing</option>
                            <option value="packed">Packed</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="ybd-formlabel" for="username">Username</label>
                        <input type="text" id="username" name="username" class="ybd-input" placeholder="Search username">
                    </div>
                    <div>
                        <label class="ybd-formlabel" for="tracking">Tracking #</label>
                        <input type="text" id="tracking" name="tracking" class="ybd-input" placeholder="Search tracking">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px; flex-wrap:wrap;">
                    <button type="submit" class="ybd-btn ybd-btn-gold"><i class="fas fa-search"></i>Search</button>
                    <button type="button" class="ybd-btn ybd-btn-dark" onclick="document.getElementById('advanced-search-form').reset();">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <div id="search-results" style="margin-top:18px;"></div>
</div>

<script>
    jQuery(document).ready(function($) {
        $('#advanced-search-form').submit(function(e) {
            e.preventDefault();

            var $resultsDiv = $('#search-results');
            $resultsDiv.html('<p class="info" style="padding:24px; text-align:center; color:#3d4a42; font-family:var(--mono); font-size:13px;">Searching…</p>');

            $.ajax({
                url: ybt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ybt_advanced_search',
                    nonce: ybt_admin.nonce,
                    date_from: $('#date_from').val(),
                    date_to: $('#date_to').val(),
                    status: $('#status').val(),
                    username: $('#username').val(),
                    tracking: $('#tracking').val()
                },
                success: function(response) {
                    if (response.success && response.data) {
                        displayResults(response.data);
                    } else {
                        var errorMsg = response.data || ybt_admin.error_text;
                        $resultsDiv.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                    }
                },
                error: function() {
                    $resultsDiv.html('<div class="notice notice-error"><p>' + ybt_admin.error_text + '</p></div>');
                }
            });
        });

        function displayResults(data) {
            if (!data || data.length === 0) {
                $('#search-results').html('<div class="ybd-empty"><i class="fas fa-folder-open"></i><h3>No results</h3><p>No results found matching your criteria.</p></div>');
                return;
            }

            var html = '<div class="ybd-panel" style="padding:0; overflow:hidden;"><div class="ybd-tablewrap"><table class="ybd-manifest">';
            html += '<thead><tr>';
            html += '<th><?php _e('Tracking #', 'ybt-packer'); ?></th>';
            html += '<th><?php _e('Username', 'ybt-packer'); ?></th>';
            html += '<th><?php _e('Status', 'ybt-packer'); ?></th>';
            html += '<th><?php _e('Date Added', 'ybt-packer'); ?></th>';
            html += '<th><?php _e('Date Received', 'ybt-packer'); ?></th>';
            html += '<th><?php _e('Description', 'ybt-packer'); ?></th>';
            html += '</tr></thead><tbody>';

            $.each(data, function(index, item) {
                var statusCls = 'ybd-chip ybd-chip-' + String(item.status || '').replace(/[^a-z_]/g, '');
                html += '<tr>';
                html += '<td><span class="ybd-trackno">' + escapeHtml(item.tracking_number) + '</span></td>';
                html += '<td>' + escapeHtml(item.username || 'Anonymous') + '</td>';
                html += '<td><span class="' + statusCls + '">' + escapeHtml(String(item.status || '').replace(/_/g, ' ')) + '</span></td>';
                html += '<td class="ybd-date">' + escapeHtml(item.date_added || '-') + '</td>';
                html += '<td class="ybd-date">' + escapeHtml(item.date_received || '-') + '</td>';
                html += '<td>' + escapeHtml(item.description || '-') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div></div>';
            $('#search-results').html(html);
        }

        // Simple HTML escape function
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });
</script>
