<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/site_chrome.php';

toolshare_require_user();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$currentDate = date('Y-m-d');
$currentTime = date('H:i');

$stmt = $pdo->prepare("SELECT * FROM tools WHERE id = ?");
$stmt->execute([$_GET['id']]);
$tool = $stmt->fetch();

if (!$tool) { die("Tool not found."); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Reservation | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root { --primary: #15324a; --accent: #1f6f78; --bg: #f8fafc; --text: #1e293b; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); line-height: 1.6; }
        .container { display: grid; grid-template-columns: 1fr 1.5fr; max-width: 1200px; margin: 24px auto 40px; gap: 40px; padding: 0 20px; }
        
        /* Left Column: Tool Summary */
        .tool-summary { background: white; padding: 30px; border-radius: 20px; height: fit-content; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .tool-summary img { width: 100%; border-radius: 12px; margin-bottom: 20px; border: 1px solid #f1f5f9; }
        .tool-summary h2 { color: var(--primary); margin-bottom: 15px; font-size: 1.5rem; }
        
        .price-list { list-style: none; margin-bottom: 20px; }
        .price-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #e2e8f0; font-size: 0.95rem; }
        .price-item strong { color: var(--primary); }

        /* Right Column: Form */
        .form-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .step { margin-bottom: 35px; }
        .step-header { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; }
        .step-num { background: var(--primary); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; }
        .step-title { font-size: 1.25rem; font-weight: 700; color: var(--primary); }

        .input-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        label { display: block; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
        input[type="date"], input[type="time"] { width: 100%; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 1rem; outline: none; transition: 0.3s; }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(26, 54, 84, 0.05); }
        .pricing-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .pricing-option input { position: absolute; opacity: 0; pointer-events: none; }
        .pricing-option label {
            display: block;
            padding: 18px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            cursor: pointer;
            transition: 0.25s;
            text-transform: none;
        }
        .pricing-option label strong { display: block; color: var(--primary); font-size: 1rem; margin-bottom: 6px; }
        .pricing-option label span { color: #64748b; font-size: 0.9rem; }
        .pricing-option input:checked + label {
            border-color: var(--accent);
            background: #f0f9fa;
            box-shadow: 0 0 0 4px rgba(31, 111, 120, 0.08);
        }

        .policy-box { background: #fffbeb; border: 1px solid #fef3c7; padding: 20px; border-radius: 15px; margin-top: 20px; }
        .policy-box ul { margin-left: 20px; margin-bottom: 15px; font-size: 0.9rem; color: #92400e; }
        
        .checkbox-container { display: flex; align-items: center; gap: 12px; cursor: pointer; font-weight: 600; font-size: 0.95rem; color: var(--primary); }
        .checkbox-container input { width: 20px; height: 20px; cursor: pointer; }

        .btn-submit { 
            width: 100%; padding: 18px; margin-top: 30px; border-radius: 100px; border: none;
            font-size: 1.1rem; font-weight: 800; transition: 0.3s; cursor: not-allowed;
            background: #e2e8f0; color: #94a3b8;
        }
        .btn-submit.active { background: var(--accent); color: white; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(31, 111, 120, 0.3); }
        .btn-submit.active:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
<?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>

<div class="container">
    <aside class="tool-summary">
        <img src="<?php echo $tool['image_path']; ?>" alt="Tool">
        <h2><?php echo htmlspecialchars($tool['title']); ?></h2>
        <ul class="price-list">
            <li class="price-item"><span>Hourly Rate</span> <strong>$<?php echo number_format($tool['price_hourly'], 2); ?></strong></li>
            <li class="price-item"><span>Daily Rate</span> <strong>$<?php echo number_format($tool['price_daily'], 2); ?></strong></li>
            <li class="price-item"><span>Weekly Rate</span> <strong>$<?php echo number_format($tool['price_weekly'], 2); ?></strong></li>
            <li class="price-item"><span>Security Deposit</span> <strong>$<?php echo number_format($tool['security_deposit'], 2); ?></strong></li>
        </ul>
        <p style="font-size: 0.85rem; color: #64748b;">📍 Pickup: <?php echo htmlspecialchars($tool['address']); ?></p>
    </aside>

    <main class="form-card">
        <form action="process_booking.php" method="POST" id="bookingForm">
            <input type="hidden" name="tool_id" value="<?php echo $tool['id']; ?>">
            
            <div class="step">
                <div class="step-header">
                    <span class="step-num">1</span>
                    <span class="step-title">Rental Schedule</span>
                </div>
                
                <div class="input-grid">
                    <div>
                        <label>Pick-Up Date</label>
                        <input type="date" name="pickup_date" id="pickup_date" min="<?php echo $currentDate; ?>" required onchange="validateDates()">
                    </div>
                    <div>
                        <label>Pick-Up Time</label>
                        <input type="time" name="pickup_time" id="pickup_time" required onchange="validateDates()">
                    </div>
                    <div>
                        <label>Drop-Off Date</label>
                        <input type="date" name="dropoff_date" id="dropoff_date" min="<?php echo $currentDate; ?>" required onchange="validateDates()">
                    </div>
                    <div>
                        <label>Drop-Off Time</label>
                        <input type="time" name="dropoff_time" id="dropoff_time" required onchange="validateDates()">
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-header">
                    <span class="step-num">2</span>
                    <span class="step-title">Pricing Type</span>
                </div>

                <div class="pricing-options">
                    <div class="pricing-option">
                        <input type="radio" name="pricing_type" id="pricing_hourly" value="hourly" onchange="toggleBtn()" required>
                        <label for="pricing_hourly">
                            <strong>Hourly</strong>
                            <span>$<?php echo number_format($tool['price_hourly'], 2); ?> per hour</span>
                        </label>
                    </div>
                    <div class="pricing-option">
                        <input type="radio" name="pricing_type" id="pricing_daily" value="daily" onchange="toggleBtn()" required>
                        <label for="pricing_daily">
                            <strong>Daily</strong>
                            <span>$<?php echo number_format($tool['price_daily'], 2); ?> per day</span>
                        </label>
                    </div>
                    <div class="pricing-option">
                        <input type="radio" name="pricing_type" id="pricing_weekly" value="weekly" onchange="toggleBtn()" required>
                        <label for="pricing_weekly">
                            <strong>Weekly</strong>
                            <span>$<?php echo number_format($tool['price_weekly'], 2); ?> per week</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-header">
                    <span class="step-num">3</span>
                    <span class="step-title">Rental Policies</span>
                </div>
                
                <div class="policy-box">
                    <ul>
                        <li>Refundable deposit of <strong>$<?php echo number_format($tool['security_deposit'], 2); ?></strong> is required.</li>
                        <li>Photo ID must be presented at time of pickup.</li>
                        <li>Tools must be returned in the same condition as received.</li>
                    </ul>
                    <label class="checkbox-container">
                        <input type="checkbox" id="agree" name="policy_agreed" value="1" onchange="toggleBtn()">
                        I agree to the rental terms and conditions.
                    </label>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn-submit" disabled>Submit Reservation Request</button>
        </form>
    </main>
</div>

<script>
    function getLocalDateParts() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');

        return {
            todayStr: `${year}-${month}-${day}`,
            nowTimeStr: `${hours}:${minutes}`
        };
    }

    function validateDates() {
        const pickupDate = document.getElementById('pickup_date');
        const pickupTime = document.getElementById('pickup_time');
        const dropoffDate = document.getElementById('dropoff_date');
        const dropoffTime = document.getElementById('dropoff_time');
        const { todayStr, nowTimeStr } = getLocalDateParts();

        if (pickupDate.value === todayStr && pickupTime.value < nowTimeStr && pickupTime.value !== "") {
            alert("Pick-up time cannot be in the past.");
            pickupTime.value = "";
        }

        if (pickupDate.value) dropoffDate.min = pickupDate.value;

        if (pickupDate.value && dropoffDate.value && pickupDate.value === dropoffDate.value) {
            if (pickupTime.value && dropoffTime.value && dropoffTime.value <= pickupTime.value) {
                alert("Drop-off time must be after the pick-up time.");
                dropoffTime.value = "";
            }
        }
        toggleBtn();
    }

    function toggleBtn() {
        const cb = document.getElementById('agree');
        const btn = document.getElementById('submitBtn');
        const pickupDate = document.getElementById('pickup_date').value;
        const pickupTime = document.getElementById('pickup_time').value;
        const dropoffDate = document.getElementById('dropoff_date').value;
        const dropoffTime = document.getElementById('dropoff_time').value;
        const pricingType = document.querySelector('input[name="pricing_type"]:checked');
        
        const isValid = cb.checked && pickupDate && pickupTime && dropoffDate && dropoffTime && pricingType;
        btn.disabled = !isValid;
        isValid ? btn.classList.add('active') : btn.classList.remove('active');
    }
</script>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
