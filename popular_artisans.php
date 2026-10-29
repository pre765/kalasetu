<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$pageTitle = 'Most Discovered Artisans';
$activePage = 'popular';

/*
 * Uses the VIEW artisan_summary (artisan + skill + district + views + COUNT of interests).
 * Ranking = profile views + number of learning interests. No ratings are used.
 */
$artisans = $pdo->query("
    SELECT artisan_id, artisan_name, skill_name, district_name, profile_views, interest_count,
           (profile_views + interest_count) AS discovery_score
    FROM artisan_summary
    ORDER BY discovery_score DESC, interest_count DESC, artisan_name
    LIMIT 10
")->fetchAll();

include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="index.php">Home</a></li><li class="breadcrumb-item active" aria-current="page">Most Discovered</li></ol></nav>
        <h1>Most Discovered Artisans</h1>
        <p class="text-muted-ks mb-0">Ranked by real activity on KalaSetu: profile views + learning interests. This is not a rating of an artisan's skill or quality.</p>
    </div>
</section>

<div class="container mt-4">
    <div class="row g-3">
        <?php foreach ($artisans as $i => $a): $rank = $i + 1; ?>
        <div class="col-12">
            <div class="ks-card rank-<?= $rank ?>">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <span class="rank-num">#<?= $rank ?></span>
                    <?= artisan_avatar($a, $root, 'sm') ?>
                    <div class="flex-grow-1" style="min-width:180px">
                        <h2 class="card-title h5 mb-0"><?= e($a['artisan_name']) ?></h2>
                        <div class="meta-line"><?= e($a['skill_name']) ?> · <?= e($a['district_name']) ?></div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="ks-badge"><i class="bi bi-eye"></i>Profile Views: <?= (int) $a['profile_views'] ?></span>
                        <span class="ks-badge terracotta"><i class="bi bi-heart"></i>Learning Interests: <?= (int) $a['interest_count'] ?></span>
                        <?php if ($rank === 1): ?><span class="ks-badge green"><i class="bi bi-stars"></i>Most Discovered</span><?php endif; ?>
                    </div>
                    <a href="artisan_details.php?id=<?= (int) $a['artisan_id'] ?>" class="btn btn-sm btn-outline-maroon">View Profile</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$artisans): ?>
            <div class="col-12"><div class="empty-state"><i class="bi bi-people"></i>No artisans yet.</div></div>
        <?php endif; ?>
    </div>
    <p class="small text-muted-ks mt-3 mb-0"><i class="bi bi-database me-1"></i>Data comes from the <code>artisan_summary</code> view (<code>LEFT JOIN</code> + <code>COUNT()</code>), ordered with <code>ORDER BY</code>.</p>
</div>

<?php include 'includes/footer.php'; ?>
