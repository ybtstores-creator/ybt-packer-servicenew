<?php
if (!defined('ABSPATH')) exit;

/**
 * Helper to check active state in navbar
 */
if (!function_exists('ybt_is_active')) {
    function ybt_is_active($slug) {
        $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ($slug === 'dashboard' && (strpos($current_url, '/dashboard/') !== false || $current_url === '/' || $current_url === '')) return 'active';
        return (!empty($current_url) && strpos($current_url, '/' . $slug . '/') !== false) ? 'active' : '';
    }
}

/**
 * Renders the responsive dashboard navigation bar.
 * Includes a JS fallback for dropdowns to prevent theme conflicts.
 */
function render_dashboard_navbar($current_user) {
    ?>
    <nav class="navbar navbar-expand-lg ybt-navbar sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo esc_url(home_url('/dashboard/')); ?>">
                <img src="<?php echo ybt_get_logo_url(); ?>"
                     alt="YBT PACKING SERVICE"
                     loading="eager"
                     fetchpriority="high"
                     style="height: 40px; border-radius: 8px;">
                <span class="d-none d-sm-inline tracking-tight" style="font-size: 0.85rem; font-weight: 900;">YBT PACKING SERVICE</span>
            </a>

            <button class="navbar-toggler border-0" type="button" id="ybt-mobile-menu-toggle" aria-controls="ybtNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="filter: brightness(0.5);"><i class="fas fa-bars fa-lg"></i></span>
            </button>

            <div class="collapse navbar-collapse" id="ybtNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 pt-2 pt-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ybt_is_active('dashboard'); ?>" href="<?php echo esc_url(home_url('/dashboard/')); ?>">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ybt_is_active('add-tracking'); ?>" href="<?php echo esc_url(home_url('/add-tracking/')); ?>">
                            <i class="fas fa-plus-circle me-1"></i> Add Item
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ybt_is_active('not-received'); ?>" href="<?php echo esc_url(home_url('/not-received/')); ?>">
                            <i class="fas fa-clock me-1"></i> Not Received
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ybt_is_active('received'); ?>" href="<?php echo esc_url(home_url('/received/')); ?>">
                            <i class="fas fa-check me-1"></i> Received
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ybt_is_active('packing-in-progress'); ?>" href="<?php echo esc_url(home_url('/packing-in-progress/')); ?>">
                            <i class="fas fa-box-open me-1"></i> Packing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ybt_is_active('packed'); ?>" href="<?php echo esc_url(home_url('/packed/')); ?>">
                            <i class="fas fa-box me-1"></i> Packed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ybt_is_active('completed'); ?>" href="<?php echo esc_url(home_url('/completed/')); ?>">
                            <i class="fas fa-check-circle me-1"></i> Sent
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ybt_is_active('support'); ?>" href="<?php echo esc_url(home_url('/support/')); ?>">
                            <i class="fas fa-headset me-1"></i> Support
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="ybtUserDropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span><?php echo esc_html($current_user->display_name); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg" aria-labelledby="ybtUserDropdown">
                            <li><span class="dropdown-header text-muted small text-uppercase">Account</span></li>
                            <li><a class="dropdown-item" href="<?php echo esc_url(home_url('/profile/')); ?>"><i class="fas fa-id-card me-2 text-primary"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo esc_url(wp_logout_url(home_url('/login/'))); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <script>
    jQuery(document).ready(function($) {
        // Mobile Toggle Helper
        const toggleMobileMenu = (forceClose = false) => {
            const $btn = $('#ybt-mobile-menu-toggle');
            const $menu = $('#ybtNavbar');
            const isExpanded = $btn.attr('aria-expanded') === 'true';

            if (forceClose || isExpanded) {
                $menu.slideUp(200);
                $btn.attr('aria-expanded', 'false');
            } else {
                $menu.slideDown(200);
                $btn.attr('aria-expanded', 'true');
            }
        };

        $('#ybt-mobile-menu-toggle').click(function(e) {
            e.stopPropagation();
            toggleMobileMenu();
        });

        // Dropdown Logic
        $('.dropdown-toggle').click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $el = $(this);
            const $menu = $el.next('.dropdown-menu');
            const isExpanded = $el.attr('aria-expanded') === 'true';

            $('.dropdown-menu').not($menu).removeClass('show');
            $('.dropdown-toggle').not($el).attr('aria-expanded', 'false');

            $menu.toggleClass('show');
            $el.attr('aria-expanded', !isExpanded);
        });

        // Global Close Handlers
        const closeAllMenus = () => {
            $('.dropdown-menu').removeClass('show');
            $('.dropdown-toggle').attr('aria-expanded', 'false');
            if ($(window).width() < 992) {
                toggleMobileMenu(true);
            }
        };

        $(document).click(function(e) {
            if (!$(e.target).closest('.dropdown').length && !$(e.target).closest('#ybtNavbar').length) {
                closeAllMenus();
            }
        });

        $(document).keydown(function(e) {
            if (e.key === 'Escape') {
                closeAllMenus();
            }
        });
    });
    </script>

    <style>
    /* Inline specific tweaks */
    @media (max-width: 991px) {
        #ybtNavbar { display: none; background: rgba(255,255,255,0.95); padding: 15px; border-radius: 12px; margin-top: 10px; border: 1px solid rgba(0,0,0,0.05); }
        .dropdown-menu { border: none; background: #f8f9fa; margin-top: 10px; box-shadow: none !important; }
        .dropdown-menu.show { display: block; }
    }
    </style>

    <?php
    // Site-Wide Popup Logic (Optimized for Dashboard & Session)
    $popup_enabled = get_option('ybt_popup_enabled');
    $popup_message = get_option('ybt_popup_message');
    $popup_url = get_option('ybt_popup_url');
    $popup_image = get_option('ybt_popup_image');

    // ONLY show on dashboard
    $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $is_dashboard = (strpos($current_url, '/dashboard/') !== false || $current_url === '/' || $current_url === '');

    if ($is_dashboard && $popup_enabled && $popup_message): ?>
    <div id="ybt-dashboard-popup" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.85); z-index:99999; align-items:center; justify-content:center;">
        <div class="glass-card animate-up" style="background:#fff; padding:40px; border-radius:24px; max-width:550px; width:90%; text-align:center; position:relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow:hidden;">
            <button onclick="closeYbtPopup()" style="position:absolute; top:20px; right:20px; background:#f1f5f9; border:none; width:35px; height:35px; border-radius:50%; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s; z-index:10;">&times;</button>

            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center" style="width:60px; height:60px; background:rgba(0, 255, 136, 0.1); border-radius:50%; margin-bottom:15px;">
                    <i class="fas fa-bell fa-2x" style="color: var(--neo-green);"></i>
                </div>
                <h3 style="font-weight:800; color:#1e293b; margin-bottom:10px;">Important Update</h3>
            </div>

            <?php if ($popup_image): ?>
                <div class="mb-4">
                    <img src="<?php echo esc_url($popup_image); ?>"
                         alt="Popup announcement"
                         loading="lazy"
                         decoding="async"
                         style="max-width:100%; max-height:250px; border-radius:16px; object-fit:cover; border:1px solid #e2e8f0;">
                </div>
            <?php endif; ?>

            <div style="font-size:1.1rem; color:#475569; line-height:1.7; margin-bottom:30px; white-space:pre-wrap;"><?php echo nl2br(esc_html($popup_message)); ?></div>

            <div class="d-flex gap-3 justify-content-center">
                <?php if ($popup_url): ?>
                    <a href="<?php echo esc_url($popup_url); ?>" target="_blank" onclick="closeYbtPopup()" class="ybt-btn-premium" style="text-decoration:none; padding: 12px 25px;">
                        <i class="fas fa-external-link-alt me-2"></i> Take Action
                    </a>
                <?php endif; ?>
                <button onclick="closeYbtPopup()" class="btn btn-light px-4 py-2 fw-bold" style="border-radius:12px;">Dismiss</button>
            </div>
        </div>
    </div>
    <script>
    function closeYbtPopup() {
        const popup = document.getElementById('ybt-dashboard-popup');
        if (popup) {
            popup.style.display = 'none';
            // Save state to localStorage to prevent showing again for this message
            const msgHash = '<?php echo esc_js(substr(wp_hash($popup_message), 0, 8)); ?>';
            localStorage.setItem('ybt_notice_seen', msgHash);
        }
    }

    jQuery(document).ready(function($) {
        const msgHash = '<?php echo esc_js(substr(wp_hash($popup_message), 0, 8)); ?>';
        const alreadySeen = localStorage.getItem('ybt_notice_seen');

        if (alreadySeen === msgHash) {
            return;
        }

        // Show popup with slight delay for better UX
        setTimeout(() => {
            $('#ybt-dashboard-popup').css('display', 'flex').hide().fadeIn(400);
        }, 800);
    });
    </script>
    <?php endif; ?>
    <?php
}
