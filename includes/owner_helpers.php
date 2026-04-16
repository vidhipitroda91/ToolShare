<?php
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/booking_charges_bootstrap.php';

if (!function_exists('toolshare_owner_overview')) {
    function toolshare_owner_overview(PDO $pdo): array
    {
        toolshare_bootstrap_booking_charges($pdo);

        $grossRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status IN ('paid','completed')")->fetchColumn();
        $totalBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
        $completedBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn();
        $activeRentals = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL AND NOW() BETWEEN pick_up_datetime AND drop_off_datetime")->fetchColumn();
        $upcomingRentals = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL AND pick_up_datetime > NOW()")->fetchColumn();
        $overdueReturns = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL AND drop_off_datetime < NOW()")->fetchColumn();
        $openDisputes = (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status IN ('pending','reviewing')")->fetchColumn();
        $pendingReturns = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NOT NULL AND return_reviewed_at IS NULL")->fetchColumn();
        $pendingPayouts = (float)$pdo->query("
            SELECT COALESCE(SUM(b.total_price * 0.97), 0)
            FROM bookings b
            WHERE b.status = 'paid'
              AND b.returned_at IS NULL
              AND NOT EXISTS (
                    SELECT 1
                    FROM disputes d
                    WHERE d.booking_id = b.id
                      AND d.status IN ('pending', 'reviewing')
              )
        ")->fetchColumn();
        $releasedOwnerPayouts = (float)$pdo->query("
            SELECT COALESCE(SUM((bc.owner_net_payout) + bc.deposit_deduction_amount), 0)
            FROM booking_charges bc
            JOIN bookings b ON bc.booking_id = b.id
            WHERE b.status = 'completed'
        ")->fetchColumn();
        $utilizedTools = (int)$pdo->query("
            SELECT COUNT(DISTINCT tool_id)
            FROM bookings
            WHERE status IN ('paid', 'completed')
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ")->fetchColumn();
        $totalTools = max(1, (int)$pdo->query("SELECT COUNT(*) FROM tools")->fetchColumn());
        $repeatRenters = (int)$pdo->query("
            SELECT COUNT(*)
            FROM (
                SELECT renter_id
                FROM bookings
                WHERE status IN ('paid','completed')
                GROUP BY renter_id
                HAVING COUNT(*) > 1
            ) AS repeaters
        ")->fetchColumn();
        $revenueBookingCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('paid','completed')")->fetchColumn();
        $avgBookingValue = $revenueBookingCount > 0 ? round($grossRevenue / $revenueBookingCount, 2) : 0.0;
        $cancellations = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn();

        return [
            'gross_revenue' => round($grossRevenue, 2),
            'platform_earnings' => round($grossRevenue * 0.06, 2),
            'released_owner_payouts' => round($releasedOwnerPayouts, 2),
            'pending_payouts' => round($pendingPayouts, 2),
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'active_rentals' => $activeRentals,
            'upcoming_rentals' => $upcomingRentals,
            'overdue_returns' => $overdueReturns,
            'open_disputes' => $openDisputes,
            'pending_returns' => $pendingReturns,
            'utilization_rate' => round(($utilizedTools / $totalTools) * 100, 1),
            'repeat_renters' => $repeatRenters,
            'avg_booking_value' => $avgBookingValue,
            'cancellation_rate' => $totalBookings > 0 ? round(($cancellations / $totalBookings) * 100, 1) : 0.0,
            'dispute_rate' => $totalBookings > 0 ? round(($openDisputes / $totalBookings) * 100, 1) : 0.0,
        ];
    }

    function toolshare_owner_top_categories(PDO $pdo): array
    {
        return $pdo->query("
            SELECT
                t.category,
                COUNT(*) AS bookings_count,
                COALESCE(SUM(b.total_price), 0) AS revenue
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            GROUP BY t.category
            ORDER BY revenue DESC, bookings_count DESC
            LIMIT 8
        ")->fetchAll();
    }

    function toolshare_owner_low_performing_tools(PDO $pdo): array
    {
        return $pdo->query("
            SELECT
                t.id,
                t.title,
                t.category,
                u.full_name AS owner_name,
                COUNT(b.id) AS bookings_count
            FROM tools t
            JOIN users u ON t.owner_id = u.id
            LEFT JOIN bookings b ON b.tool_id = t.id AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            GROUP BY t.id, t.title, t.category, u.full_name
            HAVING COUNT(b.id) = 0
            ORDER BY t.id DESC
            LIMIT 10
        ")->fetchAll();
    }

    function toolshare_owner_risk_breakdown(PDO $pdo): array
    {
        return [
            ['label' => 'Open Disputes', 'value' => (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status IN ('pending','reviewing')")->fetchColumn()],
            ['label' => 'Held Deposits', 'value' => (float)$pdo->query("SELECT COALESCE(SUM(security_deposit - deposit_refund_amount), 0) FROM bookings WHERE deposit_status = 'held'")->fetchColumn()],
            ['label' => 'Awaiting Return Review', 'value' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NOT NULL AND return_reviewed_at IS NULL")->fetchColumn()],
            ['label' => 'Cancelled Bookings', 'value' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn()],
        ];
    }

    function toolshare_owner_needs_attention(PDO $pdo): array
    {
        return [
            'pending_disputes' => (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'pending'")->fetchColumn(),
            'return_reviews' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NOT NULL AND return_reviewed_at IS NULL")->fetchColumn(),
            'payouts_on_hold' => (int)$pdo->query("
                SELECT COUNT(*)
                FROM bookings b
                WHERE b.status = 'paid'
                  AND EXISTS (
                      SELECT 1 FROM disputes d
                      WHERE d.booking_id = b.id
                        AND d.status IN ('pending', 'reviewing')
                  )
            ")->fetchColumn(),
            'overdue_returns' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL AND drop_off_datetime < NOW()")->fetchColumn(),
            'inactive_tools' => (int)$pdo->query("
                SELECT COUNT(*)
                FROM (
                    SELECT t.id
                    FROM tools t
                    LEFT JOIN bookings b ON b.tool_id = t.id AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                    GROUP BY t.id
                    HAVING COUNT(b.id) = 0
                ) AS inactive_tools
            ")->fetchColumn(),
        ];
    }
}
