<?php
/**
 * User Profile Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get user data
$user_id = get_current_user_id();
$user = get_userdata($user_id);

if (!$user || !is_admin()) {
    return;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('User Profile', 'ybt-packer-service'); ?></h1>

    <div class="profile-info">
        <p><strong>Username:</strong> <?php echo esc_html($user->user_login); ?></p>
        <p><strong>Email:</strong> <?php echo esc_html($user->user_email); ?></p>
        <p><strong>Display Name:</strong> <?php echo esc_html($user->display_name); ?></p>
    </div>
</div>
