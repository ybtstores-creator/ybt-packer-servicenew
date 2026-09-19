<?php
/**
 * Printable Invoice Template 
 * Rendered by YBT_Invoice::ajax_generate_invoice_pdf()
 * 
 * Variables available: $invoice, $line_items, $carton_items, $tracking_items, $payment_settings
 */
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo esc_html($invoice->invoice_number); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', Arial, sans-serif; 
            color: #1e293b; 
            background: #f1f5f9;
            padding: 20px;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Header */
        .invoice-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #fff;
            padding: 40px 40px 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .invoice-header .brand h1 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .invoice-header .brand p {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            margin-top: 4px;
        }
        .invoice-header .invoice-meta {
            text-align: right;
        }
        .invoice-header .invoice-meta .invoice-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
        }
        .invoice-header .invoice-meta .invoice-number {
            font-size: 20px;
            font-weight: 700;
            color: #4ade80;
            margin-top: 2px;
        }
        .invoice-header .invoice-meta .invoice-date {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            margin-top: 6px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }
        .status-draft { background: #fef3c7; color: #92400e; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        /* Body */
        .invoice-body { padding: 36px 40px; }

        /* Bill To / Ship To */
        .parties-row {
            display: flex;
            gap: 40px;
            margin-bottom: 36px;
        }
        .party-card {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px;
        }
        .party-card .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .party-card .name {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }
        .party-card .detail {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
            line-height: 1.5;
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 32px;
        }
        .summary-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }
        .summary-card .num {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }
        .summary-card .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            font-weight: 600;
            margin-top: 4px;
        }

        /* Tables */
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title .icon {
            width: 28px;
            height: 28px;
            background: #e8f5e9;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }
        table thead th {
            background: #f1f5f9;
            padding: 10px 14px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            font-weight: 600;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        table tbody td {
            padding: 12px 14px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        table tbody tr:hover { background: #fafbfc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: 600; }

        /* Totals */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 32px;
        }
        .totals-table {
            width: 320px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
        }
        .totals-table .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            color: #64748b;
        }
        .totals-table .row.total {
            border-top: 2px solid #e2e8f0;
            margin-top: 8px;
            padding-top: 14px;
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }
        .totals-table .row.total .amount { color: #228B22; }

        /* Payment Info */
        .payment-info {
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 28px;
        }
        .payment-info h3 {
            font-size: 13px;
            font-weight: 700;
            color: #166534;
            margin-bottom: 12px;
        }
        .payment-info .detail {
            font-size: 12px;
            color: #15803d;
            margin: 4px 0;
        }

        /* Footer */
        .invoice-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 40px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        /* Print */
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-container { box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
            .invoice-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        /* Print Button */
        .print-bar {
            max-width: 800px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .print-bar button, .print-bar a {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            transition: all 0.2s;
        }
        .print-bar button:hover, .print-bar a:hover {
            background: #f1f5f9;
        }
        .print-bar .btn-print {
            background: #1e293b;
            color: #fff;
            border-color: #1e293b;
        }
        .print-bar .btn-print:hover {
            background: #334155;
        }
    </style>
</head>
<body>
    <div class="print-bar no-print">
        <a href="<?php echo esc_url(admin_url('admin.php?page=ybt-invoices')); ?>">← Back to Invoices</a>
        <button class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
    </div>

    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="brand">
                <h1>🧾 <?php echo esc_html(get_bloginfo('name')); ?></h1>
                <p>Professional Freight & Logistics</p>
            </div>
            <div class="invoice-meta">
                <div class="invoice-label">Invoice</div>
                <div class="invoice-number"><?php echo esc_html($invoice->invoice_number); ?></div>
                <div class="invoice-date"><?php echo date('F j, Y', strtotime($invoice->date_created)); ?></div>
                <div class="status-badge status-<?php echo esc_attr($invoice->status); ?>">
                    <?php echo esc_html(ucfirst($invoice->status)); ?>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="invoice-body">
            <!-- Parties -->
            <div class="parties-row">
                <div class="party-card">
                    <div class="label">Bill To</div>
                    <div class="name"><?php echo esc_html($invoice->username); ?></div>
                    <?php if ($invoice->user_id): ?>
                        <?php $user = get_user_by('ID', $invoice->user_id); ?>
                        <?php if ($user): ?>
                            <div class="detail"><?php echo esc_html($user->user_email); ?></div>
                            <?php $phone = get_user_meta($invoice->user_id, 'phone', true); ?>
                            <?php if ($phone): ?>
                                <div class="detail">📞 <?php echo esc_html($phone); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="party-card">
                    <div class="label">Batch Information</div>
                    <div class="name"><?php echo esc_html($invoice->batch_id); ?></div>
                    <div class="detail">Generated: <?php echo date('M j, Y g:i A', strtotime($invoice->date_created)); ?></div>
                    <?php if ($invoice->date_sent): ?>
                        <div class="detail">Sent: <?php echo date('M j, Y', strtotime($invoice->date_sent)); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($invoice->shipping_address)): ?>
            <div class="parties-row" style="margin-top:-20px;">
                <div class="party-card" style="flex:1;">
                    <div class="label">Shipping Address</div>
                    <div class="detail" style="font-size:14px;color:#1e293b;margin-top:0;"><?php echo nl2br(esc_html($invoice->shipping_address)); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Summary -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="num"><?php echo intval($invoice->total_items); ?></div>
                    <div class="label">Items</div>
                </div>
                <div class="summary-card">
                    <div class="num"><?php echo intval($invoice->total_cartons); ?></div>
                    <div class="label">Cartons</div>
                </div>
                <div class="summary-card">
                    <div class="num"><?php echo number_format($invoice->total_weight, 1); ?></div>
                    <div class="label">Weight (KG)</div>
                </div>
                <div class="summary-card">
                    <div class="num" style="color:#228B22;"><?php echo esc_html($invoice->currency); ?> <?php echo number_format($invoice->grand_total, 2); ?></div>
                    <div class="label">Total</div>
                </div>
            </div>

            <!-- Tracking Items -->
            <div class="section-title"><span class="icon">📦</span> Tracking Items</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tracking Number</th>
                        <th>Description</th>
                        <th class="text-center">Qty</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $n = 1; foreach ($tracking_items as $ti): ?>
                    <tr>
                        <td><?php echo $n++; ?></td>
                        <td class="fw-bold"><?php echo esc_html($ti['tracking_number']); ?></td>
                        <td><?php echo esc_html($ti['description'] ?: 'Package'); ?></td>
                        <td class="text-center"><?php echo intval($ti['item_count']); ?></td>
                        <td>
                            <?php echo ($ti['shipping_method'] === 'air') ? '✈️ Air' : '🚢 Sea'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Carton Breakdown -->
            <?php if (!empty($carton_items)): ?>
            <div class="section-title"><span class="icon">📋</span> Carton Breakdown</div>
            <table>
                <thead>
                    <tr>
                        <th>Carton #</th>
                        <th class="text-right">Weight (KG)</th>
                        <th class="text-right">Amount</th>
                        <th>Currency</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carton_items as $ci): ?>
                    <tr>
                        <td class="fw-bold">Carton <?php echo intval($ci['carton_number']); ?></td>
                        <td class="text-right"><?php echo number_format(floatval($ci['weight_kg']), 2); ?></td>
                        <td class="text-right fw-bold"><?php echo number_format(floatval($ci['amount']), 2); ?></td>
                        <td><?php echo esc_html($ci['currency'] ?: 'CNY'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Totals -->
            <div class="totals-section">
                <div class="totals-table">
                    <div class="row">
                        <span>Subtotal</span>
                        <span><?php echo esc_html($invoice->currency); ?> <?php echo number_format($invoice->subtotal, 2); ?></span>
                    </div>
                    <?php if ($invoice->deduction > 0): ?>
                    <div class="row">
                        <span>Deduction</span>
                        <span>-<?php echo esc_html($invoice->deduction_currency); ?> <?php echo number_format($invoice->deduction, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice->exchange_rate && $invoice->exchange_rate != 1): ?>
                    <div class="row">
                        <span>Exchange Rate</span>
                        <span>×<?php echo number_format($invoice->exchange_rate, 4); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="row total">
                        <span>Grand Total</span>
                        <span class="amount"><?php echo esc_html($invoice->currency); ?> <?php echo number_format($invoice->grand_total, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <?php if ($payment_settings): ?>
            <div class="payment-info">
                <h3>💳 Payment Information</h3>
                <div class="detail"><strong>Bank:</strong> <?php echo esc_html($payment_settings->bank_name); ?></div>
                <div class="detail"><strong>Account Name:</strong> <?php echo esc_html($payment_settings->account_name); ?></div>
                <div class="detail"><strong>Account Number:</strong> <?php echo esc_html($payment_settings->account_number); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($invoice->notes): ?>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px;">
                <p style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px;">Notes</p>
                <p style="font-size:13px;color:#334155;"><?php echo nl2br(esc_html($invoice->notes)); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            <p><strong><?php echo esc_html(get_bloginfo('name')); ?></strong> — Auto-Generated Invoice</p>
            <p style="margin-top:4px;">Thank you for your business!</p>
        </div>
    </div>
</body>
</html>
