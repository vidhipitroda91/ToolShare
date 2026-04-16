<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/deposit_helper.php';
require 'includes/site_chrome.php';

toolshare_require_user();

$categories = ['Power Tools', 'Gardening', 'Construction', 'DIY', 'Hand Tools'];

// 1. Security Check: Is ID provided?
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$tool_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// 2. Fetch existing data (Ensure the user owns this tool)
$stmt = $pdo->prepare("SELECT * FROM tools WHERE id = ? AND owner_id = ?");
$stmt->execute([$tool_id, $user_id]);
$tool = $stmt->fetch();

if (!$tool) { 
    die("Tool not found or unauthorized."); 
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $price_h = (float)($_POST['price_hourly'] ?? 0);
    $price_d = (float)($_POST['price_daily'] ?? 0);
    $price_w = (float)($_POST['price_weekly'] ?? 0);
    $deposit = toolshare_calculate_security_deposit($category, $price_h, $price_d, $price_w);
    $address = trim((string)($_POST['address'] ?? ''));
    $image_path = $tool['image_path']; // Default to old image

    if ($title === '' || $description === '' || $address === '') {
        $error = "Please complete all required listing details.";
    } elseif (!in_array($category, $categories, true)) {
        $error = "Please choose a valid category.";
    } elseif ($price_h <= 0 || $price_d <= 0 || $price_w <= 0) {
        $error = "Hourly, daily, and weekly pricing must all be greater than zero.";
    }

    // Handle New Image Upload (if provided)
    if (!isset($error) && !empty($_FILES["tool_image"]["name"])) {
        $target_dir = "uploads/";
        $file_name = time() . "_" . basename($_FILES["tool_image"]["name"]);
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["tool_image"]["tmp_name"], $target_file)) {
            // Delete old image file from server to save space
            if (file_exists($tool['image_path'])) { 
                unlink($tool['image_path']); 
            }
            $image_path = $target_file;
        }
    }

    // Update Database including Category
    $sql = "UPDATE tools SET title=?, description=?, category=?, price_hourly=?, price_daily=?, price_weekly=?, security_deposit=?, address=?, image_path=? WHERE id=? AND owner_id=?";
    $stmt = $pdo->prepare($sql);
    
    $params = [
        $title, 
        $description, 
        $category, 
        $price_h, 
        $price_d, 
        $price_w, 
        $deposit, 
        $address, 
        $image_path, 
        $tool_id, 
        $user_id
    ];

    if (!isset($error) && $stmt->execute($params)) {
        header("Location: dashboard.php?msg=updated");
        exit;
    } elseif (!isset($error)) {
        $error = "Something went wrong. Please try again.";
    }

    $tool = array_merge($tool, [
        'title' => $title,
        'description' => $description,
        'category' => $category,
        'price_hourly' => $price_h,
        'price_daily' => $price_d,
        'price_weekly' => $price_w,
        'security_deposit' => $deposit,
        'address' => $address,
        'image_path' => $image_path,
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Tool | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f4f4f4; color: #333; }
        .form-container { background: white; padding: 30px; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        label { font-weight: bold; display: block; margin-top: 15px; }
        input, textarea, select { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .pricing-row { display: flex; gap: 10px; }
        .btn-save { background: #2ecc71; color: white; border: none; padding: 15px; width: 100%; cursor: pointer; font-size: 1rem; font-weight: bold; margin-top: 20px; border-radius: 4px; }
        .btn-save:hover { background: #27ae60; }
        .cancel-link { display: block; text-align: center; margin-top: 15px; color: #888; text-decoration: none; }
        .current-img { width: 100px; height: 100px; object-fit: cover; border-radius: 4px; margin-top: 10px; border: 1px solid #ddd; }
        .deposit-note { margin-top: 8px; color: #64748b; font-size: 0.88rem; line-height: 1.5; }
    </style>
</head>
<body>
<?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>

<div class="form-container">
    <h2>Edit Tool Listing</h2>
    
    <?php if(isset($error)): ?>
        <p style="color:red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Tool Title</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($tool['title']); ?>" required>

        <label>Description</label>
        <textarea name="description" rows="4" required><?php echo htmlspecialchars($tool['description']); ?></textarea>

        <label>Category</label>
        <select name="category" required>
            <?php foreach ($categories as $option): ?>
                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($tool['category'] == $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
            <?php endforeach; ?>
        </select>

        <div class="pricing-row">
            <div>
                <label>Hourly ($)</label>
                <input type="number" step="0.01" name="price_hourly" value="<?php echo $tool['price_hourly']; ?>" required>
            </div>
            <div>
                <label>Daily ($)</label>
                <input type="number" step="0.01" name="price_daily" value="<?php echo $tool['price_daily']; ?>" required>
            </div>
            <div>
                <label>Weekly ($)</label>
                <input type="number" step="0.01" name="price_weekly" value="<?php echo $tool['price_weekly']; ?>" required>
            </div>
        </div>

        <label>Security Deposit ($)</label>
        <input type="number" step="0.01" name="security_deposit" value="<?php echo htmlspecialchars(number_format((float)$tool['security_deposit'], 2, '.', '')); ?>" readonly>
        <p class="deposit-note">The deposit is recalculated automatically from the category and pricing each time you update the listing.</p>

        <label>Pickup Address</label>
        <input type="text" name="address" value="<?php echo htmlspecialchars($tool['address']); ?>" required>

        <label>Tool Image</label>
        <input type="file" name="tool_image" accept="image/*">
        <p><small>Current Image:</small></p>
        <img src="<?php echo $tool['image_path']; ?>" class="current-img">

        <button type="submit" class="btn-save">Update Listing</button>
        <a href="dashboard.php" class="cancel-link">Cancel and Go Back</a>
    </form>
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
