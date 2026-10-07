<?php
/**
 * YBT Parcel Timeline Events
 *
 * Append-only, customer-facing event log for each tracking number.
 * Stored separately from ybt_tracking_edits (which is an ADMIN accountability
 * trail and deliberately drops customer actions): this table records what a
 * parcel owner is allowed to see — "your parcel was received", "packing
 * started" — never field-level diffs.
 *
 * Write seams:
 *   1. YBT_Tracking_Audit::log_edit() fires do_action('ybt_tracking_event', ...)
 *      on BOTH its admin path and its customer-rejected path; on_audit_event()
 *      turns each into a row here. Covers ~25 existing call sites for free.
 *   2. Explicit YBT_Tracking_Events::record() calls at the customer write points
 *      that bypass the audit class entirely (claim / user-add / packing request).
 *   3. backfill_batch() seeds history for pre-existing parcels from the five
 *      date_* columns, once per install, idempotently.
 *
 * occurred_at = when it happened (client-supplied for offline scans),
 * recorded_at = when the server wrote it. Offline replay no longer backdates.
 *
 * Every write is try/catch + ready()-gated: a timeline failure must never
 * break a status change.
 *
 * @package YBT_Packer_Service
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_Tracking_Events {

    const DB_VERSION = '1.0';

    const OPTION_DB_VERSION = 'ybt_tracking_events_db_version';

    const OPTION_BACKFILL_CURSOR = 'ybt_tracking_events_backfill_cursor';

    const BACKFILL_BATCH = 500;

    /** Duplicate-write window (seconds) for offline-replay dedupe. */
    const DEDUPE_WINDOW = 60;

    private static $installed_this_request = false;

    /**
     * Offline-sync replay: forces occurred_at on events written during the
     * current operation to the client's original action timestamp. Set and
     * reset per operation by ybt_handle_mobile_sync_request(); consumed by
     * record() only when no explicit $args['occurred_at'] is provided.
     */
    public static $forced_occurred = null;

    /** Friendly titles keyed by event_type. */
    private static $event_titles = array(
        'created'           => 'Parcel added',
        'claimed'           => 'Parcel claimed',
        'details_updated'   => 'Details updated',
        'scan'              => 'Parcel scanned at warehouse',
        'status_change'     => 'Status updated',
        'received'          => 'Parcel received at warehouse',
        'packing_requested' => 'Packing requested',
        'cartons_attached'  => 'Cartons attached',
        'packed'            => 'Parcel packed',
        'batch_packed'      => 'Batch packed',
        'completed'         => 'Parcel completed',
        'delivered'         => 'Parcel delivered',
        'deleted'           => 'Parcel removed',
        'note'              => 'Note added',
    );

    /** Customer-friendly titles for status transitions. */
    private static $status_titles = array(
        'received'            => 'Parcel received at warehouse',
        'packing_in_progress' => 'Packing started',
        'packed'              => 'Parcel packed',
        'completed'           => 'Parcel completed',
    );

    // =========================================================================
    // INSTALLATION / MIGRATION
    // =========================================================================

    public static function install() {
        global $wpdb;

        if (self::$installed_this_request) {
            return true;
        }
        self::$installed_this_request = true;

        $table_name      = $wpdb->prefix . 'ybt_tracking_events';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tracking_id bigint(20) unsigned NOT NULL,
            tracking_number varchar(50) DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type varchar(40) NOT NULL,
            status_from varchar(30) DEFAULT NULL,
            status_to varchar(30) DEFAULT NULL,
            title varchar(140) NOT NULL,
            message varchar(255) DEFAULT NULL,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            actor_name varchar(100) DEFAULT NULL,
            actor_role varchar(20) NOT NULL DEFAULT 'system',
            source varchar(20) NOT NULL DEFAULT 'web',
            meta longtext DEFAULT NULL,
            occurred_at datetime NOT NULL,
            recorded_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_tracking_occurred (tracking_id, occurred_at),
            KEY idx_user_occurred (user_id, occurred_at),
            KEY idx_event_lookup (tracking_id, event_type, status_to)
        ) {$charset_collate};");

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);

        return true;
    }

    public static function maybe_install() {
        if (get_option(self::OPTION_DB_VERSION) === self::DB_VERSION) {
            return;
        }
        if (get_transient('ybt_tracking_events_install_failed')) {
            return;
        }
        try {
            self::install();
        } catch (Exception $e) {
            set_transient('ybt_tracking_events_install_failed', 1, 5 * MINUTE_IN_SECONDS);
            if (function_exists('ybt_log_error')) {
                ybt_log_error('Tracking events install failed', array('error' => $e->getMessage()));
            }
        }
    }

    private static function ready() {
        if (get_option(self::OPTION_DB_VERSION) === self::DB_VERSION) {
            return true;
        }
        self::maybe_install();
        return (get_option(self::OPTION_DB_VERSION) === self::DB_VERSION);
    }

    // =========================================================================
    // WRITE API
    // =========================================================================

    /**
     * Record one timeline event. Never throws.
     *
     * @param int    $tracking_id
     * @param string $event_type  Slug from $event_titles (or any slug).
     * @param array  $args        title, message, status_from, status_to, user_id,
     *                            tracking_number, actor_id, actor_name, actor_role,
     *                            source, occurred_at, meta, dedupe.
     * @return bool
     */
    public static function record($tracking_id, $event_type, array $args = array()) {
        global $wpdb;

        try {
            $tracking_id = (int) $tracking_id;
            $event_type  = sanitize_key($event_type) ?: 'update';

            if ($tracking_id <= 0 || !self::ready()) {
                return false;
            }

            $now = current_time('mysql');
            if (!empty($args['occurred_at'])) {
                $occurred = self::normalize_datetime($args['occurred_at'], $now);
            } elseif (self::$forced_occurred) {
                // Offline replay: backdate to the client's original scan time.
                $occurred = self::normalize_datetime(self::$forced_occurred, $now);
            } else {
                $occurred = $now;
            }

            // Resolve the parcel owner so per-user queries work from the index.
            $user_id = isset($args['user_id']) ? (int) $args['user_id'] : 0;
            if ($user_id <= 0) {
                $user_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
                    $tracking_id
                ));
            }

            $tracking_number = isset($args['tracking_number']) ? $args['tracking_number'] : null;
            if (empty($tracking_number)) {
                $tracking_number = $wpdb->get_var($wpdb->prepare(
                    "SELECT tracking_number FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
                    $tracking_id
                ));
            }

            $status_to   = self::normalize_status(isset($args['status_to']) ? $args['status_to'] : null);
            $status_from = self::normalize_status(isset($args['status_from']) ? $args['status_from'] : null);

            // Offline-replay double-write guard: same parcel + type + status +
            // actor within a short window counts once.
            if (!empty($args['dedupe'])) {
                $duplicate = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ybt_tracking_events
                      WHERE tracking_id = %d AND event_type = %s
                        AND COALESCE(status_to, '') = %s
                        AND actor_id = %d
                        AND recorded_at >= %s
                      LIMIT 1",
                    $tracking_id,
                    $event_type,
                    $status_to ? $status_to : '',
                    isset($args['actor_id']) ? (int) $args['actor_id'] : get_current_user_id(),
                    date('Y-m-d H:i:s', strtotime($now) - self::DEDUPE_WINDOW)
                ));
                if ($duplicate) {
                    return true;
                }
            }

            $title = isset($args['title']) && $args['title'] !== ''
                ? $args['title']
                : self::default_title($event_type, $status_to);

            $meta = '';
            if (!empty($args['meta'])) {
                $meta = wp_json_encode($args['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $message = isset($args['message']) ? $args['message'] : null;

            $inserted = $wpdb->insert(
                $wpdb->prefix . 'ybt_tracking_events',
                array(
                    'tracking_id'     => $tracking_id,
                    'tracking_number' => $tracking_number ? substr((string) $tracking_number, 0, 50) : null,
                    'user_id'         => $user_id,
                    'event_type'      => substr($event_type, 0, 40),
                    'status_from'     => $status_from ? substr((string) $status_from, 0, 30) : null,
                    'status_to'       => $status_to ? substr((string) $status_to, 0, 30) : null,
                    'title'           => substr((string) $title, 0, 140),
                    'message'         => $message !== null ? substr((string) $message, 0, 255) : null,
                    'actor_id'        => isset($args['actor_id']) ? max(0, (int) $args['actor_id']) : 0,
                    'actor_name'      => isset($args['actor_name']) ? substr((string) $args['actor_name'], 0, 100) : null,
                    'actor_role'      => isset($args['actor_role']) ? substr((string) $args['actor_role'], 0, 20) : 'system',
                    'source'          => isset($args['source']) ? substr((string) $args['source'], 0, 20) : 'web',
                    'meta'            => $meta,
                    'occurred_at'     => $occurred,
                    'recorded_at'     => $now,
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($inserted === false && function_exists('ybt_log_error')) {
                ybt_log_error('Failed to write tracking event', array(
                    'tracking_id' => $tracking_id,
                    'event_type'  => $event_type,
                    'db_error'    => $wpdb->last_error,
                ));
            }

            return $inserted !== false;
        } catch (Exception $e) {
            if (function_exists('ybt_log_error')) {
                ybt_log_error('Tracking event write failed', array(
                    'tracking_id' => $tracking_id,
                    'error'       => $e->getMessage(),
                ));
            }
            return false;
        }
    }

    /**
     * Listener for the audit class's unified write seam.
     *
     * @param int    $tracking_id
     * @param string $action  Audit action slug ('status_change', 'claim', ...).
     * @param array  $ctx     summary, source, actor_id, actor_name, actor_role,
     *                        tracking_number, changes, before.
     */
    public static function on_audit_event($tracking_id, $action, array $ctx = array()) {
        $changes = isset($ctx['changes']) ? (array) $ctx['changes'] : array();
        $before  = isset($ctx['before']) ? $ctx['before'] : null;

        $status_from = ($before && isset($before->status)) ? $before->status : null;
        $status_to   = null;
        foreach ($changes as $change) {
            if (isset($change['field']) && $change['field'] === 'status') {
                $status_to   = isset($change['to']) ? $change['to'] : null;
                $status_from = isset($change['from']) ? $change['from'] : $status_from;
                break;
            }
        }

        // Audit's diff() display-formats status ("Packing In Progress"); the
        // timeline stores raw slugs.
        $status_from = self::normalize_status($status_from);
        $status_to   = self::normalize_status($status_to);

        $event_type = self::map_audit_action($action, $status_to);

        $message = null;
        if ($event_type === 'status_change' && $status_to) {
            $message = self::status_message($status_to);
        } elseif (!empty($ctx['summary'])) {
            // Audit summaries are admin-facing diffs; only pass through the
            // short ones and never for pure 'edit' noise.
            $message = null;
        }

        return self::record($tracking_id, $event_type, array(
            'tracking_number' => isset($ctx['tracking_number']) ? $ctx['tracking_number'] : null,
            'status_from'     => $status_from,
            'status_to'       => $status_to,
            'message'         => $message,
            'actor_id'        => isset($ctx['actor_id']) ? $ctx['actor_id'] : 0,
            'actor_name'      => isset($ctx['actor_name']) ? $ctx['actor_name'] : null,
            'actor_role'      => isset($ctx['actor_role']) ? $ctx['actor_role'] : 'system',
            'source'          => isset($ctx['source']) ? $ctx['source'] : 'web',
            'dedupe'          => true,
        ));
    }

    private static function map_audit_action($action, $status_to) {
        switch ($action) {
            case 'status_change':
            case 'bulk_status':
                return 'status_change';
            case 'claim':
                return 'claimed';
            case 'create':
            case 'bulk_import':
            case 'bulk_add':
                return 'created';
            case 'delete':
            case 'bulk_delete':
                return 'deleted';
            case 'packing':
                return $status_to ? 'status_change' : 'packing_requested';
            case 'image_upload':
            case 'image_delete':
            case 'mobile_update':
                return 'details_updated';
            default:
                return 'details_updated';
        }
    }

    /**
     * A parcel's status message for a transition ("now Received at warehouse").
     */
    public static function status_message($status) {
        $messages = array(
            'not_received'        => 'Not yet received at the warehouse.',
            'received'            => 'Your parcel has been received at the warehouse.',
            'packing_in_progress' => 'Packing of your parcel has started.',
            'packed'              => 'Your parcel has been packed.',
            'completed'           => 'Your parcel is completed and ready for shipment.',
        );
        return isset($messages[$status]) ? $messages[$status] : null;
    }

    private static function default_title($event_type, $status_to) {
        if ($event_type === 'status_change' && $status_to && isset(self::$status_titles[$status_to])) {
            return self::$status_titles[$status_to];
        }
        if (isset(self::$event_titles[$event_type])) {
            return self::$event_titles[$event_type];
        }
        return ucwords(str_replace('_', ' ', (string) $event_type));
    }

    // =========================================================================
    // READ API
    // =========================================================================

    /**
     * Paged timeline for one parcel, newest first.
     *
     * Ownership MUST be checked by the caller — this returns rows for any
     * tracking_id it is given.
     *
     * @return array { items: array, has_more: bool }
     */
    public static function get_timeline($tracking_id, $limit = 25, $offset = 0, $owner_id = null) {
        global $wpdb;

        if (!self::ready()) {
            return array('items' => array(), 'has_more' => false);
        }

        $limit  = max(1, min(500, (int) $limit));
        $offset = max(0, (int) $offset);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_type, title, message, status_from, status_to,
                    actor_id, actor_name, actor_role, source, occurred_at, recorded_at
               FROM {$wpdb->prefix}ybt_tracking_events
              WHERE tracking_id = %d
              ORDER BY occurred_at DESC, id DESC
              LIMIT %d OFFSET %d",
            (int) $tracking_id,
            $limit + 1,
            $offset
        ));

        $has_more = false;
        if ($rows && count($rows) > $limit) {
            $has_more = true;
            array_pop($rows);
        }
        if (!$rows) {
            $rows = array();
        }

        // A reassigned parcel must not show the previous owner's name: callers
        // pass the viewing customer's id; admins pass null and see everything.
        if ($owner_id !== null) {
            foreach ($rows as $row) {
                if ($row->actor_role === 'customer' && (int) $row->actor_id !== (int) $owner_id) {
                    $row->actor_name = null;
                }
            }
        }

        return array('items' => $rows, 'has_more' => $has_more);
    }

    // =========================================================================
    // BACKFILL (existing parcels get non-empty timelines on day one)
    // =========================================================================

    private static $backfill_map = array(
        'created'           => array('column' => 'date_added',              'status_to' => null),
        'received'          => array('column' => 'date_received',           'status_to' => 'received'),
        'packing_requested' => array('column' => 'date_packing_requested',  'status_to' => 'packing_in_progress'),
        'packed'            => array('column' => 'date_packed',             'status_to' => 'packed'),
        'completed'         => array('column' => 'date_completed',          'status_to' => 'completed'),
    );

    /**
     * One idempotent batch of backfill, cursor-persisted so it never fatals
     * a shared-host request. Returns true while more work may remain.
     */
    public static function backfill_batch() {
        global $wpdb;

        if (!self::ready()) {
            return false;
        }
        // One pass per request.
        static $done = false;
        if ($done) {
            return false;
        }
        $done = true;

        $cursor  = (int) get_option(self::OPTION_BACKFILL_CURSOR, 0);
        $events  = $wpdb->prefix . 'ybt_tracking_events';
        $tracking = $wpdb->prefix . 'ybt_tracking';

        // The pass is SELECT-then-INSERT with no unique index behind it, and admin
        // requests run concurrently (a dashboard fires several AJAX calls at once),
        // so two processes used to read the same cursor and write the same events
        // twice. Only one pass may run at a time; others retry on a later request.
        $lock = $wpdb->get_var("SELECT GET_LOCK('ybt_tracking_events_backfill', 0)");
        if ($lock !== null && (int) $lock !== 1) {
            return true;
        }
        $locked = ($lock !== null);

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$tracking} WHERE id > %d ORDER BY id ASC LIMIT %d",
            $cursor,
            self::BACKFILL_BATCH
        ));

        if (empty($ids)) {
            if ($locked) {
                $wpdb->query("SELECT RELEASE_LOCK('ybt_tracking_events_backfill')");
            }
            return false;
        }

        foreach ($ids as $id) {
            $id     = (int) $id;
            $cursor = max($cursor, $id);
            $row    = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tracking} WHERE id = %d", $id));
            if (!$row) {
                continue;
            }

            $last_status = null;
            foreach (self::$backfill_map as $event_type => $map) {
                $when = isset($row->{$map['column']}) ? $row->{$map['column']} : null;
                if (empty($when) || $when === '0000-00-00 00:00:00') {
                    continue;
                }

                $status_to = $map['status_to'];
                // Live status changes are stored as event_type 'status_change', so
                // matching on event_type (or only on source='backfill') never sees
                // them and the sweep re-creates a timeline the parcel already has.
                if ($status_to) {
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$events}
                          WHERE tracking_id = %d AND COALESCE(status_to, '') = %s
                          LIMIT 1",
                        $id,
                        $status_to
                    ));
                } else {
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$events}
                          WHERE tracking_id = %d AND event_type = %s
                          LIMIT 1",
                        $id,
                        $event_type
                    ));
                }
                if ($exists) {
                    $last_status = $status_to ?: $last_status;
                    continue;
                }

                $title = self::default_title($event_type, $status_to);
                $wpdb->insert(
                    $events,
                    array(
                        'tracking_id'     => $id,
                        'tracking_number' => substr((string) $row->tracking_number, 0, 50),
                        'user_id'         => (int) $row->user_id,
                        'event_type'      => $event_type,
                        'status_from'     => $last_status,
                        'status_to'       => $status_to,
                        'title'           => $title,
                        'message'         => $status_to ? self::status_message($status_to) : null,
                        'actor_id'        => 0,
                        'actor_name'      => 'System',
                        'actor_role'      => 'system',
                        'source'          => 'backfill',
                        'meta'            => '',
                        'occurred_at'     => $when,
                        'recorded_at'     => current_time('mysql'),
                    ),
                    array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
                );

                $last_status = $status_to ?: $last_status;
            }
        }

        update_option(self::OPTION_BACKFILL_CURSOR, $cursor, false);

        if ($locked) {
            $wpdb->query("SELECT RELEASE_LOCK('ybt_tracking_events_backfill')");
        }

        // True if there may be more rows than this batch covered.
        return count($ids) >= self::BACKFILL_BATCH;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private static function normalize_status($value) {
        if ($value === null || $value === '' || $value === '—') {
            return null;
        }
        $slug = strtolower(trim((string) $value));
        $slug = str_replace(' ', '_', $slug);
        $slug = preg_replace('/[^a-z_]/', '', $slug);
        return $slug !== '' ? $slug : null;
    }

    private static function normalize_datetime($value, $fallback) {
        $value = trim((string) $value);
        $ts    = strtotime($value);
        if ($ts === false) {
            return $fallback;
        }
        // Clamp to [now - 2 years, now]: reject clock-skewed future and absurd past.
        $now = time();
        if ($ts > $now || $ts < ($now - 2 * YEAR_IN_SECONDS)) {
            return $fallback;
        }
        return date('Y-m-d H:i:s', $ts);
    }
}

// Unified write seam from the audit class (admin, system and customer paths).
add_action('ybt_tracking_event', array('YBT_Tracking_Events', 'on_audit_event'), 10, 3);

// Schema + backfill on upgraded installs.
add_action('admin_init', array('YBT_Tracking_Events', 'maybe_install'));
add_action('wp_loaded', array('YBT_Tracking_Events', 'maybe_install'));
add_action('admin_init', array('YBT_Tracking_Events', 'backfill_batch'));
