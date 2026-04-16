<?php
require_once __DIR__ . '/booking_charges_bootstrap.php';

if (!function_exists('toolshare_receipt_money')) {
    function toolshare_receipt_money(float $value): string
    {
        return '$' . number_format($value, 2);
    }

    function toolshare_receipt_status_label(?string $status): string
    {
        $status = trim((string)$status);
        return $status === '' ? 'Unknown' : ucwords(str_replace('_', ' ', $status));
    }

    function toolshare_receipt_payment_status(array $booking): string
    {
        return in_array((string)$booking['status'], ['paid', 'completed'], true) ? 'Paid' : ucfirst((string)$booking['status']);
    }

    function toolshare_receipt_payout_status(array $booking): string
    {
        return (string)$booking['status'] === 'completed' ? 'Released' : 'Pending Release';
    }

    function toolshare_receipt_renter_final_total(array $receipt): float
    {
        return round((float)$receipt['total_paid_by_renter'] - (float)$receipt['deposit_refund_amount'], 2);
    }

    function toolshare_receipt_owner_final_settlement(array $receipt): float
    {
        return round((float)$receipt['owner_net_payout'] + (float)$receipt['deposit_deduction_amount'], 2);
    }

    function toolshare_receipt_fetch_booking(PDO $pdo, int $bookingId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                b.*,
                t.title AS tool_title,
                renter.full_name AS renter_name,
                renter.email AS renter_email,
                owner.full_name AS owner_name,
                owner.email AS owner_email
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            JOIN users renter ON b.renter_id = renter.id
            JOIN users owner ON b.owner_id = owner.id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        return $booking ?: null;
    }

    function toolshare_receipt_calculate_snapshot(array $booking, array $existing = []): array
    {
        $subtotal = round((float)($booking['total_price'] ?? 0), 2);
        $renterPercent = isset($existing['renter_platform_fee_percent']) ? (float)$existing['renter_platform_fee_percent'] : 3.00;
        $ownerPercent = isset($existing['owner_platform_fee_percent']) ? (float)$existing['owner_platform_fee_percent'] : 3.00;
        $deposit = round((float)($booking['security_deposit'] ?? 0), 2);
        $taxAmount = round((float)($existing['tax_amount'] ?? 0), 2);
        $renterFee = round($subtotal * ($renterPercent / 100), 2);
        $ownerFee = round($subtotal * ($ownerPercent / 100), 2);
        $totalPaid = round($subtotal + $renterFee + $deposit + $taxAmount, 2);
        $ownerNet = round($subtotal - $ownerFee, 2);

        $depositStatus = (string)($booking['deposit_status'] ?? 'held');
        $depositRefund = round((float)($booking['deposit_refund_amount'] ?? 0), 2);
        $depositDeduction = 0.00;
        if (in_array($depositStatus, ['full_refund', 'partial_refund', 'forfeited'], true)) {
            $depositDeduction = round(max(0, $deposit - $depositRefund), 2);
        }

        return [
            'rental_subtotal' => $subtotal,
            'renter_platform_fee_percent' => $renterPercent,
            'renter_platform_fee_amount' => $renterFee,
            'owner_platform_fee_percent' => $ownerPercent,
            'owner_platform_fee_amount' => $ownerFee,
            'security_deposit' => $deposit,
            'tax_amount' => $taxAmount,
            'total_paid_by_renter' => $totalPaid,
            'owner_net_payout' => $ownerNet,
            'deposit_refund_amount' => $depositRefund,
            'deposit_deduction_amount' => $depositDeduction,
        ];
    }

    function toolshare_sync_booking_charges(PDO $pdo, int $bookingId): void
    {
        toolshare_bootstrap_booking_charges($pdo);

        $booking = toolshare_receipt_fetch_booking($pdo, $bookingId);
        if (!$booking) {
            return;
        }

        $existingStmt = $pdo->prepare("SELECT * FROM booking_charges WHERE booking_id = ? LIMIT 1");
        $existingStmt->execute([$bookingId]);
        $existing = $existingStmt->fetch() ?: [];
        $snapshot = toolshare_receipt_calculate_snapshot($booking, $existing);

        $stmt = $pdo->prepare("
            INSERT INTO booking_charges (
                booking_id,
                rental_subtotal,
                renter_platform_fee_percent,
                renter_platform_fee_amount,
                owner_platform_fee_percent,
                owner_platform_fee_amount,
                security_deposit,
                tax_amount,
                total_paid_by_renter,
                owner_net_payout,
                deposit_refund_amount,
                deposit_deduction_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                rental_subtotal = VALUES(rental_subtotal),
                renter_platform_fee_percent = VALUES(renter_platform_fee_percent),
                renter_platform_fee_amount = VALUES(renter_platform_fee_amount),
                owner_platform_fee_percent = VALUES(owner_platform_fee_percent),
                owner_platform_fee_amount = VALUES(owner_platform_fee_amount),
                security_deposit = VALUES(security_deposit),
                tax_amount = VALUES(tax_amount),
                total_paid_by_renter = VALUES(total_paid_by_renter),
                owner_net_payout = VALUES(owner_net_payout),
                deposit_refund_amount = VALUES(deposit_refund_amount),
                deposit_deduction_amount = VALUES(deposit_deduction_amount)
        ");
        $stmt->execute([
            $bookingId,
            $snapshot['rental_subtotal'],
            $snapshot['renter_platform_fee_percent'],
            $snapshot['renter_platform_fee_amount'],
            $snapshot['owner_platform_fee_percent'],
            $snapshot['owner_platform_fee_amount'],
            $snapshot['security_deposit'],
            $snapshot['tax_amount'],
            $snapshot['total_paid_by_renter'],
            $snapshot['owner_net_payout'],
            $snapshot['deposit_refund_amount'],
            $snapshot['deposit_deduction_amount'],
        ]);
    }

    function toolshare_receipt_fetch_snapshot(PDO $pdo, int $bookingId): ?array
    {
        toolshare_sync_booking_charges($pdo, $bookingId);

        $stmt = $pdo->prepare("
            SELECT
                bc.*,
                b.status,
                b.pick_up_datetime,
                b.drop_off_datetime,
                b.deposit_status,
                b.returned_at,
                t.title AS tool_title,
                renter.id AS renter_id,
                renter.full_name AS renter_name,
                renter.email AS renter_email,
                owner.id AS owner_id,
                owner.full_name AS owner_name,
                owner.email AS owner_email
            FROM booking_charges bc
            JOIN bookings b ON bc.booking_id = b.id
            JOIN tools t ON b.tool_id = t.id
            JOIN users renter ON b.renter_id = renter.id
            JOIN users owner ON b.owner_id = owner.id
            WHERE bc.booking_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $snapshot = $stmt->fetch();

        return $snapshot ?: null;
    }

    function toolshare_renter_receipt(PDO $pdo, int $bookingId, int $viewerId): ?array
    {
        $receipt = toolshare_receipt_fetch_snapshot($pdo, $bookingId);
        if (
            !$receipt
            || (int)$receipt['renter_id'] !== $viewerId
            || !in_array((string)$receipt['status'], ['paid', 'completed'], true)
        ) {
            return null;
        }

        return $receipt;
    }

    function toolshare_owner_receipt(PDO $pdo, int $bookingId, int $viewerId): ?array
    {
        $receipt = toolshare_receipt_fetch_snapshot($pdo, $bookingId);
        if (
            !$receipt
            || (int)$receipt['owner_id'] !== $viewerId
            || !in_array((string)$receipt['status'], ['paid', 'completed'], true)
        ) {
            return null;
        }

        return $receipt;
    }

    function toolshare_owner_statement_receipt(PDO $pdo, int $bookingId, int $viewerId, ?string $viewerRole = null): ?array
    {
        $receipt = toolshare_receipt_fetch_snapshot($pdo, $bookingId);
        if (!$receipt || !in_array((string)$receipt['status'], ['paid', 'completed'], true)) {
            return null;
        }

        $viewerRole = $viewerRole !== null ? strtolower(trim($viewerRole)) : 'user';
        if (in_array($viewerRole, ['owner_admin', 'admin'], true)) {
            return $receipt;
        }

        return (int)$receipt['owner_id'] === $viewerId ? $receipt : null;
    }

    function toolshare_receipt_layout(string $title, string $body, bool $forPdf = false): string
    {
        $font = $forPdf ? 'Helvetica, Arial, sans-serif' : 'Inter, sans-serif';
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'
            . htmlspecialchars($title)
            . '</title><style>
                body{font-family:' . $font . ';background:#ffffff;color:#1e293b;margin:0;padding:12px;}
                .sheet{max-width:940px;margin:0 auto;background:#fff;border:none;border-radius:0;padding:22px 24px;box-shadow:none;}
                .header-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:22px;align-items:start;padding-bottom:18px;border-bottom:1px solid #dbe4f3;margin-bottom:18px;}
                .brand-stack{display:flex;flex-direction:column;gap:6px;}
                .brand-mark{font-size:14px;font-weight:900;letter-spacing:0.14em;text-transform:uppercase;color:#335da8;}
                .brand-sub{font-size:13px;color:#64748b;line-height:1.5;}
                .receipt-panel{text-align:right;}
                .receipt-title{font-size:40px;line-height:0.95;letter-spacing:-0.05em;font-weight:900;color:#335da8;margin:0 0 10px;}
                .meta-table{margin-left:auto;width:100%;max-width:320px;border-collapse:collapse;}
                .meta-table td{padding:3px 0;font-size:13px;}
                .meta-table .meta-label{font-weight:800;color:#335da8;padding-right:14px;}
                .party-heading{font-size:12px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#335da8;margin-bottom:8px;}
                .party-grid{width:100%;margin-bottom:14px;border-collapse:collapse;}
                .party-grid td{vertical-align:top;width:50%;padding:0 12px 0 0;}
                .party-card{min-height:72px;}
                .party-name{font-size:15px;font-weight:800;color:#12243a;margin:0 0 4px;}
                .party-line{font-size:13px;line-height:1.35;color:#24384d;margin:0;}
                .items-table{width:100%;border-collapse:collapse;margin:8px 0 14px;}
                .items-table th,.items-table td{border:1px solid #e5e7eb;padding:8px 9px;font-size:12.5px;}
                .items-table th{color:#335da8;font-weight:800;text-align:left;border-bottom:2px solid #d1d5db;background:#fcfcfd;}
                .items-table td.number,.items-table th.number{text-align:right;}
                .bottom-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:14px;align-items:start;}
                .terms-title{font-size:12px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#335da8;margin:0 0 6px;}
                .terms-copy{font-size:12.5px;line-height:1.55;color:#334155;margin:0 0 8px;}
                .note{margin-top:8px;padding:0;background:transparent;color:#475569;font-size:12.5px;line-height:1.5;border:none;font-weight:600;}
                .alert{margin-top:8px;padding:0;background:transparent;color:#9a3412;font-size:12.5px;line-height:1.5;border:none;font-weight:700;}
                .totals-table{width:100%;border-collapse:collapse;}
                .totals-table td{padding:6px 8px;font-size:12.5px;border-bottom:1px solid #e5e7eb;}
                .totals-table .total-row td{font-weight:900;color:#335da8;background:#f8fafc;border-top:2px solid #d1d5db;border-bottom:2px solid #d1d5db;font-size:15px;}
                .footer{margin-top:14px;padding-top:10px;border-top:1px solid #e5e7eb;color:#475569;font-size:10.5px;line-height:1.5;}
                .footer-table{width:100%;border-collapse:collapse;}
                .footer-table td{width:33.33%;font-size:11px;color:#334155;}
                .footer-table td.center{text-align:center;}
                .footer-table td.right{text-align:right;}
                .actions{margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;}
                .btn{display:inline-block;text-decoration:none;background:#15324a;color:#fff;padding:12px 18px;border-radius:999px;font-weight:700;}
                .btn.alt{background:#fff;color:#15324a;border:1px solid #cbd5e1;}
                @media (max-width:720px){.sheet{padding:18px;}.header-grid,.bottom-grid{grid-template-columns:1fr;}.receipt-panel{text-align:left;}.receipt-title{font-size:34px;}.party-grid td{display:block;width:100%;padding-right:0;padding-bottom:12px;}.meta-table{margin-left:0;max-width:none;}.footer-table td{display:block;width:100%;text-align:left;padding-bottom:6px;}}
            </style></head><body><div class="sheet">' . $body . '</div></body></html>';
    }

    function toolshare_receipt_dompdf_options(): \Dompdf\Options
    {
        $projectRoot = dirname(__DIR__);
        $tmpRoot = trim((string)(getenv('TOOLSHARE_TMP_ROOT') ?: ''));
        if ($tmpRoot === '') {
            $tmpRoot = $projectRoot . '/tmp';
        }
        $dompdfRoot = $tmpRoot . '/dompdf';
        $fontDir = $dompdfRoot . '/fonts';
        $fontCache = $dompdfRoot . '/font-cache';

        foreach ([$tmpRoot, $dompdfRoot, $fontDir, $fontCache] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');
        $options->setTempDir($dompdfRoot);
        $options->setFontDir($fontDir);
        $options->setFontCache($fontCache);
        $options->setChroot($projectRoot);

        return $options;
    }

    function toolshare_render_renter_receipt_html(array $receipt, bool $forPdf = false): string
    {
        $issuedOn = date('n/j/Y');
        $rentalStart = date('M d, Y h:i A', strtotime((string)$receipt['pick_up_datetime']));
        $rentalEnd = date('M d, Y h:i A', strtotime((string)$receipt['drop_off_datetime']));
        $finalTotal = toolshare_receipt_renter_final_total($receipt);
        $lineItems = '
            <tr>
                <td>1</td>
                <td>Rental period for ' . htmlspecialchars((string)$receipt['tool_title']) . '<br><span style="color:#64748b;font-size:12px;">' . htmlspecialchars($rentalStart) . ' to ' . htmlspecialchars($rentalEnd) . '</span></td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['rental_subtotal']) . '</td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['rental_subtotal']) . '</td>
            </tr>
            <tr>
                <td>1</td>
                <td>Platform service fee for booking support and payment processing</td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['renter_platform_fee_amount']) . '</td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['renter_platform_fee_amount']) . '</td>
            </tr>
            <tr>
                <td>1</td>
                <td>Refundable security deposit held until return review</td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['security_deposit']) . '</td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['security_deposit']) . '</td>
            </tr>';
        $body = '
            <div class="header-grid">
                <div class="brand-stack">
                    <span class="brand-mark">ToolShare</span>
                    <span class="brand-sub">Online rental platform</span>
                    <span class="brand-sub">support@toolshare.local</span>
                    <span class="brand-sub">Marketplace reference generated automatically</span>
                </div>
                <div class="receipt-panel">
                    <div class="receipt-title">RECEIPT</div>
                    <table class="meta-table">
                        <tr><td class="meta-label">Receipt #:</td><td>RTR-' . (int)$receipt['booking_id'] . '</td></tr>
                        <tr><td class="meta-label">Receipt Date:</td><td>' . htmlspecialchars($issuedOn) . '</td></tr>
                        <tr><td class="meta-label">Status:</td><td>' . htmlspecialchars(toolshare_receipt_payment_status($receipt)) . '</td></tr>
                    </table>
                </div>
            </div>
            <table class="party-grid">
                <tr>
                    <td>
                        <div class="party-card">
                            <div class="party-heading">From</div>
                            <p class="party-name">ToolShare Marketplace</p>
                            <p class="party-line">Digital renter receipt</p>
                            <p class="party-line">Owner: ' . htmlspecialchars((string)$receipt['owner_name']) . '</p>
                        </div>
                    </td>
                    <td>
                        <div class="party-card">
                            <div class="party-heading">To</div>
                            <p class="party-name">' . htmlspecialchars((string)$receipt['renter_name']) . '</p>
                            <p class="party-line">' . htmlspecialchars((string)$receipt['renter_email']) . '</p>
                            <p class="party-line">' . htmlspecialchars((string)$receipt['tool_title']) . '</p>
                        </div>
                    </td>
                </tr>
            </table>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:70px;">Qty</th>
                        <th>Description</th>
                        <th class="number" style="width:150px;">Unit Price</th>
                        <th class="number" style="width:150px;">Amount</th>
                    </tr>
                </thead>
                <tbody>' . $lineItems . '</tbody>
            </table>
            <div class="bottom-grid">
                <div>
                    <div class="terms-title">Terms and notes</div>
                    <p class="terms-copy">Rental window: ' . htmlspecialchars($rentalStart) . ' to ' . htmlspecialchars($rentalEnd) . '.</p>
                    <p class="terms-copy">Deposit outcome: ' . htmlspecialchars(toolshare_receipt_status_label((string)$receipt['deposit_status'])) . '.</p>
                    <div class="note">The security deposit is collected at checkout and may be refunded fully or partially after the return review is completed.</div>
                </div>
                <div>
                    <table class="totals-table">
                        <tr><td>Subtotal</td><td style="text-align:right;">' . toolshare_receipt_money((float)$receipt['rental_subtotal']) . '</td></tr>
                        <tr><td>Platform Fee</td><td style="text-align:right;">' . toolshare_receipt_money((float)$receipt['renter_platform_fee_amount']) . '</td></tr>
                        <tr><td>Security Deposit</td><td style="text-align:right;">' . toolshare_receipt_money((float)$receipt['security_deposit']) . '</td></tr>
                        <tr><td>Deposit Refunded</td><td style="text-align:right;">-' . toolshare_receipt_money((float)$receipt['deposit_refund_amount']) . '</td></tr>
                        <tr><td>Deposit Deducted</td><td style="text-align:right;">' . toolshare_receipt_money((float)$receipt['deposit_deduction_amount']) . '</td></tr>
                        <tr class="total-row"><td>Final Cost</td><td style="text-align:right;">' . toolshare_receipt_money($finalTotal) . '</td></tr>
                    </table>
                </div>
            </div>
            <div class="footer">
                <table class="footer-table">
                    <tr>
                        <td>Booking #' . (int)$receipt['booking_id'] . '</td>
                        <td class="center">' . htmlspecialchars((string)$receipt['owner_name']) . ' · Owner</td>
                        <td class="right">toolshare.local</td>
                    </tr>
                </table>
            </div>';

        return toolshare_receipt_layout('Renter Receipt', $body, $forPdf);
    }

    function toolshare_render_owner_receipt_html(array $receipt, bool $forPdf = false): string
    {
        $issuedOn = date('n/j/Y');
        $rentalStart = date('M d, Y h:i A', strtotime((string)$receipt['pick_up_datetime']));
        $rentalEnd = date('M d, Y h:i A', strtotime((string)$receipt['drop_off_datetime']));
        $depositNote = (float)$receipt['deposit_deduction_amount'] > 0
            ? 'Part of the security deposit was awarded to the lender after dispute review and is included below.'
            : 'The security deposit was refunded to the renter, so it is not included in the lender earnings total.';
        $finalSettlement = toolshare_receipt_owner_final_settlement($receipt);
        $lineItems = '
            <tr>
                <td>1</td>
                <td>Rental earnings for ' . htmlspecialchars((string)$receipt['tool_title']) . '<br><span style="color:#64748b;font-size:12px;">' . htmlspecialchars($rentalStart) . ' to ' . htmlspecialchars($rentalEnd) . '</span></td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['rental_subtotal']) . '</td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['rental_subtotal']) . '</td>
            </tr>
            <tr>
                <td>1</td>
                <td>Platform fee withheld from owner payout</td>
                <td class="number">-' . toolshare_receipt_money((float)$receipt['owner_platform_fee_amount']) . '</td>
                <td class="number">-' . toolshare_receipt_money((float)$receipt['owner_platform_fee_amount']) . '</td>
            </tr>
            <tr>
                <td>1</td>
                <td>Deposit amount awarded after review</td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['deposit_deduction_amount']) . '</td>
                <td class="number">' . toolshare_receipt_money((float)$receipt['deposit_deduction_amount']) . '</td>
            </tr>';

        $body = '
            <div class="header-grid">
                <div class="brand-stack">
                    <span class="brand-mark">ToolShare</span>
                    <span class="brand-sub">Owner settlement statement</span>
                    <span class="brand-sub">support@toolshare.local</span>
                    <span class="brand-sub">Prepared for completed rental settlements</span>
                </div>
                <div class="receipt-panel">
                    <div class="receipt-title">RECEIPT</div>
                    <table class="meta-table">
                        <tr><td class="meta-label">Statement #:</td><td>LND-' . (int)$receipt['booking_id'] . '</td></tr>
                        <tr><td class="meta-label">Receipt Date:</td><td>' . htmlspecialchars($issuedOn) . '</td></tr>
                        <tr><td class="meta-label">Status:</td><td>' . htmlspecialchars(toolshare_receipt_payout_status($receipt)) . '</td></tr>
                    </table>
                </div>
            </div>
            <table class="party-grid">
                <tr>
                    <td>
                        <div class="party-card">
                            <div class="party-heading">From</div>
                            <p class="party-name">ToolShare Marketplace</p>
                            <p class="party-line">Renter: ' . htmlspecialchars((string)$receipt['renter_name']) . '</p>
                            <p class="party-line">Tool: ' . htmlspecialchars((string)$receipt['tool_title']) . '</p>
                        </div>
                    </td>
                    <td>
                        <div class="party-card">
                            <div class="party-heading">To</div>
                            <p class="party-name">' . htmlspecialchars((string)$receipt['owner_name']) . '</p>
                            <p class="party-line">' . htmlspecialchars((string)$receipt['owner_email']) . '</p>
                            <p class="party-line">Owner settlement copy</p>
                        </div>
                    </td>
                </tr>
            </table>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:70px;">Qty</th>
                        <th>Description</th>
                        <th class="number" style="width:150px;">Unit Price</th>
                        <th class="number" style="width:150px;">Amount</th>
                    </tr>
                </thead>
                <tbody>' . $lineItems . '</tbody>
            </table>
            <div class="bottom-grid">
                <div>
                    <div class="terms-title">Terms and notes</div>
                    <p class="terms-copy">Rental window: ' . htmlspecialchars($rentalStart) . ' to ' . htmlspecialchars($rentalEnd) . '.</p>
                    <p class="terms-copy">Deposit outcome: ' . htmlspecialchars(toolshare_receipt_status_label((string)$receipt['deposit_status'])) . '.</p>
                    <div class="note">Security deposit collected: ' . toolshare_receipt_money((float)$receipt['security_deposit']) . '. Refunded to renter: ' . toolshare_receipt_money((float)$receipt['deposit_refund_amount']) . '.</div>
                    <div class="alert">' . htmlspecialchars($depositNote) . '</div>
                </div>
                <div>
                    <table class="totals-table">
                        <tr><td>Rental Gross</td><td style="text-align:right;">' . toolshare_receipt_money((float)$receipt['rental_subtotal']) . '</td></tr>
                        <tr><td>Platform Fee (' . number_format((float)$receipt['owner_platform_fee_percent'], 2) . '%)</td><td style="text-align:right;">-' . toolshare_receipt_money((float)$receipt['owner_platform_fee_amount']) . '</td></tr>
                        <tr><td>Rental Earnings After Fee</td><td style="text-align:right;">' . toolshare_receipt_money((float)$receipt['owner_net_payout']) . '</td></tr>
                        <tr><td>Deposit Awarded</td><td style="text-align:right;">' . toolshare_receipt_money((float)$receipt['deposit_deduction_amount']) . '</td></tr>
                        <tr class="total-row"><td>Total Earnings Received</td><td style="text-align:right;">' . toolshare_receipt_money($finalSettlement) . '</td></tr>
                    </table>
                </div>
            </div>
            <div class="footer">
                <table class="footer-table">
                    <tr>
                        <td>Booking #' . (int)$receipt['booking_id'] . '</td>
                        <td class="center">' . htmlspecialchars((string)$receipt['renter_name']) . ' · Renter</td>
                        <td class="right">toolshare.local</td>
                    </tr>
                </table>
            </div>';

        return toolshare_receipt_layout('Lender Earnings Statement', $body, $forPdf);
    }
}
