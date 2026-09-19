<?php
if (!defined('ABSPATH')) exit;

function render_dashboard_charts($not_received, $received, $packed, $completed) {
?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="ybt-card glass-card" style="background: #ffffff; border: 1px solid #e2e8f0; overflow: hidden; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);">
            <div class="ybt-card-header" style="background: linear-gradient(135deg, rgba(30,90,142,0.1), rgba(34,139,34,0.1)); border: none; position: relative; z-index: 2;">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2" style="color: #1e5a8e;"></i>Parcel Status Distribution</h5>
            </div>
            <div class="card-body" style="position: relative; z-index: 2;">
                <canvas id="statusChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="ybt-card glass-card" style="background: #ffffff; border: 1px solid #e2e8f0; overflow: hidden; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);">
            <div class="ybt-card-header" style="background: linear-gradient(135deg, rgba(34,139,34,0.1), rgba(30,90,142,0.1)); border: none; position: relative; z-index: 2;">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2" style="color: #228B22;"></i>Shipping Method Breakdown</h5>
            </div>
            <div class="card-body" style="position: relative; z-index: 2;">
                <canvas id="shippingChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// DOM Safety: Check for ybt_admin object (shim)
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: <?php echo json_encode(wp_create_nonce("ybt_admin_nonce")); ?>,
        user_nonce: <?php echo json_encode(wp_create_nonce("ybt_nonce")); ?>,
        ajax_url: <?php echo json_encode(admin_url("admin-ajax.php")); ?>
    };
}

jQuery(document).ready(function($) {
    // Create Status Distribution Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx && typeof Chart !== 'undefined') {
        const statusData = [
            <?php echo (int)$not_received; ?>,
            <?php echo (int)$received; ?>,
            <?php echo (int)$packed; ?>,
            <?php echo (int)$completed; ?>
        ];

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Not Received', 'Received', 'Packed', 'Completed'],
                datasets: [{
                    data: statusData,
                    backgroundColor: [
                        'rgba(30, 90, 142, 0.9)',
                        'rgba(34, 139, 34, 0.9)',
                        'rgba(42, 122, 176, 0.9)',
                        'rgba(50, 168, 82, 0.9)'
                    ],
                    borderWidth: 4,
                    borderColor: '#fff',
                    hoverOffset: 20,
                    hoverBorderWidth: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: { size: 13, weight: '600' },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeOutQuart'
                }
            }
        });
    }

    // Get shipping method data
    $.ajax({
        url: ybt_admin.ajax_url,
        type: 'POST',
        data: {
            action: 'ybt_get_shipping_stats',
            nonce: ybt_admin.user_nonce || ybt_admin.nonce
        },
        success: function(response) {
            if (response.success && response.data) {
                const shippingCtx = document.getElementById('shippingChart');
                if (shippingCtx && typeof Chart !== 'undefined') {
                    // Context safety: Validate numbers
                    const airCount = parseInt(response.data.air) || 0;
                    const seaCount = parseInt(response.data.sea) || 0;

                    new Chart(shippingCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Air Shipment', 'Sea Shipment'],
                            datasets: [{
                                label: 'Number of Parcels',
                                data: [airCount, seaCount],
                                backgroundColor: [
                                    'rgba(30, 90, 142, 0.9)',
                                    'rgba(34, 139, 34, 0.9)'
                                ],
                                borderWidth: 0,
                                borderRadius: 12
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            },
                            animation: { duration: 2000, easing: 'easeOutBounce' }
                        }
                    });
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Chart Data Error:', error);
            const shippingCtx = document.getElementById('shippingChart');
            if (shippingCtx) {
                const ctx = shippingCtx.getContext('2d');
                ctx.font = '14px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Failed to load shipping data', shippingCtx.width/2, shippingCtx.height/2);
            }
        }
    });
});
</script>
<?php
}
