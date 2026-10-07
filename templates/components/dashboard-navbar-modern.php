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
 * Renders the dashboard navbar — "The Manifest Tab".
 *
 * Kraft paper, mono routing index, emerald active tab with a torn-ticket notch.
 * Shared tokens come from assets/css/ybt-consignment.css (enqueued); the block
 * below is the navbar-specific layer.
 */
function render_dashboard_navbar($current_user) {
    ?><style>    /* ================================================================
       YBT NAVBAR — "The Manifest Tab" (page-specific layer)
       ================================================================ */

    #ybt-nav2{

        position:sticky;
        top:0;
        z-index:1000;
        display:block;
    }

    /* ── the bar: paper tab with ink border + offset shadow ──── */
    #ybt-nav2 .ybt-nav2-bar{
        background:var(--kraft);
        background-image:repeating-linear-gradient(90deg, rgba(60,42,10,.03) 0 1px, transparent 1px 26px);
        border-bottom:1.5px solid var(--ink);
        box-shadow:0 3px 0 rgba(15,31,23,.12);
    }
    #ybt-nav2 .ybt-nav2-inner{
        max-width:1200px;
        margin:0 auto;
        padding:12px clamp(14px,3vw,28px);
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        flex-wrap:wrap;
    }

    /* ── brand: consignment mark ─────────────────────────────── */
    #ybt-nav2 .ybt-nav2-brand{
        display:flex;
        align-items:baseline;
        gap:10px;
        text-decoration:none;
    }
    #ybt-nav2 .ybt-nav2-brand strong{
        font-family:var(--display);
        font-weight:400;
        font-size:22px;
        letter-spacing:.02em;
        color:var(--emerald-deep);
    }
    #ybt-nav2 .ybt-nav2-brand span{
        font-family:var(--mono);
        font-size:10px;
        font-weight:600;
        letter-spacing:.2em;
        color:#5c5543;
        text-transform:uppercase;
    }

    /* ── routing index (desktop links) ───────────────────────── */
    #ybt-nav2 .ybt-nav2-links{
        display:flex;
        align-items:center;
        gap:4px;
        flex-wrap:wrap;
        list-style:none;
        margin:0;
        padding:0;
    }
    #ybt-nav2 .ybt-nav2-link{
        display:inline-flex;
        align-items:center;
        gap:7px;
        font-family:var(--mono);
        font-size:12.5px;
        font-weight:500;
        color:#4a4a40;
        text-decoration:none;
        padding:8px 12px;
        border:1.5px solid transparent;
        border-radius:8px;
        transition:color .15s ease, border-color .15s ease, background-color .15s ease;
    }
    #ybt-nav2 .ybt-nav2-link i{ font-size:12px; opacity:.75; }
    #ybt-nav2 .ybt-nav2-link:hover{
        color:var(--emerald-deep);
        background:var(--emerald-tint);
        border-color:var(--emerald);
    }
    /* active tab: emerald ticket with torn notch */
    #ybt-nav2 .ybt-nav2-link.active{
        position:relative;
        color:var(--emerald-deep);
        background:var(--emerald-tint);
        border:1.5px solid var(--emerald);
        font-weight:600;
    }
    #ybt-nav2 .ybt-nav2-link.active::after{
        content:'';
        position:absolute;
        top:50%;
        right:-7px;
        width:9px;
        height:9px;
        transform:translateY(-50%) rotate(45deg);
        background:var(--emerald-tint);
        border-top:1.5px solid var(--emerald);
        border-right:1.5px solid var(--emerald);
        clip-path:polygon(0 0, 100% 0, 100% 100%);
    }

    /* ── user menu ───────────────────────────────────────────── */
    #ybt-nav2 .ybt-nav2-user{
        display:flex;
        align-items:center;
        gap:12px;
        position:relative;
    }
    #ybt-nav2 .ybt-nav2-userbtn{
        display:flex;
        align-items:center;
        gap:9px;
        background:var(--paper);
        border:1.5px solid var(--ink);
        border-radius:8px;
        padding:7px 12px;
        font-family:var(--mono);
        font-size:12.5px;
        font-weight:600;
        color:var(--ink);
        cursor:pointer;
        box-shadow:3px 3px 0 rgba(15,31,23,.12);
        transition:transform .15s ease, box-shadow .15s ease;
    }
    #ybt-nav2 .ybt-nav2-userbtn:hover{ transform:translateY(-1px); box-shadow:4px 4px 0 rgba(15,31,23,.14); }
    #ybt-nav2 .ybt-nav2-avatar{
        width:26px;height:26px;
        display:flex;align-items:center;justify-content:center;
        background:var(--emerald-tint);
        border:1.5px solid var(--emerald);
        border-radius:6px;
        color:var(--emerald-deep);
        font-size:12px;
    }
    #ybt-nav2 .ybt-nav2-chev{ font-size:10px; color:#5c5543; transition:transform .2s ease; }
    #ybt-nav2 .ybt-nav2-userbtn.active .ybt-nav2-chev{ transform:rotate(180deg); }

    /* dropdown: a routing slip */
    #ybt-nav2 .ybt-nav2-drop{
        position:absolute;
        top:calc(100% + 10px);
        right:0;
        min-width:230px;
        background:var(--paper);
        border:1.5px solid var(--ink);
        border-radius:10px;
        box-shadow:6px 6px 0 rgba(15,31,23,.18);
        display:none;
        overflow:hidden;
        z-index:1100;
    }
    #ybt-nav2 .ybt-nav2-drop.show{ display:block; }
    #ybt-nav2 .ybt-nav2-drop-head{
        display:flex;
        gap:10px;
        align-items:center;
        padding:14px 16px;
        background:var(--kraft);
        border-bottom:2px dashed var(--kraft-line);
    }
    #ybt-nav2 .ybt-nav2-drop-head .ybt-nav2-avatar{ width:34px;height:34px;font-size:14px; }
    #ybt-nav2 .ybt-nav2-drop-name{
        font-weight:700;
        font-size:14px;
        color:var(--ink);
        line-height:1.3;
    }
    #ybt-nav2 .ybt-nav2-drop-mail{
        font-family:var(--mono);
        font-size:11px;
        color:#5c5543;
    }
    #ybt-nav2 .ybt-nav2-drop a{
        display:flex;
        align-items:center;
        gap:10px;
        padding:11px 16px;
        font-family:var(--mono);
        font-size:12.5px;
        font-weight:500;
        color:var(--ink);
        text-decoration:none;
        border-bottom:1px solid var(--kraft-line);
    }
    #ybt-nav2 .ybt-nav2-drop a:last-child{ border-bottom:none; }
    #ybt-nav2 .ybt-nav2-drop a:hover{ background:var(--emerald-tint); color:var(--emerald-deep); }
    #ybt-nav2 .ybt-nav2-drop a.ybt-nav2-out{ color:#9c2020; }
    #ybt-nav2 .ybt-nav2-drop a.ybt-nav2-out:hover{ background:#fdeeee; color:#9c2020; }
    #ybt-nav2 .ybt-nav2-drop i{ width:16px; text-align:center; opacity:.75; }

    /* ── mobile toggle ───────────────────────────────────────── */
    #ybt-nav2 .ybt-nav2-burger{
        display:none;
        flex-direction:column;
        justify-content:center;
        gap:5px;
        width:42px;height:38px;
        background:var(--paper);
        border:1.5px solid var(--ink);
        border-radius:8px;
        cursor:pointer;
        padding:0 10px;
        box-shadow:3px 3px 0 rgba(15,31,23,.12);
    }
    #ybt-nav2 .ybt-nav2-burger span{
        display:block;
        height:2px;
        background:var(--ink);
        border-radius:2px;
    }
    #ybt-nav2 .ybt-nav2-burger.active span:nth-child(1){ transform:translateY(7px) rotate(45deg); }
    #ybt-nav2 .ybt-nav2-burger.active span:nth-child(2){ opacity:0; }
    #ybt-nav2 .ybt-nav2-burger.active span:nth-child(3){ transform:translateY(-7px) rotate(-45deg); }

    /* ── mobile drawer ───────────────────────────────────────── */
    #ybt-nav2 .ybt-nav2-drawer{
        display:none;
        background:var(--paper);
        border-bottom:1.5px solid var(--ink);
        box-shadow:0 3px 0 rgba(15,31,23,.12);
        padding:10px 16px 14px;
    }
    #ybt-nav2 .ybt-nav2-drawer.show{ display:block; }
    #ybt-nav2 .ybt-nav2-drawer ul{
        list-style:none;
        margin:0;
        padding:0;
    }
    #ybt-nav2 .ybt-nav2-drawer a{
        display:flex;
        align-items:center;
        gap:12px;
        padding:11px 10px;
        font-family:var(--mono);
        font-size:13px;
        font-weight:500;
        color:var(--ink);
        text-decoration:none;
        border-bottom:1px dashed var(--kraft-line);
    }
    #ybt-nav2 .ybt-nav2-drawer li:last-child a{ border-bottom:none; }
    #ybt-nav2 .ybt-nav2-drawer a.active{
        color:var(--emerald-deep);
        background:var(--emerald-tint);
        border-left:4px solid var(--emerald);
        font-weight:600;
    }
    #ybt-nav2 .ybt-nav2-drawer i{ width:18px; text-align:center; opacity:.75; }

    /* ── responsive ──────────────────────────────────────────── */
    @media (max-width:980px){
        #ybt-nav2 .ybt-nav2-links{ display:none; }
        #ybt-nav2 .ybt-nav2-burger{ display:flex; }
        #ybt-nav2 .ybt-nav2-userbtn .ybt-nav2-uname{ display:none; }
        #ybt-nav2 .ybt-nav2-userbtn{ padding:7px 9px; }
    }
    @media (prefers-reduced-motion:reduce){
        #ybt-nav2 .ybt-nav2-chev{ transition:none; }
    }
    </style>
    <nav id="ybt-nav2">
        <div class="ybt-nav2-bar">
            <div class="ybt-nav2-inner">
                <!-- Brand -->
                <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="ybt-nav2-brand">
                    <strong>YBT</strong>
                    <span>Packing service</span>
                </a>

                <!-- Desktop routing index -->
                <ul class="ybt-nav2-links">
                    <li><a class="ybt-nav2-link <?php echo ybt_is_active('dashboard'); ?>" href="<?php echo esc_url(home_url('/dashboard/')); ?>"><i class="fas fa-home"></i>Dashboard</a></li>
                    <li><a class="ybt-nav2-link <?php echo ybt_is_active('add-tracking'); ?>" href="<?php echo esc_url(home_url('/add-tracking/')); ?>"><i class="fas fa-plus"></i>Add item</a></li>
                    <li><a class="ybt-nav2-link <?php echo ybt_is_active('not-received'); ?>" href="<?php echo esc_url(home_url('/not-received/')); ?>"><i class="fas fa-clock"></i>Not received</a></li>
                    <li><a class="ybt-nav2-link <?php echo ybt_is_active('received'); ?>" href="<?php echo esc_url(home_url('/received/')); ?>"><i class="fas fa-warehouse"></i>Received</a></li>
                    <li><a class="ybt-nav2-link <?php echo ybt_is_active('packing-in-progress'); ?>" href="<?php echo esc_url(home_url('/packing-in-progress/')); ?>"><i class="fas fa-box-open"></i>Packing</a></li>
                    <li><a class="ybt-nav2-link <?php echo ybt_is_active('packed'); ?>" href="<?php echo esc_url(home_url('/packed/')); ?>"><i class="fas fa-box"></i>Packed</a></li>
                    <li><a class="ybt-nav2-link <?php echo ybt_is_active('completed'); ?>" href="<?php echo esc_url(home_url('/completed/')); ?>"><i class="fas fa-check-circle"></i>Sent</a></li>
                </ul>

                <!-- Notifications bell + inbox modal (AJAX: includes/notifications-web.php) -->
                <?php require_once YBT_PLUGIN_PATH . 'templates/components/notifications-bell.php'; ?>

                <!-- User -->
                <div class="ybt-nav2-user">
                    <button class="ybt-nav2-userbtn" id="ybtUserMenuTrigger" aria-expanded="false" aria-controls="ybtUserDropdown">
                        <span class="ybt-nav2-avatar"><i class="fas fa-user"></i></span>
                        <span class="ybt-nav2-uname"><?php echo esc_html($current_user->display_name); ?></span>
                        <i class="fas fa-chevron-down ybt-nav2-chev"></i>
                    </button>
                    <div class="ybt-nav2-drop" id="ybtUserDropdown">
                        <div class="ybt-nav2-drop-head">
                            <span class="ybt-nav2-avatar"><i class="fas fa-user"></i></span>
                            <div>
                                <div class="ybt-nav2-drop-name"><?php echo esc_html($current_user->display_name); ?></div>
                                <div class="ybt-nav2-drop-mail"><?php echo esc_html($current_user->user_email); ?></div>
                            </div>
                        </div>
                        <a href="<?php echo esc_url(home_url('/profile/')); ?>">
                            <i class="fas fa-id-card"></i>My profile
                        </a>
                        <a class="ybt-nav2-out" href="<?php echo esc_url(wp_logout_url(home_url('/login/'))); ?>">
                            <i class="fas fa-sign-out-alt"></i>Log out
                        </a>
                    </div>
                </div>

                <!-- Mobile toggle -->
                <button class="ybt-nav2-burger" id="ybtMobileToggle" aria-expanded="false" aria-controls="ybtMobileNav" aria-label="Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>

            <!-- Mobile drawer -->
            <div class="ybt-nav2-drawer" id="ybtMobileNav">
                <ul>
                    <li><a class="<?php echo ybt_is_active('dashboard'); ?>" href="<?php echo esc_url(home_url('/dashboard/')); ?>"><i class="fas fa-home"></i>Dashboard</a></li>
                    <li><a class="<?php echo ybt_is_active('add-tracking'); ?>" href="<?php echo esc_url(home_url('/add-tracking/')); ?>"><i class="fas fa-plus"></i>Add item</a></li>
                    <li><a class="<?php echo ybt_is_active('not-received'); ?>" href="<?php echo esc_url(home_url('/not-received/')); ?>"><i class="fas fa-clock"></i>Not received</a></li>
                    <li><a class="<?php echo ybt_is_active('received'); ?>" href="<?php echo esc_url(home_url('/received/')); ?>"><i class="fas fa-warehouse"></i>Received</a></li>
                    <li><a class="<?php echo ybt_is_active('packing-in-progress'); ?>" href="<?php echo esc_url(home_url('/packing-in-progress/')); ?>"><i class="fas fa-box-open"></i>Packing</a></li>
                    <li><a class="<?php echo ybt_is_active('packed'); ?>" href="<?php echo esc_url(home_url('/packed/')); ?>"><i class="fas fa-box"></i>Packed</a></li>
                    <li><a class="<?php echo ybt_is_active('completed'); ?>" href="<?php echo esc_url(home_url('/completed/')); ?>"><i class="fas fa-check-circle"></i>Sent</a></li>
                    <li><a href="<?php echo esc_url(home_url('/support/')); ?>"><i class="fas fa-headset"></i>Support</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <script>
    (function() {
        // Mobile Toggle
        const mobileToggle = document.getElementById('ybtMobileToggle');
        const mobileNav = document.getElementById('ybtMobileNav');
        
        if (mobileToggle && mobileNav) {
            mobileToggle.addEventListener('click', function() {
                var open = mobileNav.classList.toggle('show');
                this.classList.toggle('active', open);
                this.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        // User Dropdown
        const userTrigger = document.getElementById('ybtUserMenuTrigger');
        const userDropdown = document.getElementById('ybtUserDropdown');
        
        if (userTrigger && userDropdown) {
            userTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                var open = userDropdown.classList.toggle('show');
                this.classList.toggle('active', open);
                this.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!userTrigger.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                    userTrigger.classList.remove('active');
                    userTrigger.setAttribute('aria-expanded', 'false');
                }
            });

            // Close on escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    userDropdown.classList.remove('show');
                    userTrigger.classList.remove('active');
                    userTrigger.setAttribute('aria-expanded', 'false');
                    if (mobileToggle && mobileToggle.classList.contains('active')) {
                        mobileToggle.classList.remove('active');
                        mobileNav.classList.remove('show');
                    }
                }
            });
        }
    })();
    </script>
    <?php
}
