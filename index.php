<?php
session_start();
require 'config/db.php';
require 'includes/site_chrome.php';

$current_user_id = $_SESSION['user_id'] ?? 0;

$featuredStmt = $pdo->prepare("SELECT * FROM tools WHERE owner_id != ? ORDER BY created_at DESC LIMIT 6");
$featuredStmt->execute([$current_user_id]);
$featured_tools = $featuredStmt->fetchAll();

$toolCount = (int)$pdo->query("SELECT COUNT(*) FROM tools")->fetchColumn();
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$paidCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ToolShare | Rent Tools Near You</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root {
            --ink: #f8fafc;
            --surface: #f4f8fa;
            --card: #ffffff;
            --line: rgba(148, 163, 184, 0.25);
            --brand: #15324a;
            --teal: #1f6f78;
            --teal-deep: #175961;
            --muted: #64748b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Avenir Next", "Segoe UI", sans-serif; }
        body { background: var(--surface); color: #102338; }
        .container { width: min(1240px, 92%); margin: 0 auto; }

        .hero {
            position: relative;
            min-height: 68vh;
            display: grid;
            place-items: center;
            overflow: hidden;
            border-radius: 0 0 28px 28px;
            background: #0f2233;
        }

        .hero video,
        .hero .hero-poster {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(8, 19, 30, 0.22) 0%, rgba(8, 19, 30, 0.78) 100%),
                radial-gradient(circle at top right, rgba(31, 111, 120, 0.24), transparent 34%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: var(--ink);
            max-width: 780px;
            padding: 76px 0 56px;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 22px;
        }

        .hero h1 {
            font-size: clamp(2.4rem, 5.4vw, 4.2rem);
            line-height: 0.98;
            letter-spacing: -0.06em;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 1.08rem;
            line-height: 1.7;
            max-width: 680px;
            margin: 0 auto 34px;
            color: rgba(248, 250, 252, 0.84);
        }

        .hero-actions {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 210px;
            min-height: 58px;
            padding: 0 24px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 800;
            transition: transform 0.22s ease, background 0.22s ease, border-color 0.22s ease, color 0.22s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--teal);
            color: white;
            box-shadow: 0 16px 32px rgba(31, 111, 120, 0.22);
        }

        .btn-primary:hover {
            background: var(--teal-deep);
        }

        .btn-outline-light {
            border: 1px solid rgba(255, 255, 255, 0.44);
            color: white;
            background: rgba(255, 255, 255, 0.06);
        }

        .stats {
            margin-top: -42px;
            position: relative;
            z-index: 3;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .stat-card {
            padding: 26px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(14px);
        }

        .stat-card h3 {
            color: var(--brand);
            font-size: 2rem;
            margin-bottom: 6px;
        }

        .stat-card p {
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.82rem;
        }

        section {
            padding: 56px 0;
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
        }

        .section-head h2 {
            color: var(--brand);
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: -0.05em;
        }

        .section-head p {
            max-width: 600px;
            color: var(--muted);
            line-height: 1.7;
        }

        .featured-section {
            position: relative;
            padding-top: 38px;
            padding-bottom: 34px;
        }

        .featured-section::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 140px;
            background: radial-gradient(circle at center top, rgba(31, 111, 120, 0.12), rgba(31, 111, 120, 0));
            pointer-events: none;
        }

        .featured-section .container {
            position: relative;
            z-index: 1;
        }

        .featured-grid {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: minmax(280px, 340px);
            gap: 22px;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 10px;
            scroll-snap-type: x proximity;
            scrollbar-width: thin;
            transition: transform 0.18s linear;
            will-change: transform;
            margin-top: 18px;
        }

        .featured-grid::-webkit-scrollbar {
            height: 10px;
        }

        .featured-grid::-webkit-scrollbar-thumb {
            background: rgba(21, 50, 74, 0.22);
            border-radius: 999px;
        }

        .tool-card {
            position: relative;
            display: block;
            overflow: hidden;
            border-radius: 26px;
            background: var(--card);
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.07);
            transition: transform 0.24s ease, box-shadow 0.24s ease;
            scroll-snap-align: start;
        }

        .tool-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 44px rgba(15, 23, 42, 0.12);
        }

        .tool-media {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: #f8fbfc;
        }

        .tool-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.35s ease;
        }

        .tool-card:hover .tool-media img {
            transform: scale(1.03);
        }

        .price-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(16, 35, 56, 0.86);
            color: white;
            font-size: 0.84rem;
            font-weight: 800;
            backdrop-filter: blur(10px);
        }

        .tool-body {
            padding: 18px 22px 20px;
        }

        .category-pill {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            background: #e7f4f5;
            color: var(--teal-deep);
            font-size: 0.78rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .tool-body h3 {
            color: var(--brand);
            font-size: 1.36rem;
            line-height: 1.2;
            margin-bottom: 12px;
        }

        .tool-meta {
            color: var(--muted);
            margin-bottom: 14px;
        }

        .rent-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            background: var(--teal);
            color: white;
            font-weight: 800;
        }

        .how-section {
            background:
                linear-gradient(135deg, rgba(16, 35, 56, 0.98), rgba(20, 59, 77, 0.92)),
                radial-gradient(circle at top left, rgba(31, 111, 120, 0.22), transparent 30%);
            color: white;
            border-radius: 34px;
            overflow: hidden;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .step-card {
            padding: 26px;
            min-height: 240px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
        }

        .step-icon {
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: rgba(31, 111, 120, 0.18);
            color: #8ad2d6;
            margin-bottom: 18px;
        }

        .step-card h3 {
            font-size: 1.34rem;
            margin-bottom: 10px;
        }

        .step-card p {
            color: rgba(255, 255, 255, 0.78);
            line-height: 1.7;
        }

        .reveal {
            opacity: 0;
            transform: translateY(36px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .support-section {
            padding-top: 12px;
        }

        .support-card {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
            gap: 24px;
            padding: 34px;
            border-radius: 28px;
            background:
                linear-gradient(135deg, rgba(16, 35, 56, 0.98), rgba(20, 59, 77, 0.92)),
                radial-gradient(circle at top right, rgba(31, 111, 120, 0.26), transparent 32%);
            color: rgba(255, 255, 255, 0.76);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 24px 46px rgba(15, 23, 42, 0.12);
        }

        .support-copy h3 {
            color: white;
            margin-bottom: 10px;
            font-size: 1.9rem;
            letter-spacing: -0.04em;
        }

        .support-copy p {
            max-width: 680px;
            line-height: 1.75;
        }

        .support-points {
            display: grid;
            gap: 10px;
            margin-top: 18px;
            color: rgba(255, 255, 255, 0.92);
            font-weight: 700;
        }

        .support-points span {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .support-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #7ce2e7;
            flex-shrink: 0;
        }

        .support-actions {
            display: grid;
            gap: 12px;
            align-content: center;
            justify-items: stretch;
        }

        .support-actions .btn,
        .support-ghost {
            width: 100%;
            min-width: 0;
        }

        .support-ghost {
            min-height: 58px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.08);
            transition: transform 0.22s ease, background 0.22s ease;
        }

        .support-ghost:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.14);
        }

        @media (max-width: 1024px) {
            .steps-grid,
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .section-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .support-card {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .hero-content {
                padding: 108px 0 70px;
            }

            .hero-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .tool-media {
                height: 190px;
            }
        }
    </style>
</head>
<body>
<?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>

<header class="hero">
    <video autoplay muted loop playsinline poster="tools.avif" class="hero-poster">
        <source src="https://videos.pexels.com/video-files/4496268/4496268-hd_1920_1080_25fps.mp4" type="video/mp4">
    </video>
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <div class="hero-kicker">Trusted neighborhood rentals</div>
        <h1>Borrow the right tool from your neighborhood in minutes.</h1>
        <p>Skip the purchase. Search local listings, reserve fast, and coordinate pickup without the friction of a traditional rental counter.</p>
        <div class="hero-actions">
            <a href="browse.php" class="btn btn-primary">Browse All Tools</a>
            <a href="add_tool.php" class="btn btn-outline-light">List My Tool</a>
        </div>
    </div>
</header>

<section class="stats reveal">
    <div class="container stats-grid">
        <article class="stat-card">
            <h3><?= number_format($toolCount) ?>+</h3>
            <p>Tools Listed</p>
        </article>
        <article class="stat-card">
            <h3><?= number_format($userCount) ?>+</h3>
            <p>Community Members</p>
        </article>
        <article class="stat-card">
            <h3><?= number_format($paidCount) ?>+</h3>
            <p>Successful Rentals</p>
        </article>
    </div>
</section>

<section class="reveal featured-section" id="featured-tools">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Featured Tools</h2>
                <p>Fresh listings from local owners, ready for quick pickup and short-term jobs.</p>
            </div>
            <a href="browse.php" class="btn btn-primary" style="min-width: 0; min-height: 50px;">View All</a>
        </div>

        <?php if (empty($featured_tools)): ?>
            <div class="stat-card"><p>No featured tools are available yet.</p></div>
        <?php else: ?>
            <div class="featured-grid">
                <?php foreach ($featured_tools as $tool): ?>
                    <a href="tool_detail.php?id=<?= (int)$tool['id'] ?>" class="tool-card">
                        <div class="tool-media">
                            <img src="<?= htmlspecialchars($tool['image_path']) ?>" alt="<?= htmlspecialchars($tool['title']) ?>">
                            <div class="price-badge">$<?= number_format((float)$tool['price_daily'], 2) ?> / day</div>
                        </div>
                        <div class="tool-body">
                            <span class="category-pill"><?= htmlspecialchars($tool['category'] ?: 'General') ?></span>
                            <h3><?= htmlspecialchars($tool['title']) ?></h3>
                            <div class="tool-meta"><?= htmlspecialchars($tool['address'] ?: 'Local pickup available') ?></div>
                            <span class="rent-btn">Rent Now</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section id="how-it-works" class="reveal">
    <div class="container how-section">
        <div style="padding: 36px 32px 0;">
            <div class="section-head" style="margin-bottom: 26px;">
                <div>
                    <h2 style="color: white;">How It Works</h2>
                    <p style="color: rgba(255,255,255,0.74);">A direct, local flow from discovery to pickup.</p>
                </div>
            </div>
        </div>
        <div class="steps-grid" style="padding: 0 32px 32px;">
            <article class="step-card reveal">
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
                </div>
                <h3>1. Search Nearby</h3>
                <p>Browse by category, compare prices, and find the exact tool for the job without overbuying.</p>
            </article>
            <article class="step-card reveal">
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h16"></path><path d="M12 4v16"></path><rect x="3" y="3" width="18" height="18" rx="4"></rect></svg>
                </div>
                <h3>2. Reserve Fast</h3>
                <p>Send a booking request, get owner approval, and lock in your rental window with clear pricing.</p>
            </article>
            <article class="step-card reveal">
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 13l4 4L19 7"></path><rect x="3" y="3" width="18" height="18" rx="4"></rect></svg>
                </div>
                <h3>3. Pick Up and Build</h3>
                <p>Coordinate pickup with the owner, finish the work, and return the tool without wasting time or money.</p>
            </article>
        </div>
    </div>
</section>

<section id="support" class="support-section">
    <div class="container">
        <div class="support-card">
            <div class="support-copy">
                <h3>Customer Support</h3>
                <p>Get help with booking approvals, payment questions, receipts, return requests, disputes, and in-app communication. Active rental issues should go through your dashboard or dispute flow so the operations team can review the case properly.</p>
                <div class="support-points">
                    <span><span class="support-dot"></span>Track booking and dispute status from one place</span>
                    <span><span class="support-dot"></span>Use in-app chat for pickup, return, and rental coordination</span>
                    <span><span class="support-dot"></span>Escalate damage or refund issues to operations when needed</span>
                </div>
            </div>
            <div class="support-actions">
                <a href="support.php" class="btn btn-primary">Open Support Center</a>
                <a href="dashboard.php" class="support-ghost">Go To Dashboard</a>
            </div>
        </div>
    </div>
</section>

<?php toolshare_render_chrome_scripts(); ?>
<script>
    const revealItems = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.16 });

    revealItems.forEach((item, index) => {
        item.style.transitionDelay = `${index * 0.08}s`;
        revealObserver.observe(item);
    });

    const heroVideo = document.querySelector('.hero video');
    const statsSection = document.querySelector('.stats');
    const featuredSection = document.querySelector('#featured-tools');
    const featuredGrid = document.querySelector('.featured-grid');

    window.addEventListener('scroll', () => {
        const offset = window.scrollY * 0.16;
        if (heroVideo) {
            heroVideo.style.transform = `translateY(${offset}px) scale(1.05)`;
        }
        if (statsSection) {
            statsSection.style.transform = `translateY(${Math.min(14, window.scrollY * 0.03)}px)`;
        }
        if (featuredSection && featuredGrid) {
            const rect = featuredSection.getBoundingClientRect();
            const shift = Math.max(-18, Math.min(18, rect.top * -0.035));
            featuredGrid.style.transform = `translateY(${shift}px)`;
        }
    }, { passive: true });
</script>
</body>
</html>
