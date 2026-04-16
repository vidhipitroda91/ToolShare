<?php
session_start();
require 'config/db.php';
require 'includes/site_chrome.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Submitted | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root {
            --primary-blue: #2563eb;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .confirmation-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }

        .icon-circle {
            width: 80px;
            height: 80px;
            background: #dbeafe;
            color: var(--primary-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 24px;
        }

        h1 {
            color: var(--text-main);
            font-size: 1.75rem;
            margin-bottom: 16px;
        }

        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary-blue);
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
        }

        .badge {
            display: inline-block;
            background: #f0fdf4;
            color: #16a34a;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<?php toolshare_render_focus_header([
    'kicker' => 'Reservation Flow',
    'title' => 'Reservation Submitted',
    'back_href' => 'dashboard.php',
    'back_label' => 'Back to Dashboard',
]); ?>

<div class="confirmation-card">
    <div class="icon-circle">✓</div>
    <div class="badge">Request Received</div>
    <h1>Your request has been submitted!</h1>
    <p>
        The tool owner has been notified. You will receive a notification on your dashboard 
        once they confirm the availability for your selected dates.
    </p>

    <div class="btn-group">
        <a href="dashboard.php" class="btn btn-primary">Go to My Dashboard</a>
        <a href="index.php" class="btn btn-secondary">Browse More Tools</a>
    </div>
</div>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
