<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';

echo "Running ToolShare bootstrap migrations..." . PHP_EOL;

require_once __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/booking_extensions_bootstrap.php';
require_once __DIR__ . '/../includes/returns_bootstrap.php';
require_once __DIR__ . '/../includes/email_verification_bootstrap.php';
require_once __DIR__ . '/../includes/booking_charges_bootstrap.php';
require_once __DIR__ . '/../includes/payment_bootstrap.php';
require_once __DIR__ . '/../includes/dispute_history_bootstrap.php';
require_once __DIR__ . '/../includes/support_tickets_bootstrap.php';
require_once __DIR__ . '/../includes/user_moderation_bootstrap.php';

echo "Bootstrap migrations completed successfully." . PHP_EOL;
