<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$activePage = 'skills';

$id = get_int('id');

// 1. The skill itself (JOIN with districts)
$stmt = $pdo->prepare("
    SELECT s.*, d.district_name, d.state
    FROM skills s
    INNER JOIN districts d ON s.district_id = d.district_id
    WHERE s.skill_id = ?
");
$stmt->execute([$id]);
$skill = $stmt->fetch();

if (!$skill) {
    http_response_code(404);
    $pageTitle = 'Skill not found';
    include 'includes/header.php';
    echo '<div class="container py-5"><div class="empty-state"><i class="bi bi-question-circle"></i><h1 class="h3">Skill not found</h1><a href="skills.php" class="btn btn-maroon mt-2">Back to skills</a></div></div>';
    include 'includes/footer.php';
    exit;
}

// 2. Artisans practising this skill
$stmt = $pdo->prepare("
    SELECT a.artisan_id, a.artisan_name, a.experience, a.accepts_apprentices, a.profile_image
    FROM artisans a
    WHERE a.skill_id = ?
    ORDER BY a.experience DESC
");
$stmt->execute([$id]);
$artisans = $stmt->fetchAll();

// 3. Products made by those artisans (skill → artisan → product)
$stmt = $pdo->prepare("
    SELECT p.*, a.artisan_name, s.category
    FROM products p
    INNER JOIN artisans a ON p.artisan_id = a.artisan_id
    INNER JOIN skills s   ON a.skill_id = s.skill_id
    WHERE s.skill_id = ?
    ORDER BY p.product_name
");
$stmt->execute([$id]);
$products = $stmt->fetchAll();

// 4. Training for this skill
$stmt = $pdo->prepare("SELECT * FROM training WHERE skill_id = ? ORDER BY training_name");
$stmt->execute([$id]);
$trainings = $stmt->fetchAll();

$risk = risk_level(count($artisans));
$pageTitle = $skill['skill_name'];
include 'includes/header.php';
?>

<section class="detail-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-3">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="skills.php">Skills</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($skill['skill_name']) ?></li>
        </ol></nav>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="skill-icon" style="background:rgba(255,255,255,.15);color:#fff"><i class="bi <?= category_icon($skill['category']) ?>"></i></span>
            <div>
                <h1 class="mb-1"><?= e($skill['skill_name']) ?></h1>
                <div class="meta-line">
                    <i class="bi bi-tag"></i> <?= e($skill['category']) ?> &nbsp;·&nbsp;
                    <i class="bi bi-geo-alt"></i> <?= e($skill['district_name']) ?>, <?= e($skill['state']) ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container mt-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ks-card static"><div class="card-body">
                <h2 class="h5">About this skill</h2>
                <p class="mb-0"><?= nl2br(e($skill['description'])) ?></p>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="ks-card static"><div class="card-body">
                <div class="row g-2">
                    <div class="col-4"><div class="info-tile"><div class="num"><?= count($artisans) ?></div><div class="lbl">Artisans</div></div></div>
                    <div class="col-4"><div class="info-tile"><div class="num"><?= count($products) ?></div><div class="lbl">Products</div></div></div>
                    <div class="col-4"><div class="info-tile"><div class="num"><?= count($trainings) ?></div><div class="lbl">Trainings</div></div></div>
                </div>
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <span class="small text-muted-ks">Risk indicator</span>
                    <a href="at_risk.php" class="risk-pill <?= $risk['class'] ?> text-decoration-none"><?= $risk['label'] ?></a>
                </div>
            </div></div>
        </div>
    </div>

    <!-- Artisans -->
    <h2 class="section-title mt-5">Artisans practising this skill</h2>
    <div class="row g-4">
        <?php foreach ($artisans as $a): ?>
        <div class="col-sm-6 col-lg-4">
            <div class="ks-card"><div class="card-body d-flex gap-3 align-items-center">
                <?= artisan_avatar($a, $root) ?>
                <div class="flex-grow-1">
                    <h3 class="card-title h5 mb-1"><?= e($a['artisan_name']) ?></h3>
                    <div class="meta-line"><i class="bi bi-hourglass-split"></i> <?= (int) $a['experience'] ?> years experience</div>
                    <div class="meta-line"><i class="bi bi-geo-alt"></i> <?= e($skill['district_name']) ?></div>
                    <?php if ($a['accepts_apprentices']): ?>
                        <span class="ks-badge green mt-1"><i class="bi bi-check-circle"></i>Accepting apprentices</span>
                    <?php else: ?>
                        <span class="ks-badge grey mt-1">Not accepting apprentices</span>
                    <?php endif; ?>
                    <div class="mt-2"><a href="artisan_details.php?id=<?= (int) $a['artisan_id'] ?>" class="btn btn-sm btn-outline-maroon">View Profile</a></div>
                </div>
            </div></div>
        </div>
        <?php endforeach; ?>
        <?php if (!$artisans): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-person-exclamation"></i>No artisans are registered for this skill yet. This skill urgently needs attention.</div></div>
        <?php endif; ?>
    </div>

    <!-- Products -->
    <h2 class="section-title mt-5">Products</h2>
    <div class="row g-4">
        <?php foreach ($products as $p): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="ks-card">
                <?= product_image($p, $root) ?>
                <div class="card-body">
                    <h3 class="card-title h6 mb-1"><?= e($p['product_name']) ?></h3>
                    <div class="meta-line mb-2">by <?= e($p['artisan_name']) ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="price"><?= format_price($p['price']) ?></span>
                        <span class="ks-badge"><?= e($p['availability']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$products): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-box"></i>No products listed for this skill yet.</div></div>
        <?php endif; ?>
    </div>

    <!-- Training -->
    <h2 class="section-title mt-5">Training Opportunities</h2>
    <div class="row g-4">
        <?php foreach ($trainings as $t): ?>
        <div class="col-md-6">
            <div class="ks-card"><div class="card-body">
                <h3 class="card-title h5"><?= e($t['training_name']) ?></h3>
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <span class="ks-badge"><i class="bi bi-clock"></i><?= e($t['duration']) ?></span>
                    <span class="ks-badge terracotta"><i class="bi bi-person-plus"></i><?= (int) $t['available_seats'] ?> seats</span>
                </div>
                <p class="text-muted-ks mb-0"><?= e($t['description']) ?></p>
            </div></div>
        </div>
        <?php endforeach; ?>
        <?php if (!$trainings): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-mortarboard"></i>No training programmes listed yet. You can still contact an artisan who accepts apprentices.</div></div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
