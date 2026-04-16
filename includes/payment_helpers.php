<?php
require_once __DIR__ . '/payment_bootstrap.php';
require_once __DIR__ . '/receipt_helpers.php';

if (!function_exists('toolshare_payment_expected_total_cents')) {
    function toolshare_payment_expected_total_cents(PDO $pdo, int $bookingId): int
    {
        toolshare_sync_booking_charges($pdo, $bookingId);
        $snapshot = toolshare_receipt_fetch_snapshot($pdo, $bookingId);
        if (!$snapshot) {
            throw new RuntimeException('Unable to load booking charges snapshot.');
        }

        return (int)round(((float)$snapshot['total_paid_by_renter']) * 100);
    }

    function toolshare_payment_line_items(PDO $pdo, int $bookingId, array $booking): array
    {
        toolshare_sync_booking_charges($pdo, $bookingId);
        $snapshot = toolshare_receipt_fetch_snapshot($pdo, $bookingId);
        if (!$snapshot) {
            throw new RuntimeException('Unable to prepare payment line items.');
        }

        $items = [];
        $subtotal = (int)round(((float)$snapshot['rental_subtotal']) * 100);
        $renterFee = (int)round(((float)$snapshot['renter_platform_fee_amount']) * 100);
        $deposit = (int)round(((float)$snapshot['security_deposit']) * 100);

        if ($subtotal > 0) {
            $items[] = [
                'price_data' => [
                    'currency' => 'cad',
                    'product_data' => [
                        'name' => 'Rental: ' . $booking['title'],
                        'description' => 'Base rental amount',
                    ],
                    'unit_amount' => $subtotal,
                ],
                'quantity' => 1,
            ];
        }

        if ($renterFee > 0) {
            $items[] = [
                'price_data' => [
                    'currency' => 'cad',
                    'product_data' => [
                        'name' => 'Platform Fee',
                        'description' => 'Renter platform fee (3%)',
                    ],
                    'unit_amount' => $renterFee,
                ],
                'quantity' => 1,
            ];
        }

        if ($deposit > 0) {
            $items[] = [
                'price_data' => [
                    'currency' => 'cad',
                    'product_data' => [
                        'name' => 'Refundable Security Deposit',
                        'description' => 'Held until return review is completed',
                    ],
                    'unit_amount' => $deposit,
                ],
                'quantity' => 1,
            ];
        }

        return $items;
    }

    function toolshare_finalize_stripe_checkout_session(PDO $pdo, \Stripe\Checkout\Session $checkoutSession): array
    {
        toolshare_bootstrap_payment_fields($pdo);

        if (($checkoutSession->payment_status ?? '') !== 'paid') {
            throw new RuntimeException('Payment session is not marked paid.');
        }

        $bookingId = (int)($checkoutSession->metadata->booking_id ?? $checkoutSession->client_reference_id ?? 0);
        $renterId = (int)($checkoutSession->metadata->renter_id ?? 0);
        if ($bookingId <= 0 || $renterId <= 0) {
            throw new RuntimeException('Missing booking metadata on Stripe session.');
        }

        $stmt = $pdo->prepare("
            SELECT id, renter_id, status, stripe_checkout_session_id
            FROM bookings
            WHERE id = ? AND renter_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId, $renterId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            throw new RuntimeException('Booking not found for Stripe payment.');
        }

        $expectedTotal = toolshare_payment_expected_total_cents($pdo, $bookingId);
        $actualTotal = (int)($checkoutSession->amount_total ?? 0);
        if ($expectedTotal !== $actualTotal) {
            throw new RuntimeException('Stripe amount does not match expected booking total.');
        }

        $sessionId = (string)($checkoutSession->id ?? '');
        $paymentIntentId = is_string($checkoutSession->payment_intent ?? null) ? $checkoutSession->payment_intent : null;

        if ($booking['status'] === 'paid') {
            return [
                'booking_id' => $bookingId,
                'renter_id' => $renterId,
                'already_paid' => true,
            ];
        }

        if ($booking['status'] !== 'confirmed') {
            throw new RuntimeException('Booking is no longer eligible for payment confirmation.');
        }

        $update = $pdo->prepare("
            UPDATE bookings
            SET status = 'paid',
                stripe_checkout_session_id = ?,
                stripe_payment_intent_id = ?,
                payment_completed_at = NOW()
            WHERE id = ?
              AND renter_id = ?
              AND status = 'confirmed'
        ");
        $update->execute([$sessionId !== '' ? $sessionId : null, $paymentIntentId, $bookingId, $renterId]);
        if ($update->rowCount() < 1) {
            throw new RuntimeException('Booking payment status could not be updated.');
        }

        toolshare_sync_booking_charges($pdo, $bookingId);

        return [
            'booking_id' => $bookingId,
            'renter_id' => $renterId,
            'already_paid' => false,
        ];
    }
}
