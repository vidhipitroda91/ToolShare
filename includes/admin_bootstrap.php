<?php
$pdo->exec("
    CREATE TABLE IF NOT EXISTS disputes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        tool_id INT NOT NULL,
        owner_id INT NOT NULL,
        renter_id INT NOT NULL,
        initiated_by ENUM('owner','renter') NOT NULL DEFAULT 'owner',
        reason VARCHAR(120) NOT NULL,
        description TEXT NOT NULL,
        evidence_paths LONGTEXT NULL,
        status ENUM('pending','reviewing','resolved','rejected') NOT NULL DEFAULT 'pending',
        admin_decision ENUM('pending','full_refund','partial_deduction','full_forfeit','deny','partial_refund','replacement_or_manual_resolution') NOT NULL DEFAULT 'pending',
        deposit_held DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        deposit_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        admin_notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        INDEX idx_disputes_status (status),
        INDEX idx_disputes_booking (booking_id),
        INDEX idx_disputes_owner (owner_id),
        INDEX idx_disputes_renter (renter_id),
        CONSTRAINT fk_disputes_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        CONSTRAINT fk_disputes_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
        CONSTRAINT fk_disputes_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_disputes_renter FOREIGN KEY (renter_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
