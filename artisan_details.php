<?php
require 'config/database.php';
require 'includes/functions.php';

$root = '';
$activePage = 'artisans';

$id = get_int('id');

// Load artisan with skill and district (INNER JOINs)
$stmt = $pdo->prepare("
    SELECT a.*, s.skill_name, s.skill_id, s.category, d.district_name
    FROM artisans a
    INNER JOIN skills s    ON a.skill_id = s.skill_id
    INNER JOIN districts d ON s.district_id = d.district_id
    WHERE a.artisan_id = ?
");
$stmt->execute([$id]);
$artisan = $stmt->fetch();

if (!$artisan) {
    http_response_code(404);
    $pageTitle = 'Artisan not found';
    include 'includes/header.php';
    echo '<div class="container py-5"><div class="empty-state"><i class="bi bi-question-circle"></i><h1 class="h3">Artisan not found</h1><a href="artisans.php" class="btn btn-maroon mt-2">Back to artisans</a></div></div>';
    include 'includes/footer.php';
    exit;
}

/* ---------------- Handle "I'm Interested in Learning" form ---------------- */
$errors = [];
$old = ['visitor_name' => '', 'visitor_email' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['visitor_name']  = trim($_POST['visitor_name'] ?? '');
    $old['visitor_email'] = trim($_POST['visitor_email'] ?? '');
    $old['message']       = trim($_POST['message'] ?? '');

    // Server-side validation
    if (mb_strlen($old['visitor_name']) < 2 || mb_strlen($old['visitor_name']) > 100) {
        $errors['visitor_name'] = 'Please enter your name (2–100 characters).';
    }
    if (!filter_var($old['visitor_email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['visitor_email']) > 150) {
        $errors['visitor_email'] = 'Please enter a valid email address.';
    }
    if (mb_strlen($old['message']) > 1000) {
        $errors['message'] = 'Message must be under 1000 characters.';
    }

    // Prevent the same email registering twice for the same artisan
    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM visitor_interest WHERE artisan_id = ? AND visitor_email = ?');
        $check->execute([$id, $old['visitor_email']]);
        if ($check->fetchColumn() > 0) {
            $errors['visitor_email'] = 'This email has already expressed interest in learning from this artisan.';
        }
    }

    if (!$errors) {
        // DML: INSERT
        $ins = $pdo->prepare('INSERT INTO visitor_interest (artisan_id, visitor_name, visitor_email, message) VALUES (?, ?, ?, ?)');
        $ins->execute([$id, $old['visitor_name'], $old['visitor_email'], $old['message']]);
        // Post/Redirect/Get – avoids duplicate submission on refresh
        redirect('artisan_details.php?id=' . $id . '&interest=1');
    }
}

$justSubmitted = isset($_GET['interest']);

// DML: UPDATE – count a profile view (not counted again on the thank-you redirect or form errors)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$justSubmitted) {
    $pdo->prepare('UPDATE artisans SET profile_views = profile_views + 1 WHERE artisan_id = ?')->execute([$id]);
    $artisan['profile_views']++;
}

// Products by this artisan
$stmt = $pdo->prepare('SELECT * FROM products WHERE artisan_id = ? ORDER BY product_name');
$stmt->execute([$id]);
$products = $stmt->fetchAll();

// Number of learning interests (COUNT)
$stmt = $pdo->prepare('SELECT COUNT(*) FROM visitor_interest WHERE artisan_id = ?');
$stmt->execute([$id]);
$interestCount = (int) $stmt->fetchColumn();

// Training for this artisan's skill
$stmt = $pdo->prepare('SELECT training_id, training_name, duration, available_seats FROM training WHERE skill_id = ?');
$stmt->execute([$artisan['skill_id']]);
$trainings = $stmt->fetchAll();

$pageTitle = $artisan['artisan_name'];
include 'includes/header.php';
?>

<section class="detail-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-3">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="artisans.php">Artisans</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($artisan['artisan_name']) ?></li>
        </ol></nav>
        <div class="d-flex flex-wrap align-items-center gap-4">
            <?= artisan_avatar($artisan, $root, 'lg') ?>
            <div>
                <h1 class="mb-1"><?= e($artisan['artisan_name']) ?></h1>
                <div class="meta-line mb-1">
                    <i class="bi bi-palette"></i> <a href="skill_details.php?id=<?= (int) $artisan['skill_id'] ?>" class="text-white"><?= e($artisan['skill_name']) ?></a>
                    &nbsp;·&nbsp; <i class="bi bi-geo-alt"></i> <?= e($artisan['district_name']) ?>
                </div>
                <div class="meta-line"><i class="bi bi-hourglass-split"></i> <?= (int) $artisan['experience'] ?> years of experience<?php if ($artisan['age']): ?> · Age <?= (int) $artisan['age'] ?><?php endif; ?></div>
            </div>
        </div>
    </div>
</section>

<div class="container mt-4">
    <?php if ($justSubmitted): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div><strong>Thank you! Your interest has been recorded.</strong></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ks-card static mb-4"><div class="card-body">
                <h2 class="h5">Biography</h2>
                <p class="mb-0"><?= nl2br(e($artisan['bio'])) ?></p>
            </div></div>

            <h2 class="section-title">Products by this Artisan</h2>
            <div class="row g-3">
                <?php foreach ($products as $p): ?>
                <div class="col-sm-6">
                    <div class="ks-card">
                        <?= product_image($p + ['category' => $artisan['category']], $root) ?>
                        <div class="card-body">
                            <h3 class="card-title h6"><?= e($p['product_name']) ?></h3>
                            <p class="small text-muted-ks"><?= e($p['description']) ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price"><?= format_price($p['price']) ?></span>
                                <span class="ks-badge"><?= e($p['availability']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$products): ?>
                    <div class="col-12"><div class="empty-state"><i class="bi bi-box"></i>No products listed yet.</div></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Learning -->
            <div class="ks-card static mb-4"><div class="card-body">
                <h2 class="h5">Learning</h2>
                <?php if ($artisan['accepts_apprentices']): ?>
                    <div class="teaching-note p-3 mb-3"><i class="bi bi-mortarboard-fill me-2"></i>This artisan is currently open to teaching.</div>
                <?php else: ?>
                    <div class="alert alert-secondary small mb-3">This artisan is not taking apprentices right now, but you can still register your interest.</div>
                <?php endif; ?>
                <button type="button" class="btn btn-terracotta w-100 btn-lg" data-bs-toggle="modal" data-bs-target="#interestModal">
                    <i class="bi bi-heart me-1"></i>I'm Interested in Learning
                </button>
                <?php if ($trainings): ?>
                    <hr>
                    <div class="small fw-semibold mb-2">Related training</div>
                    <?php foreach ($trainings as $t): ?>
                        <div class="small mb-1"><i class="bi bi-dot"></i><a href="training.php#training-<?= (int) $t['training_id'] ?>"><?= e($t['training_name']) ?></a> <span class="text-muted-ks">(<?= e($t['duration']) ?>)</span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div></div>

            <!-- Public interest -->
            <div class="ks-card static mb-4"><div class="card-body">
                <h2 class="h5">Public Interest</h2>
                <div class="row g-2">
                    <div class="col-6"><div class="info-tile"><div class="num"><?= (int) $artisan['profile_views'] ?></div><div class="lbl">Profile Views</div></div></div>
                    <div class="col-6"><div class="info-tile"><div class="num"><?= $interestCount ?></div><div class="lbl">Learning Interests</div></div></div>
                </div>
            </div></div>

            <div class="ks-card static"><div class="card-body small">
                <div class="fw-semibold mb-1"><i class="bi bi-envelope me-1"></i>Contact</div>
                <div><?= e($artisan['contact']) ?></div>
                <div class="text-muted-ks mt-2">Fictional demo profile created for a DBMS project.</div>
            </div></div>
        </div>
    </div>
</div>

<!-- Interest form (Bootstrap modal) -->
<div class="modal fade" id="interestModal" tabindex="-1" aria-labelledby="interestModalLabel" aria-hidden="true" data-open="<?= $errors ? '1' : '0' ?>">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content needs-validation" method="post" action="artisan_details.php?id=<?= (int) $id ?>" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="interestModalLabel">Learn from <?= e($artisan['artisan_name']) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="visitor_name" class="form-label">Name</label>
                    <input type="text" class="form-control <?= isset($errors['visitor_name']) ? 'is-invalid' : '' ?>" id="visitor_name" name="visitor_name"
                           value="<?= e($old['visitor_name']) ?>" required minlength="2" maxlength="100">
                    <div class="invalid-feedback"><?= e($errors['visitor_name'] ?? 'Please enter your name.') ?></div>
                </div>
                <div class="mb-3">
                    <label for="visitor_email" class="form-label">Email</label>
                    <input type="email" class="form-control <?= isset($errors['visitor_email']) ? 'is-invalid' : '' ?>" id="visitor_email" name="visitor_email"
                           value="<?= e($old['visitor_email']) ?>" required maxlength="150">
                    <div class="invalid-feedback"><?= e($errors['visitor_email'] ?? 'Please enter a valid email.') ?></div>
                </div>
                <div class="mb-1">
                    <label for="message" class="form-label">Message <span class="text-muted-ks fw-normal">(optional)</span></label>
                    <textarea class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="3" maxlength="1000"
                              placeholder="Tell the artisan what you'd like to learn"><?= e($old['message']) ?></textarea>
                    <div class="invalid-feedback"><?= e($errors['message'] ?? 'Message is too long.') ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-maroon" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-maroon"><i class="bi bi-send me-1"></i>Submit Interest</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
