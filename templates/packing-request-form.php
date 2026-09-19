<?php
if (!defined('ABSPATH')) exit;

// NOTE: Authentication redirect is handled by YBT_Core::handle_authentication_redirects()
// via 'template_redirect' hook at priority 5. Do NOT add wp_redirect() here.

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
global $wpdb;

// Load Modern Navbar Component
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);

// Get user's pending items for packing request
$pending_items = $wpdb->get_results($wpdb->prepare(
    "SELECT id, tracking_number, description, item_count
    FROM {$wpdb->prefix}ybt_tracking
    WHERE (user_id = %d OR username = %s)
    AND status = 'received'
    ORDER BY date_received DESC",
    $user_id,
    $current_user->user_login
));
?>
<style>
.packing-request-container { max-width: 950px; margin: 2rem auto; padding: 0 15px; }
.packing-card { background: white; border-radius: 18px; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.08); overflow: hidden; border: 1px solid rgba(255,255,255,0.6); }
.packing-header { background: linear-gradient(135deg, #047857 0%, #10b981 60%, #059669 100%); color: white; padding: 2.5rem 2rem; text-align: center; border-bottom: 4px solid #d4af37; }
.packing-header h1 { margin: 0; font-weight: 800; font-size: 2rem; }
.packing-header p { margin: 0.5rem 0 0; opacity: 0.9; font-size: 1.05rem; }
.form-section { padding: 2rem; border-bottom: 1px solid #f1f5f9; }
.form-section:last-child { border-bottom: none; }
.form-label { font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; display: block; font-size: 0.9rem; }
.form-control, .form-select { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1rem; width: 100%; font-size: 0.95rem; background: #f8fafc; }
.form-control:focus, .form-select:focus { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15); outline: none; background: #ffffff; }
.item-row { display: flex; align-items: center; padding: 1.2rem 1.5rem; background: #f8fafc; border-radius: 12px; margin-bottom: 1rem; border: 1px solid #e2e8f0; border-left: 4px solid #d4af37; transition: all 0.2s ease; }
.item-row:hover { background: #ffffff; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
.checkbox-container { width: 30px; margin-right: 15px; }
.tracking-info { flex: 1; }
.tracking-number { font-weight: 700; font-size: 1.1rem; color: #047857; }
.item-description { color: #64748b; font-size: 0.95rem; margin-top: 3px; }
.item-count { width: 90px; text-align: center; font-weight: 600; }
.btn-submit { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 1rem 2rem; font-size: 1.1rem; font-weight: 700; border-radius: 12px; width: 100%; transition: all 0.3s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); }
.btn-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.alert { border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
.alert-info { background: #ecfdf5; border-left: 4px solid #10b981; color: #047857; }
</style>

<div class="packing-request-container animate-up">
    <div class="packing-card">
        <div class="packing-header">
            <h1><i class="fas fa-box-open me-2"></i>Request Packing</h1>
            <p>Select received parcels and specify forwarding destination details</p>
        </div>

        <div class="form-section">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Instructions:</strong> Select items below, then fill in your shipping company details. We'll pack your items, issue photos, and calculate weight/cost before dispatch.
            </div>

            <h3 class="mb-3 text-dark fw-bold" style="font-size: 1.25rem;"><i class="fas fa-list-check me-2" style="color: #10b981;"></i>Select Items to Pack</h3>

            <?php if ($pending_items): ?>
            <div id="items-container">
                <?php foreach ($pending_items as $item): ?>
                <div class="item-row">
                    <div class="checkbox-container">
                        <input type="checkbox" class="form-check-input item-checkbox"
                               id="item-<?php echo esc_attr($item->id); ?>"
                               value="<?php echo esc_attr($item->id); ?>"
                               data-tracking="<?php echo esc_attr($item->tracking_number); ?>">
                    </div>
                    <div class="tracking-info">
                        <div class="tracking-number"><?php echo esc_html($item->tracking_number); ?></div>
                        <div class="item-description"><?php echo esc_html($item->description ?: 'No description'); ?></div>
                    </div>
                    <div class="item-count">
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold" style="border: 1px solid rgba(16,185,129,0.2);"><?php echo esc_html($item->item_count); ?> pcs</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-3 d-flex align-items-center flex-wrap gap-2">
                <button type="button" id="select-all-btn" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold" style="border-color: #10b981; color: #10b981;">
                    <i class="fas fa-check-square me-1"></i>Select All
                </button>
                <button type="button" id="deselect-all-btn" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="fas fa-square me-1"></i>Deselect All
                </button>
                <span id="selected-count" class="ms-3 text-muted fw-semibold">0 items selected</span>
            </div>
            <?php else: ?>
            <div class="alert alert-warning rounded-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                You have no received items available for packing. Please register your tracking numbers first.
            </div>
            <?php endif; ?>
        </div>

        <div class="form-section">
            <h3 class="mb-4 text-dark fw-bold" style="font-size: 1.25rem;"><i class="fas fa-truck-fast me-2" style="color: #d4af37;"></i>Forwarding Address & Instructions</h3>
            
            <form id="packing-request-form">
                <?php wp_nonce_field('ybt_nonce', 'ybt_nonce_field'); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">LOGISTICS COMPANY / RECEIVER NAME</label>
                        <input type="text" class="form-control" name="shipping_company" required placeholder="e.g. DHL Express / Agent Name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CONTACT PHONE NUMBER</label>
                        <input type="text" class="form-control" name="contact_phone" required placeholder="Receiver phone number">
                    </div>
                    <div class="col-12">
                        <label class="form-label">DELIVERY / WAREHOUSE ADDRESS</label>
                        <textarea class="form-control" name="shipping_address" rows="3" required placeholder="Full delivery address for final shipment"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SHIPPING MARK / MARKS & NUMBERS</label>
                        <input type="text" class="form-control" name="shipping_mark" placeholder="e.g. YBT-MARK-1234">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SPECIAL PACKING INSTRUCTIONS</label>
                        <input type="text" class="form-control" name="custom_notes" placeholder="e.g. Double bubble wrap, remove shoeboxes">
                    </div>
                </div>

                <div class="mt-4 pt-2">
                    <button type="submit" class="btn btn-submit shadow-lg" id="submit-packing-btn" <?php echo !$pending_items ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane me-2"></i>Submit Packing Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
