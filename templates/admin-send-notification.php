<?php
/**
 * Admin — Send Notification (announcements)
 *
 * Composes an announcement that pops up for users at their next login, both
 * on the web dashboard and in the mobile app. Delivery goes through
 * includes/notifications-announcements.php (ybt_send_announcement AJAX).
 * Rows land in the shared notification inbox (type 'announcement') so they
 * also appear in the bell / mobile Alerts tab until dismissed.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('Insufficient permissions', 'ybt-packer'));
}

$ybt_ann_nonce  = wp_create_nonce('ybt_nonce');
$ybt_ann_users  = get_users(array(
    'fields' => array('ID', 'user_login', 'display_name'),
    'number' => 2000,
    'orderby' => 'display_name',
));
$ybt_ann_recent = function_exists('ybt_announcements_recent') ? ybt_announcements_recent(15) : array();
?>
<div class="wrap ybt-admin-modern">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h1 class="wp-heading-inline fw-bold text-dark me-2">📣 Send Notification</h1>
            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Login popup — web + mobile</span>
        </div>
    </div>

    <div class="row g-4">
        <!-- Compose card -->
        <div class="col-lg-7">
            <div class="card p-4 shadow-sm" style="border-radius: 16px; border-top: 4px solid #10b981;">
                <h3 class="fw-bold text-dark mb-1"><i class="fas fa-bullhorn me-2 text-success"></i>Compose</h3>
                <p class="text-muted small mb-3">
                    The message pops up the moment the recipient signs in — on the website dashboard and in the
                    mobile app — and stays in their notification inbox until they dismiss it.
                </p>

                <form id="ybtAnnForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Recipient</label>
                        <select id="ybtAnnAudience" class="form-select">
                            <option value="all">📢 All users</option>
                            <option value="user">👤 Specific user</option>
                        </select>
                    </div>

                    <div class="mb-3" id="ybtAnnUserRow" style="display:none;">
                        <label class="form-label fw-semibold">User</label>
                        <select id="ybtAnnUser" class="form-select">
                            <?php foreach ($ybt_ann_users as $u) : ?>
                                <option value="<?php echo esc_attr($u->ID); ?>">
                                    <?php echo esc_html($u->display_name . ' (' . $u->user_login . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-muted small">(max 140 characters)</span></label>
                        <input type="text" id="ybtAnnTitle" class="form-control" maxlength="140" placeholder="e.g. Warehouse closed this Saturday" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message</label>
                        <textarea id="ybtAnnBody" class="form-control" rows="5" placeholder="Write the announcement…"></textarea>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" id="ybtAnnSend" class="button button-primary button-large"
                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                            <i class="fas fa-paper-plane me-1"></i> Send Notification
                        </button>
                        <span id="ybtAnnStatus" class="small text-muted"></span>
                    </div>
                </form>
            </div>
        </div>

        <!-- How it lands card -->
        <div class="col-lg-5">
            <div class="card p-4 shadow-sm" style="border-radius: 16px;">
                <h3 class="fw-bold text-dark mb-3"><i class="fas fa-route me-2 text-success"></i>How it reaches users</h3>
                <ul class="list-unstyled small text-muted mb-0" style="line-height: 1.9;">
                    <li><i class="fas fa-globe me-2 text-success"></i><strong>Web:</strong> popup modal on the dashboard as soon as they log in.</li>
                    <li><i class="fas fa-mobile-alt me-2 text-success"></i><strong>Mobile app:</strong> popup dialog immediately after sign-in / on next launch.</li>
                    <li><i class="fas fa-bell me-2 text-success"></i><strong>Inbox:</strong> stays in the notification bell / Alerts tab until dismissed.</li>
                    <li><i class="fab fa-android me-2 text-success"></i><strong>Push:</strong> sent when Firebase is configured; the login popup always delivers.</li>
                </ul>
            </div>
        </div>

        <!-- Recent card -->
        <div class="col-12">
            <div class="card p-4 shadow-sm" style="border-radius: 16px;">
                <h3 class="fw-bold text-dark mb-3"><i class="fas fa-history me-2 text-success"></i>Recently sent</h3>
                <?php if (!$ybt_ann_recent) : ?>
                    <p class="text-muted small mb-0">No announcements sent yet.</p>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Recipient</th>
                                    <th>Sent</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ybt_ann_recent as $r) : ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo esc_html($r['title']); ?></td>
                                        <td class="small text-muted"><?php echo esc_html(wp_trim_words($r['body'], 18)); ?></td>
                                        <td class="small"><?php echo esc_html($r['user_login']); ?></td>
                                        <td class="small text-muted"><?php echo esc_html(mysql2date('M j, Y H:i', $r['date_created'])); ?></td>
                                        <td>
                                            <?php if ($r['is_read']) : ?>
                                                <span class="badge bg-success bg-opacity-10 text-success">dismissed</span>
                                            <?php else : ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning-emphasis">pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="text-muted small mb-0 mt-2">One row per recipient — "All users" shows its newest rows here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function ($) {
    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var nonce = '<?php echo esc_js($ybt_ann_nonce); ?>';

    $('#ybtAnnAudience').on('change', function () {
        $('#ybtAnnUserRow').toggle($(this).val() === 'user');
    });

    $('#ybtAnnForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#ybtAnnSend');
        var title = $.trim($('#ybtAnnTitle').val() || '');
        var body = $.trim($('#ybtAnnBody').val() || '');

        if (!title) { $('#ybtAnnTitle').focus(); return; }
        if (!body) { $('#ybtAnnBody').focus(); return; }

        $btn.prop('disabled', true);
        $('#ybtAnnStatus').removeClass('text-danger text-success').addClass('text-muted').text('Sending…');

        $.post(ajaxUrl, {
            action: 'ybt_send_announcement',
            nonce: nonce,
            audience: $('#ybtAnnAudience').val(),
            user_id: $('#ybtAnnUser').val() || 0,
            title: title,
            body: body
        }, null, 'json').done(function (res) {
            if (res && res.success) {
                var d = res.data || {};
                var msg = 'Sent to ' + (d.inserted || 0) + ' user(s)';
                if (d.push_sent) { msg += ', ' + d.push_sent + ' push notification(s)'; }
                // The inbox rows are written either way, so say so plainly
                // when the push leg was skipped instead of reporting a plain
                // green "Sent to N user(s)".
                if (d.push_warning) {
                    $('#ybtAnnStatus').removeClass('text-muted text-success').addClass('text-warning')
                        .text(msg + ' \u2014 in-app only, push skipped: ' + d.push_warning);
                } else {
                    $('#ybtAnnStatus').removeClass('text-muted text-danger').addClass('text-success').text(msg);
                }
                $('#ybtAnnTitle').val('');
                $('#ybtAnnBody').val('');
                // Only auto-refresh on a clean send: a push warning has to stay
                // on screen long enough for the admin to read and act on it.
                if (!d.push_warning) {
                    window.setTimeout(function () { window.location.reload(); }, 1200);
                }
            } else {
                $('#ybtAnnStatus').removeClass('text-muted text-success').addClass('text-danger')
                    .text((res && res.data) || 'Failed to send.');
            }
        }).fail(function () {
            $('#ybtAnnStatus').removeClass('text-muted text-success').addClass('text-danger').text('Network error.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
});
</script>
