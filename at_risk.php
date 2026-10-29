<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$pageTitle = 'Skills at Risk';
$activePage = 'at_risk';

/*
 * Main analytical query.
 * COUNT() artisans per skill, GROUP BY skill, LEFT JOIN so that skills with
 * zero artisans are included, and CASE to label the risk level in SQL itself.
 */
$skills = $pdo->query("
    SELECT s.skill_id, s.skill_name, s.category, d.district_name,
           COUNT(a.artisan_id) AS artisan_count,
           CASE
               WHEN COUNT(a.artisan_id) < 10  THEN 'HIGH'
               WHEN COUNT(a.artisan_id) <= 20 THEN 'MEDIUM'
               ELSE 'LOW'
           END AS risk
    FROM skills s
    INNER JOIN districts d ON s.district_id = d.district_id
    LEFT JOIN artisans a   ON a.skill_id = s.skill_id
    GROUP BY s.skill_id, s.skill_name, s.category, d.district_name
    ORDER BY artisan_count ASC, s.skill_name
")->fetchAll();

// HAVING: how many skills are high-risk (fewer than 10 artisans)
$highRiskCount = (int) $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT s.skill_id
        FROM skills s
        LEFT JOIN artisans a ON a.skill_id = s.skill_id
        GROUP BY s.skill_id
        HAVING COUNT(a.artisan_id) < 10
    ) AS high_risk_skills
")->fetchColumn();

// Average artisans per skill (AVG over a grouped result)
$avgPerSkill = (float) $pdo->query("
    SELECT AVG(cnt) FROM (
        SELECT COUNT(a.artisan_id) AS cnt
        FROM skills s LEFT JOIN artisans a ON a.skill_id = s.skill_id
        GROUP BY s.skill_id
    ) AS per_skill
")->fetchColumn();

$summary = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
foreach ($skills as $s) {
    $summary[$s['risk']]++;
}
$maxCount = max(1, ...array_map('intval', array_column($skills, 'artisan_count') ?: [1]));

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="index.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Skills at Risk</li></ol></nav>
        <h1>Skills at Risk</h1>
        <p class="text-muted-ks mb-0">Skills with very few registered artisans may disappear if no one learns them.</p>
    </div>
</section>

<div class="container mt-4">
    <div class="alert alert-warning d-flex" role="note">
        <i class="bi bi-info-circle-fill me-2 mt-1"></i>
        <div>Risk level is a project-defined indicator based on the number of registered artisans. It is not an official government classification.</div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card" style="border-top-color:#b3261e"><div class="stat-num"><?= $highRiskCount ?></div><div class="stat-label">High risk<br><small>&lt; 10 artisans</small></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card" style="border-top-color:#e0a100"><div class="stat-num"><?= $summary['MEDIUM'] ?></div><div class="stat-label">Medium risk<br><small>10–20 artisans</small></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card" style="border-top-color:#2e7d32"><div class="stat-num"><?= $summary['LOW'] ?></div><div class="stat-label">Low risk<br><small>&gt; 20 artisans</small></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-num"><?= number_format($avgPerSkill, 1) ?></div><div class="stat-label">Avg. artisans<br><small>per skill</small></div></div></div>
    </div>

    <div class="ks-table table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr><th>Skill</th><th>District</th><th style="min-width:180px">Number of Artisans</th><th>Risk Level</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($skills as $s): $r = risk_level((int) $s['artisan_count']); ?>
                <tr>
                    <td><i class="bi <?= category_icon($s['category']) ?> text-terracotta me-2"></i><strong><?= e($s['skill_name']) ?></strong></td>
                    <td><?= e($s['district_name']) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold" style="min-width:2ch"><?= (int) $s['artisan_count'] ?></span>
                            <div class="risk-bar flex-grow-1"><span style="width:<?= round($s['artisan_count'] / $maxCount * 100) ?>%"></span></div>
                        </div>
                    </td>
                    <td><span class="risk-pill <?= $r['class'] ?>"><?= $r['label'] ?></span></td>
                    <td class="text-end"><a href="skill_details.php?id=<?= (int) $s['skill_id'] ?>" class="btn btn-sm btn-outline-maroon">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$skills): ?>
                <tr><td colspan="5" class="text-center text-muted-ks py-4">No skills in the database yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="small text-muted-ks mt-3 mb-0"><i class="bi bi-database me-1"></i>Computed live from MySQL using <code>COUNT()</code>, <code>GROUP BY</code>, <code>HAVING</code>, <code>LEFT JOIN</code> and <code>CASE</code>. Adding or removing artisans in the admin panel changes this page immediately.</p>
</div>

<?php include 'includes/footer.php'; ?>
