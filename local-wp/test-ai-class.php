<?php
define('ABSPATH', dirname(__FILE__) . '/var/www/html/');
require_once('/var/www/html/wp-load.php');

echo "Class exists: " . (class_exists('YBT_AI_Operator') ? 'YES' : 'NO') . PHP_EOL;
if (class_exists('YBT_AI_Operator')) {
    $ai = YBT_AI_Operator::instance();
    echo "Instance: OK" . PHP_EOL;
}
