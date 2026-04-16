<?php
// Creates extension table if missing so the feature can run without manual migration.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS booking_extensions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        renter_id INT NOT NULL,
        owner_id INT NOT NULL,
        requested_dropoff_datetime DATETIME NOT NULL,
        note TEXT NULL,
        status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        INDEX idx_booking (booking_id),
        INDEX idx_owner_status (owner_id, status),
        CONSTRAINT fk_booking_extensions_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        CONSTRAINT fk_booking_extensions_renter FOREIGN KEY (renter_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_booking_extensions_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
?>
