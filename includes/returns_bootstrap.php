<?php
require_once __DIR__ . '/admin_bootstrap.php';

if (!function_exists('toolshare_ensure_column')) {
    function toolshare_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
    {
        static $checked = [];
        $key = $table . '.' . $column;
        if (isset($checked[$key])) {
            return;
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }

        $checked[$key] = true;
    }

    function toolshare_ensure_column_definition_contains(PDO $pdo, string $table, string $column, array $requiredFragments, string $definition): void
    {
        static $checkedDefinitions = [];
        $key = $table . '.' . $column . '.definition';
        if (isset($checkedDefinitions[$key])) {
            return;
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $type = strtolower((string)($row['Type'] ?? ''));
            foreach ($requiredFragments as $fragment) {
                if (strpos($type, strtolower($fragment)) === false) {
                    $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition");
                    break;
                }
            }
        }

        $checkedDefinitions[$key] = true;
    }

    toolshare_ensure_column($pdo, 'bookings', 'returned_at', 'DATETIME NULL AFTER `drop_off_datetime`');
    toolshare_ensure_column($pdo, 'bookings', 'pickup_confirmed_at', 'DATETIME NULL AFTER `pick_up_datetime`');
    toolshare_ensure_column($pdo, 'bookings', 'return_reviewed_at', 'DATETIME NULL AFTER `returned_at`');
    toolshare_ensure_column($pdo, 'bookings', 'deposit_status', "ENUM('held','full_refund','partial_refund','forfeited') NOT NULL DEFAULT 'held' AFTER `security_deposit`");
    toolshare_ensure_column($pdo, 'bookings', 'deposit_refund_amount', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `deposit_status`");
    toolshare_ensure_column($pdo, 'disputes', 'initiated_by', "ENUM('owner','renter') NOT NULL DEFAULT 'owner' AFTER `renter_id`");
    toolshare_ensure_column_definition_contains(
        $pdo,
        'disputes',
        'admin_decision',
        ['pending', 'full_refund', 'partial_deduction', 'full_forfeit', 'deny', 'partial_refund', 'replacement_or_manual_resolution'],
        "ENUM('pending','full_refund','partial_deduction','full_forfeit','deny','partial_refund','replacement_or_manual_resolution') NOT NULL DEFAULT 'pending'"
    );
    toolshare_ensure_column($pdo, 'disputes', 'owner_notes', 'TEXT NULL AFTER `description`');
    toolshare_ensure_column($pdo, 'disputes', 'acknowledged_at', 'DATETIME NULL AFTER `admin_notes`');
    toolshare_ensure_column($pdo, 'disputes', 'review_started_at', 'DATETIME NULL AFTER `acknowledged_at`');
    toolshare_ensure_column($pdo, 'disputes', 'resolved_by_admin_id', 'INT NULL AFTER `review_started_at`');
    toolshare_ensure_column($pdo, 'disputes', 'resolution_summary', 'TEXT NULL AFTER `resolved_by_admin_id`');
}
