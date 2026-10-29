<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$isAdmin = true;

// Totals (5 COUNT subqueries)
$totals = $pdo->query("
    SELECT (SELECT COUNT(*) FROM skills)           AS skills,
           (SELECT COUNT(*) FROM artisans)         AS artisans,
           (SELECT COUNT(*) FROM products)         AS products,
           (SELECT COUNT(*) FROM training)         AS training,
           (SELECT COUNT(*) FROM visitor_interest) AS interests,
           (SELECT COUNT(*) FROM artisan_requests WHERE status = 'PENDING') AS pending_requests
")->fetch();

// Oldest pending registration requests
$pendingList = $pdo->query("
    SELECT r.request_id, r.artisan_name, r.created_at, s.skill_name, d.district_name
    FROM artisan_requests r
    INNER JOIN skills s    ON r.skill_id = s.skill_id
    INNER JOIN districts d ON r.district_id = d.district_id
    WHERE r.status = 'PENDING'
    ORDER BY r.created_at ASC
    LIMIT 5
")->fetchAll();

// Recent visitor interests (JOIN with artisans)
$recent = $pdo->query("
    SELECT vi.visitor_name, vi.visitor_email, vi.message, vi.created_at, a.artisan_id, a.artisan_name
    FROM visitor_interest vi
    INNER JOIN artisans a ON vi.artisan_id = a.artisan_id
    ORDER BY vi.created_at DESC, vi.interest_id DESC
    LIMIT 6
")->fetchAll();

// Most discovered artisans – ordered by profile views (from the view)
$popular = $pdo->query("
    SELECT artisan_id, artisan_name, skill_name, profile_views, interest_count
    FROM artisan_summary
    ORDER BY profile_views DESC
    LIMIT 5
")->fetchAll();

// Skills at risk – fewer than 10 artisans (GROUP BY + HAVING)
$atRisk = $pdo->query("
    SELECT s.skill_id, s.skill_name, d.district_name, COUNT(a.artisan_id) AS artisan_count
    FROM skills s
    INNER JOIN districts d ON s.district_id = d.district_id
    LEFT JOIN artisans a   ON a.skill_id = s.skill_id
    GROUP BY s.skill_id, s.skill_name, d.district_name
    HAVING COUNT(a.artisan_id) < 10
    ORDER BY artisan_count ASC, s.skill_name
")->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Dashboard</h1>
        <span class="text-muted-ks small">Logged in as <strong><?= e($_SESSION['admin_username'] ?? 'admin') ?></strong></span>
    </div>
    <?php show_flash(); ?>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Total Skills', $totals['skills'], 'bi-palette', 'skills.php'],
            ['Total Artisans', $totals['artisans'], 'bi-people', 'artisans.php'],
            ['Total Products', $totals['products'], 'bi-box-seam', 'products.php'],
            ['Total Training Programs', $totals['training'], 'bi-mortarboard', 'training.php'],
            ['Total Visitor Interests', $totals['interests'], 'bi-envelope-heart', 'interests.php'],
            ['Pending Registrations', $totals['pending_requests'], 'bi-person-plus', 'requests.php'],
        ];
        foreach ($cards as [$label, $num, $icon, $link]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= $link ?>" class="text-decoration-none">
                <div class="admin-stat">
                    <div class="ic"><i class="bi <?= $icon ?>"></i></div>
                    <div><div class="num"><?= (int) $num ?></div><div class="lbl"><?= e($label) ?></div></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pendingList): ?>
    <div class="ks-card static mb-4" style="border-left:5px solid var(--ks-terracotta)"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h2 class="h5 mb-0"><i class="bi bi-person-plus text-terracotta me-1"></i>Artisan Registrations Awaiting Review</h2>
            <a href="requests.php" class="btn btn-sm btn-maroon">Review all (<?= (int) $totals['pending_requests'] ?>)</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <tbody>
                <?php foreach ($pendingList as $p): ?>
                    <tr>
                        <td><strong><?= e($p['artisan_name']) ?></strong></td>
                        <td><?= e($p['skill_name']) ?></td>
                        <td class="text-muted-ks"><?= e($p['district_name']) ?></td>
                        <td class="small text-muted-ks text-nowrap">Submitted <?= e(date('d M Y', strtotime($p['created_at']))) ?></td>
                        <td class="text-end"><a href="requests.php?id=<?= (int) $p['request_id'] ?>" class="btn btn-sm btn-outline-maroon">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-2">Recent Visitor Interests</h2>
                <a href="interests.php" class="small">View all</a>
            </div>
            <div class="ks-table table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Visitor</th><th>Artisan</th><th>Date</th><th>Message</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><strong><?= e($r['visitor_name']) ?></strong><br><span class="small text-muted-ks"><?= e($r['visitor_email']) ?></span></td>
                            <td><?= e($r['artisan_name']) ?></td>
                            <td class="small text-nowrap"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                            <td class="small"><?= e(excerpt($r['message'], 60)) ?: '<span class="text-muted-ks">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="4" class="text-center text-muted-ks py-3">No visitor interests yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-5">
            <h2 class="h5 mb-2">Most Discovered Artisans</h2>
            <div class="ks-table table-responsive mb-4">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Artisan</th><th class="text-end">Views</th><th class="text-end">Interests</th></tr></thead>
                    <tbody>
                    <?php foreach ($popular as $p): ?>
                        <tr>
                            <td><a href="../artisan_details.php?id=<?= (int) $p['artisan_id'] ?>" target="_blank" rel="noopener"><?= e($p['artisan_name']) ?></a><br><span class="small text-muted-ks"><?= e($p['skill_name']) ?></span></td>
                            <td class="text-end"><?= (int) $p['profile_views'] ?></td>
                            <td class="text-end"><?= (int) $p['interest_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-2">Skills at Risk <span class="small text-muted-ks fw-normal">(&lt; 10 artisans)</span></h2>
                <a href="../at_risk.php" class="small" target="_blank" rel="noopener">Public page</a>
            </div>
            <div class="ks-table table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Skill</th><th>District</th><th class="text-end">Artisans</th></tr></thead>
                    <tbody>
                    <?php foreach ($atRisk as $s): ?>
                        <tr>
                            <td><?= e($s['skill_name']) ?></td>
                            <td><?= e($s['district_name']) ?></td>
                            <td class="text-end"><span class="risk-pill risk-high"><?= (int) $s['artisan_count'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$atRisk): ?><tr><td colspan="3" class="text-center text-muted-ks py-3">No skills at high risk.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
