<?php
/**
 * PDF Invoice Generator - Editorial Management System
 * Clean one-page design with EMS branding
 */

$paymentId = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$journalId = isset($_GET['journal_id']) ? (int)$_GET['journal_id'] : 0;

if (!$paymentId || !$journalId) {
    die('Invalid request.');
}

// Read OJS config
$configFile = dirname(__DIR__, 3) . '/config.inc.php';
if (!file_exists($configFile)) {
    die('Config not found.');
}

$config = parse_ini_file($configFile, true);
$dbDriverRaw = $config['database']['driver'] ?? 'pgsql';
$dbHost = $config['database']['host'] ?? 'localhost';
$dbName = $config['database']['name'] ?? '';
$dbUser = $config['database']['username'] ?? '';
$dbPass = $config['database']['password'] ?? '';

$driverMap = [
    'postgres9' => 'pgsql',
    'postgres' => 'pgsql',
    'mysql' => 'mysql'
];
$dbDriver = $driverMap[$dbDriverRaw] ?? $dbDriverRaw;

try {
    $pdo = new PDO("{$dbDriver}:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database error.');
}

// Get payment details
$stmt = $pdo->prepare("SELECT ph.*, j.path as journal_path 
    FROM emspubcore_payment_history ph 
    LEFT JOIN journals j ON j.journal_id = ph.journal_id 
    WHERE ph.payment_id = ? AND ph.journal_id = ?");
$stmt->execute([$paymentId, $journalId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die('Payment not found.');
}

// Get journal name
$stmt = $pdo->prepare("SELECT setting_value FROM journal_settings WHERE journal_id = ? AND setting_name = 'name' LIMIT 1");
$stmt->execute([$journalId]);
$journalName = $stmt->fetchColumn() ?: 'Journal';

// Get journal plan info
$stmt = $pdo->prepare("SELECT * FROM emspubcore_journal_plans WHERE journal_id = ? LIMIT 1");
$stmt->execute([$journalId]);
$journalPlan = $stmt->fetch(PDO::FETCH_ASSOC);

// Format data
$invoiceId = 'INV-' . str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT);
$paymentDate = date('F d, Y', strtotime($payment['payment_date']));
$amount = number_format($payment['amount'] / 100, 2);
$planName = ucfirst($payment['plan_type']);
$status = $payment['status'] === 'succeeded' ? 'PAID' : strtoupper($payment['status']);
$billingCycle = ucfirst($payment['billing_cycle'] ?? 'Yearly');
$planValidUntil = $journalPlan ? date('F d, Y', strtotime($journalPlan['plan_end_date'])) : 'N/A';
$submissionLimit = $journalPlan ? $journalPlan['submissions_limit'] : 'N/A';
$transactionId = $payment['stripe_payment_intent_id'] ?? 'N/A';

// Get logo as base64
$logoPath = __DIR__ . '/images/ems_brand_logo_full.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $invoiceId . '.html"');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $invoiceId; ?></title>
    <style>
        @page { size: A4; margin: 0; }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .invoice-wrapper { box-shadow: none !important; margin: 0 !important; border-radius: 0 !important; }
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .invoice-wrapper {
            max-width: 700px;
            margin: 20px auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 12px;
            overflow: hidden;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #057F5F 0%, #0ABF96 100%);
            color: #fff;
            padding: 30px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-area img {
            height: 100px;
            width: auto;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .logo-text .brand-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .logo-text .brand-tagline {
            font-size: 10px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        
        .header-right {
            text-align: right;
        }
        
        .invoice-title {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        
        .invoice-number {
            font-size: 14px;
            opacity: 0.9;
            margin: 5px 0 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            background: #22c55e;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .status-badge.pending { background: #f59e0b; }
        .status-badge.failed { background: #ef4444; }
        
        /* Content */
        .content {
            padding: 30px 35px;
        }
        
        /* Details Row */
        .details-row {
            display: flex;
            gap: 30px;
            margin-bottom: 25px;
        }
        
        .details-box {
            flex: 1;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #0ABF96;
        }
        
        .details-box h4 {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
        }
        
        .details-box .primary {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .details-box .detail-line {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 4px;
        }
        
        .details-box .detail-line .label { color: #64748b; }
        .details-box .detail-line .value { font-weight: 600; color: #1e293b; }
        
        .transaction-id {
            font-family: monospace;
            font-size: 11px;
            background: #e2e8f0;
            padding: 3px 8px;
            border-radius: 4px;
            word-break: break-all;
        }
        
        /* Table */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .invoice-table thead {
            background: #057F5F;
            color: #fff;
        }
        
        .invoice-table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .invoice-table th:last-child { text-align: right; }
        
        .invoice-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .invoice-table td:last-child { text-align: right; }
        
        .desc-main { font-weight: 600; font-size: 14px; }
        .desc-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
        
        .period-badge {
            display: inline-block;
            padding: 3px 10px;
            background: #d1fae5;
            color: #057F5F;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .amount { font-weight: 700; font-size: 15px; color: #1e293b; }
        
        /* Totals */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .totals-box {
            width: 250px;
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px 20px;
        }
        
        .totals-box .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }
        
        .totals-box .row.subtotal { border-bottom: 1px solid #e2e8f0; }
        
        .totals-box .row.total {
            font-size: 16px;
            font-weight: 700;
            color: #0ABF96;
            padding-top: 10px;
            margin-top: 5px;
            border-top: 2px solid #0ABF96;
        }
        
        /* Plan Active Box */
        .plan-box {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #0ABF96;
            border-radius: 8px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .plan-box .icon {
            width: 36px;
            height: 36px;
            background: #0ABF96;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
        }
        
        .plan-box .text h5 {
            font-size: 14px;
            color: #065f46;
            font-weight: 700;
        }
        
        .plan-box .text p {
            font-size: 12px;
            color: #047857;
        }
        
        /* Footer */
        .footer {
            background: #f8fafc;
            padding: 20px 35px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer .brand {
            font-size: 14px;
            font-weight: 700;
            color: #057F5F;
        }
        
        .footer .tagline {
            font-size: 11px;
            color: #64748b;
            margin: 3px 0 8px;
        }
        
        .footer .contact {
            font-size: 11px;
            color: #94a3b8;
        }
        
        .footer a { color: #0ABF96; text-decoration: none; }
        
        /* Print Button */
        .print-btn-wrapper {
            text-align: center;
            padding: 15px;
        }
        
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 25px;
            background: #0ABF96;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .print-btn:hover { background: #057F5F; }
    </style>
</head>
<body>
    <div class="invoice-wrapper">
        <!-- Header -->
        <div class="header">
            <div class="logo-area">
                <?php if ($logoBase64): ?>
                    <img src="<?php echo $logoBase64; ?>" alt="EMS">
                <?php endif; ?>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number"># <?php echo $invoiceId; ?></div>
                <span class="status-badge <?php echo strtolower($status); ?>"><?php echo $status; ?></span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Details -->
            <div class="details-row">
                <div class="details-box">
                    <h4>Billed To</h4>
                    <div class="primary"><?php echo htmlspecialchars($journalName); ?></div>
                    <div class="detail-line">
                        <span class="label">Journal ID</span>
                        <span class="value">#<?php echo $journalId; ?></span>
                    </div>
                    <div class="detail-line">
                        <span class="label">Plan Type</span>
                        <span class="value"><?php echo $planName; ?></span>
                    </div>
                </div>
                
                <div class="details-box">
                    <h4>Payment Information</h4>
                    <div class="detail-line">
                        <span class="label">Invoice Date</span>
                        <span class="value"><?php echo $paymentDate; ?></span>
                    </div>
                    <div class="detail-line">
                        <span class="label">Payment Method</span>
                        <span class="value">Stripe</span>
                    </div>
                    <div class="detail-line" style="margin-top: 8px;">
                        <span class="label">Transaction ID</span>
                    </div>
                    <div class="transaction-id"><?php echo htmlspecialchars($transactionId); ?></div>
                </div>
            </div>
            
            <!-- Table -->
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="width: 45%;">Description</th>
                        <th style="width: 20%;">Period</th>
                        <th style="width: 15%;">Qty</th>
                        <th style="width: 20%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="desc-main"><?php echo $planName; ?> Plan Subscription</div>
                            <div class="desc-sub">Up to <?php echo $submissionLimit; ?> submissions per year</div>
                        </td>
                        <td><span class="period-badge"><?php echo $billingCycle; ?></span></td>
                        <td>1</td>
                        <td class="amount">$<?php echo $amount; ?> <?php echo $payment['currency']; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Totals -->
            <div class="totals-section">
                <div class="totals-box">
                    <div class="row subtotal">
                        <span>Subtotal</span>
                        <span>$<?php echo $amount; ?></span>
                    </div>
                    <div class="row">
                        <span>Tax (0%)</span>
                        <span>$0.00</span>
                    </div>
                    <div class="row total">
                        <span>Total</span>
                        <span>$<?php echo $amount; ?> <?php echo $payment['currency']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Plan Active -->
            <div class="plan-box">
                <div class="icon">✓</div>
                <div class="text">
                    <h5>Your <?php echo $planName; ?> Plan is Active</h5>
                    <p>Valid until <?php echo $planValidUntil; ?> • <?php echo $submissionLimit; ?> submissions included</p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="brand">Editorial Management System</div>
            <div class="tagline">Empowering Academic Publishing Excellence</div>
            <div class="contact">
                Thank you for your subscription! For support, contact <a href="mailto:support@ems.pub">support@ems.pub</a> | <a href="https://www.ems.pub">www.ems.pub</a>
            </div>
        </div>
    </div>
    
    <!-- Print Button -->
    <div class="print-btn-wrapper no-print">
        <button class="print-btn" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print / Save as PDF
        </button>
    </div>
</body>
</html>
