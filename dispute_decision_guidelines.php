<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/admin_layout.php';

toolshare_require_admin();
toolshare_admin_render_layout_start($pdo, 'Dispute Guidelines', 'disputes', 'Professional decision framework for handling rental damage and return disputes.');
?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Dispute Decision Guidelines</h2>
        <a href="dispute_decision_guidelines_pdf.php" class="admin-btn">Download PDF</a>
    </div>
    <div class="admin-grid cols-2">
        <div class="admin-note">
            <strong style="display:block; margin-bottom:8px; color:#15324a;">Purpose</strong>
            These guidelines help the operations team make neutral, evidence-based decisions instead of guessing or relying only on the claim itself.
        </div>
        <div class="admin-note">
            <strong style="display:block; margin-bottom:8px; color:#15324a;">Professional Standard</strong>
            Every final decision should answer three questions: what happened, what evidence supports it, and why the selected refund or deduction amount is fair.
        </div>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Recommended Review Process</h2>
    </div>
    <div class="admin-grid">
        <div class="admin-note">1. Confirm the booking, tool, renter, owner, deposit amount, and return timeline.</div>
        <div class="admin-note">2. Review the dispute reason, issue description, inspection notes, uploaded images, and any related messages.</div>
        <div class="admin-note">3. Decide whether the issue looks like normal wear, minor damage, major damage, missing parts, late return, or an unsupported claim.</div>
        <div class="admin-note">4. Match the decision to available proof and keep the amount proportional to the actual loss.</div>
        <div class="admin-note">5. Record a resolution summary before closing the case so the decision is easy to explain later.</div>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Decision Meanings</h2>
    </div>
    <div class="admin-grid cols-2">
        <div class="admin-note">
            <strong style="display:block; margin-bottom:8px; color:#15324a;">Full Refund</strong>
            Use when the evidence is weak, the damage is not proven, the issue looks like normal wear, or the owner has not shown a fair basis for deduction.
        </div>
        <div class="admin-note">
            <strong style="display:block; margin-bottom:8px; color:#15324a;">Partial Deduction</strong>
            Use when the renter is responsible for some loss or damage, but keeping the full deposit would be excessive.
        </div>
        <div class="admin-note">
            <strong style="display:block; margin-bottom:8px; color:#15324a;">Full Forfeit</strong>
            Use when strong evidence shows severe damage, major loss, or a return condition that clearly justifies holding the full deposit.
        </div>
        <div class="admin-note">
            <strong style="display:block; margin-bottom:8px; color:#15324a;">Rejected Dispute</strong>
            Use when the dispute itself should be closed as invalid, duplicate, unsupported, or in the renter’s favor.
        </div>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Required Resolution Note</h2>
    </div>
    <div class="admin-note">
        The admin note should briefly state:
        <br><br>
        - what evidence was reviewed
        <br>
        - what conclusion was reached
        <br>
        - why the chosen refund or deduction amount is fair
        <br><br>
        Example:
        <br>
        “Image evidence confirms visible breakage beyond normal wear. Inspection notes support renter responsibility. A partial deduction of $40 was applied to reflect repair impact, and the remaining deposit was refunded.”
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
