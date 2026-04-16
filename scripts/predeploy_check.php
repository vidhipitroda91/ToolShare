<?php
function env_or_default(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : (string)$value;
}

function print_line(string $label, string $value): void
{
    echo str_pad($label, 28) . $value . PHP_EOL;
}

echo "ToolShare pre-deploy check" . PHP_EOL;
echo str_repeat('=', 28) . PHP_EOL;

$dbHost = trim(env_or_default('DB_HOST', '127.0.0.1'));
$dbName = trim(env_or_default('DB_NAME', 'tool_rental'));
$dbUser = env_or_default('DB_USER', 'root');
$dbPass = env_or_default('DB_PASS', '');
$dbPort = trim(env_or_default('DB_PORT', ''));
$dbSocket = trim(env_or_default('DB_SOCKET', ''));
$dbCharset = trim(env_or_default('DB_CHARSET', 'utf8mb4'));

print_line('DB host', $dbSocket !== '' ? 'socket:' . $dbSocket : $dbHost . ($dbPort !== '' ? ':' . $dbPort : ''));
print_line('DB name', $dbName);
print_line('DB user', $dbUser !== '' ? $dbUser : '[missing]');
print_line('App URL', env_or_default('APP_URL', '[not set]'));
print_line('Stripe secret', env_or_default('STRIPE_SECRET_KEY') !== '' ? '[set]' : '[missing]');
print_line('Stripe webhook', env_or_default('STRIPE_WEBHOOK_SECRET') !== '' ? '[set]' : '[optional/missing]');
print_line('Mail host', env_or_default('MAIL_HOST', '[file or missing]'));
print_line('Mail user', env_or_default('MAIL_USERNAME', '[file or missing]'));

$dsnParts = [];
if ($dbSocket !== '') {
    $dsnParts[] = 'unix_socket=' . $dbSocket;
} else {
    $dsnParts[] = 'host=' . ($dbHost !== '' ? $dbHost : '127.0.0.1');
    if ($dbPort !== '') {
        $dsnParts[] = 'port=' . $dbPort;
    }
}
$dsnParts[] = 'dbname=' . ($dbName !== '' ? $dbName : 'tool_rental');
$dsnParts[] = 'charset=' . ($dbCharset !== '' ? $dbCharset : 'utf8mb4');
$dsn = 'mysql:' . implode(';', $dsnParts);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    print_line('DB connection', 'ok');

    $bookingSummary = $pdo->query("SELECT status, COUNT(*) AS total FROM bookings GROUP BY status ORDER BY status")->fetchAll();
    echo PHP_EOL . "Booking status counts" . PHP_EOL;
    foreach ($bookingSummary as $row) {
        print_line('  ' . $row['status'], (string)$row['total']);
    }

    $checks = [
        'Completed without return' => "SELECT COUNT(*) FROM bookings WHERE status = 'completed' AND returned_at IS NULL",
        'Paid/completed without charges' => "SELECT COUNT(*) FROM bookings b LEFT JOIN booking_charges bc ON bc.booking_id = b.id WHERE b.status IN ('paid','completed') AND bc.booking_id IS NULL",
        'Reviewed without return' => "SELECT COUNT(*) FROM bookings WHERE return_reviewed_at IS NOT NULL AND returned_at IS NULL",
        'Completed with held deposit' => "SELECT COUNT(*) FROM bookings WHERE status = 'completed' AND deposit_status = 'held'",
    ];

    echo PHP_EOL . "Workflow consistency" . PHP_EOL;
    foreach ($checks as $label => $sql) {
        $count = (int)$pdo->query($sql)->fetchColumn();
        print_line('  ' . $label, $count === 0 ? 'ok' : 'issue: ' . $count);
    }
} catch (Throwable $e) {
    print_line('DB connection', 'failed');
    print_line('DB error', $e->getMessage());
}

echo PHP_EOL . "Suggested cloud env vars" . PHP_EOL;
echo "  DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS" . PHP_EOL;
echo "  APP_URL" . PHP_EOL;
echo "  STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET" . PHP_EOL;
echo "  MAIL_ENABLED, MAIL_HOST, MAIL_PORT, MAIL_SECURE, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_EMAIL, MAIL_FROM_NAME" . PHP_EOL;
