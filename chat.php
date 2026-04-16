<?php
session_start();
require 'config/db.php';
require 'includes/site_chrome.php';
require 'includes/auth.php';
require 'includes/marketplace_mail_helper.php';

toolshare_require_user();

$user_id = (int)$_SESSION['user_id'];
$tool_id = isset($_GET['tool_id']) ? (int)$_GET['tool_id'] : 0;
$requested_receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
$is_embed = isset($_GET['embed']) && $_GET['embed'] === '1';
$error_message = '';
$back_href = 'dashboard.php';

if ($tool_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// 1. Fetch Tool Info
$stmt = $pdo->prepare("SELECT id, owner_id, title FROM tools WHERE id = ?");
$stmt->execute([$tool_id]);
$tool = $stmt->fetch();

if (!$tool) {
    header("Location: dashboard.php");
    exit;
}

// 2. Resolve allowed receiver
if ((int)$tool['owner_id'] !== $user_id) {
    // Renter can only message the owner from tool page/chat page
    $receiver_id = (int)$tool['owner_id'];
    $back_href = 'tool_detail.php?id=' . $tool_id;
} else {
    $receiver_id = $requested_receiver_id;
    if ($receiver_id <= 0) {
        header("Location: dashboard.php");
        exit;
    }
    $back_href = 'dashboard.php';

    // Owner can message only renters who have booking history for this tool
    $bookingCheck = $pdo->prepare("
        SELECT id FROM bookings
        WHERE tool_id = ? AND owner_id = ? AND renter_id = ?
        LIMIT 1
    ");
    $bookingCheck->execute([$tool_id, $user_id, $receiver_id]);
    if (!$bookingCheck->fetch()) {
        header("Location: dashboard.php");
        exit;
    }
}

if ($receiver_id === $user_id) {
    header("Location: dashboard.php");
    exit;
}

// 3. Fetch Receiver Name
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$receiver_id]);
$receiver = $stmt->fetch();

if (!$receiver) {
    header("Location: dashboard.php");
    exit;
}

// 4. Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = trim($_POST['message'] ?? '');
    if ($msg === '') {
        $error_message = 'Type a message before sending.';
    } elseif (mb_strlen($msg) > 1000) {
        $error_message = 'Please keep your message under 1000 characters.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (tool_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tool_id, $user_id, $receiver_id, $msg]);
        toolshare_mail_send_chat_message_notification($pdo, (int)$pdo->lastInsertId());
        $redirectUrl = "chat.php?tool_id=$tool_id&receiver_id=$receiver_id";
        if ($is_embed) {
            $redirectUrl .= '&embed=1';
        }
        header("Location: $redirectUrl");
        exit;
    }
}

// 5. Mark incoming messages as read
$markRead = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE tool_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = 0");
$markRead->execute([$tool_id, $receiver_id, $user_id]);

// 6. Fetch Conversation History
$stmt = $pdo->prepare("SELECT * FROM messages WHERE tool_id = ? 
    AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
    ORDER BY created_at ASC");
$stmt->execute([$tool_id, $user_id, $receiver_id, $receiver_id, $user_id]);
$messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Chat - ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; margin: 0; }
        body.chat-embed { background: transparent; padding-top: 0 !important; }
        .chat-container { max-width: 760px; margin: 20px auto; background: white; border-radius: 22px; overflow: hidden; display: flex; flex-direction: column; height: calc(100vh - 150px); box-shadow: 0 18px 35px rgba(15,23,42,0.08); }
        body.chat-embed .chat-container { max-width: 100%; margin: 0; height: 100vh; border-radius: 0; box-shadow: none; }
        .chat-header { background: #2c3e50; color: white; padding: 15px 18px; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .chat-header-copy strong { display: block; font-size: 18px; }
        .chat-header-copy small { color: rgba(255,255,255,0.82); font-size: 13px; }
        .chat-close { color: white; text-decoration: none; border: 1px solid rgba(255,255,255,0.24); border-radius: 999px; padding: 8px 12px; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .chat-box { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); }
        .chat-empty { margin: auto; text-align: center; color: #64748b; max-width: 320px; line-height: 1.5; }
        .msg-row { display: flex; flex-direction: column; }
        .msg { padding: 10px 15px; border-radius: 18px; max-width: 70%; line-height: 1.45; word-break: break-word; }
        .sent { align-self: flex-end; background: #0084ff; color: white; border-bottom-right-radius: 2px; }
        .received { align-self: flex-start; background: #e4e6eb; color: black; border-bottom-left-radius: 2px; }
        .msg-time { font-size: 11px; color: #94a3b8; margin-top: 4px; }
        .msg-row.sent-row .msg-time { align-self: flex-end; }
        .msg-row.received-row .msg-time { align-self: flex-start; }
        .chat-error { margin: 14px 15px 0; padding: 10px 12px; border-radius: 12px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-size: 14px; }
        .chat-input { padding: 15px; border-top: 1px solid #ddd; display: flex; gap: 10px; align-items: center; }
        .chat-input input { flex: 1; padding: 12px 14px; border: 1px solid #ddd; border-radius: 20px; outline: none; }
        .chat-input input:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,0.16); }
        .chat-input button { background: #0084ff; color: white; border: none; padding: 12px 20px; border-radius: 20px; cursor: pointer; font-weight: 700; }
        @media (max-width: 820px) {
            .chat-container { margin: 12px auto; height: calc(100vh - 120px); width: min(96%, 760px); }
            .chat-header { flex-direction: column; align-items: stretch; }
            .chat-close { align-self: flex-start; }
            .msg { max-width: 85%; }
            .chat-input { flex-wrap: wrap; }
            .chat-input button { width: 100%; }
        }
    </style>
</head>
<body class="<?= $is_embed ? 'chat-embed' : '' ?>">
<?php if (!$is_embed): ?>
    <?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>
<?php endif; ?>

<div class="chat-container">
    <div class="chat-header">
        <div class="chat-header-copy">
            <strong><?php echo htmlspecialchars($tool['title']); ?></strong>
            <small>Chatting with <?php echo htmlspecialchars($receiver['full_name']); ?></small>
        </div>
        <?php if ($is_embed): ?>
            <button type="button" class="chat-close" id="closeChatOverlayButton" style="background:transparent;">Close Chat</button>
        <?php else: ?>
            <a href="<?= htmlspecialchars($back_href) ?>" class="chat-close">Close Chat</a>
        <?php endif; ?>
    </div>

    <div class="chat-box" id="chatBox">
        <?php if (empty($messages)): ?>
            <div class="chat-empty">
                No messages yet. Start the conversation here about the tool, pickup, return, or any rental questions.
            </div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <?php $isSent = (int)$m['sender_id'] === $user_id; ?>
                <div class="msg-row <?= $isSent ? 'sent-row' : 'received-row' ?>">
                    <div class="msg <?= $isSent ? 'sent' : 'received' ?>">
                        <?php echo nl2br(htmlspecialchars($m['message_text'])); ?>
                    </div>
                    <div class="msg-time"><?php echo date('M d, Y h:i A', strtotime($m['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($error_message !== ''): ?>
        <div class="chat-error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form class="chat-input" method="POST">
        <input type="text" name="message" placeholder="Type a message..." autocomplete="off" maxlength="1000" required>
        <button type="submit">Send</button>
    </form>
</div>

<script>
    // Auto-scroll to bottom of chat
    var chatBox = document.getElementById("chatBox");
    chatBox.scrollTop = chatBox.scrollHeight;

    var closeChatOverlayButton = document.getElementById('closeChatOverlayButton');
    if (closeChatOverlayButton) {
        closeChatOverlayButton.addEventListener('click', function () {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'toolshare-close-chat' }, window.location.origin);
            }
        });
    }
</script>
<?php if (!$is_embed): ?>
    <?php toolshare_render_chrome_scripts(); ?>
<?php endif; ?>
</body>
</html>
