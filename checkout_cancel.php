<?php
/**
 * Stripe Checkout Cancel Handler
 * Standalone version with journal-specific redirect
 */

$journalId = isset($_GET['journalId']) ? (int)$_GET['journalId'] : 0;

// Read OJS config to get journal path
$configFile = dirname(__DIR__, 3) . '/config.inc.php';
$journalPath = 'index';

if (file_exists($configFile) && $journalId) {
    $config = parse_ini_file($configFile, true);
    $dbDriverRaw = $config['database']['driver'] ?? 'pgsql';
    $dbHost = $config['database']['host'] ?? 'localhost';
    $dbName = $config['database']['name'] ?? '';
    $dbUser = $config['database']['username'] ?? '';
    $dbPass = $config['database']['password'] ?? '';

    $driverMap = [
        'postgres9' => 'pgsql',
        'postgres' => 'pgsql',
        'postgresql' => 'pgsql',
        'mysql' => 'mysql',
        'mysqli' => 'mysql'
    ];
    $dbDriver = $driverMap[$dbDriverRaw] ?? $dbDriverRaw;

    try {
        $pdo = new PDO("{$dbDriver}:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
        $stmt = $pdo->prepare("SELECT path FROM journals WHERE journal_id = ?");
        $stmt->execute([$journalId]);
        $journalPath = $stmt->fetchColumn() ?: 'index';
    } catch (Exception $e) {
        // Use default path
    }
}

$redirectUrl = "/index.php/{$journalPath}/management/settings/workflow#emspubcorePlan";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Cancelled</title>
    <meta http-equiv="refresh" content="3;url=<?php echo $redirectUrl; ?>">
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; background: white; padding: 40px 60px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 450px; }
        h1 { color: #dc3545; margin-bottom: 20px; }
        .icon { font-size: 60px; color: #dc3545; }
        p { color: #666; margin: 10px 0; }
        .btn { display: inline-block; margin-top: 25px; padding: 12px 35px; background: #006798; color: white; text-decoration: none; border-radius: 4px; font-size: 16px; }
        .btn:hover { background: #005580; }
        .redirect-note { font-size: 13px; color: #999; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✕</div>
        <h1>Payment Cancelled</h1>
        <p>The payment process was cancelled.</p>
        <p>No charges were made to your account.</p>
        <a href="<?php echo $redirectUrl; ?>" class="btn">Back to Plan Settings</a>
        <p class="redirect-note">Redirecting automatically in 3 seconds...</p>
    </div>
</body>
</html>
