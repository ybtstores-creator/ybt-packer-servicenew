<?php
/**
 * YBT Performance Profiler Class
 * Monitors execution time and memory usage for key operations
 */
if (!defined('ABSPATH')) {
    exit;
}

class YBT_Profiler {
    private static $instance = null;
    private $checkpoints = array();
    private $enabled = false;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Enable profiling if debug is on or explicitly requested
        $this->enabled = (defined('YBT_DEBUG') && YBT_DEBUG) || (isset($_GET['ybt_profile']) && current_user_can('manage_options'));

        if ($this->enabled) {
            add_action('shutdown', array($this, 'log_report'), 999);
        }
    }

    /**
     * Start a checkpoint
     */
    public function start($name) {
        if (!$this->enabled) return;

        $this->checkpoints[$name] = array(
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        );
    }

    /**
     * End a checkpoint and calculate metrics
     */
    public function stop($name) {
        if (!$this->enabled || !isset($this->checkpoints[$name])) return;

        $checkpoint = &$this->checkpoints[$name];
        $checkpoint['end_time'] = microtime(true);
        $checkpoint['end_memory'] = memory_get_usage(true);

        $checkpoint['duration'] = $checkpoint['end_time'] - $checkpoint['start_time'];
        $checkpoint['memory_diff'] = $checkpoint['end_memory'] - $checkpoint['start_memory'];
    }

    /**
     * Log a summary report to error log
     */
    public function log_report() {
        if (!$this->enabled || empty($this->checkpoints)) return;

        $report = "\n--- YBT Performance Report ---\n";
        $report .= "Request: " . ($_SERVER['REQUEST_URI'] ?? 'CLI') . "\n";

        foreach ($this->checkpoints as $name => $data) {
            if (!isset($data['duration'])) {
                $this->stop($name);
            }

            $duration = round($data['duration'] * 1000, 2); // ms
            $memory = round($data['memory_diff'] / 1024, 2); // KB

            $report .= sprintf("[%s] Duration: %s ms | Memory: %s KB\n", $name, $duration, $memory);
        }

        $report .= "Total Execution Time: " . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . " ms\n";
        $report .= "Peak Memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
        $report .= "------------------------------\n";

        error_log($report);
    }
}

/**
 * Global helper for profiling
 */
if (!function_exists('ybt_profile_start')) {
    function ybt_profile_start($name) {
        YBT_Profiler::getInstance()->start($name);
    }
}

if (!function_exists('ybt_profile_stop')) {
    function ybt_profile_stop($name) {
        YBT_Profiler::getInstance()->stop($name);
    }
}

// Initialize profiler
YBT_Profiler::getInstance();
