<?php
require_once __DIR__ . '/booking_charges_bootstrap.php';

if (!function_exists('toolshare_bootstrap_payment_fields')) {
    function toolshare_bootstrap_payment_fields(PDO $pdo): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        toolshare_ensure_column($pdo, 'bookings', 'stripe_checkout_session_id', 'VARCHAR(255) NULL AFTER `policy_agreed`');
        toolshare_ensure_column($pdo, 'bookings', 'stripe_payment_intent_id', 'VARCHAR(255) NULL AFTER `stripe_checkout_session_id`');
        toolshare_ensure_column($pdo, 'bookings', 'payment_completed_at', 'DATETIME NULL AFTER `stripe_payment_intent_id`');

        $bootstrapped = true;
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        toolshare_bootstrap_payment_fields($pdo);
    }
}
