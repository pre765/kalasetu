<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$pageTitle = 'Training Opportunities';
$activePage = 'training';

// Training → Skill → District, plus how many artisans of that skill accept apprentices
$trainings = $pdo->query("
    SELECT t.*, s.skill_name, s.category, d.district_name,
           (SELECT COUNT(*) FROM artisans a
             WHERE a.skill_id = t.skill_id AND a.accepts_apprentices = TRUE) AS teaching_artisans
    FROM training t
    INNER JOIN skills s    ON t.skill_id = s.skill_id
    INNER JOIN districts d ON s.district_id = d.district_id
    ORDER BY s.skill_name, t.training_name
")->fetchAll();

$totalSeats = array_sum(array_column($trainings, 'available_seats'));

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="index.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Training</li></ol></nav>
        <h1>Learn a Traditional Skill</h1>
        <p class="text-muted-ks mb-0"><?= count($trainings) ?> training programmes · <?= (int) $totalSeats ?> seats available in total.</p>
    </div>
</section>

<div class="container mt-4">
    <div class="row g-4">
        <?php foreach ($trainings as $t): ?>
        <div class="col-md-6 col-lg-4" id="training-<?= (int) $t['training_id'] ?>">
            <div class="ks-card">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="skill-icon"><i class="bi <?= category_icon($t['category']) ?>"></i></span>
                        <h2 class="card-title mb-0"><?= e($t['training_name']) ?></h2>
                    </div>
                    <div class="meta-line"><i class="bi bi-palette"></i> Skill: <?= e($t['skill_name']) ?></div>
                    <div class="meta-line"><i class="bi bi-geo-alt"></i> <?= e($t['district_name']) ?></div>
                    <div class="d-flex gap-2 flex-wrap my-2">
                        <span class="ks-badge"><i class="bi bi-clock"></i><?= e($t['duration']) ?></span>
                        <?php if ((int) $t['available_seats'] > 0): ?>
                            <span class="ks-badge terracotta"><i class="bi bi-person-plus"></i>Seats: <?= (int) $t['available_seats'] ?></span>
                        <?php else: ?>
                            <span class="ks-badge grey">Seats full</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted-ks flex-grow-1"><?= e($t['description']) ?></p>
                    <div class="small text-muted-ks mb-2"><?= (int) $t['teaching_artisans'] ?> artisan(s) of this skill accept apprentices</div>
                    <a href="skill_details.php?id=<?= (int) $t['skill_id'] ?>" class="btn btn-sm btn-maroon">View</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$trainings): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-mortarboard"></i>No training programmes listed yet.</div></div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
