<?php
/**
 * YBT Tracking Audit Trail
 *
 * Records WHICH administrator edited a tracking number, WHEN, and WHAT changed
 * (old value -> new value) for every edit on every tracking number.
 *
 * Two layers of storage:
 *   1. ybt_tracking.last_edited_by / last_edited_by_name / last_edited_at / edit_count
 *      -> fast "last editor" display in the tracking list, no extra query needed.
 *   2. ybt_tracking_edits (history table)
 *      -> full per-tracking edit trail with field-level before/after values.
 *
 * Usage in edit handlers (preferred — does the update AND the audit in one call):
 *
 *     YBT_Tracking_Audit::apply_update($id, $update_data, 'status_change');
 *
 * For updates performed with raw SQL / bulk queries:
 *
 *     $before = YBT_Tracking_Audit::snapshot($id);          // BEFORE the update
 *     $wpdb->query(...);                                     // do the update
 *     YBT_Tracking_Audit::log_edit($id, 'bulk_status', YBT_Tracking_Audit::diff($update_data, $before));
 *
 * @package YBT_Packer_Service
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class YBT_Tracking_Audit {

    /** Bump when columns/table change so install() re-runs once. */
    const DB_VERSION = '1.0';

    const OPTION_DB_VERSION = 'ybt_tracking_audit_db_version';

    /** Fields we are willing to diff and display, with human labels. */
    private static $field_labels = array(
        'tracking_number'       => 'Tracking Number',
        'user_id'               => 'Assigned User ID',
        'username'              => 'User',
        'phone'                 => 'Phone',
        'description'           => 'Description',
        'status'                => 'Status',
        'images'                => 'Images',
        'batch_id'              => 'Batch',
        'item_count'            => 'Item Count',
        'item_type'             => 'Item Type',
        'is_complete'           => 'Complete',
        'payment_evidence'      => 'Payment Evidence',
        'shipping_method'       => 'Shipping Method',
        'shipping_address'      => 'Shipping Address',
        'package_phone'         => 'Package Phone',
        'shipping_company_phone'=> 'Shipping Company Phone',
        'shipping_company_name' => 'Shipping Company',
        'shipping_mark'         => 'Shipping Mark',
        'cartons'               => 'Cartons',
        'weight_kg'             => 'Weight (kg)',
        'admin_notes'           => 'Admin Notes',
        'payment_amount'        => 'Payment Amount',
        'payment_currency'      => 'Payment Currency',
        'date_received'         => 'Date Received',
        'date_packing_requested'=> 'Date Packing Requested',
        'date_packed'           => 'Date Packed',
        'date_completed'        => 'Date Completed',
    );

    /** Changes longer than this are truncated in the stored history. */
    const MAX_VALUE_LEN = 160;

    private static $installed_this_request = false;

    // =========================================================================
    // INSTALLATION / MIGRATION
    // =========================================================================

    /**
     * Idempotent schema install: history table + last-editor columns.
     */
    public static function install() {
        global $wpdb;

        if (self::$installed_this_request) {
            return true;
        }
        self::$installed_this_request = true;

        $tracking_table = $wpdb->prefix . 'ybt_tracking';
        $edits_table    = $wpdb->prefix . 'ybt_tracking_edits';

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Last-editor columns on the tracking table (for existing installs).
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$tracking_table}", 0);
        if (is_array($columns)) {
            $needed = array(
                'last_edited_by'      => "bigint(20) DEFAULT NULL",
                'last_edited_by_name' => "varchar(100) DEFAULT NULL",
                'last_edited_at'      => "datetime DEFAULT NULL",
                'edit_count'          => "int(11) DEFAULT 0",
            );
            foreach ($needed as $column => $definition) {
                if (!in_array($column, $columns, true)) {
                    $wpdb->query("ALTER TABLE {$tracking_table} ADD COLUMN {$column} {$definition}");
                }
            }
        }

        // 2. History table.
        dbDelta("CREATE TABLE {$edits_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tracking_id bigint(20) NOT NULL,
            tracking_number varchar(50) DEFAULT NULL,
            admin_id bigint(20) NOT NULL DEFAULT 0,
            admin_name varchar(100) DEFAULT NULL,
            action varchar(50) NOT NULL,
            summary text,
            changes longtext,
            ip_address varchar(45) DEFAULT NULL,
            source varchar(20) DEFAULT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tracking_id (tracking_id),
            KEY idx_admin_id (admin_id),
            KEY idx_action (action),
            KEY idx_date_created (date_created)
        ) {$charset_collate};");

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);

        // History table for an existing install did not exist when the tracking
        // list pages were built, so make sure nothing is cached stale.
        if (function_exists('ybt_cache') && method_exists(ybt_cache(), 'invalidate_tracking_cache')) {
            ybt_cache()->invalidate_tracking_cache();
        }

        return true;
    }

    /**
     * Run install() once per plugin version. Safe to call on every request.
     */
    public static function maybe_install() {
        if (get_option(self::OPTION_DB_VERSION) === self::DB_VERSION) {
            return;
        }
        // Avoid hammering the DB if the migration keeps failing.
        if (get_transient('ybt_tracking_audit_install_failed')) {
            return;
        }
        try {
            self::install();
        } catch (Exception $e) {
            set_transient('ybt_tracking_audit_install_failed', 1, 5 * MINUTE_IN_SECONDS);
            if (function_exists('ybt_log_error')) {
                ybt_log_error('Tracking audit install failed', array('error' => $e->getMessage()));
            }
        }
    }

    /**
     * Cheap guard so audit writes never explode if the schema is missing.
     */
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
     * Snapshot a tracking row BEFORE it is modified, for diffing.
     *
     * @param int $tracking_id
     * @return object|null
     */
    public static function snapshot($tracking_id) {
        global $wpdb;
        $tracking_id = (int) $tracking_id;
        if ($tracking_id <= 0) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
            $tracking_id
        ));
    }

    /**
     * Perform a tracking update and record the audit entry in one step.
     *
     * Drop-in replacement for $wpdb->update() on the tracking table that also
     * attributes the change to the acting admin.
     *
     * @param int    $tracking_id
     * @param array  $update_data Column => new value.
     * @param string $action      Short action slug, e.g. 'status_change'.
     * @param array  $args        Optional: format, where_format, summary, source,
     *                            log_activity (bool), force (bool).
     * @return int|false wpdb result (false on failure), same as $wpdb->update().
     */
    public static function apply_update($tracking_id, array $update_data, $action = 'edit', array $args = array()) {
        global $wpdb;

        $tracking_id = (int) $tracking_id;
        $table       = $wpdb->prefix . 'ybt_tracking';
        $before      = !empty($args['before']) ? $args['before'] : self::snapshot($tracking_id);

        // Audit bookkeeping columns must never be treated as user edits.
        unset(
            $update_data['last_edited_by'],
            $update_data['last_edited_by_name'],
            $update_data['last_edited_at'],
            $update_data['edit_count']
        );

        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $tracking_id),
            isset($args['format']) ? $args['format'] : null,
            isset($args['where_format']) ? $args['where_format'] : array('%d')
        );

        if ($result === false) {
            return false;
        }

        // Nothing actually changed and it was not an explicit action -> stay quiet.
        $changes = self::diff($update_data, $before);
        $force   = !empty($args['force']);
        if (empty($changes) && !$force && $result === 0) {
            return $result;
        }

        self::log_edit($tracking_id, $action, $changes, array_merge($args, array('before' => $before)));

        return $result;
    }

    /**
     * Build a field => array('from' => x, 'to' => y) diff.
     *
     * @param array       $update_data Column => new value.
     * @param object|null $before      Row snapshot taken BEFORE the update.
     * @return array Ordered list of change records.
     */
    public static function diff(array $update_data, $before) {
        $changes = array();

        foreach ($update_data as $field => $new_value) {
            if (!isset(self::$field_labels[$field])) {
                continue;
            }

            $old_value = ($before && isset($before->$field)) ? $before->$field : null;

            if (self::values_equal($old_value, $new_value)) {
                continue;
            }

            $changes[] = array(
                'field' => $field,
                'label' => self::$field_labels[$field],
                'from'  => self::format_value($field, $old_value),
                'to'    => self::format_value($field, $new_value),
            );
        }

        return $changes;
    }

    /**
     * Record an audit entry and stamp the "last edited by" columns.
     *
     * @param int    $tracking_id
     * @param string $action  Short slug.
     * @param array  $changes Ordered change records from diff() (or hand-built).
     * @param array  $args    summary, source, tracking_number, admin_id, admin_name,
     *                        log_activity, before.
     * @return bool
     */
    public static function log_edit($tracking_id, $action, array $changes = array(), array $args = array()) {
        global $wpdb;

        $tracking_id = (int) $tracking_id;
        if ($tracking_id <= 0 || !self::ready()) {
            return false;
        }

        $before = isset($args['before']) ? $args['before'] : null;

        // Resolve tracking number for the history row (denormalised so history
        // survives a rename of the tracking number itself).
        $tracking_number = isset($args['tracking_number']) ? $args['tracking_number'] : null;
        if ($tracking_number === null) {
            if ($before && isset($before->tracking_number)) {
                $tracking_number = $before->tracking_number;
            } else {
                $tracking_number = $wpdb->get_var($wpdb->prepare(
                    "SELECT tracking_number FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
                    $tracking_id
                ));
            }
        }

        // Resolve the acting admin.
        $actor = self::current_actor(
            isset($args['admin_id']) ? $args['admin_id'] : null,
            isset($args['admin_name']) ? $args['admin_name'] : null
        );

        $action  = sanitize_key($action) ?: 'edit';
        $summary = isset($args['summary']) ? $args['summary'] : self::build_summary($action, $changes);
        $source  = isset($args['source']) ? $args['source'] : self::detect_source();

        // This trail is an ADMIN accountability record: customer self-service
        // changes (claiming a parcel, editing their own entry) are not attributed
        // as admin work. System actions (id 0) are labelled "System".
        if ($actor['id'] > 0
            && empty($args['force'])
            && function_exists('user_can')
            && !user_can($actor['id'], 'manage_options')) {
            // The customer action is still a parcel-timeline event; the events
            // subsystem listens here and records the customer-facing version.
            do_action('ybt_tracking_event', $tracking_id, $action, array(
                'summary'         => $summary,
                'source'          => $source,
                'actor_id'        => $actor['id'],
                'actor_name'      => $actor['name'],
                'actor_role'      => 'customer',
                'tracking_number' => $tracking_number,
                'changes'         => $changes,
                'before'          => $before,
            ));
            return false;
        }

        $changes_json = !empty($changes)
            ? wp_json_encode(array_values($changes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ybt_tracking_edits',
            array(
                'tracking_id'     => $tracking_id,
                'tracking_number' => $tracking_number ? substr((string) $tracking_number, 0, 50) : null,
                'admin_id'        => $actor['id'],
                'admin_name'      => $actor['name'],
                'action'          => substr($action, 0, 50),
                'summary'         => $summary,
                'changes'         => $changes_json,
                'ip_address'      => self::client_ip(),
                'source'          => $source,
                'date_created'    => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            if (function_exists('ybt_log_error')) {
                ybt_log_error('Failed to write tracking edit history', array(
                    'tracking_id' => $tracking_id,
                    'db_error'    => $wpdb->last_error,
                ));
            }
            return false;
        }

        // Same seam for the timeline: admin/system edits are audited AND shown
        // to the parcel owner (as a friendly event, not a field diff).
        do_action('ybt_tracking_event', $tracking_id, $action, array(
            'summary'         => $summary,
            'source'          => $source,
            'actor_id'        => $actor['id'],
            'actor_name'      => $actor['name'],
            'actor_role'      => !empty($args['force']) ? 'system' : 'admin',
            'tracking_number' => $tracking_number,
            'changes'         => $changes,
            'before'          => $before,
        ));

        // Stamp the tracking row so the list can show "last edited by" without a join.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ybt_tracking
                SET last_edited_by = %d,
                    last_edited_by_name = %s,
                    last_edited_at = %s,
                    edit_count = COALESCE(edit_count, 0) + 1
              WHERE id = %d",
            $actor['id'],
            $actor['name'],
            current_time('mysql'),
            $tracking_id
        ));

        if (!isset($args['log_activity']) || $args['log_activity'] !== false) {
            if (function_exists('ybt_log_activity')) {
                ybt_log_activity(
                    $actor['id'],
                    'tracking_edit',
                    sprintf(
                        '%s — tracking %s: %s',
                        $actor['name'],
                        $tracking_number ? $tracking_number : ('#' . $tracking_id),
                        $summary
                    )
                );
            }
        }

        return true;
    }

    /**
     * Convenience wrapper for a manual/raw-SQL update: snapshot, diff, log.
     *
     * @param int         $tracking_id
     * @param object|null $before      Snapshot taken before the raw update.
     * @param array       $update_data Column => new value that was written.
     * @param string      $action
     * @param array       $args
     * @return bool
     */
    public static function log_after_raw_update($tracking_id, $before, array $update_data, $action = 'edit', array $args = array()) {
        return self::log_edit(
            $tracking_id,
            $action,
            self::diff($update_data, $before),
            array_merge($args, array('before' => $before))
        );
    }

    // =========================================================================
    // READ API
    // =========================================================================

    /**
     * Full edit history for one tracking number, newest first.
     *
     * @param int $tracking_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_history($tracking_id, $limit = 100, $offset = 0) {
        global $wpdb;

        if (!self::ready()) {
            return array();
        }

        $limit  = max(1, min(500, (int) $limit));
        $offset = max(0, (int) $offset);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tracking_id, tracking_number, admin_id, admin_name, action,
                    summary, changes, ip_address, source, date_created
               FROM {$wpdb->prefix}ybt_tracking_edits
              WHERE tracking_id = %d
              ORDER BY date_created DESC, id DESC
              LIMIT %d OFFSET %d",
            (int) $tracking_id,
            $limit,
            $offset
        ));

        if (!$rows) {
            return array();
        }

        foreach ($rows as $row) {
            $row->id         = (int) $row->id;
            $row->tracking_id = (int) $row->tracking_id;
            $row->admin_id   = (int) $row->admin_id;
            $row->admin_name = $row->admin_name ?: 'System';
            $row->changes    = !empty($row->changes) ? json_decode($row->changes, true) : array();
            if (!is_array($row->changes)) {
                $row->changes = array();
            }
        }

        return $rows;
    }

    /**
     * Number of recorded edits for a tracking number.
     */
    public static function count_history($tracking_id) {
        global $wpdb;
        if (!self::ready()) {
            return 0;
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ybt_tracking_edits WHERE tracking_id = %d",
            (int) $tracking_id
        ));
    }

    /**
     * Admins who have edited tracking numbers, for the "Edited By" filter.
     *
     * @return array admin_name => number of edits
     */
    public static function get_editing_admins() {
        global $wpdb;
        if (!self::ready()) {
            return array();
        }

        $rows = $wpdb->get_results(
            "SELECT admin_name, COUNT(*) AS edits
               FROM {$wpdb->prefix}ybt_tracking_edits
              WHERE admin_name IS NOT NULL AND admin_name <> ''
              GROUP BY admin_name
              ORDER BY admin_name ASC"
        );

        $admins = array();
        if ($rows) {
            foreach ($rows as $row) {
                $admins[$row->admin_name] = (int) $row->edits;
            }
        }
        return $admins;
    }

    /**
     * All edits across all tracking numbers (used by the global history view).
     */
    public static function get_recent_edits($limit = 100, $offset = 0, $admin_name = '') {
        global $wpdb;

        if (!self::ready()) {
            return array();
        }

        $limit  = max(1, min(500, (int) $limit));
        $offset = max(0, (int) $offset);

        if ($admin_name !== '') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ybt_tracking_edits
                  WHERE admin_name = %s
                  ORDER BY date_created DESC, id DESC
                  LIMIT %d OFFSET %d",
                $admin_name,
                $limit,
                $offset
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ybt_tracking_edits
                  ORDER BY date_created DESC, id DESC
                  LIMIT %d OFFSET %d",
                $limit,
                $offset
            ));
        }

        if ($rows) {
            foreach ($rows as $row) {
                $row->changes = !empty($row->changes) ? json_decode($row->changes, true) : array();
                if (!is_array($row->changes)) {
                    $row->changes = array();
                }
            }
        }

        return $rows ? $rows : array();
    }

    /**
     * Human-readable "3 edits" label + last editor for a tracking row.
     *
     * @param object $item Tracking row (must include the last_edited_* columns).
     * @return array
     */
    public static function describe_last_editor($item) {
        $name  = isset($item->last_edited_by_name) ? $item->last_edited_by_name : '';
        $at    = isset($item->last_edited_at) ? $item->last_edited_at : '';
        $count = isset($item->edit_count) ? (int) $item->edit_count : 0;

        return array(
            'name'      => $name ? $name : '',
            'has_editor'=> $name !== '',
            'edited_at' => $at,
            'edited_ago'=> $at ? human_time_diff(strtotime($at), current_time('timestamp')) . ' ago' : '',
            'count'     => $count,
            'label'     => $count > 0 ? sprintf(_n('%d edit', '%d edits', $count, 'ybt-packer'), $count) : '',
        );
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    /**
     * AJAX: return the edit history for one tracking number.
     */
    public static function ajax_get_history() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $tracking_id = isset($_POST['tracking_id']) ? (int) $_POST['tracking_id'] : 0;
        if ($tracking_id <= 0) {
            wp_send_json_error('Invalid tracking ID');
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT id, tracking_number, last_edited_by_name, last_edited_at, edit_count
               FROM {$wpdb->prefix}ybt_tracking WHERE id = %d",
            $tracking_id
        ));

        if (!$item) {
            wp_send_json_error('Tracking record not found');
        }

        wp_send_json_success(array(
            'tracking_id'     => (int) $item->id,
            'tracking_number' => $item->tracking_number,
            'total'           => self::count_history($tracking_id),
            'entries'         => self::get_history($tracking_id, 200),
        ));
    }

    /**
     * AJAX: return the global "who edited what" feed.
     */
    public static function ajax_get_recent_edits() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $page       = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page   = isset($_POST['per_page']) ? max(1, min(200, (int) $_POST['per_page'])) : 50;
        $admin_name = isset($_POST['admin_name']) ? sanitize_text_field(wp_unslash($_POST['admin_name'])) : '';

        wp_send_json_success(array(
            'entries' => self::get_recent_edits($per_page, ($page - 1) * $per_page, $admin_name),
            'admins'  => self::get_editing_admins(),
            'page'    => $page,
        ));
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Resolve the acting administrator.
     *
     * @return array{id:int,name:string}
     */
    public static function current_actor($admin_id = null, $admin_name = null) {
        if ($admin_id === null) {
            $admin_id = get_current_user_id();
        }
        $admin_id = (int) $admin_id;

        if ($admin_name === null || $admin_name === '') {
            if ($admin_id > 0 && function_exists('get_userdata')) {
                $user = get_userdata($admin_id);
                if ($user) {
                    $admin_name = $user->display_name ?: $user->user_login;
                }
            }
        }

        if ($admin_name === null || $admin_name === '') {
            $admin_name = ($admin_id > 0) ? ('User #' . $admin_id) : 'System';
        }

        return array(
            'id'   => $admin_id,
            'name' => substr((string) $admin_name, 0, 100),
        );
    }

    /**
     * Where did this edit come from?
     */
    private static function detect_source() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'rest';
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return 'ajax';
        }
        if (is_admin()) {
            return 'admin';
        }
        if (function_exists('ybt_is_mobile_request') && ybt_is_mobile_request()) {
            return 'mobile';
        }
        return get_current_user_id() > 0 ? 'web' : 'system';
    }

    /**
     * Loose comparison: null and '' are the same "empty", everything else compares
     * as trimmed strings so MySQL's int/string coercion does not create noise.
     */
    private static function values_equal($a, $b) {
        $a = is_null($a) ? '' : $a;
        $b = is_null($b) ? '' : $b;

        if (is_bool($a)) {
            $a = $a ? '1' : '0';
        }
        if (is_bool($b)) {
            $b = $b ? '1' : '0';
        }
        if (is_array($a)) {
            $a = wp_json_encode($a);
        }
        if (is_array($b)) {
            $b = wp_json_encode($b);
        }

        return trim((string) $a) === trim((string) $b);
    }

    /**
     * Make a stored value readable and safe to display.
     */
    private static function format_value($field, $value) {
        if (is_array($value)) {
            $value = wp_json_encode($value);
        }

        if ($field === 'images') {
            if (empty($value)) {
                return 'none';
            }
            $parts = array_filter(array_map('trim', explode(',', (string) $value)));
            $count = count($parts);
            return sprintf(_n('%d image', '%d images', $count, 'ybt-packer'), $count);
        }

        if ($field === 'status' && $value !== null && $value !== '') {
            return ucwords(str_replace('_', ' ', (string) $value));
        }

        $value = (string) $value;
        $value = trim(wp_strip_all_tags($value));

        if ($value === '') {
            return '—';
        }

        if (strlen($value) > self::MAX_VALUE_LEN) {
            $value = substr($value, 0, self::MAX_VALUE_LEN) . '…';
        }

        return $value;
    }

    /**
     * Build the one-line summary of an edit.
     */
    private static function build_summary($action, array $changes) {
        if (empty($changes)) {
            return self::action_label($action);
        }

        if (count($changes) === 1) {
            $change = reset($changes);
            return sprintf(
                '%s: %s → %s',
                isset($change['label']) ? $change['label'] : 'Field',
                isset($change['from']) ? $change['from'] : '—',
                isset($change['to']) ? $change['to'] : '—'
            );
        }

        $labels = array();
        foreach ($changes as $change) {
            $labels[] = isset($change['label']) ? $change['label'] : 'Field';
        }

        return self::action_label($action) . ': ' . implode(', ', array_slice($labels, 0, 4));
    }

    /**
     * Friendly name for an action slug.
     */
    public static function action_label($action) {
        $labels = array(
            'edit'            => 'Edited',
            'full_edit'       => 'Edited details',
            'status_change'   => 'Status changed',
            'bulk_status'     => 'Bulk status change',
            'item_count'      => 'Item count changed',
            'assign'          => 'Reassigned to user',
            'bulk_assign'     => 'Bulk reassignment',
            'image_upload'    => 'Images uploaded',
            'image_delete'    => 'Image deleted',
            'delete'          => 'Deleted',
            'bulk_delete'     => 'Bulk deleted',
            'create'          => 'Created',
            'bulk_import'     => 'Imported',
            'bulk_add'        => 'Bulk added',
            'claim'           => 'Claimed by user',
            'packing'         => 'Packing updated',
            'mobile_update'   => 'Updated (mobile app)',
        );

        if (isset($labels[$action])) {
            return $labels[$action];
        }

        return ucwords(str_replace('_', ' ', (string) $action));
    }

    /**
     * Client IP, same trust rules as the logger.
     */
    private static function client_ip() {
        $ip = function_exists('ybt_get_client_ip') ? ybt_get_client_ip() : '';
        if ($ip === '' && isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return substr($ip, 0, 45);
    }
}

// Register the history AJAX endpoints.
add_action('wp_ajax_ybt_get_tracking_edit_history', array('YBT_Tracking_Audit', 'ajax_get_history'));
add_action('wp_ajax_ybt_get_tracking_recent_edits', array('YBT_Tracking_Audit', 'ajax_get_recent_edits'));

// Make sure the schema exists on upgraded installs.
add_action('admin_init', array('YBT_Tracking_Audit', 'maybe_install'));
add_action('wp_loaded', array('YBT_Tracking_Audit', 'maybe_install'));
