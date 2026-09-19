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
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;">';
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
?>

<div class="ybt-dashboard-wrapper animate-fade-in-up">
    <div class="container py-4">

        <!-- Page Title & Counter -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2" style="font-family: var(--ybt-font-heading);">
                    <i class="fas fa-warehouse" style="color: #10b981;"></i> In Warehouse
                </h2>
                <p class="text-muted small mb-0">Parcels received at China warehouse ready for packing & consolidation</p>
            </div>
            <div class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2 fs-6" style="border: 1px solid rgba(16, 185, 129, 0.25);">
                <i class="fas fa-boxes me-2"></i>Total Received: <?php echo intval($total_items); ?>
            </div>
        </div>

        <form id="packing-request-form">
            <!-- Floating Selection Badge (Brand Green Gradient) -->
            <div id="selection-badge" class="position-fixed" style="top: 85px; right: 24px; z-index: 1050; display: none;">
                <div class="text-white rounded-pill px-4 py-2.5 shadow-lg d-flex align-items-center gap-2" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: 1.5px solid rgba(255,255,255,0.4);">
                    <i class="fas fa-check-circle text-warning fs-5"></i>
                    <span class="fw-bold fs-6">0</span>
                    <span class="small opacity-90">items selected</span>
                </div>
            </div>
            
            <div class="glass-card glass-card-green">
                <div class="info-card-header bg-white border-bottom p-3.5 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-bold text-secondary"><i class="fas fa-check-double me-2" style="color: #d4af37;"></i>Select items to pack together</span>
                    <button type="button" id="btn-request-packing" class="btn btn-premium-primary rounded-pill shadow-sm px-4">
                        <i class="fas fa-box-open me-2"></i>Request Packing
                    </button>
                </div>
                <div class="info-card-body p-0">
                    <?php if ($items): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light text-secondary small text-uppercase" style="letter-spacing: 0.5px;">
                                    <tr>
                                        <th class="ps-4 py-3" style="width:50px;"><input type="checkbox" id="select-all" class="form-check-input"></th>
                                        <th class="py-3">Tracking Details</th>
                                        <th class="py-3">Contents</th>
                                        <th class="py-3">Received Photos</th>
                                        <th class="text-end pe-4 py-3">Received Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="ps-4"><input type="checkbox" name="tracking_ids[]" class="item-checkbox form-check-input" value="<?php echo $item->id; ?>"></td>
                                        <td>
                                            <div class="fw-bold fs-6" style="color: #047857; font-family: monospace; letter-spacing: 0.5px;"><?php echo esc_html($item->tracking_number); ?></div>
                                            <div class="text-muted small"><?php echo esc_html($item->description ?: 'No description'); ?></div>
                                        </td>
                                        <td>
                                            <span class="status-pill status-pill-warehouse">
                                                <i class="fas fa-cubes me-1"></i><?php echo esc_html($item->item_count); ?> pcs
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($item->images):
                                                $images = explode(',', $item->images);
                                                $images = array_filter(array_map('trim', $images));
                                            ?>
                                                <div class="d-flex gap-2">
                                                    <?php foreach (array_slice($images, 0, 3) as $image): ?>
                                                        <div class="position-relative" style="width: 48px; height: 48px;">
                                                            <img src="<?php echo esc_url($image); ?>" class="rounded-3 shadow-sm border border-success border-opacity-25" style="width:100%; height:100%; object-fit:cover; cursor:pointer; transition: transform 0.2s;" onclick="openImageFullscreen('<?php echo esc_js($image); ?>')" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if(count($images) > 3): ?>
                                                        <button type="button" class="btn btn-sm btn-light rounded-circle border d-flex align-items-center justify-content-center view-images fw-bold text-success" style="width: 48px; height: 48px;" data-images="<?php echo esc_attr($item->images); ?>">+<?php echo count($images)-3; ?></button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small opacity-50">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="text-dark fw-semibold"><?php echo date('M j, Y', strtotime($item->date_received)); ?></div>
                                            <div class="text-muted small"><?php echo date('h:i A', strtotime($item->date_received)); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <div class="rounded-circle bg-success bg-opacity-10 text-success p-4 d-inline-flex mb-3">
                                <i class="fas fa-warehouse fa-3x"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-2">No Items Currently in Warehouse</h4>
                            <p class="text-muted max-w-md mx-auto">Once your vendor parcels arrive and are inspected by warehouse staff, they will appear here ready for repacking.</p>
                            <a href="<?php echo home_url('/add-tracking/'); ?>" class="btn btn-premium-primary rounded-pill mt-2">
                                <i class="fas fa-plus-circle me-2"></i>Register Tracking Numbers
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-5 d-flex justify-content-center">
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
</div>

<!-- Image Viewer Modal (Glassmorphism) -->
<div id="image-viewer-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.75); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card" style="padding:24px; max-width:90%; max-height:90%; overflow-y:auto; width:800px; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-images me-2" style="color: #10b981;"></i>Parcel Verification Photos</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" onclick="jQuery('#image-viewer-modal').hide()"><i class="fas fa-times"></i></button>
        </div>
        <div id="image-gallery" class="text-center d-flex flex-wrap justify-content-center gap-3"></div>
    </div>
</div>

<!-- Packing Modal (Glassmorphism Brand Theme) -->
<div id="packing-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.75); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-card p-4 p-md-5 mx-3" style="width: 100%; max-width: 580px; background: #ffffff; position: relative; border-radius: 24px; border-top: 4px solid #10b981;">
        <button type="button" class="btn-close position-absolute" style="top: 20px; right: 20px;" onclick="jQuery('#packing-modal').hide()" aria-label="Close"></button>
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 68px; height: 68px; font-size: 28px;">
                <i class="fas fa-box-open"></i>
            </div>
            <h4 class="fw-bold text-dark">Confirm Packing Request</h4>
            <p class="text-muted small">You are requesting to pack <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fs-6" id="selected-count">0</span> selected parcels together.</p>
        </div>

        <form id="confirm-packing-form">
            <input type="hidden" name="action" value="ybt_request_packing">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ybt_nonce'); ?>">
            <input type="hidden" name="tracking_ids" id="modal-tracking-ids">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small">RECEIVER NAME / COMPANY</label>
                    <input type="text" name="shipping_company_name" class="form-control form-control-premium" required placeholder="e.g. John Doe Imports">
                </div>

                 <div class="col-12">
                     <label class="form-label fw-semibold text-secondary small">DELIVERY ADDRESS</label>
                     <textarea name="shipping_company_address" class="form-control form-control-premium" required rows="2" placeholder="Full delivery destination address"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">PHONE NUMBER</label>
                    <input type="text" name="shipping_company_phone" class="form-control form-control-premium" required placeholder="Receiver phone number">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small">SHIPPING MARK</label>
                    <input type="text" name="shipping_mark" class="form-control form-control-premium" required placeholder="e.g. YBT-001">
                </div>

                 <div class="col-12">
                     <label class="form-label fw-semibold text-secondary small">SPECIAL INSTRUCTIONS (OPTIONAL)</label>
                     <textarea name="instructions" class="form-control form-control-premium" rows="2" placeholder="e.g. Fragile padding, Remove shoe boxes..."></textarea>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4 pt-2">
                <button type="submit" class="btn btn-premium-primary btn-lg shadow-sm fw-bold py-3">
                    <i class="fas fa-paper-plane me-2"></i>Submit Packing Request
                </button>
                <button type="button" class="btn btn-light btn-sm text-muted rounded-pill" onclick="jQuery('#packing-modal').hide()">Cancel</button>
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

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';

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
