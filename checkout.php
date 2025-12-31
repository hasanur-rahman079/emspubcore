<?php
/**
 * Standalone Stripe Checkout Handler
 * Minimal dependencies - reads config directly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get request parameters
$journalId = isset($_GET['journalId']) ? (int)$_GET['journalId'] : 0;
$plan = isset($_GET['plan']) ? strtolower(trim($_GET['plan'])) : '';
$billing = isset($_GET['billing']) ? $_GET['billing'] : 'yearly';
$isRenewal = isset($_GET['renew']) && $_GET['renew'] == '1';

// Basic validation - journalId required
if (!$journalId || !$plan) {
    die('Invalid request parameters: Missing journal ID or plan.');
}

if ($plan === 'free') {
    die('Free plan does not require payment.');
}

// Read OJS config directly
$configFile = dirname(__DIR__, 3) . '/config.inc.php';
if (!file_exists($configFile)) {
    die('OJS config not found.');
}

$config = parse_ini_file($configFile, true);
$dbDriverRaw = $config['database']['driver'] ?? 'pgsql';
$dbHost = $config['database']['host'] ?? 'localhost';
$dbName = $config['database']['name'] ?? '';
$dbUser = $config['database']['username'] ?? '';
$dbPass = $config['database']['password'] ?? '';

// Map OJS driver names to PDO driver names
$driverMap = [
    'postgres9' => 'pgsql',
    'postgres' => 'pgsql',
    'postgresql' => 'pgsql',
    'mysql' => 'mysql',
    'mysqli' => 'mysql'
];
$dbDriver = $driverMap[$dbDriverRaw] ?? $dbDriverRaw;

// Connect to database
try {
    $dsn = "{$dbDriver}:host={$dbHost};dbname={$dbName}";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get Stripe secret key from plugin_settings
$stmt = $pdo->prepare("SELECT setting_value FROM plugin_settings WHERE plugin_name = 'emspubcoreplugin' AND setting_name = 'stripeSecretKey' AND COALESCE(context_id, 0) = 0");
$stmt->execute();
$secretKey = $stmt->fetchColumn();

if (!$secretKey) {
    die('Stripe is not configured. Please add your Stripe keys in Site Settings > Payment Gateways.');
}

// Get plan data from emspubcore_plans table - validate plan exists and get pricing
// Use case-insensitive matching with normalized plan key (lowercase, no spaces)
$stmt = $pdo->prepare("SELECT name, price, discounted_price FROM emspubcore_plans WHERE LOWER(REPLACE(name, ' ', '')) = ?");
$stmt->execute([strtolower(str_replace(' ', '', $plan))]);
$planData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$planData) {
    // Plan not found in database
    die('Invalid plan: "' . htmlspecialchars($plan) . '" not found. Please ensure the plan exists in Site Settings > Submission Plans.');
}

// Get journal discount from plugin_settings
$stmt = $pdo->prepare("SELECT setting_value FROM plugin_settings WHERE plugin_name = 'emspubcoreplugin' AND setting_name = 'journalDiscount' AND context_id = ?");
$stmt->execute([$journalId]);
$journalDiscount = (int)$stmt->fetchColumn();

// Calculate amount
$price = $planData['discounted_price'] ?: $planData['price'];

// Apply Journal Discount if set
if ($journalDiscount > 0) {
    $price = $price * (1 - $journalDiscount / 100);
}

$amount = (int)($price * 100); // Convert to cents

if ($amount <= 0) {
    die('This plan has no price configured or is free.');
}

// Load Stripe library
$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    die('Stripe library not installed. Run: composer require stripe/stripe-php');
}
require_once $vendorPath;

// Initialize Stripe
\Stripe\Stripe::setApiKey($secretKey);

// Build return URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$pluginPath = dirname($_SERVER['SCRIPT_NAME']);

$renewParam = $isRenewal ? '&renew=1' : '';
$successUrl = $protocol . '://' . $host . $pluginPath . '/checkout_success.php?session_id={CHECKOUT_SESSION_ID}&journalId=' . $journalId . $renewParam;
$cancelUrl = $protocol . '://' . $host . $pluginPath . '/checkout_cancel.php?journalId=' . $journalId;

// Set product description based on renewal or upgrade
$productName = ucfirst($plan) . ' Plan (' . ucfirst($billing) . ')';
$productDescription = $isRenewal ? 'Plan Renewal - Submission counter will be reset' : 'Journal Subscription Plan';

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => $productName,
                    'description' => $productDescription
                ],
                'unit_amount' => $amount,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata' => [
            'journal_id' => $journalId,
            'plan_type' => $plan,
            'billing_cycle' => $billing,
            'is_renewal' => $isRenewal ? '1' : '0',
        ],
    ]);

    // Redirect to Stripe Checkout
    header('HTTP/1.1 303 See Other');
    header('Location: ' . $session->url);
    exit;
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    die('Stripe API Error: ' . $e->getMessage());
} catch (\Exception $e) {
    die('Error: ' . $e->getMessage());
}
