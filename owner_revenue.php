<?php
session_start();
require 'config/db.php';
require 'includes/owner_layout.php';

$overview = toolshare_owner_overview($pdo);
$rows = toolshare_admin_fetch_earnings($pdo);
$revenueRows = toolshare_admin_chart_monthly_revenue($pdo);
$maxRevenue = 1.0;
foreach ($revenueRows as $row) {
    $maxRevenue = max($maxRevenue, (float)$row['revenue']);
}

toolshare_owner_render_layout_start($pdo, 'Revenue & Profitability', 'revenue', 'Track gross booking value, commissions, payouts, and the marketplace earning engine.');
?>

<section class="owner-card">
    <div class="owner-card-header"><h2>Financial Snapshot</h2></div>
    <div class="owner-grid cols-4">
        <div class="owner-stat"><h3>$<?= number_format($overview['gross_revenue'], 2) ?></h3><p>Gross Booking Revenue</p></div>
        <div class="owner-stat"><h3>$<?= number_format($overview['platform_earnings'], 2) ?></h3><p>Platform Commissions</p></div>
        <div class="owner-stat"><h3>$<?= number_format($overview['released_owner_payouts'], 2) ?></h3><p>Released Payouts</p></div>
        <div class="owner-stat"><h3>$<?= number_format($overview['pending_payouts'], 2) ?></h3><p>Pending Payouts</p></div>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header"><h2>Revenue Trend</h2></div>
    <div class="owner-chart-list">
        <?php if (empty($revenueRows)): ?>
            <div class="owner-note">Revenue trend will appear once bookings start flowing through the marketplace.</div>
        <?php else: ?>
            <?php foreach ($revenueRows as $row): ?>
                <?php $width = ((float)$row['revenue'] / $maxRevenue) * 100; ?>
                <div class="owner-chart-row">
                    <strong><?= htmlspecialchars((string)$row['label']) ?></strong>
                    <div class="owner-chart-track"><div class="owner-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                    <span>$<?= number_format((float)$row['revenue'], 0) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header"><h2>Commission Ledger</h2></div>
    <div class="owner-table-wrap">
        <table>
            <thead><tr><th>Booking</th><th>Tool</th><th>Status</th><th>Gross</th><th>Renter Fee</th><th>Owner Fee</th><th>Platform Total</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['booking_id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['tool_title']) ?></strong></td>
                        <td><span class="owner-badge <?= $row['status'] === 'cancelled' ? 'warning' : '' ?>"><?= htmlspecialchars(ucfirst((string)$row['status'])) ?></span></td>
                        <td>$<?= number_format((float)$row['total_price'], 2) ?></td>
                        <td>$<?= number_format((float)$row['renter_commission'], 2) ?></td>
                        <td>$<?= number_format((float)$row['owner_commission'], 2) ?></td>
                        <td>$<?= number_format((float)$row['platform_total'], 2) ?></td>
                        <td>
                            <?php if (in_array((string)$row['status'], ['paid', 'completed'], true)): ?>
                                <button type="button" data-download-url="owner_earnings_receipt_pdf.php?booking_id=<?= (int)$row['booking_id'] ?>" class="owner-btn js-direct-download" style="border:none; cursor:pointer;">Download Statement</button>
                            <?php else: ?>
                                <span class="owner-note">Available after payment</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('click', async function (event) {
    const button = event.target.closest('.js-direct-download');
    if (!button) return;
    event.preventDefault();
    const downloadUrl = button.dataset.downloadUrl;
    if (!downloadUrl) return;
    const originalText = button.textContent;
    button.textContent = 'Downloading...';
    button.disabled = true;
    try {
        const response = await fetch(downloadUrl, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('Download failed');
        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const tempLink = document.createElement('a');
        tempLink.href = objectUrl;
        tempLink.download = '';
        document.body.appendChild(tempLink);
        tempLink.click();
        tempLink.remove();
        URL.revokeObjectURL(objectUrl);
    } catch (error) {
        window.alert('Unable to download the breakdown right now.');
    } finally {
        button.textContent = originalText;
        button.disabled = false;
    }
});
</script>

<?php toolshare_owner_render_layout_end(); ?>
