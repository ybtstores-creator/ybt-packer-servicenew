<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('Unauthorized');
/**
 * Barcode Scanner — "Scan window"
 * Consignment-note design system (ledger scope). JS contract preserved:
 * #scanner-container, #scanner-video, #start-scan, #stop-scan,
 * #manual-tracking, #manual-search, #scan-results, ybt_scan_barcode.
 */
?>

<div class="wrap ybt-ledger">
    <div class="ybt-ledgerhead">
        <div>
            <span class="ybd-eyebrow">Depot ledger · Scan window</span>
            <h1>Barcode scanner</h1>
            <p>Scan a consignment label — the register opens the matching parcel, creating it if the code is new.</p>
        </div>
        <span class="ybd-count"><i class="fas fa-video"></i> Camera</span>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:18px; align-items:start;">
        <div class="ybd-panel">
            <div class="ybd-panelhead">
                <span class="ybd-hint"><i class="fas fa-camera"></i>Live scanner</span>
            </div>
            <div class="ybd-panelbody">
                <div id="scanner-container" style="width:100%; border:1.5px dashed var(--kraft-deep); border-radius:10px; overflow:hidden; background:var(--kraft);">
                    <video id="scanner-video" style="width:100%; display:block;"></video>
                </div>

                <div style="display:flex; gap:10px; justify-content:center; margin-top:14px;">
                    <button id="start-scan" class="ybd-btn ybd-btn-emerald"><i class="fas fa-play"></i>Start scanner</button>
                    <button id="stop-scan" class="ybd-btn ybd-btn-dark"><i class="fas fa-stop"></i>Stop scanner</button>
                </div>
            </div>
        </div>

        <div class="ybd-panel">
            <div class="ybd-panelhead">
                <span class="ybd-hint"><i class="fas fa-keyboard"></i>Manual entry</span>
            </div>
            <div class="ybd-panelbody">
                <label class="ybd-formlabel">Or enter the code by hand</label>
                <input type="text" id="manual-tracking" class="ybd-input" placeholder="Enter tracking number">
                <button id="manual-search" class="ybd-btn ybd-btn-gold" style="width:100%; margin-top:14px;"><i class="fas fa-search"></i>Search</button>
                <p style="margin:10px 0 0; font-size:12.5px; color:#3d4a42;">Unknown codes are registered as anonymous parcels, ready to be claimed.</p>
            </div>
        </div>
    </div>

    <div id="scan-results" style="margin-top:18px;"></div>
</div>

<script src="https://unpkg.com/@zxing/library@0.20.0"></script>
<script>
// Debug Objects
console.log('[YBT Scanner] Initializing...');
if (typeof ZXing !== 'undefined') {
    console.log('[YBT Scanner] ZXing Library Loaded @0.20.0');
} else {
    console.error('[YBT Scanner] ZXing Library FAILED to load!');
}

// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
    console.warn('[YBT Scanner] ybt_admin was missing, initialized local fallback.');
} else {
    console.log('[YBT Scanner] Global ybt_admin found.');
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s])) : '';

jQuery(document).ready(function($) {
    let codeReader = null;
    let lastScanned = null;
    let scanTimeout = null;

    try {
        if (typeof ZXing !== 'undefined') {
            codeReader = new ZXing.BrowserMultiFormatReader();
        }
    } catch (e) {
        console.error('ZXing library not initialized:', e);
    }

    let scanning = false;

    $('#start-scan').click(function() {
        if (!codeReader) {
            alert('Scanner library not loaded.');
            return;
        }
        if (scanning) return;
        scanning = true;
        $(this).text('Scanning...').addClass('button-disabled');

        console.log('[YBT Scanner] Camera requested...');

        codeReader.decodeFromVideoDevice(null, 'scanner-video', (result, err) => {
            if (result && result.text !== lastScanned) {
                console.log('[YBT Scanner] Scanned:', result.text);
                lastScanned = result.text;

                searchTracking(result.text);

                // Continuous Scan logic:
                // Instead of codeReader.reset(), we briefly pause or just let it continue.
                // To prevent infinite scan of same code, we use a timeout.
                setTimeout(() => { lastScanned = null; }, 5000);
            }
            if (err && !(err instanceof ZXing.NotFoundException)) {
                console.warn('[YBT Scanner] Runtime error:', err);
            }
        }).catch(err => {
            console.error('[YBT Scanner] Permission/Device error:', err);
            alert('Could not access camera. Ensure you are on HTTPS and granted permissions.');
            scanning = false;
            $('#start-scan').text('Start Scanner').removeClass('button-disabled');
        });
    });

    $('#stop-scan').click(function() {
        if (codeReader) {
            codeReader.reset();
        }
        scanning = false;
        $('#start-scan').text('Start Scanner').removeClass('button-disabled');
        console.log('[YBT Scanner] Scanner stopped.');
    });

    $('#manual-search').click(function() {
        let tracking = $('#manual-tracking').val().trim();
        if (tracking) {
            searchTracking(tracking);
        } else {
            alert('Please enter a tracking number.');
        }
    });

    function searchTracking(tracking) {
        $('#scan-results').prepend('<div class="notice notice-info notice-alt"><p>Searching: <strong>' + escapeHtml(tracking) + '</strong></p></div>');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_scan_barcode',
                nonce: ybt_admin.nonce,
                barcode: tracking
            },
            success: function(response) {
                $('.notice-alt').remove(); // Clean up "Searching" notices
                if (response.success && response.data) {
                    displayResult(response.data);
                } else {
                    $('#scan-results').prepend('<div class="notice notice-error is-dismissible"><p><strong>' + escapeHtml(tracking) + ':</strong> ' + escapeHtml(response.data || 'Not found') + '</p></div>');
                }
            },
            error: function() {
                $('.notice-alt').remove();
                $('#scan-results').prepend('<div class="notice notice-error"><p>Network error connecting to scanner handler.</p></div>');
            }
        });
    }

    function displayResult(item) {
        if (!item) return;
        let isNew = item.old_status === 'new';
        let status = item.new_status || item.status;

        let html = '<div class="card" style="padding:15px 18px; border-left:4px solid ' + (isNew ? '#047857' : '#10b981') + '; margin-bottom:10px;">';
        html += '<h3 style="margin-top:0; font-family:var(--display); font-weight:400;">' + (isNew ? '🆕 Added' : '✅ Updated') + ': <span style="font-family:var(--mono);">' + escapeHtml(item.tracking_number) + '</span></h3>';
        html += '<p><strong>User:</strong> ' + escapeHtml(item.username || 'Anonymous') + '</p>';
        html += '<p><strong>Status:</strong> ' + escapeHtml(status.replace(/_/g, ' ')) + '</p>';
        if (item.message) {
            html += '<p style="color:#666; font-style:italic;">' + escapeHtml(item.message) + '</p>';
        }
        html += '</div>';

        // Keep list of last few scans
        $('#scan-results').prepend(html);
        if ($('#scan-results .card').length > 5) {
            $('#scan-results .card').last().remove();
        }
    }
});
</script>
