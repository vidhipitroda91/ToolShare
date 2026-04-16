<?php
session_start();
require 'config/db.php';
require 'includes/site_chrome.php';

$current_user_id = $_SESSION['user_id'] ?? 0;
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$catStmt = $pdo->query("SELECT DISTINCT category FROM tools WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

$query = "SELECT * FROM tools WHERE owner_id != ?";
$params = [$current_user_id];

if ($search !== '') {
    $query .= " AND (title LIKE ? OR description LIKE ? OR category LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($category !== '') {
    $query .= " AND category = ?";
    $params[] = $category;
}

switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY price_daily ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY price_daily DESC";
        break;
    default:
        $query .= " ORDER BY created_at DESC";
        break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tools = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Tools | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: linear-gradient(180deg, #edf5f6 0%, #f8fafc 100%); color: #1e293b; }
        .container { width: min(1250px, 94%); margin: 18px auto 50px; }
        h1 { color: #1a3654; margin-bottom: 16px; font-size: 2rem; }

        .filters { background: rgba(255,255,255,0.85); border: 1px solid rgba(226,232,240,0.9); border-radius: 22px; padding: 14px; display: grid; grid-template-columns: 1.8fr 1fr 1fr auto; gap: 12px; margin-bottom: 16px; box-shadow: 0 18px 40px rgba(15,23,42,0.06); backdrop-filter: blur(12px); }
        .filters input, .filters select { width: 100%; border: 1px solid #dbe3ee; border-radius: 10px; padding: 11px 12px; font-size: 14px; }
        .filters button { background: #1f6f78; color: #fff; border: none; border-radius: 999px; padding: 0 22px; font-weight: 800; cursor: pointer; }
        .chip-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .chip { text-decoration: none; color: #1a3654; background: #e2e8f0; padding: 8px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; }
        .chip.active { background: #102338; color: #fff; }

        .result-meta { color: #64748b; margin-bottom: 14px; font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .card { border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden; text-decoration: none; color: inherit; background: #fff; transition: transform 0.25s, box-shadow 0.25s; box-shadow: 0 18px 35px rgba(15, 23, 42, 0.06); }
        .card:hover { transform: translateY(-5px); box-shadow: 0 24px 40px rgba(15, 23, 42, 0.12); }
        .card-media { position: relative; overflow: hidden; height: 220px; }
        .card img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.38s ease; }
        .card:hover img { transform: scale(1.08); }
        .card-body { padding: 18px; }
        .card-body h3 { color: #102338; margin-bottom: 10px; font-size: 1.18rem; }
        .price { position: absolute; top: 14px; right: 14px; font-weight: 800; color: #fff; background: rgba(16,35,56,0.82); padding: 10px 14px; border-radius: 999px; backdrop-filter: blur(10px); }
        .sub { color: #64748b; font-size: 13px; margin-bottom: 8px; }
        .category-tag { display: inline-block; margin-bottom: 10px; padding: 6px 10px; border-radius: 999px; background: #e7f4f5; color: #175961; font-size: 12px; font-weight: 800; }
        .rent-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 16px; border-radius: 999px; background: #1f6f78; color: #fff; font-weight: 800; margin-top: 6px; }
        .empty { background: #fff; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 24px; color: #64748b; }

        @media (max-width: 860px) {
            .filters { grid-template-columns: 1fr; }
            .filters button { padding: 11px 12px; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php toolshare_render_nav(['search_value' => $search, 'support_href' => 'index.php#support']); ?>

<div class="container">
    <h1>Browse Tools</h1>
    <form method="GET" class="filters">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by tool name, description, or category">
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
        </select>
        <button type="submit">Apply</button>
    </form>

    <div class="chip-row">
        <a class="chip <?= $category === '' ? 'active' : '' ?>" href="browse.php?search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a class="chip <?= $category === $cat ? 'active' : '' ?>" href="browse.php?category=<?= urlencode($cat) ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>">
                <?= htmlspecialchars($cat) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="result-meta"><?= count($tools) ?> tool(s) found</div>

    <?php if (empty($tools)): ?>
        <div class="empty">No tools match your current filters. Try another category or keyword.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($tools as $tool): ?>
                <a href="tool_detail.php?id=<?= (int)$tool['id'] ?>" class="card">
                    <div class="card-media">
                        <img src="<?= htmlspecialchars($tool['image_path']) ?>" alt="<?= htmlspecialchars($tool['title']) ?>">
                        <div class="price">$<?= number_format($tool['price_daily'], 2) ?> / day</div>
                    </div>
                    <div class="card-body">
                        <div class="category-tag"><?= htmlspecialchars($tool['category'] ?: 'General') ?></div>
                        <h3><?= htmlspecialchars($tool['title']) ?></h3>
                        <div class="sub"><?= htmlspecialchars($tool['address'] ?: 'Nearby') ?></div>
                        <span class="rent-btn">Rent Now</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
