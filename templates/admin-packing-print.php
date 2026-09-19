<?php
/**
 * Printable Packing Request Template 
 * Rendered by YBT_Packing_Export::handle_download()
 * 
 * Variables available: $username, $method, $items, $user, $user_email, $user_phone, $user_mark
 */
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Request - <?php echo esc_html($username); ?> (<?php echo esc_html(strtoupper($method)); ?>)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            color: #1e293b; 
            background: #fff;
            padding: 40px;
            margin: 0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .header-title h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
            color: #0f172a;
        }
        .header-title p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }
        .header-meta {
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
            background: #f1f5f9;
            color: #475569;
        }
        .badge.air { background: #e0f2fe; color: #0284c7; }
        .badge.sea { background: #dcfce7; color: #16a34a; }
        
        .user-info {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .info-col {
            flex: 1;
        }
        .info-label {
            font-size: 12px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 15px;
            font-weight: 500;
            color: #0f172a;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
        }
        td {
            font-size: 14px;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .date-cell { font-size: 12px; color: #64748b; }
        
        .summary {
            display: flex;
            justify-content: flex-end;
        }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px 25px;
            border-radius: 8px;
            min-width: 250px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .summary-row.total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #e2e8f0;
            font-weight: 700;
            font-size: 18px;
            color: #0f172a;
            margin-bottom: 0;
        }

        .print-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .user-info { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr:nth-child(even) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="print-bar no-print">
        <a href="javascript:history.back()" class="btn">← Back</a>
        <button class="btn btn-primary" onclick="window.print()">🖨️ Print Request</button>
    </div>

    <div class="header">
        <div class="header-title">
            <h1>Packing Request</h1>
            <p><?php echo esc_html(get_bloginfo('name')); ?></p>
        </div>
        <div class="header-meta">
            <div class="badge <?php echo esc_attr($method); ?>"><?php echo esc_html(strtoupper($method)); ?> FREIGHT</div>
            <p style="margin-top: 10px; color: #64748b; font-size: 14px;">Date: <?php echo date('Y-m-d'); ?></p>
        </div>
    </div>

    <div class="user-info">
        <div class="info-col">
            <div class="info-label">Customer Name</div>
            <div class="info-value"><?php echo esc_html($username); ?></div>
        </div>
        <div class="info-col">
            <div class="info-label">Email / Phone</div>
            <div class="info-value"><?php echo esc_html($user_email); ?> / <?php echo esc_html($user_phone); ?></div>
        </div>
        <div class="info-col">
            <div class="info-label">Mark</div>
            <div class="info-value"><?php echo esc_html($user_mark); ?></div>
        </div>
        <?php if (!empty($user_address)): ?>
        <div class="info-col" style="flex: 2;">
            <div class="info-label">Shipping Address</div>
            <div class="info-value" style="font-size: 13px;"><?php echo nl2br(esc_html($user_address)); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="25%">Tracking Number</th>
                <th width="30%">Description</th>
                <th width="10%" class="text-center">Items</th>
                <th width="15%">Date Requested</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            $total_pkg = 0;
            foreach ($items as $item):
                $count = intval($item->item_count ?: 1);
                $total_pkg += $count;
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td style="font-weight: 500; font-family: monospace; font-size: 15px;"><?php echo esc_html($item->tracking_number); ?></td>
                <td><?php echo esc_html($item->description); ?></td>
                <td class="text-center"><?php echo $count; ?></td>
                <td class="date-cell"><?php echo !empty($item->date_packing_requested) ? esc_html(date('Y-m-d H:i', strtotime($item->date_packing_requested))) : '—'; ?></td>
                <td>
                    <?php if ($item->status === 'packing_in_progress'): ?>
                        <span style="color: #ea580c; font-weight: 500;">Packing...</span>
                    <?php else: ?>
                        <span style="color: #16a34a; font-weight: 500;">Received</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-row">
                <span>Total Tracking Rows:</span>
                <span><?php echo count($items); ?></span>
            </div>
            <div class="summary-row total">
                <span>Total Items (Count):</span>
                <span><?php echo $total_pkg; ?></span>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 50px; padding-top: 30px; border-top: 1px dashed #cbd5e1; display: flex; justify-content: space-between;">
        <div style="width: 45%; text-align: center;">
            <div style="border-bottom: 1px solid #94a3b8; height: 30px; margin-bottom: 10px;"></div>
            <span style="font-size: 12px; color: #64748b; text-transform: uppercase;">Packer Signature</span>
        </div>
        <div style="width: 45%; text-align: center;">
            <div style="border-bottom: 1px solid #94a3b8; height: 30px; margin-bottom: 10px;"></div>
            <span style="font-size: 12px; color: #64748b; text-transform: uppercase;">Date Packed</span>
        </div>
    </div>
</body>
</html>
