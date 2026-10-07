<?php
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) { echo '<div class="alert alert-warning">Please login to view received parcels.</div>'; return; }

$current_user = wp_get_current_user();
global $wpdb;

// ====== DASHBOARD SETTINGS ======
add_action('wp_head', function() {
    echo '<style>
        #wpadminbar { display: none !important; }
        body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        html { margin-top: 0 !important; }
    </style>';
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: blob: https:; connect-src \'self\' https:;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);

// Pagination
$per_page = 25;
$current_page = max(1, get_query_var('paged', 1));
$offset = ($current_page - 1) * $per_page;

// Fetch Data
$items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'received' ORDER BY date_received DESC LIMIT %d OFFSET %d", get_current_user_id(), $current_user->user_login, $per_page, $offset));

$total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking WHERE (user_id = %d OR username = %s) AND status = 'received'", get_current_user_id(), $current_user->user_login));
$total_pages = ceil($total_items / $per_page);

require YBT_PLUGIN_PATH . 'templates/components/status-page-styles.php';
?>

<div class="ybd-page">

    <!-- Page header -->
    <div class="ybd-pagehead">
        <div>
            <p class="ybd-eyebrow">Stage 3 of 5 &middot; Warehouse register</p>
            <h2>In warehouse</h2>
            <p class="ybd-sub">Parcels received and inspected — select the ones to pack together.</p>
        </div>
        <span class="ybd-count">Total received: <?php echo intval($total_items); ?></span>
    </div>

    <form id="packing-request-form">
        <!-- Floating Selection Badge (JS toggles it) -->
        <div id="selection-badge" style="position:fixed; top:85px; right:24px; z-index:1050; display:none;">
            <div class="ybd-selbadge">
                <i class="fas fa-check-double"></i>
                <span class="fw-bold">0</span>
                <span>items selected</span>
            </div>
        </div>

        <div class="ybd-panel">
            <div class="ybd-panelhead">
                <span class="ybd-hint"><i class="fas fa-check-double"></i>Select items to pack together</span>
                <button type="button" id="btn-request-packing" class="ybd-btn ybd-btn-gold">
                    <i class="fas fa-box-open"></i>Request packing
                </button>
            </div>
            <?php if ($items): ?>
                <div class="ybd-tablewrap">
                    <table class="ybd-manifest">
                        <thead>
                            <tr>
                                <th style="width:46px;"><input type="checkbox" id="select-all" class="ybd-check" aria-label="Select all"></th>
                                <th>Tracking details</th>
                                <th>Contents</th>
                                <th>Received photos</th>
                                <th style="text-align:right;">Received date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><input type="checkbox" name="tracking_ids[]" class="item-checkbox ybd-check" value="<?php echo $item->id; ?>" aria-label="Select <?php echo esc_attr($item->tracking_number); ?>"></td>
                                <td>
                                    <span class="ybd-trackno"><?php echo esc_html($item->tracking_number); ?></span>
                                    <span class="ybd-desc"><?php echo esc_html($item->description ?: 'No description'); ?></span>
                                </td>
                                <td>
                                    <span class="ybd-pcs"><i class="fas fa-cubes"></i><?php echo esc_html($item->item_count); ?> pcs</span>
                                </td>
                                <td>
                                    <?php if ($item->images):
                                        $images = explode(',', $item->images);
                                        $images = array_filter(array_map('trim', $images));
                                    ?>
                                        <div class="ybd-thumbs">
                                            <?php foreach (array_slice($images, 0, 3) as $image): ?>
                                                <img src="<?php echo esc_url($image); ?>" alt="Received parcel photo" class="ybd-thumb" loading="lazy" decoding="async"
                                                     onclick="openImageFullscreen('<?php echo esc_js($image); ?>')">
                                            <?php endforeach; ?>
                                            <?php if(count($images) > 3): ?>
                                                <button type="button" class="ybd-morethumb view-images" data-images="<?php echo esc_attr($item->images); ?>" aria-label="View all photos">+<?php echo count($images)-3; ?></button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="ybd-none">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="ybd-date">
                                    <strong><?php echo date('M j, Y', strtotime($item->date_received)); ?></strong>
                                    <small><?php echo date('h:i A', strtotime($item->date_received)); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="ybd-empty">
                    <div class="ybd-emptyicon"><i class="fas fa-warehouse"></i></div>
                    <h4>Nothing in the warehouse yet</h4>
                    <p>Once your vendor parcels arrive and are inspected by warehouse staff, they will appear here ready for repacking.</p>
                    <a href="<?php echo home_url('/add-tracking/'); ?>" class="ybd-btn ybd-btn-emerald"><i class="fas fa-plus-circle"></i>Register tracking numbers</a>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="ybd-pagenav">
        <?php
        echo paginate_links(array(
            'base'      => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
            'format'    => 'page/%#%/',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => '<i class="fas fa-chevron-left"></i>',
            'next_text' => '<i class="fas fa-chevron-right"></i>',
            'type'      => 'list',
            'end_size'  => 1,
            'mid_size'  => 1
        ));
        ?>
    </nav>
    <?php endif; ?>
</div>

<!-- Image Viewer Modal -->
<div id="image-viewer-modal" class="ybd-modal ybd-modal-wide" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999;">
    <div style="max-width:800px;">
        <div class="ybd-modalhead">
            <h5><i class="fas fa-images"></i> Parcel verification photos</h5>
            <button type="button" class="ybd-modalclose" onclick="jQuery('#image-viewer-modal').hide()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div id="image-gallery" style="display:flex; flex-wrap:wrap; gap:12px; justify-content:center;"></div>
    </div>
</div>

<!-- Packing Modal -->
<div id="packing-modal" class="ybd-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999;">
    <div>
        <div class="ybd-modalhead">
            <h5><i class="fas fa-box-open"></i> Confirm packing request</h5>
            <button type="button" class="ybd-modalclose" onclick="jQuery('#packing-modal').hide()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <p style="margin:0 0 6px; color:#3d4a42; font-size:14px;">
            You are requesting to pack <span class="ybd-pcs" id="selected-count">0</span> selected parcels together.
        </p>

        <form id="confirm-packing-form">
            <input type="hidden" name="action" value="ybt_request_packing">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ybt_nonce'); ?>">
            <input type="hidden" name="tracking_ids" id="modal-tracking-ids">

            <label class="ybd-formlabel" for="pf-company">Receiver name / company</label>
            <input type="text" name="shipping_company_name" id="pf-company" class="ybd-input" required placeholder="e.g. John Doe Imports">

            <label class="ybd-formlabel" for="pf-address">Delivery address</label>
            <textarea name="shipping_company_address" id="pf-address" class="ybd-textarea" required rows="2" placeholder="Full delivery destination address"></textarea>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 14px;">
                <div>
                    <label class="ybd-formlabel" for="pf-phone">Phone number</label>
                    <input type="text" name="shipping_company_phone" id="pf-phone" class="ybd-input" required placeholder="Receiver phone number">
                </div>
                <div>
                    <label class="ybd-formlabel" for="pf-mark">Shipping mark</label>
                    <input type="text" name="shipping_mark" id="pf-mark" class="ybd-input" required placeholder="e.g. YBT-001">
                </div>
            </div>

            <label class="ybd-formlabel" for="pf-instructions">Special instructions (optional)</label>
            <textarea name="instructions" id="pf-instructions" class="ybd-textarea" rows="2" placeholder="e.g. Fragile padding, remove shoe boxes"></textarea>

            <div style="display:grid; gap:10px; margin-top:20px;">
                <button type="submit" class="ybd-btn ybd-btn-gold" style="width:100%; padding:14px 20px;">
                    <i class="fas fa-paper-plane"></i>Submit packing request
                </button>
                <button type="button" class="ybd-btn ybd-btn-emerald" style="width:100%;" onclick="jQuery('#packing-modal').hide()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// DOM Safety: Check for ybt_ajax object
if (typeof ybt_ajax === 'undefined') {
    window.ybt_ajax = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s])) : '';

jQuery(document).ready(function($) {
    const SELECTION_KEY = 'ybt_received_selections';

    function getSelected() {
        const stored = sessionStorage.getItem(SELECTION_KEY);
        return stored ? JSON.parse(stored) : [];
    }

    function saveSelected(ids) {
        sessionStorage.setItem(SELECTION_KEY, JSON.stringify(ids));
        updateFloatingCount(ids.length);
    }

    function updateFloatingCount(count) {
        const badge = $('#selection-badge');
        if (count > 0) {
            badge.find('span.fw-bold').text(count);
            badge.fadeIn();
            $('#btn-request-packing').html('<i class="fas fa-box-open me-2"></i>Request Packing (' + count + ')');
        } else {
            badge.hide();
            $('#btn-request-packing').html('<i class="fas fa-box-open me-2"></i>Request Packing');
        }
    }

    function initSelections() {
        const selected = getSelected();
        $('.item-checkbox').each(function() {
            if (selected.includes($(this).val())) {
                $(this).prop('checked', true);
            }
        });
        updateFloatingCount(selected.length);
        
        if ($('.item-checkbox').length > 0 && $('.item-checkbox:not(:checked)').length === 0) {
            $('#select-all').prop('checked', true);
        }
    }

    initSelections();

    $('.item-checkbox').change(function() {
        let selected = getSelected();
        const id = $(this).val();

        if ($(this).is(':checked')) {
            if (!selected.includes(id)) selected.push(id);
        } else {
            selected = selected.filter(i => i !== id);
            $('#select-all').prop('checked', false);
        }
        saveSelected(selected);
    });

    $('#select-all').change(function() {
        const isChecked = $(this).prop('checked');
        let selected = getSelected();
        
        $('.item-checkbox').each(function() {
            const id = $(this).val();
            $(this).prop('checked', isChecked);
            
            if (isChecked) {
                if (!selected.includes(id)) selected.push(id);
            } else {
                selected = selected.filter(i => i !== id);
            }
        });
        saveSelected(selected);
    });

    $('#btn-request-packing').click(function() {
        const ids = getSelected();
        if(ids.length === 0) {
            alert('Please select at least one item to pack. You can select items across multiple pages!');
            return;
        }

        $('#selected-count').text(ids.length);
        $('#modal-tracking-ids').val(ids.join(','));
        $('#packing-modal').css('display', 'flex').hide().fadeIn(200);
    });

    $('#confirm-packing-form').submit(function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');

        $.post(ybt_ajax.ajax_url, $(this).serialize(), function(res) {
            if(res.success) {
                sessionStorage.removeItem(SELECTION_KEY);
                alert(escapeHtml(res.data));
                location.reload();
            } else {
                alert('Error: ' + escapeHtml(res.data));
                btn.prop('disabled', false).html(originalText);
            }
        }).fail(function() {
            alert('A network error occurred. Please try again.');
            btn.prop('disabled', false).html(originalText);
        });
    });

    window.openImageFullscreen = function(src) {
        if (!src) return;
        const safeSrc = src.replace(/"/g, '&quot;');
        const modal = $('<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);z-index:99999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;"><img src="' + safeSrc + '" style="max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 0 50px rgba(0,0,0,0.5);"></div>');
        $('body').append(modal.hide().fadeIn(200));
        modal.click(function() { $(this).fadeOut(200, function(){ $(this).remove(); }); });
    };

    $('.view-images').click(function(e) {
        e.stopPropagation();
        const rawImages = $(this).data('images');
        if (!rawImages) return;
        const images = String(rawImages).split(',');
        let html = '';
        images.forEach(img => {
            const trimmed = img.trim();
            if(trimmed) {
                const safeImg = escapeHtml(trimmed);
                html += `<div class="p-1"><img src="${safeImg}" class="rounded-3 border border-success border-opacity-25" style="max-width:100%;max-height:400px;display:block;cursor:zoom-in;" onclick="openImageFullscreen('${safeImg}')"></div>`;
            }
        });
        $('#image-gallery').html(html || '<div class="text-muted">No images found.</div>');
        $('#image-viewer-modal').css('display', 'flex').hide().fadeIn(200);
    });

    $('#image-viewer-modal').click(function(e) {
        if (e.target === this) $(this).fadeOut(200);
    });

    $('#packing-modal').click(function(e) {
        if (e.target === this) $(this).hide();
    });

    $(document).keydown(function(e) {
        if (e.key === 'Escape') {
            $('#packing-modal').hide();
            $('#image-viewer-modal').fadeOut(200);
        }
    });
});
</script>
