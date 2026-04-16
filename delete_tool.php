<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';

toolshare_require_user();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$tool_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// SECURITY: Ensure the person deleting the tool actually owns it
$stmt = $pdo->prepare("SELECT image_path FROM tools WHERE id = ? AND owner_id = ?");
$stmt->execute([$tool_id, $user_id]);
$tool = $stmt->fetch();

if ($tool) {
    // 1. Delete the physical image file from the /uploads folder
    if (file_exists($tool['image_path'])) {
        unlink($tool['image_path']);
    }

    // 2. Delete the record from the database
    $delete_stmt = $pdo->prepare("DELETE FROM tools WHERE id = ?");
    $delete_stmt->execute([$tool_id]);

    header("Location: dashboard.php?msg=deleted");
} else {
    die("Unauthorized action or tool not found.");
}
?>
