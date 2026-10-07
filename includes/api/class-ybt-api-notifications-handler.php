<?php
/**
 * YBT API Notifications Handler
 *
 * Mobile endpoints for the parcel timeline and the notification inbox.
 * Auth pattern: JWT Bearer via YBT_API_Handler_Base::authenticate()
 * (registered on wp_ajax_ AND wp_ajax_nopriv_ by the router).
 */

if (!defined('ABSPATH')) exit;

class YBT_API_Notifications_Handler extends YBT_API_Handler_Base {

    /**
     * GET ybt_mobile_parcel_timeline
     * Paged, customer-facing event timeline for one parcel.
     */
    public function get_parcel_timeline() {
        $this->authenticate();

        global $wpdb;
        $user = get_userdata($this->user_id);
        if (!$user) {
            YBT_API_Response::unauthorized();
            return;
        }

        $tracking_id = $this->get_param('tracking_id', 0, 'int', 'get');
        if ($tracking_id <= 0) {
            YBT_API_Response::error('tracking_id required', 'missing_param');
            return;
        }

        // IDOR guard: the parcel must belong to the caller (admins see any).
        $owned = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ybt_tracking
              WHERE id = %d AND (user_id = %d OR username = %s)",
            $tracking_id,
            $this->user_id,
            $user->user_login
        ));
        if (!$owned && !$this->is_admin) {
            YBT_API_Response::error('Parcel not found', 'not_found', 404);
            return;
        }

        $limit  = $this->get_param('limit', 25, 'int', 'get');
        $offset = $this->get_param('offset', 0, 'int', 'get');

        $timeline = YBT_Tracking_Events::get_timeline($tracking_id, $limit, $offset, $this->is_admin ? null : $this->user_id);
        YBT_API_Response::success(array(
            'items'    => $timeline['items'],
            'has_more' => $timeline['has_more'],
        ));
    }

    /**
     * GET ybt_mobile_notifications_v2
     * Inbox list. Data is a JSON array of { id, title, body, type,
     * created_at, is_read, tracking_id, tracking_number } — the shape
     * NotificationItem.kt already parses. Opening the list marks them read.
     */
    public function get_notifications() {
        $this->authenticate();

        global $wpdb;
        $table = $wpdb->prefix . 'ybt_notifications';

        $limit  = max(1, min(100, $this->get_param('limit', 25, 'int', 'get')));
        $offset = max(0, $this->get_param('offset', 0, 'int', 'get'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, body, type, tracking_id, tracking_number, is_read, date_created
               FROM {$table}
              WHERE user_id = %d
              ORDER BY date_created DESC, id DESC
              LIMIT %d OFFSET %d",
            $this->user_id,
            $limit + 1,
            $offset
        ));

        $has_more = $rows && count($rows) > $limit;
        if ($has_more) {
            array_pop($rows);
        }
        if (!$rows) {
            $rows = array();
        }

        $items = array();
        foreach ($rows as $row) {
            $items[] = array(
                'id'              => (int) $row->id,
                'title'           => $row->title,
                'body'            => $row->body,
                'type'            => $row->type,
                'tracking_id'     => $row->tracking_id ? (int) $row->tracking_id : null,
                'tracking_number' => $row->tracking_number,
                'is_read'         => (bool) $row->is_read,
                'created_at'      => $row->date_created,
            );
        }

        // Opening the inbox reads the listed messages (same idiom as chat) —
        // but only the listed ones, never rows beyond this page. Announcements
        // are excluded: they must stay unread until acknowledged through the
        // dedicated login-popup dismiss endpoint, otherwise the popup at the
        // next launch would never fire.
        $ids = array();
        foreach ($items as $item) {
            if ($item['type'] !== 'announcement') {
                $ids[] = $item['id'];
            }
        }
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET is_read = 1
                  WHERE user_id = %d AND is_read = 0 AND id IN ($placeholders)",
                array_merge(array($this->user_id), $ids)
            ));
        }

        YBT_API_Response::success($items);
    }

    /**
     * POST ybt_mobile_notifications_read  (id=N or all=1)
     */
    public function mark_read() {
        $this->authenticate();

        global $wpdb;
        $table = $wpdb->prefix . 'ybt_notifications';
        $all = $this->get_param('all', 0, 'int');
        $id  = $this->get_param('id', 0, 'int');

        if ($all) {
            $updated = $wpdb->update(
                $table,
                array('is_read' => 1),
                array('user_id' => $this->user_id, 'is_read' => 0)
            );
        } elseif ($id > 0) {
            $updated = $wpdb->update(
                $table,
                array('is_read' => 1),
                array('id' => $id, 'user_id' => $this->user_id, 'is_read' => 0)
            );
        } else {
            YBT_API_Response::error('id or all=1 required', 'missing_param');
            return;
        }

        YBT_API_Response::success(array('updated' => (int) $updated));
    }

    /**
     * GET ybt_mobile_announcements_unread
     * Unread admin announcements for the authenticated user — the app pops
     * these as a dialog right after login/launch. response.data =
     * [ { id, title, body, created_at } ]. They stay until dismissed via
     * ybt_mobile_announcements_dismiss, which simply marks the row read.
     */
    public function get_announcements() {
        $this->authenticate();
        YBT_API_Response::success(ybt_announcements_get_unread($this->user_id));
    }

    /**
     * POST ybt_mobile_announcements_dismiss  (id=N)
     * Acknowledge one announcement (popup button tap). Ownership is enforced
     * inside ybt_announcements_dismiss — a foreign id silently updates 0 rows.
     */
    public function dismiss_announcement() {
        $this->authenticate();

        $id = $this->get_query_param('id', 0, 'int');
        if ($id <= 0) {
            YBT_API_Response::error('id required', 'missing_param');
            return;
        }

        $updated = ybt_announcements_dismiss($this->user_id, $id);
        YBT_API_Response::success(array('updated' => $updated));
    }

    /**
     * GET ybt_mobile_notifications_unread — cheap badge poll.
     */
    public function get_unread() {
        $this->authenticate();

        global $wpdb;
        $table = $wpdb->prefix . 'ybt_notifications';

        $last_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(id) FROM {$table} WHERE user_id = %d",
            $this->user_id
        ));
        $unread = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
            $this->user_id
        ));

        YBT_API_Response::success(array('last_id' => $last_id, 'unread' => $unread));
    }
}
