<?php
session_start();

require 'config/db.php';
require 'includes/auth.php';
require 'includes/notification_helpers.php';

toolshare_require_user();

$userId = (int)($_SESSION['user_id'] ?? 0);
$itemType = trim((string)($_GET['type'] ?? ''));
$itemKey = trim((string)($_GET['item_key'] ?? ''));
$target = trim((string)($_GET['to'] ?? 'dashboard.php'));

if ($itemType === 'booking_request' && $itemKey !== '') {
    toolshare_mark_notification_item_viewed($pdo, $userId, 'user', 'booking_request', $itemKey);
}

$parsedTarget = parse_url($target);
$isRelativeTarget = $target !== ''
    && !isset($parsedTarget['scheme'])
    && !isset($parsedTarget['host'])
    && !str_starts_with($target, '//')
    && !str_starts_with($target, '/');

if (!$isRelativeTarget) {
    $target = 'dashboard.php';
}

header('Location: ' . $target);
exit;
