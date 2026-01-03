<?php
/**
 * Professional PDF Invoice Generator - Editorial Management System
 * Uses Dompdf for direct PDF delivery
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

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
    'mysql' => 'mysql',
    'mysqli' => 'mysql'
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
$totalAmountCents = (int)$payment['amount'];
$taxAmountCents = (int)($payment['tax_amount'] ?? 0);
$subtotalCents = $totalAmountCents - $taxAmountCents;

$amount = number_format($totalAmountCents / 100, 2);
$taxAmount = number_format($taxAmountCents / 100, 2);
$subtotal = number_format($subtotalCents / 100, 2);

// Calculate tax percentage for display
$taxPercent = $subtotalCents > 0 ? round(($taxAmountCents / $subtotalCents) * 100) : 0;

$planName = ucfirst($payment['plan_type']);
$status = $payment['status'] === 'succeeded' ? 'PAID' : strtoupper($payment['status']);
$billingCycle = ucfirst($payment['billing_cycle'] ?? 'Yearly');
$planValidUntil = $journalPlan ? date('F d, Y', strtotime($journalPlan['plan_end_date'])) : 'N/A';
$submissionLimit = $journalPlan ? $journalPlan['submissions_limit'] : 'N/A';
$transactionId = $payment['stripe_payment_intent_id'] ?? $payment['paddle_transaction_id'] ?? 'N/A';
$paymentMethod = 'Stripe';
if (strpos($transactionId, 'txn_') === 0 || strpos($transactionId, 'trn_') === 0) {
    $paymentMethod = 'Paddle';
}

// Get logo as base64 - Filtered to white for professional look on green background
$logoPath = __DIR__ . '/images/ems_brand_logo_full.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    try {
        $img = imagecreatefrompng($logoPath);
        if ($img) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            // Apply brightness filter to make it white (255)
            imagefilter($img, IMG_FILTER_BRIGHTNESS, 255);
            
            ob_start();
            imagepng($img);
            $imgData = ob_get_clean();
            $logoBase64 = 'data:image/png;base64,' . base64_encode($imgData);
            imagedestroy($img);
        }
    } catch (Exception $e) {
        // Fallback to original if GD fails
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.4;
        }
        .header {
            background: #057F5F;
            color: #fff;
            padding: 30px 40px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-img {
            height: 90px;
        }
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            text-align: right;
            letter-spacing: 2px;
        }
        .invoice-info {
            text-align: right;
            font-size: 14px;
            margin-top: 5px;
        }
        .status-paid {
            background: #22c55e;
            color: #fff;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
            margin-top: 10px;
        }
        .content {
            padding: 40px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 15px;
        }
        .details-table td:first-child { padding-left: 0; }
        .details-table td:last-child { padding-right: 0; }

        .details-box {
            background: #d8f3dc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #0ABF96;
        }
        .details-box h4 {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }
        .details-box .primary {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .detail-line {
            font-size: 12px;
            margin-bottom: 5px;
            color: #475569;
        }
        .detail-line b { color: #1e293b; }
        
        .transaction-id {
            font-family: monospace;
            font-size: 10px;
            background: #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 8px;
            display: block;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .invoice-table th {
            background: #057F5F;
            color: #fff;
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        .invoice-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .desc-main { font-weight: bold; font-size: 14px; }
        .desc-sub { color: #64748b; font-size: 12px; }
        
        .totals-table {
            float: right;
            width: 250px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 10px;
            font-size: 14px;
        }
        .row-total {
            border-top: 2px solid #0ABF96;
            color: #0ABF96;
            font-weight: bold;
            font-size: 18px !important;
        }

        
        .plan-box {
            clear: both;
            margin-top: 25px;
            background: #ecfdf5;
            border: 1px solid #0ABF96;
            border-radius: 8px;
            padding: 15px 20px;
        }
        .plan-box h5 {
            color: #065f46;
            font-size: 16px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .plan-box p {
            color: #047857;
            font-size: 12px;
        }

        .footer {
            margin-top: 10px;
            width: 100%;
            background: #f8fafc;
            padding: 25px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer .brand { font-weight: bold; color: #057F5F; }
        .footer .contact { font-size: 11px; color: #64748b; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <?php if ($logoBase64): ?>
                        <img src="<?php echo $logoBase64; ?>" class="logo-img">
                    <?php endif; ?>
                </td>
                <td>
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-info"># <?php echo $invoiceId; ?></div>
                    <div style="text-align: right;">
                        <span class="status-paid"><?php echo $status; ?></span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table class="details-table">
            <tr>
                <td>
                    <div class="details-box">
                        <h4>Billed To</h4>
                        <div class="primary"><?php echo htmlspecialchars($journalName); ?></div>
                        <div class="detail-line">Journal ID: <b>#<?php echo $journalId; ?></b></div>
                        <div class="detail-line">Plan Type: <b><?php echo $planName; ?></b></div>
                    </div>
                </td>
                <td>
                    <div class="details-box">
                        <h4>Payment Information</h4>
                        <div class="detail-line">Invoice Date: <b><?php echo $paymentDate; ?></b></div>
                        <div class="detail-line">Payment Method: <b><?php echo $paymentMethod; ?></b></div>
                        <div class="detail-line" style="margin-top: 10px;">Transaction ID:</div>
                        <div class="transaction-id"><?php echo htmlspecialchars($transactionId); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th style="width: 20%;">Period</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 20%; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="desc-main"><?php echo $planName; ?> Plan</div>
                        <div class="desc-sub">Up to <?php echo $submissionLimit; ?> submissions/year</div>
                    </td>
                    <td><?php echo $billingCycle; ?></td>
                    <td>1</td>
                    <td style="text-align: right; font-weight: bold;">$<?php echo $amount; ?></td>
                </tr>
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td style="color: #64748b;">Subtotal</td>
                <td style="text-align: right;">$<?php echo $subtotal; ?></td>
            </tr>
            <tr>
                <td style="color: #64748b;">Tax (<?php echo $taxPercent; ?>%)</td>
                <td style="text-align: right;">$<?php echo $taxAmount; ?></td>
            </tr>
            <tr>
                <td class="row-total">Total</td>
                <td class="row-total" style="text-align: right;">$<?php echo $amount; ?> <?php echo $payment['currency']; ?></td>
            </tr>
        </table>

        <div class="plan-box">
            <h5>Your <?php echo ($planName === 'Juniorscholar' ? 'Junior Scholar' : $planName); ?> Plan is Active</h5>
            <p style="margin-top: 5px;">Valid until <?php echo $planValidUntil; ?> • <?php echo $submissionLimit; ?> submissions included</p>
        </div>
    </div>

    <div class="footer">
        <div class="brand">Editorial Management System (ems.pub)</div>
        <div class="contact">
            For support, contact support@ems.pub | www.ems.pub<br>
            Thank you for your business!
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Dompdf Setup
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Stream the PDF
$dompdf->stream($invoiceId . ".pdf", array("Attachment" => 1));
