<?php
/**
 * YBT Admin Shell — full-screen operation console.
 *
 * On YBT plugin pages the WordPress chrome (admin bar + sidebar menu +
 * footer) is hidden and replaced with a slim brand header carrying the
 * page groups and a "WordPress dashboard" exit link. Non-YBT admin pages
 * (posts, plugins, settings…) keep the normal WP shell untouched.
 *
 * @package YBT_Packer
 */

if (!defined('ABSPATH')) {
    exit;
}

class YBT_Admin_Shell {

    public function __construct() {
        add_filter('admin_body_class', array($this, 'body_class'));
        add_action('in_admin_header', array($this, 'render_header'), 0);
        add_action('admin_head', array($this, 'hide_chrome'));
        add_action('wp_ajax_ybt_shell_lookup', array($this, 'ajax_lookup'));
    }

    /**
     * Typeahead lookup for the shell header search: matches tracking
     * numbers (and usernames) and returns register rows with their
     * Full-Edit detail URLs so an admin can jump straight to a parcel.
     */
    public function ajax_lookup() {
        check_ajax_referer('ybt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }

        global $wpdb;
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        if (mb_strlen($q) < 2) {
            wp_send_json_success(array());
        }

        $table  = $wpdb->prefix . 'ybt_tracking';
        $like   = '%' . $wpdb->esc_like($q) . '%';
        $rows   = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tracking_number, username, status
             FROM {$table}
             WHERE tracking_number LIKE %s OR username LIKE %s
             ORDER BY tracking_number = %s DESC, date_added DESC
             LIMIT 8",
            $like, $like, $q
        ));

        $url_base = admin_url('admin.php?page=ybt-add-tracking&edit=');
        wp_send_json_success(array_map(function ($r) use ($url_base) {
            return array(
                'id'       => (int) $r->id,
                'tracking' => $r->tracking_number,
                'user'     => $r->username ?: '-',
                'status'   => $r->status,
                'url'      => $url_base . $r->id,
            );
        }, $rows));
    }

    /**
     * Is the current screen a YBT plugin page?
     */
    private function is_ybt_screen() {
        if (!function_exists('get_current_screen')) {
            return false;
        }
        $screen = get_current_screen();
        return $screen && $screen->id && (
            strpos($screen->id, 'ybt-') !== false ||
            strpos($screen->id, 'toplevel_page_ybt') !== false
        );
    }

    /**
     * Tag YBT pages so CSS/JS can scope the shell.
     */
    public function body_class($classes) {
        if ($this->is_ybt_screen()) {
            $classes .= ' ybt-shell';
        }
        return $classes;
    }

    /**
     * Slim navigation model for the shell header.
     *
     * 'children' feed the "All page groups" panel — the shell replaces the
     * WP sidebar, so every submenu page (FCM credentials, Send Notification,
     * Messages, …) must stay reachable from here.
     */
    private function nav_model() {
        return array(
            array('label' => 'Console',   'slug' => 'ybt-packer',           'icon' => 'fa-gauge-high'),
            array('label' => 'Intake',    'slug' => 'ybt-add-tracking',     'icon' => 'fa-plus'),
            array('label' => 'Register',  'slug' => 'ybt-manage-tracking',  'icon' => 'fa-table-list'),
            array('label' => 'Scanner',   'slug' => 'ybt-barcode-scanner',  'icon' => 'fa-barcode'),
            array('label' => 'Packing',   'slug' => 'ybt-process-packing',  'icon' => 'fa-boxes-packing'),
            array('label' => 'Cartons',   'slug' => 'ybt-manage-cartons',   'icon' => 'fa-box-open'),
            array('label' => 'Import',    'slug' => 'ybt-bulk-import',      'icon' => 'fa-file-import'),
            array('label' => 'Search',    'slug' => 'ybt-advanced-search',  'icon' => 'fa-magnifying-glass'),
            array('label' => 'Support',   'slug' => 'ybt-chat',             'icon' => 'fa-comments', 'children' => array(
                array('label' => 'Chat',              'slug' => 'ybt-chat'),
                array('label' => 'Send Notification', 'slug' => 'ybt-send-notification'),
                array('label' => 'Messages',          'slug' => 'ybt-messages'),
            )),
            array('label' => 'Settings',  'slug' => 'ybt-settings-group',   'icon' => 'fa-gear', 'children' => array(
                array('label' => 'General',           'slug' => 'ybt-settings'),
                array('label' => 'FCM Push Notifs',   'slug' => 'ybt-fcm-settings'),
                array('label' => 'Warehouse',         'slug' => 'ybt-warehouse-settings'),
                array('label' => 'Payments',          'slug' => 'ybt-payment-settings'),
                array('label' => 'Payment Evidence',  'slug' => 'ybt-payment-evidence'),
                array('label' => 'Email',             'slug' => 'ybt-email-settings'),
                array('label' => 'Security',          'slug' => 'ybt-security'),
                array('label' => 'System Tools',      'slug' => 'ybt-tools'),
                array('label' => 'Site Export',       'slug' => 'ybt-site-export'),
                array('label' => 'Site Import',       'slug' => 'ybt-site-import'),
                array('label' => 'Reset Data',        'slug' => 'ybt-reset-data'),
                array('label' => 'Data Cleanup',      'slug' => 'ybt-data-cleanup'),
                array('label' => 'App Updates',       'slug' => 'ybt-app-updates'),
            )),
            array('label' => 'Reports',   'slug' => 'ybt-reports',          'icon' => 'fa-chart-area', 'children' => array(
                array('label' => 'Analytics',         'slug' => 'ybt-analytics'),
                array('label' => 'Smart Dashboard',   'slug' => 'ybt-smart-dashboard'),
                array('label' => 'User Summary',      'slug' => 'ybt-user-summary'),
                array('label' => 'Activity Log',      'slug' => 'ybt-activity-log'),
            )),
            array('label' => 'Invoices',  'slug' => 'ybt-invoices',         'icon' => 'fa-file-invoice'),
        );
    }

    /**
     * Render the brand header inside #wpcontent (before page content).
     */
    public function render_header() {
        if (!$this->is_ybt_screen()) {
            return;
        }

        $screen = get_current_screen();
        $current = '';
        if ($screen && preg_match('/_page_(ybt-[a-z-]+)/', $screen->id, $m)) {
            $current = $m[1];
        } elseif ($screen && strpos($screen->id, 'toplevel_page_ybt-packer') !== false) {
            $current = 'ybt-packer';
        }

        $user = wp_get_current_user();
        $custom_id = get_user_meta($user->ID, 'ybt_custom_user_id', true);
        $name = $user->display_name ?: $user->user_login;
        ?>
        <div id="ybt-shellhead">
            <div class="ybt-shellhead-brand">
                <span class="ybt-shellhead-mark">YBT</span>
                <span class="ybt-shellhead-wordmark">PACKING SERVICE</span>
            </div>

            <nav class="ybt-shellhead-nav" id="ybt-shellnav" aria-label="YBT operations">
                <?php foreach ($this->nav_model() as $item):
                    $active = ($item['slug'] === $current) ? ' active' : '';
                    if ($item['slug'] === 'ybt-settings-group' && $current !== 'ybt-settings-group') {
                        // highlight settings for any settings subpage
                        if (in_array($current, array('ybt-settings', 'ybt-fcm-settings', 'ybt-warehouse-settings', 'ybt-payment-settings', 'ybt-site-export', 'ybt-site-import', 'ybt-reset-data', 'ybt-data-cleanup', 'ybt-app-updates'), true)) {
                            $active = ' active';
                        }
                    }
                    $url = admin_url('admin.php?page=' . $item['slug']);
                ?>
                    <a href="<?php echo esc_url($url); ?>" class="ybt-shellhead-link<?php echo esc_attr($active); ?>">
                        <i class="fas <?php echo esc_attr($item['icon']); ?>"></i><?php echo esc_html($item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <button type="button" class="ybt-shellnav-toggle" aria-expanded="false"
                    aria-controls="ybt-shellnav-panel" title="All page groups">
                <i class="fas fa-bars"></i><span class="ybt-shellnav-count"></span>
            </button>
            <div class="ybt-shellnav-panel" id="ybt-shellnav-panel" hidden>
                <?php foreach ($this->nav_model() as $item):
                    $url2 = admin_url('admin.php?page=' . $item['slug']);
                    $act2 = ($item['slug'] === $current) ? ' active' : '';
                ?>
                    <a href="<?php echo esc_url($url2); ?>" class="ybt-shellnav-panel-link<?php echo esc_attr($act2); ?>">
                        <i class="fas <?php echo esc_attr($item['icon']); ?>"></i><?php echo esc_html($item['label']); ?>
                    </a>
                    <?php foreach (($item['children'] ?? array()) as $child):
                        $url3 = admin_url('admin.php?page=' . $child['slug']);
                        $act3 = ($child['slug'] === $current) ? ' active' : '';
                    ?>
                        <a href="<?php echo esc_url($url3); ?>" class="ybt-shellnav-panel-link is-sub<?php echo esc_attr($act3); ?>">
                            <?php echo esc_html($child['label']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <form class="ybt-shellhead-search" method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>"
                  onsubmit="if(!this.search.value.trim()){return false;}" role="search">
                <input type="hidden" name="page" value="ybt-manage-tracking">
                <label class="screen-reader-text" for="ybt-shellsearch-input">Quick tracking search</label>
                <input type="text" id="ybt-shellsearch-input" name="search" value=""
                       placeholder="Tracking no. or user…" autocomplete="off"
                       role="combobox" aria-expanded="false" aria-controls="ybt-shellsearch-list"
                       aria-autocomplete="list" aria-keyshortcuts="/ Control+K">
                <span class="ybt-shellsearch-hint" aria-hidden="true">/</span>
                <button type="submit" title="Search the tracking register ( / )">
                    <i class="fas fa-magnifying-glass"></i>
                </button>
                <div class="ybt-shellsearch-list" id="ybt-shellsearch-list" role="listbox" hidden></div>
            </form>

            <div class="ybt-shellhead-side">
                <span class="ybt-shellhead-user" title="<?php echo esc_attr($custom_id ? "ID $custom_id" : ''); ?>">
                    <i class="fas fa-user"></i><?php echo esc_html($name); ?>
                </span>
                <a href="<?php echo esc_url(admin_url('index.php')); ?>" class="ybt-shellhead-exit">
                    <i class="fas fa-arrow-left"></i>WordPress dashboard
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Hide the WP chrome on YBT pages only.
     */
    public function hide_chrome() {
        if (!$this->is_ybt_screen()) {
            return;
        }
        ?>
        <style id="ybt-shell-css">
        /* ── full-screen shell: strip WP chrome on YBT pages ── */
        body.ybt-shell #wpadminbar,
        body.ybt-shell #adminmenuback,
        body.ybt-shell #adminmenuwrap,
        body.ybt-shell #adminmenu,
        body.ybt-shell #screen-meta-links,
        body.ybt-shell #screen-meta,
        body.ybt-shell #wpfooter {
            display: none !important;
        }
        body.ybt-shell #wpcontent,
        body.ybt-shell #wpbody,
        body.ybt-shell #wpbody-content {
            margin-left: 0 !important;
            padding-left: 0 !important;
        }
        body.ybt-shell #wpcontent { padding: 0 !important; }
        body.ybt-shell #wpbody-content { padding-bottom: 0; }
        body.ybt-shell.auto-fold #wpcontent,
        html.wp-toolbar body.ybt-shell { padding-top: 0 !important; }
        html.wp-toolbar body.ybt-shell { padding-top: 0 !important; }

        /* WP reserves 32px on <html> for the fixed admin bar — the bar is
           hidden here, so drop the reservation too. This style block only
           ever prints on YBT screens, so other admin pages are unaffected. */
        html.wp-toolbar { padding-top: 0 !important; }

        /* ── neutralize legacy offset hacks from ybt-admin-combined.min.css ──
           Those rules were written to tuck YBT pages under the old admin bar
           (negative margins on #wpbody-content / .ybt-navbar-modern, and a
           -20px left pull on .ybt-admin-wrap). Under the shell they instead
           drag page content up beneath the sticky header. Doubled class
           specificity wins regardless of stylesheet print order. */
        body.ybt-shell.toplevel_page_ybt-packer #wpbody-content,
        body.ybt-shell[class*="ybt-packer"] #wpbody-content {
            margin-top: 0 !important;
        }
        body.ybt-shell.toplevel_page_ybt-packer #wpbody-content > *:first-child,
        body.ybt-shell[class*="ybt-packer"] #wpbody-content > *:first-child {
            margin-top: 0 !important;
        }
        body.ybt-shell .ybt-admin-wrap {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        body.ybt-shell nav.ybt-navbar-modern {
            margin-top: 0 !important;
            top: 0 !important;
            position: static !important;
        }
        /* the settings family nests .wrap inside .ybt-admin-wrapper inside
           .ybt-admin-wrapper — each adds the legacy -10px margin-top pull
           (stacked -20px). Zero them all at ID specificity (the legacy
           rules use #wpbody-content .ybt-admin-wrapper). */
        body.ybt-shell #wpbody-content .ybt-admin-wrapper,
        body.ybt-shell #wpbody-content > .ybt-admin-wrapper:first-child {
            margin-top: 0 !important;
        }

        /* ── brand header ── */
        #ybt-shellhead {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            padding: 10px 22px;
            background: #0f1f17;
            border-bottom: 3px solid #d4af37;
            position: sticky;
            top: 0;
            z-index: 9990;
        }
        .ybt-shellhead-brand { display: flex; align-items: baseline; gap: 8px; }
        .ybt-shellhead-mark {
            font-family: 'Archivo Black', 'Arial Black', sans-serif;
            font-size: 20px;
            color: #f1ead9;
            letter-spacing: 1px;
        }
        .ybt-shellhead-wordmark {
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 9.5px;
            letter-spacing: 2.2px;
            color: #d4af37;
        }
        .ybt-shellhead-nav {
            display: flex;
            align-items: center;
            gap: 2px;
            flex-wrap: nowrap;          /* one row, always */
            flex: 1 1 auto;
            min-width: 0;               /* allow the row to shrink */
            overflow-x: auto;
            scrollbar-width: none;      /* nav looks like part of the bar */
            -ms-overflow-style: none;
        }
        .ybt-shellhead-nav::-webkit-scrollbar { display: none; }
        /* ── edge fades: tint the bar where nav content runs off-screen ──
           JS toggles the classes from scroll position; gradients sit on the
           element's own background (behind link text, so labels stay legible
           as they sink into the header ink). */
        .ybt-shellhead-nav.ybt-navfade-left,
        .ybt-shellhead-nav.ybt-navfade-right {
            background-repeat: no-repeat;
            background-size: 18px 100%;
        }
        .ybt-shellhead-nav.ybt-navfade-left {
            background-image: linear-gradient(to right, #0f1f17, rgba(15, 31, 23, 0));
            background-position: left center;
        }
        .ybt-shellhead-nav.ybt-navfade-right {
            background-image: linear-gradient(to left, #0f1f17, rgba(15, 31, 23, 0));
            background-position: right center;
        }
        .ybt-shellhead-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            border-radius: 8px 8px 0 0;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 11.5px;
            letter-spacing: 0.4px;
            color: rgba(241, 234, 217, 0.72);
            text-decoration: none;
            border: 1px solid transparent;
        }
        .ybt-shellhead-link i { font-size: 10px; color: rgba(212, 175, 55, 0.75); }
        .ybt-shellhead-link:hover { color: #f1ead9; background: rgba(212, 175, 55, 0.08); }
        .ybt-shellhead-link:focus-visible { outline: 2px solid #d4af37; outline-offset: 2px; }
        /* hover previews the tab: a faint gold bar reaching for the rule */
        .ybt-shellhead-link:hover::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -10px;
            height: 3px;
            background: rgba(212, 175, 55, 0.35);
        }
        /* active = ledger tab: full cream ink, faint gold wash, and a 3px
           gold bar that lands flush on the header's bottom rule (header
           padding-bottom is 10px, so bottom:-10px fuses them) */
        .ybt-shellhead-link.active {
            color: #f1ead9;
            background: rgba(212, 175, 55, 0.12);
            font-weight: 600;
            border-radius: 8px 8px 0 0;
        }
        .ybt-shellhead-link.active i { color: #d4af37; }
        .ybt-shellhead-link.active::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -10px;
            height: 3px;
            background: #d4af37;
        }
        /* ── overflow toggle + dropdown (shown only when links don't fit) ── */
        .ybt-shellnav-toggle {
            display: none;              /* JS reveals when nav overflows */
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            background: rgba(212, 175, 55, 0.12);
            border: 1px solid rgba(212, 175, 55, 0.55);
            border-radius: 8px;
            color: #f1ead9;
            cursor: pointer;
            font-size: 12px;
            line-height: 1;
        }
        .ybt-shellnav-toggle i { color: #d4af37; }
        .ybt-shellnav-toggle:hover { background: rgba(212, 175, 55, 0.2); }
        .ybt-shellnav-toggle:focus-visible { outline: 2px solid #f1ead9; outline-offset: 2px; }
        .ybt-shellnav-count {
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 10.5px;
            color: #d4af37;
        }
        .ybt-shellnav-panel {
            position: absolute;
            top: calc(100% + 4px);
            right: 12px;
            z-index: 9991;
            min-width: 200px;
            max-height: 74vh;
            overflow-y: auto;
            padding: 6px;
            background: #16302480;
            background: rgba(22, 48, 36, 0.98);
            border: 1px solid rgba(212, 175, 55, 0.4);
            border-radius: 10px;
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.45);
        }
        .ybt-shellnav-panel-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 12px;
            border-radius: 7px;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 11.5px;
            letter-spacing: 0.4px;
            color: rgba(241, 234, 217, 0.82);
            text-decoration: none;
        }
        .ybt-shellnav-panel-link i { width: 13px; font-size: 10px; color: rgba(212, 175, 55, 0.8); }
        .ybt-shellnav-panel-link:hover { background: rgba(212, 175, 55, 0.12); color: #f1ead9; }
        .ybt-shellnav-panel-link.active {
            color: #d4af37;
            font-weight: 600;
            background: rgba(212, 175, 55, 0.1);
        }
        /* group separators: each top-level entry starts a visually distinct block */
        .ybt-shellnav-panel-link:not(.is-sub):not(:first-child) {
            margin-top: 6px;
            border-top: 1px solid rgba(212, 175, 55, 0.18);
            padding-top: 12px;
        }
        /* submenu entries (FCM, Messages, …) — indented ledger lines */
        .ybt-shellnav-panel-link.is-sub {
            padding: 6px 12px 6px 34px;
            font-size: 10.5px;
            color: rgba(241, 234, 217, 0.62);
        }
        .ybt-shellnav-panel-link.is-sub::before {
            content: '\203A';           /* › */
            margin-right: 8px;
            color: rgba(212, 175, 55, 0.6);
        }
        .ybt-shellnav-panel-link.is-sub:hover { color: #f1ead9; }
        .ybt-shellnav-panel-link.is-sub.active { color: #d4af37; }

        .ybt-shellhead-side { display: flex; align-items: center; gap: 14px; }
        /* ── quick tracking search ── */
        .ybt-shellhead-search { display: flex; align-items: stretch; }
        .ybt-shellhead-search input[type="text"] {
            width: 190px;
            padding: 7px 10px;
            background: rgba(241, 234, 217, 0.08);
            border: 1px solid rgba(241, 234, 217, 0.25);
            border-right: 0;
            border-radius: 8px 0 0 8px;
            color: #f1ead9;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 11.5px;
            letter-spacing: 0.3px;
        }
        .ybt-shellhead-search input[type="text"]::placeholder { color: rgba(241, 234, 217, 0.45); }
        .ybt-shellhead-search input[type="text"]:focus {
            outline: none;
            border-color: #d4af37;
            background: rgba(241, 234, 217, 0.12);
        }
        .ybt-shellhead-search button {
            padding: 7px 11px;
            background: #d4af37;
            border: 1px solid #d4af37;
            border-radius: 0 8px 8px 0;
            color: #0f1f17;
            cursor: pointer;
            font-size: 11px;
            line-height: 1;
        }
        .ybt-shellhead-search button:hover { background: #e6c560; }
        .ybt-shellhead-search button:focus-visible { outline: 2px solid #f1ead9; outline-offset: 2px; }
        /* ── keyboard hint chip (hidden once the field is focused/filled) ── */
        .ybt-shellsearch-hint {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            padding: 0 5px;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 10px;
            line-height: 14px;
            color: rgba(241, 234, 217, 0.5);
            border: 1px solid rgba(241, 234, 217, 0.3);
            border-radius: 4px;
            pointer-events: none;
            opacity: 1;
            transition: opacity 0.12s ease;
        }
        .ybt-shellhead-search:focus-within .ybt-shellsearch-hint,
        .ybt-shellhead-search.ybt-ss-hasvalue .ybt-shellsearch-hint { opacity: 0; }
        /* ── typeahead dropdown ── */
        .ybt-shellhead-search { position: relative; }
        .ybt-shellsearch-list {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            min-width: 290px;
            max-width: 360px;
            max-height: 320px;
            overflow-y: auto;
            z-index: 9992;
            padding: 5px;
            background: rgba(22, 48, 36, 0.98);
            border: 1px solid rgba(212, 175, 55, 0.4);
            border-radius: 10px;
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.45);
        }
        .ybt-shellsearch-item {
            display: block;
            padding: 8px 10px;
            border-radius: 7px;
            text-decoration: none;
            color: rgba(241, 234, 217, 0.85);
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 11.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ybt-shellsearch-item:hover,
        .ybt-shellsearch-item[aria-selected="true"] { background: rgba(212, 175, 55, 0.14); color: #f1ead9; }
        .ybt-shellsearch-item .ybt-ss-track { color: #d4af37; font-weight: 600; letter-spacing: 0.3px; }
        .ybt-shellsearch-item .ybt-ss-meta { color: rgba(241, 234, 217, 0.55); font-size: 10.5px; margin-left: 6px; }
        .ybt-shellsearch-status {
            padding: 8px 10px;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 11px;
            color: rgba(241, 234, 217, 0.55);
        }
        .ybt-shellhead-user {
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 11.5px;
            color: rgba(241, 234, 217, 0.85);
        }
        .ybt-shellhead-user i { color: #10b981; margin-right: 4px; }
        .ybt-shellhead-exit {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            border: 1.5px solid rgba(241, 234, 217, 0.35);
            border-radius: 8px;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 11.5px;
            color: #f1ead9;
            text-decoration: none;
        }
        .ybt-shellhead-exit i { font-size: 10px; color: #10b981; }
        .ybt-shellhead-exit:hover { border-color: #10b981; background: rgba(16, 185, 129, 0.1); }

        /* page content sits in a kraft sheet, matching the design system */
        body.ybt-shell .ybt-admin-wrapper {
            max-width: 1460px;
            margin: 0 auto;
            padding: 26px 22px 60px;
        }
        /* legacy wrap variant pages still need breathing room under the
           sticky header; the wrapper class carries the sheet styling */
        body.ybt-shell .ybt-admin-wrap {
            padding-top: 26px;
            padding-bottom: 60px;
        }
        /* raw WP-standard pages (settings family etc.) render inside the
           stock .wrap container — give it header clearance too */
        /* raw WP-standard pages (settings family etc.) render inside the
           stock .wrap container — give it header clearance too. ID-grade
           specificity outranks every legacy .wrap/#wpbody-content rule. */
        body.ybt-shell #wpbody-content .wrap,
        body.ybt-shell #wpbody-content > .wrap:first-child {
            margin-top: 0 !important;
            padding-top: 30px !important;
        }
        /* clear the sticky header (unless the page scrolls beneath it) */
        body.ybt-shell #wpbody-content { padding-top: 0; }
        body.ybt-shell .ybt-admin-wrapper { scroll-margin-top: 80px; }
        @media (min-width: 783px) {
            body.ybt-shell #wpbody { margin-top: 0; }
        }
        @media (max-width: 1100px) {
            #ybt-shellhead { padding: 10px 14px; gap: 10px; }
            .ybt-shellhead-link { padding: 6px 8px; font-size: 10.5px; }
            .ybt-shellhead-search input[type="text"] { width: 130px; }
        }
        @media (max-width: 780px) {
            /* brand + search leave the row; nav gets its own full-width row */
            #ybt-shellhead { flex-wrap: wrap; gap: 8px 10px; }
            .ybt-shellhead-wordmark { display: none; }
            .ybt-shellhead-search { order: 3; flex: 1 1 auto; }
            .ybt-shellhead-search input[type="text"] { flex: 1 1 auto; width: auto; }
            .ybt-shellhead-nav { order: 4; flex-basis: 100%; padding-bottom: 2px; }
            .ybt-shellnav-toggle { order: 5; margin-left: auto; }
            .ybt-shellhead-side { order: 6; }
            .ybt-shellnav-panel { left: 12px; right: 12px; }
        }
        </style>
        <script>
        // Keep WP's dynamic menu behaviour from fighting the hidden sidebar.
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('folded');
            var mq = window.matchMedia('(max-width: 960px)');
            if (!mq.matches && document.body.classList.contains('auto-fold')) {
                document.body.classList.remove('auto-fold');
            }

            /* ── shell header typeahead ── */
            var sform = document.querySelector('.ybt-shellhead-search');
            var sinput = document.getElementById('ybt-shellsearch-input');
            var slist = document.getElementById('ybt-shellsearch-list');
            if (sform && sinput && slist && window.jQuery && window.ybt_admin) {
                var $inp = jQuery(sinput), sTimer = null, sLast = '', sIdx = -1;
                var opts = function () { return slist.querySelectorAll('.ybt-shellsearch-item'); };
                function closeList() {
                    slist.hidden = true; slist.innerHTML = ''; sIdx = -1;
                    sinput.setAttribute('aria-expanded', 'false');
                }
                function render(res) {
                    var rows = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                    slist.innerHTML = '';
                    if (!rows.length) {
                        slist.innerHTML = '<div class="ybt-shellsearch-status">No matching parcels</div>';
                    } else {
                        rows.forEach(function (r, i) {
                            var a = document.createElement('a');
                            a.className = 'ybt-shellsearch-item';
                            a.href = r.url;
                            a.setAttribute('role', 'option');
                            a.setAttribute('aria-selected', i === sIdx ? 'true' : 'false');
                            var t = document.createElement('span'); t.className = 'ybt-ss-track'; t.textContent = r.tracking;
                            var m = document.createElement('span'); m.className = 'ybt-ss-meta'; m.textContent = r.user + ' · ' + r.status;
                            a.appendChild(t); a.appendChild(m);
                            slist.appendChild(a);
                        });
                    }
                    slist.hidden = false;
                    sinput.setAttribute('aria-expanded', 'true');
                }
                $inp.on('input', function () {
                    sform.classList.toggle('ybt-ss-hasvalue', sinput.value.length > 0);
                    var q = sinput.value.trim();
                    clearTimeout(sTimer);
                    if (q.length < 2) { closeList(); return; }
                    sTimer = setTimeout(function () {
                        if (q === sLast) return;
                        sLast = q; sIdx = -1;
                        jQuery.post(window.ybt_admin.ajax_url,
                            { action: 'ybt_shell_lookup', nonce: window.ybt_admin.nonce, q: q },
                            render
                        );
                    }, 220);
                });
                sinput.addEventListener('keydown', function (e) {
                    var list = opts();
                    if (slist.hidden) {
                        if (e.key === 'ArrowDown' && sinput.value.trim().length >= 2) { e.preventDefault(); $inp.trigger('input'); }
                        return;
                    }
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (!list.length) return;
                        sIdx = e.key === 'ArrowDown' ? (sIdx + 1) % list.length : (sIdx - 1 + list.length) % list.length;
                        list.forEach(function (o, i) { o.setAttribute('aria-selected', i === sIdx ? 'true' : 'false'); });
                        list[sIdx].scrollIntoView({ block: 'nearest' });
                    } else if (e.key === 'Enter' && sIdx >= 0 && list[sIdx]) {
                        e.preventDefault();
                        location.href = list[sIdx].href;
                    } else if (e.key === 'Escape') {
                        closeList();
                    }
                });
                document.addEventListener('click', function (e) {
                    if (!slist.hidden && !sform.contains(e.target)) closeList();
                });

                /* keyboard shortcuts: "/" or Ctrl/Cmd+K focus the search from
                   anywhere; typing targets are respected; Escape blurs. */
                document.addEventListener('keydown', function (e) {
                    var tag = (e.target.tagName || '').toLowerCase();
                    var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
                    if ((e.key === '/' && !typing) || ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K'))) {
                        e.preventDefault();
                        sinput.focus();
                        sinput.select();
                    } else if (e.key === 'Escape' && document.activeElement === sinput && slist.hidden) {
                        sinput.blur();
                    }
                });
            }

            /* ── shell nav overflow watcher ── */
            var nav = document.getElementById('ybt-shellnav');
            var toggle = document.querySelector('.ybt-shellnav-toggle');
            var panel = document.getElementById('ybt-shellnav-panel');
            if (!nav || !toggle || !panel) return;
            var count = toggle.querySelector('.ybt-shellnav-count');

            function syncFades() {
                var max = nav.scrollWidth - nav.clientWidth;
                nav.classList.toggle('ybt-navfade-left', nav.scrollLeft > 1);
                nav.classList.toggle('ybt-navfade-right', nav.scrollLeft < max - 1);
            }

            function syncOverflow() {
                var overflowing = nav.scrollWidth > nav.clientWidth + 1;
                /* the toggle is the only route to submenu pages (FCM, Send
                   Notification, …) — keep it visible even when the top row
                   fits, so "All page groups" is always one click away */
                toggle.style.display = 'inline-flex';
                count.textContent = overflowing ? nav.querySelectorAll('.ybt-shellhead-link').length : '';
                if (!overflowing) {
                    panel.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                }
                if (overflowing) {
                    // scroll the active tab into view
                    var act = nav.querySelector('.active');
                    if (act) act.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
                syncFades();
            }

            nav.addEventListener('scroll', syncFades, { passive: true });

            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                panel.hidden = !panel.hidden;
                toggle.setAttribute('aria-expanded', String(!panel.hidden));
            });
            document.addEventListener('click', function (e) {
                if (!panel.hidden && !panel.contains(e.target)) {
                    panel.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !panel.hidden) {
                    panel.hidden = true;
                    toggle.setAttribute('aria-expanded', '');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.focus();
                }
            });

            if ('ResizeObserver' in window) {
                new ResizeObserver(syncOverflow).observe(nav);
            }
            window.addEventListener('resize', syncOverflow);
            syncOverflow();
        });
        </script>
        <?php
    }
}

new YBT_Admin_Shell();
