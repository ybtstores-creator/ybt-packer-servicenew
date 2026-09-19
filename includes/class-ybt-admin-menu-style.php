<?php
/**
 * YBT Admin Menu Style
 *
 * Handles visual styling for the restructured YBT admin menu:
 * section labels between groups, accent strips on hover/active,
 * and folded sidebar support.
 *
 * @package YBT_Packer
 * @since   3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class YBT_Admin_Menu_Style {

    /**
     * Constructor – registers the admin_head hook on every admin page
     * so menu group labels and accent strips are always visible.
     */
    public function __construct() {
        add_action('admin_head', array($this, 'render'));
    }

    /**
     * Render inline CSS + JS for menu group section labels and accent strips.
     *
     * Outputs:
     *  - CSS for .ybt-menu-section headings (hidden in folded mode)
     *  - CSS for ::after accent colour strips on each group
     *  - Vanilla JS that injects <li class="ybt-menu-section"> before each group
     */
    public function render() {
        ?>
        <style>
        /* === YBT Menu Group Section Labels ===
         * <li> elements injected via JS below.
         * Hidden when sidebar is collapsed (body.folded). */
        body:not(.folded) li.ybt-menu-section {
            pointer-events: none;
            cursor: default;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        body:not(.folded) li.ybt-menu-section .ybt-menu-section-label {
            display: block;
            padding: 14px 15px 2px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(148, 163, 184, 0.4);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            user-select: none;
        }
        body.folded li.ybt-menu-section {
            display: none;
        }

        /* --- Light accent colour strip on the left for each group --- */
        #toplevel_page_ybt-parcels,
        #toplevel_page_ybt-reports,
        #toplevel_page_ybt-settings-group,
        #toplevel_page_ybt-support,
        #toplevel_page_ybt-invoices {
            position: relative;
        }
        #toplevel_page_ybt-parcels::after,
        #toplevel_page_ybt-reports::after,
        #toplevel_page_ybt-settings-group::after,
        #toplevel_page_ybt-support::after,
        #toplevel_page_ybt-invoices::after {
            content: '';
            position: absolute;
            left: 0;
            top: 4px;
            bottom: 4px;
            width: 3px;
            border-radius: 0 2px 2px 0;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        #toplevel_page_ybt-parcels::after        { background: #3B82F6; }
        #toplevel_page_ybt-reports::after        { background: #10B981; }
        #toplevel_page_ybt-settings-group::after { background: #F59E0B; }
        #toplevel_page_ybt-support::after        { background: #8B5CF6; }
        #toplevel_page_ybt-invoices::after       { background: #EC4899; }
        /* Show accent on hover / when menu or submenu is active */
        #toplevel_page_ybt-parcels:hover::after,
        #toplevel_page_ybt-parcels.wp-has-current-submenu::after,
        #toplevel_page_ybt-reports:hover::after,
        #toplevel_page_ybt-reports.wp-has-current-submenu::after,
        #toplevel_page_ybt-settings-group:hover::after,
        #toplevel_page_ybt-settings-group.wp-has-current-submenu::after,
        #toplevel_page_ybt-support:hover::after,
        #toplevel_page_ybt-support.wp-has-current-submenu::after,
        #toplevel_page_ybt-invoices:hover::after,
        #toplevel_page_ybt-invoices.wp-has-current-submenu::after {
            opacity: 0.7;
        }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.body.classList.contains('folded')) return;
            var sections = [
                { id: 'toplevel_page_ybt-parcels',        label: 'PARCELS' },
                { id: 'toplevel_page_ybt-reports',        label: 'REPORTS' },
                { id: 'toplevel_page_ybt-settings-group', label: 'SETTINGS' },
                { id: 'toplevel_page_ybt-support',         label: 'SUPPORT' },
                { id: 'toplevel_page_ybt-invoices',       label: 'INVOICES' }
            ];
            for (var i = 0; i < sections.length; i++) {
                var menu = document.getElementById(sections[i].id);
                if (menu) {
                    var li = document.createElement('li');
                    li.className = 'ybt-menu-section';
                    li.innerHTML = '<span class="ybt-menu-section-label">' + sections[i].label + '</span>';
                    menu.parentNode.insertBefore(li, menu);
                }
            }
        });
        </script>
        <?php
    }
}

// Instantiate so the admin_head hook is registered on load
new YBT_Admin_Menu_Style();
