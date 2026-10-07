<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
if (!defined('ABSPATH')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'ybt-packer'), 403);
}

header('Content-Type: text/plain');
echo "PHPINIDir used: " . (getenv('PHPRC') ? getenv('PHPRC') : 'from registry/Apache config') . "\n";
echo "php.ini: " . php_ini_loaded_file() . "\n";
echo "GD section check:\n";
$ini = @file_get_contents('C:/xampp/php/php.ini');
if ($ini && preg_match('/^\s*extension\s*=\s*["]?gd["]?/m', $ini)) {
    echo "Found 'extension=gd' in php.ini: YES\n";
} else {
    echo "Found 'extension=gd' in php.ini: NO\n";
}
// Check the actual directive values
echo "extension_dir: " . ini_get('extension_dir') . "\n";
echo "gd extension dir attempt: " . ini_get('extension_dir') . "/php_gd.dll exists: " . (file_exists(ini_get('extension_dir') . '/php_gd.dll') ? 'YES' : 'NO') . "\n";
