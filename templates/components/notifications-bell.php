<?php
/**
 * Notifications bell + inbox modal (rendered inside the dashboard navbar).
 * Behavior lives in assets/js/ybt-timeline.js; data via ybt_get_notifications
 * / ybt_notifications_unread_count web AJAX (includes/notifications-web.php).
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    return;
}
?>
<div class="ybt-bell-wrap">
    <button type="button" class="ybt-bell-trigger" id="ybtBellTrigger" aria-label="Notifications">
        <i class="fas fa-bell"></i>
        <span class="ybt-badge-count" id="ybtBellCount" style="display:none;">0</span>
    </button>
    <div class="ybt-bell-dropdown" id="ybtBellDropdown">
        <div class="ybt-bell-dropdown-header">
            <span>Notifications</span>
        </div>
        <ul class="ybt-bell-list"></ul>
        <div class="ybt-bell-dropdown-footer">
            <a href="#" id="ybtBellViewAll">View all</a>
        </div>
    </div>
</div>

<div class="ybt-inbox-modal" id="ybtNotificationsModal" style="display:none;">
    <div class="ybt-inbox-dialog">
        <button type="button" class="ybt-inbox-close" id="ybtInboxClose" aria-label="Close">&times;</button>
        <h5 class="fw-bold mb-0"><i class="fas fa-bell me-2 text-success"></i>Notifications</h5>
        <ul class="ybt-inbox-list"></ul>
    </div>
</div>

<?php /** Announcement popup: admin messages shown once per login until dismissed (ybt_get_unread_announcements). */ ?>
<div class="ybt-ann-modal" id="ybtAnnouncementModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ybtAnnTitleEl">
    <div class="ybt-ann-dialog">
        <div class="ybt-ann-megaphone"><i class="fas fa-bullhorn"></i></div>
        <span class="ybt-ann-kicker">Announcement</span>
        <h5 class="fw-bold mb-1" id="ybtAnnTitleEl"></h5>
        <p class="ybt-ann-body mb-3" id="ybtAnnBodyEl"></p>
        <button type="button" class="btn btn-success w-100 fw-semibold" id="ybtAnnGotIt">Got it</button>
        <span class="ybt-ann-meta" id="ybtAnnMetaEl"></span>
    </div>
</div>
