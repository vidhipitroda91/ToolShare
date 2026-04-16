<?php
require_once __DIR__ . '/returns_bootstrap.php';

if (!function_exists('toolshare_bootstrap_booking_charges')) {
    function toolshare_bootstrap_booking_charges(PDO $pdo): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS booking_charges (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    booking_id INT NOT NULL,
                    rental_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    renter_platform_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00,
                    renter_platform_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    owner_platform_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00,
                    owner_platform_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    security_deposit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    total_paid_by_renter DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    owner_net_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    deposit_refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    deposit_deduction_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_booking_charges_booking (booking_id),
                    CONSTRAINT fk_booking_charges_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        } catch (Throwable $e) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS booking_charges (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    booking_id INT NOT NULL,
                    rental_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    renter_platform_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00,
                    renter_platform_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    owner_platform_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00,
                    owner_platform_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    security_deposit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    total_paid_by_renter DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    owner_net_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    deposit_refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    deposit_deduction_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_booking_charges_booking (booking_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }

        $bootstrapped = true;
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        toolshare_bootstrap_booking_charges($pdo);
    }
}
