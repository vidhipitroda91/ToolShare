<?php
session_start();
require 'config/db.php';
require 'includes/site_chrome.php';
require 'includes/auth.php';
require 'includes/support_helpers.php';

toolshare_require_user();

$userId = (int)($_SESSION['user_id'] ?? 0);
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : (int)($_POST['ticket_id'] ?? 0);
$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';
$errorMessage = '';
$backHref = 'support.php';

if ($ticketId <= 0) {
    header('Location: support.php');
    exit;
}

$ticket = toolshare_support_fetch_user_ticket($pdo, $ticketId, $userId);
if (!$ticket) {
    header('Location: support.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim((string)($_POST['message'] ?? ''));
    if ((string)$ticket['status'] === 'resolved') {
        $errorMessage = 'This support ticket is already closed.';
    } elseif ($message === '') {
        $errorMessage = 'Type a message before sending.';
    } elseif (mb_strlen($message) > 2000) {
        $errorMessage = 'Please keep your reply under 2000 characters.';
    } else {
        try {
            toolshare_support_reply_as_user($pdo, $ticketId, $userId, $message);
            $redirectUrl = 'support_chat.php?ticket_id=' . $ticketId;
            if ($isEmbed) {
                $redirectUrl .= '&embed=1';
            }
            header('Location: ' . $redirectUrl);
            exit;
        } catch (Throwable $e) {
            $errorMessage = 'Unable to send your reply right now.';
        }
    }
}

toolshare_support_mark_ticket_messages_read($pdo, $ticketId, $userId);
$messages = toolshare_support_fetch_ticket_messages($pdo, $ticketId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Support Chat - ToolShare</title>
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
<body class="<?= $isEmbed ? 'chat-embed' : '' ?>">
<?php if (!$isEmbed): ?>
    <?php toolshare_render_nav(['support_href' => 'support.php']); ?>
<?php endif; ?>

<div class="chat-container">
    <div class="chat-header">
        <div class="chat-header-copy">
            <strong>Support Ticket #<?= (int)$ticket['id'] ?></strong>
            <small>Chatting with ToolShare Support · <?= htmlspecialchars(toolshare_support_status_label((string)$ticket['status'])) ?></small>
        </div>
        <?php if ($isEmbed): ?>
            <button type="button" class="chat-close" id="closeChatOverlayButton" style="background:transparent;">Close Chat</button>
        <?php else: ?>
            <a href="<?= htmlspecialchars($backHref) ?>" class="chat-close">Close Chat</a>
        <?php endif; ?>
    </div>

    <div class="chat-box" id="chatBox">
        <?php if (empty($messages)): ?>
            <div class="chat-empty">
                No messages yet. Start the conversation here with ToolShare Support.
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <?php $isSent = $message['sender_type'] === 'user'; ?>
                <div class="msg-row <?= $isSent ? 'sent-row' : 'received-row' ?>">
                    <div class="msg <?= $isSent ? 'sent' : 'received' ?>">
                        <?= nl2br(htmlspecialchars((string)$message['message_text'])) ?>
                    </div>
                    <div class="msg-time"><?= date('M d, Y h:i A', strtotime((string)$message['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="chat-error"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <?php if ((string)$ticket['status'] === 'resolved'): ?>
        <div class="chat-error" style="margin:0; border-top:1px solid #ddd; border-radius:0; background:#eff6ff; color:#1e3a8a; border-left:none; border-right:none; border-bottom:none;">
            This support ticket is closed. No further replies can be sent.
        </div>
    <?php else: ?>
        <form class="chat-input" method="POST">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
            <input type="text" name="message" placeholder="Type a message..." autocomplete="off" maxlength="2000" required>
            <button type="submit">Send</button>
        </form>
    <?php endif; ?>
</div>

<script>
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
<?php if (!$isEmbed): ?>
    <?php toolshare_render_chrome_scripts(); ?>
<?php endif; ?>
</body>
</html>
