<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Visitor Interests';
$activePage = 'interests';
$isAdmin = true;

// Delete a single interest (e.g. spam)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verify_csrf()) {
        $stmt = $pdo->prepare('DELETE FROM visitor_interest WHERE interest_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        set_flash('success', 'Visitor interest removed.');
    }
    redirect('interests.php');
}

$artisanId = get_int('artisan');

// How many people are interested in each artisan (GROUP BY + COUNT)
$perArtisan = $pdo->query("
    SELECT a.artisan_id, a.artisan_name, s.skill_name, COUNT(vi.interest_id) AS interest_count
    FROM artisans a
    INNER JOIN skills s ON a.skill_id = s.skill_id
    INNER JOIN visitor_interest vi ON vi.artisan_id = a.artisan_id
    GROUP BY a.artisan_id, a.artisan_name, s.skill_name
    ORDER BY interest_count DESC, a.artisan_name
")->fetchAll();

// All interests (optionally filtered by artisan)
$sql = "
    SELECT vi.*, a.artisan_name, s.skill_name
    FROM visitor_interest vi
    INNER JOIN artisans a ON vi.artisan_id = a.artisan_id
    INNER JOIN skills s   ON a.skill_id = s.skill_id
    " . ($artisanId ? 'WHERE vi.artisan_id = ?' : '') . "
    ORDER BY vi.created_at DESC, vi.interest_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($artisanId ? [$artisanId] : []);
$interests = $stmt->fetchAll();

include '../includes/header.php';
?>
<div class="container py-4">
    <h1 class="h3 mb-3">Visitor Interests</h1>
    <?php show_flash(); ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <h2 class="h6 text-uppercase text-muted-ks">Interests per artisan</h2>
            <div class="list-group shadow-sm">
                <a href="interests.php" class="list-group-item list-group-item-action d-flex justify-content-between <?= !$artisanId ? 'active' : '' ?>" style="<?= !$artisanId ? 'background:var(--ks-maroon);border-color:var(--ks-maroon)' : '' ?>">
                    All artisans <span class="badge bg-light text-dark"><?= array_sum(array_column($perArtisan, 'interest_count')) ?></span>
                </a>
                <?php foreach ($perArtisan as $p): $act = $artisanId == $p['artisan_id']; ?>
                    <a href="interests.php?artisan=<?= (int) $p['artisan_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $act ? 'active' : '' ?>" style="<?= $act ? 'background:var(--ks-maroon);border-color:var(--ks-maroon)' : '' ?>">
                        <span><?= e($p['artisan_name']) ?><br><small class="<?= $act ? '' : 'text-muted-ks' ?>"><?= e($p['skill_name']) ?></small></span>
                        <span class="badge rounded-pill" style="background:var(--ks-terracotta)"><?= (int) $p['interest_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-8">
            <h2 class="h6 text-uppercase text-muted-ks"><?= count($interests) ?> record<?= count($interests) === 1 ? '' : 's' ?></h2>
            <div class="ks-table table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Visitor</th><th>Artisan</th><th>Date</th><th>Message</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($interests as $i): ?>
                        <tr>
                            <td><strong><?= e($i['visitor_name']) ?></strong><br><a href="mailto:<?= e($i['visitor_email']) ?>" class="small"><?= e($i['visitor_email']) ?></a></td>
                            <td><?= e($i['artisan_name']) ?><br><span class="small text-muted-ks"><?= e($i['skill_name']) ?></span></td>
                            <td class="small text-nowrap"><?= e(date('d M Y, H:i', strtotime($i['created_at']))) ?></td>
                            <td class="small"><?= $i['message'] !== '' && $i['message'] !== null ? nl2br(e($i['message'])) : '<span class="text-muted-ks">—</span>' ?></td>
                            <td class="text-end">
                                <form method="post" data-confirm="Remove this visitor interest?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $i['interest_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete" aria-label="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$interests): ?><tr><td colspan="5" class="text-center text-muted-ks py-4">No visitor interests yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
