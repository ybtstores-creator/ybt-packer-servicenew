<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

global $wpdb;

// Enqueue Enhanced CSS

/* Bounded: newest payment-evidence users only. The proof table grows forever,
 * so this page needs hard ceilings to stay fast. */
$max_users = 50;
$max_proofs_per_user = 20;

// Get users with payment evidence (proofs live on ybt_tracking rows)
$users_with_payments = $wpdb->get_results($wpdb->prepare("
    SELECT t.user_id, u.display_name, u.user_login, u.user_email, MAX(t.date_added) AS last_upload
    FROM {$wpdb->prefix}ybt_tracking t
    INNER JOIN {$wpdb->users} u ON t.user_id = u.ID
    WHERE t.payment_evidence IS NOT NULL AND t.payment_evidence != ''
    GROUP BY t.user_id, u.display_name, u.user_login, u.user_email
    ORDER BY last_upload DESC
    LIMIT %d
", $max_users));
?>

<div class="wrap ybt-admin-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="wp-heading-inline" style="font-size: 2rem; color: var(--text-main);"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i> <?php _e('Payment Proofs', 'ybt-packer'); ?></h1>
    </div>

    <div class="ybt-admin-container">
        <?php if ($users_with_payments): ?>
            <div class="glass-card">
                 <div class="info-card-header bg-white">
                    <span><i class="fas fa-users me-2 text-primary"></i>Users with New Payments</span>
                 </div>
                 <div class="info-card-body p-0">
                    <?php foreach ($users_with_payments as $user):
                        $user_payments = $wpdb->get_results($wpdb->prepare("
                            SELECT MIN(t.id) AS id,
                                   t.batch_id,
                                   COUNT(t.id) AS item_count,
                                   GROUP_CONCAT(DISTINCT t.tracking_number ORDER BY t.tracking_number SEPARATOR ', ') AS tracking_numbers,
                                   MAX(t.payment_evidence) AS payment_evidence,
                                   MAX(t.date_added) AS date_uploaded
                            FROM {$wpdb->prefix}ybt_tracking t
                            WHERE t.user_id = %d AND t.payment_evidence IS NOT NULL AND t.payment_evidence != ''
                            GROUP BY t.batch_id
                            ORDER BY date_uploaded DESC
                            LIMIT %d
                        ", $user->user_id, $max_proofs_per_user));

                        // Cartons for every evidence row in ONE query (was: one query per proof).
                        $batch_ids = array_unique(wp_list_pluck($user_payments, 'batch_id'));
                        $all_cartons = array();
                        if ($batch_ids) {
                            $id_placeholders = implode(',', array_fill(0, count($batch_ids), '%s'));
                            $carton_rows = $wpdb->get_results($wpdb->prepare(
                                "SELECT batch_id, id, carton_image, carton_number, weight_kg
                                 FROM {$wpdb->prefix}ybt_cartons
                                 WHERE batch_id IN ($id_placeholders)
                                 ORDER BY carton_number ASC",
                                $batch_ids
                            ));
                            foreach ($carton_rows as $crow) {
                                $all_cartons[$crow->batch_id][] = $crow;
                            }
                        }
                    ?>

                    <div class="p-3 border-bottom">
                         <div class="d-flex justify-content-between align-items-center mb-3">
                             <div>
                                 <h3 class="m-0 text-dark" style="font-size: 1.1rem;">👤 <?php echo esc_html($user->display_name); ?> <span class="text-muted small">(<?php echo esc_html($user->user_login); ?>)</span></h3>
                                 <small class="text-muted"><?php echo esc_html($user->user_email); ?></small>
                             </div>
                             <span class="badge-glass"><?php echo count($user_payments); ?> payment(s)</span>
                         </div>

                         <table class="table admin-table mb-0" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Batch ID</th>
                                    <th>Items / Tracking</th>
                                    <th>Cartons</th>
                                    <th>Uploaded</th>
                                    <th class="text-end">Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_payments as $evidence):
                                    $cartons = isset($all_cartons[$evidence->batch_id]) ? $all_cartons[$evidence->batch_id] : array();
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($evidence->batch_id); ?></strong></td>
                                        <td>
                                            <div><?php echo $evidence->item_count; ?> items</div>
                                            <small class="text-muted d-block text-truncate" style="max-width: 250px;" title="<?php echo esc_attr($evidence->tracking_numbers); ?>">
                                                <?php echo esc_html($evidence->tracking_numbers); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($cartons): ?>
                                                <?php $evidence_key = esc_attr($user->user_id . '-' . $evidence->batch_id); ?>
                                                <button class="button button-small button-secondary mb-1" onclick="toggleCartons('<?php echo $evidence_key; ?>')">
                                                    📦 <?php echo count($cartons); ?> Cartons
                                                </button>
                                                <div id="cartons-<?php echo $evidence_key; ?>" style="display:none; padding: 10px; background: #f8fafc; border-radius: 6px; margin-top: 5px;">
                                                    <div style="display:flex; flex-wrap:wrap; gap:10px;">
                                                    <?php foreach ($cartons as $carton): ?>
                                                        <?php
                                                        $carton_photos = array_values(array_filter(array_map('trim', explode(',', (string) $carton->carton_image))));
                                                        $carton_photo = $carton_photos ? $carton_photos[0] : '';
                                                        ?>
                                                        <div class="text-center p-2 border rounded bg-white">
                                                            <?php if ($carton_photo): ?>
                                                                <img src="<?php echo esc_url($carton_photo); ?>"
                                                                     loading="lazy" decoding="async"
                                                                     style="width:50px; height:50px; object-fit:cover; border-radius:4px; cursor:pointer; border: 1px solid #ddd; display:block; margin:0 auto 5px;"
                                                                     onclick="openFullscreen('<?php echo esc_js($carton_photo); ?>')">
                                                            <?php endif; ?>
                                                            <small class="d-block w-100 fw-bold">#<?php echo $carton->carton_number; ?></small>
                                                            <small class="text-muted"><?php echo $carton->weight_kg; ?>kg</small>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo date('M j, Y H:i', strtotime($evidence->date_uploaded)); ?>
                                        </td>
                                        <td class="text-end">
                                            <?php
                                            $ext = strtolower(pathinfo($evidence->payment_evidence, PATHINFO_EXTENSION));
                                            $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            ?>
                                            <div class="d-flex justify-content-end gap-2 align-items-center">
                                                <?php if ($is_img): ?>
                                                    <img src="<?php echo esc_url($evidence->payment_evidence); ?>"
                                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; cursor: pointer;"
                                                         onclick="showPaymentImage('<?php echo esc_js($evidence->payment_evidence); ?>')">

                                                    <button class="button button-small" onclick="showPaymentImage('<?php echo esc_js($evidence->payment_evidence); ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="<?php echo esc_url($evidence->payment_evidence); ?>" target="_blank" class="button button-small button-secondary">
                                                        <i class="fas fa-file-alt"></i> View
                                                    </a>
                                                <?php endif; ?>

                                                <a href="<?php echo esc_url($evidence->payment_evidence); ?>" download class="button button-small" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                         </table>
                    </div>
                    <?php endforeach; ?>
                 </div>
            </div>
        <?php else: ?>
            <div class="glass-card text-center p-5">
                <i class="fas fa-check-circle fa-4x mb-3 text-muted opacity-25"></i>
                <h3 class="text-muted">No Payment Proofs Pending</h3>
                <p class="text-muted">Good job! All uploaded payments have been processed.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="payment-viewer-modal" class="ybt-modal-overlay">
    <div class="ybt-modal-content" style="max-width: 600px;">
        <h3>💳 Evidence Preview</h3>
        <div id="payment-preview-container" class="text-center p-3 bg-light rounded mb-3"></div>
        <div class="ybt-modal-actions">
            <button type="button" class="button close-modal">Close</button>
        </div>
    </div>
</div>

<style>
.ybt-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
.ybt-modal-content { background: #fff; padding: 25px; border-radius: 12px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
.ybt-modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
</style>

<script>
// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';

function toggleCartons(id) {
    const el = document.getElementById('cartons-' + id);
    if (el) el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}

function showPaymentImage(src) {
    if (!src) return;
    const safeSrc = src.replace(/"/g, '&quot;');
    const html = `<img src="${safeSrc}" style="max-width: 100%; max-height: 60vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                  <div class="mt-3">
                      <a href="${safeSrc}" target="_blank" class="button button-primary">Open Full Size</a>
                      <a href="${safeSrc}" download class="button">Download</a>
                  </div>`;
    document.getElementById('payment-preview-container').innerHTML = html;
    document.querySelector('#payment-viewer-modal').style.display = 'flex';
}

function openFullscreen(src) {
    if (!src) return;
    const safeSrc = src.replace(/"/g, '&quot;');
    const modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:10000;display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
    modal.innerHTML = `<img src="${safeSrc}" style="max-width:95%;max-height:95%;border-radius:4px;">`;
    modal.onclick = function() { document.body.removeChild(modal); };
    document.body.appendChild(modal);
}

jQuery(document).ready(function($) {
    $('.close-modal').click(function() {
        $('.ybt-modal-overlay').hide();
    });
});
</script>
