<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/deposit_helper.php';
require 'includes/site_chrome.php';

toolshare_require_user();

$categories = ['Power Tools', 'Gardening', 'Construction', 'DIY', 'Hand Tools'];
$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$category = trim((string)($_POST['category'] ?? 'Power Tools'));
$price_h = (float)($_POST['price_hourly'] ?? 0);
$price_d = (float)($_POST['price_daily'] ?? 0);
$price_w = (float)($_POST['price_weekly'] ?? 0);
$address = trim((string)($_POST['address'] ?? ''));
$deposit = toolshare_calculate_security_deposit($category, $price_h, $price_d, $price_w);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $owner_id = $_SESSION['user_id'];
    $deposit = toolshare_calculate_security_deposit($category, $price_h, $price_d, $price_w);

    if ($title === '' || $description === '' || $address === '') {
        $error = "Please complete all required listing details.";
    } elseif (!in_array($category, $categories, true)) {
        $error = "Please choose a valid category.";
    } elseif ($price_h <= 0 || $price_d <= 0 || $price_w <= 0) {
        $error = "Hourly, daily, and weekly pricing must all be greater than zero.";
    } elseif (empty($_FILES["tool_image"]["tmp_name"])) {
        $error = "Please upload a tool image.";
    }

    $target_dir = "uploads/";
    // Ensure directory exists
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

    $file_name = time() . "_" . basename($_FILES["tool_image"]["name"]);
    $target_file = $target_dir . $file_name;

    if (!isset($error) && move_uploaded_file($_FILES["tool_image"]["tmp_name"], $target_file)) {
        $sql = "INSERT INTO tools (owner_id, title, description, category, price_hourly, price_daily, price_weekly, security_deposit, address, image_path) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$owner_id, $title, $description, $category, $price_h, $price_d, $price_w, $deposit, $address, $target_file]);
        $success = "Tool listed successfully!";
        $title = '';
        $description = '';
        $category = 'Power Tools';
        $price_h = 0;
        $price_d = 0;
        $price_w = 0;
        $address = '';
        $deposit = toolshare_calculate_security_deposit($category, $price_h, $price_d, $price_w);
    } elseif (!isset($error)) {
        $error = "Failed to upload image.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>List a Tool | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root { --primary: #15324a; --accent: #1f6f78; --bg: #f8fafc; --text: #1e293b; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(180deg, #eef2f7 0%, #f8fafc 100%); color: var(--text); }
        .main-content { width: min(1100px, 94%); margin: 0 auto; padding: 20px 0 40px; }
        .form-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); max-width: 900px; margin: 0 auto; }
        
        h2 { color: var(--primary); margin-bottom: 30px; font-size: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .full-width { grid-column: span 2; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 700; color: var(--primary); margin-bottom: 8px; text-transform: uppercase; }
        
        input, select, textarea { 
            width: 100%; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; 
            outline: none; transition: 0.3s; font-size: 15px; background: #fff;
        }
        input:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(26, 54, 84, 0.05); }

        .pricing-section { 
            background: #f1f5f9; padding: 20px; border-radius: 15px; 
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin: 20px 0;
        }

        .btn-submit { 
            background: var(--accent); color: #fff; width: 100%; padding: 16px; 
            border: none; border-radius: 12px; font-weight: 800; font-size: 16px; 
            cursor: pointer; transition: 0.3s; margin-top: 20px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(31, 111, 120, 0.3); }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .deposit-note { margin-top: 8px; font-size: 13px; line-height: 1.5; color: #64748b; }
    </style>
</head>
<body>
<?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>

<div class="main-content">
    <div class="form-card">
        <h2>List Your Tool for Rent</h2>

        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?= $success ?> <a href="dashboard.php" style="color: inherit;">View in Dashboard</a></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form action="add_tool.php" method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Tool Title</label>
                    <input type="text" name="title" placeholder="e.g. DeWalt 20V Cordless Compact Drill" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <?php foreach ($categories as $option): ?>
                            <option value="<?= htmlspecialchars($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tool Image</label>
                    <input type="file" name="tool_image" accept="image/*" required>
                </div>

                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Describe the tool condition, included accessories, etc." required><?= htmlspecialchars($description) ?></textarea>
                </div>
            </div>

            <label>Rental Pricing (USD)</label>
            <div class="pricing-section">
                <div class="form-group">
                    <label style="font-size: 11px;">Hourly</label>
                    <input type="number" step="0.01" min="0.01" name="price_hourly" placeholder="0.00" value="<?= $price_h > 0 ? htmlspecialchars(number_format($price_h, 2, '.', '')) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label style="font-size: 11px;">Daily</label>
                    <input type="number" step="0.01" min="0.01" name="price_daily" placeholder="0.00" value="<?= $price_d > 0 ? htmlspecialchars(number_format($price_d, 2, '.', '')) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label style="font-size: 11px;">Weekly</label>
                    <input type="number" step="0.01" min="0.01" name="price_weekly" placeholder="0.00" value="<?= $price_w > 0 ? htmlspecialchars(number_format($price_w, 2, '.', '')) : '' ?>" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Security Deposit ($)</label>
                    <input type="number" step="0.01" name="security_deposit" value="<?= htmlspecialchars(number_format($deposit, 2, '.', '')) ?>" readonly>
                    <p class="deposit-note">This deposit is calculated automatically from the category and pricing, then rounded to a clean amount for checkout.</p>
                </div>
                <div class="form-group">
                    <label>Pickup Location</label>
                    <input type="text" name="address" placeholder="e.g. 123 Main St, New York" value="<?= htmlspecialchars($address) ?>" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">Post Tool Listing</button>
        </form>
    </div>
</div>
<?php toolshare_render_chrome_scripts(); ?>
<script>
    (() => {
        const policyMap = {
            "power tools": { multiplier: 2.0, floor: 75.0, cap: 400.0 },
            "construction": { multiplier: 3.0, floor: 125.0, cap: 750.0 },
            "gardening": { multiplier: 1.5, floor: 40.0, cap: 250.0 },
            "diy": { multiplier: 1.25, floor: 25.0, cap: 150.0 },
            "hand tools": { multiplier: 1.25, floor: 25.0, cap: 150.0 },
            "__default": { multiplier: 1.5, floor: 35.0, cap: 300.0 }
        };

        const category = document.querySelector('select[name="category"]');
        const hourly = document.querySelector('input[name="price_hourly"]');
        const daily = document.querySelector('input[name="price_daily"]');
        const weekly = document.querySelector('input[name="price_weekly"]');
        const deposit = document.querySelector('input[name="security_deposit"]');

        if (!category || !hourly || !daily || !weekly || !deposit) {
            return;
        }

        const roundToCleanAmount = (value) => Math.ceil(value / 5) * 5;

        const calculateDeposit = () => {
            const normalizedCategory = (category.value || '').trim().toLowerCase();
            const policy = policyMap[normalizedCategory] || policyMap.__default;
            const hourlyRate = parseFloat(hourly.value || '0');
            const dailyRate = parseFloat(daily.value || '0');
            const weeklyRate = parseFloat(weekly.value || '0');
            const effectiveDailyRate = dailyRate > 0 ? dailyRate : Math.max(hourlyRate * 8, weeklyRate / 5, 0);
            const calculated = Math.min(policy.cap, Math.max(policy.floor, effectiveDailyRate * policy.multiplier));

            deposit.value = roundToCleanAmount(calculated).toFixed(2);
        };

        [category, hourly, daily, weekly].forEach((field) => {
            field.addEventListener('input', calculateDeposit);
            field.addEventListener('change', calculateDeposit);
        });

        calculateDeposit();
    })();
</script>
</body>
</html>
