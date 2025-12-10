<?php
/**
 * PDF Invoice Generator
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

// Format data
$invoiceId = 'INV-' . str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT);
$paymentDate = date('F d, Y', strtotime($payment['payment_date']));
$amount = number_format($payment['amount'] / 100, 2);
$planName = ucfirst($payment['plan_type']);
$status = $payment['status'] === 'succeeded' ? 'PAID' : strtoupper($payment['status']);

// Generate PDF-like HTML (simple invoice)
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $invoiceId . '.html"');

// For a proper PDF, you would use a library like TCPDF or DOMPDF
// This creates a printable HTML invoice that can be saved as PDF via browser
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $invoiceId; ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            color: #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #006798;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #006798;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            margin: 0;
            font-size: 32px;
            color: #333;
        }
        .invoice-number {
            color: #666;
            font-size: 14px;
        }
        .details-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .details-box h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .details-box p {
            margin: 5px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background: #f8f9fa;
            text-align: left;
            padding: 12px;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 15px 12px;
            border-bottom: 1px solid #eee;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
        .total-row td {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 18px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            color: #999;
            font-size: 12px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }
        .print-btn {
            display: block;
            margin: 20px auto;
            padding: 10px 30px;
            background: #006798;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print / Save as PDF</button>
    
    <div class="header">
        <div class="logo">EmsPubCore</div>
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <div class="invoice-number"><?php echo $invoiceId; ?></div>
        </div>
    </div>
    
    <div class="details-section">
        <div class="details-box">
            <h3>Billed To</h3>
            <p><strong><?php echo htmlspecialchars($journalName); ?></strong></p>
            <p>Journal ID: <?php echo $journalId; ?></p>
        </div>
        <div class="details-box" style="text-align: right;">
            <h3>Invoice Details</h3>
            <p><strong>Date:</strong> <?php echo $paymentDate; ?></p>
            <p><strong>Status:</strong> <span class="status-badge"><?php echo $status; ?></span></p>
            <p><strong>Payment ID:</strong> <?php echo htmlspecialchars($payment['stripe_payment_intent_id'] ?? 'N/A'); ?></p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Billing Cycle</th>
                <th class="amount">Amount (<?php echo $payment['currency']; ?>)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo $planName; ?> Plan Subscription</td>
                <td><?php echo ucfirst($payment['billing_cycle'] ?: 'Yearly'); ?></td>
                <td class="amount">$<?php echo $amount; ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="amount">$<?php echo $amount; ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Thank you for your subscription!</p>
        <p>This invoice was automatically generated by EmsPubCore.</p>
        <p>For questions, please contact support.</p>
    </div>
</body>
</html>
