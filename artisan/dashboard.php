<?php
/**
 * Artisan Dashboard – only for APPROVED artisans.
 * Everything shown comes from the same `artisans`, `products` and
 * `visitor_interest` rows that visitors and the admin see.
 */
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';

$artisanAccount = require_approved_artisan($pdo);
$artisanId = (int) $artisanAccount['artisan_id'];

$root = '../';
$pageTitle = 'Artisan Dashboard';
$activePage = 'a_dashboard';
$isArtisan = true;

// The public artisan profile (INNER JOIN skill + district)
$stmt = $pdo->prepare('
    SELECT a.*, s.skill_name, s.category, d.district_name
    FROM artisans a
    INNER JOIN skills s    ON a.skill_id = s.skill_id
    INNER JOIN districts d ON s.district_id = d.district_id
    WHERE a.artisan_id = ?');
$stmt->execute([$artisanId]);
$artisan = $stmt->fetch();

// Counts for the stat cards
$stmt = $pdo->prepare('
    SELECT (SELECT COUNT(*) FROM products WHERE artisan_id = ?)         AS products,
           (SELECT COUNT(*) FROM visitor_interest WHERE artisan_id = ?) AS interests');
$stmt->execute([$artisanId, $artisanId]);
$counts = $stmt->fetch();

// Learners who want to learn from this artisan
$stmt = $pdo->prepare('SELECT visitor_name, visitor_email, message, created_at
                       FROM visitor_interest WHERE artisan_id = ?
                       ORDER BY created_at DESC, interest_id DESC LIMIT 10');
$stmt->execute([$artisanId]);
$interests = $stmt->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
    <?php show_flash(); ?>

    <div class="ks-card static mb-4" style="border-left:5px solid #2e7d32"><div class="card-body p-4">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?= artisan_avatar($artisan, $root, 'lg') ?>
            <div class="flex-grow-1">
                <h1 class="h3 mb-1">Welcome, <?= e($artisan['artisan_name']) ?></h1>
                <div class="mb-1">Status: <span class="risk-pill risk-low">APPROVED</span></div>
                <div class="fw-semibold" style="color:#2e7d32">🎉 You are now a registered KalaSetu Artisan.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="profile.php" class="btn btn-maroon"><i class="bi bi-pencil me-1"></i>Edit Profile</a>
                <a href="../artisan_details.php?id=<?= $artisanId ?>" class="btn btn-outline-maroon" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>View public profile</a>
            </div>
        </div>
    </div></div>

    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Profile views', $artisan['profile_views'], 'bi-eye', '../artisan_details.php?id=' . $artisanId],
            ['My products', $counts['products'], 'bi-box-seam', 'products.php'],
            ['Learning interests', $counts['interests'], 'bi-envelope-heart', '#interests'],
            ['Years of experience', $artisan['experience'], 'bi-hourglass-split', 'profile.php'],
        ] as [$label, $num, $icon, $link]): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= e($link) ?>" class="text-decoration-none">
                <div class="admin-stat">
                    <div class="ic"><i class="bi <?= $icon ?>"></i></div>
                    <div><div class="num"><?= (int) $num ?></div><div class="lbl"><?= e($label) ?></div></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="ks-card static h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">My Profile</h2>
                    <a href="profile.php" class="small">Edit</a>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-5">Skill</dt><dd class="col-7"><a href="../skill_details.php?id=<?= (int) $artisan['skill_id'] ?>"><?= e($artisan['skill_name']) ?></a></dd>
                    <dt class="col-5">Category</dt><dd class="col-7"><?= e($artisan['category']) ?></dd>
                    <dt class="col-5">District</dt><dd class="col-7"><?= e($artisan['district_name']) ?></dd>
                    <dt class="col-5">Age</dt><dd class="col-7"><?= $artisan['age'] ? (int) $artisan['age'] : '<span class="text-muted-ks">Not added</span>' ?></dd>
                    <dt class="col-5">Experience</dt><dd class="col-7"><?= (int) $artisan['experience'] ?> years</dd>
                    <dt class="col-5">Public contact</dt><dd class="col-7"><?= e($artisan['contact']) ?></dd>
                    <dt class="col-5">Teaches apprentices</dt><dd class="col-7"><?= $artisan['accepts_apprentices'] ? 'Yes' : 'No' ?></dd>
                    <dt class="col-5">Login email</dt><dd class="col-7"><?= e($artisanAccount['email']) ?></dd>
                </dl>
                <hr>
                <p class="small mb-0"><?= nl2br(e(excerpt($artisan['bio'], 300))) ?></p>
            </div></div>
        </div>

        <div class="col-lg-7" id="interests">
            <div class="ks-card static h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Learners interested in my craft</h2>
                    <a href="products.php" class="btn btn-sm btn-outline-maroon"><i class="bi bi-box-seam me-1"></i>Manage products</a>
                </div>
                <?php if ($interests): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Visitor</th><th>Message</th><th>Date</th></tr></thead>
                            <tbody>
                            <?php foreach ($interests as $i): ?>
                                <tr>
                                    <td><strong><?= e($i['visitor_name']) ?></strong><br><a href="mailto:<?= e($i['visitor_email']) ?>" class="small"><?= e($i['visitor_email']) ?></a></td>
                                    <td class="small"><?= e(excerpt($i['message'], 90)) ?: '<span class="text-muted-ks">—</span>' ?></td>
                                    <td class="small text-nowrap text-muted-ks"><?= e(date('d M Y', strtotime($i['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted-ks small mb-0">No learning interests yet. Visitors can send one from your public profile page.</p>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
