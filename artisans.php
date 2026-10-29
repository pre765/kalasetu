<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$pageTitle = 'Artisans';
$activePage = 'artisans';

$q          = trim($_GET['q'] ?? '');
$skillId    = get_int('skill');
$districtId = get_int('district');
$experienced = isset($_GET['experienced']); // above-average experience filter

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = 'a.artisan_name LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($skillId) {
    $where[]  = 'a.skill_id = ?';
    $params[] = $skillId;
}
if ($districtId) {
    $where[]  = 's.district_id = ?';
    $params[] = $districtId;
}
if ($experienced) {
    // SUBQUERY: artisans whose experience is greater than the average experience
    $where[] = 'a.experience > (SELECT AVG(experience) FROM artisans)';
}

$sql = "
    SELECT a.artisan_id, a.artisan_name, a.experience, a.profile_image, a.accepts_apprentices,
           s.skill_name, d.district_name
    FROM artisans a
    INNER JOIN skills s    ON a.skill_id = s.skill_id
    INNER JOIN districts d ON s.district_id = d.district_id
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY a.artisan_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$artisans = $stmt->fetchAll();

// AVG() shown to the visitor
$avgExperience = (float) $pdo->query('SELECT AVG(experience) FROM artisans')->fetchColumn();

$skills    = get_skills($pdo);
$districts = get_districts($pdo);

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="index.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Artisans</li></ol></nav>
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div>
                <h1>Artisans</h1>
                <p class="text-muted-ks mb-0">The people who keep traditional skills alive. Average experience: <strong><?= number_format($avgExperience, 1) ?> years</strong>.</p>
            </div>
            <a href="register.php" class="btn btn-outline-maroon"><i class="bi bi-person-plus me-1"></i>Are you an artisan? Register</a>
        </div>
    </div>
</section>

<div class="container mt-4">
    <form class="filter-bar row g-2 align-items-end" method="get">
        <div class="col-md-4">
            <label for="q" class="form-label small">Search by name</label>
            <input type="text" id="q" name="q" class="form-control" placeholder="Artisan name" value="<?= e($q) ?>" maxlength="100">
        </div>
        <div class="col-6 col-md-3">
            <label for="skill" class="form-label small">Skill</label>
            <select id="skill" name="skill" class="form-select" data-autosubmit>
                <option value="">All skills</option>
                <?php foreach ($skills as $s): ?>
                    <option value="<?= (int) $s['skill_id'] ?>" <?= $s['skill_id'] == $skillId ? 'selected' : '' ?>><?= e($s['skill_name']) ?></option>
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
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-maroon flex-grow-1" type="submit"><i class="bi bi-search me-1"></i>Search</button>
            <a href="artisans.php" class="btn btn-outline-maroon" title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
        </div>
        <div class="col-12">
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="experienced" id="experienced" value="1" <?= $experienced ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label small" for="experienced">Only show artisans with above-average experience (more than <?= number_format($avgExperience, 1) ?> years)</label>
            </div>
        </div>
    </form>

    <p class="text-muted-ks mt-3 mb-3"><?= count($artisans) ?> artisan<?= count($artisans) === 1 ? '' : 's' ?> found</p>

    <div class="row g-4">
        <?php foreach ($artisans as $a): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <div class="ks-card text-center">
                <div class="card-body d-flex flex-column align-items-center">
                    <?= artisan_avatar($a, $root, 'lg') ?>
                    <h2 class="card-title h5 mt-3 mb-1"><?= e($a['artisan_name']) ?></h2>
                    <div class="meta-line"><i class="bi bi-palette"></i> <?= e($a['skill_name']) ?></div>
                    <div class="meta-line"><i class="bi bi-geo-alt"></i> <?= e($a['district_name']) ?></div>
                    <div class="d-flex gap-1 flex-wrap justify-content-center my-2">
                        <span class="ks-badge"><i class="bi bi-hourglass-split"></i><?= (int) $a['experience'] ?> yrs experience</span>
                        <?php if ($a['accepts_apprentices']): ?><span class="ks-badge green">Teaching</span><?php endif; ?>
                    </div>
                    <a href="artisan_details.php?id=<?= (int) $a['artisan_id'] ?>" class="btn btn-sm btn-maroon mt-auto">View Profile</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$artisans): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-search"></i>No artisans match your search. <a href="artisans.php">Clear filters</a></div></div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
