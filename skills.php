<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$pageTitle = 'Traditional Skills';
$activePage = 'skills';

// Read filters from the URL
$q          = trim($_GET['q'] ?? '');
$category   = trim($_GET['category'] ?? '');
$districtId = get_int('district');

// Build the WHERE clause step by step (always with placeholders)
$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = 's.skill_name LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($category !== '') {
    $where[]  = 's.category = ?';
    $params[] = $category;
}
if ($districtId) {
    $where[]  = 's.district_id = ?';
    $params[] = $districtId;
}

$sql = "
    SELECT s.skill_id, s.skill_name, s.category, s.description, d.district_name,
           COUNT(a.artisan_id) AS artisan_count
    FROM skills s
    INNER JOIN districts d ON s.district_id = d.district_id
    LEFT JOIN artisans a   ON a.skill_id = s.skill_id
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    GROUP BY s.skill_id, s.skill_name, s.category, s.description, d.district_name
    ORDER BY s.skill_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$skills = $stmt->fetchAll();

$categories = get_categories($pdo);
$districts  = get_districts($pdo);

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="index.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Skills</li></ol></nav>
        <h1>Traditional Skills</h1>
        <p class="text-muted-ks mb-0">Crafts, arts and techniques practised across Maharashtra's districts.</p>
    </div>
</section>

<div class="container mt-4">
    <form class="filter-bar row g-2 align-items-end" method="get">
        <div class="col-md-5">
            <label for="q" class="form-label small">Search by skill name</label>
            <input type="text" id="q" name="q" class="form-control" placeholder="e.g. Pottery" value="<?= e($q) ?>" maxlength="100">
        </div>
        <div class="col-6 col-md-3">
            <label for="category" class="form-label small">Category</label>
            <select id="category" name="category" class="form-select" data-autosubmit>
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c) ?>" <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="district" class="form-label small">District</label>
            <select id="district" name="district" class="form-select" data-autosubmit>
                <option value="">All districts</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?= (int) $d['district_id'] ?>" <?= $d['district_id'] == $districtId ? 'selected' : '' ?>><?= e($d['district_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-maroon flex-grow-1" type="submit"><i class="bi bi-search me-1"></i>Search</button>
            <a href="skills.php" class="btn btn-outline-maroon" title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <p class="text-muted-ks mt-3 mb-3"><?= count($skills) ?> skill<?= count($skills) === 1 ? '' : 's' ?> found</p>

    <div class="row g-4">
        <?php foreach ($skills as $sk): ?>
        <div class="col-md-6 col-lg-4">
            <div class="ks-card">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="skill-icon"><i class="bi <?= category_icon($sk['category']) ?>"></i></span>
                        <div>
                            <h2 class="card-title mb-1"><?= e($sk['skill_name']) ?></h2>
                            <span class="ks-badge terracotta"><?= e($sk['category']) ?></span>
                        </div>
                    </div>
                    <div class="meta-line mb-2"><i class="bi bi-geo-alt"></i> <?= e($sk['district_name']) ?> District</div>
                    <p class="text-muted-ks flex-grow-1"><?= e(excerpt($sk['description'], 130)) ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="ks-badge"><i class="bi bi-people"></i><?= (int) $sk['artisan_count'] ?> artisan<?= $sk['artisan_count'] == 1 ? '' : 's' ?></span>
                        <a href="skill_details.php?id=<?= (int) $sk['skill_id'] ?>" class="btn btn-sm btn-maroon">View Skill</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!$skills): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-search"></i>No skills match your search. <a href="skills.php">Clear filters</a></div></div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
