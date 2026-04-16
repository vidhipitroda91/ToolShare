<?php
ob_start();
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/receipt_helpers.php';
require 'vendor/autoload.php';

try {
    toolshare_require_admin();

    if (!class_exists(\Dompdf\Dompdf::class)) {
        throw new RuntimeException('Dompdf is not installed.');
    }

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Dispute Decision Guidelines</title><style>
        body{font-family:Helvetica,Arial,sans-serif;background:#f8fafc;color:#1e293b;margin:0;padding:28px;}
        .sheet{max-width:880px;margin:0 auto;background:#fff;border:1px solid #dbe3ee;border-radius:20px;padding:30px;}
        .header{border-bottom:2px solid #15324a;padding-bottom:16px;margin-bottom:22px;}
        .brand{font-size:28px;font-weight:900;color:#15324a;}
        .sub{font-size:14px;color:#64748b;margin-top:6px;line-height:1.6;}
        .section{margin-bottom:20px;padding:16px 18px;border:1px solid #e2e8f0;border-radius:16px;background:#fcfdff;}
        .section h2{margin:0 0 12px;color:#15324a;font-size:18px;}
        .item{margin-bottom:12px;line-height:1.7;color:#334155;}
        .item:last-child{margin-bottom:0;}
        .strong{font-weight:800;color:#15324a;}
        .footer{margin-top:22px;padding-top:12px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;text-align:center;}
    </style></head><body><div class="sheet">
        <div class="header">
            <div class="brand">TOOLSHARE</div>
            <div class="sub">Dispute Decision Guidelines for Operations Review<br>Prepared for academic presentation and project workflow documentation.</div>
        </div>
        <div class="section">
            <h2>Professional Review Standard</h2>
            <div class="item">Each decision should answer three questions: <span class="strong">what happened, what evidence supports it, and why the chosen refund or deduction amount is fair.</span></div>
        </div>
        <div class="section">
            <h2>Recommended Review Process</h2>
            <div class="item">1. Confirm the booking, tool, renter, owner, deposit amount, and return timeline.</div>
            <div class="item">2. Review the dispute reason, issue description, inspection notes, uploaded images, and related messages.</div>
            <div class="item">3. Identify whether the case is normal wear, minor damage, major damage, missing parts, late return, or unsupported.</div>
            <div class="item">4. Match the financial outcome to the strength of the evidence and the proportional loss.</div>
            <div class="item">5. Record a resolution summary before closing the case.</div>
        </div>
        <div class="section">
            <h2>Decision Meanings</h2>
            <div class="item"><span class="strong">Full Refund:</span> evidence is weak, damage is not proven, or the issue looks like normal wear.</div>
            <div class="item"><span class="strong">Partial Deduction:</span> renter responsibility is supported, but the full deposit would be excessive.</div>
            <div class="item"><span class="strong">Full Forfeit:</span> strong evidence shows severe damage, major loss, or a clearly unusable return condition.</div>
            <div class="item"><span class="strong">Rejected Dispute:</span> the case is invalid, duplicate, unsupported, or should close in the renter’s favor.</div>
        </div>
        <div class="section">
            <h2>Required Resolution Note</h2>
            <div class="item">The closing note should state what evidence was reviewed, what conclusion was reached, and why the deduction or refund amount is fair.</div>
            <div class="item"><span class="strong">Example:</span> “Image evidence confirms visible breakage beyond normal wear. Inspection notes support renter responsibility. A partial deduction of $40 was applied to reflect repair impact, and the remaining deposit was refunded.”</div>
        </div>
        <div class="footer">ToolShare dispute workflow guidance document</div>
    </div></body></html>';

    $dompdf = new \Dompdf\Dompdf(toolshare_receipt_dompdf_options());
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();
    $pdf = $dompdf->output();

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="toolshare-dispute-decision-guidelines.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'Unable to generate dispute guidelines PDF right now.';
    exit;
}
