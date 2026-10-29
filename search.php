<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$pageTitle = 'Search';
$activePage = '';

$q = trim($_GET['q'] ?? '');
$skills = $artisans = $products = [];

if (mb_strlen($q) >= 2 && mb_strlen($q) <= 100) {
    $like = '%' . $q . '%';

    // Skills: match name or category
    $stmt = $pdo->prepare("
        SELECT s.skill_id, s.skill_name, s.category, d.district_name
        FROM skills s INNER JOIN districts d ON s.district_id = d.district_id
        WHERE s.skill_name LIKE ? OR s.category LIKE ?
        ORDER BY s.skill_name");
    $stmt->execute([$like, $like]);
    $skills = $stmt->fetchAll();

    // Artisans: match name
    $stmt = $pdo->prepare("
        SELECT a.artisan_id, a.artisan_name, a.profile_image, a.experience, s.skill_name, d.district_name
        FROM artisans a
        INNER JOIN skills s    ON a.skill_id = s.skill_id
        INNER JOIN districts d ON s.district_id = d.district_id
        WHERE a.artisan_name LIKE ?
        ORDER BY a.artisan_name");
    $stmt->execute([$like]);
    $artisans = $stmt->fetchAll();

    // Products: match name
    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, p.price, a.artisan_id, a.artisan_name
        FROM products p INNER JOIN artisans a ON p.artisan_id = a.artisan_id
        WHERE p.product_name LIKE ?
        ORDER BY p.product_name");
    $stmt->execute([$like]);
    $products = $stmt->fetchAll();
}

$total = count($skills) + count($artisans) + count($products);

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Search results</h1>
        <?php if ($q !== ''): ?>
            <p class="text-muted-ks mb-0"><?= $total ?> result<?= $total === 1 ? '' : 's' ?> for “<?= e($q) ?>”</p>
        <?php else: ?>
            <p class="text-muted-ks mb-0">Type at least 2 characters in the search box.</p>
        <?php endif; ?>
    </div>
</section>

<div class="container mt-4">
    <form class="filter-bar d-flex gap-2 mb-4" method="get" role="search">
        <input type="search" name="q" class="form-control" placeholder="Search skills, artisans, products" value="<?= e($q) ?>" required minlength="2" maxlength="100" aria-label="Search">
        <button class="btn btn-maroon px-4" type="submit"><i class="bi bi-search"></i></button>
    </form>

    <?php if ($q !== '' && mb_strlen($q) < 2): ?>
        <div class="alert alert-warning">Please type at least 2 characters.</div>
    <?php endif; ?>

    <?php if ($skills): ?>
    <h2 class="section-title h4">Skills (<?= count($skills) ?>)</h2>
    <div class="list-group mb-4 shadow-sm">
        <?php foreach ($skills as $s): ?>
            <a href="skill_details.php?id=<?= (int) $s['skill_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span><i class="bi <?= category_icon($s['category']) ?> text-terracotta me-2"></i><strong><?= e($s['skill_name']) ?></strong> <span class="text-muted-ks small">· <?= e($s['category']) ?></span></span>
                <span class="small text-muted-ks"><?= e($s['district_name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($artisans): ?>
    <h2 class="section-title h4">Artisans (<?= count($artisans) ?>)</h2>
    <div class="list-group mb-4 shadow-sm">
        <?php foreach ($artisans as $a): ?>
            <a href="artisan_details.php?id=<?= (int) $a['artisan_id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                <?= artisan_avatar($a, $root, 'sm') ?>
                <span class="flex-grow-1"><strong><?= e($a['artisan_name']) ?></strong><br><span class="small text-muted-ks"><?= e($a['skill_name']) ?> · <?= e($a['district_name']) ?></span></span>
                <span class="small text-muted-ks"><?= (int) $a['experience'] ?> yrs</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($products): ?>
    <h2 class="section-title h4">Products (<?= count($products) ?>)</h2>
    <div class="list-group mb-4 shadow-sm">
        <?php foreach ($products as $p): ?>
            <a href="artisan_details.php?id=<?= (int) $p['artisan_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box-seam text-terracotta me-2"></i><strong><?= e($p['product_name']) ?></strong> <span class="small text-muted-ks">by <?= e($p['artisan_name']) ?></span></span>
                <span class="price small"><?= format_price($p['price']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($q !== '' && mb_strlen($q) >= 2 && $total === 0): ?>
        <div class="empty-state"><i class="bi bi-search"></i>Nothing found for “<?= e($q) ?>”. Try another word, like “pottery” or “Kolhapur”.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
