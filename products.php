<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$pageTitle = 'Traditional Products';
$activePage = 'products';

$q       = trim($_GET['q'] ?? '');
$skillId = get_int('skill');

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = 'p.product_name LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($skillId) {
    $where[]  = 's.skill_id = ?';
    $params[] = $skillId;
}

// Product → Artisan → Skill (two INNER JOINs)
$sql = "
    SELECT p.*, a.artisan_id, a.artisan_name, s.skill_name, s.category
    FROM products p
    INNER JOIN artisans a ON p.artisan_id = a.artisan_id
    INNER JOIN skills s   ON a.skill_id = s.skill_id
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY p.product_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$skills = get_skills($pdo);

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="index.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Products</li></ol></nav>
        <h1>Traditional Products</h1>
        <p class="text-muted-ks mb-0">Handmade work by registered artisans. Listed for discovery only – KalaSetu does not sell products.</p>
    </div>
</section>

<div class="container mt-4">
    <form class="filter-bar row g-2 align-items-end" method="get">
        <div class="col-md-6">
            <label for="q" class="form-label small">Search products</label>
            <input type="text" id="q" name="q" class="form-control" placeholder="e.g. Chappal, Saree, Diya" value="<?= e($q) ?>" maxlength="100">
        </div>
        <div class="col-md-4">
            <label for="skill" class="form-label small">Skill</label>
            <select id="skill" name="skill" class="form-select" data-autosubmit>
                <option value="">All skills</option>
                <?php foreach ($skills as $s): ?>
                    <option value="<?= (int) $s['skill_id'] ?>" <?= $s['skill_id'] == $skillId ? 'selected' : '' ?>><?= e($s['skill_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-maroon flex-grow-1" type="submit"><i class="bi bi-search me-1"></i>Search</button>
            <a href="products.php" class="btn btn-outline-maroon" title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <p class="text-muted-ks mt-3 mb-3"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> found</p>

    <div class="row g-4">
        <?php foreach ($products as $p): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <div class="ks-card d-flex flex-column">
                <?= product_image($p, $root) ?>
                <div class="card-body d-flex flex-column">
                    <h2 class="card-title h6 mb-1"><?= e($p['product_name']) ?></h2>
                    <div class="meta-line"><i class="bi bi-person"></i> <?= e($p['artisan_name']) ?></div>
                    <div class="meta-line mb-2"><i class="bi bi-palette"></i> <?= e($p['skill_name']) ?></div>
                    <div class="d-flex justify-content-between align-items-center mb-3 mt-auto">
                        <span class="price"><?= format_price($p['price']) ?></span>
                        <span class="ks-badge"><?= e($p['availability']) ?></span>
                    </div>
                    <a href="artisan_details.php?id=<?= (int) $p['artisan_id'] ?>" class="btn btn-sm btn-outline-maroon">View Artisan</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$products): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-search"></i>No products match your search. <a href="products.php">Clear filters</a></div></div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
