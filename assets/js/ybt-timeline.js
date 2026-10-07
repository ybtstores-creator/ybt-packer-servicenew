/**
 * YBT Parcel Timeline + Notifications Bell (web)
 *
 * Hydrates the timeline section inside #parcelDetailsModal and drives the
 * navbar bell. Uses the ybt_ajax object localized by class-ybt-shortcodes.
 */
(function ($) {
    'use strict';

    if (typeof ybt_ajax === 'undefined') {
        return;
    }

    var POLL_INTERVAL = 60000;
    var ANNOUNCEMENTS_SHOWN_KEY = 'ybtAnnouncementsShownAt';

    function esc(s) {
        return $('<span>').text(s == null ? '' : String(s)).html();
    }

    function fmtDate(mysql) {
        if (!mysql) return '';
        var d = new Date(mysql.replace(' ', 'T'));
        if (isNaN(d.getTime())) return mysql;
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) +
               ' ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    }

    // =========================================================================
    // Parcel timeline (inside the existing details modal)
    // =========================================================================

    var EDITIONS = {};

    function renderTimeline($list, items) {
        $list.empty();
        if (!items.length) {
            $list.append('<div class="ybt-timeline-empty text-muted small">No activity recorded yet.</div>');
            return;
        }
        items.forEach(function (it) {
            var done = !!it.status_to;
            var cls = it.status_to ? 'ybt-timeline-status-' + esc(it.status_to) : (it.actor_role === 'customer' ? 'ybt-timeline-self' : 'ybt-timeline-done');
            var line = '<li class="ybt-timeline-item ' + cls + '">' +
                '<span class="ybt-timeline-dot"><i class="fas fa-' + (it.event_type === 'created' ? 'plus' : (it.status_to === 'completed' ? 'check' : 'circle')) + '"></i></span>' +
                '<div class="ybt-timeline-body">' +
                '<div class="ybt-timeline-title">' + esc(it.title) + '</div>' +
                (it.message ? '<div class="ybt-timeline-message">' + esc(it.message) + '</div>' : '') +
                '<div class="ybt-timeline-meta">' + esc(fmtDate(it.occurred_at)) +
                (it.actor_name && it.actor_role !== 'system' ? ' &middot; ' + esc(it.actor_name) : '') +
                '</div></div></li>';
            $list.append(line);
        });
    }

    function loadTimeline(trackingId) {
        var $wrap = $('#m-timeline');
        if (!$wrap.length) return;

        var edition = (EDITIONS[trackingId] || 0) + 1;
        EDITIONS[trackingId] = edition;

        $wrap.find('.ybt-timeline-list').empty().append(
            '<li class="ybt-timeline-loading text-muted small"><i class="fas fa-spinner fa-spin me-2"></i>Loading…</li>'
        );
        $wrap.show();

        $.post(ybt_ajax.ajax_url, {
            action: 'ybt_get_parcel_timeline',
            nonce: ybt_ajax.nonce,
            tracking_id: trackingId,
            limit: 50
        }).done(function (res) {
            if (EDITIONS[trackingId] !== edition) return;
            if (res && res.success && res.data) {
                renderTimeline($wrap.find('.ybt-timeline-list'), res.data.items || []);
            }
        }).fail(function () {
            if (EDITIONS[trackingId] !== edition) return;
            $wrap.find('.ybt-timeline-list').empty().append(
                '<li class="ybt-timeline-empty text-muted small">Could not load the timeline.</li>'
            );
        });
    }

    $(document).on('click', '.view-parcel-details', function () {
        var data = $(this).data('parcel');
        if (data && data.id) {
            loadTimeline(data.id);
        }
    });

    // =========================================================================
    // Notifications bell
    // =========================================================================

    function setBadge(n) {
        var $badge = $('#ybtBellCount');
        if (!$badge.length) return;
        n = parseInt(n, 10) || 0;
        $badge.text(n > 99 ? '99+' : n).toggle(n > 0);
    }

    function refreshBadge() {
        var $badge = $('#ybtBellCount');
        if (!$badge.length) return;
        $.post(ybt_ajax.ajax_url, {
            action: 'ybt_notifications_unread_count',
            nonce: ybt_ajax.nonce
        }).done(function (res) {
            if (res && res.success) {
                setBadge(res.data.unread);
            }
        });
    }

    function renderBellItems($list, items) {
        $list.empty();
        if (!items.length) {
            $list.append('<li class="ybt-bell-empty">No notifications yet</li>');
            return;
        }
        items.slice(0, 8).forEach(function (it) {
            $list.append(
                '<li class="ybt-bell-item">' +
                '<span class="ybt-bell-item-dot ' + (it.type === 'tracking_update' ? 'ybt-bell-type-track' : 'ybt-bell-type-other') + '"></span>' +
                '<div class="ybt-bell-item-body">' +
                '<div class="ybt-bell-item-title">' + esc(it.title) + '</div>' +
                (it.body ? '<div class="ybt-bell-item-text">' + esc(it.body) + '</div>' : '') +
                '<div class="ybt-bell-item-meta">' + esc(fmtDate(it.created_at)) + '</div>' +
                '</div></li>'
            );
        });
    }

    function openInboxModal() {
        var $modal = $('#ybtNotificationsModal');
        if (!$modal.length) return;
        $modal.css('display', 'flex').hide().fadeIn(150);
        loadInboxList($modal.find('.ybt-inbox-list'), true);
    }

    function loadInboxList($list, clearUnreadBadge) {
        $list.empty().append('<li class="ybt-inbox-loading"><i class="fas fa-spinner fa-spin me-2"></i>Loading…</li>');
        $.post(ybt_ajax.ajax_url, {
            action: 'ybt_get_notifications',
            nonce: ybt_ajax.nonce,
            limit: 50
        }).done(function (res) {
            $list.empty();
            var items = (res && res.success && res.data && res.data.items) ? res.data.items : [];
            if (!items.length) {
                $list.append('<li class="ybt-bell-empty">No notifications yet</li>');
            }
            items.forEach(function (it) {
                $list.append(
                    '<li class="ybt-inbox-item">' +
                    '<div class="ybt-inbox-item-head"><span class="ybt-inbox-item-title">' + esc(it.title) + '</span>' +
                    '<span class="ybt-inbox-item-date">' + esc(fmtDate(it.created_at)) + '</span></div>' +
                    (it.body ? '<div class="ybt-inbox-item-body">' + esc(it.body) + '</div>' : '') +
                    (it.tracking_number ? '<div class="ybt-inbox-item-track"><i class="fas fa-box me-1"></i>' + esc(it.tracking_number) + '</div>' : '') +
                    '</li>'
                );
            });
            if (clearUnreadBadge) {
                setBadge(res && res.success && res.data ? res.data.unread_count : 0);
            }
        }).fail(function () {
            $list.empty().append('<li class="ybt-bell-empty">Could not load notifications.</li>');
        });
    }

    $(document).ready(function () {
        if (!$('#ybtBellTrigger').length) {
            return;
        }

        refreshBadge();
        var timer = setInterval(function () {
            if (!document.hidden) refreshBadge();
        }, POLL_INTERVAL);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshBadge();
        });

        var $dropdown = $('#ybtBellDropdown');
        $('#ybtBellTrigger').on('click', function (e) {
            e.stopPropagation();
            var opening = !$dropdown.hasClass('show');
            $dropdown.toggleClass('show');
            $(this).toggleClass('active');
            if (opening) {
                var $list = $dropdown.find('.ybt-bell-list');
                $list.empty().append('<li class="ybt-inbox-loading"><i class="fas fa-spinner fa-spin me-2"></i>Loading…</li>');
                $.post(ybt_ajax.ajax_url, {
                    action: 'ybt_get_notifications',
                    nonce: ybt_ajax.nonce,
                    limit: 8
                }).done(function (res) {
                    var items = (res && res.success && res.data && res.data.items) ? res.data.items : [];
                    renderBellItems($list, items);
                    setBadge(res && res.success && res.data ? res.data.unread_count : 0);
                }).fail(function () {
                    $list.empty().append('<li class="ybt-bell-empty">Could not load notifications.</li>');
                });
            }
        });

        $('#ybtBellViewAll').on('click', function (e) {
            e.preventDefault();
            $dropdown.removeClass('show');
            $('#ybtBellTrigger').removeClass('active');
            openInboxModal();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#ybtBellTrigger, #ybtBellDropdown').length) {
                $dropdown.removeClass('show');
                $('#ybtBellTrigger').removeClass('active');
            }
        });

        $('#ybtInboxClose, #ybtNotificationsModal').on('click', function (e) {
            if (e.target === this) {
                $('#ybtNotificationsModal').fadeOut(150);
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('#ybtNotificationsModal').fadeOut(150);
                $dropdown.removeClass('show');
                $('#ybtBellTrigger').removeClass('active');
            }
        });

        // =====================================================================
        // Announcements: admin-sent messages that pop up once per login.
        // =====================================================================
        var annShownThisLogin = sessionStorage.getItem(ANNOUNCEMENTS_SHOWN_KEY) === '1';
        var annQueue = [];

        function showNextAnnouncement() {
            var $modal = $('#ybtAnnouncementModal');
            if (!$modal.length || !annQueue.length) {
                return;
            }
            var it = annQueue.shift();
            $('#ybtAnnTitleEl').text(it.title || 'Announcement');
            $('#ybtAnnBodyEl').text(it.body || '');
            $('#ybtAnnMetaEl').text(fmtDate(it.created_at));
            $('#ybtAnnGotIt').data('annId', it.id);
            $modal.css('display', 'flex').hide().fadeIn(150);
        }

        $('#ybtAnnGotIt').on('click', function () {
            var id = $(this).data('annId');
            if (id) {
                $.post(ybt_ajax.ajax_url, {
                    action: 'ybt_dismiss_announcement',
                    nonce: ybt_ajax.nonce,
                    id: id
                });
            }
            $('#ybtAnnouncementModal').fadeOut(150, showNextAnnouncement);
            refreshBadge();
        });

        function loadAnnouncementsPopup() {
            if (annShownThisLogin) {
                return; // already popped during this browser-tab session
            }
            $.post(ybt_ajax.ajax_url, {
                action: 'ybt_get_unread_announcements',
                nonce: ybt_ajax.nonce
            }).done(function (res) {
                if (!(res && res.success && res.data && res.data.items && res.data.items.length)) {
                    return;
                }
                annShownThisLogin = true;
                sessionStorage.setItem(ANNOUNCEMENTS_SHOWN_KEY, '1');
                annQueue = res.data.items;
                showNextAnnouncement();
            });
        }

        // Pop as soon as the dashboard (bell) is ready.
        loadAnnouncementsPopup();
    });

})(jQuery);
