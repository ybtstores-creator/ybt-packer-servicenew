<?php
if (!defined('ABSPATH')) exit;

function render_dashboard_stats($not_received, $received, $packed, $completed) {
    // Data Validation: Ensure all stats are integers
    $not_received = (int)$not_received;
    $received     = (int)$received;
    $packed       = (int)$packed;
    $completed    = (int)$completed;

    // Helper formatting function for counters
    $format_num = function($n) { return number_format($n); };
?>
<div class="row">
    <div class="col-md-3">
        <div class="ybt-stats-card glass-card" data-tilt>
            <div style="display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2;">
                <div>
                    <div class="ybt-stats-number" data-count="<?php echo esc_attr($not_received); ?>">
                        <?php echo esc_html($format_num($not_received)); ?>
                    </div>
                    <div style="font-weight: 500; letter-spacing: 0.5px;"><?php echo esc_html(__('Not Received', 'ybt-packer')); ?></div>
                </div>
                <div class="icon-3d">
                    <i class="fas fa-clock" style="font-size: 32px;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ybt-stats-card glass-card" data-tilt style="background: linear-gradient(135deg, #228B22 0%, #1a6b1a 100%);">
            <div style="display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2;">
                <div>
                    <div class="ybt-stats-number" data-count="<?php echo esc_attr($received); ?>">
                        <?php echo esc_html($format_num($received)); ?>
                    </div>
                    <div style="font-weight: 500; letter-spacing: 0.5px;"><?php echo esc_html(__('Received', 'ybt-packer')); ?></div>
                </div>
                <div class="icon-3d">
                    <i class="fas fa-check" style="font-size: 32px;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ybt-stats-card glass-card" data-tilt style="background: linear-gradient(135deg, #2a7ab0 0%, #1e5a8e 100%);">
            <div style="display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2;">
                <div>
                    <div class="ybt-stats-number" data-count="<?php echo esc_attr($packed); ?>">
                        <?php echo esc_html($format_num($packed)); ?>
                    </div>
                    <div style="font-weight: 500; letter-spacing: 0.5px;"><?php echo esc_html(__('Packed', 'ybt-packer')); ?></div>
                </div>
                <div class="icon-3d">
                    <i class="fas fa-box" style="font-size: 32px;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ybt-stats-card glass-card" data-tilt style="background: linear-gradient(135deg, #32a852 0%, #228B22 100%);">
            <div style="display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2;">
                <div>
                    <div class="ybt-stats-number" data-count="<?php echo esc_attr($completed); ?>">
                        <?php echo esc_html($format_num($completed)); ?>
                    </div>
                    <div style="font-weight: 500; letter-spacing: 0.5px;"><?php echo esc_html(__('Completed', 'ybt-packer')); ?></div>
                </div>
                <div class="icon-3d">
                    <i class="fas fa-check-circle" style="font-size: 32px;"></i>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
}
