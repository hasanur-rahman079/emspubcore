<?php
/**
 * Stripe Checkout Success Handler
 * Standalone version - reads config directly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$sessionId = isset($_GET['session_id']) ? $_GET['session_id'] : '';
$journalId = isset($_GET['journalId']) ? (int)$_GET['journalId'] : 0;

if (!$sessionId || !$journalId) {
    die('Invalid session or journal.');
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

// Get Stripe secret key
$stmt = $pdo->prepare("SELECT setting_value FROM plugin_settings WHERE plugin_name = 'emspubcoreplugin' AND setting_name = 'stripeSecretKey' AND COALESCE(context_id, 0) = 0");
$stmt->execute();
$secretKey = $stmt->fetchColumn();

if (!$secretKey) {
    die('Stripe not configured.');
}

// Load Stripe
require_once __DIR__ . '/vendor/autoload.php';
\Stripe\Stripe::setApiKey($secretKey);

try {
    $session = \Stripe\Checkout\Session::retrieve($sessionId);
    
    if ($session->payment_status === 'paid') {
        // Get metadata
        $planType = $session->metadata->plan_type ?? 'basic';
        $billingCycle = $session->metadata->billing_cycle ?? 'yearly';
        $isRenewal = ($session->metadata->is_renewal ?? '0') === '1';
        
        // Get new plan limit from emspubcore_plans table
        $stmt = $pdo->prepare("SELECT submission_limit FROM emspubcore_plans WHERE LOWER(REPLACE(name, ' ', '')) = ?");
        $stmt->execute([strtolower(str_replace(' ', '', $planType))]);
        $newPlanLimit = $stmt->fetchColumn() ?: 100;
        
        $now = date('Y-m-d H:i:s');
        $duration = ($billingCycle === 'yearly') ? '+1 year' : '+1 month';
        $endDate = date('Y-m-d H:i:s', strtotime($duration));
        
        // Check if plan exists for this journal (and get current plan details for carryover calculation)
        $stmt = $pdo->prepare("SELECT plan_id, plan_type, submissions_limit FROM emspubcore_journal_plans WHERE journal_id = ?");
        $stmt->execute([$journalId]);
        $existingPlan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $finalLimit = $newPlanLimit;
        $shouldResetCounter = false;
        
        // Calculate carryover for upgrades (not renewals)
        if ($existingPlan && !$isRenewal) {
            $oldPlanLimit = (int)$existingPlan['submissions_limit'];
            
            // Get current usage for this year
            $currentYear = date('Y');
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(submission_count), 0) FROM emspubcore_submission_usage 
                WHERE journal_id = ? AND year_month LIKE ?");
            $stmt->execute([$journalId, $currentYear . '-%']);
            $currentUsage = (int)$stmt->fetchColumn();
            
            // Calculate remaining from old plan
            $remainingFromOldPlan = max(0, $oldPlanLimit - $currentUsage);
            
            // Add carryover: new plan limit + remaining from old plan, then reset counter
            if ($remainingFromOldPlan > 0) {
                $finalLimit = $newPlanLimit + $remainingFromOldPlan;
                $shouldResetCounter = true; // Reset counter so user gets fresh start with bonus
            }
        }
        
        if ($existingPlan) {
            // Update existing plan
            $stmt = $pdo->prepare("UPDATE emspubcore_journal_plans SET 
                plan_type = ?, 
                billing_cycle = ?, 
                submissions_limit = ?, 
                plan_start_date = ?, 
                plan_end_date = ?, 
                stripe_customer_id = ?,
                is_active = 1 
                WHERE plan_id = ?");
            $stmt->execute([$planType, $billingCycle, $finalLimit, $now, $endDate, $session->customer ?? '', $existingPlan['plan_id']]);
        } else {
            // Insert new plan
            $stmt = $pdo->prepare("INSERT INTO emspubcore_journal_plans 
                (journal_id, plan_type, billing_cycle, submissions_limit, plan_start_date, plan_end_date, stripe_customer_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$journalId, $planType, $billingCycle, $finalLimit, $now, $endDate, $session->customer ?? '']);
        }
        
        // Reset the submission usage counter for renewals OR upgrades with carryover
        if ($isRenewal || $shouldResetCounter) {
            // Get current year
            $currentYear = date('Y');
            
            // Reset the yearly count by deleting this year's usage records
            // The year_month column format is "YYYY-MM" (e.g., "2024-12")
            $stmt = $pdo->prepare("DELETE FROM emspubcore_submission_usage 
                WHERE journal_id = ? AND year_month LIKE ?");
            $stmt->execute([$journalId, $currentYear . '-%']);
        }
        
        // Log payment
        $stmt = $pdo->prepare("INSERT INTO emspubcore_payment_history 
            (journal_id, amount, currency, stripe_payment_intent_id, status, payment_date, plan_type, billing_cycle) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $journalId,
            $session->amount_total,
            strtoupper($session->currency ?? 'USD'),
            $session->payment_intent ?? '',
            'succeeded',
            $now,
            $planType,
            $billingCycle
        ]);
        
        // Get journal path for redirect URL
        $stmt = $pdo->prepare("SELECT path FROM journals WHERE journal_id = ?");
        $stmt->execute([$journalId]);
        $journalPath = $stmt->fetchColumn() ?: 'index';
        
        $redirectUrl = "/index.php/{$journalPath}/management/settings/workflow#emspubcorePlan";
        
        // Set messages based on renewal or upgrade
        $titleText = $isRenewal ? 'Renewal Successful!' : 'Payment Successful!';
        $messageText = $isRenewal 
            ? 'Your <span class="plan-name">' . htmlspecialchars($planType) . '</span> plan has been renewed.'
            : 'Your plan has been upgraded to <span class="plan-name">' . htmlspecialchars($planType) . '</span>.';
        $extraInfo = $isRenewal ? '<p style="color: #28a745; font-weight: bold;">✓ Submission counter has been reset to 0</p>' : '';
        
        // Show success page with auto-redirect
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo $titleText; ?></title>
            <meta http-equiv="refresh" content="3;url=<?php echo $redirectUrl; ?>">
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
                .container { text-align: center; background: white; padding: 40px 60px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 450px; }
                h1 { color: #28a745; margin-bottom: 20px; }
                .checkmark { font-size: 60px; color: #28a745; }
                p { color: #666; margin: 10px 0; }
                .plan-name { font-weight: bold; color: #333; text-transform: capitalize; }
                .btn { display: inline-block; margin-top: 25px; padding: 12px 35px; background: #006798; color: white; text-decoration: none; border-radius: 4px; font-size: 16px; }
                .btn:hover { background: #005580; }
                .redirect-note { font-size: 13px; color: #999; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="checkmark">✓</div>
                <h1><?php echo $titleText; ?></h1>
                <p><?php echo $messageText; ?></p>
                <p>Billing cycle: <strong><?php echo ucfirst($billingCycle); ?></strong></p>
                <?php echo $extraInfo; ?>
                <p>Thank you for your payment.</p>
                <a href="<?php echo $redirectUrl; ?>" class="btn">Back to Plan Settings</a>
                <p class="redirect-note">Redirecting automatically in 3 seconds...</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        echo '<h3>Payment not completed. Status: ' . htmlspecialchars($session->payment_status) . '</h3>';
    }
    
} catch (\Exception $e) {
    echo '<h3>Error verifying payment: ' . htmlspecialchars($e->getMessage()) . '</h3>';
}
