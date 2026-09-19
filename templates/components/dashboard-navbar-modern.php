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
 * Renders the modern responsive navigation bar with glassmorphism
 */
function render_dashboard_navbar($current_user) {
    ?><nav class="ybt-navbar-modern">
        <div class="ybt-navbar-container">
            <!-- Logo Section -->
            <div class="ybt-navbar-brand">
                <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="ybt-brand-link">
                    <img src="<?php echo ybt_get_logo_url(); ?>"
                         alt="YBT"
                         loading="eager"
                         fetchpriority="high"
                         decoding="async"
                         class="ybt-brand-logo" />
                    <span class="ybt-brand-text">
                        <span class="ybt-brand-title">YBT PACKING</span>
                        <span class="ybt-brand-subtitle">SERVICE</span>
                    </span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="ybt-navbar-desktop">
                <ul class="ybt-nav-list">
                    <li class="ybt-nav-item">
                        <a class="ybt-nav-link <?php echo ybt_is_active('dashboard'); ?>" href="<?php echo esc_url(home_url('/dashboard/')); ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="ybt-nav-item">
                        <a class="ybt-nav-link <?php echo ybt_is_active('add-tracking'); ?>" href="<?php echo esc_url(home_url('/add-tracking/')); ?>">
                            <i class="fas fa-plus"></i>
                            <span>Add Item</span>
                        </a>
                    </li>
                    <li class="ybt-nav-item">
                        <a class="ybt-nav-link <?php echo ybt_is_active('not-received'); ?>" href="<?php echo esc_url(home_url('/not-received/')); ?>">
                            <i class="fas fa-clock"></i>
                            <span>Not Received</span>
                        </a>
                    </li>
                    <li class="ybt-nav-item">
                        <a class="ybt-nav-link <?php echo ybt_is_active('received'); ?>" href="<?php echo esc_url(home_url('/received/')); ?>">
                            <i class="fas fa-warehouse"></i>
                            <span>Received</span>
                        </a>
                    </li>
                    <li class="ybt-nav-item">
                        <a class="ybt-nav-link <?php echo ybt_is_active('packing-in-progress'); ?>" href="<?php echo esc_url(home_url('/packing-in-progress/')); ?>">
                            <i class="fas fa-box-open"></i>
                            <span>Packing</span>
                        </a>
                    </li>
                    <li class="ybt-nav-item">
                        <a class="ybt-nav-link <?php echo ybt_is_active('packed'); ?>" href="<?php echo esc_url(home_url('/packed/')); ?>">
                            <i class="fas fa-box"></i>
                            <span>Packed</span>
                        </a>
                    </li>
                    <li class="ybt-nav-item">
                        <a class="ybt-nav-link <?php echo ybt_is_active('completed'); ?>" href="<?php echo esc_url(home_url('/completed/')); ?>">
                            <i class="fas fa-check-circle"></i>
                            <span>Sent</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User Menu -->
            <div class="ybt-navbar-actions">
                <div class="ybt-user-menu">
                    <button class="ybt-user-trigger" id="ybtUserMenuTrigger">
                        <div class="ybt-user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="ybt-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                        <i class="fas fa-chevron-down ybt-chevron"></i>
                    </button>
                    <div class="ybt-user-dropdown" id="ybtUserDropdown">
                        <div class="ybt-dropdown-header">
                            <div class="ybt-dropdown-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="ybt-dropdown-info">
                                <div class="ybt-dropdown-name"><?php echo esc_html($current_user->display_name); ?></div>
                                <div class="ybt-dropdown-email"><?php echo esc_html($current_user->user_email); ?></div>
                            </div>
                        </div>
                        <div class="ybt-dropdown-divider"></div>
                        <a class="ybt-dropdown-item" href="<?php echo esc_url(home_url('/profile/')); ?>">
                            <i class="fas fa-id-card"></i>
                            <span>My Profile</span>
                        </a>
                        <div class="ybt-dropdown-divider"></div>
                        <a class="ybt-dropdown-item ybt-dropdown-logout" href="<?php echo esc_url(wp_logout_url(home_url('/login/'))); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile Toggle -->
            <button class="ybt-mobile-toggle" id="ybtMobileToggle">
                <span class="ybt-toggle-icon"></span>
                <span class="ybt-toggle-icon"></span>
                <span class="ybt-toggle-icon"></span>
            </button>
        </div>

        <!-- Mobile Navigation -->
        <div class="ybt-navbar-mobile" id="ybtMobileNav">
            <ul class="ybt-mobile-list">
                <li class="ybt-mobile-item">
                    <a class="ybt-mobile-link <?php echo ybt_is_active('dashboard'); ?>" href="<?php echo esc_url(home_url('/dashboard/')); ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="ybt-mobile-item">
                    <a class="ybt-mobile-link <?php echo ybt_is_active('add-tracking'); ?>" href="<?php echo esc_url(home_url('/add-tracking/')); ?>">
                        <i class="fas fa-plus"></i>
                        <span>Add Item</span>
                    </a>
                </li>
                <li class="ybt-mobile-item">
                    <a class="ybt-mobile-link <?php echo ybt_is_active('not-received'); ?>" href="<?php echo esc_url(home_url('/not-received/')); ?>">
                        <i class="fas fa-clock"></i>
                        <span>Not Received</span>
                    </a>
                </li>
                <li class="ybt-mobile-item">
                    <a class="ybt-mobile-link <?php echo ybt_is_active('received'); ?>" href="<?php echo esc_url(home_url('/received/')); ?>">
                        <i class="fas fa-warehouse"></i>
                        <span>Received</span>
                    </a>
                </li>
                <li class="ybt-mobile-item">
                    <a class="ybt-mobile-link <?php echo ybt_is_active('packing-in-progress'); ?>" href="<?php echo esc_url(home_url('/packing-in-progress/')); ?>">
                        <i class="fas fa-box-open"></i>
                        <span>Packing</span>
                    </a>
                </li>
                <li class="ybt-mobile-item">
                    <a class="ybt-mobile-link <?php echo ybt_is_active('packed'); ?>" href="<?php echo esc_url(home_url('/packed/')); ?>">
                        <i class="fas fa-box"></i>
                        <span>Packed</span>
                    </a>
                </li>
                <li class="ybt-mobile-item">
                    <a class="ybt-mobile-link <?php echo ybt_is_active('completed'); ?>" href="<?php echo esc_url(home_url('/completed/')); ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Sent</span>
                    </a>
                </li>
                <li class="ybt-mobile-item">
                    <a class="ybt-mobile-link <?php echo ybt_is_active('support'); ?>" href="<?php echo esc_url(home_url('/support/')); ?>">
                        <i class="fas fa-headset"></i>
                        <span>Support</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <script>
    jQuery(document).ready(function($) {
        // Mobile Toggle
        const mobileToggle = document.getElementById('ybtMobileToggle');
        const mobileNav = document.getElementById('ybtMobileNav');
        
        if (mobileToggle && mobileNav) {
            mobileToggle.addEventListener('click', function() {
                this.classList.toggle('active');
                mobileNav.classList.toggle('show');
            });
        }

        // User Dropdown
        const userTrigger = document.getElementById('ybtUserMenuTrigger');
        const userDropdown = document.getElementById('ybtUserDropdown');
        
        if (userTrigger && userDropdown) {
            userTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
                this.classList.toggle('active');
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!userTrigger.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                    userTrigger.classList.remove('active');
                }
            });

            // Close on escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    userDropdown.classList.remove('show');
                    userTrigger.classList.remove('active');
                    if (mobileToggle && mobileToggle.classList.contains('active')) {
                        mobileToggle.classList.remove('active');
                        mobileNav.classList.remove('show');
                    }
                }
            });
        }
    });
    </script>
    <?php
}
