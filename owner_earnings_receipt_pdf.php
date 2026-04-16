<?php
ob_start();
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/receipt_helpers.php';
require 'vendor/autoload.php';

try {
    toolshare_require_signed_in();

    if (!class_exists(\Dompdf\Dompdf::class)) {
        throw new RuntimeException('Dompdf is not installed. Run composer require dompdf/dompdf.');
    }

    $bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
    if ($bookingId <= 0) {
        throw new RuntimeException('Invalid booking.');
    }

    $receipt = toolshare_owner_statement_receipt($pdo, $bookingId, (int)$_SESSION['user_id'], toolshare_current_role());
    if (!$receipt) {
        throw new RuntimeException('Settlement statement not found or access denied.');
    }

    $dompdf = new \Dompdf\Dompdf(toolshare_receipt_dompdf_options());
    $dompdf->loadHtml(toolshare_render_owner_receipt_html($receipt, true));
    $dompdf->setPaper('A4');
    $dompdf->render();
    $pdf = $dompdf->output();

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="toolshare-lender-earnings-statement-' . $bookingId . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
} catch (Throwable $e) {
    error_log('[owner_earnings_receipt_pdf] ' . $e->getMessage());
    @file_put_contents(__DIR__ . '/receipt_debug.log', '[' . date('Y-m-d H:i:s') . '] [owner_earnings_receipt_pdf] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo 'Unable to generate lender earnings statement right now.';
    exit;
}
