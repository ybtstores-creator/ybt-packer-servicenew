<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

global $wpdb;

// Pagination Setup
$per_page = isset($_GET['per_page']) ? max(10, intval($_GET['per_page'])) : 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Search Logic
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$search_query = "";
$params = [];

if ($search) {
    $search_query = "AND (t.batch_id LIKE %s OR t.username LIKE %s)";
    $search_term = '%' . $wpdb->esc_like($search) . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

// Increase GROUP_CONCAT limit to handle many tracking numbers
$wpdb->query("SET SESSION group_concat_max_len = 10000");

// Main Query setup
$query = "
    SELECT
        t.batch_id,
        MAX(t.username) as username,
        MAX(t.user_id) as user_id,
        COUNT(t.id) as item_count,
        MAX(t.cartons) as carton_count,
        MAX(t.weight_kg) as total_weight,
        MAX(t.payment_amount) as payment_amount,
        MAX(t.payment_currency) as payment_currency,
        MAX(t.status) as status,
        MAX(COALESCE(t.date_packed, t.date_packing_requested, t.date_added)) as date_created,
        (SELECT payment_evidence FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = t.batch_id AND payment_evidence IS NOT NULL AND payment_evidence != '' LIMIT 1) as payment_evidence,
        GROUP_CONCAT(DISTINCT t.tracking_number ORDER BY t.tracking_number SEPARATOR ', ') as tracking_numbers
    FROM {$wpdb->prefix}ybt_tracking t
    WHERE t.batch_id IS NOT NULL AND t.batch_id != ''
    $search_query
    GROUP BY t.batch_id
    ORDER BY date_created DESC
    LIMIT %d OFFSET %d
";

$final_params = array_merge($params, [(int)$per_page, (int)$offset]);
$cartons = $wpdb->get_results($wpdb->prepare($query, ...$final_params));

// Total count for pagination - use same logic as main query
$count_query = "SELECT COUNT(DISTINCT t.batch_id) FROM {$wpdb->prefix}ybt_tracking t WHERE t.batch_id IS NOT NULL AND t.batch_id != '' $search_query";
if ($search) {
    $total_items = (int) $wpdb->get_var($wpdb->prepare($count_query, ...$params));
} else {
    $total_items = (int) $wpdb->get_var($count_query);
}
$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 m-0 text-dark"><i class="fas fa-box-open me-2 text-primary"></i> Manage Cartons / Batches</h1>
    </div>

    <!-- SEARCH / FILTER CARD -->
    <div class="bg-white p-4 border rounded shadow-sm mb-4" style="border-color: #ccd0d4 !important;">
        <h2 class="h5 mb-3 text-secondary"><i class="fas fa-search me-2"></i>Search Batches</h2>
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="ybt-manage-cartons" />

            <div class="col-md-9">
                <label class="form-label fw-bold small text-dark mb-1">Batch ID or Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Enter Batch ID or Username..." class="form-control">
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="button button-primary w-100" style="height: 38px;"><i class="fas fa-filter me-1"></i> Filter Results</button>
            </div>
        </form>
    </div>

    <!-- BATCHES TABLE CARD -->
    <div class="bg-white border rounded shadow-sm" style="border-color: #ccd0d4 !important; overflow: hidden;">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light text-muted uppercase small fw-bold">
                    <tr>
                        <th class="ps-3 py-3">Date Info</th>
                        <th class="py-3">Batch ID</th>
                        <th class="py-3">User</th>
                        <th class="py-3">Items</th>
                        <th class="py-3">Carton Details</th>
                        <th class="py-3">Payment</th>
                        <th class="py-3">Evidence</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Weight Img</th>
                        <th class="pe-3 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($cartons): ?>
                        <?php foreach ($cartons as $carton): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark"><?php echo $carton->date_created ? date('M j, Y', strtotime($carton->date_created)) : 'N/A'; ?></div>
                                    <small class="text-muted"><?php echo $carton->date_created ? date('g:i a', strtotime($carton->date_created)) : ''; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo esc_html($carton->batch_id); ?></span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $carton->user_id); ?>" class="text-decoration-none fw-bold text-primary">
                                        <i class="fas fa-user-circle me-1"></i><?php echo esc_html($carton->username); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="button button-white button-small dropdown-toggle w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#items-<?php echo $carton->batch_id; ?>">
                                            <?php echo $carton->item_count; ?> Items <i class="fas fa-chevron-down float-end mt-1"></i>
                                        </button>
                                        <div class="collapse mt-1 p-2 bg-light border rounded small" id="items-<?php echo $carton->batch_id; ?>" style="max-height: 150px; overflow-y: auto;">
                                            <?php
                                            if (!empty($carton->tracking_numbers)) {
                                                $numbers = explode(', ', $carton->tracking_numbers);
                                                foreach ($numbers as $num) echo '<div><i class="fas fa-barcode me-1 text-muted"></i>' . esc_html($num) . '</div>';
                                            } else {
                                                echo '<em class="text-muted">No items</em>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div class="mb-1"><i class="fas fa-box me-1 text-secondary"></i> <strong>Count:</strong> <?php echo $carton->carton_count ?: '-'; ?></div>
                                        <div><i class="fas fa-weight-hanging me-1 text-secondary"></i> <strong>Weight:</strong> <?php echo $carton->total_weight ? number_format($carton->total_weight, 2) . ' kg' : '-'; ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($carton->payment_amount > 0): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">
                                            <?php echo number_format($carton->payment_amount, 2) . ' ' . ($carton->payment_currency ?: 'USD'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Not Set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($carton->payment_evidence): ?>
                                        <button class="button button-white button-small view-payment-evidence" data-images="<?php echo esc_attr($carton->payment_evidence); ?>">
                                            <i class="fas fa-receipt me-1 text-success"></i> View
                                        </button>
                                        <?php if ($carton->status !== 'completed'): ?>
                                            <div class="mt-1">
                                                <button class="button button-small confirm-carton-payment w-100" data-batch="<?php echo esc_attr($carton->batch_id); ?>" style="background: #f0ad4e; color: white; border: none; font-size: 10px;">
                                                    Confirm
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_map = [
                                        'packed' => ['bg' => 'bg-info-subtle', 'text' => 'text-info-emphasis'],
                                        'completed' => ['bg' => 'bg-success-subtle', 'text' => 'text-success-emphasis'],
                                        'packing_in_progress' => ['bg' => 'bg-warning-subtle', 'text' => 'text-warning-emphasis'],
                                    ];
                                    $style = $status_map[$carton->status] ?? ['bg' => 'bg-light', 'text' => 'text-muted'];
                                    ?>
                                    <span class="badge <?php echo $style['bg'] . ' ' . $style['text']; ?> border">
                                        <?php echo ucwords(str_replace('_', ' ', $carton->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    // Inline DB call kept from original
                                    $weight_image = $wpdb->get_var($wpdb->prepare(
                                        "SELECT weight_image FROM {$wpdb->prefix}ybt_tracking WHERE batch_id = %s AND weight_image IS NOT NULL LIMIT 1",
                                        $carton->batch_id
                                    ));
                                    if ($weight_image):
                                    ?>
                                        <a href="#" onclick="openImageFullscreen('<?php echo esc_js($weight_image); ?>'); return false;">
                                            <img src="<?php echo esc_url($weight_image); ?>" class="rounded border" style="width: 40px; height: 40px; object-fit: cover;">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted text-center d-block">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 text-end">
                                    <button type="button" class="button button-white button-small view-batch-details" data-batch-id="<?php echo esc_attr($carton->batch_id); ?>">
                                        Details <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center p-5 text-muted">No cartons or batches found matching your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination moved outside card -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-3 d-flex justify-content-center">
            <?php
            // Use WP pagination but wrap it if needed for style
            echo '<div class="pagination-wrapper">' . paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '<span class="button button-white">&laquo; Prev</span>',
                'next_text' => '<span class="button button-white">Next &raquo;</span>',
                'total' => $total_pages,
                'current' => $current_page
            )) . '</div>';
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODERN MODALS -->
<!-- Payment Modal -->
<div class="modal fade" id="paymentEvidenceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-white border-bottom">
        <h5 class="modal-title"><i class="fas fa-receipt me-2 text-success"></i>Payment Evidence</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body bg-light text-center" id="payment-evidence-gallery">
        <!-- Content -->
      </div>
      <div class="modal-footer bg-white border-top">
        <button type="button" class="button button-white" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="cartonDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-white border-bottom">
        <h5 class="modal-title"><i class="fas fa-box me-2 text-primary"></i>Batch Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body bg-light" id="cartons-container">
        <!-- Content -->
      </div>
      <div class="modal-footer bg-white border-top">
        <button type="button" class="button button-white" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Image Modal Wrapper for custom fullscreen -->
<div id="custom-image-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:100000; align-items:center; justify-content:center;">
    <img id="custom-image-view" src="" style="max-width:90%; max-height:90%; border-radius:8px;">
</div>

<script>
// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

// YBT Utility functions
window.YBT = {
    escapeHtml: function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    escapeHtmlAttr: function(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
};

jQuery(document).ready(function($) {
    // Image Fullscreen
    // Image Fullscreen
    window.openImageFullscreen = function(src) {
        if (!src) return;

        let $modal = $('#custom-image-modal');
        let $img = $modal.find('img');

        // Ensure modal exists in DOM
        if ($modal.length === 0) {
             $modal = $('<div id="custom-image-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:100000; align-items:center; justify-content:center; cursor:pointer;"></div>');
             $img = $('<img id="custom-image-view" style="max-width:90%; max-height:90%; border-radius:8px; box-shadow: 0 0 20px rgba(0,0,0,0.5);">');
             $modal.append($img);
             $('body').append($modal);

             // Close on click
             $modal.click(function() { $(this).fadeOut(200); });
        }

        $img.attr('src', src);
        $modal.css('display', 'flex').hide().fadeIn(200);
    };

    // View Payment Evidence
    $('.view-payment-evidence').click(function() {
        const imagesRaw = $(this).data('images');
        if (!imagesRaw) return;
        const images = String(imagesRaw).split(',');
        let html = '';
        images.forEach(img => {
            const trimmedImg = img.trim();
            if(trimmedImg) {
                const safeImg = escapeHtmlAttr(trimmedImg);
                html += `<div class="mb-3 p-3 bg-white border rounded">
                    <img src="${safeImg}" class="img-fluid rounded mb-2" style="max-height:400px; display:block; margin:0 auto;">
                    <br><a href="${safeImg}" target="_blank" class="button button-primary">Download Original</a>
                </div>`;
            }
        });
        const $gallery = $('#payment-evidence-gallery, #payment-evidence-modal #payment-evidence-gallery');
        $gallery.html(html || '<p class="text-muted">No evidence found.</p>');

        // Handle both possible modal types (Bootstrap and custom)
        const bsModalEl = document.getElementById('paymentEvidenceModal');
        if (bsModalEl && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(bsModalEl).show();
        } else {
            $('#payment-evidence-modal').css('display', 'flex').show();
        }
    });

    $('.close-payment-modal').click(function() { $('#payment-evidence-modal').hide(); });

    // Confirm Payment
    $(document).on('click', '.confirm-carton-payment', function() {
        const batchId = $(this).data('batch');
        const $btn = $(this);

        if(confirm('Mark this batch as completely PAID?')) {
            $.post(ybt_admin.ajax_url, {
                action: 'ybt_confirm_carton_payment',
                nonce: ybt_admin.nonce,
                batch_id: batchId
            }, function(res) {
                if(res.success) {
                    $btn.closest('td, .mt-1').html('<span class="badge bg-success text-white border-0" style="background:#28a745 !important;"><i class="fas fa-check me-1"></i> Paid</span>');
                } else {
                    alert('Error: ' + (res.data || 'Unknown error'));
                }
            }).fail(function() { alert('Server error while confirming payment.'); });
        }
    });

    // View Details
    $('.view-batch-details').click(function() {
        const batchId = $(this).data('batch-id');
        const $container = $('#cartons-container');
        $container.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><p>Loading details...</p></div>');

        const bsModalEl = document.getElementById('cartonDetailsModal');
        if (bsModalEl && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(bsModalEl).show();
        } else {
            $('#carton-modal').css('display', 'flex').show();
        }

        $.post(ybt_admin.ajax_url, {
            action: 'ybt_get_carton_details',
            nonce: ybt_admin.nonce,
            batch_id: batchId
        }, function(res) {
            if(res.success && res.data && res.data.length > 0) {
                let html = '<div class="row g-3" style="max-height:60vh; overflow-y:auto; padding:5px;">';
                res.data.forEach(carton => {
                    const safeNum = YBT.escapeHtml(carton.carton_number);
                    const safeWeight = YBT.escapeHtml(carton.weight_kg);
                    const safeAmount = YBT.escapeHtml(carton.payment_amount);
                    const safeCurrency = YBT.escapeHtml(carton.payment_currency);

                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border shadow-sm">
                                <div class="card-header bg-white fw-bold text-success" style="color:#228B22 !important;">
                                    <i class="fas fa-box me-1"></i> Carton ${safeNum || 'N/A'}
                                </div>
                                <div class="card-body">
                                    <p class="mb-1"><strong>Weight:</strong> ${safeWeight} kg</p>
                                    <p class="mb-1"><strong>Cost:</strong> ${safeAmount} ${safeCurrency}</p>`;

                    if(carton.carton_image) {
                        const images = String(carton.carton_image).split(',');
                        html += '<div class="mt-2 text-center d-flex gap-2 flex-wrap justify-content-center">';
                        images.forEach(img => {
                            if(img.trim()) {
                                const safeImg = YBT.escapeHtmlAttr(img.trim());
                                html += `<a href="${safeImg}" target="_blank"><img src="${safeImg}" class="img-fluid rounded border" style="width:80px; height:80px; object-fit:cover;"></a>`;
                            }
                        });
                        html += '</div>';
                    }
                    html += `</div></div></div>`;
                });
                html += '</div>';
                $container.html(html);
            } else {
                $container.html('<p class="text-center text-muted p-4">No detailed carton data found for this batch.</p>');
            }
        }).fail(function() { $container.html('<p class="text-danger text-center p-4">Server error loading details.</p>'); });
    });

    $('.close-carton-modal').click(function() { $('#carton-modal').hide(); });
});
</script>
