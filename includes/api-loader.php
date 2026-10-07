<?php
/**
 * YBT API Loader
 *
 * Boots the new API structure.
 */

if (!defined('ABSPATH')) exit;

// Load API classes
require_once __DIR__ . '/api/class-ybt-api-response.php';
require_once __DIR__ . '/api/class-ybt-api-helper.php';
require_once __DIR__ . '/api/class-ybt-api-handler-base.php';
require_once __DIR__ . '/api/class-ybt-api-auth-handler.php';
require_once __DIR__ . '/api/class-ybt-api-tracking-handler.php';
require_once __DIR__ . '/api/class-ybt-api-packing-handler.php';
require_once __DIR__ . '/api/class-ybt-api-chat-handler.php';
require_once __DIR__ . '/api/class-ybt-api-admin-handler.php';
require_once __DIR__ . '/api/class-ybt-api-notifications-handler.php';
require_once __DIR__ . '/api/class-ybt-api-router.php';

// Initialize the API Router
add_action('plugins_loaded', function() {
    $ybt_api_router = new YBT_API_Router();
    $ybt_api_router->register_endpoints();
}, 20); // Priority 20 to ensure other components are loaded
