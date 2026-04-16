<?php
require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/receipt_helpers.php';

if (!function_exists('toolshare_mail_safe_dispatch')) {
    function toolshare_mail_safe_dispatch(callable $callback): void
    {
        if (!toolshare_mail_is_ready()) {
            return;
        }

        try {
            $callback();
        } catch (Throwable $e) {
            error_log('ToolShare mail delivery skipped: ' . $e->getMessage());
        }
    }

    function toolshare_marketplace_url(string $path): string
    {
        return rtrim(toolshare_mail_app_url(), '/') . '/' . ltrim($path, '/');
    }

    function toolshare_booking_status_label(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'pending' => 'Pending Owner Review',
            'confirmed' => 'Approved',
            'paid' => 'Paid',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    function toolshare_dispute_status_label(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => 'Pending Review',
            'reviewing' => 'Under Review',
            'resolved' => 'Resolved',
            'rejected' => 'Closed Without Customer Relief',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    function toolshare_fetch_booking_mail_context(PDO $pdo, int $bookingId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.tool_id,
                b.renter_id,
                b.owner_id,
                b.status,
                b.duration_count,
                b.duration_type,
                b.total_price,
                b.security_deposit,
                b.pick_up_datetime,
                b.drop_off_datetime,
                t.title AS tool_title,
                renter.full_name AS renter_name,
                renter.email AS renter_email,
                owner.full_name AS owner_name,
                owner.email AS owner_email
            FROM bookings b
            JOIN tools t ON t.id = b.tool_id
            JOIN users renter ON renter.id = b.renter_id
            JOIN users owner ON owner.id = b.owner_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function toolshare_fetch_dispute_mail_context(PDO $pdo, int $disputeId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                d.*,
                b.pick_up_datetime,
                b.drop_off_datetime,
                b.total_price,
                b.security_deposit,
                t.title AS tool_title,
                renter.full_name AS renter_name,
                renter.email AS renter_email,
                owner.full_name AS owner_name,
                owner.email AS owner_email
            FROM disputes d
            JOIN bookings b ON b.id = d.booking_id
            JOIN tools t ON t.id = d.tool_id
            JOIN users renter ON renter.id = d.renter_id
            JOIN users owner ON owner.id = d.owner_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $stmt->execute([$disputeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function toolshare_fetch_support_ticket_mail_context(PDO $pdo, int $ticketId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, user_id, name, email, status, created_at, updated_at
            FROM support_tickets
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$ticketId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function toolshare_fetch_message_mail_context(PDO $pdo, int $messageId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                m.id,
                m.tool_id,
                m.sender_id,
                m.receiver_id,
                m.message_text,
                m.created_at,
                t.title AS tool_title,
                sender.full_name AS sender_name,
                sender.email AS sender_email,
                receiver.full_name AS receiver_name,
                receiver.email AS receiver_email
            FROM messages m
            JOIN tools t ON t.id = m.tool_id
            JOIN users sender ON sender.id = m.sender_id
            JOIN users receiver ON receiver.id = m.receiver_id
            WHERE m.id = ?
            LIMIT 1
        ");
        $stmt->execute([$messageId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function toolshare_mail_send_booking_request_notifications(PDO $pdo, int $bookingId): void
    {
        $booking = toolshare_fetch_booking_mail_context($pdo, $bookingId);
        if (!$booking) {
            return;
        }

        $details = [
            'Booking ID' => '#' . (int)$booking['id'],
            'Tool' => (string)$booking['tool_title'],
            'Pickup' => date('M d, Y h:i A', strtotime((string)$booking['pick_up_datetime'])),
            'Drop-off' => date('M d, Y h:i A', strtotime((string)$booking['drop_off_datetime'])),
            'Rental subtotal' => '$' . number_format((float)$booking['total_price'], 2),
            'Security deposit' => '$' . number_format((float)$booking['security_deposit'], 2),
        ];

        toolshare_mail_safe_dispatch(static function () use ($booking, $details): void {
            toolshare_send_templated_email(
                (string)$booking['owner_email'],
                (string)$booking['owner_name'],
                'New booking request for ' . (string)$booking['tool_title'],
                'New booking request received',
                (string)$booking['renter_name'] . ' requested to rent your tool. Review the request and respond from your dashboard.',
                $details,
                'Review Request',
                toolshare_marketplace_url('dashboard.php#incoming-requests')
            );
        });

        toolshare_mail_safe_dispatch(static function () use ($booking, $details): void {
            toolshare_send_templated_email(
                (string)$booking['renter_email'],
                (string)$booking['renter_name'],
                'Your ToolShare booking request is pending',
                'Booking request submitted',
                'Your request has been sent to the tool owner. We will email you again as soon as the owner approves or declines it.',
                $details,
                'Open Dashboard',
                toolshare_marketplace_url('dashboard.php')
            );
        });
    }

    function toolshare_mail_send_booking_status_update(PDO $pdo, int $bookingId, string $status): void
    {
        $booking = toolshare_fetch_booking_mail_context($pdo, $bookingId);
        if (!$booking) {
            return;
        }

        $status = strtolower(trim($status));
        $subject = $status === 'confirmed'
            ? 'Your booking was approved for ' . (string)$booking['tool_title']
            : 'Your booking was updated for ' . (string)$booking['tool_title'];
        $intro = $status === 'confirmed'
            ? 'The tool owner approved your request. Complete payment in ToolShare to activate the rental.'
            : 'Your booking status changed to ' . toolshare_booking_status_label($status) . '.';

        $details = [
            'Booking ID' => '#' . (int)$booking['id'],
            'Tool' => (string)$booking['tool_title'],
            'Status' => toolshare_booking_status_label($status),
            'Pickup' => date('M d, Y h:i A', strtotime((string)$booking['pick_up_datetime'])),
            'Drop-off' => date('M d, Y h:i A', strtotime((string)$booking['drop_off_datetime'])),
        ];

        $ctaUrl = $status === 'confirmed'
            ? toolshare_marketplace_url('checkout.php?booking_id=' . (int)$booking['id'])
            : toolshare_marketplace_url('dashboard.php');
        $ctaLabel = $status === 'confirmed' ? 'Complete Payment' : 'Open Dashboard';

        toolshare_mail_safe_dispatch(static function () use ($booking, $subject, $intro, $details, $ctaLabel, $ctaUrl): void {
            toolshare_send_templated_email(
                (string)$booking['renter_email'],
                (string)$booking['renter_name'],
                $subject,
                'Booking update',
                $intro,
                $details,
                $ctaLabel,
                $ctaUrl
            );
        });
    }

    function toolshare_mail_send_payment_receipt_notifications(PDO $pdo, int $bookingId): void
    {
        $receipt = toolshare_receipt_fetch_snapshot($pdo, $bookingId);
        if (!$receipt) {
            return;
        }

        $renterDetails = [
            'Booking ID' => '#' . (int)$receipt['booking_id'],
            'Tool' => (string)$receipt['tool_title'],
            'Rental subtotal' => '$' . number_format((float)$receipt['rental_subtotal'], 2),
            'Platform fee' => '$' . number_format((float)$receipt['renter_platform_fee_amount'], 2),
            'Security deposit' => '$' . number_format((float)$receipt['security_deposit'], 2),
            'Total paid' => '$' . number_format((float)$receipt['total_paid_by_renter'], 2),
        ];

        toolshare_mail_safe_dispatch(static function () use ($receipt, $renterDetails): void {
            toolshare_send_templated_email(
                (string)$receipt['renter_email'],
                (string)$receipt['renter_name'],
                'Payment confirmed for ' . (string)$receipt['tool_title'],
                'Payment successful',
                'Your ToolShare payment is confirmed and your rental is now active. Your receipt is available from the links below.',
                $renterDetails,
                'View Receipt',
                toolshare_marketplace_url('renter_receipt.php?booking_id=' . (int)$receipt['booking_id']),
                [
                    'You can also download the PDF receipt after signing in: ' . toolshare_marketplace_url('renter_receipt_pdf.php?booking_id=' . (int)$receipt['booking_id']),
                ]
            );
        });

        $ownerDetails = [
            'Booking ID' => '#' . (int)$receipt['booking_id'],
            'Tool' => (string)$receipt['tool_title'],
            'Renter' => (string)$receipt['renter_name'],
            'Pickup' => date('M d, Y h:i A', strtotime((string)$receipt['pick_up_datetime'])),
            'Drop-off' => date('M d, Y h:i A', strtotime((string)$receipt['drop_off_datetime'])),
        ];

        toolshare_mail_safe_dispatch(static function () use ($receipt, $ownerDetails): void {
            toolshare_send_templated_email(
                (string)$receipt['owner_email'],
                (string)$receipt['owner_name'],
                'Rental confirmed for ' . (string)$receipt['tool_title'],
                'Rental paid and confirmed',
                (string)$receipt['renter_name'] . ' completed payment. You can now coordinate pickup through ToolShare chat.',
                $ownerDetails,
                'Open Dashboard',
                toolshare_marketplace_url('dashboard.php')
            );
        });
    }

    function toolshare_mail_send_return_requested_notification(PDO $pdo, int $bookingId): void
    {
        $booking = toolshare_fetch_booking_mail_context($pdo, $bookingId);
        if (!$booking) {
            return;
        }

        toolshare_mail_safe_dispatch(static function () use ($booking): void {
            toolshare_send_templated_email(
                (string)$booking['owner_email'],
                (string)$booking['owner_name'],
                'Return request received for ' . (string)$booking['tool_title'],
                'Return ready for review',
                (string)$booking['renter_name'] . ' marked the rental as returned. Review the return and approve it from your dashboard.',
                [
                    'Booking ID' => '#' . (int)$booking['id'],
                    'Tool' => (string)$booking['tool_title'],
                    'Renter' => (string)$booking['renter_name'],
                ],
                'Review Return',
                toolshare_marketplace_url('dashboard.php')
            );
        });
    }

    function toolshare_mail_send_return_approved_notification(PDO $pdo, int $bookingId): void
    {
        $receipt = toolshare_receipt_fetch_snapshot($pdo, $bookingId);
        if (!$receipt) {
            return;
        }

        toolshare_mail_safe_dispatch(static function () use ($receipt): void {
            toolshare_send_templated_email(
                (string)$receipt['renter_email'],
                (string)$receipt['renter_name'],
                'Return approved for ' . (string)$receipt['tool_title'],
                'Return approved',
                'The owner approved your return review. Your deposit is now marked for full refund in ToolShare.',
                [
                    'Booking ID' => '#' . (int)$receipt['booking_id'],
                    'Tool' => (string)$receipt['tool_title'],
                    'Deposit refund amount' => '$' . number_format((float)$receipt['deposit_refund_amount'], 2),
                ],
                'Open Dashboard',
                toolshare_marketplace_url('dashboard.php')
            );
        });
    }

    function toolshare_mail_send_extension_requested_notification(PDO $pdo, int $bookingId, string $requestedDropoffDatetime): void
    {
        $booking = toolshare_fetch_booking_mail_context($pdo, $bookingId);
        if (!$booking) {
            return;
        }

        toolshare_mail_safe_dispatch(static function () use ($booking, $requestedDropoffDatetime): void {
            toolshare_send_templated_email(
                (string)$booking['owner_email'],
                (string)$booking['owner_name'],
                'Extension request for ' . (string)$booking['tool_title'],
                'Extension request received',
                (string)$booking['renter_name'] . ' asked to extend the rental window. Review the request from your dashboard.',
                [
                    'Booking ID' => '#' . (int)$booking['id'],
                    'Current drop-off' => date('M d, Y h:i A', strtotime((string)$booking['drop_off_datetime'])),
                    'Requested drop-off' => date('M d, Y h:i A', strtotime($requestedDropoffDatetime)),
                ],
                'Review Extension',
                toolshare_marketplace_url('dashboard.php')
            );
        });
    }

    function toolshare_mail_send_extension_status_notification(PDO $pdo, int $bookingId, string $status, string $dropoffDatetime): void
    {
        $booking = toolshare_fetch_booking_mail_context($pdo, $bookingId);
        if (!$booking) {
            return;
        }

        $subject = $status === 'approved'
            ? 'Extension approved for ' . (string)$booking['tool_title']
            : 'Extension update for ' . (string)$booking['tool_title'];
        $intro = $status === 'approved'
            ? 'The tool owner approved your extension request.'
            : 'Your extension request was declined.';

        toolshare_mail_safe_dispatch(static function () use ($booking, $status, $dropoffDatetime, $subject, $intro): void {
            toolshare_send_templated_email(
                (string)$booking['renter_email'],
                (string)$booking['renter_name'],
                $subject,
                'Extension request update',
                $intro,
                [
                    'Booking ID' => '#' . (int)$booking['id'],
                    'Tool' => (string)$booking['tool_title'],
                    'Decision' => ucfirst($status),
                    'Requested drop-off' => date('M d, Y h:i A', strtotime($dropoffDatetime)),
                ],
                'Open Dashboard',
                toolshare_marketplace_url('dashboard.php')
            );
        });
    }

    function toolshare_mail_send_dispute_created_notifications(PDO $pdo, int $disputeId): void
    {
        $dispute = toolshare_fetch_dispute_mail_context($pdo, $disputeId);
        if (!$dispute) {
            return;
        }

        $initiatedBy = (string)($dispute['initiated_by'] ?? 'owner');
        $isRenterDispute = $initiatedBy === 'renter';
        $submitterEmail = $isRenterDispute ? (string)$dispute['renter_email'] : (string)$dispute['owner_email'];
        $submitterName = $isRenterDispute ? (string)$dispute['renter_name'] : (string)$dispute['owner_name'];
        $otherEmail = $isRenterDispute ? (string)$dispute['owner_email'] : (string)$dispute['renter_email'];
        $otherName = $isRenterDispute ? (string)$dispute['owner_name'] : (string)$dispute['renter_name'];
        $otherRole = $isRenterDispute ? 'owner' : 'renter';

        $details = [
            'Dispute ID' => '#' . (int)$dispute['id'],
            'Tool' => (string)$dispute['tool_title'],
            'Reason' => (string)$dispute['reason'],
            'Status' => toolshare_dispute_status_label((string)$dispute['status']),
        ];

        toolshare_mail_safe_dispatch(static function () use ($submitterEmail, $submitterName, $details): void {
            toolshare_send_templated_email(
                $submitterEmail,
                $submitterName,
                'Your ToolShare dispute was submitted',
                'Dispute submitted',
                'Your case has been sent to operations for review. We will email you again when the review status changes or a final decision is recorded.',
                $details,
                'Open Dashboard',
                toolshare_marketplace_url('dashboard.php')
            );
        });

        toolshare_mail_safe_dispatch(static function () use ($otherEmail, $otherName, $otherRole, $dispute, $details): void {
            toolshare_send_templated_email(
                $otherEmail,
                $otherName,
                'New dispute update for ' . (string)$dispute['tool_title'],
                'A dispute was raised',
                'A ' . $otherRole . '-related dispute was opened for this booking. Operations will review the case and may contact you if more information is needed.',
                $details,
                'Open Dashboard',
                toolshare_marketplace_url('dashboard.php')
            );
        });
    }

    function toolshare_mail_send_dispute_updated_notifications(PDO $pdo, int $disputeId): void
    {
        $dispute = toolshare_fetch_dispute_mail_context($pdo, $disputeId);
        if (!$dispute) {
            return;
        }

        $details = [
            'Dispute ID' => '#' . (int)$dispute['id'],
            'Tool' => (string)$dispute['tool_title'],
            'Reason' => (string)$dispute['reason'],
            'Review status' => toolshare_dispute_status_label((string)$dispute['status']),
        ];

        if (trim((string)($dispute['resolution_summary'] ?? '')) !== '') {
            $details['Decision summary'] = trim((string)$dispute['resolution_summary']);
        }

        toolshare_mail_safe_dispatch(static function () use ($dispute, $details): void {
            toolshare_send_templated_email(
                (string)$dispute['renter_email'],
                (string)$dispute['renter_name'],
                'Dispute update for ' . (string)$dispute['tool_title'],
                'Dispute status updated',
                'Operations recorded a new update on this dispute. Open ToolShare to review the latest status and notes.',
                $details,
                'Open Dashboard',
                toolshare_marketplace_url('dashboard.php')
            );
        });

        toolshare_mail_safe_dispatch(static function () use ($dispute, $details): void {
            toolshare_send_templated_email(
                (string)$dispute['owner_email'],
                (string)$dispute['owner_name'],
                'Dispute update for ' . (string)$dispute['tool_title'],
                'Dispute status updated',
                'Operations recorded a new update on this dispute. Open ToolShare to review the latest status and notes.',
                $details,
                'Open Dashboard',
                toolshare_marketplace_url('dashboard.php')
            );
        });
    }

    function toolshare_mail_send_support_ticket_created_notification(PDO $pdo, int $ticketId): void
    {
        $ticket = toolshare_fetch_support_ticket_mail_context($pdo, $ticketId);
        if (!$ticket) {
            return;
        }

        toolshare_mail_safe_dispatch(static function () use ($ticket): void {
            toolshare_send_templated_email(
                (string)$ticket['email'],
                (string)$ticket['name'],
                'Your ToolShare support ticket is open',
                'Support ticket received',
                'ToolShare Support received your request. We will reply in the support chat and send you another email when there is a response.',
                [
                    'Ticket ID' => '#' . (int)$ticket['id'],
                    'Status' => toolshare_support_status_label((string)$ticket['status']),
                    'Opened' => date('M d, Y h:i A', strtotime((string)$ticket['created_at'])),
                ],
                'Open Support Chat',
                toolshare_marketplace_url('support_chat.php?ticket_id=' . (int)$ticket['id'])
            );
        });
    }

    function toolshare_mail_send_support_reply_notification(PDO $pdo, int $ticketId): void
    {
        $ticket = toolshare_fetch_support_ticket_mail_context($pdo, $ticketId);
        if (!$ticket) {
            return;
        }

        $subject = (string)$ticket['status'] === 'resolved'
            ? 'Your ToolShare support ticket was closed'
            : 'New reply from ToolShare Support';
        $intro = (string)$ticket['status'] === 'resolved'
            ? 'ToolShare Support replied and closed this ticket. Open the support chat to review the final response.'
            : 'ToolShare Support sent a new reply on your ticket. Open the conversation to continue.';

        toolshare_mail_safe_dispatch(static function () use ($ticket, $subject, $intro): void {
            toolshare_send_templated_email(
                (string)$ticket['email'],
                (string)$ticket['name'],
                $subject,
                'Support ticket update',
                $intro,
                [
                    'Ticket ID' => '#' . (int)$ticket['id'],
                    'Status' => toolshare_support_status_label((string)$ticket['status']),
                    'Updated' => date('M d, Y h:i A', strtotime((string)$ticket['updated_at'])),
                ],
                'Open Support Chat',
                toolshare_marketplace_url('support_chat.php?ticket_id=' . (int)$ticket['id'])
            );
        });
    }

    function toolshare_mail_send_chat_message_notification(PDO $pdo, int $messageId): void
    {
        $message = toolshare_fetch_message_mail_context($pdo, $messageId);
        if (!$message) {
            return;
        }

        $threadUnread = $pdo->prepare("
            SELECT COUNT(*)
            FROM messages
            WHERE tool_id = ?
              AND sender_id = ?
              AND receiver_id = ?
              AND is_read = 0
        ");
        $threadUnread->execute([(int)$message['tool_id'], (int)$message['sender_id'], (int)$message['receiver_id']]);
        if ((int)$threadUnread->fetchColumn() !== 1) {
            return;
        }

        $preview = trim((string)$message['message_text']);
        if (mb_strlen($preview) > 140) {
            $preview = mb_substr($preview, 0, 140) . '...';
        }

        toolshare_mail_safe_dispatch(static function () use ($message, $preview): void {
            toolshare_send_templated_email(
                (string)$message['receiver_email'],
                (string)$message['receiver_name'],
                'New ToolShare message from ' . (string)$message['sender_name'],
                'You have a new message',
                (string)$message['sender_name'] . ' sent you a new message about ' . (string)$message['tool_title'] . '.',
                [
                    'Tool' => (string)$message['tool_title'],
                    'From' => (string)$message['sender_name'],
                    'Preview' => $preview,
                ],
                'Open Chat',
                toolshare_marketplace_url('chat.php?tool_id=' . (int)$message['tool_id'] . '&receiver_id=' . (int)$message['sender_id'])
            );
        });
    }
}
