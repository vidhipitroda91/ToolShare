<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/returns_bootstrap.php';
require 'includes/dispute_history_bootstrap.php';
require 'includes/site_chrome.php';
require 'includes/marketplace_mail_helper.php';

toolshare_require_user();

if (!function_exists('toolshare_dispute_mode_meta')) {
    function toolshare_dispute_mode_meta(string $mode): array
    {
        if ($mode === 'renter') {
            return [
                'title' => 'Report a Problem',
                'kicker' => 'Pickup Support',
                'heading' => 'Report a Pickup or Service Problem',
                'subtitle' => 'Tell operations what went wrong before you confirm pickup. Use this for no-shows, damaged handoff condition, unsafe tools, or service issues that blocked the rental from starting properly.',
                'back_href' => 'dashboard.php',
                'back_label' => 'Back to Dashboard',
                'redirect' => 'dashboard.php?msg=renter_dispute_submitted',
                'duplicate_redirect' => 'dashboard.php?msg=dispute_exists',
                'evidence_required' => false,
                'reason_label' => 'Problem Type',
                'description_label' => 'What Happened',
                'description_help' => 'Summarize what happened at pickup or handoff so operations can understand the issue quickly.',
                'notes_label' => 'Supporting Notes',
                'notes_help' => 'Add useful details like when you arrived, who you contacted, what the condition looked like, or what part of the listing did not match.',
                'evidence_help' => 'Upload photos or screenshots if available. Evidence is helpful but not required for every renter-side claim.',
                'reason_options' => [
                    'Tool not received' => 'Tool not received',
                    'Pickup no-show' => 'Pickup no-show',
                    'Tool damaged on pickup' => 'Tool damaged on pickup',
                    'Tool not as described' => 'Tool not as described',
                    'Unsafe or unusable tool' => 'Unsafe or unusable tool',
                ],
            ];
        }

        return [
            'title' => 'Raise Dispute',
            'kicker' => 'Return Review',
            'heading' => 'Raise Return / Damage Dispute',
            'subtitle' => 'Submit the issue for admin review. All fields below are required so the admin has enough detail to make a fair decision.',
            'back_href' => 'active_rentals.php',
            'back_label' => 'Back to Rentals',
            'redirect' => 'dashboard.php?msg=dispute_submitted',
            'duplicate_redirect' => 'active_rentals.php?msg=dispute_exists',
            'evidence_required' => true,
            'reason_label' => 'Reason',
            'description_label' => 'Description',
            'description_help' => 'Use this box for the core issue summary the admin should understand first.',
            'notes_label' => 'Inspection Notes',
            'notes_help' => 'Use this box for extra supporting details from your inspection, separate from the main issue description above.',
            'evidence_help' => 'Upload at least one clear image. Only image files under 5 MB each are accepted.',
            'reason_options' => [
                'Damage to tool' => 'Damage to tool',
                'Missing accessories' => 'Missing accessories',
                'Late return' => 'Late return',
                'Incorrect return condition' => 'Incorrect return condition',
            ],
        ];
    }
}

$mode = trim((string)($_GET['mode'] ?? $_POST['mode'] ?? 'owner'));
$mode = in_array($mode, ['owner', 'renter'], true) ? $mode : 'owner';
$modeMeta = toolshare_dispute_mode_meta($mode);

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (int)($_POST['booking_id'] ?? 0);
if ($booking_id <= 0) {
    die('Invalid booking.');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

$sql = "
    SELECT
        b.*,
        t.title,
        t.image_path,
        renter.full_name AS renter_name,
        owner.full_name AS owner_name
    FROM bookings b
    JOIN tools t ON b.tool_id = t.id
    JOIN users renter ON b.renter_id = renter.id
    JOIN users owner ON b.owner_id = owner.id
    WHERE b.id = ?
";
$params = [$booking_id];

if ($mode === 'renter') {
    $sql .= "
      AND b.renter_id = ?
      AND b.status = 'paid'
      AND b.returned_at IS NULL
      AND b.pickup_confirmed_at IS NULL
      AND NOW() >= DATE_SUB(b.pick_up_datetime, INTERVAL 2 HOUR)
      AND NOW() <= DATE_ADD(b.drop_off_datetime, INTERVAL 24 HOUR)
    ";
} else {
    $sql .= "
      AND b.owner_id = ?
      AND b.status = 'paid'
      AND b.returned_at IS NOT NULL
      AND b.return_reviewed_at IS NULL
    ";
}
$params[] = $user_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$booking = $stmt->fetch();

if (!$booking) {
    die($mode === 'renter'
        ? 'Booking not found or this pickup issue is no longer eligible for renter review.'
        : 'Booking not found or this return is not eligible for dispute review.');
}

$check = $pdo->prepare("SELECT id FROM disputes WHERE booking_id = ? AND status IN ('pending','reviewing') LIMIT 1");
$check->execute([$booking_id]);
$existingDispute = $check->fetchColumn();
if ($existingDispute) {
    header('Location: ' . $modeMeta['duplicate_redirect']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $supportingNotes = trim((string)($_POST['owner_notes'] ?? ''));
    $evidencePaths = [];

    if ($reason === '' || $description === '' || $supportingNotes === '') {
        $error = 'Please complete all required dispute details before submitting.';
    } elseif ($modeMeta['evidence_required'] && empty($_FILES['evidence']['name'][0])) {
        $error = 'Please upload at least one evidence image.';
    } else {
        $uploadDir = 'uploads/disputes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!empty($_FILES['evidence']['name'][0])) {
            foreach ($_FILES['evidence']['name'] as $index => $fileName) {
                if (($_FILES['evidence']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $tmpName = $_FILES['evidence']['tmp_name'][$index];
                if (!is_uploaded_file($tmpName)) {
                    continue;
                }

                $fileSize = (int)($_FILES['evidence']['size'][$index] ?? 0);
                if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
                    continue;
                }

                $mimeType = mime_content_type($tmpName) ?: '';
                if (strpos($mimeType, 'image/') !== 0) {
                    continue;
                }

                $extension = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    $extension = 'jpg';
                }

                $safeName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '_' . $index . '.' . $extension;
                $targetPath = $uploadDir . $safeName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    @chmod($targetPath, 0644);
                    $evidencePaths[] = $targetPath;
                }
            }
        }

        if ($modeMeta['evidence_required'] && empty($evidencePaths)) {
            $error = 'Please upload at least one valid evidence image under 5 MB.';
        }

        if (!isset($error)) {
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare("
                    INSERT INTO disputes (
                        booking_id, tool_id, owner_id, renter_id, initiated_by, reason, description,
                        owner_notes, evidence_paths, status, admin_decision, deposit_held, deposit_deducted
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, 0.00)
                ");
                $insert->execute([
                    $booking['id'],
                    $booking['tool_id'],
                    $booking['owner_id'],
                    $booking['renter_id'],
                    $mode,
                    $reason,
                    $description,
                    $supportingNotes,
                    !empty($evidencePaths) ? json_encode($evidencePaths) : null,
                    $booking['security_deposit'],
                ]);
                $disputeId = (int)$pdo->lastInsertId();

                try {
                    $history = $pdo->prepare("
                        INSERT INTO dispute_history (
                            dispute_id,
                            admin_id,
                            previous_status,
                            new_status,
                            previous_decision,
                            new_decision,
                            deposit_deducted,
                            admin_notes
                        ) VALUES (?, NULL, NULL, 'pending', NULL, 'pending', 0.00, ?)
                    ");
                    $history->execute([$disputeId, $supportingNotes]);
                } catch (Throwable $e) {
                    // Dispute creation should still succeed even if history logging fails.
                }

                if ($mode === 'owner') {
                    $updateBooking = $pdo->prepare("
                        UPDATE bookings
                        SET deposit_status = 'held',
                            deposit_refund_amount = 0.00
                        WHERE id = ?
                    ");
                    $updateBooking->execute([$booking['id']]);
                }

                $pdo->commit();
                toolshare_mail_send_dispute_created_notifications($pdo, $disputeId);
                header('Location: ' . $modeMeta['redirect']);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Unable to submit the dispute right now. Please try again.';
            }
        }
    }
}

$asideLines = $mode === 'renter'
    ? [
        'Owner: ' . (string)$booking['owner_name'],
        'Booking #' . (int)$booking['id'],
        'Pickup window: ' . date('M d, Y h:i A', strtotime((string)$booking['pick_up_datetime'])),
        'Drop-off: ' . date('M d, Y h:i A', strtotime((string)$booking['drop_off_datetime'])),
        'Amount paid: $' . number_format((float)$booking['total_price'], 2),
        'Deposit paid: $' . number_format((float)$booking['security_deposit'], 2),
    ]
    : [
        'Renter: ' . (string)$booking['renter_name'],
        'Booking #' . (int)$booking['id'],
        'Returned at: ' . date('M d, Y h:i A', strtotime((string)$booking['returned_at'])),
        'Deposit held: $' . number_format((float)$booking['security_deposit'], 2),
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($modeMeta['title']) ?> | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root { --primary: #15324a; --accent: #1f7a86; --bg: #f8fafc; --text: #1e293b; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); }
        .wrap { width: min(980px, 94%); margin: 0 auto; padding: 22px 0 40px; }
        .grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; }
        .card { background: #fff; border-radius: 22px; padding: 24px; box-shadow: 0 16px 34px rgba(15,23,42,0.06); border: 1px solid #e2e8f0; }
        .card img { width: 100%; border-radius: 16px; margin-bottom: 18px; }
        h1 { color: var(--primary); margin-bottom: 8px; }
        p.sub { color: #64748b; margin-bottom: 18px; }
        label { display: block; font-size: 12px; text-transform: uppercase; font-weight: 800; color: #64748b; margin-bottom: 8px; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #dbe3ee; border-radius: 12px; font-size: 14px; margin-bottom: 16px; }
        textarea { min-height: 140px; resize: vertical; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 999px; text-decoration: none; border: none; font-weight: 800; cursor: pointer; background: var(--accent); color: #fff; }
        .btn.secondary { background: #e2e8f0; color: var(--primary); margin-left: 10px; }
        .error { margin-bottom: 16px; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px; border-radius: 12px; }
        @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php toolshare_render_focus_header([
    'kicker' => $modeMeta['kicker'],
    'title' => $modeMeta['title'],
    'back_href' => $modeMeta['back_href'],
    'back_label' => $modeMeta['back_label'],
]); ?>
<div class="wrap">
    <div class="grid">
        <aside class="card">
            <img src="<?= htmlspecialchars($booking['image_path']) ?>" alt="<?= htmlspecialchars($booking['title']) ?>">
            <h2><?= htmlspecialchars($booking['title']) ?></h2>
            <?php foreach ($asideLines as $line): ?>
                <p class="sub"><?= htmlspecialchars($line) ?></p>
            <?php endforeach; ?>
        </aside>
        <main class="card">
            <h1><?= htmlspecialchars($modeMeta['heading']) ?></h1>
            <p class="sub"><?= htmlspecialchars($modeMeta['subtitle']) ?></p>
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="booking_id" value="<?= (int)$booking_id ?>">
                <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                <label><?= htmlspecialchars($modeMeta['reason_label']) ?></label>
                <select name="reason" required>
                    <option value="">Select a reason</option>
                    <?php foreach ($modeMeta['reason_options'] as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= (($_POST['reason'] ?? '') === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label><?= htmlspecialchars($modeMeta['description_label']) ?></label>
                <textarea name="description" placeholder="Required: explain the main problem clearly so operations can investigate quickly." required><?= htmlspecialchars((string)($_POST['description'] ?? '')) ?></textarea>
                <p class="sub" style="margin-top:-8px; margin-bottom:16px;"><?= htmlspecialchars($modeMeta['description_help']) ?></p>
                <label><?= htmlspecialchars($modeMeta['notes_label']) ?></label>
                <textarea name="owner_notes" placeholder="Required: add supporting details, timeline notes, contact attempts, or any facts that help operations review the claim fairly." required><?= htmlspecialchars((string)($_POST['owner_notes'] ?? '')) ?></textarea>
                <p class="sub" style="margin-top:-8px; margin-bottom:16px;"><?= htmlspecialchars($modeMeta['notes_help']) ?></p>
                <label>Evidence Images</label>
                <input type="file" name="evidence[]" accept="image/*" multiple <?= $modeMeta['evidence_required'] ? 'required' : '' ?>>
                <p class="sub" style="margin-top:-8px; margin-bottom:16px;"><?= htmlspecialchars($modeMeta['evidence_help']) ?></p>
                <button type="submit" class="btn"><?= $mode === 'renter' ? 'Submit Problem Report' : 'Submit Dispute' ?></button>
                <a href="<?= htmlspecialchars($modeMeta['back_href']) ?>" class="btn secondary">Back</a>
            </form>
        </main>
    </div>
</div>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
