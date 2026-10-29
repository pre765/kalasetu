<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$pageTitle = 'Preserve the Skills. Empower the People.';
$activePage = 'home';

// Dynamic statistics – one query, four COUNT() subqueries
$stats = $pdo->query("
    SELECT (SELECT COUNT(*) FROM skills)   AS total_skills,
           (SELECT COUNT(*) FROM artisans) AS total_artisans,
           (SELECT COUNT(*) FROM products) AS total_products,
           (SELECT COUNT(*) FROM training) AS total_training
")->fetch();

// Featured skills: LEFT JOIN so skills with 0 artisans still appear
$featuredSkills = $pdo->query("
    SELECT s.skill_id, s.skill_name, s.category, s.description, d.district_name,
           COUNT(a.artisan_id) AS artisan_count
    FROM skills s
    INNER JOIN districts d ON s.district_id = d.district_id
    LEFT JOIN artisans a   ON a.skill_id = s.skill_id
    GROUP BY s.skill_id, s.skill_name, s.category, s.description, d.district_name
    ORDER BY artisan_count DESC, s.skill_name
    LIMIT 6
")->fetchAll();

// A few artisans to discover (random each visit)
$artisans = $pdo->query("
    SELECT a.artisan_id, a.artisan_name, a.experience, a.profile_image, a.accepts_apprentices,
           s.skill_name, d.district_name
    FROM artisans a
    INNER JOIN skills s    ON a.skill_id = s.skill_id
    INNER JOIN districts d ON s.district_id = d.district_id
    ORDER BY RAND()
    LIMIT 6
")->fetchAll();

// Skills that need attention: fewer than 10 registered artisans (GROUP BY + HAVING)
$attention = $pdo->query("
    SELECT s.skill_id, s.skill_name, d.district_name, COUNT(a.artisan_id) AS artisan_count
    FROM skills s
    INNER JOIN districts d ON s.district_id = d.district_id
    LEFT JOIN artisans a   ON a.skill_id = s.skill_id
    GROUP BY s.skill_id, s.skill_name, d.district_name
    HAVING COUNT(a.artisan_id) < 10
    ORDER BY artisan_count ASC, s.skill_name
    LIMIT 4
")->fetchAll();

include 'includes/header.php';
?>

<!-- HERO -->
<section class="hero py-5">
    <div class="container py-lg-5 position-relative" style="z-index:1">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="hero-kicker mb-2"><i class="bi bi-flower1 me-1"></i>KalaSetu · कला सेतु</span>
                <h1 class="mb-3">Preserve the Skills.<br>Empower the People.</h1>
                <p class="lead mb-4">Discover India's traditional skills, the artisans who keep them alive, and opportunities to learn them.</p>
                <div class="d-flex flex-wrap gap-2 pb-4">
                    <a href="skills.php" class="btn btn-light-cream btn-lg px-4"><i class="bi bi-compass me-2"></i>Explore Skills</a>
                    <a href="artisans.php" class="btn btn-outline-light btn-lg px-4"><i class="bi bi-people me-2"></i>Discover Artisans</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STATS (from MySQL) -->
<section class="container stats-wrap">
    <div class="row g-3">
        <?php
        $statItems = [
            ['num' => $stats['total_skills'],   'label' => 'Traditional Skills',     'icon' => 'bi-palette'],
            ['num' => $stats['total_artisans'], 'label' => 'Artisans',               'icon' => 'bi-people'],
            ['num' => $stats['total_products'], 'label' => 'Products',               'icon' => 'bi-box-seam'],
            ['num' => $stats['total_training'], 'label' => 'Training Opportunities', 'icon' => 'bi-mortarboard'],
        ];
        foreach ($statItems as $s): ?>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <i class="bi <?= $s['icon'] ?>"></i>
                <div class="stat-num mt-1"><?= (int) $s['num'] ?></div>
                <div class="stat-label"><?= e($s['label']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- FEATURED SKILLS -->
<section class="container mt-5 pt-3">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
        <h2 class="section-title">Featured Skills</h2>
        <a href="skills.php" class="mb-4 text-decoration-none fw-semibold">View all skills <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-4">
        <?php foreach ($featuredSkills as $sk): ?>
        <div class="col-md-6 col-lg-4">
            <div class="ks-card">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="skill-icon"><i class="bi <?= category_icon($sk['category']) ?>"></i></span>
                        <div>
                            <h3 class="card-title mb-0"><?= e($sk['skill_name']) ?></h3>
                            <span class="meta-line"><i class="bi bi-geo-alt"></i> <?= e($sk['district_name']) ?></span>
                        </div>
                    </div>
                    <p class="text-muted-ks flex-grow-1"><?= e(excerpt($sk['description'])) ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="ks-badge"><i class="bi bi-people"></i><?= (int) $sk['artisan_count'] ?> artisans</span>
                        <a href="skill_details.php?id=<?= (int) $sk['skill_id'] ?>" class="btn btn-sm btn-outline-maroon">View Skill</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- DISCOVER ARTISANS -->
<section class="container mt-5 pt-3">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
        <h2 class="section-title">Discover Local Artisans</h2>
        <a href="artisans.php" class="mb-4 text-decoration-none fw-semibold">All artisans <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-4">
        <?php foreach ($artisans as $a): ?>
        <div class="col-sm-6 col-lg-4">
            <div class="ks-card">
                <div class="card-body d-flex gap-3 align-items-center">
                    <?= artisan_avatar($a, $root) ?>
                    <div class="flex-grow-1">
                        <h3 class="card-title mb-1 h5"><?= e($a['artisan_name']) ?></h3>
                        <div class="meta-line"><i class="bi bi-palette"></i> <?= e($a['skill_name']) ?></div>
                        <div class="meta-line"><i class="bi bi-geo-alt"></i> <?= e($a['district_name']) ?> · <?= (int) $a['experience'] ?> yrs</div>
                    </div>
                    <a href="artisan_details.php?id=<?= (int) $a['artisan_id'] ?>" class="btn btn-sm btn-maroon" aria-label="View profile of <?= e($a['artisan_name']) ?>"><i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- SKILLS THAT NEED ATTENTION -->
<section class="container mt-5 pt-3">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
        <h2 class="section-title">Skills That Need Attention</h2>
        <a href="at_risk.php" class="mb-4 text-decoration-none fw-semibold">See risk analysis <i class="bi bi-arrow-right"></i></a>
    </div>
    <p class="text-muted-ks">These skills have fewer than 10 registered artisans on KalaSetu.</p>
    <div class="row g-3">
        <?php foreach ($attention as $sk): $r = risk_level((int) $sk['artisan_count']); ?>
        <div class="col-sm-6 col-lg-3">
            <a href="skill_details.php?id=<?= (int) $sk['skill_id'] ?>" class="text-decoration-none">
                <div class="ks-card risk-card">
                    <div class="card-body">
                        <span class="risk-pill <?= $r['class'] ?>"><?= $r['label'] ?></span>
                        <h3 class="card-title h6 mt-2 mb-1"><?= e($sk['skill_name']) ?></h3>
                        <div class="meta-line"><i class="bi bi-geo-alt"></i> <?= e($sk['district_name']) ?></div>
                        <div class="fw-bold text-maroon mt-2"><?= (int) $sk['artisan_count'] ?> artisan<?= $sk['artisan_count'] == 1 ? '' : 's' ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
        <?php if (!$attention): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-emoji-smile"></i>No skills currently have fewer than 10 artisans.</div></div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA -->
<section class="container mt-5">
    <div class="cta-band p-4 p-md-5">
        <div class="row align-items-center g-3 position-relative">
            <div class="col-lg-8">
                <h2 class="mb-2">Help Keep a Skill Alive</h2>
                <p class="mb-2 opacity-75">Every skill survives only when someone learns it. Find an artisan who is open to teaching and tell them you're interested.</p>
                <p class="mb-0 small">Practise a traditional craft yourself? <a href="register.php" class="text-white fw-semibold">Register as an artisan <i class="bi bi-arrow-right"></i></a></p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="training.php" class="btn btn-light-cream btn-lg me-2 mb-2"><i class="bi bi-mortarboard me-1"></i>Find Training</a>
                <a href="artisans.php" class="btn btn-outline-light btn-lg mb-2">Meet Artisans</a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
