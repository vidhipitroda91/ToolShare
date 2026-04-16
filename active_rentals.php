<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/returns_bootstrap.php';
require 'includes/site_chrome.php';

toolshare_require_user();

$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        b.*,
        COALESCE(t.title, 'Tool unavailable') AS title,
        COALESCE(u.full_name, 'Renter unavailable') AS renter_name
    FROM bookings b
    LEFT JOIN tools t ON b.tool_id = t.id
    LEFT JOIN users u ON b.renter_id = u.id
    WHERE b.owner_id = ?
      AND b.status = 'paid'
      AND b.returned_at IS NULL
    ORDER BY b.drop_off_datetime ASC
");
$stmt->execute([$user_id]);
$rentals = $stmt->fetchAll();

$stmt_returns = $pdo->prepare("
    SELECT
        b.*,
        t.title,
        u.full_name AS renter_name,
        COALESCE((
            SELECT d.status
            FROM disputes d
            WHERE d.booking_id = b.id
            ORDER BY d.created_at DESC
            LIMIT 1
        ), 'none') AS dispute_status
    FROM bookings b
    JOIN tools t ON b.tool_id = t.id
    JOIN users u ON b.renter_id = u.id
    WHERE b.owner_id = ?
      AND b.status = 'paid'
      AND b.returned_at IS NOT NULL
    ORDER BY b.returned_at DESC
");
$stmt_returns->execute([$user_id]);
$returnedBookings = $stmt_returns->fetchAll();

$flashMessage = '';
$flashTone = 'success';
if (!empty($_GET['msg'])) {
    switch ((string)$_GET['msg']) {
        case 'dispute_exists':
            $flashMessage = 'An open dispute already exists for this booking.';
            $flashTone = 'error';
            break;
        case 'return_approved':
            $flashMessage = 'Return approved and the deposit was marked for full refund.';
            break;
        case 'return_action_denied':
            $flashMessage = 'That return action is not available for this booking.';
            $flashTone = 'error';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Active Rentals | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root { --primary: #1a3654; --bg: #f8fafc; --text: #1e293b; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 13px; display: inline-block; }
        .btn-primary { background: var(--primary); color: #fff; }
        .card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #f1f5f9; }
        td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-upcoming { background: #dbeafe; color: #1e40af; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>
    <div class="wrap">
        <?php if ($flashMessage !== ''): ?>
            <div style="<?= $flashTone === 'error'
                ? 'background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;'
                : 'background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;'
            ?> padding:10px 12px; border-radius:10px; margin-bottom:18px; font-size:14px;">
                <?= htmlspecialchars($flashMessage) ?>
            </div>
        <?php endif; ?>
        <div class="topbar">
            <h1>My Active Rentals</h1>
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
        <div class="card">
            <h2 style="margin-bottom: 16px; color: var(--primary);">Current Rentals</h2>
            <?php if (empty($rentals)): ?>
                <p class="muted">No active rentals.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Renter</th>
                            <th>Status</th>
                            <th>Pickup</th>
                            <th>Drop-off</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rentals as $rental): ?>
                            <?php
                                $now = time();
                                $pickupTs = strtotime($rental['pick_up_datetime']);
                                $dropoffTs = strtotime($rental['drop_off_datetime']);
                                $isActiveNow = $now >= $pickupTs;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($rental['title']) ?></strong></td>
                                <td><?= htmlspecialchars($rental['renter_name']) ?></td>
                                <td>
                                    <?php if ($isActiveNow): ?>
                                        <span class="badge badge-active">Active Now</span>
                                    <?php else: ?>
                                        <span class="badge badge-upcoming">Upcoming</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y h:i A', $pickupTs) ?></td>
                                <td><?= date('M d, Y h:i A', $dropoffTs) ?></td>
                                <td>
                                    <a href="chat.php?tool_id=<?= (int)$rental['tool_id'] ?>&receiver_id=<?= (int)$rental['renter_id'] ?>" class="btn btn-primary">Message Renter</a>
                                    <button type="button" data-download-url="owner_earnings_receipt_pdf.php?booking_id=<?= (int)$rental['id'] ?>" class="btn js-direct-download" style="margin-left:8px; background:#1f6f78; color:#fff; border:none; cursor:pointer;">Download Statement</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="card" style="margin-top: 24px;">
            <h2 style="margin-bottom: 16px; color: var(--primary);">Returned Tools Waiting for Review</h2>
            <?php if (empty($returnedBookings)): ?>
                <p class="muted">No return requests waiting for action.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Renter</th>
                            <th>Returned At</th>
                            <th>Deposit</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returnedBookings as $booking): ?>
                            <?php
                                $hasOpenDispute = in_array($booking['dispute_status'], ['pending', 'reviewing'], true);
                                $isResolvedDispute = in_array($booking['dispute_status'], ['resolved', 'rejected'], true);
                                $ownerNeedsAction = $booking['return_reviewed_at'] === null && !$hasOpenDispute && $booking['status'] === 'paid';
                                $statusLabel = $hasOpenDispute ? 'Under Dispute' : ($booking['return_reviewed_at'] !== null || $isResolvedDispute ? 'Reviewed' : 'Awaiting Review');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($booking['title']) ?></strong></td>
                                <td><?= htmlspecialchars($booking['renter_name']) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($booking['returned_at'])) ?></td>
                                <td>$<?= number_format((float)$booking['security_deposit'], 2) ?></td>
                                <td><span class="badge <?= $ownerNeedsAction ? 'badge-pending' : 'badge-active' ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                                <td>
                                    <?php if ($ownerNeedsAction): ?>
                                        <a href="update_booking.php?id=<?= (int)$booking['id'] ?>&action=approve_return" class="btn btn-primary">Approve Return</a>
                                        <a href="raise_dispute.php?booking_id=<?= (int)$booking['id'] ?>&mode=owner" class="btn btn-primary" style="margin-left:8px; background:#b91c1c;">Raise Dispute</a>
                                    <?php elseif ($hasOpenDispute): ?>
                                        <span class="muted">Escalated to operations review. Support may contact both parties if more information is needed.</span>
                                    <?php else: ?>
                                        <span class="muted">No pending action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('click', async function (event) {
            const button = event.target.closest('.js-direct-download');
            if (!button) return;
            event.preventDefault();
            const downloadUrl = button.dataset.downloadUrl;
            if (!downloadUrl) return;
            const originalText = button.textContent;
            button.textContent = 'Downloading...';
            button.disabled = true;
            try {
                const response = await fetch(downloadUrl, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('Download failed');
                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const tempLink = document.createElement('a');
                tempLink.href = objectUrl;
                tempLink.download = '';
                document.body.appendChild(tempLink);
                tempLink.click();
                tempLink.remove();
                URL.revokeObjectURL(objectUrl);
            } catch (error) {
                window.alert('Unable to download the file right now.');
            } finally {
                button.textContent = originalText;
                button.disabled = false;
            }
        });
    </script>
    <?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
