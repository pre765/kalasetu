<?php
require '../config/database.php';
require '../includes/functions.php';
require '../includes/auth.php';
require_admin();

$root = '../';
$pageTitle = 'Manage Artisans';
$activePage = 'artisans';
$isAdmin = true;

$errors = [];
$form = ['artisan_id' => 0, 'skill_id' => '', 'artisan_name' => '', 'age' => '', 'experience' => '',
         'bio' => '', 'contact' => '', 'accepts_apprentices' => 0, 'profile_image' => null];
$action = $_GET['action'] ?? 'list';

/* ======================= HANDLE POST ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('artisans.php');
    }

    // ---------- DELETE ----------
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $img = $pdo->prepare('SELECT profile_image FROM artisans WHERE artisan_id = ?');
        $img->execute([$id]);
        $oldImage = $img->fetchColumn();

        $stmt = $pdo->prepare('DELETE FROM artisans WHERE artisan_id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            delete_image($oldImage ?: null);
            set_flash('success', 'Artisan deleted. Their products and visitor interests were removed too (ON DELETE CASCADE). A linked artisan login, if any, is kept but unlinked (ON DELETE SET NULL).');
        }
        redirect('artisans.php');
    }

    // ---------- CREATE / UPDATE ----------
    $form['artisan_id']          = (int) ($_POST['artisan_id'] ?? 0);
    $form['skill_id']            = (int) ($_POST['skill_id'] ?? 0);
    $form['artisan_name']        = trim($_POST['artisan_name'] ?? '');
    $form['age']                 = trim($_POST['age'] ?? '');
    $form['experience']          = trim($_POST['experience'] ?? '');
    $form['bio']                 = trim($_POST['bio'] ?? '');
    $form['contact']             = trim($_POST['contact'] ?? '');
    $form['accepts_apprentices'] = isset($_POST['accepts_apprentices']) ? 1 : 0;

    // Keep the current image when editing
    if ($form['artisan_id']) {
        $cur = $pdo->prepare('SELECT profile_image FROM artisans WHERE artisan_id = ?');
        $cur->execute([$form['artisan_id']]);
        $form['profile_image'] = $cur->fetchColumn() ?: null;
    }

    $chk = $pdo->prepare('SELECT COUNT(*) FROM skills WHERE skill_id = ?');
    $chk->execute([$form['skill_id']]);
    if (!$chk->fetchColumn()) {
        $errors['skill_id'] = 'Please choose a valid skill.';
    }
    if (mb_strlen($form['artisan_name']) < 2 || mb_strlen($form['artisan_name']) > 150) {
        $errors['artisan_name'] = 'Name is required (2–150 characters).';
    }
    // Age is optional: artisans who registered through register.php don't provide it
    if ($form['age'] !== '' && filter_var($form['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 120]]) === false) {
        $errors['age'] = 'Age must be a whole number between 10 and 120, or left empty.';
    }
    if (filter_var($form['experience'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]) === false) {
        $errors['experience'] = 'Experience must be a whole number between 0 and 100.';
    } elseif ($form['age'] !== '' && !isset($errors['age']) && (int) $form['experience'] >= (int) $form['age']) {
        $errors['experience'] = 'Experience must be less than age.';
    }
    if (mb_strlen($form['bio']) < 10) {
        $errors['bio'] = 'Please write a short biography (at least 10 characters).';
    }
    if ($form['contact'] === '' || mb_strlen($form['contact']) > 100) {
        $errors['contact'] = 'Contact is required (max 100 characters).';
    }

    // Optional image upload
    $newImage = null;
    if (!$errors) {
        try {
            $newImage = handle_image_upload('profile_image');
        } catch (RuntimeException $ex) {
            $errors['profile_image'] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $oldImage = $form['profile_image'];
        if ($newImage) {
            $form['profile_image'] = $newImage;
        } elseif (isset($_POST['remove_image'])) {
            $form['profile_image'] = null;
        }

        $values = [$form['skill_id'], $form['artisan_name'], $form['age'] === '' ? null : (int) $form['age'], (int) $form['experience'],
                   $form['bio'], $form['contact'], $form['accepts_apprentices'], $form['profile_image']];

        if ($form['artisan_id']) {
            $stmt = $pdo->prepare('UPDATE artisans SET skill_id = ?, artisan_name = ?, age = ?, experience = ?, bio = ?,
                                   contact = ?, accepts_apprentices = ?, profile_image = ? WHERE artisan_id = ?');
            $stmt->execute([...$values, $form['artisan_id']]);
            if ($oldImage !== $form['profile_image']) {
                delete_image($oldImage);
            }
            set_flash('success', 'Artisan updated successfully.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO artisans (skill_id, artisan_name, age, experience, bio, contact, accepts_apprentices, profile_image)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute($values);
            set_flash('success', 'Artisan added successfully.');
        }
        redirect('artisans.php');
    }
    $action = $form['artisan_id'] ? 'edit' : 'add';
}

/* ======================= LOAD FOR EDIT ======================= */
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM artisans WHERE artisan_id = ?');
    $stmt->execute([get_int('id')]);
    $row = $stmt->fetch();
    if (!$row) {
        set_flash('warning', 'Artisan not found.');
        redirect('artisans.php');
    }
    $form = $row;
}

/* ======================= LIST ======================= */
$filterSkill = get_int('skill');
$sql = "
    SELECT a.artisan_id, a.artisan_name, a.experience, a.accepts_apprentices, a.profile_views, a.profile_image,
           s.skill_name, d.district_name, COUNT(p.product_id) AS product_count, ac.account_id
    FROM artisans a
    INNER JOIN skills s    ON a.skill_id = s.skill_id
    INNER JOIN districts d ON s.district_id = d.district_id
    LEFT JOIN products p   ON p.artisan_id = a.artisan_id
    LEFT JOIN artisan_accounts ac ON ac.artisan_id = a.artisan_id   -- registered (self-managed) artisans
    " . ($filterSkill ? 'WHERE a.skill_id = ?' : '') . "
    GROUP BY a.artisan_id, a.artisan_name, a.experience, a.accepts_apprentices, a.profile_views, a.profile_image, s.skill_name, d.district_name, ac.account_id
    ORDER BY a.artisan_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($filterSkill ? [$filterSkill] : []);
$artisans = $stmt->fetchAll();

$skills = get_skills($pdo);

include '../includes/header.php';
?>
<div class="container py-4">
<?php if ($action === 'add' || $action === 'edit'): ?>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="artisans.php">Artisans</a></li><li class="breadcrumb-item active"><?= $action === 'edit' ? 'Edit' : 'Add' ?></li></ol></nav>
    <h1 class="h3 mb-3"><?= $action === 'edit' ? 'Edit Artisan' : 'Add Artisan' ?></h1>
    <div class="alert alert-info small" style="max-width:860px"><i class="bi bi-info-circle me-1"></i>Use fictional/demo details only. Do not enter real people's personal information.</div>
    <div class="ks-card static" style="max-width:860px"><div class="card-body p-4">
        <form method="post" action="artisans.php" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="artisan_id" value="<?= (int) $form['artisan_id'] ?>">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="artisan_name">Artisan name</label>
                    <input type="text" id="artisan_name" name="artisan_name" class="form-control <?= isset($errors['artisan_name']) ? 'is-invalid' : '' ?>" value="<?= e($form['artisan_name']) ?>" required minlength="2" maxlength="150">
                    <div class="invalid-feedback"><?= e($errors['artisan_name'] ?? 'Name is required.') ?></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="skill_id">Skill</label>
                    <select id="skill_id" name="skill_id" class="form-select <?= isset($errors['skill_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Choose…</option>
                        <?php foreach ($skills as $s): ?>
                            <option value="<?= (int) $s['skill_id'] ?>" <?= $s['skill_id'] == $form['skill_id'] ? 'selected' : '' ?>><?= e($s['skill_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($errors['skill_id'] ?? 'Please choose a skill.') ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="age">Age <span class="fw-normal text-muted-ks">(optional)</span></label>
                    <input type="number" id="age" name="age" class="form-control <?= isset($errors['age']) ? 'is-invalid' : '' ?>" value="<?= e($form['age']) ?>" min="10" max="120">
                    <div class="invalid-feedback"><?= e($errors['age'] ?? 'Enter a valid age.') ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="experience">Experience (years)</label>
                    <input type="number" id="experience" name="experience" class="form-control <?= isset($errors['experience']) ? 'is-invalid' : '' ?>" value="<?= e($form['experience']) ?>" required min="0" max="100">
                    <div class="invalid-feedback"><?= e($errors['experience'] ?? 'Enter years of experience.') ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contact">Contact (demo email/phone)</label>
                    <input type="text" id="contact" name="contact" class="form-control <?= isset($errors['contact']) ? 'is-invalid' : '' ?>" value="<?= e($form['contact']) ?>" required maxlength="100" placeholder="name@kalasetu.example">
                    <div class="invalid-feedback"><?= e($errors['contact'] ?? 'Contact is required.') ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="bio">Biography</label>
                    <textarea id="bio" name="bio" rows="4" class="form-control <?= isset($errors['bio']) ? 'is-invalid' : '' ?>" required minlength="10"><?= e($form['bio']) ?></textarea>
                    <div class="invalid-feedback"><?= e($errors['bio'] ?? 'Please write a short biography.') ?></div>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="profile_image">Profile image <span class="fw-normal text-muted-ks">(optional · JPG/PNG/WEBP · max 2 MB)</span></label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp" class="form-control <?= isset($errors['profile_image']) ? 'is-invalid' : '' ?>">
                    <div class="invalid-feedback"><?= e($errors['profile_image'] ?? '') ?></div>
                    <?php if (!empty($form['profile_image'])): ?>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <?= artisan_avatar($form, $root, 'sm') ?>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_image" id="remove_image"><label class="form-check-label small" for="remove_image">Remove current image</label></div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="accepts_apprentices" name="accepts_apprentices" <?= $form['accepts_apprentices'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="accepts_apprentices">Accepts apprentices</label>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-maroon" type="submit"><i class="bi bi-check-lg me-1"></i><?= $action === 'edit' ? 'Save changes' : 'Add artisan' ?></button>
                <a href="artisans.php" class="btn btn-outline-maroon ms-1">Cancel</a>
            </div>
        </form>
    </div></div>

<?php else: ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h3 mb-0">Manage Artisans</h1>
        <div class="d-flex gap-2">
            <form method="get" class="d-flex">
                <select name="skill" class="form-select form-select-sm" data-autosubmit aria-label="Filter by skill">
                    <option value="">All skills</option>
                    <?php foreach ($skills as $s): ?>
                        <option value="<?= (int) $s['skill_id'] ?>" <?= $s['skill_id'] == $filterSkill ? 'selected' : '' ?>><?= e($s['skill_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="artisans.php?action=add" class="btn btn-maroon text-nowrap"><i class="bi bi-plus-lg me-1"></i>Add Artisan</a>
        </div>
    </div>
    <?php show_flash(); ?>
    <div class="ks-table table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Artisan</th><th>Skill</th><th>District</th><th class="text-center">Exp.</th><th class="text-center">Teaching</th><th class="text-center">Products</th><th class="text-center">Views</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($artisans as $a): ?>
                <tr>
                    <td><div class="d-flex align-items-center gap-2"><?= artisan_avatar($a, $root, 'sm') ?><strong><?= e($a['artisan_name']) ?></strong><?php if ($a['account_id']): ?> <span class="ks-badge" title="Registered through the website – has an artisan login (account #<?= (int) $a['account_id'] ?>)"><i class="bi bi-person-check"></i> Account</span><?php endif; ?></div></td>
                    <td><?= e($a['skill_name']) ?></td>
                    <td><?= e($a['district_name']) ?></td>
                    <td class="text-center"><?= (int) $a['experience'] ?></td>
                    <td class="text-center"><?= $a['accepts_apprentices'] ? '<i class="bi bi-check-circle-fill text-success" title="Yes"></i>' : '<i class="bi bi-dash-circle text-muted" title="No"></i>' ?></td>
                    <td class="text-center"><?= (int) $a['product_count'] ?></td>
                    <td class="text-center"><?= (int) $a['profile_views'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="artisans.php?action=edit&id=<?= (int) $a['artisan_id'] ?>" class="btn btn-sm btn-outline-maroon"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="post" action="artisans.php" class="d-inline" data-confirm="Delete '<?= e($a['artisan_name']) ?>'? Their products and visitor interests will also be deleted.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $a['artisan_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$artisans): ?><tr><td colspan="8" class="text-center text-muted-ks py-4">No artisans found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
